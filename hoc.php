<?php
require_once 'config.php';

// Yêu cầu đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$course_id = (int)($_GET['course_id'] ?? 0);
$lesson_id = (int)($_GET['lesson_id'] ?? 0);

if ($course_id <= 0) { header("Location: index.php"); exit(); }

// Lấy thông tin khóa học
$course_res = $conn->query("SELECT * FROM courses WHERE id = $course_id AND is_active = 1");
if (!$course_res || $course_res->num_rows === 0) { header("Location: index.php"); exit(); }
$course = $course_res->fetch_assoc();

// Tự động đăng ký học nếu chưa có
$enroll_check = $conn->query("SELECT id FROM enrollments WHERE user_id = $user_id AND course_id = $course_id");
if (!$enroll_check || $enroll_check->num_rows === 0) {
    $conn->query("INSERT IGNORE INTO enrollments (user_id, course_id, progress_percent, status) VALUES ($user_id, $course_id, 0, 'active')");
}

// Lấy tất cả chương & bài giảng kèm tiến độ
$chapters = [];
$ch_res = $conn->query("SELECT * FROM chapters WHERE course_id = $course_id ORDER BY sort_order ASC, id ASC");
$total_lessons = 0;
$completed_lessons = 0;
if ($ch_res) {
    while ($ch = $ch_res->fetch_assoc()) {
        $ch_id = (int)$ch['id'];
        $ls_res = $conn->query("SELECT l.*,
            COALESCE(lp.completed, 0) as is_completed,
            COALESCE(lp.last_position_seconds, 0) as last_pos
            FROM lessons l
            LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = $user_id
            WHERE l.chapter_id = $ch_id
            ORDER BY l.sort_order ASC, l.id ASC");
        $lessons = [];
        if ($ls_res) {
            while ($ls = $ls_res->fetch_assoc()) {
                $lessons[] = $ls;
                $total_lessons++;
                if ($ls['is_completed']) $completed_lessons++;
            }
        }
        $ch['lessons'] = $lessons;
        $chapters[] = $ch;
    }
}

// Chọn bài đầu tiên nếu chưa chọn
if ($lesson_id <= 0 && !empty($chapters)) {
    foreach ($chapters as $ch) {
        if (!empty($ch['lessons'])) { $lesson_id = (int)$ch['lessons'][0]['id']; break; }
    }
}

// Tính prev/next
$all_lesson_ids = [];
foreach ($chapters as $ch) foreach ($ch['lessons'] as $ls) $all_lesson_ids[] = (int)$ls['id'];
$prev_lesson_id = 0; $next_lesson_id = 0;
foreach ($all_lesson_ids as $idx => $lid) {
    if ($lid === $lesson_id) {
        $prev_lesson_id = $idx > 0 ? $all_lesson_ids[$idx - 1] : 0;
        $next_lesson_id = $idx < count($all_lesson_ids) - 1 ? $all_lesson_ids[$idx + 1] : 0;
    }
}

// Lấy bài học hiện tại
$current_lesson = null;
if ($lesson_id > 0) {
    $ls_q = $conn->query("SELECT l.*,
        COALESCE(lp.completed, 0) as is_completed,
        COALESCE(lp.last_position_seconds, 0) as last_pos,
        COALESCE(lp.watch_seconds, 0) as watch_secs
        FROM lessons l
        LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = $user_id
        WHERE l.id = $lesson_id AND l.course_id = $course_id");
    if ($ls_q && $ls_q->num_rows > 0) $current_lesson = $ls_q->fetch_assoc();
}

// Ghi chú bài học hiện tại
$notes = [];
if ($lesson_id > 0) {
    $notes_res = $conn->query("SELECT * FROM lesson_notes WHERE user_id = $user_id AND lesson_id = $lesson_id ORDER BY video_time_seconds ASC, created_at ASC");
    if ($notes_res) while ($n = $notes_res->fetch_assoc()) $notes[] = $n;
}

// Q&A bài học hiện tại
$qa_posts = [];
if ($lesson_id > 0) {
    $qa_res = $conn->query("SELECT q.*, u.fullname, u.role
        FROM qa_posts q LEFT JOIN users u ON u.id = q.user_id
        WHERE q.lesson_id = $lesson_id AND q.parent_id = 0
        ORDER BY q.created_at DESC LIMIT 20");
    if ($qa_res) {
        while ($qa = $qa_res->fetch_assoc()) {
            $qa_id = (int)$qa['id'];
            $rep_res = $conn->query("SELECT q.*, u.fullname, u.role FROM qa_posts q LEFT JOIN users u ON u.id = q.user_id WHERE q.parent_id = $qa_id ORDER BY q.created_at ASC");
            $qa['replies'] = [];
            if ($rep_res) while ($r = $rep_res->fetch_assoc()) $qa['replies'][] = $r;
            $qa_posts[] = $qa;
        }
    }
}

// Lấy đề thi gắn với bài học này (lesson_id)
$lesson_exams = [];
if ($lesson_id > 0) {
    $le_res = $conn->query("SELECT e.*,
        (SELECT COUNT(*) FROM exam_results WHERE exam_id = e.id AND user_id = $user_id) as attempts_used,
        (SELECT score FROM exam_results WHERE exam_id = e.id AND user_id = $user_id ORDER BY id DESC LIMIT 1) as last_score,
        (SELECT passed FROM exam_results WHERE exam_id = e.id AND user_id = $user_id ORDER BY id DESC LIMIT 1) as last_passed
        FROM exams e
        WHERE e.lesson_id = $lesson_id AND e.is_active = 1
        ORDER BY e.id ASC");
    if ($le_res) while ($ex = $le_res->fetch_assoc()) $lesson_exams[] = $ex;
}

// Tiến độ tổng thể
$progress_percent = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;
$conn->query("UPDATE enrollments SET progress_percent = $progress_percent WHERE user_id = $user_id AND course_id = $course_id");

// ===== AJAX HANDLERS =====
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $act = $_GET['ajax'];

    if ($act === 'save_progress' && isset($_POST['lesson_id'])) {
        $ls_id = (int)$_POST['lesson_id'];
        $pos = (int)($_POST['position'] ?? 0);
        $watch = (int)($_POST['watch_seconds'] ?? 0);
        $completed = isset($_POST['completed']) ? 1 : 0;
        $completed_at = $completed ? "'" . date('Y-m-d H:i:s') . "'" : 'NULL';
        $conn->query("INSERT INTO lesson_progress (user_id, lesson_id, course_id, completed, watch_seconds, last_position_seconds, completed_at)
            VALUES ($user_id, $ls_id, $course_id, $completed, $watch, $pos, $completed_at)
            ON DUPLICATE KEY UPDATE
            completed = IF($completed = 1, 1, completed),
            watch_seconds = GREATEST(watch_seconds, $watch),
            last_position_seconds = $pos,
            completed_at = IF($completed = 1 AND completed_at IS NULL, NOW(), completed_at),
            updated_at = NOW()");
        $total_q = $conn->query("SELECT COUNT(*) FROM lessons WHERE course_id = $course_id")->fetch_row()[0] ?? 0;
        $done_q  = $conn->query("SELECT COUNT(*) FROM lesson_progress WHERE user_id = $user_id AND course_id = $course_id AND completed = 1")->fetch_row()[0] ?? 0;
        $pct = $total_q > 0 ? round(($done_q / $total_q) * 100) : 0;
        $conn->query("UPDATE enrollments SET progress_percent = $pct WHERE user_id = $user_id AND course_id = $course_id");
        echo json_encode(['ok' => true, 'progress' => $pct, 'completed' => $done_q, 'total' => $total_q]);
        exit();
    }

    if ($act === 'add_note' && isset($_POST['lesson_id'], $_POST['note_text'])) {
        $ls_id = (int)$_POST['lesson_id'];
        $time_sec = (int)($_POST['video_time'] ?? 0);
        $text = $conn->real_escape_string(trim($_POST['note_text']));
        if (!empty($text)) {
            $conn->query("INSERT INTO lesson_notes (user_id, lesson_id, course_id, note_text, video_time_seconds) VALUES ($user_id, $ls_id, $course_id, '$text', $time_sec)");
            $note = $conn->query("SELECT * FROM lesson_notes WHERE id = " . $conn->insert_id)->fetch_assoc();
            echo json_encode(['ok' => true, 'note' => $note]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Nội dung không được trống.']);
        }
        exit();
    }

    if ($act === 'delete_note' && isset($_POST['note_id'])) {
        $conn->query("DELETE FROM lesson_notes WHERE id = " . (int)$_POST['note_id'] . " AND user_id = $user_id");
        echo json_encode(['ok' => true]);
        exit();
    }

    if ($act === 'post_qa' && isset($_POST['lesson_id'], $_POST['content'])) {
        $ls_id = (int)$_POST['lesson_id'];
        $parent = (int)($_POST['parent_id'] ?? 0);
        $content = $conn->real_escape_string(trim($_POST['content']));
        if (!empty($content)) {
            $conn->query("INSERT INTO qa_posts (lesson_id, course_id, user_id, parent_id, content) VALUES ($ls_id, $course_id, $user_id, $parent, '$content')");
            $qa = $conn->query("SELECT q.*, u.fullname, u.role FROM qa_posts q LEFT JOIN users u ON u.id = q.user_id WHERE q.id = " . $conn->insert_id)->fetch_assoc();
            echo json_encode(['ok' => true, 'post' => $qa]);
        } else {
            echo json_encode(['ok' => false]);
        }
        exit();
    }

    echo json_encode(['ok' => false]);
    exit();
}

$page_title = ($current_lesson ? htmlspecialchars($current_lesson['title']) . ' – ' : '') . htmlspecialchars($course['title']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> | EduNova</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* =========================================================
   EDUNOVA LEARN PAGE  –  Custom dark-teal theme
   ========================================================= */
:root {
    --bg:       #0a0e1a;
    --bg2:      #111827;
    --bg3:      #1a2235;
    --bg4:      #1e293b;
    --accent:   #14b8a6;   /* teal */
    --accent2:  #06b6d4;   /* cyan */
    --indigo:   #818cf8;
    --success:  #22c55e;
    --warning:  #f59e0b;
    --danger:   #ef4444;
    --text:     #e2e8f0;
    --muted:    #64748b;
    --border:   #1e293b;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; overflow: hidden; }

/* ===== TOPBAR ===== */
.topbar {
    position: fixed; top: 0; left: 0; right: 0; height: 54px; z-index: 1000;
    background: var(--bg2); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; padding: 0 16px; gap: 12px;
}
.topbar .brand { font-weight: 800; font-size: 1.1rem; color: #fff; text-decoration: none; white-space: nowrap; letter-spacing: -0.02em; }
.topbar .brand em { color: var(--accent); font-style: normal; }
.topbar .course-name { flex: 1; font-size: 0.82rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.topbar .prog-wrap { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.topbar .prog-track { width: 120px; height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; }
.topbar .prog-fill  { height: 100%; background: linear-gradient(90deg, var(--accent), var(--accent2)); border-radius: 3px; transition: width .4s; }
.topbar .prog-label { font-size: 0.72rem; color: var(--muted); white-space: nowrap; }
.topbar .tbtn {
    background: transparent; border: 1px solid var(--border); color: var(--muted);
    border-radius: 7px; padding: 5px 11px; font-size: 0.78rem; cursor: pointer;
    display: flex; align-items: center; gap: 5px; white-space: nowrap; transition: all .15s;
}
.topbar .tbtn:hover  { background: var(--bg3); color: var(--text); }
.topbar .tbtn.active { background: var(--accent); border-color: var(--accent); color: #fff; }

/* ===== LAYOUT ===== */
.app { display: flex; height: 100vh; padding-top: 54px; }

/* ===== SIDEBAR ===== */
.sidebar {
    width: 320px; flex-shrink: 0;
    background: var(--bg2); border-left: 1px solid var(--border);
    display: flex; flex-direction: column; overflow: hidden;
    transition: width .25s ease; position: relative; z-index: 30;
}
.sidebar.closed { width: 0; }
.sidebar-inner { overflow-y: auto; flex: 1; }
.sidebar-inner::-webkit-scrollbar { width: 4px; }
.sidebar-inner::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

.sbar-head {
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0; background: var(--bg2); z-index: 5;
}
.sbar-head h6 { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 8px; }
.sbar-prog { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; margin-bottom: 5px; }
.sbar-prog-fill { height: 100%; background: linear-gradient(90deg, var(--accent), var(--accent2)); border-radius: 2px; }
.sbar-prog-txt { font-size: 0.72rem; color: var(--muted); display: flex; justify-content: space-between; }

.ch-title {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; cursor: pointer;
    background: var(--bg); border-bottom: 1px solid var(--border);
    font-size: 0.78rem; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .04em;
    transition: background .15s; user-select: none;
}
.ch-title:hover { background: var(--bg3); }
.ch-title.open  { color: var(--accent); background: rgba(20,184,166,.06); }
.ch-meta { font-size: 0.68rem; font-weight: 400; color: var(--muted); text-transform: none; letter-spacing: 0; }
.ch-lessons { overflow: hidden; }

.ls-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 16px 9px 20px;
    text-decoration: none; cursor: pointer;
    border-left: 3px solid transparent;
    transition: background .12s;
}
.ls-item:hover { background: var(--bg3); }
.ls-item.active  { background: rgba(20,184,166,.1); border-left-color: var(--accent); }
.ls-item.done    { }
.ls-icon { font-size: 0.95rem; flex-shrink: 0; color: var(--muted); }
.ls-item.active  .ls-icon { color: var(--accent); }
.ls-item.done    .ls-icon { color: var(--success); }
.ls-name { font-size: 0.8rem; color: var(--muted); flex: 1; line-height: 1.4; }
.ls-item.active  .ls-name { color: var(--text); font-weight: 600; }
.ls-dur  { font-size: 0.7rem; color: #374151; flex-shrink: 0; }

.quiz-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 16px 9px 20px;
    text-decoration: none; cursor: pointer;
    border-left: 3px solid transparent;
    transition: background .12s;
    background: rgba(99,102,241,.04);
}
.quiz-item:hover { background: rgba(99,102,241,.12); }
.quiz-item .qi-icon { color: var(--indigo); font-size: 0.9rem; flex-shrink: 0; }
.quiz-item .qi-name { font-size: 0.78rem; color: var(--muted); flex: 1; }
.quiz-item .qi-badge { font-size: 0.65rem; padding: 1px 7px; border-radius: 10px; white-space: nowrap; }

/* ===== MAIN AREA ===== */
.main { flex: 1; min-width: 0; min-height: 0; display: flex; flex-direction: column; overflow: hidden; position: relative; z-index: 1; }

/* Video zone - Thiết kế chính xác không tràn layout */
.video-zone {
    background: #000; flex-shrink: 0;
    width: 100%; height: 52vh; max-height: 52vh;
    position: relative; overflow: hidden;
    display: flex; justify-content: center; align-items: center;
    z-index: 1;
}
.video-zone .video-container {
    width: 100%; height: 100%; max-width: 100%;
    position: relative; overflow: hidden;
    background: #000;
}
.video-zone video, .video-zone iframe {
    width: 100%; height: 100%;
    background: #000; border: none;
    display: block; object-fit: contain;
}
.no-video {
    background: linear-gradient(135deg, #0a0e1a 0%, #111827 100%);
    width: 100%; height: 100%; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 10px; color: var(--muted);
}
.no-video i { font-size: 3rem; }

/* Info strip */
.info-strip {
    background: var(--bg2); border-bottom: 1px solid var(--border);
    padding: 10px 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    position: relative; z-index: 20; flex-shrink: 0;
}
.info-strip h5 { font-size: 0.95rem; font-weight: 700; color: var(--text); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.info-strip .meta { font-size: 0.75rem; color: var(--muted); display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.badge-free { background: rgba(20,184,166,.15); color: var(--accent); border-radius: 4px; padding: 1px 7px; font-size: 0.7rem; font-weight: 600; }
.btn-done {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border: none; color: #fff; border-radius: 7px; padding: 6px 16px;
    font-size: 0.8rem; font-weight: 700; cursor: pointer; white-space: nowrap;
    display: flex; align-items: center; gap: 5px; transition: opacity .2s;
    user-select: none;
}
.btn-done:hover { opacity: .88; }
.btn-done.done-state { background: linear-gradient(135deg, #22c55e, #16a34a); }

/* Tabs */
.tab-nav {
    display: flex; background: var(--bg2); border-bottom: 1px solid var(--border);
    overflow-x: auto; flex-shrink: 0;
    position: relative; z-index: 20;
}
.tab-nav::-webkit-scrollbar { height: 2px; }
.tab-btn {
    background: none; border: none; color: var(--muted);
    padding: 10px 18px; font-size: 0.82rem; font-weight: 500; cursor: pointer;
    border-bottom: 2px solid transparent; white-space: nowrap;
    display: flex; align-items: center; gap: 6px; transition: all .15s;
    user-select: none;
}
.tab-btn:hover  { color: var(--text); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
.tab-btn .cnt   { background: var(--bg3); border-radius: 10px; padding: 1px 7px; font-size: 0.7rem; }
.tab-btn.quiz-tab.active { color: var(--indigo); border-bottom-color: var(--indigo); }

/* Scroll area */
.scroll-area { flex: 1; min-height: 0; overflow-y: auto; padding: 20px; background: var(--bg); position: relative; z-index: 10; }
.scroll-area::-webkit-scrollbar { width: 5px; }
.scroll-area::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

/* ===== TAB CONTENT ===== */
.tab-pane { display: none !important; }
.tab-pane.show, .tab-pane.active { display: block !important; }

/* Content */
.content-body { color: #94a3b8; font-size: 0.9rem; line-height: 1.8; }
.content-body h4 { color: var(--text); }

/* Nav bar */
.nav-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-top: 20px; border-top: 1px solid var(--border); margin-top: 20px; }
.btn-nav {
    background: var(--bg3); border: 1px solid var(--border); color: var(--muted);
    border-radius: 8px; padding: 8px 18px; font-size: 0.82rem; cursor: pointer;
    display: flex; align-items: center; gap: 6px; text-decoration: none; transition: all .15s;
}
.btn-nav:hover { background: var(--bg4); color: var(--text); }
.btn-nav:disabled { opacity: .4; cursor: not-allowed; }
.btn-nav-next {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border: none; color: #fff; border-radius: 8px; padding: 8px 20px;
    font-size: 0.82rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 6px; text-decoration: none; transition: opacity .15s;
}
.btn-nav-next:hover { opacity: .88; color: #fff; }

/* Notes */
.note-card {
    background: var(--bg2); border: 1px solid var(--border); border-radius: 9px;
    padding: 11px 14px; margin-bottom: 10px; transition: border-color .15s;
}
.note-card:hover { border-color: var(--accent); }
.note-time { font-size: 0.72rem; color: var(--accent); font-weight: 600; cursor: pointer; }
.note-time:hover { text-decoration: underline; }
.note-text { color: #94a3b8; font-size: 0.85rem; margin: 5px 0 0; line-height: 1.6; }
.note-form textarea { background: var(--bg2); border: 1px solid var(--border); color: var(--text); border-radius: 8px; resize: none; width: 100%; }
.note-form textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px rgba(20,184,166,.2); }
.note-form textarea::placeholder { color: var(--muted); }

/* Q&A */
.qa-card {
    background: var(--bg2); border: 1px solid var(--border); border-radius: 9px;
    padding: 14px; margin-bottom: 12px;
}
.qa-av {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--accent), var(--indigo));
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.8rem; color: #fff;
}
.qa-av.admin { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #111; }
.qa-name { font-weight: 600; font-size: 0.85rem; }
.qa-time { font-size: 0.72rem; color: var(--muted); }
.qa-body { color: #94a3b8; font-size: 0.85rem; margin-top: 7px; line-height: 1.6; }
.qa-reply-wrap { margin-left: 44px; margin-top: 8px; }
.qa-reply-wrap .qa-card { background: rgba(20,184,166,.03); border-color: rgba(30,41,59,.8); }
.qa-form-area { background: var(--bg2); border: 1px solid var(--border); color: var(--text); border-radius: 8px; resize: none; width: 100%; padding: 10px 12px; font-size: 0.85rem; }
.qa-form-area:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px rgba(20,184,166,.2); }
.qa-form-area::placeholder { color: var(--muted); }

/* ===== QUIZ TAB (inline) ===== */
.quiz-tab-content { }
.ql-exam-card {
    background: var(--bg2); border: 1px solid var(--border); border-radius: 12px;
    padding: 20px; margin-bottom: 16px;
}
.ql-exam-card h6 { font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; }
.ql-exam-desc { font-size: 0.82rem; color: var(--muted); margin-bottom: 14px; }
.ql-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.ql-stat { background: var(--bg3); border-radius: 8px; padding: 7px 14px; display: flex; align-items: center; gap: 7px; font-size: 0.8rem; }
.ql-stat i { color: var(--accent2); }

/* Score badge in sidebar */
.score-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 10px; }
.score-pass { background: rgba(34,197,94,.15); color: var(--success); border: 1px solid rgba(34,197,94,.3); }
.score-fail { background: rgba(239,68,68,.1); color: var(--danger); border: 1px solid rgba(239,68,68,.2); }

/* Quiz running inline */
.inline-quiz-wrap { margin-top: 8px; }
.iq-topbar {
    background: var(--bg3); border: 1px solid var(--border); border-radius: 10px;
    padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px;
    margin-bottom: 14px;
}
.iq-timer { display: flex; align-items: center; gap: 6px; font-weight: 700; color: var(--warning); font-size: 0.95rem; }
.iq-timer.red { color: var(--danger); animation: blink .5s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.5} }
.iq-progress { font-size: 0.8rem; color: var(--muted); }
.iq-q-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 10px; }
.iq-q-num { font-size: 0.72rem; color: var(--accent2); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; }
.iq-q-text { font-size: 0.9rem; font-weight: 600; line-height: 1.6; margin-bottom: 12px; }
.iq-opt {
    display: flex; align-items: center; gap: 10px;
    background: var(--bg3); border: 1.5px solid var(--border); border-radius: 8px;
    padding: 9px 12px; margin-bottom: 7px; cursor: pointer; transition: all .12s;
}
.iq-opt:hover { border-color: var(--accent); background: rgba(20,184,166,.06); }
.iq-opt:has(input:checked) { border-color: var(--accent); background: rgba(20,184,166,.1); }
.iq-opt input { display: none; }
.iq-letter {
    min-width: 28px; height: 28px; border-radius: 50%;
    border: 2px solid var(--border); display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.8rem; color: var(--muted); flex-shrink: 0; transition: all .12s;
}
.iq-opt:has(input:checked) .iq-letter { background: var(--accent); border-color: var(--accent); color: #fff; }
.iq-opt-text { font-size: 0.88rem; }
.iq-actions { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; gap: 10px; }
.btn-submit-iq {
    background: linear-gradient(135deg, var(--indigo), #6366f1);
    color: #fff; border: none; border-radius: 8px; padding: 9px 24px;
    font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: opacity .2s;
}
.btn-submit-iq:hover { opacity: .88; }
.btn-cancel-iq { background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 8px; padding: 9px 16px; font-size: 0.85rem; cursor: pointer; }
.btn-cancel-iq:hover { border-color: var(--danger); color: var(--danger); }
.answered-info { font-size: 0.8rem; color: var(--muted); }

/* Quiz result inline */
.iq-result { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.iq-score-row { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.iq-score-circle {
    width: 80px; height: 80px; border-radius: 50%; flex-shrink: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    border: 4px solid var(--accent); background: var(--bg3);
}
.iq-score-circle.pass { border-color: var(--success); }
.iq-score-circle.fail { border-color: var(--danger); }
.iq-score-num { font-size: 1.3rem; font-weight: 800; }
.iq-score-lbl { font-size: 0.65rem; color: var(--muted); }
.iq-rev-card { background: var(--bg3); border-radius: 8px; padding: 12px; margin-bottom: 8px; border-left: 3px solid var(--border); }
.iq-rev-card.ok  { border-left-color: var(--success); }
.iq-rev-card.bad { border-left-color: var(--danger); }
.iq-rev-opt { font-size: 0.82rem; padding: 5px 10px; border-radius: 6px; margin-top: 5px; }
.iq-rev-opt.user-wrong { background: rgba(239,68,68,.1); color: var(--danger); }
.iq-rev-opt.user-right { background: rgba(34,197,94,.1); color: var(--success); }
.iq-rev-opt.correct    { background: rgba(34,197,94,.08); color: var(--success); }
.iq-expl { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.2); border-radius: 6px; padding: 8px 12px; margin-top: 8px; font-size: 0.8rem; color: #fcd34d; }

/* Utility */
.btn-sm-t { background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 6px; padding: 4px 10px; font-size: 0.75rem; cursor: pointer; }
.btn-sm-t:hover { border-color: var(--accent); color: var(--accent); }
.btn-teal { background: var(--accent); border: none; color: #fff; border-radius: 7px; padding: 7px 16px; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: opacity .15s; display: flex; align-items: center; gap: 6px; }
.btn-teal:hover { opacity: .88; }
.btn-indigo { background: var(--indigo); border: none; color: #fff; border-radius: 7px; padding: 7px 16px; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: opacity .15s; display: flex; align-items: center; gap: 6px; }
.btn-indigo:hover { opacity: .88; }
.empty-state { text-align: center; padding: 36px 0; color: var(--muted); }
.empty-state i { font-size: 2.5rem; }

@media(max-width:768px){
    .sidebar { position: fixed; left: 0; top: 54px; height: calc(100vh - 54px); z-index: 900; transform: translateX(-100%); transition: transform .3s; width: 300px; }
    .sidebar.open { transform: translateX(0); }
    .sidebar.closed { width: 300px; transform: translateX(-100%); }
    .topbar .prog-wrap { display: none; }
}
</style>
</head>
<body>

<!-- ===== TOPBAR ===== -->
<div class="topbar">
    <a href="index.php" class="brand">Edu<em>Nova</em></a>
    <div class="course-name d-none d-md-block"><?= htmlspecialchars($course['title']) ?></div>

    <div class="prog-wrap d-none d-lg-flex">
        <div class="prog-track">
            <div class="prog-fill" id="topProg" style="width:<?= $progress_percent ?>%"></div>
        </div>
        <span class="prog-label" id="topProgLbl"><?= $completed_lessons ?>/<?= $total_lessons ?> bài</span>
    </div>

    <div class="d-flex gap-2 ms-auto align-items-center">
        <button type="button" class="tbtn" id="btnNotes" onclick="switchTab('notes')">
            <i class="bi bi-journal-text"></i><span class="d-none d-md-inline">Ghi chú</span>
        </button>
        <button type="button" class="tbtn" id="btnQA" onclick="switchTab('qa')">
            <i class="bi bi-chat-dots"></i><span class="d-none d-md-inline">Hỏi đáp</span>
        </button>
        <button type="button" class="tbtn active" id="btnSidebar" onclick="toggleSidebar()">
            <i class="bi bi-layout-sidebar-reverse"></i><span class="d-none d-md-inline">Nội dung</span>
        </button>
        <a href="profile.php?tab=khoahoc" class="tbtn d-none d-md-flex" style="text-decoration:none;">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<!-- ===== APP SHELL ===== -->
<div class="app">

    <!-- ===== MAIN ===== -->
    <div class="main" id="mainArea">

        <!-- VIDEO -->
        <div class="video-zone">
            <div class="video-container">
                <?php if ($current_lesson && !empty($current_lesson['video_url'])): ?>
                    <?php $vurl = $current_lesson['video_url']; ?>
                    <?php if (preg_match('/\.(mp4|webm|ogg|mov)$/i', $vurl) || strpos($vurl, 'uploads/') === 0): ?>
                        <video id="lessonVideo" controls preload="metadata"
                               src="<?= htmlspecialchars($vurl) ?>"
                               style="width:100%;height:100%;background:#000;object-fit:contain;">
                            Trình duyệt không hỗ trợ video.
                        </video>
                    <?php else: ?>
                        <?php
                        $embed = $vurl;
                        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i', $vurl, $m))
                            $embed = 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&enablejsapi=1';
                        elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/i', $vurl, $m))
                            $embed = 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&enablejsapi=1';
                        ?>
                        <iframe id="lessonVideo" src="<?= htmlspecialchars($embed) ?>"
                                frameborder="0" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture"
                                allowfullscreen style="width:100%;height:100%;border:none;"></iframe>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-video">
                        <i class="bi bi-file-earmark-richtext"></i>
                        <p style="font-size:.85rem;">Bài học này không có video</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- INFO STRIP -->
        <div class="info-strip">
            <h5><?= $current_lesson ? htmlspecialchars($current_lesson['title']) : 'Chọn bài học để bắt đầu' ?></h5>
            <div class="meta">
                <?php if ($current_lesson): ?>
                    <span><i class="bi bi-clock me-1"></i><?= (int)($current_lesson['duration_minutes'] ?? 0) ?>p</span>
                    <?php if ($current_lesson['is_free'] ?? 0): ?><span class="badge-free">Học thử</span><?php endif; ?>
                    <?php if (!empty($lesson_exams)): ?>
                        <span style="color:var(--indigo);font-size:.75rem;"><i class="bi bi-clipboard-check me-1"></i><?= count($lesson_exams) ?> bài kiểm tra</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ($current_lesson): ?>
            <button type="button" class="btn-done <?= $current_lesson['is_completed'] ? 'done-state' : '' ?>" id="btnDone" onclick="markDone(<?= $lesson_id ?>)">
                <i class="bi bi-<?= $current_lesson['is_completed'] ? 'check-circle-fill' : 'circle' ?>"></i>
                <?= $current_lesson['is_completed'] ? 'Đã hoàn thành' : 'Hoàn thành' ?>
            </button>
            <?php endif; ?>
        </div>

        <!-- TABS -->
        <div class="tab-nav" id="tabNav">
            <button type="button" class="tab-btn active" data-tab="content" onclick="switchTab('content')">
                <i class="bi bi-file-text"></i> Nội dung
            </button>
            <button type="button" class="tab-btn" data-tab="notes" id="tabBtnNotes" onclick="switchTab('notes')">
                <i class="bi bi-journal-text"></i> Ghi chú
                <?php if (!empty($notes)): ?><span class="cnt"><?= count($notes) ?></span><?php endif; ?>
            </button>
            <?php if (!empty($lesson_exams)): ?>
            <button type="button" class="tab-btn quiz-tab" data-tab="quiz" id="tabBtnQuiz" onclick="switchTab('quiz')">
                <i class="bi bi-clipboard2-check"></i> Bài kiểm tra
                <span class="cnt" style="background:rgba(99,102,241,.2);color:var(--indigo);"><?= count($lesson_exams) ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="tab-btn" data-tab="qa" id="tabBtnQA" onclick="switchTab('qa')">
                <i class="bi bi-chat-dots"></i> Hỏi đáp
                <?php if (!empty($qa_posts)): ?><span class="cnt"><?= count($qa_posts) ?></span><?php endif; ?>
            </button>
        </div>

        <!-- SCROLL AREA -->
        <div class="scroll-area">

            <!-- === TAB NỘI DUNG === -->
            <div class="tab-pane show" id="pane-content">
                <div class="content-body">
                    <?php if ($current_lesson && !empty($current_lesson['content'])): ?>
                        <div><?= nl2br(htmlspecialchars($current_lesson['content'])) ?></div>
                    <?php else: ?>
                        <div class="empty-state"><i class="bi bi-journals"></i><p class="mt-2">Bài học chưa có mô tả.</p></div>
                    <?php endif; ?>
                </div>
                <!-- NAV PREV/NEXT -->
                <div class="nav-bar">
                    <?php if ($prev_lesson_id > 0): ?>
                        <a href="hoc.php?course_id=<?= $course_id ?>&lesson_id=<?= $prev_lesson_id ?>" class="btn-nav">
                            <i class="bi bi-arrow-left"></i> Bài trước
                        </a>
                    <?php else: ?>
                        <button class="btn-nav" disabled><i class="bi bi-arrow-left"></i> Bài trước</button>
                    <?php endif; ?>

                    <?php if ($next_lesson_id > 0): ?>
                        <a href="hoc.php?course_id=<?= $course_id ?>&lesson_id=<?= $next_lesson_id ?>" class="btn-nav-next">
                            Bài tiếp theo <i class="bi bi-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <span style="color:var(--success);font-size:.85rem;font-weight:600;"><i class="bi bi-trophy me-1"></i>Hoàn thành khóa học!</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- === TAB GHI CHÚ === -->
            <div class="tab-pane" id="pane-notes">
                <?php if ($current_lesson): ?>
                <div class="note-form mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-journal-plus" style="color:var(--accent)"></i>
                        <span style="font-size:.82rem;color:var(--muted);">Ghi chú tại</span>
                        <span style="background:var(--bg2);border-radius:5px;padding:1px 8px;font-size:.78rem;color:var(--accent);font-weight:700;" id="curTimeDisp">00:00</span>
                    </div>
                    <textarea id="noteText" class="note-form" rows="3" placeholder="Nhập ghi chú... bạn có thể dán link, code, ..."></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span style="font-size:.72rem;color:var(--muted);">Ghi chú riêng tư của bạn</span>
                        <button class="btn-teal" onclick="addNote()"><i class="bi bi-plus-lg"></i>Lưu ghi chú</button>
                    </div>
                </div>
                <div id="notesList">
                    <?php if (empty($notes)): ?>
                    <div class="empty-state" id="emptyNotes"><i class="bi bi-journal"></i><p class="mt-2 small">Chưa có ghi chú. Bấm nút ▶ trên timeline để ghi chú!</p></div>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                        <div class="note-card" id="note-<?= $note['id'] ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="note-time" onclick="seekTo(<?= $note['video_time_seconds'] ?>)">
                                    <i class="bi bi-play-circle me-1"></i><?= gmdate('i:s', (int)$note['video_time_seconds']) ?>
                                </span>
                                <button class="btn-sm-t" onclick="deleteNote(<?= $note['id'] ?>)" title="Xóa"><i class="bi bi-trash3"></i></button>
                            </div>
                            <p class="note-text"><?= nl2br(htmlspecialchars($note['note_text'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="empty-state"><i class="bi bi-journal"></i><p class="mt-2">Chọn bài học để xem ghi chú.</p></div>
                <?php endif; ?>
            </div>

            <!-- === TAB BÀI KIỂM TRA (inline quiz) === -->
            <div class="tab-pane" id="pane-quiz">
                <?php if (empty($lesson_exams)): ?>
                <div class="empty-state"><i class="bi bi-clipboard2"></i><p class="mt-2">Bài học này chưa có bài kiểm tra.</p></div>
                <?php else: ?>
                <div class="quiz-tab-content">
                    <?php foreach ($lesson_exams as $ex): ?>
                    <?php
                    $ex_id      = (int)$ex['id'];
                    $att_used   = (int)$ex['attempts_used'];
                    $att_left   = max(0, (int)$ex['max_attempts'] - $att_used);
                    $last_score = $ex['last_score'];
                    $last_pass  = $ex['last_passed'];
                    // Đếm số câu
                    $q_cnt_r = $conn->query("SELECT COUNT(*) FROM exam_questions WHERE exam_id = $ex_id");
                    $q_cnt   = $q_cnt_r ? (int)$q_cnt_r->fetch_row()[0] : 0;
                    ?>
                    <div class="ql-exam-card" id="examCard_<?= $ex_id ?>">
                        <!-- Header -->
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                            <h6><?= htmlspecialchars($ex['title']) ?></h6>
                            <?php if ($last_score !== null): ?>
                                <?php if ($last_pass): ?>
                                    <span class="score-badge score-pass"><i class="bi bi-check-circle-fill me-1"></i>Đạt <?= $last_score ?>%</span>
                                <?php else: ?>
                                    <span class="score-badge score-fail"><i class="bi bi-x-circle-fill me-1"></i>Chưa đạt <?= $last_score ?>%</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($ex['description'])): ?>
                        <p class="ql-exam-desc"><?= htmlspecialchars($ex['description']) ?></p>
                        <?php endif; ?>

                        <div class="ql-stats">
                            <div class="ql-stat"><i class="bi bi-clock"></i><?= (int)$ex['duration_minutes'] ?> phút</div>
                            <div class="ql-stat"><i class="bi bi-patch-question"></i><?= $q_cnt ?> câu</div>
                            <div class="ql-stat"><i class="bi bi-award"></i>Đạt <?= (int)$ex['pass_score'] ?>%</div>
                            <div class="ql-stat"><i class="bi bi-arrow-repeat" style="color:<?= $att_left > 0 ? 'var(--success)' : 'var(--danger)' ?>"></i>
                                <span style="color:<?= $att_left > 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= $att_left ?> lượt còn</span>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="d-flex flex-wrap gap-2 align-items-center" id="examActions_<?= $ex_id ?>">
                            <?php if ($att_left > 0): ?>
                            <button class="btn-indigo" onclick="startInlineQuiz(<?= $ex_id ?>)">
                                <i class="bi bi-play-fill"></i>
                                <?= $att_used > 0 ? 'Thi lại' : 'Bắt đầu làm bài' ?>
                            </button>
                            <?php else: ?>
                            <span style="font-size:.8rem;color:var(--danger);"><i class="bi bi-x-circle me-1"></i>Hết lượt thi</span>
                            <?php endif; ?>
                            <a href="quiz.php?exam_id=<?= $ex_id ?>&course_id=<?= $course_id ?>" target="_blank" class="btn-sm-t">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Mở toàn màn hình
                            </a>
                        </div>

                        <!-- Inline quiz container (hidden by default) -->
                        <div class="inline-quiz-wrap" id="inlineQuiz_<?= $ex_id ?>" style="display:none;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- === TAB HỎI ĐÁP === -->
            <div class="tab-pane" id="pane-qa">
                <?php if ($current_lesson): ?>
                <div class="mb-4">
                    <textarea class="qa-form-area" id="qaInput" rows="3" placeholder="Đặt câu hỏi về bài học này..."></textarea>
                    <div class="d-flex justify-content-end mt-2">
                        <button class="btn-teal" onclick="postQA(0)"><i class="bi bi-send"></i>Đăng câu hỏi</button>
                    </div>
                </div>
                <div id="qaList">
                    <?php if (empty($qa_posts)): ?>
                    <div class="empty-state" id="emptyQA"><i class="bi bi-chat-square-dots"></i><p class="mt-2 small">Chưa có câu hỏi. Hãy là người đầu tiên!</p></div>
                    <?php else: ?>
                        <?php foreach ($qa_posts as $qa): ?>
                        <div class="qa-card" id="qa-<?= $qa['id'] ?>">
                            <div class="d-flex gap-3 align-items-start">
                                <div class="qa-av <?= ($qa['role'] ?? 0) == 1 ? 'admin' : '' ?>"><?= mb_substr($qa['fullname'] ?? 'U', 0, 1) ?></div>
                                <div class="flex-1 w-100">
                                    <div class="d-flex justify-content-between">
                                        <span class="qa-name"><?= htmlspecialchars($qa['fullname'] ?? 'Học viên') ?>
                                            <?php if (($qa['role'] ?? 0) == 1): ?><span style="color:var(--warning);font-size:.72rem;margin-left:4px;">✦ GV</span><?php endif; ?>
                                        </span>
                                        <span class="qa-time"><?= date('d/m H:i', strtotime($qa['created_at'])) ?></span>
                                    </div>
                                    <p class="qa-body"><?= nl2br(htmlspecialchars($qa['content'])) ?></p>
                                    <button class="btn-sm-t" onclick="toggleReply(<?= $qa['id'] ?>)">
                                        <i class="bi bi-reply me-1"></i>Trả lời (<?= count($qa['replies']) ?>)
                                    </button>
                                    <?php if (!empty($qa['replies'])): ?>
                                    <div class="qa-reply-wrap">
                                        <?php foreach ($qa['replies'] as $rep): ?>
                                        <div class="qa-card">
                                            <div class="d-flex gap-2 align-items-start">
                                                <div class="qa-av <?= ($rep['role'] ?? 0) == 1 ? 'admin' : '' ?>" style="width:28px;height:28px;font-size:.7rem;"><?= mb_substr($rep['fullname'] ?? 'U', 0, 1) ?></div>
                                                <div>
                                                    <span class="qa-name" style="font-size:.8rem;"><?= htmlspecialchars($rep['fullname'] ?? 'Học viên') ?><?php if (($rep['role'] ?? 0) == 1): ?><span style="color:var(--warning);font-size:.7rem;margin-left:4px;">✦ GV</span><?php endif; ?></span>
                                                    <p class="qa-body"><?= nl2br(htmlspecialchars($rep['content'])) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div id="rf-<?= $qa['id'] ?>" style="display:none;margin-top:8px;">
                                        <textarea class="qa-form-area" rows="2" id="ri-<?= $qa['id'] ?>" placeholder="Nhập trả lời..."></textarea>
                                        <div class="d-flex justify-content-end gap-2 mt-1">
                                            <button class="btn-sm-t" onclick="toggleReply(<?= $qa['id'] ?>)">Hủy</button>
                                            <button class="btn-teal" onclick="postReply(<?= $qa['id'] ?>)" style="font-size:.78rem;padding:5px 12px;"><i class="bi bi-send"></i>Gửi</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="empty-state"><i class="bi bi-chat"></i><p class="mt-2">Chọn bài học để xem hỏi đáp.</p></div>
                <?php endif; ?>
            </div>

        </div><!-- /scroll-area -->
    </div><!-- /main -->

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar" id="sidebar">
        <div class="sbar-head">
            <h6><i class="bi bi-collection-play me-1"></i>Nội dung khóa học</h6>
            <div class="sbar-prog">
                <div class="sbar-prog-fill" id="sbarProgFill" style="width:<?= $progress_percent ?>%"></div>
            </div>
            <div class="sbar-prog-txt">
                <span id="sbarDone"><?= $completed_lessons ?></span>/<span><?= $total_lessons ?></span> bài
                <span id="sbarPct"><?= $progress_percent ?>%</span>
            </div>
        </div>

        <div class="sidebar-inner">
            <?php foreach ($chapters as $ch): ?>
            <?php
            $ch_active = false;
            foreach ($ch['lessons'] as $ls) if ((int)$ls['id'] === $lesson_id) { $ch_active = true; break; }
            $ch_done = count(array_filter($ch['lessons'], fn($l) => $l['is_completed']));
            $ch_total = count($ch['lessons']);
            ?>
            <div>
                <div class="ch-title <?= $ch_active ? 'open' : '' ?>" onclick="toggleCh(<?= $ch['id'] ?>)">
                    <span>
                        <span class="ch-meta"><?= $ch_done ?>/<?= $ch_total ?> bài</span>
                        <?= htmlspecialchars($ch['title']) ?>
                    </span>
                    <i class="bi bi-chevron-<?= $ch_active ? 'up' : 'down' ?>" id="chi-<?= $ch['id'] ?>"></i>
                </div>
                <div class="ch-lessons" id="chl-<?= $ch['id'] ?>" style="<?= $ch_active ? '' : 'display:none;' ?>">
                    <?php foreach ($ch['lessons'] as $ls): ?>
                    <?php
                    $is_active = (int)$ls['id'] === $lesson_id;
                    $is_done   = $ls['is_completed'];
                    // Kiểm tra có exam gắn với bài này không
                    $ls_exam_r = $conn->query("SELECT COUNT(*) FROM exams WHERE lesson_id = " . (int)$ls['id'] . " AND is_active = 1");
                    $ls_has_quiz = $ls_exam_r && (int)$ls_exam_r->fetch_row()[0] > 0;
                    ?>
                    <a href="hoc.php?course_id=<?= $course_id ?>&lesson_id=<?= $ls['id'] ?>"
                       class="ls-item <?= $is_active ? 'active' : '' ?> <?= $is_done ? 'done' : '' ?>">
                        <i class="ls-icon bi bi-<?= $is_done ? 'check-circle-fill' : ($is_active ? 'play-circle-fill' : 'circle') ?>"></i>
                        <div style="flex:1;min-width:0;">
                            <div class="ls-name"><?= htmlspecialchars($ls['title']) ?></div>
                            <div style="display:flex;gap:5px;margin-top:2px;">
                                <?php if ($ls['is_free'] ?? 0): ?><span style="font-size:.65rem;background:rgba(20,184,166,.12);color:var(--accent);border-radius:3px;padding:0 5px;">Thử</span><?php endif; ?>
                                <?php if ($ls_has_quiz): ?><span style="font-size:.65rem;background:rgba(99,102,241,.12);color:var(--indigo);border-radius:3px;padding:0 5px;"><i class="bi bi-clipboard-check"></i></span><?php endif; ?>
                            </div>
                        </div>
                        <span class="ls-dur"><?= (int)$ls['duration_minutes'] ?>p</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- ĐỀ THI KHÓA HỌC (toàn khóa, không gắn lesson) -->
            <?php
            $global_exams = [];
            $ge_res = $conn->query("SELECT * FROM exams WHERE course_id = $course_id AND (lesson_id = 0 OR lesson_id IS NULL) AND is_active = 1 ORDER BY id ASC");
            if ($ge_res) while ($ge = $ge_res->fetch_assoc()) $global_exams[] = $ge;
            ?>
            <?php if (!empty($global_exams)): ?>
            <div>
                <div class="ch-title" onclick="toggleCh('exams')">
                    <span>
                        <span class="ch-meta"><?= count($global_exams) ?> đề thi</span>Kiểm tra &amp; Đánh giá
                    </span>
                    <i class="bi bi-chevron-down" id="chi-exams"></i>
                </div>
                <div class="ch-lessons" id="chl-exams" style="display:none;">
                    <?php foreach ($global_exams as $ex): ?>
                    <a href="quiz.php?exam_id=<?= $ex['id'] ?>&course_id=<?= $course_id ?>" target="_blank" class="quiz-item">
                        <i class="qi-icon bi bi-clipboard2-check"></i>
                        <div style="flex:1;min-width:0;">
                            <div class="qi-name"><?= htmlspecialchars($ex['title']) ?></div>
                            <span style="font-size:.7rem;color:var(--muted);"><?= $ex['duration_minutes'] ?>p · Qua <?= $ex['pass_score'] ?>%</span>
                        </div>
                        <i class="bi bi-box-arrow-up-right" style="font-size:.7rem;color:var(--muted);"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- /sidebar -->

</div><!-- /app -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const COURSE_ID = <?= $course_id ?>;
const LESSON_ID = <?= $lesson_id ?>;
const API = 'hoc.php?course_id=' + COURSE_ID + '&ajax=';

// ===== SIDEBAR =====
let sidebarOpen = true;
function toggleSidebar() {
    const sb  = document.getElementById('sidebar');
    const btn = document.getElementById('btnSidebar');
    sidebarOpen = !sidebarOpen;
    if (window.innerWidth <= 768) {
        sb.classList.toggle('open', sidebarOpen);
        sb.classList.toggle('closed', !sidebarOpen);
    } else {
        sb.classList.toggle('closed', !sidebarOpen);
    }
    btn.classList.toggle('active', sidebarOpen);
}
function toggleCh(id) {
    const el   = document.getElementById('chl-' + id);
    if (!el) return;
    const icon = document.getElementById('chi-' + id);
    const title= el.previousElementSibling;
    const isHidden = window.getComputedStyle(el).display === 'none';
    el.style.display = isHidden ? 'block' : 'none';
    if (icon) icon.className = 'bi bi-chevron-' + (isHidden ? 'up' : 'down');
    if (title) title.classList.toggle('open', isHidden);
}

// ===== TABS =====
function switchTab(name) {
    document.querySelectorAll('.tab-pane').forEach(p => {
        p.classList.remove('show');
        p.classList.remove('active');
        p.style.setProperty('display', 'none', 'important');
    });
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    const pane = document.getElementById('pane-' + name);
    const btn  = document.querySelector('[data-tab="' + name + '"]');
    if (pane) {
        pane.classList.add('show');
        pane.classList.add('active');
        pane.style.setProperty('display', 'block', 'important');
    }
    if (btn) btn.classList.add('active');
    
    // sync topbar buttons
    document.getElementById('btnNotes')?.classList.toggle('active', name === 'notes');
    document.getElementById('btnQA')?.classList.toggle('active', name === 'qa');
}

// ===== VIDEO =====
let videoEl = document.getElementById('lessonVideo');
let curTime = 0;
let ytPlayer = null;
function fmtTime(s) { return String(Math.floor(s/60)).padStart(2,'0')+':'+String(Math.floor(s%60)).padStart(2,'0'); }

function updateCurTimeDisp() {
    const el = document.getElementById('curTimeDisp');
    if (el) el.textContent = fmtTime(curTime);
}

if (videoEl && videoEl.tagName === 'VIDEO') {
    // Native <video> element
    videoEl.addEventListener('timeupdate', () => {
        curTime = Math.floor(videoEl.currentTime);
        updateCurTimeDisp();
    });
    setInterval(() => { if (!videoEl.paused) saveProgress(false); }, 15000);
    videoEl.addEventListener('pause', () => saveProgress(false));
    videoEl.addEventListener('ended', () => saveProgress(true));
    const lastPos = <?= (int)($current_lesson['last_pos'] ?? 0) ?>;
    if (lastPos > 10) {
        videoEl.addEventListener('loadedmetadata', () => {
            if (lastPos < videoEl.duration - 5) videoEl.currentTime = lastPos;
        }, { once: true });
    }
} else if (videoEl && videoEl.tagName === 'IFRAME') {
    // YouTube iframe – dùng YouTube IFrame Player API
    // Đảm bảo URL có enablejsapi=1
    let src = videoEl.src;
    if (src && !src.includes('enablejsapi')) {
        videoEl.src = src + (src.includes('?') ? '&' : '?') + 'enablejsapi=1';
    }

    // Load YouTube IFrame API nếu chưa có
    if (!window.YT) {
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(tag);
    }

    // Callback khi API sẵn sàng
    window.onYouTubeIframeAPIReady = function() {
        ytPlayer = new YT.Player(videoEl.id, {
            events: {
                'onReady': function(e) {
                    // Poll mỗi giây để cập nhật curTime và thanh tiến độ
                    setInterval(function() {
                        if (ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
                            try {
                                curTime = Math.floor(ytPlayer.getCurrentTime());
                                updateCurTimeDisp();
                            } catch(err) {}
                        }
                    }, 1000);
                    // Auto-seek đến vị trí đã học cuối cùng
                    const lastPos = <?= (int)($current_lesson['last_pos'] ?? 0) ?>;
                    if (lastPos > 10) {
                        try { e.target.seekTo(lastPos, true); } catch(err) {}
                    }
                },
                'onStateChange': function(e) {
                    // YT.PlayerState: ENDED=0, PLAYING=1, PAUSED=2
                    if (e.data === 0) saveProgress(true);   // ended
                    if (e.data === 2) saveProgress(false);  // paused
                }
            }
        });
        // Poll progress mỗi 15 giây khi đang chạy
        setInterval(function() {
            if (ytPlayer && typeof ytPlayer.getPlayerState === 'function') {
                try {
                    if (ytPlayer.getPlayerState() === 1) saveProgress(false); // PLAYING
                } catch(err) {}
            }
        }, 15000);
    };

    // Nếu YT đã load rồi (ví dụ: load lại trang), gọi lại thủ công
    if (window.YT && window.YT.Player) {
        window.onYouTubeIframeAPIReady();
    }
}

function seekTo(s) {
    if (videoEl && videoEl.tagName === 'VIDEO') {
        videoEl.currentTime = s;
        videoEl.play();
        switchTab('content');
    } else if (ytPlayer && typeof ytPlayer.seekTo === 'function') {
        try { ytPlayer.seekTo(s, true); ytPlayer.playVideo(); } catch(err) {}
        switchTab('content');
    }
}

// ===== PROGRESS =====
function saveProgress(done = false) {
    if (!LESSON_ID) return;
    if (videoEl && videoEl.tagName === 'VIDEO') {
        curTime = Math.floor(videoEl.currentTime);
    } else if (ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
        try { curTime = Math.floor(ytPlayer.getCurrentTime()); } catch(err) {}
    }
    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('position', curTime);
    fd.append('watch_seconds', curTime);
    if (done) fd.append('completed', '1');
    fetch(API + 'save_progress', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (d.ok) updateProgUI(d.progress, d.completed, d.total); }).catch(()=>{});
}
function markDone(lid) {
    const btn = document.getElementById('btnDone');
    if (btn.classList.contains('done-state')) return;
    const fd = new FormData();
    fd.append('lesson_id', lid);
    fd.append('position', curTime);
    fd.append('watch_seconds', curTime);
    fd.append('completed', '1');
    fetch(API + 'save_progress', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.ok) {
                btn.classList.add('done-state');
                btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Đã hoàn thành';
                updateProgUI(d.progress, d.completed, d.total);
                document.querySelectorAll('.ls-item.active .ls-icon').forEach(el => {
                    el.className = 'ls-icon bi bi-check-circle-fill';
                    el.closest('.ls-item')?.classList.add('done');
                });
                <?php if ($next_lesson_id > 0): ?>
                setTimeout(() => {
                    if (confirm('Bạn đã hoàn thành bài này! Chuyển sang bài tiếp theo?'))
                        location.href = 'hoc.php?course_id='+COURSE_ID+'&lesson_id=<?= $next_lesson_id ?>';
                }, 800);
                <?php endif; ?>
            }
        });
}
function updateProgUI(pct, done, total) {
    const pf = document.getElementById('topProg');
    const pl = document.getElementById('topProgLbl');
    const sf = document.getElementById('sbarProgFill');
    const sd = document.getElementById('sbarDone');
    const sp = document.getElementById('sbarPct');
    if (pf) pf.style.width = pct + '%';
    if (pl) pl.textContent = done + '/' + total + ' bài';
    if (sf) sf.style.width = pct + '%';
    if (sd) sd.textContent = done;
    if (sp) sp.textContent = pct + '%';
}

// ===== GHI CHÚ =====
function addNote() {
    const text = document.getElementById('noteText').value.trim();
    if (!text) { alert('Vui lòng nhập nội dung!'); return; }
    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('note_text', text);
    fd.append('video_time', curTime);
    fetch(API + 'add_note', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.ok) {
                document.getElementById('noteText').value = '';
                document.getElementById('emptyNotes')?.remove();
                const list = document.getElementById('notesList');
                list.insertAdjacentHTML('afterbegin', `<div class="note-card" id="note-${d.note.id}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="note-time" onclick="seekTo(${d.note.video_time_seconds})"><i class="bi bi-play-circle me-1"></i>${fmtTime(parseInt(d.note.video_time_seconds))}</span>
                        <button class="btn-sm-t" onclick="deleteNote(${d.note.id})"><i class="bi bi-trash3"></i></button>
                    </div>
                    <p class="note-text">${d.note.note_text.replace(/\n/g,'<br>')}</p>
                </div>`);
            }
        });
}
function deleteNote(id) {
    if (!confirm('Xóa ghi chú này?')) return;
    const fd = new FormData(); fd.append('note_id', id);
    fetch(API + 'delete_note', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (d.ok) document.getElementById('note-'+id)?.remove(); });
}

// ===== Q&A =====
function postQA(pid) {
    const inp = pid === 0 ? document.getElementById('qaInput') : document.getElementById('ri-'+pid);
    const txt = inp.value.trim();
    if (!txt) { alert('Vui lòng nhập nội dung!'); return; }
    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('content', txt);
    fd.append('parent_id', pid);
    fetch(API + 'post_qa', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.ok) {
                inp.value = '';
                if (pid === 0) {
                    document.getElementById('emptyQA')?.remove();
                    document.getElementById('qaList').insertAdjacentHTML('afterbegin', renderQA(d.post));
                } else {
                    document.getElementById('rf-'+pid).style.display = 'none';
                    location.reload();
                }
            }
        });
}
function postReply(pid) { postQA(pid); }
function toggleReply(id) {
    const f = document.getElementById('rf-'+id);
    if (f) f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
function renderQA(p) {
    const init = (p.fullname||'U').charAt(0);
    const admin = parseInt(p.role) === 1;
    const t = new Date(p.created_at).toLocaleString('vi-VN');
    return `<div class="qa-card" id="qa-${p.id}">
        <div class="d-flex gap-3 align-items-start">
            <div class="qa-av ${admin?'admin':''}">${init}</div>
            <div class="flex-1 w-100">
                <div class="d-flex justify-content-between">
                    <span class="qa-name">${p.fullname||'Học viên'}${admin?'<span style="color:var(--warning);font-size:.72rem;margin-left:4px;">✦ GV</span>':''}</span>
                    <span class="qa-time">${t}</span>
                </div>
                <p class="qa-body">${p.content.replace(/\n/g,'<br>')}</p>
                <button class="btn-sm-t" onclick="toggleReply(${p.id})"><i class="bi bi-reply me-1"></i>Trả lời (0)</button>
                <div id="rf-${p.id}" style="display:none;margin-top:8px;">
                    <textarea class="qa-form-area" rows="2" id="ri-${p.id}" placeholder="Nhập trả lời..."></textarea>
                    <div class="d-flex justify-content-end gap-2 mt-1">
                        <button class="btn-sm-t" onclick="toggleReply(${p.id})">Hủy</button>
                        <button class="btn-teal" onclick="postReply(${p.id})" style="font-size:.78rem;padding:5px 12px;"><i class="bi bi-send"></i>Gửi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

// ===== INLINE QUIZ ENGINE =====
const quizState = {};  // { [exam_id]: { questions, answers, timer, startTs } }

async function startInlineQuiz(examId) {
    // Ẩn buttons, hiện loading
    document.getElementById('examActions_'+examId).style.display = 'none';
    const wrap = document.getElementById('inlineQuiz_'+examId);
    wrap.style.display = 'block';
    wrap.innerHTML = '<div style="text-align:center;padding:24px;color:var(--muted);"><i class="bi bi-hourglass-split" style="font-size:2rem;"></i><p class="mt-2">Đang tải câu hỏi...</p></div>';

    // Fetch questions via hidden form
    const resp = await fetch('hoc_quiz_data.php?exam_id='+examId+'&lesson_id='+LESSON_ID+'&course_id='+COURSE_ID);
    let data;
    try { data = await resp.json(); } catch(e) { data = null; }

    if (!data || !data.ok || !data.questions || data.questions.length === 0) {
        wrap.innerHTML = '<div style="color:var(--danger);padding:16px;"><i class="bi bi-exclamation-triangle me-2"></i>Không thể tải câu hỏi. Vui lòng thử lại.</div>';
        document.getElementById('examActions_'+examId).style.display = 'flex';
        return;
    }

    quizState[examId] = {
        questions: data.questions,
        exam:      data.exam,
        answers:   {},
        startTs:   Math.floor(Date.now()/1000)
    };

    renderInlineQuiz(examId);
    startInlineTimer(examId);
}

function renderInlineQuiz(examId) {
    const st = quizState[examId];
    const qs = st.questions;
    const ex = st.exam;
    const wrap = document.getElementById('inlineQuiz_'+examId);

    let html = `<form id="iqForm_${examId}">
    <div class="iq-topbar">
        <div class="iq-timer" id="iqTimer_${examId}"><i class="bi bi-stopwatch"></i><span id="iqTimerDisp_${examId}">${fmtTime(ex.duration_minutes*60)}</span></div>
        <div class="iq-progress"><span id="iqAns_${examId}">0</span>/${qs.length} câu đã trả lời</div>
    </div>`;

    qs.forEach((q, idx) => {
        const opts = [
            {l:'A',v:q.option_a},{l:'B',v:q.option_b},{l:'C',v:q.option_c},{l:'D',v:q.option_d}
        ].filter(o => o.v);
        html += `<div class="iq-q-card">
            <div class="iq-q-num">Câu ${idx+1}/${qs.length}</div>
            <div class="iq-q-text">${escHtml(q.question_text)}</div>`;
        opts.forEach(o => {
            html += `<label class="iq-opt" onclick="iqCountAns(${examId})">
                <input type="radio" name="ans_${examId}_${q.id}" value="${o.l}">
                <div class="iq-letter">${o.l}</div>
                <span class="iq-opt-text">${escHtml(o.v)}</span>
            </label>`;
        });
        html += `</div>`;
    });

    html += `<div class="iq-actions">
        <span class="answered-info"><span id="iqAns2_${examId}">0</span>/${qs.length} câu đã trả lời</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn-cancel-iq" onclick="cancelInlineQuiz(${examId})"><i class="bi bi-x"></i> Thoát</button>
            <button type="button" class="btn-submit-iq" onclick="submitInlineQuiz(${examId})"><i class="bi bi-send-check-fill"></i> Nộp bài</button>
        </div>
    </div></form>`;

    wrap.innerHTML = html;
}

function iqCountAns(examId) {
    setTimeout(() => {
        const st  = quizState[examId];
        if (!st) return;
        let ans = 0;
        st.questions.forEach(q => {
            const checked = document.querySelector(`input[name="ans_${examId}_${q.id}"]:checked`);
            if (checked) ans++;
        });
        const e1 = document.getElementById('iqAns_'+examId);
        const e2 = document.getElementById('iqAns2_'+examId);
        if (e1) e1.textContent = ans;
        if (e2) e2.textContent = ans;
    }, 50);
}

function startInlineTimer(examId) {
    const st = quizState[examId];
    if (!st) return;
    const totalSec = st.exam.duration_minutes * 60;
    if (st.timerInterval) clearInterval(st.timerInterval);
    st.timerInterval = setInterval(() => {
        const elapsed = Math.floor(Date.now()/1000) - st.startTs;
        const left = Math.max(0, totalSec - elapsed);
        const dispEl = document.getElementById('iqTimerDisp_'+examId);
        const boxEl  = document.getElementById('iqTimer_'+examId);
        if (dispEl) dispEl.textContent = fmtTime(left);
        if (boxEl) {
            boxEl.className = 'iq-timer' + (left <= 60 ? ' red' : '');
        }
        if (left <= 0) {
            clearInterval(st.timerInterval);
            submitInlineQuiz(examId, true);
        }
    }, 1000);
}

function cancelInlineQuiz(examId) {
    if (!confirm('Bạn chắc muốn thoát? Tiến trình sẽ không được lưu.')) return;
    if (quizState[examId]?.timerInterval) clearInterval(quizState[examId].timerInterval);
    delete quizState[examId];
    document.getElementById('inlineQuiz_'+examId).style.display = 'none';
    document.getElementById('inlineQuiz_'+examId).innerHTML = '';
    document.getElementById('examActions_'+examId).style.display = 'flex';
}

async function submitInlineQuiz(examId, autoSubmit = false) {
    const st = quizState[examId];
    if (!st) return;
    if (!autoSubmit) {
        const ans = st.questions.filter(q => document.querySelector(`input[name="ans_${examId}_${q.id}"]:checked`)).length;
        const unanswered = st.questions.length - ans;
        const msg = unanswered > 0 ? `Còn ${unanswered} câu chưa trả lời. Bạn có chắc muốn nộp?` : 'Bạn có chắc muốn nộp bài?';
        if (!confirm(msg)) return;
    }
    if (st.timerInterval) clearInterval(st.timerInterval);

    // Collect answers
    const answers = {};
    st.questions.forEach(q => {
        const checked = document.querySelector(`input[name="ans_${examId}_${q.id}"]:checked`);
        answers[q.id] = checked ? checked.value : '';
    });

    const wrap = document.getElementById('inlineQuiz_'+examId);
    wrap.innerHTML = '<div style="text-align:center;padding:24px;color:var(--muted);"><i class="bi bi-hourglass-split" style="font-size:2rem;"></i><p class="mt-2">Đang chấm điểm...</p></div>';

    // POST to grade endpoint
    const fd = new FormData();
    fd.append('exam_id', examId);
    fd.append('course_id', COURSE_ID);
    fd.append('lesson_id', LESSON_ID);
    fd.append('start_time', st.startTs);
    st.questions.forEach(q => { fd.append('answer_'+q.id, answers[q.id] || ''); });

    const resp = await fetch('hoc_quiz_submit.php', { method: 'POST', body: fd });
    let res;
    try { res = await resp.json(); } catch(e) { res = null; }

    if (!res || !res.ok) {
        wrap.innerHTML = '<div style="color:var(--danger);padding:16px;"><i class="bi bi-exclamation-triangle me-2"></i>Lỗi khi nộp bài. Vui lòng thử lại.</div>';
        return;
    }

    delete quizState[examId];
    renderInlineResult(examId, res, st.questions);
}

function renderInlineResult(examId, res, questions) {
    const wrap = document.getElementById('inlineQuiz_'+examId);
    const ex   = res.exam;
    const passed= res.passed;
    const score = res.score;
    const right = res.correct;
    const total = res.total;
    const timeTaken = res.time_taken;

    let html = `<div class="iq-result">
        <div class="iq-score-row">
            <div class="iq-score-circle ${passed?'pass':'fail'}">
                <div class="iq-score-num" style="color:${passed?'var(--success)':'var(--danger)'}">${score}%</div>
                <div class="iq-score-lbl">Điểm</div>
            </div>
            <div>
                <div style="font-size:1.1rem;font-weight:700;">${passed?'🎉 Đạt yêu cầu!':'😔 Chưa đạt'}</div>
                <div style="font-size:.82rem;color:var(--muted);margin-top:4px;">${right}/${total} câu đúng · ${fmtTime(timeTaken)}</div>
                <div style="font-size:.78rem;color:var(--muted);">Điểm đạt: ${ex.pass_score}%</div>
            </div>
        </div>
        <details style="margin-bottom:12px;">
            <summary style="cursor:pointer;font-size:.85rem;color:var(--accent2);font-weight:600;padding:8px 0;"><i class="bi bi-clipboard-check me-1"></i>Xem chi tiết từng câu</summary>
            <div style="margin-top:10px;">`;

    res.detail.forEach((d, i) => {
        const isRight = d.is_correct;
        const ua = d.user_answer || '(Bỏ trống)';
        const ca = d.correct_answer;
        html += `<div class="iq-rev-card ${isRight?'ok':'bad'}">
            <div style="font-size:.8rem;color:${isRight?'var(--success)':'var(--danger)'};font-weight:700;margin-bottom:5px;">
                Câu ${i+1}: ${isRight?'✓ Đúng':'✗ Sai'}
            </div>
            <div style="font-size:.85rem;font-weight:600;margin-bottom:7px;">${escHtml(d.question_text)}</div>`;
        ['A','B','C','D'].forEach(l => {
            const optKey = 'option_' + l.toLowerCase();
            const optText = d[optKey];
            if (!optText) return;
            let cls = '';
            if (l === ua && !isRight) cls = 'user-wrong';
            else if (l === ua && isRight) cls = 'user-right';
            if (l === ca && !isRight) cls = 'correct';
            if (cls) html += `<div class="iq-rev-opt ${cls}">${l}. ${escHtml(optText)}${l===ua?' ← Bạn chọn':''}${l===ca&&!isRight?' ← Đúng':''}</div>`;
        });
        if (d.explanation) {
            html += `<div class="iq-expl"><i class="bi bi-lightbulb-fill me-1"></i>${escHtml(d.explanation)}</div>`;
        }
        html += `</div>`;
    });

    html += `</div></details>
        <div class="d-flex flex-wrap gap-2">`;

    // Retry button (phụ thuộc lượt còn lại - check từ server res.attempts_left)
    if (res.attempts_left > 0) {
        html += `<button class="btn-indigo" onclick="startInlineQuiz(${examId})"><i class="bi bi-arrow-clockwise"></i> Thi lại (${res.attempts_left} lượt)</button>`;
    }
    html += `<a href="quiz.php?exam_id=${examId}&course_id=${COURSE_ID}&result_id=${res.result_id}" target="_blank" class="btn-sm-t" style="display:flex;align-items:center;gap:5px;"><i class="bi bi-box-arrow-up-right"></i> Xem chi tiết</a>`;
    html += `</div></div>`;

    wrap.innerHTML = html;
    document.getElementById('examActions_'+examId).style.display = 'none';
}

function escHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ===== KEYBOARD =====
document.addEventListener('keydown', e => {
    if (['INPUT','TEXTAREA'].includes(e.target.tagName)) return;
    if (videoEl && videoEl.tagName === 'VIDEO') {
        if (e.key === ' ')          { e.preventDefault(); videoEl.paused ? videoEl.play() : videoEl.pause(); }
        if (e.key === 'ArrowRight') { e.preventDefault(); videoEl.currentTime += 10; }
        if (e.key === 'ArrowLeft')  { e.preventDefault(); videoEl.currentTime -= 10; }
    }
});
</script>
</body>
</html>
