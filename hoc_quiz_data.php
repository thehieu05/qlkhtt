<?php
/**
 * hoc_quiz_data.php
 * Trả về JSON câu hỏi của một đề thi để hiển thị inline trong hoc.php
 */
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập']);
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$exam_id   = (int)($_GET['exam_id']   ?? 0);
$lesson_id = (int)($_GET['lesson_id'] ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);

if ($exam_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Thiếu exam_id']);
    exit();
}

// Lấy thông tin đề thi
$exam_res = $conn->query("SELECT * FROM exams WHERE id = $exam_id AND is_active = 1");
if (!$exam_res || $exam_res->num_rows === 0) {
    echo json_encode(['ok' => false, 'msg' => 'Đề thi không tồn tại']);
    exit();
}
$exam = $exam_res->fetch_assoc();

// Kiểm tra số lần thi
$att_res  = $conn->query("SELECT COUNT(*) FROM exam_results WHERE user_id = $user_id AND exam_id = $exam_id");
$att_used = $att_res ? (int)$att_res->fetch_row()[0] : 0;
if ($att_used >= (int)$exam['max_attempts']) {
    echo json_encode(['ok' => false, 'msg' => 'Đã hết lượt thi']);
    exit();
}

// Lấy câu hỏi
$q_res = $conn->query("SELECT q.*, eq.sort_order
    FROM exam_questions eq
    JOIN questions q ON q.id = eq.question_id
    WHERE eq.exam_id = $exam_id
    ORDER BY eq.sort_order ASC");

$questions = [];
if ($q_res) {
    while ($q = $q_res->fetch_assoc()) {
        $questions[] = [
            'id'            => (int)$q['id'],
            'question_text' => $q['question_text'],
            'option_a'      => $q['option_a'],
            'option_b'      => $q['option_b'],
            'option_c'      => $q['option_c'],
            'option_d'      => $q['option_d'],
        ];
    }
}

// Xáo câu hỏi nếu cần
if ((int)$exam['shuffle_questions'] === 1) shuffle($questions);

echo json_encode([
    'ok'        => true,
    'exam'      => [
        'id'               => (int)$exam['id'],
        'title'            => $exam['title'],
        'duration_minutes' => (int)$exam['duration_minutes'],
        'pass_score'       => (int)$exam['pass_score'],
        'max_attempts'     => (int)$exam['max_attempts'],
    ],
    'questions' => $questions,
    'attempts_used' => $att_used,
    'attempts_left' => max(0, (int)$exam['max_attempts'] - $att_used),
]);
