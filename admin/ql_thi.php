<?php
require_once __DIR__ . '/../config.php';

require_admin('../login.php');

$page_title = 'Quản lý Bài kiểm tra Trắc nghiệm';
$active_nav = 'thi';

$course_id = (int)($_GET['course_id'] ?? 0);
$quiz_id   = (int)($_GET['quiz_id'] ?? 0);
$tab       = $_GET['tab'] ?? ($quiz_id > 0 ? 'questions' : 'quizzes');

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Thêm hoặc sửa Quiz
    if ($action === 'add_quiz' || $action === 'edit_quiz') {
        $q_id        = (int)($_POST['quiz_id'] ?? 0);
        $c_id        = (int)($_POST['course_id'] ?? 0);
        $s_id        = (int)($_POST['section_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration    = (int)($_POST['duration_minutes'] ?? 30);
        $pass_score  = (int)($_POST['pass_score'] ?? 70);
        $max_attempts= (int)($_POST['max_attempts'] ?? 3);
        $status      = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

        if ($title === '' || $c_id <= 0) {
            set_flash("Vui lòng nhập tên bài kiểm tra và chọn khóa học!", "danger");
        } else {
            $sec_param = $s_id > 0 ? $s_id : null;
            if ($action === 'edit_quiz' && $q_id > 0) {
                $stmt = $conn->prepare("UPDATE quizzes SET course_id = ?, section_id = ?, title = ?, description = ?, duration_minutes = ?, pass_score = ?, max_attempts = ?, status = ? WHERE id = ?");
                $stmt->bind_param('iissiiisi', $c_id, $sec_param, $title, $description, $duration, $pass_score, $max_attempts, $status, $q_id);
                $stmt->execute();
                $stmt->close();
                set_flash("Đã cập nhật bài kiểm tra #$q_id!", "success");
            } else {
                $stmt = $conn->prepare("INSERT INTO quizzes (course_id, section_id, title, description, duration_minutes, pass_score, max_attempts, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iissiiis', $c_id, $sec_param, $title, $description, $duration, $pass_score, $max_attempts, $status);
                $stmt->execute();
                $q_id = $stmt->insert_id;
                $stmt->close();
                set_flash("Đã tạo bài kiểm tra mới (#$q_id)! Hãy thêm câu hỏi trắc nghiệm.", "success");
            }
        }
        header("Location: ql_thi.php?course_id=$c_id&quiz_id=$q_id&tab=questions");
        exit();
    }

    // 2. Xóa Quiz
    if ($action === 'delete_quiz') {
        $q_id = (int)($_POST['quiz_id'] ?? 0);
        if ($q_id > 0) {
            $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
            $stmt->bind_param('i', $q_id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã xóa bài kiểm tra!", "success");
        }
        header("Location: ql_thi.php");
        exit();
    }

    // 3. Thêm hoặc sửa câu hỏi
    if ($action === 'add_question' || $action === 'edit_question') {
        $qid         = (int)($_POST['question_id'] ?? 0);
        $target_quiz = (int)($_POST['quiz_id'] ?? 0);
        $q_text      = trim($_POST['question_text'] ?? '');
        $opt_a       = trim($_POST['option_a'] ?? '');
        $opt_b       = trim($_POST['option_b'] ?? '');
        $opt_c       = trim($_POST['option_c'] ?? '');
        $opt_d       = trim($_POST['option_d'] ?? '');
        $correct     = strtoupper(trim($_POST['correct_answer'] ?? 'A'));
        $explanation = trim($_POST['explanation'] ?? '');
        $sort_order  = (int)($_POST['sort_order'] ?? 1);

        if ($q_text === '' || $opt_a === '' || $opt_b === '' || $target_quiz <= 0) {
            set_flash("Vui lòng nhập đầy đủ câu hỏi và ít nhất 2 đáp án (A, B)!", "danger");
        } else {
            if ($action === 'edit_question' && $qid > 0) {
                $stmt = $conn->prepare("UPDATE questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, explanation = ?, sort_order = ? WHERE id = ?");
                $stmt->bind_param('sssssssii', $q_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $sort_order, $qid);
                $stmt->execute();
                $stmt->close();
                set_flash("Đã cập nhật câu hỏi #$qid!", "success");
            } else {
                $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssssssi', $target_quiz, $q_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $sort_order);
                $stmt->execute();
                $stmt->close();
                set_flash("Đã thêm câu hỏi trắc nghiệm mới!", "success");
            }
        }
        header("Location: ql_thi.php?quiz_id=$target_quiz&tab=questions");
        exit();
    }

    // 4. Xóa câu hỏi
    if ($action === 'delete_question') {
        $qid = (int)($_POST['question_id'] ?? 0);
        $target_quiz = (int)($_POST['quiz_id'] ?? 0);
        if ($qid > 0) {
            $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->bind_param('i', $qid);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã xóa câu hỏi!", "success");
        }
        header("Location: ql_thi.php?quiz_id=$target_quiz&tab=questions");
        exit();
    }
}

// Lấy danh sách khóa học và danh sách chương
$courses_list = [];
$c_res = $conn->query("SELECT id, title FROM courses ORDER BY id DESC");
if ($c_res) {
    while ($r = $c_res->fetch_assoc()) $courses_list[] = $r;
}

$sections_by_course = [];
$s_res = $conn->query("SELECT id, course_id, title, sort_order FROM sections ORDER BY course_id, sort_order ASC, id ASC");
if ($s_res) {
    while ($sr = $s_res->fetch_assoc()) {
        $sections_by_course[(int)$sr['course_id']][] = $sr;
    }
}

// Lấy danh sách đề thi (quizzes)
$where_quiz = "";
if ($course_id > 0) {
    $where_quiz = "WHERE q.course_id = $course_id";
}
$quizzes = [];
$qz_res = $conn->query("SELECT q.*, c.title AS course_title, s.title AS section_title,
    (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
    (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) AS attempt_count
    FROM quizzes q
    JOIN courses c ON c.id = q.course_id
    LEFT JOIN sections s ON s.id = q.section_id
    $where_quiz
    ORDER BY q.id DESC");
if ($qz_res) {
    while ($r = $qz_res->fetch_assoc()) $quizzes[] = $r;
}

// Nếu đang xem chi tiết một Quiz
$current_quiz = null;
$questions_list = [];
if ($quiz_id > 0) {
    $stmt_cq = $conn->prepare("SELECT q.*, c.title AS course_title FROM quizzes q JOIN courses c ON c.id = q.course_id WHERE q.id = ? LIMIT 1");
    $stmt_cq->bind_param('i', $quiz_id);
    $stmt_cq->execute();
    $current_quiz = $stmt_cq->get_result()->fetch_assoc();
    $stmt_cq->close();

    if ($current_quiz) {
        $stmt_ql = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt_ql->bind_param('i', $quiz_id);
        $stmt_ql->execute();
        $ql_res = $stmt_ql->get_result();
        while ($qr = $ql_res->fetch_assoc()) $questions_list[] = $qr;
        $stmt_ql->close();
    }
}

// Lịch sử thi (attempts)
$attempts_list = [];
if ($tab === 'results') {
    $att_where = $quiz_id > 0 ? "WHERE qa.quiz_id = $quiz_id" : "";
    $att_sql = "SELECT qa.*, u.fullname, u.username, q.title AS quiz_title, c.title AS course_title
                FROM quiz_attempts qa
                JOIN users u ON u.id = qa.user_id
                JOIN quizzes q ON q.id = qa.quiz_id
                JOIN courses c ON c.id = q.course_id
                $att_where
                ORDER BY qa.id DESC LIMIT 50";
    $att_res = $conn->query($att_sql);
    if ($att_res) {
        while ($r = $att_res->fetch_assoc()) $attempts_list[] = $r;
    }
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Trắc nghiệm & Đánh giá</h4>
        <p>Tạo đề kiểm tra, soạn thảo câu hỏi & đáp án đúng (tự động chấm điểm) và theo dõi kết quả</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddQuiz" onclick="resetQuizForm()">
            <i class="bi bi-plus-circle me-1"></i> Tạo Đề Thi Mới
        </button>
    </div>
</div>

<!-- NAV TABS -->
<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'quizzes' ? 'active' : '' ?>" href="ql_thi.php?tab=quizzes<?= $course_id > 0 ? "&course_id=$course_id" : '' ?>">
            <i class="bi bi-collection me-1"></i> Danh sách Đề thi (<?= count($quizzes) ?>)
        </a>
    </li>
    <?php if ($current_quiz): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'questions' ? 'active' : '' ?>" href="ql_thi.php?quiz_id=<?= $quiz_id ?>&tab=questions">
            <i class="bi bi-patch-question-fill me-1"></i> Câu hỏi đề #<?= $quiz_id ?> (<?= count($questions_list) ?> câu)
        </a>
    </li>
    <?php endif; ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'results' ? 'active' : '' ?>" href="ql_thi.php?tab=results<?= $quiz_id > 0 ? "&quiz_id=$quiz_id" : '' ?>">
            <i class="bi bi-clipboard2-check me-1"></i> Lịch sử làm bài
        </a>
    </li>
</ul>

<?php if ($tab === 'quizzes'): ?>
    <!-- TAB 1: DANH SÁCH ĐỀ THI -->
    <div class="table-card">
        <div class="table-card-header d-flex justify-content-between align-items-center">
            <div class="title">Danh sách Đề thi trắc nghiệm</div>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="quizzes">
                <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Tất cả khóa học --</option>
                    <?php foreach ($courses_list as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $course_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên đề thi</th>
                        <th>Khóa học</th>
                        <th>Thời lượng</th>
                        <th>Điểm đạt</th>
                        <th>Số câu hỏi</th>
                        <th>Lượt làm</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quizzes)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">Chưa có đề thi nào.</td></tr>
                    <?php else: foreach ($quizzes as $q): ?>
                        <tr>
                            <td class="text-muted small">#<?= (int)$q['id'] ?></td>
                            <td>
                                <a href="ql_thi.php?quiz_id=<?= (int)$q['id'] ?>&tab=questions" class="fw-bold text-dark text-decoration-none">
                                    <?= e($q['title']) ?>
                                </a>
                                <?php if (!empty($q['section_title'])): ?>
                                    <div class="small text-muted"><i class="bi bi-folder2 me-1"></i><?= e($q['section_title']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e($q['course_title']) ?></td>
                            <td><?= (int)$q['duration_minutes'] ?> phút</td>
                            <td><span class="badge bg-success-subtle text-success"><?= (int)$q['pass_score'] ?>%</span></td>
                            <td>
                                <a href="ql_thi.php?quiz_id=<?= (int)$q['id'] ?>&tab=questions" class="badge bg-primary text-decoration-none">
                                    <?= (int)$q['question_count'] ?> câu
                                </a>
                            </td>
                            <td><?= (int)$q['attempt_count'] ?></td>
                            <td>
                                <?php if ($q['status'] === 'published'): ?>
                                    <span class="badge-active">Công khai</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Bản nháp</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="ql_thi.php?quiz_id=<?= (int)$q['id'] ?>&tab=questions" class="btn btn-sm btn-outline-info" title="Soạn câu hỏi">
                                    <i class="bi bi-list-check"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-primary" onclick='openEditQuizModal(<?= json_encode($q, JSON_UNESCAPED_UNICODE) ?>)' title="Chỉnh sửa">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Xóa đề thi này và tất cả câu hỏi bên trong?');">
                                    <input type="hidden" name="action" value="delete_quiz">
                                    <input type="hidden" name="quiz_id" value="<?= (int)$q['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'questions' && $current_quiz): ?>
    <!-- TAB 2: SOẠN CÂU HỎI CHO MỘT ĐỀ THI CỤ THỂ -->
    <div class="table-card mb-4 p-3 bg-light border">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="badge bg-primary">Đề thi #<?= $quiz_id ?></span>
                <h5 class="fw-bold text-dark mt-1 mb-0"><?= e($current_quiz['title']) ?></h5>
                <small class="text-muted"><?= e($current_quiz['course_title']) ?> · Thời gian: <?= (int)$current_quiz['duration_minutes'] ?> phút · Điểm đạt: <?= (int)$current_quiz['pass_score'] ?>%</small>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddQuestion" onclick="resetQuestionForm()">
                    <i class="bi bi-plus-circle me-1"></i> Thêm câu hỏi
                </button>
                <a href="../quiz.php?quiz_id=<?= $quiz_id ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Xem giao diện thi
                </a>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <div class="title">Danh sách câu hỏi (<?= count($questions_list) ?>)</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Nội dung câu hỏi & các lựa chọn</th>
                        <th>Đáp án đúng</th>
                        <th>Giải thích</th>
                        <th class="text-end" style="width: 120px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questions_list)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Đề thi chưa có câu hỏi nào. Hãy bấm "Thêm câu hỏi" ở trên.</td></tr>
                    <?php else: foreach ($questions_list as $idx => $q): ?>
                        <tr>
                            <td class="fw-bold text-muted"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-semibold text-dark mb-1"><?= e($q['question_text']) ?></div>
                                <div class="row g-1 small text-muted">
                                    <div class="col-sm-6 <?= $q['correct_answer'] === 'A' ? 'fw-bold text-success' : '' ?>"><b>A:</b> <?= e($q['option_a']) ?></div>
                                    <div class="col-sm-6 <?= $q['correct_answer'] === 'B' ? 'fw-bold text-success' : '' ?>"><b>B:</b> <?= e($q['option_b']) ?></div>
                                    <?php if (!empty($q['option_c'])): ?>
                                        <div class="col-sm-6 <?= $q['correct_answer'] === 'C' ? 'fw-bold text-success' : '' ?>"><b>C:</b> <?= e($q['option_c']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($q['option_d'])): ?>
                                        <div class="col-sm-6 <?= $q['correct_answer'] === 'D' ? 'fw-bold text-success' : '' ?>"><b>D:</b> <?= e($q['option_d']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-success fs-6"><?= e($q['correct_answer']) ?></span>
                            </td>
                            <td class="small text-secondary"><?= e($q['explanation'] ?? '') ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick='openEditQuestionModal(<?= json_encode($q, JSON_UNESCAPED_UNICODE) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Xóa câu hỏi này?');">
                                    <input type="hidden" name="action" value="delete_question">
                                    <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                                    <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'results'): ?>
    <!-- TAB 3: LỊCH SỬ KẾT QUẢ THI CỦA HỌC VIÊN -->
    <div class="table-card">
        <div class="table-card-header">
            <div class="title">Lịch sử kết quả làm bài trắc nghiệm của học viên</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Học viên</th>
                        <th>Đề thi</th>
                        <th>Khóa học</th>
                        <th>Điểm số</th>
                        <th>Câu đúng</th>
                        <th>Kết quả</th>
                        <th>Thời gian nộp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attempts_list)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Chưa có lượt nộp bài nào.</td></tr>
                    <?php else: foreach ($attempts_list as $a): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($a['fullname']) ?></div>
                                <div class="small text-muted">@<?= e($a['username']) ?></div>
                            </td>
                            <td class="fw-bold text-dark"><?= e($a['quiz_title']) ?></td>
                            <td class="small text-muted"><?= e($a['course_title']) ?></td>
                            <td><span class="fw-bold text-primary"><?= number_format((float)$a['score'], 1) ?>%</span></td>
                            <td><?= (int)$a['correct_count'] ?>/<?= (int)$a['total_questions'] ?> câu</td>
                            <td>
                                <?php if ($a['passed']): ?>
                                    <span class="badge bg-success-subtle text-success">Đạt</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Chưa đạt</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($a['submitted_at'] ?? $a['started_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- MODAL THÊM / SỬA ĐỀ THI -->
<div class="modal fade" id="modalAddQuiz" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" id="quizAction" value="add_quiz">
            <input type="hidden" name="quiz_id" id="quizId" value="0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="quizModalTitle">Tạo Đề Thi Trắc Nghiệm Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Tên bài kiểm tra *</label>
                    <input type="text" name="title" id="quizTitle" class="form-control" required placeholder="Ví dụ: Kiểm tra trắc nghiệm Chương 1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Thuộc khóa học *</label>
                    <select name="course_id" id="quizCourse" class="form-select" required onchange="updateSectionDropdown(this.value)">
                        <option value="">-- Chọn khóa học --</option>
                        <?php foreach ($courses_list as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Thuộc chương môn học *</label>
                    <select name="section_id" id="quizSection" class="form-select" required>
                        <option value="">-- Chọn chương trước --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Thời gian (phút)</label>
                    <input type="number" name="duration_minutes" id="quizDuration" class="form-control" value="30">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Điểm đạt (%)</label>
                    <input type="number" name="pass_score" id="quizPassScore" class="form-control" value="70">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lượt thi tối đa</label>
                    <input type="number" name="max_attempts" id="quizAttempts" class="form-control" value="3">
                </div>
                <div class="col-12">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" id="quizStatus" class="form-select">
                        <option value="published">Công khai</option>
                        <option value="draft">Bản nháp</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả / Hướng dẫn làm bài</label>
                    <textarea name="description" id="quizDesc" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu Đề Thi</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL THÊM / SỬA CÂU HỎI -->
<div class="modal fade" id="modalAddQuestion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" id="qAction" value="add_question">
            <input type="hidden" name="question_id" id="qId" value="0">
            <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="qModalTitle">Thêm Câu Hỏi Trắc Nghiệm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Nội dung câu hỏi *</label>
                    <textarea name="question_text" id="qText" class="form-control" rows="3" required placeholder="Nhập nội dung câu hỏi..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lựa chọn A *</label>
                    <input type="text" name="option_a" id="qOptA" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lựa chọn B *</label>
                    <input type="text" name="option_b" id="qOptB" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lựa chọn C</label>
                    <input type="text" name="option_c" id="qOptC" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lựa chọn D</label>
                    <input type="text" name="option_d" id="qOptD" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Đáp án đúng (Tự động chấm) *</label>
                    <select name="correct_answer" id="qCorrect" class="form-select fw-bold text-success">
                        <option value="A">Đáp án A</option>
                        <option value="B">Đáp án B</option>
                        <option value="C">Đáp án C</option>
                        <option value="D">Đáp án D</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Thứ tự câu hỏi</label>
                    <input type="number" name="sort_order" id="qOrder" class="form-control" value="<?= count($questions_list) + 1 ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Giải thích chi tiết (Hiển thị sau khi nộp bài)</label>
                    <textarea name="explanation" id="qExp" class="form-control" rows="2" placeholder="Giải thích vì sao đáp án này đúng..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu Câu Hỏi</button>
            </div>
        </form>
    </div>
</div>

<script>
const sectionsByCourse = <?= json_encode($sections_by_course, JSON_UNESCAPED_UNICODE) ?>;

function updateSectionDropdown(courseId, selectedSectionId = 0) {
    const secSelect = document.getElementById('quizSection');
    secSelect.innerHTML = '<option value="">-- Chọn chương môn học --</option>';
    const cId = parseInt(courseId);
    if (sectionsByCourse[cId]) {
        sectionsByCourse[cId].forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.title;
            if (parseInt(s.id) === parseInt(selectedSectionId)) {
                opt.selected = true;
            }
            secSelect.appendChild(opt);
        });
    }
}

function resetQuizForm() {
    document.getElementById('quizModalTitle').innerText = 'Tạo Đề Thi Trắc Nghiệm Mới';
    document.getElementById('quizAction').value = 'add_quiz';
    document.getElementById('quizId').value = '0';
    document.getElementById('quizTitle').value = '';
    document.getElementById('quizCourse').value = '';
    document.getElementById('quizSection').innerHTML = '<option value="">-- Chọn khóa học trước --</option>';
    document.getElementById('quizDuration').value = 15;
    document.getElementById('quizPassScore').value = 70;
    document.getElementById('quizAttempts').value = 3;
    document.getElementById('quizStatus').value = 'published';
    document.getElementById('quizDesc').value = '';
}

function openEditQuizModal(q) {
    document.getElementById('quizModalTitle').innerText = 'Cập Nhật Đề Thi #' + q.id;
    document.getElementById('quizAction').value = 'edit_quiz';
    document.getElementById('quizId').value = q.id;
    document.getElementById('quizTitle').value = q.title || '';
    document.getElementById('quizCourse').value = q.course_id;
    updateSectionDropdown(q.course_id, q.section_id);
    document.getElementById('quizDuration').value = q.duration_minutes || 30;
    document.getElementById('quizPassScore').value = q.pass_score || 70;
    document.getElementById('quizAttempts').value = q.max_attempts || 3;
    document.getElementById('quizStatus').value = q.status || 'published';
    document.getElementById('quizDesc').value = q.description || '';
    new bootstrap.Modal(document.getElementById('modalAddQuiz')).show();
}

function resetQuestionForm() {
    document.getElementById('qModalTitle').innerText = 'Thêm Câu Hỏi Trắc Nghiệm';
    document.getElementById('qAction').value = 'add_question';
    document.getElementById('qId').value = '0';
    document.getElementById('qText').value = '';
    document.getElementById('qOptA').value = '';
    document.getElementById('qOptB').value = '';
    document.getElementById('qOptC').value = '';
    document.getElementById('qOptD').value = '';
    document.getElementById('qCorrect').value = 'A';
    document.getElementById('qExp').value = '';
}

function openEditQuestionModal(q) {
    document.getElementById('qModalTitle').innerText = 'Cập Nhật Câu Hỏi';
    document.getElementById('qAction').value = 'edit_question';
    document.getElementById('qId').value = q.id;
    document.getElementById('qText').value = q.question_text || '';
    document.getElementById('qOptA').value = q.option_a || '';
    document.getElementById('qOptB').value = q.option_b || '';
    document.getElementById('qOptC').value = q.option_c || '';
    document.getElementById('qOptD').value = q.option_d || '';
    document.getElementById('qCorrect').value = q.correct_answer || 'A';
    document.getElementById('qOrder').value = q.sort_order || 1;
    document.getElementById('qExp').value = q.explanation || '';
    new bootstrap.Modal(document.getElementById('modalAddQuestion')).show();
}
</script>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
