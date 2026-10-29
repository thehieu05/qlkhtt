<?php
require_once 'header.php';

// Kiểm tra quyền hạn Quản trị viên / Giảng viên (Role = 1)
$is_admin = isset($_SESSION['role']) && (int)$_SESSION['role'] === 1;

// XỬ LÝ ĐĂNG NHẬP NHANH TÀI KHOẢN GIẢNG VIÊN ĐỂ TIỆN TEST
if (isset($_GET['quick_login']) && $_GET['quick_login'] === 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['fullname'] = 'Thầy Giáo Hiếu';
    $_SESSION['role'] = 1;
    $_SESSION['flash_msg'] = "Đăng nhập nhanh thành công với quyền <b>Quản trị viên / Giảng viên (Thầy Giáo Hiếu)</b>!";
    $_SESSION['flash_type'] = "success";
    header("Location: ql_khoahoc.php");
    exit();
}

// -------------------------------------------------------------
// 1. XỬ LÝ FORM: THÊM KHÓA HỌC (INSERT) & SỬA KHÓA HỌC (UPDATE)
// -------------------------------------------------------------
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && $is_admin) {
    $action = $_POST['action'] ?? '';
    
    // XỬ LÝ XÓA QUA POST MODAL (NẾU DÙNG FORM XÓA)
    if ($action === 'delete') {
        $del_id = intval($_POST['course_id'] ?? 0);
        if ($del_id > 0) {
            // Lấy tên khóa học để hiển thị thông báo
            $check = mysqli_query($conn, "SELECT title FROM courses WHERE id = $del_id");
            $cRow = mysqli_fetch_assoc($check);
            $del_title = $cRow ? $cRow['title'] : "ID #$del_id";

            $del_sql = "DELETE FROM courses WHERE id = $del_id";
            if (mysqli_query($conn, $del_sql)) {
                $_SESSION['flash_msg'] = "Đã xóa thành công khóa học: <b>" . htmlspecialchars($del_title) . "</b> (Mã #$del_id)!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "Lỗi khi xóa khóa học: " . mysqli_error($conn);
                $_SESSION['flash_type'] = "danger";
            }
        }
        header("Location: ql_khoahoc.php");
        exit();
    }

    // XỬ LÝ THÊM MỚI HOẶC CẬP NHẬT
    $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $title     = trim($_POST['title'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $img       = trim($_POST['image_url'] ?? '');

    // Nếu để trống ảnh, đặt ảnh mặc định chất lượng cao
    if (empty($img)) {
        $img = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80';
    }

    if (empty($title) || empty($desc)) {
        $_SESSION['flash_msg'] = "Vui lòng nhập đầy đủ <b>Tên khóa học</b> và <b>Mô tả khóa học</b>!";
        $_SESSION['flash_type'] = "danger";
        header("Location: ql_khoahoc.php" . ($course_id > 0 ? "?edit=$course_id" : ""));
        exit();
    }

    // Làm sạch dữ liệu để tránh SQL Injection
    $safe_title = mysqli_real_escape_string($conn, $title);
    $safe_desc  = mysqli_real_escape_string($conn, $desc);
    $safe_img   = mysqli_real_escape_string($conn, $img);

    if ($course_id > 0) {
        // LỆNH UPDATE KHÓA HỌC
        $sql = "UPDATE courses SET title = '$safe_title', description = '$safe_desc', image_url = '$safe_img' WHERE id = $course_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['flash_msg'] = "Cập nhật thành công khóa học mã <b>#$course_id</b>: <b>" . htmlspecialchars($title) . "</b>!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_msg'] = "Lỗi khi cập nhật khóa học: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        // LỆNH INSERT KHÓA HỌC MỚI
        $sql = "INSERT INTO courses (title, description, image_url) VALUES ('$safe_title', '$safe_desc', '$safe_img')";
        if (mysqli_query($conn, $sql)) {
            $new_id = mysqli_insert_id($conn);
            $_SESSION['flash_msg'] = "Thêm thành công khóa học mới (Mã <b>#$new_id</b>): <b>" . htmlspecialchars($title) . "</b>!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_msg'] = "Lỗi khi thêm khóa học mới: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
        }
    }

    header("Location: ql_khoahoc.php");
    exit();
}

// -------------------------------------------------------------
// 2. XỬ LÝ XÓA KHÓA HỌC (LỆNH DELETE QUA GET METHOD)
// -------------------------------------------------------------
if (isset($_GET['delete']) && $is_admin) {
    $del_id = intval($_GET['delete']);
    if ($del_id > 0) {
        // Lấy tên khóa học trước khi xóa
        $check = mysqli_query($conn, "SELECT title FROM courses WHERE id = $del_id");
        $cRow = mysqli_fetch_assoc($check);
        $del_title = $cRow ? $cRow['title'] : "Mã #$del_id";

        $sql = "DELETE FROM courses WHERE id = $del_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['flash_msg'] = "Đã xóa vĩnh viễn khóa học: <b>" . htmlspecialchars($del_title) . "</b>!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_msg'] = "Lỗi khi xóa khóa học: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
        }
    }
    header("Location: ql_khoahoc.php");
    exit();
}

// -------------------------------------------------------------
// 3. XỬ LÝ NẠP LẠI 6 KHÓA HỌC MẪU CHUẨN (CHO ADMIN)
// -------------------------------------------------------------
if (isset($_GET['seed_samples']) && $_GET['seed_samples'] === '1' && $is_admin) {
    $sample_data = [
        [
            'title' => 'Lập trình Web Fullstack với PHP & MySQL',
            'description' => 'Khóa học toàn diện từ cơ bản đến nâng cao: Cú pháp PHP hiện đại, tư duy OOP, thiết kế CSDL MySQL chuẩn hóa, xây dựng website thương mại điện tử bảo mật cao (chống SQL Injection, XSS) và triển khai hosting.',
            'image_url' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=800&auto=format&fit=crop&q=80'
        ],
        [
            'title' => 'Lập trình JavaScript Hiện Đại & React.js Toàn Diện',
            'description' => 'Chinh phục hệ sinh thái JavaScript: ES6+, Async/Await, làm chủ React Hooks, Redux Toolkit, TailwindCSS và xây dựng ứng dụng Web thực tế chuẩn công nghiệp.',
            'image_url' => 'https://images.unsplash.com/photo-1579468118864-dd49292ab727?w=800&auto=format&fit=crop&q=80'
        ],
        [
            'title' => 'Lập trình Python & Trí Tuệ Nhân Tạo (AI & Data Science)',
            'description' => 'Học Python từ số 0 đến nâng cao: Phân tích và trực quan hóa dữ liệu với Pandas, NumPy, Matplotlib, Seaborn; xây dựng mô hình Học máy (Machine Learning) ứng dụng thực tế.',
            'image_url' => 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800&auto=format&fit=crop&q=80'
        ],
        [
            'title' => 'Thiết Kế Đồ Họa & UI/UX Chuyên Nghiệp (Figma, Photoshop)',
            'description' => 'Làm chủ quy trình thiết kế sản phẩm số: Thiết kế giao diện Website & Mobile App trực quan trên Figma, tư duy hệ thống lưới, phối màu và chỉnh sửa ảnh truyền thông với Photoshop.',
            'image_url' => 'https://images.unsplash.com/photo-1581291518655-9523c93269c4?w=800&auto=format&fit=crop&q=80'
        ],
        [
            'title' => 'Digital Marketing Thực Chiến Đa Kênh (SEO, Facebook & TikTok Ads)',
            'description' => 'Chiến lược tiếp thị số tối ưu chuyển đổi từ A-Z: Phân tích thị trường, SEO on-page/off-page lên top Google, chạy quảng cáo Facebook Ads, Google Ads hiệu quả và sáng tạo video ngắn TikTok.',
            'image_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop&q=80'
        ],
        [
            'title' => 'Tiếng Anh Giao Tiếp & Phỏng Vấn Thực Chiến Cho Dân Công Nghệ',
            'description' => 'Nâng cao phản xạ giao tiếp quốc tế: Kỹ năng viết email chuyên nghiệp, thuyết trình báo cáo dự án bằng tiếng Anh, trả lời phỏng vấn IT và tự tin làm việc với khách hàng toàn cầu.',
            'image_url' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=800&auto=format&fit=crop&q=80'
        ]
    ];

    // Xóa khóa học cũ và chèn lại 6 khóa học mẫu chuẩn
    mysqli_query($conn, "TRUNCATE TABLE courses");
    foreach ($sample_data as $s) {
        $st = mysqli_real_escape_string($conn, $s['title']);
        $sd = mysqli_real_escape_string($conn, $s['description']);
        $si = mysqli_real_escape_string($conn, $s['image_url']);
        mysqli_query($conn, "INSERT INTO courses (title, description, image_url) VALUES ('$st', '$sd', '$si')");
    }

    $_SESSION['flash_msg'] = "Đã khôi phục thành công <b>6 khóa học mẫu chuẩn</b> với đầy đủ tên, mô tả và hình ảnh đẹp!";
    $_SESSION['flash_type'] = "success";
    header("Location: ql_khoahoc.php");
    exit();
}

// -------------------------------------------------------------
// 4. LẤY THÔNG TIN KHÓA HỌC NẾU ĐANG BẤM NÚT "SỬA"
// -------------------------------------------------------------
$edit_course = null;
if (isset($_GET['edit']) && $is_admin) {
    $edit_id = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM courses WHERE id = $edit_id");
    if ($res && mysqli_num_rows($res) > 0) {
        $edit_course = mysqli_fetch_assoc($res);
    }
}

// -------------------------------------------------------------
// 5. THỐNG KÊ TỔNG QUAN HỆ THỐNG
// -------------------------------------------------------------
$stat_courses  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM courses"))[0] ?? 0;
$stat_students = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 0"))[0] ?? 0;
$stat_teachers = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 1"))[0] ?? 0;
$stat_lessons  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM lessons"))[0] ?? 0;

// -------------------------------------------------------------
// 6. TÌM KIẾM & LẤY DANH SÁCH KHÓA HỌC
// -------------------------------------------------------------
$search_admin = isset($_GET['q_admin']) ? trim($_GET['q_admin']) : '';
$where_admin = "";
if ($search_admin !== '') {
    $safe_q = mysqli_real_escape_string($conn, $search_admin);
    $where_admin = "WHERE title LIKE '%$safe_q%' OR description LIKE '%$safe_q%'";
}

$courses_query = mysqli_query($conn, "SELECT * FROM courses $where_admin ORDER BY id DESC");
$total_found = mysqli_num_rows($courses_query);

// Đọc và xóa flash message
$flash_msg  = $_SESSION['flash_msg'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
?>

<div class="container py-4">

    <!-- BREADCRUMB & THANH ĐIỀU HƯỚNG ADMIN -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb auth-breadcrumb">
            <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door me-1"></i>Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Bảng điều khiển Quản trị Khóa học</li>
        </ol>
    </nav>

    <!-- TIÊU ĐỀ TRANG ADMIN & CÁC NÚT TÁC VỤ NHANH -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 p-md-4 rounded-4 border shadow-sm">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary px-3 py-1 rounded-pill">ADMIN PANEL</span>
                <?php if ($is_admin): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($_SESSION['fullname']) ?> (Giảng viên / Admin)
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                        <i class="bi bi-exclamation-triangle me-1"></i>Chế độ Khách / Chưa đăng nhập
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="fw-bold mb-1 text-dark">Quản Trị Hệ Thống Khóa Học</h2>
            <p class="text-muted small mb-0">Thực hiện thêm mới, cập nhật, xóa và kiểm tra danh sách khóa học trên EduNova</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm fw-semibold rounded-3 px-3">
                <i class="bi bi-eye me-1"></i> Xem Website Ngoài
            </a>
            <?php if ($is_admin): ?>
                <a href="ql_khoahoc.php?seed_samples=1" 
                   class="btn btn-outline-info btn-sm fw-semibold rounded-3 px-3" 
                   onclick="return confirm('Bạn có chắc muốn nạp lại 6 khóa học mẫu chuẩn? Dữ liệu khóa học hiện tại sẽ được đặt lại theo mẫu chuẩn đẹp mắt.')">
                    <i class="bi bi-magic me-1"></i> Nạp 6 Khóa Học Mẫu
                </a>
                <a href="#form-khoa-hoc" class="btn btn-success btn-sm fw-semibold rounded-3 px-3">
                    <i class="bi bi-plus-circle me-1"></i> Thêm Khóa Học Mới
                </a>
            <?php else: ?>
                <a href="ql_khoahoc.php?quick_login=admin" class="btn btn-warning btn-sm fw-bold rounded-3 px-3 shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập Giảng viên Nhanh (1 Click)
                </a>
                <a href="login.php" class="btn btn-primary btn-sm fw-semibold rounded-3 px-3">
                    <i class="bi bi-person-fill me-1"></i> Trang Đăng Nhập
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- HỘP THỐNG KÊ NHANH (KPI METRICS) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card d-flex align-items-center gap-3">
                <div class="admin-stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-journal-code"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Tổng Khóa Học</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($stat_courses) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card d-flex align-items-center gap-3">
                <div class="admin-stat-icon bg-success-subtle text-success">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Học Viên</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($stat_students) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card d-flex align-items-center gap-3">
                <div class="admin-stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Giảng Viên</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($stat_teachers) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card d-flex align-items-center gap-3">
                <div class="admin-stat-icon bg-info-subtle text-info">
                    <i class="bi bi-camera-video-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Bài Giảng / Video</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($stat_lessons) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- THÔNG BÁO FLASH MESSAGE (NẾU CÓ) -->
    <?php if ($flash_msg): ?>
        <div class="alert alert-<?= $flash_type ?> alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi <?= $flash_type === 'success' ? 'bi-check-circle-fill fs-4' : 'bi-exclamation-triangle-fill fs-4' ?>"></i>
            <div class="flex-grow-1"><?= $flash_msg ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- CẢNH BÁO NẾU CHƯA ĐĂNG NHẬP ADMIN -->
    <?php if (!$is_admin): ?>
        <div class="card border-warning bg-warning-subtle rounded-4 p-3 mb-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-shield-lock-fill fs-1 text-warning"></i>
                <div class="flex-grow-1">
                    <h5 class="fw-bold text-dark mb-1">Bạn đang xem ở chế độ Khách (Chỉ Xem)</h5>
                    <p class="mb-0 text-muted small">
                        Để thực hiện các chức năng <b>Thêm khóa học</b>, <b>Sửa khóa học</b> và <b>Xóa khóa học</b> (các lệnh INSERT, UPDATE, DELETE trong MySQL), 
                        bạn cần đăng nhập bằng tài khoản Giảng viên / Quản trị viên.
                    </p>
                </div>
                <a href="ql_khoahoc.php?quick_login=admin" class="btn btn-warning fw-bold px-3 py-2 rounded-3 shadow-sm text-nowrap">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Bật Quyền Admin Ngay
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4" id="form-khoa-hoc">
        <!-- ========================================================
             CỘT TRÁI: FORM THÊM / SỬA KHÓA HỌC (CHỈ HIỆN CHO ADMIN)
             ======================================================== -->
        <?php if ($is_admin): ?>
        <div class="col-12 col-lg-5">
            <div class="admin-form-card">
                <!-- Header Form đổi màu theo chế độ Thêm mới (Xanh lá) hoặc Sửa (Vàng cam) -->
                <div class="card-header <?= $edit_course ? 'bg-warning text-dark' : 'bg-success text-white' ?> py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="fw-bold fs-6">
                        <i class="bi <?= $edit_course ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-2"></i>
                        <?= $edit_course ? '✏️ HIỆU CHỈNH KHÓA HỌC #' . $edit_course['id'] : '➕ THÊM KHÓA HỌC MỚI' ?>
                    </div>
                    <?php if ($edit_course): ?>
                        <span class="badge bg-dark text-white rounded-pill">Đang Sửa</span>
                    <?php else: ?>
                        <span class="badge bg-white text-success rounded-pill fw-bold">Tạo Mới</span>
                    <?php endif; ?>
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="ql_khoahoc.php" id="courseAdminForm">
                        <input type="hidden" name="course_id" value="<?= $edit_course['id'] ?? '' ?>">
                        <input type="hidden" name="action" value="<?= $edit_course ? 'edit' : 'add' ?>">

                        <!-- 1. TÊN KHÓA HỌC -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">
                                Tên khóa học <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-book"></i></span>
                                <input type="text" 
                                       name="title" 
                                       id="courseTitleInput"
                                       class="form-control" 
                                       placeholder="Ví dụ: Lập trình Web Fullstack với PHP & MySQL" 
                                       required 
                                       value="<?= htmlspecialchars($edit_course['title'] ?? '') ?>">
                            </div>
                            <div class="form-text small text-muted">Đặt tên rõ ràng, thu hút học viên quan tâm.</div>
                        </div>

                        <!-- 2. LINK ẢNH BÌA & XEM TRƯỚC HÌNH ẢNH -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small d-flex justify-content-between align-items-center">
                                <span>Link ảnh bìa (URL)</span>
                                <span class="text-muted fw-normal" style="font-size: 0.75rem;">(Tùy chọn, để trống sẽ dùng ảnh mẫu)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-image"></i></span>
                                <input type="url" 
                                       name="image_url" 
                                       id="courseImageInput" 
                                       class="form-control" 
                                       placeholder="https://images.unsplash.com/photo-..." 
                                       value="<?= htmlspecialchars($edit_course['image_url'] ?? '') ?>">
                            </div>

                            <!-- GỢI Ý LINK ẢNH ĐẸP NHANH (QUICK PICKS) -->
                            <div class="mt-2">
                                <span class="text-muted small me-1">Gợi ý ảnh đẹp:</span>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <span class="quick-img-pill" onclick="pickImage('https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=800&auto=format&fit=crop&q=80')">💻 Web PHP</span>
                                    <span class="quick-img-pill" onclick="pickImage('https://images.unsplash.com/photo-1579468118864-dd49292ab727?w=800&auto=format&fit=crop&q=80')">⚡ JavaScript</span>
                                    <span class="quick-img-pill" onclick="pickImage('https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800&auto=format&fit=crop&q=80')">🐍 Python AI</span>
                                    <span class="quick-img-pill" onclick="pickImage('https://images.unsplash.com/photo-1581291518655-9523c93269c4?w=800&auto=format&fit=crop&q=80')">🎨 UI/UX Figma</span>
                                    <span class="quick-img-pill" onclick="pickImage('https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop&q=80')">📊 Marketing</span>
                                </div>
                            </div>

                            <!-- KHUNG XEM TRƯỚC ẢNH (LIVE PREVIEW) -->
                            <div class="live-preview-box mt-2">
                                <div class="small text-muted mb-1 d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-eye me-1"></i>Xem trước ảnh bìa:</span>
                                    <span id="previewStatus" class="badge bg-secondary-subtle text-secondary">Sẵn sàng</span>
                                </div>
                                <img id="liveImagePreview" 
                                     src="<?= htmlspecialchars($edit_course['image_url'] ?? 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80') ?>" 
                                     alt="Preview ảnh bìa" 
                                     onerror="onImagePreviewError()">
                            </div>
                        </div>

                        <!-- 3. MÔ TẢ KHÓA HỌC -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small">
                                Mô tả khóa học <span class="text-danger">*</span>
                            </label>
                            <textarea name="description" 
                                      id="courseDescInput"
                                      class="form-control" 
                                      rows="4" 
                                      placeholder="Tóm tắt nội dung khóa học, kiến thức học được và mục tiêu đầu ra..." 
                                      required><?= htmlspecialchars($edit_course['description'] ?? '') ?></textarea>
                            <div class="form-text small text-muted">Mô tả ngắn gọn, súc tích (khoảng 2–4 câu).</div>
                        </div>

                        <!-- CÁC NÚT BẤM THỰC THI (ACTION BUTTONS) -->
                        <div class="d-grid gap-2">
                            <?php if ($edit_course): ?>
                                <button type="submit" class="btn btn-warning fw-bold py-2 shadow-sm">
                                    <i class="bi bi-check2-circle me-1"></i> Lưu Cập Nhật Khóa Học
                                </button>
                                <a href="ql_khoahoc.php" class="btn btn-light border fw-semibold py-2">
                                    <i class="bi bi-x-circle me-1"></i> Hủy Sửa (Về Thêm Mới)
                                </a>
                            <?php else: ?>
                                <button type="submit" class="btn btn-success fw-bold py-2 shadow-sm">
                                    <i class="bi bi-plus-lg me-1"></i> Thêm Khóa Học Vào Hệ Thống
                                </button>
                                <button type="button" class="btn btn-light border py-2" onclick="resetAdminForm()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Làm Mới Form
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========================================================
             CỘT PHẢI: BẢNG DANH SÁCH KHÓA HỌC HIỆN CÓ
             ======================================================== -->
        <div class="<?= $is_admin ? 'col-12 col-lg-7' : 'col-12' ?>">
            <div class="admin-table-card">
                <!-- Thanh công cụ lọc & tìm kiếm khóa học -->
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <div class="row align-items-center g-2">
                        <div class="col-md-6">
                            <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                <i class="bi bi-collection-play-fill text-primary"></i> 
                                <span>DANH SÁCH KHÓA HỌC</span>
                                <span class="badge bg-primary rounded-pill"><?= $total_found ?> khóa</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="ql_khoahoc.php" class="d-flex gap-2">
                                <div class="input-group input-group-sm">
                                    <input type="text" 
                                           name="q_admin" 
                                           class="form-control" 
                                           placeholder="Tìm theo tên khóa học..." 
                                           value="<?= htmlspecialchars($search_admin) ?>">
                                    <button class="btn btn-outline-primary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                    <?php if ($search_admin !== ''): ?>
                                        <a href="ql_khoahoc.php" class="btn btn-outline-secondary" title="Xóa bộ lọc">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Bảng danh sách khóa học -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase fw-semibold">
                            <tr>
                                <th width="60" class="text-center">#ID</th>
                                <th width="110">Ảnh Bìa</th>
                                <th>Tên & Mô Tả Khóa Học</th>
                                <th width="150" class="text-center">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_found > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($courses_query)): 
                                    $img_display = !empty($row['image_url']) ? $row['image_url'] : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80';
                                    $is_current_editing = $edit_course && $edit_course['id'] == $row['id'];
                                ?>
                                <tr class="<?= $is_current_editing ? 'table-warning' : '' ?>">
                                    <!-- Cột ID -->
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">#<?= $row['id'] ?></span>
                                    </td>

                                    <!-- Cột Ảnh Bìa -->
                                    <td>
                                        <img src="<?= htmlspecialchars($img_display) ?>" 
                                             alt="<?= htmlspecialchars($row['title']) ?>" 
                                             class="admin-table-img shadow-sm"
                                             onclick="showImageModal('<?= htmlspecialchars($img_display) ?>', '<?= htmlspecialchars(addslashes($row['title'])) ?>')"
                                             title="Bấm để xem ảnh phóng to"
                                             style="cursor: pointer;">
                                    </td>

                                    <!-- Cột Thông tin khóa học -->
                                    <td>
                                        <div class="fw-bold text-dark mb-1 d-flex align-items-center flex-wrap gap-2">
                                            <span><?= htmlspecialchars($row['title']) ?></span>
                                            <?php if ($is_current_editing): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-pencil me-1"></i>Đang sửa</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-muted small mb-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;">
                                            <?= htmlspecialchars($row['description']) ?>
                                        </p>
                                        <div class="d-flex align-items-center gap-2 small text-secondary">
                                            <span><i class="bi bi-calendar3 me-1"></i><?= !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : 'Vừa tạo' ?></span>
                                            <span>•</span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Hoạt động</span>
                                        </div>
                                    </td>

                                    <!-- Cột Hành Động (Sửa & Xóa) -->
                                    <td class="text-center">
                                        <?php if ($is_admin): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <!-- NÚT SỬA KHÓA HỌC -->
                                                <a href="ql_khoahoc.php?edit=<?= $row['id'] ?>#form-khoa-hoc" 
                                                   class="btn <?= $is_current_editing ? 'btn-warning' : 'btn-outline-warning' ?>" 
                                                   title="Sửa thông tin khóa học">
                                                    <i class="bi bi-pencil-square"></i> Sửa
                                                </a>

                                                <!-- NÚT XÓA KHÓA HỌC (LỆNH DELETE) -->
                                                <a href="ql_khoahoc.php?delete=<?= $row['id'] ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('⚠️ BẠN CÓ CHẮC CHẮN MUỐN XÓA KHÓA HỌC:\n\n\'<?= htmlspecialchars(addslashes($row['title'])) ?>\' (Mã #<?= $row['id'] ?>)?\n\nHành động này sẽ thực thi lệnh DELETE trong MySQL và không thể hoàn tác!')" 
                                                   title="Xóa khóa học">
                                                    <i class="bi bi-trash3-fill"></i> Xóa
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <a href="index.php" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-eye"></i> Xem
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                        <p class="mb-2 fw-semibold">Không tìm thấy khóa học nào phù hợp!</p>
                                        <?php if ($search_admin !== ''): ?>
                                            <a href="ql_khoahoc.php" class="btn btn-sm btn-outline-primary">Xóa bộ lọc tìm kiếm</a>
                                        <?php elseif ($is_admin): ?>
                                            <a href="ql_khoahoc.php?seed_samples=1" class="btn btn-sm btn-primary">
                                                <i class="bi bi-magic me-1"></i> Nạp lại 6 khóa học mẫu ngay
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer của Card bảng -->
                <div class="card-footer bg-light py-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        Hiển thị <b><?= $total_found ?></b> khóa học trên trang quản trị.
                    </span>
                    <a href="index.php#khoa-hoc" target="_blank" class="small text-primary text-decoration-none fw-semibold">
                        Xem hiển thị trên Website <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MODAL XEM PHÓNG TO ẢNH BÌA -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold" id="imageModalTitle">Ảnh bìa khóa học</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="imageModalSrc" src="" alt="Ảnh khóa học" class="img-fluid rounded-3 shadow" style="max-height: 480px; width: 100%; object-fit: cover;">
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT HỖ TRỢ LIVE PREVIEW & INTERACTION -->
<script>
    // Hàm chọn link ảnh mẫu nhanh từ danh sách gợi ý
    function pickImage(url) {
        const input = document.getElementById('courseImageInput');
        if (input) {
            input.value = url;
            updateLivePreview(url);
        }
    }

    // Cập nhật khung xem trước ảnh trong form
    function updateLivePreview(url) {
        const preview = document.getElementById('liveImagePreview');
        const status = document.getElementById('previewStatus');
        if (!preview) return;

        if (url && url.trim() !== '') {
            preview.src = url.trim();
            if (status) {
                status.className = 'badge bg-success-subtle text-success';
                status.innerText = 'Đang tải ảnh...';
            }
            preview.onload = function() {
                if (status) {
                    status.className = 'badge bg-success-subtle text-success';
                    status.innerText = 'Ảnh hợp lệ ✓';
                }
            };
        } else {
            preview.src = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80';
            if (status) {
                status.className = 'badge bg-secondary-subtle text-secondary';
                status.innerText = 'Ảnh mặc định';
            }
        }
    }

    // Xử lý khi ảnh URL bị lỗi (không tồn tại hoặc link chết)
    function onImagePreviewError() {
        const preview = document.getElementById('liveImagePreview');
        const status = document.getElementById('previewStatus');
        if (preview) {
            preview.src = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80';
        }
        if (status) {
            status.className = 'badge bg-danger-subtle text-danger';
            status.innerText = 'Link ảnh lỗi (dùng ảnh thay thế)';
        }
    }

    // Lắng nghe sự kiện người dùng gõ / dán link ảnh vào input
    const imgInput = document.getElementById('courseImageInput');
    if (imgInput) {
        imgInput.addEventListener('input', function() {
            updateLivePreview(this.value);
        });
        imgInput.addEventListener('change', function() {
            updateLivePreview(this.value);
        });
    }

    // Làm mới Form
    function resetAdminForm() {
        const form = document.getElementById('courseAdminForm');
        if (form) {
            form.reset();
            updateLivePreview('');
        }
    }

    // Phóng to ảnh trong Modal
    function showImageModal(src, title) {
        const modalImg = document.getElementById('imageModalSrc');
        const modalTitle = document.getElementById('imageModalTitle');
        if (modalImg && modalTitle) {
            modalImg.src = src;
            modalTitle.innerText = title;
            const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
            modal.show();
        }
    }
</script>

<?php
require_once 'footer.php';
?>
