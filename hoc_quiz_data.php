<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Chưa đăng nhập']);
    exit();
}

$user_id = current_user_id();
$quiz_id = (int)($_GET['quiz_id'] ?? $_GET['exam_id'] ?? 0);
if ($quiz_id <= 0) {
    echo json_encode(['error' => 'Bài kiểm tra không hợp lệ']);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND status = 'published' LIMIT 1");
$stmt->bind_param('i', $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    echo json_encode(['error' => 'Bài kiểm tra không tồn tại hoặc đã bị ẩn']);
    exit();
}

if (!is_admin() && !has_course_access($conn, $user_id, (int)$quiz['course_id'])) {
    echo json_encode(['error' => 'Bạn chưa được cấp quyền học khóa học này']);
    exit();
}

$stmt_att = $conn->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND quiz_id = ?");
$stmt_att->bind_param('ii', $user_id, $quiz_id);
$stmt_att->execute();
$used = (int)($stmt_att->get_result()->fetch_row()[0] ?? 0);
$stmt_att->close();

if ($used >= (int)$quiz['max_attempts']) {
    echo json_encode(['error' => 'Bạn đã hết lượt làm bài kiểm tra này']);
    exit();
}

$questions = [];
$stmt_q = $conn->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM questions WHERE quiz_id = ? ORDER BY sort_order ASC, id ASC");
$stmt_q->bind_param('i', $quiz_id);
$stmt_q->execute();
$q_res = $stmt_q->get_result();
while ($q = $q_res->fetch_assoc()) {
    $questions[] = $q;
}
$stmt_q->close();

echo json_encode([
    'exam' => [
        'id'               => (int)$quiz['id'],
        'title'            => $quiz['title'],
        'duration_minutes' => (int)$quiz['duration_minutes'],
        'pass_score'       => (int)$quiz['pass_score'],
        'attempts_left'    => (int)$quiz['max_attempts'] - $used,
    ],
    'questions' => $questions,
], JSON_UNESCAPED_UNICODE);
