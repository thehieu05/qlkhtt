<?php
require_once 'config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_res = $conn->query("SELECT * FROM users WHERE id = $user_id LIMIT 1");
$current_user = ($user_res && $user_res->num_rows > 0) ? $user_res->fetch_assoc() : null;

// XỬ LÝ POST: CẬP NHẬT THÔNG TIN / ĐỔI MẬT KHẨU TRONG TAB CÀI ĐẶT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Cập nhật thông tin cá nhân
    if ($action === 'update_profile') {
        $new_fullname = trim($_POST['fullname'] ?? '');
        $new_phone    = trim($_POST['phone'] ?? '');

        if (!empty($new_fullname)) {
            $s_fn = $conn->real_escape_string($new_fullname);
            $s_ph = $conn->real_escape_string($new_phone);
            $conn->query("UPDATE users SET fullname = '$s_fn', phone = '$s_ph' WHERE id = $user_id");
            $_SESSION['fullname'] = $new_fullname;
            $_SESSION['flash_msg'] = "Lưu thay đổi thông tin cá nhân thành công!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_msg'] = "Vui lòng nhập tên hiển thị!";
            $_SESSION['flash_type'] = "danger";
        }
        header("Location: profile.php?tab=caidat");
        exit();
    }

    // 2. Đổi mật khẩu tài khoản
    if ($action === 'change_password') {
        $curr_pass    = trim($_POST['current_password'] ?? '');
        $new_pass     = trim($_POST['new_password'] ?? '');
        $confirm_pass = trim($_POST['confirm_password'] ?? '');

        $db_pass = $current_user['password'] ?? '';
        if ($curr_pass !== $db_pass) {
            $_SESSION['flash_msg'] = "Mật khẩu hiện tại không chính xác! Vui lòng thử lại.";
            $_SESSION['flash_type'] = "danger";
        } elseif (empty($new_pass) || strlen($new_pass) < 6) {
            $_SESSION['flash_msg'] = "Mật khẩu mới phải có ít nhất 6 ký tự!";
            $_SESSION['flash_type'] = "danger";
        } elseif ($new_pass !== $confirm_pass) {
            $_SESSION['flash_msg'] = "Nhập lại mật khẩu mới không trùng khớp!";
            $_SESSION['flash_type'] = "danger";
        } else {
            $s_np = $conn->real_escape_string($new_pass);
            $conn->query("UPDATE users SET password = '$s_np' WHERE id = $user_id");
            $_SESSION['flash_msg'] = "Đổi mật khẩu thành công! Hãy ghi nhớ mật khẩu mới của bạn.";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: profile.php?tab=caidat");
        exit();
    }
}

$username = htmlspecialchars($current_user['username'] ?? $_SESSION['username'] ?? $_SESSION['fullname'] ?? 'laubmg4ij');
$fullname = htmlspecialchars($current_user['fullname'] ?? $_SESSION['fullname'] ?? $username);
$role     = (int)($current_user['role'] ?? $_SESSION['role'] ?? 0);
$phone    = htmlspecialchars($current_user['phone'] ?? '');

// Định dạng email hiển thị
if (!empty($current_user['email'])) {
    $email = $current_user['email'];
} elseif (strpos($username, '@') !== false) {
    $email = $username;
} else {
    $email = $username . "@mozmail.com";
}

// Lấy tab hiện tại từ query string (?tab=caidat, ?tab=donhang,...)
$active_tab = $_GET['tab'] ?? 'donhang';

// Lấy danh sách khóa học thực tế
$courses_query = $conn->query("SELECT * FROM courses ORDER BY id DESC LIMIT 5");
$user_courses = [];
if ($courses_query && $courses_query->num_rows > 0) {
    while ($row = $courses_query->fetch_assoc()) {
        $user_courses[] = $row;
    }
}

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
                                <?= $role == 1 ? 'Giảng viên' : 'Chưa tham gia' ?>
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
                        <i class="bi bi-collection-play me-1.5"></i> Khóa học đã mua
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'yeucau' ? 'active' : '' ?>" href="profile.php?tab=yeucau">
                        <i class="bi bi-camera-video me-1.5"></i> Khóa học theo yêu cầu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'donhang' ? 'active' : '' ?>" href="profile.php?tab=donhang">
                        <i class="bi bi-receipt me-1.5"></i> Đơn hàng
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'hoivien' ? 'active' : '' ?>" href="index.php#goi-hoi-vien">
                        <i class="bi bi-award me-1.5"></i> Hội viên
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'congcu' ? 'active' : '' ?>" href="profile.php?tab=congcu">
                        <i class="bi bi-tools me-1.5"></i> Công cụ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'affiliate' ? 'active' : '' ?>" href="profile.php?tab=affiliate">
                        <i class="bi bi-share me-1.5"></i> Affiliate
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'yeuthich' ? 'active' : '' ?>" href="profile.php?tab=yeuthich">
                        <i class="bi bi-heart me-1.5"></i> Yêu thích
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'caidat' ? 'active' : '' ?>" href="profile.php?tab=caidat">
                        <i class="bi bi-gear me-1.5"></i> Cài đặt
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
                                <th scope="col" style="width: 15%;">MÃ ĐƠN</th>
                                <th scope="col" style="width: 18%;">NGÀY ĐẶT</th>
                                <th scope="col" style="width: 28%;">TRẠNG THÁI</th>
                                <th scope="col" style="width: 20%;">TỔNG TIỀN</th>
                                <th scope="col" class="text-end" style="width: 19%;">THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-bold text-dark font-monospace" style="font-size: 0.88rem;">#1806889</td>
                                <td class="text-secondary small">28/10/2026</td>
                                <td>
                                    <span class="status-pill status-pill-info">
                                        <span class="status-dot"></span> Điền thông tin thanh toán
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-danger" style="font-size: 1rem;">149.999 ₫</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-column gap-1.5">
                                        <a href="index.php#khoa-hoc" class="btn btn-outline-secondary btn-sm px-3 rounded-2 fw-semibold" style="font-size: 0.8rem;">
                                            Chi tiết →
                                        </a>
                                        <a href="index.php#goi-hoi-vien" class="btn btn-dark btn-sm px-3 rounded-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-1" style="font-size: 0.8rem;">
                                            Điền thông tin <i class="bi bi-box-arrow-up-right" style="font-size: 0.7rem;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
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
                            <div class="card h-100 border rounded-3 overflow-hidden shadow-sm">
                                <img src="<?= htmlspecialchars($c['image_url'] ?? 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600') ?>" class="card-img-top" style="height: 160px; object-fit: cover;" alt="cover">
                                <div class="card-body d-flex flex-column p-3">
                                    <h6 class="fw-bold text-dark mb-2"><?= htmlspecialchars($c['title']) ?></h6>
                                    <p class="text-muted small text-truncate-2 mb-3"><?= htmlspecialchars($c['description']) ?></p>
                                    <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                        <span class="badge bg-success-subtle text-success">Đã kích hoạt</span>
                                        <a href="index.php#khoa-hoc" class="btn btn-primary btn-sm rounded-pill px-3">Vào học ngay</a>
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
