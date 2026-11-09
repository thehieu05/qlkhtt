<?php
require_once '../config.php';
require_once 'db_helper.php';
init_admin_database($conn);

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Quản lý Thi Trực tuyến';
$active_nav = 'thi';

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Thêm đề thi mới
    if ($action === 'add' || $action === 'edit') {
        $exam_id     = (int)($_POST['exam_id'] ?? 0);
        $course_id   = (int)($_POST['course_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration    = (int)($_POST['duration_minutes'] ?? 45);
        $pass_score  = (int)($_POST['pass_score'] ?? 70);
        $max_attempts = (int)($_POST['max_attempts'] ?? 3);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title) || $course_id <= 0) {
            $_SESSION['flash_msg'] = "Vui lòng nhập tên đề thi và chọn khóa học!";
            $_SESSION['flash_type'] = "danger";
        } else {
            $s_t = $conn->real_escape_string($title);
            $s_d = $conn->real_escape_string($description);

            if ($action === 'edit' && $exam_id > 0) {
                $sql = "UPDATE exams SET 
                        course_id = $course_id,
                        title = '$s_t',
                        description = '$s_d',
                        duration_minutes = $duration,
                        pass_score = $pass_score,
                        max_attempts = $max_attempts,
                        is_active = $is_active
                        WHERE id = $exam_id";
                if ($conn->query($sql)) {
                    $_SESSION['flash_msg'] = "Cập nhật thành công đề thi #$exam_id!";
                    $_SESSION['flash_type'] = "success";
                }
            } else {
                $sql = "INSERT INTO exams (course_id, title, description, duration_minutes, pass_score, max_attempts, is_active)
                        VALUES ($course_id, '$s_t', '$s_d', $duration, $pass_score, $max_attempts, $is_active)";
                if ($conn->query($sql)) {
                    $new_id = $conn->insert_id;
                    $_SESSION['flash_msg'] = "Tạo đề thi mới (#$new_id) thành công! Hãy thêm câu hỏi vào đề thi.";
                    $_SESSION['flash_type'] = "success";
                }
            }
        }
        header("Location: ql_thi.php");
        exit();
    }

    // Gán câu hỏi vào đề thi
    if ($action === 'assign_questions') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        $question_ids = $_POST['question_ids'] ?? [];

        if ($exam_id > 0) {
            // Xóa câu hỏi cũ
            $conn->query("DELETE FROM exam_questions WHERE exam_id = $exam_id");
            if (!empty($question_ids)) {
                $order = 1;
                foreach ($question_ids as $qid) {
                    $qid = (int)$qid;
                    if ($qid > 0) {
                        $conn->query("INSERT INTO exam_questions (exam_id, question_id, sort_order) VALUES ($exam_id, $qid, $order)");
                        $order++;
                    }
                }
            }
            $_SESSION['flash_msg'] = "Đã cập nhật bộ câu hỏi cho đề thi thành công (" . count($question_ids) . " câu)!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_thi.php");
        exit();
    }

    // Xóa đề thi
    if ($action === 'delete') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        if ($exam_id > 0) {
            $conn->query("DELETE FROM exam_questions WHERE exam_id = $exam_id");
            $conn->query("DELETE FROM exam_results WHERE exam_id = $exam_id");
            $conn->query("DELETE FROM exams WHERE id = $exam_id");
            $_SESSION['flash_msg'] = "Đã xóa vĩnh viễn đề thi!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_thi.php");
        exit();
    }
}

// Lấy danh sách đề thi kèm số câu hỏi và lượt thi
$exams_query = "SELECT e.*, c.title as course_title,
    (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count,
    (SELECT COUNT(*) FROM exam_results WHERE exam_id = e.id) as test_count
    FROM exams e 
    LEFT JOIN courses c ON c.id = e.course_id 
    ORDER BY e.id DESC";
$exams_res = $conn->query($exams_query);
$exams = [];
if ($exams_res) {
    while ($r = $exams_res->fetch_assoc()) $exams[] = $r;
}

// Lấy kết quả thi gần đây của học viên
$results_query = "SELECT r.*, u.fullname, u.username, e.title as exam_title, e.pass_score 
    FROM exam_results r 
    JOIN users u ON u.id = r.user_id 
    JOIN exams e ON e.id = r.exam_id 
    ORDER BY r.id DESC LIMIT 15";
$results_res = $conn->query($results_query);
$exam_results = [];
if ($results_res) {
    while ($r = $results_res->fetch_assoc()) $exam_results[] = $r;
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
        <h4>Quản lý Thi Trực tuyến & Đánh giá (US-07)</h4>
        <p>Thiết lập đề thi trắc nghiệm, gán câu hỏi từ ngân hàng, cấu hình thời gian và theo dõi kết quả thi</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddExam">
            <i class="bi bi-plus-circle me-1"></i> Tạo Đề Thi Mới
        </button>
    </div>
</div>

<!-- TABS: DANH SÁCH ĐỀ THI VS KẾT QUẢ THI -->
<ul class="nav nav-pills mb-3" id="examTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active fw-semibold" id="tab-exams-btn" data-bs-toggle="pill" data-bs-target="#tab-exams" type="button">
            <i class="bi bi-clipboard2-check-fill me-1"></i> Danh Sách Đề Thi (<?= count($exams) ?>)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" id="tab-results-btn" data-bs-toggle="pill" data-bs-target="#tab-results" type="button">
            <i class="bi bi-trophy-fill me-1"></i> Kết Quả Thi Của Học Viên (<?= count($exam_results) ?>)
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- TAB 1: DANH SÁCH ĐỀ THI -->
    <div class="tab-pane fade show active" id="tab-exams">
        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Danh sách Đề thi trắc nghiệm</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Tên đề thi</th>
                            <th>Khóa học</th>
                            <th>Thời lượng</th>
                            <th>Điểm đạt</th>
                            <th>Số câu hỏi</th>
                            <th>Lượt thi</th>
                            <th>Trạng thái</th>
                            <th class="text-end" style="width: 170px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exams)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">Chưa có đề thi nào. Hãy bấm "Tạo Đề Thi Mới".</td></tr>
                        <?php else: foreach ($exams as $ex): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $ex['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($ex['title']) ?></div>
                                    <?php if (!empty($ex['description'])): ?>
                                        <small class="text-muted text-truncate d-block" style="max-width: 250px;"><?= htmlspecialchars($ex['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($ex['course_title'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-stopwatch me-1"></i><?= (int)$ex['duration_minutes'] ?> phút
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-warning-subtle text-warning-emphasis">
                                        ≥ <?= (int)$ex['pass_score'] ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fs-6 px-2.5 py-1">
                                        <i class="bi bi-patch-question me-1"></i><?= (int)$ex['question_count'] ?> câu
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success"><?= (int)$ex['test_count'] ?> lượt</span>
                                </td>
                                <td>
                                    <?php if (($ex['is_active'] ?? 1) == 1): ?>
                                        <span class="badge-active">Đang mở</span>
                                    <?php else: ?>
                                        <span class="badge-inactive">Đã đóng</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-success" onclick="openAssignQuestionsModal(<?= $ex['id'] ?>, <?= $ex['course_id'] ?>, '<?= htmlspecialchars(addslashes($ex['title'])) ?>')" title="Chọn câu hỏi vào đề">
                                            <i class="bi bi-list-check"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" onclick='openEditExamModal(<?= json_encode($ex) ?>)' title="Sửa đề thi">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="confirmDeleteExam(<?= $ex['id'] ?>)" title="Xóa">
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
    </div>

    <!-- TAB 2: KẾT QUẢ THI -->
    <div class="tab-pane fade" id="tab-results">
        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-award me-2 text-warning"></i>Lịch sử Làm bài Thi của Học viên</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Học viên</th>
                            <th>Đề thi</th>
                            <th>Điểm số</th>
                            <th>Số câu đúng</th>
                            <th>Thời gian làm</th>
                            <th>Kết quả</th>
                            <th>Ngày thi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exam_results)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">Chưa có học viên nào nộp bài thi.</td></tr>
                        <?php else: foreach ($exam_results as $res): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($res['fullname']) ?></div>
                                    <small class="text-muted">@<?= htmlspecialchars($res['username']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-primary"><?= htmlspecialchars($res['exam_title']) ?></div>
                                </td>
                                <td>
                                    <span class="fs-6 fw-bold text-dark"><?= (int)$res['score'] ?> / 100</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= (int)$res['correct_answers'] ?> / <?= (int)$res['total_questions'] ?> câu
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><?= round((int)$res['time_taken_seconds'] / 60, 1) ?> phút</small>
                                </td>
                                <td>
                                    <?php if (($res['passed'] ?? 0) == 1): ?>
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Đạt (Pass)</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-circle me-1"></i>Chưa đạt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($res['created_at']) ?></small>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TẠO ĐỀ THI MỚI -->
<div class="modal fade" id="modalAddExam" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Tạo Đề Thi Trắc Nghiệm Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Tên đề thi <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Ví dụ: Đề thi đánh giá kết thúc môn học">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Khóa học áp dụng <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select" required>
                            <option value="">-- Chọn khóa học --</option>
                            <?php foreach ($courses_list as $co): ?>
                                <option value="<?= $co['id'] ?>">#<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Thời gian làm bài (phút)</label>
                        <input type="number" name="duration_minutes" class="form-control" value="45" min="5">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Điểm đạt yêu cầu (%)</label>
                        <input type="number" name="pass_score" class="form-control" value="70" min="0" max="100">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Mô tả / Hướng dẫn làm bài</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Nhập quy chế thi, số lần thi tối đa và lưu ý dành cho học viên..."></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="chkActiveExam" checked>
                            <label class="form-check-label fw-semibold" for="chkActiveExam">Mở đề thi cho học viên tham gia làm bài</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Tạo Đề Thi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SỬA ĐỀ THI -->
<div class="modal fade" id="modalEditExam" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="exam_id" id="edit_exam_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Cập nhật Đề Thi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Tên đề thi <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_exam_title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Khóa học áp dụng</label>
                        <select name="course_id" id="edit_exam_course" class="form-select" required>
                            <?php foreach ($courses_list as $co): ?>
                                <option value="<?= $co['id'] ?>">#<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Thời lượng (phút)</label>
                        <input type="number" name="duration_minutes" id="edit_exam_duration" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Điểm đạt (%)</label>
                        <input type="number" name="pass_score" id="edit_exam_pass" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Mô tả / Hướng dẫn</label>
                        <textarea name="description" id="edit_exam_desc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_exam_active">
                            <label class="form-check-label fw-semibold" for="edit_exam_active">Mở đề thi cho học viên</label>
                        </div>
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

<!-- MODAL GÁN CÂU HỎI VÀO ĐỀ THI -->
<div class="modal fade" id="modalAssignQuestions" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="assign_questions">
                <input type="hidden" name="exam_id" id="assign_exam_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-list-check text-primary me-2"></i>Chọn Câu Hỏi Vào Đề Thi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">Đang cấu hình câu hỏi cho: <b id="assign_exam_title" class="text-primary"></b></div>
                    <p class="text-muted small">Tích chọn các câu hỏi từ ngân hàng dưới đây để đưa vào đề thi này:</p>
                    <div id="questions_checkbox_list" class="border rounded p-3" style="max-height: 380px; overflow-y: auto;">
                        <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> Đang tải ngân hàng câu hỏi...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Lưu Bộ Câu Hỏi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORM ẨN XÓA ĐỀ THI -->
<form id="formDeleteExam" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="exam_id" id="delete_exam_id">
</form>

<script>
function openEditExamModal(ex) {
    document.getElementById('edit_exam_id').value = ex.id;
    document.getElementById('edit_exam_title').value = ex.title || '';
    document.getElementById('edit_exam_course').value = ex.course_id;
    document.getElementById('edit_exam_duration').value = ex.duration_minutes || 45;
    document.getElementById('edit_exam_pass').value = ex.pass_score || 70;
    document.getElementById('edit_exam_desc').value = ex.description || '';
    document.getElementById('edit_exam_active').checked = (parseInt(ex.is_active) === 1);
    new bootstrap.Modal(document.getElementById('modalEditExam')).show();
}

function openAssignQuestionsModal(examId, courseId, title) {
    document.getElementById('assign_exam_id').value = examId;
    document.getElementById('assign_exam_title').innerText = title;
    var container = document.getElementById('questions_checkbox_list');
    container.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> Đang nạp câu hỏi...</div>';

    // Fetch questions via AJAX
    fetch('api_get_questions.php?exam_id=' + examId + '&course_id=' + courseId)
        .then(res => res.json())
        .then(data => {
            if (!data.questions || data.questions.length === 0) {
                container.innerHTML = '<div class="text-center py-3 text-muted">Chưa có câu hỏi nào trong ngân hàng của khóa này. Vui lòng vào <a href="ngan_hang_cauhoi.php">Ngân hàng Câu hỏi</a> để soạn thêm.</div>';
                return;
            }
            var html = '';
            data.questions.forEach((q, idx) => {
                var checked = q.is_selected ? 'checked' : '';
                html += '<div class="form-check p-2 border-bottom">';
                html += '  <input class="form-check-input" type="checkbox" name="question_ids[]" value="' + q.id + '" id="qchk_' + q.id + '" ' + checked + '>';
                html += '  <label class="form-check-label ms-1" for="qchk_' + q.id + '">';
                html += '    <b>#' + q.id + ':</b> ' + q.question_text + ' <span class="badge bg-light text-dark border">Đ/a: ' + q.correct_answer + '</span>';
                html += '  </label>';
                html += '</div>';
            });
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="text-danger py-3">Lỗi tải danh sách câu hỏi.</div>';
        });

    new bootstrap.Modal(document.getElementById('modalAssignQuestions')).show();
}

function confirmDeleteExam(id) {
    if (confirm('Bạn có chắc muốn xóa đề thi #' + id + '? Toàn bộ kết quả thi liên quan cũng sẽ bị xóa!')) {
        document.getElementById('delete_exam_id').value = id;
        document.getElementById('formDeleteExam').submit();
    }
}
</script>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

