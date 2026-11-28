<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Chưa đăng nhập']);
    exit();
}

$user_id = current_user_id();
$quiz_id = (int)($_POST['quiz_id'] ?? $_POST['exam_id'] ?? 0);
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
    echo json_encode(['error' => 'Bài kiểm tra không tồn tại']);
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
    echo json_encode(['error' => 'Đã hết lượt làm bài']);
    exit();
}

$questions = [];
$stmt_q = $conn->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation FROM questions WHERE quiz_id = ? ORDER BY sort_order ASC, id ASC");
$stmt_q->bind_param('i', $quiz_id);
$stmt_q->execute();
$q_res = $stmt_q->get_result();
while ($q = $q_res->fetch_assoc()) {
    $questions[] = $q;
}
$stmt_q->close();

$submitted = $_POST['answers'] ?? [];
$total     = count($questions);
$correct   = 0;
$details   = [];

foreach ($questions as $q) {
    $qid  = (int)$q['id'];
    $uans = strtoupper(trim($submitted[$qid] ?? ''));
    $cans = strtoupper(trim($q['correct_answer']));
    $ok   = ($uans !== '' && $uans === $cans) ? 1 : 0;
    if ($ok) $correct++;
    $details[] = [
        'question_id'    => $qid,
        'question_text'  => $q['question_text'],
        'user_answer'    => $uans,
        'correct_answer' => $cans,
        'is_correct'     => $ok,
        'explanation'    => $q['explanation'] ?? '',
        'options'        => [
            'A' => $q['option_a'],
            'B' => $q['option_b'],
            'C' => $q['option_c'],
            'D' => $q['option_d'],
        ],
    ];
}

$score      = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
$passed     = $score >= (int)$quiz['pass_score'] ? 1 : 0;
$start_ts   = (int)($_POST['start_time'] ?? time());
$started_at = date('Y-m-d H:i:s', $start_ts);

$stmt_ins = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, user_id, score, correct_count, total_questions, passed, status, started_at, submitted_at)
    VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW())");
$stmt_ins->bind_param('iidiiis', $quiz_id, $user_id, $score, $correct, $total, $passed, $started_at);
$stmt_ins->execute();
$attempt_id = (int)$stmt_ins->insert_id;
$stmt_ins->close();

$stmt_qa = $conn->prepare("INSERT INTO quiz_answers (attempt_id, question_id, selected_answer, is_correct) VALUES (?, ?, ?, ?)");
foreach ($details as $d) {
    $sel = in_array($d['user_answer'], ['A', 'B', 'C', 'D'], true) ? $d['user_answer'] : null;
    $stmt_qa->bind_param('iisi', $attempt_id, $d['question_id'], $sel, $d['is_correct']);
    $stmt_qa->execute();
}
$stmt_qa->close();

echo json_encode([
    'ok'            => true,
    'result_id'     => $attempt_id,
    'score'         => $score,
    'correct'       => $correct,
    'total'         => $total,
    'passed'        => $passed,
    'pass_score'    => (int)$quiz['pass_score'],
    'attempts_left' => max(0, (int)$quiz['max_attempts'] - $used - 1),
    'details'       => $details,
], JSON_UNESCAPED_UNICODE);
