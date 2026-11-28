<?php
require_once __DIR__ . '/../config.php';

require_admin('../login.php');

$page_title = 'Quản lý Học viên & Tiến độ';
$active_nav = 'hocvien';

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Khóa / Kích hoạt tài khoản học viên
    if ($action === 'toggle_status') {
        $u_id = (int)($_POST['user_id'] ?? 0);
        $new_st = ($_POST['new_status'] ?? 'active') === 'locked' ? 'locked' : 'active';
        if ($u_id > 0) {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'student'");
            $stmt->bind_param('si', $new_st, $u_id);
            $stmt->execute();
            $stmt->close();
            set_flash($new_st === 'locked' ? 'Đã khóa tài khoản học viên!' : 'Đã kích hoạt lại tài khoản học viên!', 'success');
        }
        header("Location: ql_hocvien.php");
        exit();
    }

    // Ghi danh thủ công cho học viên vào khóa học
    if ($action === 'enroll') {
        $user_id   = (int)($_POST['user_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        if ($user_id > 0 && $course_id > 0) {
            $stmt = $conn->prepare("INSERT INTO enrollments (user_id, course_id, progress_percent, status)
                                    VALUES (?, ?, 0, 'active')
                                    ON DUPLICATE KEY UPDATE status = 'active'");
            $stmt->bind_param('ii', $user_id, $course_id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã cấp quyền học viên vào khóa học thành công!", "success");
        }
        header("Location: ql_hocvien.php?tab=enrollments");
        exit();
    }

    // Cập nhật tiến độ học viên
    if ($action === 'update_progress') {
        $enroll_id = (int)($_POST['enroll_id'] ?? 0);
        $progress  = (int)($_POST['progress_percent'] ?? 0);
        $status    = trim($_POST['status'] ?? 'active');
        if ($enroll_id > 0) {
            $stmt = $conn->prepare("UPDATE enrollments SET progress_percent = ?, status = ? WHERE id = ?");
            $stmt->bind_param('isi', $progress, $status, $enroll_id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã cập nhật tiến độ học viên!", "success");
        }
        header("Location: ql_hocvien.php?tab=enrollments");
        exit();
    }

    // Hủy quyền học (unenroll)
    if ($action === 'unenroll') {
        $enroll_id = (int)($_POST['enroll_id'] ?? 0);
        if ($enroll_id > 0) {
            $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
            $stmt->bind_param('i', $enroll_id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã hủy quyền truy cập khóa học!", "warning");
        }
        header("Location: ql_hocvien.php?tab=enrollments");
        exit();
    }
}

$tab = $_GET['tab'] ?? 'students';
$q = trim($_GET['q'] ?? '');
$course_filter = (int)($_GET['course_id'] ?? 0);

// Danh sách học viên
$where_u = "WHERE u.role = 'student'";
$params_u = [];
$types_u = '';
if ($q !== '') {
    $like = "%$q%";
    $where_u .= " AND (u.fullname LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $types_u .= 'sss';
    $params_u = [$like, $like, $like];
}

$students_query = "SELECT u.*,
    (SELECT COUNT(*) FROM enrollments WHERE user_id = u.id AND status = 'active') as course_count,
    (SELECT AVG(progress_percent) FROM enrollments WHERE user_id = u.id AND status = 'active') as avg_progress,
    (SELECT AVG(score) FROM quiz_attempts WHERE user_id = u.id) as avg_quiz_score
    FROM users u $where_u ORDER BY u.id DESC";

$stmt_st = $conn->prepare($students_query);
if ($types_u !== '') {
    $stmt_st->bind_param($types_u, ...$params_u);
}
$stmt_st->execute();
$st_res = $stmt_st->get_result();
$students = [];
while ($r = $st_res->fetch_assoc()) $students[] = $r;
$stmt_st->close();

// Danh sách khóa học cho dropdown
$courses_list = [];
$c_res = $conn->query("SELECT id, title FROM courses ORDER BY id DESC");
if ($c_res) {
    while ($r = $c_res->fetch_assoc()) $courses_list[] = $r;
}

// Danh sách ghi danh (enrollments) & tiến độ
$enrollments_list = [];
$where_en = "";
if ($course_filter > 0) {
    $where_en = "WHERE e.course_id = $course_filter";
}
$en_query = "SELECT e.*, u.fullname, u.username, u.email, c.title as course_title,
             (SELECT COUNT(*) FROM lesson_progress WHERE user_id = e.user_id AND course_id = e.course_id AND completed = 1) AS completed_lessons,
             (SELECT COUNT(*) FROM lessons WHERE course_id = e.course_id) AS total_lessons
             FROM enrollments e
             JOIN users u ON u.id = e.user_id
             JOIN courses c ON c.id = e.course_id
             $where_en
             ORDER BY e.id DESC";
$en_res = $conn->query($en_query);
if ($en_res) {
    while ($r = $en_res->fetch_assoc()) $enrollments_list[] = $r;
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Học viên, Tiến độ & Điểm số</h4>
        <p>Quản trị tài khoản học viên, khóa/mở trạng thái, theo dõi tiến độ hoàn thành bài học và kết quả kiểm tra</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEnroll">
            <i class="bi bi-person-plus-fill me-1"></i> Ghi Danh Học Viên
        </button>
    </div>
</div>

<!-- NAV TABS -->
<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'students' ? 'active' : '' ?>" href="ql_hocvien.php?tab=students">
            <i class="bi bi-people-fill me-1"></i> Danh Sách Học Viên (<?= count($students) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'enrollments' ? 'active' : '' ?>" href="ql_hocvien.php?tab=enrollments">
            <i class="bi bi-journal-check me-1"></i> Tiến Độ Khóa Học (<?= count($enrollments_list) ?>)
        </a>
    </li>
</ul>

<?php if ($tab === 'students'): ?>
    <div class="table-card mb-4 p-3">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="students">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Tìm theo họ tên, username hoặc email..." value="<?= e($q) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
                <?php if ($q !== ''): ?>
                    <a href="ql_hocvien.php?tab=students" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Học viên</th>
                        <th>Email / Điện thoại</th>
                        <th>Khóa học đã cấp</th>
                        <th>Tiến độ TB</th>
                        <th>Điểm thi TB</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Khóa / Mở</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Không tìm thấy học viên nào.</td></tr>
                    <?php else: foreach ($students as $st): ?>
                        <tr>
                            <td class="text-muted small">#<?= (int)$st['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($st['fullname']) ?></div>
                                <div class="small text-muted font-monospace">@<?= e($st['username']) ?></div>
                            </td>
                            <td class="small">
                                <div><i class="bi bi-envelope me-1 text-muted"></i><?= e($st['email']) ?></div>
                                <div><i class="bi bi-phone me-1 text-muted"></i><?= e($st['phone'] ?: 'Chưa cập nhật') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary"><?= (int)$st['course_count'] ?> khóa</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 70px;">
                                        <div class="progress-bar bg-success" style="width: <?= (int)($st['avg_progress'] ?? 0) ?>%;"></div>
                                    </div>
                                    <span class="small fw-semibold"><?= round((float)($st['avg_progress'] ?? 0)) ?>%</span>
                                </div>
                            </td>
                            <td>
                                <?php if ($st['avg_quiz_score'] !== null): ?>
                                    <span class="fw-bold text-primary"><?= round((float)$st['avg_quiz_score'], 1) ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted small">Chưa làm bài</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($st['status'] === 'active'): ?>
                                    <span class="badge-active">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2">Đang bị khóa</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" class="d-inline" onsubmit="return confirm('<?= $st['status'] === 'active' ? 'Khóa tài khoản học viên này?' : 'Mở khóa tài khoản học viên này?' ?>');">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= (int)$st['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $st['status'] === 'active' ? 'locked' : 'active' ?>">
                                    <button type="submit" class="btn btn-sm <?= $st['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                        <i class="bi <?= $st['status'] === 'active' ? 'bi-lock' : 'bi-unlock' ?>"></i>
                                        <?= $st['status'] === 'active' ? 'Khóa' : 'Mở khóa' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'enrollments'): ?>
    <div class="table-card mb-4 p-3">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="enrollments">
            <div class="col-md-8">
                <select name="course_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">-- Tất cả khóa học --</option>
                    <?php foreach ($courses_list as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $course_filter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <span class="text-muted small">Lọc danh sách học viên theo từng khóa học</span>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Học viên</th>
                        <th>Khóa học</th>
                        <th>Tiến độ bài học</th>
                        <th>Trạng thái quyền</th>
                        <th>Ngày cấp</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enrollments_list)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Chưa có bản ghi ghi danh nào.</td></tr>
                    <?php else: foreach ($enrollments_list as $en): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($en['fullname']) ?></div>
                                <div class="small text-muted">@<?= e($en['username']) ?> · <?= e($en['email']) ?></div>
                            </td>
                            <td class="fw-semibold text-dark"><?= e($en['course_title']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 80px;">
                                        <div class="progress-bar bg-primary" style="width: <?= (int)$en['progress_percent'] ?>%;"></div>
                                    </div>
                                    <span class="small fw-bold"><?= (int)$en['progress_percent'] ?>%</span>
                                    <span class="small text-muted">(<?= (int)$en['completed_lessons'] ?>/<?= (int)$en['total_lessons'] ?> bài)</span>
                                </div>
                            </td>
                            <td>
                                <?php if ($en['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success">Đã cấp quyền</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Đã thu hồi</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($en['enrolled_at'])) ?></td>
                            <td class="text-end">
                                <form method="POST" class="d-inline" onsubmit="return confirm('Hủy quyền học của học viên này trong khóa học?');">
                                    <input type="hidden" name="action" value="unenroll">
                                    <input type="hidden" name="enroll_id" value="<?= (int)$en['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hủy quyền</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- MODAL GHI DANH HỌC VIÊN -->
<div class="modal fade" id="modalEnroll" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="enroll">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Ghi Danh Học Viên vào Khóa Học</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Chọn học viên *</label>
                    <select name="user_id" class="form-select" required>
                        <?php foreach ($students as $st): ?>
                            <option value="<?= (int)$st['id'] ?>"><?= e($st['fullname']) ?> (@<?= e($st['username']) ?> - <?= e($st['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Chọn khóa học *</label>
                    <select name="course_id" class="form-select" required>
                        <?php foreach ($courses_list as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Xác nhận Ghi danh</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
