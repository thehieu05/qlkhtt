<?php
require_once 'config.php';

$error = "";
$success = "";
$active_tab = "login";
$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? 'login';
    if ($action === 'register') {
        $active_tab = "register";
        $reg_username = trim($_POST['reg_username'] ?? '');
        $reg_email    = trim($_POST['reg_email'] ?? '');
        $reg_password = $_POST['reg_password'] ?? '';
        $reg_fullname = trim($_POST['reg_fullname'] ?? '');

        if ($reg_username === '' || $reg_email === '' || $reg_password === '' || $reg_fullname === '') {
            $error = "Vui lòng nhập đầy đủ họ tên, tên đăng nhập, email và mật khẩu!";
        } elseif (!filter_var($reg_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Địa chỉ email không hợp lệ!";
        } elseif (strlen($reg_password) < 6) {
            $error = "Mật khẩu phải có ít nhất 6 ký tự!";
        } else {
            $stmt_chk = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt_chk->bind_param('ss', $reg_username, $reg_email);
            $stmt_chk->execute();
            $chk_res = $stmt_chk->get_result();
            if ($chk_res && $chk_res->num_rows > 0) {
                $error = "Tên đăng nhập hoặc email đã tồn tại. Vui lòng chọn thông tin khác!";
                $stmt_chk->close();
            } else {
                $stmt_chk->close();
                $hashed = password_hash($reg_password, PASSWORD_BCRYPT);
                $role = 'student';
                $status = 'active';
                $stmt_ins = $conn->prepare("INSERT INTO users (username, password, fullname, email, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ins->bind_param('ssssss', $reg_username, $hashed, $reg_fullname, $reg_email, $role, $status);
                if ($stmt_ins->execute()) {
                    $new_id = $stmt_ins->insert_id;
                    $stmt_ins->close();
                    $_SESSION['user_id'] = $new_id;
                    $_SESSION['fullname'] = $reg_fullname;
                    $_SESSION['username'] = $reg_username;
                    $_SESSION['role'] = 'student';
                    $target = ($redirect !== '' && strpos($redirect, 'http') !== 0) ? $redirect : 'profile.php';
                    header("Location: " . $target);
                    exit();
                } else {
                    $error = "Có lỗi xảy ra khi tạo tài khoản: " . $conn->error;
                    $stmt_ins->close();
                }
            }
        }
    } else {
        $active_tab = "login";
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT id, username, password, fullname, role, status FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();

            if (!password_verify($password, $row['password'])) {
                $error = "Sai tên đăng nhập hoặc mật khẩu! Vui lòng thử lại.";
            } elseif ($row['status'] !== 'active') {
                $error = "Tài khoản của bạn hiện đang bị khóa. Vui lòng liên hệ quản trị viên!";
            } else {
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['fullname'] = $row['fullname'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];

                if ($row['role'] === 'admin') {
                    header("Location: admin/index.php");
                } else {
                    $target = ($redirect !== '' && strpos($redirect, 'http') !== 0) ? $redirect : 'profile.php';
                    header("Location: " . $target);
                }
                exit();
            }
        } else {
            if ($stmt) $stmt->close();
            $error = "Sai tên đăng nhập hoặc mật khẩu! Vui lòng thử lại.";
        }
    }
}
require_once 'includes/header.php';
require_once 'includes/menu.php';
?>
<div class="container pb-4 auth-page-bg">
    <div class="auth-breadcrumb-wrap">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb auth-breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="index.php">
                        <i class="bi bi-house-door me-1" style="font-size: 0.8rem;"></i>Trang chủ
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    Đăng nhập & đăng ký
                </li>
            </ol>
        </nav>
    </div>
    <div class="auth-split-card">
        <div class="row g-0">
            <div class="col-lg-5 auth-brand-col">
                <div>
                    <div class="auth-pill-badge mb-4">
                        <span class="badge-icon">E</span>
                        <span>EduNova</span>
                    </div>
                    <div class="text-warning fw-bold text-uppercase small mb-2" style="letter-spacing: 1.5px; font-size: 0.88rem;">
                        HỌC TẬP LIỀN MẠCH
                    </div>
                    <h2 class="text-white fw-bold mb-3" style="font-size: 1.45rem; line-height: 1.3;">
                        Mọi khóa học của bạn luôn sẵn sàng.
                    </h2>
                    <div class="d-flex flex-column gap-3 mb-5 mt-4">
                        <div class="auth-benefit-item">
                            <div class="auth-benefit-icon">
                                <i class="bi bi-journal-bookmark-fill"></i>
                            </div>
                            <div class="auth-benefit-text">
                                Lưu khóa học và link truy cập tại một nơi
                            </div>
                        </div>
                        <div class="auth-benefit-item">
                            <div class="auth-benefit-icon">
                                <i class="bi bi-box-seam-fill"></i>
                            </div>
                            <div class="auth-benefit-text">
                                Theo dõi đơn hàng, không cần tìm lại tin nhắn
                            </div>
                        </div>
                        <div class="auth-benefit-item">
                            <div class="auth-benefit-icon">
                                <i class="bi bi-headset"></i>
                            </div>
                            <div class="auth-benefit-text">
                                Được ưu tiên hỗ trợ khi cần cấp lại khóa học
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-top border-secondary border-opacity-25 mt-3">
                    <div class="text-secondary small mb-1" style="font-size: 0.78rem;">Cần trợ giúp?</div>
                    <a href="https://zalo.me/0773308885" target="_blank" class="text-white fw-bold small text-decoration-none d-inline-flex align-items-center gap-1">
                        Nhắn Zalo <span class="text-warning">0773308885</span> <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.72rem;"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-7 auth-form-col">
                <div class="auth-segmented-tabs">
                    <button type="button" class="tab-btn <?= $active_tab === 'login' ? 'active' : '' ?>" id="btnTabLogin" onclick="switchAuthTab('login')">
                        Đăng nhập
                    </button>
                    <button type="button" class="tab-btn <?= $active_tab === 'register' ? 'active' : '' ?>" id="btnTabRegister" onclick="switchAuthTab('register')">
                        Tạo tài khoản
                    </button>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-4 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-6 flex-shrink-0 text-danger"></i>
                        <div><?= $error ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2.5 px-3 small d-flex align-items-center gap-2 mb-4 rounded-3 shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0 text-success"></i>
                        <div>
                            <div class="fw-bold">Tạo tài khoản thành công!</div>
                            <div class="text-secondary small mt-0.5">Đang tự động đăng nhập và chuyển hướng về trang chủ trong giây lát...</div>
                        </div>
                    </div>
                <?php endif; ?>
                <div id="authFormLogin" style="<?= $active_tab === 'login' ? '' : 'display: none;' ?>">
                    <div class="text-warning-emphasis fw-bold text-uppercase small mb-4" style="font-size: 1.35rem; letter-spacing: 0.5px;">
                        CHÀO MỪNG BẠN QUAY LẠI 
                    </div>
                    <form method="POST" action="login.php">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">Email hoặc Tên đăng nhập</label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text ps-3">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="text" name="username" class="form-control" placeholder="ban@example.com hoặc giangvien" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold text-dark mb-0">Mật khẩu</label>
                                <a href="javascript:void(0)" onclick="alert('Vui lòng liên hệ Hotline/Zalo: 0773 308 885 để được cấp lại mật khẩu ngay!');" class="small text-muted text-decoration-none" style="font-size: 0.8rem;">
                                    Tạo / quên mật khẩu?
                                </a>
                            </div>
                            <div class="input-group login-input-group">
                                <span class="input-group-text ps-3">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Nhập mật khẩu" required>
                                <button type="button" class="input-group-text pe-3 text-muted" onclick="togglePasswordVisibility('loginPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-auth-gold w-100 fw-bold py-2.5 mb-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                            <span>Đăng nhập</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                        <div class="text-center small text-muted mt-3">
                            Chưa có tài khoản? <a href="javascript:void(0)" onclick="switchAuthTab('register')" class="fw-semibold text-warning-emphasis text-decoration-none">Đăng ký ngay</a>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: FORM TẠO TÀI KHOẢN -->
                <div id="authFormRegister" style="<?= $active_tab === 'register' ? '' : 'display: none;' ?>">
                    <div class="text-warning-emphasis fw-bold text-uppercase small mb-1" style="font-size: 1.35rem; letter-spacing: 0.5px;">
                        TẠO TÀI KHOẢN EduNova
                    </div>
                    <p class="text-muted small mb-4" style="font-size: 0.88rem;">
                        Nhận ngay quyền truy cập thư viện khóa học trực tuyến.
                    </p>
                    
                    <form method="POST" action="login.php">
                        <input type="hidden" name="action" value="register">

                        <!-- Họ và tên -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-dark mb-1">Họ và tên </label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text ps-3">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" name="reg_fullname" class="form-control" placeholder="Ví dụ: Nguyễn Văn A" required value="<?= htmlspecialchars($_POST['reg_fullname'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Tên đăng nhập -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-dark mb-1">Tên đăng nhập (viết liền)</label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text ps-3">
                                    <i class="bi bi-at"></i>
                                </span>
                                <input type="text" name="reg_username" class="form-control" placeholder="Ví dụ: hocvien2026" required value="<?= htmlspecialchars($_POST['reg_username'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-dark mb-1">Email</label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text ps-3">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" name="reg_email" class="form-control" placeholder="Ví dụ: hocvien@gmail.com" required value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Mật khẩu -->
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-dark mb-1">Mật khẩu</label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text ps-3">
                                    <i class="bi bi-shield-lock"></i>
                                </span>
                                <input type="password" name="reg_password" id="regPassword" class="form-control" placeholder="Tạo mật khẩu an toàn" required>
                                <button type="button" class="input-group-text pe-3 text-muted" onclick="togglePasswordVisibility('regPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Nút Tạo tài khoản -->
                        <button type="submit" class="btn btn-auth-gold w-100 fw-bold py-2.5 mb-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                            <span>Tạo tài khoản</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>

                        <!-- Chuyển sang Đăng nhập -->
                        <div class="text-center small text-muted mt-3">
                            Đã có tài khoản? <a href="javascript:void(0)" onclick="switchAuthTab('login')" class="fw-semibold text-warning-emphasis text-decoration-none">Đăng nhập ngay</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT CHUYỂN TAB & ẨN HIỆN MẬT KHẨU -->
<script>
function switchAuthTab(tab) {
    const btnLogin = document.getElementById('btnTabLogin');
    const btnRegister = document.getElementById('btnTabRegister');
    const formLogin = document.getElementById('authFormLogin');
    const formRegister = document.getElementById('authFormRegister');

    if (tab === 'login') {
        btnLogin.classList.add('active');
        btnRegister.classList.remove('active');
        formLogin.style.display = 'block';
        formRegister.style.display = 'none';
    } else {
        btnRegister.classList.add('active');
        btnLogin.classList.remove('active');
        formRegister.style.display = 'block';
        formLogin.style.display = 'none';
    }
}

function togglePasswordVisibility(inputId, btn) {
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

<?php if (!empty($success)): ?>
// Đếm ngược chuyển trang khi tạo tài khoản thành công
setTimeout(function() {
    window.location.href = 'profile.php';
}, 1800);
<?php endif; ?>
</script>

<?php
// Nhúng Footer chung (Đóng container, hiển thị Cam kết & Chân trang)
require_once 'includes/footer.php';
?>
