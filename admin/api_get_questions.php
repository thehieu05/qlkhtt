<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$exam_id   = (int)($_GET['exam_id'] ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);

// Lấy danh sách ID câu hỏi đã có trong đề thi này
$selected_ids = [];
if ($exam_id > 0) {
    $res = $conn->query("SELECT question_id FROM exam_questions WHERE exam_id = $exam_id");
    if ($res) {
        while ($r = $res->fetch_assoc()) $selected_ids[] = (int)$r['question_id'];
    }
}

// Lấy toàn bộ câu hỏi (ưu tiên theo khóa học của đề thi)
$sql = "SELECT id, question_text, correct_answer, difficulty FROM questions";
if ($course_id > 0) {
    $sql .= " WHERE course_id = $course_id";
}
$sql .= " ORDER BY id DESC";

$res = $conn->query($sql);
$questions = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['is_selected'] = in_array((int)$row['id'], $selected_ids);
        $questions[] = $row;
    }
}

echo json_encode(['questions' => $questions], JSON_UNESCAPED_UNICODE);

