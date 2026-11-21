<?php
/**
 * hoc_quiz_submit.php
 * Nhận POST, chấm điểm inline quiz, trả JSON kết quả
 */
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập']);
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$exam_id   = (int)($_POST['exam_id']   ?? 0);
$course_id = (int)($_POST['course_id'] ?? 0);
$start_time= (int)($_POST['start_time'] ?? time());

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

// Lấy câu hỏi để chấm
$q_res = $conn->query("SELECT q.id, q.correct_answer
    FROM exam_questions eq
    JOIN questions q ON q.id = eq.question_id
    WHERE eq.exam_id = $exam_id");

$to_grade = [];
if ($q_res) {
    while ($qr = $q_res->fetch_assoc()) {
        $to_grade[$qr['id']] = strtoupper($qr['correct_answer']);
    }
}

$total_q   = count($to_grade);
$correct_n = 0;
$user_ans_map = [];

foreach ($to_grade as $qid => $correct_ans) {
    $user_ans = strtoupper(trim($_POST['answer_' . $qid] ?? ''));
    $user_ans_map[$qid] = $user_ans;
    if ($user_ans === $correct_ans) $correct_n++;
}

$score      = $total_q > 0 ? round(($correct_n / $total_q) * 100) : 0;
$passed     = $score >= (int)$exam['pass_score'] ? 1 : 0;
$time_taken = max(0, time() - $start_time);

// Lưu kết quả
$conn->query("INSERT INTO exam_results (exam_id, user_id, score, total_questions, correct_answers, time_taken_seconds, passed)
    VALUES ($exam_id, $user_id, $score, $total_q, $correct_n, $time_taken, $passed)");
$result_id = (int)$conn->insert_id;

// Lưu từng câu
foreach ($to_grade as $qid => $correct_ans) {
    $ua = $conn->real_escape_string($user_ans_map[$qid] ?? '');
    $is_correct = ($user_ans_map[$qid] === $correct_ans) ? 1 : 0;
    $conn->query("INSERT INTO exam_answers (result_id, question_id, user_answer, is_correct) VALUES ($result_id, $qid, '$ua', $is_correct)");
}

// Lấy chi tiết để trả về
$detail_res = $conn->query("SELECT ea.question_id, ea.user_answer, ea.is_correct,
    q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.explanation
    FROM exam_answers ea
    JOIN questions q ON q.id = ea.question_id
    WHERE ea.result_id = $result_id
    ORDER BY ea.id ASC");

$detail = [];
if ($detail_res) {
    while ($d = $detail_res->fetch_assoc()) {
        $detail[] = [
            'question_id'    => (int)$d['question_id'],
            'question_text'  => $d['question_text'],
            'option_a'       => $d['option_a'],
            'option_b'       => $d['option_b'],
            'option_c'       => $d['option_c'],
            'option_d'       => $d['option_d'],
            'user_answer'    => $d['user_answer'],
            'correct_answer' => strtoupper($d['correct_answer']),
            'is_correct'     => (int)$d['is_correct'],
            'explanation'    => $d['explanation'],
        ];
    }
}

$att_now = $att_used + 1;
$att_left = max(0, (int)$exam['max_attempts'] - $att_now);

echo json_encode([
    'ok'          => true,
    'result_id'   => $result_id,
    'score'       => $score,
    'passed'      => $passed,
    'correct'     => $correct_n,
    'total'       => $total_q,
    'time_taken'  => $time_taken,
    'attempts_left'=> $att_left,
    'exam'        => [
        'id'         => (int)$exam['id'],
        'title'      => $exam['title'],
        'pass_score' => (int)$exam['pass_score'],
    ],
    'detail'      => $detail,
]);
