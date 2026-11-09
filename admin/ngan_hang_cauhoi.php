<?php
require_once '../config.php';
require_once 'db_helper.php';
init_admin_database($conn);

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Ngân hàng Câu hỏi';
$active_nav = 'cauhoi';

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Thêm hoặc sửa câu hỏi
    if ($action === 'add' || $action === 'edit') {
        $q_id        = (int)($_POST['question_id'] ?? 0);
        $course_id   = (int)($_POST['course_id'] ?? 0);
        $question_text = trim($_POST['question_text'] ?? '');
        $opt_a       = trim($_POST['option_a'] ?? '');
        $opt_b       = trim($_POST['option_b'] ?? '');
        $opt_c       = trim($_POST['option_c'] ?? '');
        $opt_d       = trim($_POST['option_d'] ?? '');
        $correct     = strtoupper(trim($_POST['correct_answer'] ?? 'A'));
        $difficulty  = trim($_POST['difficulty'] ?? 'medium');
        $explanation = trim($_POST['explanation'] ?? '');

        if (empty($question_text) || empty($opt_a) || empty($opt_b) || $course_id <= 0) {
            $_SESSION['flash_msg'] = "Vui lòng nhập đầy đủ nội dung câu hỏi, khóa học và ít nhất 2 đáp án (A, B)!";
            $_SESSION['flash_type'] = "danger";
        } else {
            $s_q   = $conn->real_escape_string($question_text);
            $s_a   = $conn->real_escape_string($opt_a);
            $s_b   = $conn->real_escape_string($opt_b);
            $s_c   = $conn->real_escape_string($opt_c);
            $s_d   = $conn->real_escape_string($opt_d);
            $s_cor = $conn->real_escape_string($correct);
            $s_dif = $conn->real_escape_string($difficulty);
            $s_exp = $conn->real_escape_string($explanation);

            if ($action === 'edit' && $q_id > 0) {
                $sql = "UPDATE questions SET 
                        course_id = $course_id,
                        question_text = '$s_q',
                        option_a = '$s_a',
                        option_b = '$s_b',
                        option_c = '$s_c',
                        option_d = '$s_d',
                        correct_answer = '$s_cor',
                        difficulty = '$s_dif',
                        explanation = '$s_exp'
                        WHERE id = $q_id";
                if ($conn->query($sql)) {
                    $_SESSION['flash_msg'] = "Cập nhật thành công câu hỏi #$q_id!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $_SESSION['flash_msg'] = "Lỗi khi cập nhật câu hỏi: " . $conn->error;
                    $_SESSION['flash_type'] = "danger";
                }
            } else {
                $sql = "INSERT INTO questions (course_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty, explanation)
                        VALUES ($course_id, '$s_q', '$s_a', '$s_b', '$s_c', '$s_d', '$s_cor', '$s_dif', '$s_exp')";
                if ($conn->query($sql)) {
                    $new_id = $conn->insert_id;
                    $_SESSION['flash_msg'] = "Thêm mới thành công câu hỏi #$new_id vào ngân hàng!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $_SESSION['flash_msg'] = "Lỗi khi thêm câu hỏi: " . $conn->error;
                    $_SESSION['flash_type'] = "danger";
                }
            }
        }
        header("Location: ngan_hang_cauhoi.php");
        exit();
    }

    // Xóa câu hỏi
    if ($action === 'delete') {
        $q_id = (int)($_POST['question_id'] ?? 0);
        if ($q_id > 0) {
            $conn->query("DELETE FROM exam_questions WHERE question_id = $q_id");
            $conn->query("DELETE FROM questions WHERE id = $q_id");
            $_SESSION['flash_msg'] = "Đã xóa câu hỏi khỏi ngân hàng!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ngan_hang_cauhoi.php");
        exit();
    }
}

// Bộ lọc
$filter_course = (int)($_GET['course_id'] ?? 0);
$filter_diff   = trim($_GET['difficulty'] ?? '');
$search_q      = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
if ($filter_course > 0) {
    $where .= " AND q.course_id = $filter_course";
}
if (!empty($filter_diff)) {
    $sd = $conn->real_escape_string($filter_diff);
    $where .= " AND q.difficulty = '$sd'";
}
if (!empty($search_q)) {
    $sq = $conn->real_escape_string($search_q);
    $where .= " AND (q.question_text LIKE '%$sq%' OR q.explanation LIKE '%$sq%')";
}

// Danh sách câu hỏi
$questions_query = "SELECT q.*, c.title as course_title 
    FROM questions q 
    LEFT JOIN courses c ON c.id = q.course_id 
    $where ORDER BY q.id DESC";
$questions_res = $conn->query($questions_query);
$questions = [];
if ($questions_res) {
    while ($r = $questions_res->fetch_assoc()) $questions[] = $r;
}

// Khóa học cho dropdown
$courses_res = $conn->query("SELECT id, title FROM courses ORDER BY id DESC");
$courses_list = [];
if ($courses_res) {
    while ($r = $courses_res->fetch_assoc()) $courses_list[] = $r;
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Ngân hàng Câu hỏi (US-06)</h4>
        <p>Lưu trữ các câu hỏi trắc nghiệm đa lựa chọn, phân loại độ khó, giải thích chi tiết để đưa vào đề thi</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddQuestion">
            <i class="bi bi-plus-circle me-1"></i> Soạn Câu Hỏi Mới
        </button>
    </div>
</div>

<!-- THANH TÌM KIẾM & BỘ LỌC CÂU HỎI -->
<div class="table-card mb-4 p-3">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Tìm nội dung câu hỏi..." value="<?= htmlspecialchars($search_q) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="course_id" class="form-select">
                <option value="0">-- Tất cả khóa học --</option>
                <?php foreach ($courses_list as $co): ?>
                    <option value="<?= $co['id'] ?>" <?= $co['id'] == $filter_course ? 'selected' : '' ?>>
                        <?= htmlspecialchars($co['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="difficulty" class="form-select">
                <option value="">-- Mọi độ khó --</option>
                <option value="easy" <?= $filter_diff === 'easy' ? 'selected' : '' ?>>Dễ</option>
                <option value="medium" <?= $filter_diff === 'medium' ? 'selected' : '' ?>>Trung bình</option>
                <option value="hard" <?= $filter_diff === 'hard' ? 'selected' : '' ?>>Khó</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Lọc</button>
            <?php if (!empty($search_q) || $filter_course > 0 || !empty($filter_diff)): ?>
                <a href="ngan_hang_cauhoi.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- BẢNG CÂU HỎI -->
<div class="table-card">
    <div class="table-card-header">
        <div class="title"><i class="bi bi-patch-question me-2 text-primary"></i>Danh sách Câu hỏi trong Ngân hàng (<?= count($questions) ?>)</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 320px;">Nội dung câu hỏi</th>
                    <th>Khóa học</th>
                    <th>Đáp án đúng</th>
                    <th>Độ khó</th>
                    <th class="text-end" style="width: 140px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($questions)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Không có câu hỏi nào trong ngân hàng. Hãy bấm "Soạn Câu Hỏi Mới".</td></tr>
                <?php else: foreach ($questions as $q): ?>
                    <tr>
                        <td class="fw-bold text-muted">#<?= $q['id'] ?></td>
                        <td>
                            <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($q['question_text']) ?></div>
                            <div class="small text-muted">
                                <div><b>A:</b> <?= htmlspecialchars($q['option_a']) ?></div>
                                <div><b>B:</b> <?= htmlspecialchars($q['option_b']) ?></div>
                                <?php if (!empty($q['option_c'])): ?><div><b>C:</b> <?= htmlspecialchars($q['option_c']) ?></div><?php endif; ?>
                                <?php if (!empty($q['option_d'])): ?><div><b>D:</b> <?= htmlspecialchars($q['option_d']) ?></div><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($q['course_title'] ?? 'N/A') ?></span>
                        </td>
                        <td>
                            <span class="badge bg-success fs-6 px-2.5 py-1">
                                Đáp án <?= htmlspecialchars($q['correct_answer']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($q['difficulty'] === 'easy'): ?>
                                <span class="badge-easy">Dễ</span>
                            <?php elseif ($q['difficulty'] === 'hard'): ?>
                                <span class="badge-hard">Khó</span>
                            <?php else: ?>
                                <span class="badge-medium">Trung bình</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick='openEditQuestionModal(<?= json_encode($q) ?>)' title="Sửa câu hỏi">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="confirmDeleteQuestion(<?= $q['id'] ?>)" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL THÊM CÂU HỎI -->
<div class="modal fade" id="modalAddQuestion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Soạn Câu Hỏi Trắc Nghiệm Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Khóa học áp dụng <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select" required>
                            <option value="">-- Chọn khóa học --</option>
                            <?php foreach ($courses_list as $co): ?>
                                <option value="<?= $co['id'] ?>">#<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mức độ khó</label>
                        <select name="difficulty" class="form-select">
                            <option value="easy">Dễ (Nhận biết)</option>
                            <option value="medium" selected>Trung bình (Thông hiểu)</option>
                            <option value="hard">Khó (Vận dụng cao)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                        <textarea name="question_text" class="form-control" rows="3" required placeholder="Nhập câu hỏi trắc nghiệm..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Đáp án A <span class="text-danger">*</span></label>
                        <input type="text" name="option_a" class="form-control" required placeholder="Nội dung phương án A">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Đáp án B <span class="text-danger">*</span></label>
                        <input type="text" name="option_b" class="form-control" required placeholder="Nội dung phương án B">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Đáp án C</label>
                        <input type="text" name="option_c" class="form-control" placeholder="Nội dung phương án C (nếu có)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Đáp án D</label>
                        <input type="text" name="option_d" class="form-control" placeholder="Nội dung phương án D (nếu có)">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold text-success">Chọn Đáp án đúng <span class="text-danger">*</span></label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_answer" id="radAddA" value="A" checked>
                                <label class="form-check-label fw-bold" for="radAddA">A</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_answer" id="radAddB" value="B">
                                <label class="form-check-label fw-bold" for="radAddB">B</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_answer" id="radAddC" value="C">
                                <label class="form-check-label fw-bold" for="radAddC">C</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_answer" id="radAddD" value="D">
                                <label class="form-check-label fw-bold" for="radAddD">D</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Giải thích chi tiết (Hiển thị sau khi học viên nộp bài)</label>
                        <textarea name="explanation" class="form-control" rows="2" placeholder="Giải thích vì sao đáp án này đúng..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Vào Ngân Hàng</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SỬA CÂU HỎI -->
<div class="modal fade" id="modalEditQuestion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="question_id" id="edit_q_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Cập nhật Câu Hỏi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Khóa học áp dụng</label>
                        <select name="course_id" id="edit_q_course" class="form-select" required>
                            <?php foreach ($courses_list as $co): ?>
                                <option value="<?= $co['id'] ?>">#<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mức độ khó</label>
                        <select name="difficulty" id="edit_q_difficulty" class="form-select">
                            <option value="easy">Dễ</option>
                            <option value="medium">Trung bình</option>
                            <option value="hard">Khó</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                        <textarea name="question_text" id="edit_q_text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Đáp án A <span class="text-danger">*</span></label>
                        <input type="text" name="option_a" id="edit_q_a" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Đáp án B <span class="text-danger">*</span></label>
                        <input type="text" name="option_b" id="edit_q_b" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Đáp án C</label>
                        <input type="text" name="option_c" id="edit_q_c" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Đáp án D</label>
                        <input type="text" name="option_d" id="edit_q_d" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold text-success">Chọn Đáp án đúng <span class="text-danger">*</span></label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_answer" id="radEditA" value="A">
                                <label class="form-check-label fw-bold" for="radEditA">A</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_answer" id="radEditB" value="B">
                                <label class="form-check-label fw-bold" for="radEditB">B</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_answer" id="radEditC" value="C">
                                <label class="form-check-label fw-bold" for="radEditC">C</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_answer" id="radEditD" value="D">
                                <label class="form-check-label fw-bold" for="radEditD">D</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Giải thích chi tiết</label>
                        <textarea name="explanation" id="edit_q_exp" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Thay Đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORM ẨN XÓA CÂU HỎI -->
<form id="formDeleteQuestion" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="question_id" id="delete_q_id">
</form>

<script>
function openEditQuestionModal(q) {
    document.getElementById('edit_q_id').value = q.id;
    document.getElementById('edit_q_course').value = q.course_id;
    document.getElementById('edit_q_difficulty').value = q.difficulty || 'medium';
    document.getElementById('edit_q_text').value = q.question_text || '';
    document.getElementById('edit_q_a').value = q.option_a || '';
    document.getElementById('edit_q_b').value = q.option_b || '';
    document.getElementById('edit_q_c').value = q.option_c || '';
    document.getElementById('edit_q_d').value = q.option_d || '';
    document.getElementById('edit_q_exp').value = q.explanation || '';

    var cor = (q.correct_answer || 'A').toUpperCase();
    if (document.getElementById('radEdit' + cor)) {
        document.getElementById('radEdit' + cor).checked = true;
    }
    new bootstrap.Modal(document.getElementById('modalEditQuestion')).show();
}

function confirmDeleteQuestion(id) {
    if (confirm('Bạn có chắc muốn xóa câu hỏi #' + id + ' khỏi ngân hàng?')) {
        document.getElementById('delete_q_id').value = id;
        document.getElementById('formDeleteQuestion').submit();
    }
}
</script>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

