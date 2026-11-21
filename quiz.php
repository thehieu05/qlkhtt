<?php
require_once 'config.php';

// Yêu cầu đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$exam_id  = (int)($_GET['exam_id']  ?? 0);
$course_id= (int)($_GET['course_id'] ?? 0);
$result_id= (int)($_GET['result_id'] ?? 0);

if ($exam_id <= 0) {
    header("Location: index.php");
    exit();
}

// ===== LẤY THÔNG TIN ĐỀ THI =====
$exam_res = $conn->query("SELECT e.*, c.title as course_title 
    FROM exams e 
    LEFT JOIN courses c ON c.id = e.course_id 
    WHERE e.id = $exam_id AND e.is_active = 1");
if (!$exam_res || $exam_res->num_rows === 0) {
    die("<div class='text-center p-5'>Đề thi không tồn tại hoặc đã bị ẩn.</div>");
}
$exam = $exam_res->fetch_assoc();
if ($course_id <= 0) $course_id = (int)$exam['course_id'];

// ===== ĐÃ CÓ KẾT QUẢ → TRANG XEM KẾT QUẢ =====
if ($result_id > 0) {
    $rr = $conn->query("SELECT er.*, u.fullname FROM exam_results er 
        LEFT JOIN users u ON u.id = er.user_id 
        WHERE er.id = $result_id AND er.user_id = $user_id AND er.exam_id = $exam_id");
    if (!$rr || $rr->num_rows === 0) {
        header("Location: quiz.php?exam_id=$exam_id&course_id=$course_id");
        exit();
    }
    $result = $rr->fetch_assoc();

    // Lấy chi tiết từng câu trả lời
    $answers_res = $conn->query("SELECT ea.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.explanation
        FROM exam_answers ea
        JOIN questions q ON q.id = ea.question_id
        WHERE ea.result_id = $result_id
        ORDER BY ea.id ASC");
    $detail_answers = [];
    if ($answers_res) {
        while ($a = $answers_res->fetch_assoc()) $detail_answers[] = $a;
    }

    // Đếm số lần thi
    $attempt_res = $conn->query("SELECT COUNT(*) as cnt FROM exam_results WHERE user_id = $user_id AND exam_id = $exam_id");
    $attempts_used = $attempt_res ? (int)$attempt_res->fetch_assoc()['cnt'] : 1;
    $attempts_left = max(0, (int)$exam['max_attempts'] - $attempts_used);

    $mode = 'result';

// ===== XỬ LÝ NỘP BÀI =====
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {

    $posted_exam_id  = (int)($_POST['exam_id']  ?? 0);
    $posted_course_id= (int)($_POST['course_id'] ?? 0);
    $start_time      = (int)($_POST['start_time'] ?? time());

    if ($posted_exam_id !== $exam_id) die("Dữ liệu không hợp lệ.");

    // Kiểm tra số lần thi
    $att_res = $conn->query("SELECT COUNT(*) as cnt FROM exam_results WHERE user_id = $user_id AND exam_id = $exam_id");
    $attempts_used_now = $att_res ? (int)$att_res->fetch_assoc()['cnt'] : 0;
    if ($attempts_used_now >= (int)$exam['max_attempts']) {
        header("Location: quiz.php?exam_id=$exam_id&course_id=$course_id&err=max_attempts");
        exit();
    }

    // Lấy câu hỏi để chấm
    $q_res = $conn->query("SELECT q.id as question_id, q.correct_answer 
        FROM exam_questions eq 
        JOIN questions q ON q.id = eq.question_id 
        WHERE eq.exam_id = $exam_id");
    $questions_to_grade = [];
    if ($q_res) {
        while ($qr = $q_res->fetch_assoc()) {
            $questions_to_grade[$qr['question_id']] = strtoupper($qr['correct_answer']);
        }
    }

    $total_q   = count($questions_to_grade);
    $correct_n = 0;

    foreach ($questions_to_grade as $qid => $correct_ans) {
        $user_ans = strtoupper(trim($_POST['answer_' . $qid] ?? ''));
        if ($user_ans === $correct_ans) $correct_n++;
    }

    $score         = $total_q > 0 ? round(($correct_n / $total_q) * 100) : 0;
    $passed        = $score >= (int)$exam['pass_score'] ? 1 : 0;
    $time_taken    = max(0, time() - $start_time);
    $safe_fullname = $conn->real_escape_string($_SESSION['fullname'] ?? '');

    // Lưu kết quả
    $conn->query("INSERT INTO exam_results (exam_id, user_id, score, total_questions, correct_answers, time_taken_seconds, passed) 
        VALUES ($exam_id, $user_id, $score, $total_q, $correct_n, $time_taken, $passed)");
    $new_result_id = (int)$conn->insert_id;

    // Lưu từng câu trả lời
    foreach ($questions_to_grade as $qid => $correct_ans) {
        $user_ans  = strtoupper(trim($_POST['answer_' . $qid] ?? ''));
        $is_correct= ($user_ans === $correct_ans) ? 1 : 0;
        $safe_ans  = $conn->real_escape_string($user_ans);
        $conn->query("INSERT INTO exam_answers (result_id, question_id, user_answer, is_correct) 
            VALUES ($new_result_id, $qid, '$safe_ans', $is_correct)");
    }

    header("Location: quiz.php?exam_id=$exam_id&course_id=$course_id&result_id=$new_result_id");
    exit();

// ===== TRANG GIỚI THIỆU / DANH SÁCH CÂU HỎI =====
} else {

    // Kiểm tra số lần thi
    $att_res = $conn->query("SELECT COUNT(*) as cnt FROM exam_results WHERE user_id = $user_id AND exam_id = $exam_id");
    $attempts_used = $att_res ? (int)$att_res->fetch_assoc()['cnt'] : 0;
    $attempts_left = max(0, (int)$exam['max_attempts'] - $attempts_used);

    $mode = isset($_GET['start']) ? 'quiz' : 'intro';

    if ($mode === 'quiz' && $attempts_left <= 0) {
        $mode = 'maxed';
    }

    if ($mode === 'quiz') {
        // Lấy câu hỏi
        $q_res = $conn->query("SELECT q.*, eq.sort_order 
            FROM exam_questions eq 
            JOIN questions q ON q.id = eq.question_id 
            WHERE eq.exam_id = $exam_id 
            ORDER BY eq.sort_order ASC");
        $questions = [];
        if ($q_res) {
            while ($qr = $q_res->fetch_assoc()) $questions[] = $qr;
        }
        // Xáo trộn nếu cài đặt bật
        if ((int)$exam['shuffle_questions'] === 1) shuffle($questions);
    }
}

$page_title = htmlspecialchars($exam['title']);
$option_labels = ['A', 'B', 'C', 'D'];
$option_keys   = ['option_a', 'option_b', 'option_c', 'option_d'];

function fmt_time($seconds) {
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d', $m, $s);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> – EduNova</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --bg-dark: #0f1117;
    --bg-card: #1a1d27;
    --bg-card2: #21253a;
    --accent: #f05123;
    --accent2: #ff7b4f;
    --success: #22c55e;
    --danger:  #ef4444;
    --warning: #f59e0b;
    --text-main: #e8eaf0;
    --text-muted: #8b92a9;
    --border: #2a2f47;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg-dark); color: var(--text-main); font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }

/* ===== TOP BAR ===== */
.quiz-topbar {
    position: sticky; top: 0; z-index: 1000;
    background: var(--bg-card); border-bottom: 1px solid var(--border);
    padding: 0 1.5rem; height: 56px;
    display: flex; align-items: center; gap: 1rem;
}
.quiz-topbar .logo { color: var(--accent); font-weight: 800; font-size: 1.1rem; text-decoration: none; white-space: nowrap; }
.quiz-topbar .exam-title { flex: 1; font-size: 0.9rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.timer-box {
    display: flex; align-items: center; gap: 0.4rem;
    background: #2a0d00; border: 1px solid var(--accent); border-radius: 8px;
    padding: 0.3rem 0.8rem; font-weight: 700; font-size: 1rem; color: var(--accent2);
    min-width: 90px; justify-content: center; white-space: nowrap;
}
.timer-box.warning { background: #2d1a00; border-color: var(--warning); color: var(--warning); animation: pulse-warn 1s infinite; }
.timer-box.danger  { background: #2d0000; border-color: var(--danger);  color: var(--danger);  animation: pulse-warn .5s infinite; }
@keyframes pulse-warn { 0%,100%{opacity:1} 50%{opacity:.6} }
.progress-info { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }

/* ===== INTRO PAGE ===== */
.intro-wrapper { max-width: 760px; margin: 0 auto; padding: 2.5rem 1rem; }
.intro-card {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 2.5rem;
}
.intro-card h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.5rem; }
.intro-card .sub { color: var(--text-muted); margin-bottom: 1.5rem; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1.5rem 0; }
.info-item {
    background: var(--bg-card2); border-radius: 10px; padding: 1rem 1.2rem;
    display: flex; align-items: center; gap: 0.75rem;
}
.info-item i { font-size: 1.5rem; color: var(--accent2); }
.info-item .label { font-size: 0.75rem; color: var(--text-muted); }
.info-item .val   { font-size: 1.1rem; font-weight: 700; }
.btn-start {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: #fff; border: none; border-radius: 10px; padding: 0.9rem 2.5rem;
    font-size: 1rem; font-weight: 700; cursor: pointer; text-decoration: none;
    transition: opacity .2s, transform .15s;
}
.btn-start:hover { opacity: .9; transform: translateY(-2px); color: #fff; }
.btn-back { color: var(--text-muted); text-decoration: none; font-size: 0.9rem; }
.btn-back:hover { color: var(--text-main); }
.attempt-badge { display: inline-flex; align-items: center; gap: 0.4rem; background: #1e2535; border: 1px solid var(--border); border-radius: 20px; padding: 0.3rem 0.8rem; font-size: 0.8rem; }

/* ===== QUIZ PAGE ===== */
.quiz-wrapper { max-width: 900px; margin: 0 auto; padding: 2rem 1rem 4rem; }
.question-card {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px;
    padding: 1.75rem; margin-bottom: 1.25rem; transition: border-color .2s;
}
.question-card:hover { border-color: #3a4060; }
.question-num { font-size: 0.75rem; font-weight: 700; color: var(--accent2); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.6rem; }
.question-text { font-size: 1rem; font-weight: 600; line-height: 1.6; margin-bottom: 1.2rem; }
.options-grid { display: flex; flex-direction: column; gap: 0.6rem; }
.option-label {
    display: flex; align-items: center; gap: 0.8rem;
    background: var(--bg-card2); border: 1.5px solid var(--border);
    border-radius: 10px; padding: 0.75rem 1rem; cursor: pointer;
    transition: background .15s, border-color .15s;
}
.option-label:hover { background: #2a2f47; border-color: #4a5080; }
.option-label input[type="radio"] { display: none; }
.option-label input[type="radio"]:checked ~ .opt-box { background: var(--accent); border-color: var(--accent); color: #fff; }
.option-label:has(input:checked) { background: #1f1428; border-color: var(--accent); }
.opt-box {
    min-width: 32px; height: 32px; border-radius: 50%;
    border: 2px solid var(--border); display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.9rem; color: var(--text-muted); transition: all .15s;
}
.opt-text { font-size: 0.95rem; flex: 1; }
.submit-bar {
    position: sticky; bottom: 0; background: rgba(15,17,23,.95); backdrop-filter: blur(8px);
    border-top: 1px solid var(--border); padding: 1rem 1.5rem;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; z-index: 50;
}
.btn-submit {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: #fff; border: none; border-radius: 10px; padding: 0.75rem 2rem;
    font-size: 1rem; font-weight: 700; cursor: pointer; transition: opacity .2s;
}
.btn-submit:hover { opacity: .9; }
.answered-counter { font-size: 0.85rem; color: var(--text-muted); }
.answered-counter span { color: var(--accent2); font-weight: 700; }

/* ===== RESULT PAGE ===== */
.result-wrapper { max-width: 900px; margin: 0 auto; padding: 2rem 1rem 5rem; }
.score-card {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
    padding: 2.5rem; text-align: center; margin-bottom: 2rem;
}
.score-ring {
    width: 140px; height: 140px; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
    border: 6px solid var(--accent2); background: var(--bg-card2);
}
.score-ring.passed  { border-color: var(--success); }
.score-ring.failed  { border-color: var(--danger); }
.score-ring .pct    { font-size: 2.4rem; font-weight: 800; }
.score-ring .pct-label { font-size: 0.75rem; color: var(--text-muted); }
.badge-pass { background: rgba(34,197,94,.15); color: var(--success); border: 1px solid var(--success); border-radius: 20px; padding: .3rem 1rem; font-size:.85rem; font-weight:700; }
.badge-fail { background: rgba(239,68,68,.15);  color: var(--danger);  border: 1px solid var(--danger);  border-radius: 20px; padding: .3rem 1rem; font-size:.85rem; font-weight:700; }
.stats-row { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; margin-top: 1.5rem; }
.stat-box { background: var(--bg-card2); border-radius: 10px; padding: .8rem 1.4rem; text-align: center; min-width: 110px; }
.stat-box .sv { font-size: 1.3rem; font-weight: 700; }
.stat-box .sk { font-size: 0.75rem; color: var(--text-muted); margin-top: .2rem; }
.review-section h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 1.2rem; display:flex;align-items:center;gap:.5rem; }
.review-card {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px;
    padding: 1.5rem; margin-bottom: 1rem;
}
.review-card.correct-card { border-left: 4px solid var(--success); }
.review-card.wrong-card   { border-left: 4px solid var(--danger); }
.review-opt {
    display: flex; align-items: center; gap: 0.75rem;
    background: var(--bg-card2); border: 1.5px solid var(--border);
    border-radius: 10px; padding: .6rem .9rem; margin-bottom: .5rem;
}
.review-opt.user-pick  { border-color: var(--danger);  background: rgba(239,68,68,.08); }
.review-opt.user-pick.is-correct  { border-color: var(--success); background: rgba(34,197,94,.08); }
.review-opt.correct-ans { border-color: var(--success); background: rgba(34,197,94,.08); }
.opt-icon { width: 28px; height: 28px; border-radius: 50%; display:flex;align-items:center;justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }
.opt-icon.neutral { background:#2a2f47; color:var(--text-muted); }
.opt-icon.green   { background:var(--success); color:#fff; }
.opt-icon.red     { background:var(--danger);  color:#fff; }
.explanation-box { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.3); border-radius: 8px; padding: .75rem 1rem; margin-top: .75rem; font-size: .88rem; color: #fcd34d; }
.action-row { display:flex;flex-wrap:wrap;gap:.8rem;justify-content:center;margin-top:2rem; }
.btn-action {
    display:inline-flex;align-items:center;gap:.5rem;
    border-radius:10px;padding:.7rem 1.4rem;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;
    transition:opacity .2s,transform .15s;
}
.btn-action:hover{opacity:.88;transform:translateY(-2px);}
.btn-primary2 { background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff; }
.btn-outline2  { background:transparent;border:1.5px solid var(--border);color:var(--text-main); }

/* Responsive */
@media(max-width:600px){
    .info-grid{grid-template-columns:1fr;}
    .quiz-topbar .exam-title{display:none;}
}
</style>
</head>
<body>

<!-- ===== TOP BAR ===== -->
<div class="quiz-topbar">
    <a href="hoc.php?course_id=<?= $course_id ?>" class="logo">
        <i class="bi bi-mortarboard-fill"></i> EduNova
    </a>
    <div class="exam-title"><?= htmlspecialchars($exam['title']) ?></div>

    <?php if (isset($mode) && $mode === 'quiz'): ?>
    <div class="timer-box" id="timerBox">
        <i class="bi bi-stopwatch"></i>
        <span id="timerDisplay"><?= fmt_time((int)$exam['duration_minutes'] * 60) ?></span>
    </div>
    <div class="progress-info" id="progressInfo">
        <span id="answeredCount">0</span>/<?= count($questions) ?> câu
    </div>
    <?php endif; ?>
</div>


<?php /* ======================= TRANG GIỚI THIỆU ======================= */ ?>
<?php if (isset($mode) && $mode === 'intro'): ?>
<div class="intro-wrapper">
    <div class="intro-card">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="hoc.php?course_id=<?= $course_id ?>" class="btn-back"><i class="bi bi-arrow-left"></i> Quay lại khóa học</a>
        </div>
        <h1><?= htmlspecialchars($exam['title']) ?></h1>
        <p class="sub"><?= htmlspecialchars($exam['description'] ?? '') ?></p>

        <div class="info-grid">
            <div class="info-item">
                <i class="bi bi-clock"></i>
                <div>
                    <div class="label">Thời gian làm bài</div>
                    <div class="val"><?= (int)$exam['duration_minutes'] ?> phút</div>
                </div>
            </div>
            <div class="info-item">
                <i class="bi bi-patch-question"></i>
                <div>
                    <div class="label">Số câu hỏi</div>
                    <?php
                    $cnt_res = $conn->query("SELECT COUNT(*) as cnt FROM exam_questions WHERE exam_id = $exam_id");
                    $q_count = $cnt_res ? (int)$cnt_res->fetch_assoc()['cnt'] : 0;
                    ?>
                    <div class="val"><?= $q_count ?> câu</div>
                </div>
            </div>
            <div class="info-item">
                <i class="bi bi-award"></i>
                <div>
                    <div class="label">Điểm đạt</div>
                    <div class="val"><?= (int)$exam['pass_score'] ?>%</div>
                </div>
            </div>
            <div class="info-item">
                <i class="bi bi-arrow-repeat"></i>
                <div>
                    <div class="label">Số lần thi còn lại</div>
                    <div class="val" style="color: <?= $attempts_left > 0 ? 'var(--success)' : 'var(--danger)' ?>">
                        <?= $attempts_left ?>/<?= (int)$exam['max_attempts'] ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3">
            <?php if ($attempts_left > 0): ?>
            <a href="quiz.php?exam_id=<?= $exam_id ?>&course_id=<?= $course_id ?>&start=1" class="btn-start">
                <i class="bi bi-play-fill"></i> Bắt đầu làm bài
            </a>
            <?php else: ?>
            <span class="attempt-badge text-danger"><i class="bi bi-x-circle"></i> Đã hết lượt thi</span>
            <?php endif; ?>
            <?php if ($attempts_used > 0): ?>
            <?php
            $last_res = $conn->query("SELECT id FROM exam_results WHERE user_id = $user_id AND exam_id = $exam_id ORDER BY id DESC LIMIT 1");
            $last_result = $last_res ? $last_res->fetch_assoc() : null;
            ?>
            <?php if ($last_result): ?>
            <a href="quiz.php?exam_id=<?= $exam_id ?>&course_id=<?= $course_id ?>&result_id=<?= $last_result['id'] ?>" class="btn-back">
                <i class="bi bi-clock-history"></i> Xem lần thi trước
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['err']) && $_GET['err'] === 'max_attempts'): ?>
        <div class="mt-3 p-3 rounded" style="background:rgba(239,68,68,.1);border:1px solid var(--danger);color:var(--danger);">
            <i class="bi bi-exclamation-triangle"></i> Bạn đã dùng hết số lần thi cho phép.
        </div>
        <?php endif; ?>
    </div>
</div>


<?php /* ======================= TRANG HẾT LƯỢT THI ======================= */ ?>
<?php elseif (isset($mode) && $mode === 'maxed'): ?>
<div class="intro-wrapper">
    <div class="intro-card text-center py-5">
        <i class="bi bi-emoji-frown" style="font-size:4rem;color:var(--danger);"></i>
        <h2 class="mt-3">Hết lượt thi</h2>
        <p class="text-muted mt-2">Bạn đã sử dụng hết <?= (int)$exam['max_attempts'] ?> lượt thi cho đề này.</p>
        <?php
        $last_res2 = $conn->query("SELECT id FROM exam_results WHERE user_id = $user_id AND exam_id = $exam_id ORDER BY id DESC LIMIT 1");
        $last_r2 = $last_res2 ? $last_res2->fetch_assoc() : null;
        ?>
        <div class="action-row">
            <?php if ($last_r2): ?>
            <a href="quiz.php?exam_id=<?= $exam_id ?>&course_id=<?= $course_id ?>&result_id=<?= $last_r2['id'] ?>" class="btn-action btn-outline2">
                <i class="bi bi-clipboard-data"></i> Xem kết quả
            </a>
            <?php endif; ?>
            <a href="hoc.php?course_id=<?= $course_id ?>" class="btn-action btn-primary2">
                <i class="bi bi-arrow-left"></i> Quay về khóa học
            </a>
        </div>
    </div>
</div>


<?php /* ======================= TRANG LÀM BÀI ======================= */ ?>
<?php elseif (isset($mode) && $mode === 'quiz'): ?>
<form method="POST" id="quizForm" action="quiz.php?exam_id=<?= $exam_id ?>&course_id=<?= $course_id ?>">
    <input type="hidden" name="exam_id"   value="<?= $exam_id ?>">
    <input type="hidden" name="course_id" value="<?= $course_id ?>">
    <input type="hidden" name="start_time" id="startTimeInput" value="<?= time() ?>">
    <input type="hidden" name="submit_exam" value="1">

    <div class="quiz-wrapper">
        <?php if (empty($questions)): ?>
        <div class="text-center py-5" style="color:var(--text-muted);">
            <i class="bi bi-inbox" style="font-size:3rem;"></i>
            <p class="mt-2">Đề thi chưa có câu hỏi nào.</p>
        </div>
        <?php else: ?>
        <?php foreach ($questions as $idx => $q): ?>
        <?php
        $qid  = (int)$q['id'];
        $num  = $idx + 1;
        ?>
        <div class="question-card" id="qcard_<?= $qid ?>">
            <div class="question-num">Câu <?= $num ?>/<?= count($questions) ?></div>
            <div class="question-text"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>
            <div class="options-grid">
                <?php foreach ($option_labels as $oi => $label): ?>
                <?php $opt_key = $option_keys[$oi]; $opt_text = $q[$opt_key] ?? ''; if(empty($opt_text)) continue; ?>
                <label class="option-label" onclick="countAnswered()">
                    <input type="radio" name="answer_<?= $qid ?>" value="<?= $label ?>">
                    <div class="opt-box"><?= $label ?></div>
                    <span class="opt-text"><?= htmlspecialchars($opt_text) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Submit Bar (sticky bottom) -->
    <div class="submit-bar">
        <div class="answered-counter">
            Đã trả lời: <span id="answeredCount2">0</span>/<?= count($questions) ?> câu
        </div>
        <button type="button" class="btn-submit" onclick="confirmSubmit()">
            <i class="bi bi-send-check-fill"></i> Nộp bài
        </button>
    </div>
</form>


<?php /* ======================= TRANG KẾT QUẢ ======================= */ ?>
<?php elseif (isset($mode) && $mode === 'result'): ?>
<?php
$score_val  = (int)$result['score'];
$passed_val = (int)$result['passed'];
$total_val  = (int)$result['total_questions'];
$correct_val= (int)$result['correct_answers'];
$wrong_val  = $total_val - $correct_val;
$time_val   = (int)$result['time_taken_seconds'];
$ring_class = $passed_val ? 'passed' : 'failed';
?>
<div class="result-wrapper">

    <!-- Score Card -->
    <div class="score-card">
        <div class="score-ring <?= $ring_class ?>">
            <div class="pct" style="color:<?= $passed_val ? 'var(--success)' : 'var(--danger)' ?>"><?= $score_val ?>%</div>
            <div class="pct-label">Điểm số</div>
        </div>

        <?php if ($passed_val): ?>
        <span class="badge-pass"><i class="bi bi-trophy-fill"></i> Đạt yêu cầu</span>
        <p class="mt-2" style="color:var(--text-muted);">Chúc mừng! Bạn đã vượt qua bài kiểm tra.</p>
        <?php else: ?>
        <span class="badge-fail"><i class="bi bi-x-circle-fill"></i> Chưa đạt</span>
        <p class="mt-2" style="color:var(--text-muted);">Điểm đạt yêu cầu: <?= (int)$exam['pass_score'] ?>%. Hãy ôn tập và thử lại!</p>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="sv" style="color:var(--success)"><?= $correct_val ?></div>
                <div class="sk">Câu đúng</div>
            </div>
            <div class="stat-box">
                <div class="sv" style="color:var(--danger)"><?= $wrong_val ?></div>
                <div class="sk">Câu sai</div>
            </div>
            <div class="stat-box">
                <div class="sv"><?= $total_val ?></div>
                <div class="sk">Tổng câu</div>
            </div>
            <div class="stat-box">
                <div class="sv"><?= fmt_time($time_val) ?></div>
                <div class="sk">Thời gian</div>
            </div>
            <div class="stat-box">
                <div class="sv"><?= (int)$exam['pass_score'] ?>%</div>
                <div class="sk">Điểm đạt</div>
            </div>
        </div>
    </div>

    <!-- Review Answers -->
    <div class="review-section">
        <h3><i class="bi bi-clipboard-check" style="color:var(--accent2)"></i> Chi tiết bài làm</h3>

        <?php foreach ($detail_answers as $di => $da): ?>
        <?php
        $ua       = strtoupper($da['user_answer'] ?? '');
        $ca       = strtoupper($da['correct_answer']);
        $is_right = ($ua === $ca);
        $card_cls = $is_right ? 'correct-card' : 'wrong-card';
        ?>
        <div class="review-card <?= $card_cls ?>">
            <div class="d-flex align-items-start gap-2 mb-3">
                <span style="color:var(--text-muted);font-size:.8rem;min-width:60px;">Câu <?= $di+1 ?></span>
                <?php if ($is_right): ?>
                    <span style="color:var(--success);font-size:.85rem;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Đúng</span>
                <?php else: ?>
                    <span style="color:var(--danger);font-size:.85rem;font-weight:700;"><i class="bi bi-x-circle-fill"></i> Sai</span>
                <?php endif; ?>
            </div>
            <div class="question-text mb-3" style="font-size:.95rem"><?= nl2br(htmlspecialchars($da['question_text'])) ?></div>

            <?php foreach ($option_labels as $oi => $label): ?>
            <?php
            $opt_key  = $option_keys[$oi];
            $opt_text = $da[$opt_key] ?? '';
            if (empty($opt_text)) continue;
            $is_user_pick = ($label === $ua);
            $is_correct_ans = ($label === $ca);
            $row_cls = '';
            $icon_cls = 'neutral';
            $icon_char= $label;
            if ($is_correct_ans) { $row_cls = 'correct-ans'; $icon_cls = 'green'; $icon_char = '✓'; }
            if ($is_user_pick && !$is_right) { $row_cls = 'user-pick'; $icon_cls = 'red'; $icon_char = '✗'; }
            if ($is_user_pick && $is_right)  { $row_cls = 'user-pick is-correct'; }
            ?>
            <div class="review-opt <?= $row_cls ?>">
                <div class="opt-icon <?= $icon_cls ?>"><?= $icon_char ?></div>
                <span style="font-size:.9rem"><?= htmlspecialchars($opt_text) ?></span>
                <?php if ($is_user_pick && !$is_correct_ans): ?>
                    <span class="ms-auto" style="font-size:.75rem;color:var(--danger);">Bạn chọn</span>
                <?php elseif ($is_correct_ans && !$is_user_pick): ?>
                    <span class="ms-auto" style="font-size:.75rem;color:var(--success);">Đáp án đúng</span>
                <?php elseif ($is_user_pick && $is_correct_ans): ?>
                    <span class="ms-auto" style="font-size:.75rem;color:var(--success);">Bạn chọn ✓</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($da['explanation'])): ?>
            <div class="explanation-box">
                <i class="bi bi-lightbulb-fill"></i>
                <strong>Giải thích:</strong> <?= htmlspecialchars($da['explanation']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Action Buttons -->
    <div class="action-row">
        <?php if ($attempts_left > 0): ?>
        <a href="quiz.php?exam_id=<?= $exam_id ?>&course_id=<?= $course_id ?>&start=1" class="btn-action btn-primary2">
            <i class="bi bi-arrow-clockwise"></i> Thi lại (còn <?= $attempts_left ?> lượt)
        </a>
        <?php endif; ?>
        <a href="hoc.php?course_id=<?= $course_id ?>" class="btn-action btn-outline2">
            <i class="bi bi-arrow-left"></i> Quay về khóa học
        </a>
        <a href="index.php" class="btn-action btn-outline2">
            <i class="bi bi-house"></i> Trang chủ
        </a>
    </div>
</div>
<?php endif; ?>


<script>
// ===== TIMER (quiz mode only) =====
(function(){
    const timerBox  = document.getElementById('timerBox');
    const timerDisp = document.getElementById('timerDisplay');
    if (!timerBox || !timerDisp) return;

    const TOTAL_SEC = <?= isset($mode) && $mode === 'quiz' ? (int)$exam['duration_minutes'] * 60 : 0 ?>;
    const STORE_KEY = 'quiz_timer_<?= $exam_id ?>_<?= $user_id ?>';
    const STORE_START= 'quiz_start_<?= $exam_id ?>_<?= $user_id ?>';

    // Khôi phục hoặc bắt đầu mới
    let startTs = parseInt(sessionStorage.getItem(STORE_START) || '0');
    if (!startTs) {
        startTs = Math.floor(Date.now() / 1000);
        sessionStorage.setItem(STORE_START, startTs);
    }

    // Cập nhật input start_time
    const startInput = document.getElementById('startTimeInput');
    if (startInput) startInput.value = startTs;

    function tick() {
        const now     = Math.floor(Date.now() / 1000);
        const elapsed = now - startTs;
        const left    = Math.max(0, TOTAL_SEC - elapsed);
        const m = Math.floor(left / 60);
        const s = left % 60;
        timerDisp.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');

        timerBox.className = 'timer-box';
        if (left <= 60)   timerBox.classList.add('danger');
        else if (left <= 300) timerBox.classList.add('warning');

        if (left <= 0) {
            clearInterval(timerInterval);
            timerDisp.textContent = '00:00';
            // Auto nộp bài
            sessionStorage.removeItem(STORE_START);
            document.getElementById('quizForm').submit();
        }
    }

    tick();
    const timerInterval = setInterval(tick, 1000);
})();

// ===== ĐẾM SỐ CÂU ĐÃ TRẢ LỜI =====
function countAnswered() {
    setTimeout(function(){
        const total  = document.querySelectorAll('.question-card').length;
        let answered = 0;
        document.querySelectorAll('.question-card').forEach(function(card){
            if (card.querySelector('input[type="radio"]:checked')) answered++;
        });
        const el1 = document.getElementById('answeredCount');
        const el2 = document.getElementById('answeredCount2');
        if (el1) el1.textContent = answered;
        if (el2) el2.textContent = answered;
    }, 50);
}

// ===== XÁC NHẬN NỘP BÀI =====
function confirmSubmit() {
    const total  = document.querySelectorAll('.question-card').length;
    let answered = 0;
    document.querySelectorAll('.question-card').forEach(function(card){
        if (card.querySelector('input[type="radio"]:checked')) answered++;
    });
    const unanswered = total - answered;
    let msg = 'Bạn có chắc muốn nộp bài?';
    if (unanswered > 0) {
        msg = 'Còn ' + unanswered + ' câu chưa trả lời. Bạn có chắc muốn nộp bài?';
    }
    if (confirm(msg)) {
        sessionStorage.removeItem('quiz_start_<?= $exam_id ?>_<?= $user_id ?>');
        document.getElementById('quizForm').submit();
    }
}

// Lắng nghe tất cả radio để cập nhật đếm
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('input[type="radio"]').forEach(function(r){
        r.addEventListener('change', countAnswered);
    });
});
</script>

</body>
</html>
