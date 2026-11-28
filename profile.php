<?php
require_once 'config.php';

require_login('login.php');

$user_id = current_user_id();
$stmt_u = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt_u->bind_param('i', $user_id);
$stmt_u->execute();
$current_user = $stmt_u->get_result()->fetch_assoc();
$stmt_u->close();

if (!$current_user || $current_user['status'] !== 'active') {
    session_destroy();
    header("Location: login.php");
    exit();
}

// XỬ LÝ POST: CẬP NHẬT THÔNG TIN / ĐỔI MẬT KHẨU
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $new_fullname = trim($_POST['fullname'] ?? '');
        $new_phone    = trim($_POST['phone'] ?? '');

        if ($new_fullname !== '') {
            $stmt_upd = $conn->prepare("UPDATE users SET fullname = ?, phone = ? WHERE id = ?");
            $stmt_upd->bind_param('ssi', $new_fullname, $new_phone, $user_id);
            $stmt_upd->execute();
            $stmt_upd->close();
            $_SESSION['fullname'] = $new_fullname;
            set_flash('Lưu thay đổi thông tin cá nhân thành công!', 'success');
        } else {
            set_flash('Vui lòng nhập họ và tên!', 'danger');
        }
        header("Location: profile.php?tab=caidat");
        exit();
    }

    if ($action === 'change_password') {
        $curr_pass    = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        $db_pass = $current_user['password'] ?? '';
        if (!password_verify($curr_pass, $db_pass)) {
            set_flash('Mật khẩu hiện tại không chính xác! Vui lòng thử lại.', 'danger');
        } elseif (strlen($new_pass) < 6) {
            set_flash('Mật khẩu mới phải có ít nhất 6 ký tự!', 'danger');
        } elseif ($new_pass !== $confirm_pass) {
            set_flash('Nhập lại mật khẩu mới không trùng khớp!', 'danger');
        } else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt_pw = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_pw->bind_param('si', $hashed, $user_id);
            $stmt_pw->execute();
            $stmt_pw->close();
            set_flash('Đổi mật khẩu thành công!', 'success');
        }
        header("Location: profile.php?tab=caidat");
        exit();
    }
}

$username = htmlspecialchars($current_user['username'] ?? '');
$fullname = htmlspecialchars($current_user['fullname'] ?? '');
$role     = $current_user['role'] ?? 'student';
$phone    = htmlspecialchars($current_user['phone'] ?? '');
$email    = htmlspecialchars($current_user['email'] ?? '');

$active_tab = $_GET['tab'] ?? 'khoahoc';

// Lấy danh sách khóa học đã được cấp quyền học (chỉ enrollments active)
$user_courses = [];
$stmt_ec = $conn->prepare("SELECT c.*, COALESCE(e.progress_percent, 0) AS progress_percent, e.enrolled_at, e.status AS enroll_status
                           FROM enrollments e
                           JOIN courses c ON c.id = e.course_id
                           WHERE e.user_id = ? AND e.status = 'active'
                           ORDER BY e.id DESC");
$stmt_ec->bind_param('i', $user_id);
$stmt_ec->execute();
$res_ec = $stmt_ec->get_result();
while ($row = $res_ec->fetch_assoc()) {
    $user_courses[] = $row;
}
$stmt_ec->close();

// Lấy danh sách đơn hàng mô phỏng của học viên
$orders_list = [];
$stmt_ord = $conn->prepare("SELECT o.*,
                            GROUP_CONCAT(c.title SEPARATOR ', ') AS course_titles
                            FROM orders o
                            LEFT JOIN order_items oi ON oi.order_id = o.id
                            LEFT JOIN courses c ON c.id = oi.course_id
                            WHERE o.user_id = ?
                            GROUP BY o.id
                            ORDER BY o.id DESC");
$stmt_ord->bind_param('i', $user_id);
$stmt_ord->execute();
$res_ord = $stmt_ord->get_result();
while ($orow = $res_ord->fetch_assoc()) {
    $orders_list[] = $orow;
}
$stmt_ord->close();

// Lấy lịch sử làm bài trắc nghiệm
$quiz_attempts = [];
$stmt_qa = $conn->prepare("SELECT qa.*, q.title AS quiz_title, q.pass_score, c.title AS course_title, c.id AS course_id
                           FROM quiz_attempts qa
                           JOIN quizzes q ON q.id = qa.quiz_id
                           JOIN courses c ON c.id = q.course_id
                           WHERE qa.user_id = ?
                           ORDER BY qa.id DESC");
$stmt_qa->bind_param('i', $user_id);
$stmt_qa->execute();
$res_qa = $stmt_qa->get_result();
while ($qarow = $res_qa->fetch_assoc()) {
    $quiz_attempts[] = $qarow;
}
$stmt_qa->close();

require_once 'includes/header.php';
require_once 'includes/menu.php';
?>

<div class="user-profile-page py-4">
    <div class="container">
        <!-- FLASH MESSAGE -->
        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4 rounded-3 shadow-sm" role="alert">
                <i class="bi <?= ($_SESSION['flash_type'] ?? '') === 'danger' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-check-circle-fill text-success' ?> fs-5 flex-shrink-0"></i>
                <div class="fw-medium"><?= $_SESSION['flash_msg'] ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_msg'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <!-- TOP PROFILE HEADER -->
        <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
            <div>
                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.85rem; letter-spacing: -0.5px;">
                    Xin chào, <span class="text-dark"><?= $username ?></span>
                </h2>
                <div class="text-muted small" style="font-size: 0.88rem;"><?= htmlspecialchars($email) ?></div>
            </div>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill fw-semibold d-inline-flex align-items-center gap-1">
                <i class="bi bi-box-arrow-right"></i> Thoát
            </a>
        </div>

        <!-- 4 METRIC STAT CARDS -->
        <div class="row g-3 mb-4">
            <!-- 1. Khóa học -->
            <div class="col-6 col-md-3">
                <a href="profile.php?tab=khoahoc" class="text-decoration-none">
                    <div class="profile-stat-box <?= $active_tab === 'khoahoc' ? 'active' : '' ?>">
                        <div class="profile-stat-icon-wrap icon-gold">
                            <i class="bi bi-book"></i>
                        </div>
                        <div>
                            <div class="stat-label">Khóa học</div>
                            <div class="stat-value"><?= count($user_courses) ?></div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- 2. Công cụ -->
            <div class="col-6 col-md-3">
                <a href="profile.php?tab=congcu" class="text-decoration-none">
                    <div class="profile-stat-box <?= $active_tab === 'congcu' ? 'active' : '' ?>">
                        <div class="profile-stat-icon-wrap icon-gold">
                            <i class="bi bi-tools"></i>
                        </div>
                        <div>
                            <div class="stat-label">Công cụ</div>
                            <div class="stat-value">0</div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- 3. Đã chi -->
            <div class="col-6 col-md-3">
                <a href="profile.php?tab=donhang" class="text-decoration-none">
                    <div class="profile-stat-box <?= $active_tab === 'donhang' ? 'active' : '' ?>">
                        <div class="profile-stat-icon-wrap icon-gold">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div>
                            <div class="stat-label">Đã chi</div>
                            <div class="stat-value">0</div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- 4. Hội viên -->
            <div class="col-6 col-md-3">
                <a href="index.php#goi-hoi-vien" class="text-decoration-none">
                    <div class="profile-stat-box">
                        <div class="profile-stat-icon-wrap icon-gold">
                            <i class="bi bi-award"></i>
                        </div>
                        <div>
                            <div class="stat-label">Hội viên</div>
                            <div class="stat-value stat-text-badge text-warning-emphasis fw-bold" style="font-size: 0.92rem;">
                                <?= $role === 'admin' ? 'Quản trị viên' : 'Học viên' ?>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- HORIZONTAL NAV TABS GIỐNG HỆT ẢNH MẪU (8 TABS ĐẦY ĐỦ) -->
        <div class="profile-nav-tabs-container mb-4">
            <ul class="nav profile-nav-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'khoahoc' ? 'active' : '' ?>" href="profile.php?tab=khoahoc">
                        <i class="bi bi-collection-play me-1.5"></i> Khóa học của tôi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'donhang' ? 'active' : '' ?>" href="profile.php?tab=donhang">
                        <i class="bi bi-receipt me-1.5"></i> Đơn hàng
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'ketqua' ? 'active' : '' ?>" href="profile.php?tab=ketqua">
                        <i class="bi bi-clipboard2-check me-1.5"></i> Lịch sử làm kiểm tra
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'caidat' ? 'active' : '' ?>" href="profile.php?tab=caidat">
                        <i class="bi bi-gear me-1.5"></i> Thông tin cá nhân
                    </a>
                </li>
            </ul>
        </div>

        <!-- ========================================== -->
        <!-- TAB CONTENT 1: CÀI ĐẶT (GIỐNG ẢNH MẪU 100%) -->
        <!-- ========================================== -->
        <?php if ($active_tab === 'caidat'): ?>
            <!-- CARD 1: THÔNG TIN CÁ NHÂN -->
            <div class="settings-card">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="settings-badge-icon">
                        <i class="bi bi-person"></i>
                    </div>
                    <div>
                        <div class="settings-card-title">Thông tin cá nhân</div>
                        <div class="settings-card-subtitle">Cập nhật tên hiển thị và số điện thoại hỗ trợ đơn hàng.</div>
                    </div>
                </div>

                <form method="POST" action="profile.php?tab=caidat">
                    <input type="hidden" name="action" value="update_profile">

                    <!-- Email đăng nhập (readonly) -->
                    <div class="mb-3">
                        <label class="settings-label">Email đăng nhập</label>
                        <input type="text" class="form-control settings-readonly-input" value="<?= htmlspecialchars($email) ?>" readonly>
                        <div class="settings-verified-badge">
                            <i class="bi bi-check-circle"></i> Email đã xác nhận và không thể thay đổi
                        </div>
                    </div>

                    <!-- Tên hiển thị & Số điện thoại / Zalo -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="settings-label">Tên hiển thị</label>
                            <input type="text" name="fullname" class="form-control settings-input" value="<?= htmlspecialchars($fullname) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="settings-label">Số điện thoại / Zalo</label>
                            <input type="text" name="phone" class="form-control settings-input" placeholder="Ví dụ: 0583953426" value="<?= htmlspecialchars($phone) ?>">
                        </div>
                    </div>

                    <!-- Footer card 1 -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-2">
                        <div class="settings-helper-text">
                            Thông tin này được dùng để hỗ trợ và xác nhận đơn hàng của bạn.
                        </div>
                        <button type="submit" class="btn btn-save-gold">
                            <i class="bi bi-floppy"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>

            <!-- CARD 2: BẢO MẬT TÀI KHOẢN -->
            <div class="settings-card">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="settings-badge-icon">
                        <i class="bi bi-key"></i>
                    </div>
                    <div>
                        <div class="settings-card-title">Bảo mật tài khoản</div>
                        <div class="settings-card-subtitle">Đổi mật khẩu định kỳ để bảo vệ khóa học và đơn hàng của bạn.</div>
                    </div>
                </div>

                <form method="POST" action="profile.php?tab=caidat">
                    <input type="hidden" name="action" value="change_password">

                    <!-- Mật khẩu hiện tại -->
                    <div class="mb-3">
                        <label class="settings-label">Mật khẩu hiện tại *</label>
                        <div class="input-group settings-input-group">
                            <input type="password" name="current_password" id="inputCurrentPass" class="form-control" required>
                            <span class="input-group-text" onclick="toggleSettingsPassword('inputCurrentPass', this)" title="Ẩn/hiện mật khẩu">
                                <i class="bi bi-eye"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Mật khẩu mới & Nhập lại mật khẩu mới -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="settings-label">Mật khẩu mới *</label>
                            <div class="input-group settings-input-group">
                                <input type="password" name="new_password" id="inputNewPass" class="form-control" required>
                                <span class="input-group-text" onclick="toggleSettingsPassword('inputNewPass', this)" title="Ẩn/hiện mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="settings-label">Nhập lại mật khẩu mới *</label>
                            <div class="input-group settings-input-group">
                                <input type="password" name="confirm_password" id="inputConfirmPass" class="form-control" required>
                                <span class="input-group-text" onclick="toggleSettingsPassword('inputConfirmPass', this)" title="Ẩn/hiện mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer card 2 -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-2">
                        <a href="javascript:void(0)" onclick="alert('Vui lòng liên hệ Hotline/Zalo: 0773 308 885 để được cấp lại mật khẩu ngay!');" class="settings-forgot-link">
                            <i class="bi bi-question-circle"></i> Quên mật khẩu?
                        </a>
                        <button type="submit" class="btn btn-save-gold">
                            <i class="bi bi-floppy"></i> Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>

        <!-- ========================================== -->
        <!-- TAB CONTENT 2: ĐƠN HÀNG (MẶC ĐỊNH) -->
        <!-- ========================================== -->
        <?php elseif ($active_tab === 'donhang'): ?>
            <div class="mb-3 d-flex justify-content-between align-items-end">
                <div>
                    <span class="text-uppercase fw-bold text-warning-emphasis small" style="font-size: 0.72rem; letter-spacing: 1px;">LỊCH SỬ MUA HÀNG</span>
                    <h4 class="fw-bold text-dark mb-0" style="font-size: 1.4rem;">Đơn hàng đã đặt</h4>
                </div>
                <span class="badge bg-light text-secondary border px-3 py-1.5 rounded-pill" style="font-size: 0.78rem;">Trang 1/1</span>
            </div>

            <!-- BẢNG ĐƠN HÀNG THIẾT KẾ Y NHƯ ẢNH -->
            <div class="order-table-card">
                <div class="table-responsive">
                    <table class="table order-custom-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 16%;">MÃ ĐƠN</th>
                                <th scope="col" style="width: 24%;">KHÓA HỌC</th>
                                <th scope="col" style="width: 15%;">NGÀY ĐẶT</th>
                                <th scope="col" style="width: 18%;">TRẠNG THÁI</th>
                                <th scope="col" style="width: 13%;">TỔNG TIỀN</th>
                                <th scope="col" class="text-end" style="width: 14%;">THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders_list)):
                                foreach ($orders_list as $ord):
                                    $st = $ord['status'] ?? 'pending';
                            ?>
                            <tr>
                                <td class="fw-bold text-dark font-monospace" style="font-size: 0.85rem;">#<?= e($ord['order_code']) ?></td>
                                <td class="small text-dark fw-medium"><?= e($ord['course_titles'] ?: 'Khóa học') ?></td>
                                <td class="text-secondary small"><?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?></td>
                                <td>
                                    <?php if ($st === 'confirmed'): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i>Đã xác nhận
                                        </span>
                                    <?php elseif ($st === 'cancelled'): ?>
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1">
                                            <i class="bi bi-x-circle-fill me-1"></i>Đã hủy
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1">
                                            <i class="bi bi-hourglass-split me-1"></i>Chờ xác nhận
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-danger"><?= number_format((float)$ord['total_amount'], 0, ',', '.') ?> ₫</span>
                                </td>
                                <td class="text-end">
                                    <?php if ($st === 'confirmed'): ?>
                                        <a href="profile.php?tab=khoahoc" class="btn btn-outline-primary btn-sm px-3 rounded-2 fw-semibold">
                                            Vào học →
                                        </a>
                                    <?php elseif ($st === 'pending'): ?>
                                        <form method="POST" action="order_action.php" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này?');">
                                            <input type="hidden" name="action" value="cancel_order">
                                            <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm px-2 rounded-2">Hủy đơn</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Bạn chưa có đơn hàng nào. <a href="danh_muc.php" class="fw-semibold">Khám phá khóa học</a>.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- ========================================== -->
        <!-- TAB CONTENT 3: KHÓA HỌC ĐÃ MUA -->
        <!-- ========================================== -->
        <?php elseif ($active_tab === 'khoahoc'): ?>
            <div class="mb-3">
                <span class="text-uppercase fw-bold text-warning-emphasis small" style="font-size: 0.72rem; letter-spacing: 1px;">KHO HỌC TẬP</span>
                <h4 class="fw-bold text-dark mb-0" style="font-size: 1.4rem;">Khóa học của bạn</h4>
            </div>
            <div class="row g-3">
                <?php if (!empty($user_courses)): ?>
                    <?php foreach ($user_courses as $c): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border rounded-3 overflow-hidden shadow-sm d-flex flex-column">
                                <a href="hoc.php?course_id=<?= $c['id'] ?>" class="d-block position-relative overflow-hidden">
                                    <img src="<?= htmlspecialchars($c['image_url'] ?? 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600') ?>" class="card-img-top" style="height: 160px; object-fit: cover;" alt="cover">
                                    <div class="position-absolute top-0 start-0 m-2">
                                        <span class="badge bg-success shadow-sm"><i class="bi bi-check-circle-fill me-1"></i>Đã kích hoạt</span>
                                    </div>
                                </a>
                                <div class="card-body d-flex flex-column p-3">
                                    <h6 class="fw-bold mb-2">
                                        <a href="hoc.php?course_id=<?= $c['id'] ?>" class="text-dark text-decoration-none">
                                            <?= htmlspecialchars($c['title']) ?>
                                        </a>
                                    </h6>
                                    <p class="text-muted small text-truncate-2 mb-3"><?= htmlspecialchars($c['description']) ?></p>
                                    
                                    <!-- Hiển thị tiến độ học tập nếu có -->
                                    <?php $pct = (int)($c['progress_percent'] ?? 0); ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center small text-muted mb-1" style="font-size: 0.75rem;">
                                            <span>Tiến độ học tập</span>
                                            <span class="fw-bold text-primary"><?= $pct ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 5px; background-color: #e9ecef;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>

                                    <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center gap-2">
                                        <a href="chitiet_khoahoc.php?id=<?= $c['id'] ?>" class="text-secondary small text-decoration-none" title="Xem chi tiết giới thiệu">
                                            <i class="bi bi-info-circle me-1"></i>Chi tiết
                                        </a>
                                        <a href="hoc.php?course_id=<?= $c['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm">
                                            <i class="bi bi-play-circle-fill"></i> Vào học ngay
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 py-5 text-center text-muted">
                        <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
                        Bạn chưa đăng ký khóa học nào. <a href="index.php#khoa-hoc">Xem danh sách khóa học</a>
                    </div>
                <?php endif; ?>
            </div>

        <!-- ========================================== -->
        <!-- TAB CONTENT 4: LỊCH SỬ TRẮC NGHIỆM -->
        <!-- ========================================== -->
        <?php elseif ($active_tab === 'ketqua'): ?>
            <div class="mb-3">
                <span class="text-uppercase fw-bold text-warning-emphasis small" style="font-size: 0.72rem; letter-spacing: 1px;">KIỂM TRA & ĐÁNH GIÁ</span>
                <h4 class="fw-bold text-dark mb-0" style="font-size: 1.4rem;">Lịch sử làm bài trắc nghiệm</h4>
            </div>
            <div class="order-table-card">
                <div class="table-responsive">
                    <table class="table order-custom-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>BÀI KIỂM TRA</th>
                                <th>KHÓA HỌC</th>
                                <th>ĐIỂM SỐ</th>
                                <th>TRẢ LỜI ĐÚNG</th>
                                <th>KẾT QUẢ</th>
                                <th>THỜI GIAN NỘP</th>
                                <th class="text-end">CHI TIẾT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($quiz_attempts)): ?>
                                <?php foreach ($quiz_attempts as $qa): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= e($qa['quiz_title']) ?></td>
                                    <td class="small text-secondary"><?= e($qa['course_title']) ?></td>
                                    <td class="fw-bold text-primary"><?= number_format((float)$qa['score'], 1) ?>%</td>
                                    <td class="small"><?= (int)$qa['correct_count'] ?>/<?= (int)$qa['total_questions'] ?> câu</td>
                                    <td>
                                        <?php if ($qa['passed']): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3">Đạt</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Chưa đạt</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($qa['submitted_at'] ?? $qa['started_at'])) ?></td>
                                    <td class="text-end">
                                        <a href="quiz.php?quiz_id=<?= (int)$qa['quiz_id'] ?>&attempt_id=<?= (int)$qa['id'] ?>" class="btn btn-outline-primary btn-sm rounded-2">Xem lại</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        Bạn chưa thực hiện bài kiểm tra trắc nghiệm nào.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- ========================================== -->
        <!-- CÁC TAB KHÁC -->
        <!-- ========================================== -->
        <?php else: ?>
            <div class="order-table-card p-5 text-center text-muted">
                <i class="bi bi-hourglass-split fs-1 d-block mb-3 text-warning"></i>
                <h5 class="fw-bold text-dark">Tính năng đang được cập nhật</h5>
                <p class="small text-secondary mb-3">Mục này sẽ sớm hoàn thiện trong bản nâng cấp tiếp theo.</p>
                <a href="profile.php?tab=donhang" class="btn btn-outline-primary btn-sm rounded-pill px-4">Quay về Đơn hàng</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- SCRIPT ẨN HIỆN MẬT KHẨU CHO TAB CÀI ĐẶT -->
<script>
function toggleSettingsPassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>
