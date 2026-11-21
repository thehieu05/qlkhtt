<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action    = $_GET['action'] ?? '';
$course_id = (int)($_GET['course_id'] ?? 0);
$chapter_id= (int)($_GET['chapter_id'] ?? 0);
$exam_id   = (int)($_GET['exam_id'] ?? 0);

// 1. Lấy danh sách chương của khóa học kèm số câu hỏi
if ($action === 'chapters') {
    if ($course_id <= 0) {
        echo json_encode([]);
        exit();
    }
    $res = $conn->query("
        SELECT ch.id, ch.title, ch.sort_order,
               (SELECT COUNT(*) FROM questions q WHERE q.chapter_id = ch.id) as question_count
        FROM chapters ch
        WHERE ch.course_id = $course_id
        ORDER BY ch.sort_order ASC, ch.id ASC
    ");
    $chapters = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $chapters[] = [
                'id' => (int)$r['id'],
                'title' => $r['title'],
                'question_count' => (int)$r['question_count']
            ];
        }
    }
    echo json_encode($chapters, JSON_UNESCAPED_UNICODE);
    exit();
}

// 2. Lấy danh sách bài học của chương kèm số câu hỏi
if ($action === 'lessons') {
    if ($chapter_id <= 0) {
        echo json_encode([]);
        exit();
    }
    $res = $conn->query("
        SELECT l.id, l.title, l.sort_order,
               (SELECT COUNT(*) FROM questions q WHERE q.lesson_id = l.id) as question_count
        FROM lessons l
        WHERE l.chapter_id = $chapter_id
        ORDER BY l.sort_order ASC, l.id ASC
    ");
    $lessons = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $lessons[] = [
                'id' => (int)$r['id'],
                'title' => $r['title'],
                'question_count' => (int)$r['question_count']
            ];
        }
    }
    echo json_encode($lessons, JSON_UNESCAPED_UNICODE);
    exit();
}

// 3. Mặc định: Lấy danh sách câu hỏi cho đề thi (ql_thi.php)
$selected_ids = [];
if ($exam_id > 0) {
    $res = $conn->query("SELECT question_id FROM exam_questions WHERE exam_id = $exam_id");
    if ($res) {
        while ($r = $res->fetch_assoc()) $selected_ids[] = (int)$r['question_id'];
    }
}

$sql = "SELECT id, question_text, correct_answer, difficulty, chapter_id, lesson_id FROM questions";
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
