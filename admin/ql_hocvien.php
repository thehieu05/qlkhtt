<?php
require_once '../config.php';
require_once 'db_helper.php';
init_admin_database($conn);

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Quản lý Học viên & Đăng ký';
$active_nav = 'hocvien';

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ghi danh học viên vào khóa học
    if ($action === 'enroll') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        if ($user_id > 0 && $course_id > 0) {
            $chk = $conn->query("SELECT id FROM enrollments WHERE user_id = $user_id AND course_id = $course_id");
            if ($chk && $chk->num_rows > 0) {
                $_SESSION['flash_msg'] = "Học viên này đã được ghi danh vào khóa học này từ trước!";
                $_SESSION['flash_type'] = "warning";
            } else {
                $conn->query("INSERT INTO enrollments (user_id, course_id, progress_percent, status) VALUES ($user_id, $course_id, 0, 'active')");
                $_SESSION['flash_msg'] = "Đã ghi danh học viên thành công vào khóa học!";
                $_SESSION['flash_type'] = "success";
            }
        }
        header("Location: ql_hocvien.php");
        exit();
    }

    // Cập nhật tiến độ & trạng thái
    if ($action === 'update_progress') {
        $enroll_id = (int)($_POST['enroll_id'] ?? 0);
        $progress = (int)($_POST['progress_percent'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        if ($enroll_id > 0) {
            $s_st = $conn->real_escape_string($status);
            $conn->query("UPDATE enrollments SET progress_percent = $progress, status = '$s_st' WHERE id = $enroll_id");
            $_SESSION['flash_msg'] = "Đã cập nhật tiến độ học tập thành công!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_hocvien.php");
        exit();
    }

    // Hủy đăng ký học viên khỏi khóa
    if ($action === 'unenroll') {
        $enroll_id = (int)($_POST['enroll_id'] ?? 0);
        if ($enroll_id > 0) {
            $conn->query("DELETE FROM enrollments WHERE id = $enroll_id");
            $_SESSION['flash_msg'] = "Đã hủy quyền truy cập khóa học của học viên!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_hocvien.php");
        exit();
    }
}

// Bộ lọc & Tìm kiếm
$q = trim($_GET['q'] ?? '');
$course_filter = (int)($_GET['course_id'] ?? 0);

$where = "WHERE u.role = 0";
if (!empty($q)) {
    $sq = $conn->real_escape_string($q);
    $where .= " AND (u.fullname LIKE '%$sq%' OR u.username LIKE '%$sq%')";
}

// Lấy danh sách học viên
$students_query = "SELECT u.*, 
    (SELECT COUNT(*) FROM enrollments WHERE user_id = u.id) as course_count,
    (SELECT AVG(progress_percent) FROM enrollments WHERE user_id = u.id) as avg_progress
    FROM users u $where ORDER BY u.id DESC";
$students_res = $conn->query($students_query);

$students = [];
if ($students_res) {
    while ($r = $students_res->fetch_assoc()) $students[] = $r;
}

// Lấy danh sách khóa học cho dropdown ghi danh & lọc
$courses_list = [];
$c_res = $conn->query("SELECT id, title FROM courses ORDER BY id DESC");
if ($c_res) {
    while ($r = $c_res->fetch_assoc()) $courses_list[] = $r;
}

// Lấy toàn bộ danh sách enrollments nếu đang lọc theo khóa học
$enrollments_list = [];
$en_query = "SELECT e.*, u.fullname, u.username, c.title as course_title 
    FROM enrollments e 
    JOIN users u ON u.id = e.user_id 
    JOIN courses c ON c.id = e.course_id ";
if ($course_filter > 0) {
    $en_query .= " WHERE e.course_id = $course_filter ";
}
$en_query .= " ORDER BY e.id DESC";
$en_res = $conn->query($en_query);
if ($en_res) {
    while ($r = $en_res->fetch_assoc()) $enrollments_list[] = $r;
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Học trực tuyến & Học viên (US-05)</h4>
        <p>Theo dõi danh sách học viên, tiến độ hoàn thành các khóa học, cấp quyền và quản lý tài khoản</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEnroll">
            <i class="bi bi-person-plus-fill me-1"></i> Ghi Danh Học Viên
        </button>
    </div>
</div>

<!-- NAV TABS: HỌC VIÊN VS TIẾN ĐỘ KHÓA HỌC -->
<ul class="nav nav-pills mb-3" id="studentTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active fw-semibold" id="tab-students-btn" data-bs-toggle="pill" data-bs-target="#tab-students" type="button">
            <i class="bi bi-people-fill me-1"></i> Danh Sách Học Viên (<?= count($students) ?>)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" id="tab-enrollments-btn" data-bs-toggle="pill" data-bs-target="#tab-enrollments" type="button">
            <i class="bi bi-journal-check me-1"></i> Tiến Độ Khóa Học (<?= count($enrollments_list) ?>)
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- TAB 1: DANH SÁCH HỌC VIÊN -->
    <div class="tab-pane fade show active" id="tab-students">
        <!-- TÌM KIẾM HỌC VIÊN -->
        <div class="table-card mb-4 p-3">
            <form method="GET" class="row g-2">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Tìm theo tên học viên hoặc tên đăng nhập..." value="<?= htmlspecialchars($q) ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Tìm</button>
                    <?php if (!empty($q)): ?>
                        <a href="ql_hocvien.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-people me-2 text-primary"></i>Danh sách Tài khoản Học viên</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Học viên</th>
                            <th>Tên đăng nhập</th>
                            <th>Số khóa học đã ghi danh</th>
                            <th>Tiến độ trung bình</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Không tìm thấy học viên nào.</td></tr>
                        <?php else: foreach ($students as $st): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $st['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-weight: 700;">
                                            <?= mb_strtoupper(mb_substr($st['fullname'] ?? 'H', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($st['fullname']) ?></div>
                                            <small class="text-muted">ID: #<?= $st['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= htmlspecialchars($st['username']) ?></code></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fs-6 px-2.5 py-1">
                                        <i class="bi bi-journal-bookmark me-1"></i><?= (int)$st['course_count'] ?> khóa
                                    </span>
                                </td>
                                <td>
                                    <?php $prog = round((float)($st['avg_progress'] ?? 0)); ?>
                                    <div class="d-flex align-items-center gap-2" style="width: 140px;">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?= $prog ?>%"></div>
                                        </div>
                                        <span class="fw-semibold small text-muted"><?= $prog ?>%</span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="quickEnroll(<?= $st['id'] ?>, '<?= htmlspecialchars(addslashes($st['fullname'])) ?>')">
                                        <i class="bi bi-plus-circle me-1"></i> Ghi danh
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: TIẾN ĐỘ & GHI DANH KHÓA HỌC -->
    <div class="tab-pane fade" id="tab-enrollments">
        <div class="table-card mb-4 p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label mb-0 fw-bold">Lọc theo khóa học:</label>
                </div>
                <div class="col-md-7">
                    <select name="course_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Tất cả các khóa học --</option>
                        <?php foreach ($courses_list as $co): ?>
                            <option value="<?= $co['id'] ?>" <?= $co['id'] == $course_filter ? 'selected' : '' ?>>
                                <?= htmlspecialchars($co['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <?php if ($course_filter > 0): ?>
                        <a href="ql_hocvien.php" class="btn btn-outline-secondary w-100">Bỏ lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-mortarboard me-2 text-primary"></i>Danh sách Học viên tham gia khóa học</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Học viên</th>
                            <th>Khóa học</th>
                            <th>Ngày ghi danh</th>
                            <th>Tiến độ học tập</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Cập nhật</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrollments_list)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Chưa có lượt ghi danh nào.</td></tr>
                        <?php else: foreach ($enrollments_list as $en): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($en['fullname']) ?></div>
                                    <small class="text-muted">@<?= htmlspecialchars($en['username']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-primary"><?= htmlspecialchars($en['course_title']) ?></div>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($en['enrolled_at']) ?></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2" style="width: 140px;">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?= (int)$en['progress_percent'] ?>%"></div>
                                        </div>
                                        <span class="fw-semibold small text-muted"><?= (int)$en['progress_percent'] ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($en['status'] === 'completed'): ?>
                                        <span class="badge bg-success-subtle text-success">Đã hoàn thành</span>
                                    <?php elseif ($en['status'] === 'cancelled'): ?>
                                        <span class="badge bg-danger-subtle text-danger">Tạm khóa</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary">Đang học</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick='openUpdateProgressModal(<?= json_encode($en) ?>)'>
                                        <i class="bi bi-pencil-square"></i> Cập nhật
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="confirmUnenroll(<?= $en['id'] ?>, '<?= htmlspecialchars(addslashes($en['fullname'])) ?>')">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL GHI DANH HỌC VIÊN -->
<div class="modal fade" id="modalEnroll" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="enroll">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Ghi Danh Học Viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Chọn học viên <span class="text-danger">*</span></label>
                        <select name="user_id" id="modal_enroll_user_id" class="form-select" required>
                            <option value="">-- Chọn học viên --</option>
                            <?php foreach ($students as $st): ?>
                                <option value="<?= $st['id'] ?>">
                                    <?= htmlspecialchars($st['fullname']) ?> (@<?= htmlspecialchars($st['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chọn khóa học <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select" required>
                            <option value="">-- Chọn khóa học --</option>
                            <?php foreach ($courses_list as $co): ?>
                                <option value="<?= $co['id'] ?>">#<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Xác nhận Ghi danh</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CẬP NHẬT TIẾN ĐỘ -->
<div class="modal fade" id="modalUpdateProgress" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_progress">
                <input type="hidden" name="enroll_id" id="up_enroll_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil text-primary me-2"></i>Cập nhật Tiến độ Học viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">Học viên: <b id="up_fullname" class="text-dark"></b></div>
                    <div class="mb-3">Khóa học: <b id="up_course" class="text-primary"></b></div>
                    <div class="mb-3">
                        <label class="form-label">Tiến độ hoàn thành (%)</label>
                        <input type="number" name="progress_percent" id="up_progress" class="form-control" min="0" max="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái khóa học</label>
                        <select name="status" id="up_status" class="form-select">
                            <option value="active">Đang học (Active)</option>
                            <option value="completed">Đã hoàn thành khóa học</option>
                            <option value="cancelled">Tạm khóa truy cập</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Tiến Độ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORM ẨN XÓA ENROLL -->
<form id="formUnenroll" method="POST" style="display:none;">
    <input type="hidden" name="action" value="unenroll">
    <input type="hidden" name="enroll_id" id="unenroll_id">
</form>

<script>
function quickEnroll(userId, name) {
    document.getElementById('modal_enroll_user_id').value = userId;
    new bootstrap.Modal(document.getElementById('modalEnroll')).show();
}

function openUpdateProgressModal(en) {
    document.getElementById('up_enroll_id').value = en.id;
    document.getElementById('up_fullname').innerText = en.fullname;
    document.getElementById('up_course').innerText = en.course_title;
    document.getElementById('up_progress').value = en.progress_percent || 0;
    document.getElementById('up_status').value = en.status || 'active';
    new bootstrap.Modal(document.getElementById('modalUpdateProgress')).show();
}

function confirmUnenroll(enId, name) {
    if (confirm('Bạn có chắc chắn muốn hủy ghi danh học viên "' + name + '" khỏi khóa học này?')) {
        document.getElementById('unenroll_id').value = enId;
        document.getElementById('formUnenroll').submit();
    }
}
</script>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

