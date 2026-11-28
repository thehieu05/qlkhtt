<?php
require_once 'config.php';

// Lấy ID khóa học từ query string
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Truy vấn khóa học từ CSDL (chỉ hiển thị khóa học đang công khai cho khách/học viên)
$course = null;
$status_cond = is_admin() ? "" : " AND c.status = 'published'";
if ($course_id > 0) {
    $stmt = $conn->prepare("SELECT c.*, cat.name AS category_name FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.id = ? $status_cond LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $course_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $course = $res->fetch_assoc();
        }
        $stmt->close();
    }
}

// Nếu không tìm thấy khóa học công khai theo ID, chuyển hướng về danh mục
if (!$course) {
    set_flash('Khóa học không tồn tại hoặc chưa được công khai.', 'warning');
    header("Location: danh_muc.php");
    exit();
}

// Tính toán giá bán
$regular_price = (float)($course['price'] > 0 ? $course['price'] : 0);
$old_price     = (float)($course['old_price'] > 0 ? $course['old_price'] : $regular_price);
$flash_price   = $regular_price;
if ($old_price > $flash_price && $old_price > 0) {
    $discount_percent = round((1 - ($flash_price / $old_price)) * 100);
    $savings = $old_price - $flash_price;
} else {
    $discount_percent = 0;
    $savings = 0;
}

// Đếm số bài học và học viên thực tế
$total_lessons = 0;
$stmt_lc = $conn->prepare("SELECT COUNT(*) AS total FROM lessons WHERE course_id = ?");
if ($stmt_lc) {
    $stmt_lc->bind_param('i', $course_id);
    $stmt_lc->execute();
    $total_lessons = (int)($stmt_lc->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_lc->close();
}

$total_students = 0;
$stmt_ec = $conn->prepare("SELECT COUNT(*) AS total FROM enrollments WHERE course_id = ? AND status = 'active'");
if ($stmt_ec) {
    $stmt_ec->bind_param('i', $course_id);
    $stmt_ec->execute();
    $total_students = (int)($stmt_ec->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_ec->close();
}

// Kiểm tra quyền học và trạng thái đơn hàng của người dùng hiện tại
$is_enrolled = is_logged_in() && has_course_access($conn, current_user_id(), $course_id);
$has_pending_order = false;
if (is_logged_in() && !$is_enrolled) {
    $uid = current_user_id();
    $stmt_po = $conn->prepare("SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = ? AND oi.course_id = ? AND o.status = 'pending' LIMIT 1");
    if ($stmt_po) {
        $stmt_po->bind_param('ii', $uid, $course_id);
        $stmt_po->execute();
        $has_pending_order = ($stmt_po->get_result()->num_rows > 0);
        $stmt_po->close();
    }
}

// Lấy danh sách chương (sections) & bài học (lessons)
$chapters_data = [];
$stmt_sec = $conn->prepare("SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
if ($stmt_sec) {
    $stmt_sec->bind_param('i', $course_id);
    $stmt_sec->execute();
    $sec_res = $stmt_sec->get_result();
    $stmt_ls = $conn->prepare("SELECT * FROM lessons WHERE section_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt_qz = $conn->prepare("SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count FROM quizzes q WHERE q.section_id = ? AND q.status = 'published' ORDER BY q.id ASC");
    while ($sec = $sec_res->fetch_assoc()) {
        $sec_id = (int)$sec['id'];
        $sec['lessons'] = [];
        $sec['quizzes'] = [];
        if ($stmt_ls) {
            $stmt_ls->bind_param('i', $sec_id);
            $stmt_ls->execute();
            $ls_res = $stmt_ls->get_result();
            while ($ls = $ls_res->fetch_assoc()) {
                $sec['lessons'][] = $ls;
            }
        }
        if ($stmt_qz) {
            $stmt_qz->bind_param('i', $sec_id);
            $stmt_qz->execute();
            $qz_res = $stmt_qz->get_result();
            while ($q = $qz_res->fetch_assoc()) {
                $sec['quizzes'][] = $q;
            }
        }
        $chapters_data[] = $sec;
    }
    if ($stmt_qz) $stmt_qz->close();
    if ($stmt_ls) $stmt_ls->close();
    $stmt_sec->close();
}

// Tìm bài học giới thiệu (is_preview = 1) đầu tiên
$first_free_lesson = null;
foreach ($chapters_data as $ch) {
    if (!empty($ch['lessons'])) {
        foreach ($ch['lessons'] as $ls) {
            if (!empty($ls['is_preview'])) {
                $first_free_lesson = $ls;
                break 2;
            }
        }
    }
}

// Hình ảnh khóa học & tên danh mục
$course_img = !empty($course['image_url']) ? $course['image_url'] : 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=800&auto=format&fit=crop&q=80';
$category_name = !empty($course['category_name']) ? $course['category_name'] : 'Khóa học';

require_once 'includes/header.php';
require_once 'includes/menu.php';
?>

<div class="course-detail-page py-4">
    <div class="container">

        <!-- 1. BREADCRUMB -->
        <nav aria-label="breadcrumb" class="detail-breadcrumb mb-3">
            <a href="index.php"><i class="bi bi-house-door me-1"></i>Trang chủ</a>
            <span class="mx-2 text-muted">›</span>
            <a href="index.php?q=<?= urlencode($category_name) ?>"><?= htmlspecialchars($category_name) ?></a>
            <span class="mx-2 text-muted">›</span>
            <span class="text-dark fw-semibold"><?= htmlspecialchars($course['title']) ?></span>
        </nav>

        <div class="row g-4">
            <!-- ======================================================== -->
            <!-- CỘT TRÁI (8 CỘT): HERO CARD + THÔNG TIN + MÔ TẢ + GIÁO TRÌNH -->
            <!-- ======================================================== -->
            <div class="col-lg-8">

                <!-- HERO CARD: DANH MỤC, TIÊU ĐỀ, ĐÁNH GIÁ & KHUNG 4 Ô THÔNG TIN -->
                <div class="detail-hero-box">
                    <!-- Category Pill -->
                    <span class="detail-cat-badge">
                        <?= htmlspecialchars($category_name) ?>
                    </span>

                    <!-- Course Title -->
                    <h1 class="detail-hero-title">
                        <?= htmlspecialchars($course['title']) ?>
                    </h1>

                    <!-- Rating Row -->
                    <div class="detail-rating-wrap">
                        <div class="detail-stars">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <span class="text-secondary small fw-semibold">
                            <i class="bi bi-fire text-danger me-1"></i>Được học viên quan tâm
                        </span>
                    </div>

                    <!-- Thông tin khóa học -->
                    <div class="detail-info-title">Thông tin khóa học</div>

                    <!-- Khung 4 ô thông tin thiết kế chuẩn ảnh mẫu -->
                    <div class="info-grid-container">
                        <div class="row g-0">
                            <!-- Ô 1: Danh mục -->
                            <div class="col-sm-6 border-end-sm border-bottom">
                                <div class="info-grid-cell">
                                    <div class="info-cell-icon">
                                        <i class="bi bi-grid-fill"></i>
                                    </div>
                                    <div>
                                        <div class="info-cell-label">Danh mục</div>
                                        <div class="info-cell-value"><?= htmlspecialchars($category_name) ?>, Marketing</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ô 2: Nội dung -->
                            <div class="col-sm-6 border-bottom">
                                <div class="info-grid-cell">
                                    <div class="info-cell-icon">
                                        <i class="bi bi-collection-play-fill"></i>
                                    </div>
                                    <div>
                                        <div class="info-cell-label">Nội dung</div>
                                        <div class="info-cell-value"><?= $total_lessons ?> Bài Giảng</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ô 3: Quyền truy cập -->
                            <div class="col-sm-6 border-end-sm border-bottom border-bottom-sm-0">
                                <div class="info-grid-cell">
                                    <div class="info-cell-icon">
                                        <i class="bi bi-patch-check-fill"></i>
                                    </div>
                                    <div>
                                        <div class="info-cell-label">Quyền truy cập</div>
                                        <div class="info-cell-value">Trọn đời</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ô 4: Tài nguyên -->
                            <div class="col-sm-6">
                                <div class="info-grid-cell">
                                    <div class="info-cell-icon">
                                        <i class="bi bi-folder2-open"></i>
                                    </div>
                                    <div>
                                        <div class="info-cell-label">Tài nguyên</div>
                                        <div class="info-cell-value">1 thư mục Google Drive</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Meta Tags hàng dưới -->
                    <div class="detail-meta-tags">
                        <div class="detail-meta-item">
                            <i class="bi bi-people-fill text-warning"></i>
                            <span><b><?= $total_students ?></b> học viên đã mua</span>
                        </div>
                        <div class="detail-meta-item text-success">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Đang sẵn sàng</span>
                        </div>
                        <div class="detail-meta-item text-secondary">
                            <i class="bi bi-shield-lock-fill text-warning"></i>
                            <span>Bảo hành link trọn đời</span>
                        </div>
                    </div>
                </div>

                <!-- PHẦN NỘI DUNG CHI TIẾT & MÔ TẢ -->
                <div class="detail-content-wrap">
                    <div class="detail-section-tag">NỘI DUNG CHI TIẾT</div>
                    <div class="detail-desc-heading">
                        <span class="detail-desc-icon"><i class="bi bi-folder-fill"></i></span>
                        Mô tả khóa học
                    </div>

                    <h5 class="fw-bold text-dark mb-3">
                        Chia Sẻ <?= htmlspecialchars($course['title']) ?>
                    </h5>

                    <div class="text-secondary leading-relaxed mb-4" style="line-height: 1.75; font-size: 0.96rem;">
                        <?= nl2br(htmlspecialchars($course['description'] ?? '')) ?>
                    </div>

                    <!-- Khung viền vàng nổi bật: Vì sao bạn nên học -->
                    <h5 class="fw-bold text-dark mt-4 mb-3">
                        Vì Sao Bạn Nên Học <?= htmlspecialchars($course['title']) ?>
                    </h5>

                    <div class="detail-highlight-box">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <i class="bi bi-check2-circle text-warning fs-5 flex-shrink-0 mt-1"></i>
                                <span>Cập nhật những thay đổi mới nhất về thuật toán và chính sách năm 2025.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <i class="bi bi-check2-circle text-warning fs-5 flex-shrink-0 mt-1"></i>
                                <span>Lộ trình học bài bản từ cấp độ cơ bản đến chuyên sâu, dễ hiểu, dễ áp dụng.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <i class="bi bi-check2-circle text-warning fs-5 flex-shrink-0 mt-1"></i>
                                <span>Nắm vững tư duy tìm "Mồi Ngon" và phương pháp Target chuẩn xác, tránh đốt tiền lãng phí.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <i class="bi bi-check2-circle text-warning fs-5 flex-shrink-0 mt-1"></i>
                                <span>Kết hợp kỹ năng quay dựng, edit video bằng Capcut và Canva để tạo Content thu hút khách hàng.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <i class="bi bi-check2-circle text-warning fs-5 flex-shrink-0 mt-1"></i>
                                <span>Hướng dẫn chi tiết cách đọc chỉ số, tối ưu ngân sách và quản lý tài sản an toàn bền vững.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Bạn sẽ học được gì -->
                    <h5 class="fw-bold text-dark mt-4 mb-3">
                        Bạn Sẽ Học Được Gì Từ Khóa Học Này
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="p-3 bg-white border rounded-3 h-100 shadow-sm d-flex align-items-start gap-2">
                                <i class="bi bi-patch-check-fill text-success fs-5 flex-shrink-0"></i>
                                <span class="small fw-semibold text-dark">Làm chủ công cụ quản lý quảng cáo & thiết lập chuyên nghiệp</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-white border rounded-3 h-100 shadow-sm d-flex align-items-start gap-2">
                                <i class="bi bi-patch-check-fill text-success fs-5 flex-shrink-0"></i>
                                <span class="small fw-semibold text-dark">Quy trình nghiên cứu sản phẩm và định vị thị trường ngách hiệu quả</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-white border rounded-3 h-100 shadow-sm d-flex align-items-start gap-2">
                                <i class="bi bi-patch-check-fill text-success fs-5 flex-shrink-0"></i>
                                <span class="small fw-semibold text-dark">Kỹ năng làm video ngắn triệu view và tối ưu chuyển đổi bằng AI</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-white border rounded-3 h-100 shadow-sm d-flex align-items-start gap-2">
                                <i class="bi bi-patch-check-fill text-success fs-5 flex-shrink-0"></i>
                                <span class="small fw-semibold text-dark">Kỹ thuật hạ giá thầu, tối ưu chi phí CPA và tăng tỷ lệ hoàn vốn ROI</span>
                            </div>
                        </div>
                    </div>

                    <!-- GIÁO TRÌNH / ACCORDION BÀI GIẢNG -->
                    <div class="d-flex align-items-center justify-content-between mt-5 mb-3">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-journal-text text-warning fs-4"></i>
                            Chương trình học chi tiết
                        </h5>
                        <span class="badge bg-light text-secondary border px-3 py-1.5 rounded-pill">
                            <?= count($chapters_data) ?> chương · <?= $total_lessons ?> bài học
                        </span>
                    </div>

                    <div class="accordion curriculum-accordion" id="courseAccordion">
                        <?php foreach ($chapters_data as $idx => $chapter): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-<?= $chapter['id'] ?>">
                                    <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $chapter['id'] ?>" aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                            <span><?= htmlspecialchars($chapter['title']) ?></span>
                                            <span class="badge bg-white text-dark border small fw-normal">
                                                <?= count($chapter['lessons']) ?> bài
                                            </span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse-<?= $chapter['id'] ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#courseAccordion">
                                    <div class="accordion-body p-0">
                                        <?php if (!empty($chapter['lessons'])): ?>
                                            <?php foreach ($chapter['lessons'] as $lesson): ?>
                                                <div class="curriculum-lesson-row">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <i class="bi bi-play-circle text-warning fs-5"></i>
                                                        <span class="text-dark fw-medium"><?= htmlspecialchars($lesson['title']) ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <?php if (!empty($lesson['is_preview'])): ?>
                                                            <span class="lesson-badge-free" style="cursor: pointer;" onclick='openTrialLesson(<?= htmlspecialchars(json_encode($lesson), ENT_QUOTES) ?>)'>
                                                                <i class="bi bi-play-fill"></i> Học thử miễn phí
                                                            </span>
                                                        <?php else: ?>
                                                            <i class="bi bi-lock text-muted" title="Bài học cần kích hoạt"></i>
                                                        <?php endif; ?>
                                                        <span class="text-muted small">
                                                            <?= !empty($lesson['duration_minutes']) ? $lesson['duration_minutes'] . ' phút' : '15 phút' ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($chapter['quizzes'])): ?>
                                            <?php foreach ($chapter['quizzes'] as $qz): ?>
                                                <div class="curriculum-lesson-row" style="background-color: rgba(99, 102, 241, 0.05); border-left: 3px solid #6366f1;">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <i class="bi bi-patch-question-fill text-primary fs-5"></i>
                                                        <div>
                                                            <span class="text-dark fw-bold"><?= htmlspecialchars($qz['title']) ?></span>
                                                            <span class="badge bg-primary-subtle text-primary ms-2">Trắc nghiệm chương</span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <span class="badge bg-white text-muted border small"><?= (int)$qz['question_count'] ?> câu</span>
                                                        <span class="text-muted small"><?= (int)$qz['duration_minutes'] ?> phút</span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if (empty($chapter['lessons']) && empty($chapter['quizzes'])): ?>
                                            <div class="p-3 text-muted small text-center">Nội dung bài học đang được cập nhật.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

            </div>

            <!-- ======================================================== -->
            <!-- CỘT PHẢI (4 CỘT): STICKY SIDEBAR CARD MUA HÀNG CHUẨN ẢNH MẪU -->
            <!-- ======================================================== -->
            <div class="col-lg-4">
                <div class="detail-sticky-wrap">
                    <div class="detail-sidebar-card">

                        <!-- Ảnh thumbnail khóa học -->
                        <div class="position-relative">
                            <img src="<?= htmlspecialchars($course_img) ?>" alt="<?= htmlspecialchars($course['title']) ?>" class="detail-sidebar-img">
                            <div class="position-absolute bottom-0 start-0 w-100 p-2" style="background: linear-gradient(to top, rgba(0,0,0,0.65), transparent);">
                                <span class="badge bg-warning text-dark fw-bold">
                                    <i class="bi bi-award-fill me-1"></i>Khóa học chất lượng cao
                                </span>
                            </div>
                        </div>

                        <!-- Thanh Flash Sale Strip màu đỏ nhạt -->
                        <div class="flash-sale-strip">
                            <div class="d-flex align-items-center gap-1.5">
                                <i class="bi bi-lightning-charge-fill"></i>
                                <span>Flash Sale -5%</span>
                            </div>
                            <div class="small font-monospace" id="detail-countdown">
                                Kết thúc 02:31:30
                            </div>
                        </div>

                        <!-- Khung giá bán & Tiết kiệm -->
                        <div class="detail-price-box">
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span class="detail-price-big">
                                    <?= number_format($flash_price, 0, ',', '.') ?> đ
                                </span>
                                <span class="detail-price-old">
                                    <?= number_format($old_price, 0, ',', '.') ?> đ
                                </span>
                                <span class="detail-discount-badge">
                                    -<?= $discount_percent ?>%
                                </span>
                            </div>
                            <div class="detail-price-note">
                                Giá thường <?= number_format($regular_price, 0, ',', '.') ?> đ · tiết kiệm <b class="text-danger"><?= number_format($savings, 0, ',', '.') ?> đ</b>
                            </div>
                        </div>

                        <div class="detail-actions-box">
                            <?php if ($is_enrolled): ?>
                                <a href="hoc.php?course_id=<?= $course_id ?>" class="btn-study-green">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-mortarboard fs-4 text-success-emphasis"></i>
                                        <div>
                                            <div class="fw-bold" style="font-size: 0.95rem;">Vào học ngay</div>
                                            <div style="font-size: 0.72rem; opacity: 0.85;">Bạn đã sở hữu khóa học này</div>
                                        </div>
                                    </div>
                                    <i class="bi bi-play-circle-fill small"></i>
                                </a>
                            <?php elseif ($has_pending_order): ?>
                                <a href="profile.php?tab=donhang" class="btn btn-warning w-100 fw-bold py-2 rounded-3 text-dark">
                                    <i class="bi bi-hourglass-split me-1"></i> Đơn hàng đang chờ xác nhận
                                </a>
                                <div class="text-muted small text-center">Quản trị viên sẽ xác nhận để cấp quyền học cho bạn.</div>
                            <?php elseif (is_logged_in()): ?>
                                <form method="POST" action="order_action.php">
                                    <input type="hidden" name="action" value="direct_order">
                                    <input type="hidden" name="course_id" value="<?= $course_id ?>">
                                    <button type="submit" class="btn btn-flash-buy w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="main-text">Đặt mua khóa học — <?= number_format($regular_price, 0, ',', '.') ?> đ</span>
                                            <i class="bi bi-arrow-right fs-5"></i>
                                        </div>
                                        <span class="sub-text">Tạo đơn hàng mô phỏng · Cấp quyền sau khi xác nhận</span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="login.php?redirect=<?= urlencode('chitiet_khoahoc.php?id=' . $course_id) ?>" class="btn btn-flash-buy w-100 text-decoration-none">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="main-text">Đăng nhập để đặt mua — <?= number_format($regular_price, 0, ',', '.') ?> đ</span>
                                        <i class="bi bi-box-arrow-in-right fs-5"></i>
                                    </div>
                                    <span class="sub-text">Yêu cầu đăng nhập tài khoản học viên</span>
                                </a>
                            <?php endif; ?>

                            <?php if ($first_free_lesson): ?>
                            <a href="javascript:void(0)" class="btn-trial-purple" onclick="openFirstTrialLesson()">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-play-circle fs-4 text-purple"></i>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.92rem; color: #6d28d9;">Xem bài học giới thiệu</div>
                                        <div style="font-size: 0.72rem; color: #7c3aed;">Trải nghiệm miễn phí trước khi đặt mua</div>
                                    </div>
                                </div>
                                <i class="bi bi-box-arrow-up-right small text-purple"></i>
                            </a>
                            <?php endif; ?>
                        </div>

                        <!-- Danh sách cam kết dịch vụ dưới cùng cột phải -->
                        <div class="guarantee-list">
                            <div class="guarantee-item">
                                <i class="bi bi-stars text-warning"></i>
                                <span>Nhận link Google Drive ngay sau khi xác nhận thanh toán</span>
                            </div>
                            <div class="guarantee-item">
                                <i class="bi bi-arrow-repeat text-primary"></i>
                                <span>Xem lại không giới hạn, sở hữu trọn đời</span>
                            </div>
                            <div class="guarantee-item">
                                <i class="bi bi-shield-check text-warning"></i>
                                <span>Bảo hành link trọn đời – lỗi được cấp lại miễn phí</span>
                            </div>
                            <div class="guarantee-item">
                                <i class="bi bi-chat-dots text-success"></i>
                                <span>Hỗ trợ qua Zalo mỗi ngày</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 1: MUA GIÁ FLASH / THANH TOÁN TỰ ĐỘNG QUA VIETQR -->
<!-- ======================================================== -->
<div class="modal fade" id="buyModal" tabindex="-1" aria-labelledby="buyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="buyModalLabel">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Xác Nhận Đăng Ký Khóa Học
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 text-center">
                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($course['title']) ?></h6>
                    <div class="text-danger fw-bold fs-4"><?= number_format($flash_price, 0, ',', '.') ?> đ</div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-shield-check me-1"></i>Đã áp dụng mã Flash Sale -5%
                    </span>
                </div>

                <div class="card bg-light border-0 rounded-3 p-3 text-center mb-3">
                    <div class="text-muted small mb-2">Quét mã VietQR để thanh toán tự động qua App Ngân hàng hoặc MoMo:</div>
                    <div class="d-flex justify-content-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode("2|99|0773308885|EDUNOVA|edunova@gmail.com|0|0|" . $flash_price . "|KHOAHOC_" . $course_id) ?>" 
                             alt="VietQR Code" 
                             class="img-fluid border rounded-3 p-1 bg-white shadow-sm" style="max-width: 170px;">
                    </div>
                    <div class="mt-2 small text-secondary">
                        Chủ tài khoản: <b>NGUYEN VAN EDUNOVA</b><br>
                        STK: <b class="text-dark font-monospace">0773 308 885</b> (MB Bank / MoMo)<br>
                        Nội dung CK: <b class="text-danger font-monospace">KH <?= $course_id ?> <?= !empty($_SESSION['username']) ? $_SESSION['username'] : 'VIP' ?></b>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success fw-bold py-2.5 rounded-3" onclick="confirmPayment()">
                        <i class="bi bi-check2-circle me-1"></i> Tôi đã chuyển khoản xong
                    </button>
                    <button type="button" class="btn btn-outline-secondary py-2 rounded-3" data-bs-dismiss="modal">
                        Đóng lại
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 2: HỌC THỬ MIỄN PHÍ VIDEO PREVIEW -->
<!-- ======================================================== -->
<div class="modal fade" id="trialModal" tabindex="-1" aria-labelledby="trialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-purple text-white p-2 rounded-3" style="background: #7c3aed;">
                        <i class="bi bi-play-circle-fill fs-6"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="trialModalTitle">Học thử: <?= htmlspecialchars($first_free_lesson['title'] ?? 'Bài 1 - Giới thiệu & Lộ trình học 2025') ?></h6>
                        <span class="text-muted small"><?= htmlspecialchars($course['title']) ?></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="stopTrialVideo()"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ratio ratio-16x9 bg-black" id="trialVideoContainer">
                    <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=0" title="Video học thử" allowfullscreen></iframe>
                </div>
                <div class="p-3 bg-light d-flex justify-content-between align-items-center">
                    <span class="small text-secondary">
                        <i class="bi bi-info-circle text-primary me-1"></i> Đây là bài học xem trước miễn phí giúp bạn đánh giá chất lượng khóa học.
                    </span>
                    <button type="button" class="btn btn-danger btn-sm fw-bold px-3 rounded-pill" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#buyModal">
                        Đăng ký trọn bộ ngay
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 3: AFFILIATE / KIẾM TIỀN TỪ KHÓA HỌC -->
<!-- ======================================================== -->
<div class="modal fade" id="affiliateModal" tabindex="-1" aria-labelledby="affiliateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 py-3 bg-warning-subtle text-warning-emphasis">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-share-fill me-1"></i> Chương Trình Cộng Tác Viên Affiliate
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary small mb-3">
                    Chia sẻ link giới thiệu khóa học này tới bạn bè, người theo dõi. Bạn sẽ nhận ngay <b>30% hoa hồng</b> (tương đương <b><?= number_format(round($flash_price * 0.3), 0, ',', '.') ?> đ</b>) cho mỗi đơn hàng được kích hoạt thành công!
                </p>

                <label class="form-label fw-bold small text-dark">Link giới thiệu của bạn:</label>
                <div class="input-group mb-3">
                    <input type="text" id="affiliateLinkInput" class="form-control" readonly 
                           value="http://<?= htmlspecialchars(($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['PHP_SELF'] ?? '')) ?>?id=<?= $course_id ?>&ref=<?= (int)($_SESSION['user_id'] ?? 1088) ?>">
                    <button class="btn btn-warning fw-bold px-3" type="button" onclick="copyAffiliateLink()">
                        <i class="bi bi-clipboard"></i> Sao chép
                    </button>
                </div>

                <div class="alert alert-light border small text-muted mb-0">
                    <i class="bi bi-cash-stack text-success fs-5 me-2 align-middle"></i>
                    Hoa hồng được cộng trực tiếp vào ví tài khoản của bạn và có thể rút về tài khoản ngân hàng bất cứ lúc nào.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TOAST THÔNG BÁO TƯƠNG TÁC -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
    <div id="actionToast" class="toast align-items-center text-white bg-dark border-0 rounded-3 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage">
                Thành công!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
// Live Flash Sale Countdown
let countdownSecs = 2 * 3600 + 31 * 60 + 30; // 02:31:30
setInterval(function() {
    if (countdownSecs > 0) {
        countdownSecs--;
        let h = Math.floor(countdownSecs / 3600);
        let m = Math.floor((countdownSecs % 3600) / 60);
        let s = countdownSecs % 60;
        let pad = (n) => n < 10 ? '0' + n : n;
        let str = `Kết thúc ${pad(h)}:${pad(m)}:${pad(s)}`;
        const el = document.getElementById('detail-countdown');
        if (el) el.innerText = str;
    }
}, 1000);

// Hiển thị thông báo Toast
function showToast(msg, bgClass = 'bg-dark') {
    const toastEl = document.getElementById('actionToast');
    const toastMsg = document.getElementById('toastMessage');
    if (toastEl && toastMsg) {
        toastEl.className = `toast align-items-center text-white ${bgClass} border-0 rounded-3 shadow`;
        toastMsg.innerHTML = msg;
        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
    }
}

// Yêu thích
function toggleFavorite(btn) {
    const icon = btn.querySelector('i');
    if (icon.classList.contains('bi-heart')) {
        icon.classList.remove('bi-heart');
        icon.classList.add('bi-heart-fill', 'text-danger');
        showToast('❤️ Đã thêm khóa học vào danh sách yêu thích!', 'bg-danger');
    } else {
        icon.classList.remove('bi-heart-fill', 'text-danger');
        icon.classList.add('bi-heart');
        showToast('Đã xóa khỏi danh sách yêu thích.');
    }
}

// Vào học ngay - chuyển hướng tới trang hoc.php
function handleDirectAccess() {
    <?php if (isset($_SESSION['user_id'])): ?>
        window.location.href = 'hoc.php?course_id=<?= $course_id ?>';
    <?php else: ?>
        showToast('Vui lòng đăng nhập để vào học!', 'bg-warning text-dark');
        setTimeout(() => {
            window.location.href = 'login.php?redirect=hoc.php?course_id=<?= $course_id ?>';
        }, 1200);
    <?php endif; ?>
}

// Xác nhận thanh toán
function confirmPayment() {
    const modalEl = document.getElementById('buyModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    
    showToast('🎉 Cảm ơn bạn! Đơn hàng đang được hệ thống xác nhận tự động.', 'bg-success');
    setTimeout(() => {
        window.location.href = 'profile.php?tab=donhang';
    }, 1500);
}

// Sao chép link Affiliate
function copyAffiliateLink() {
    const input = document.getElementById('affiliateLinkInput');
    if (input) {
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            showToast('📋 Đã sao chép link Affiliate vào bộ nhớ tạm!', 'bg-primary');
        }).catch(() => {
            document.execCommand('copy');
            showToast('📋 Đã sao chép link!', 'bg-primary');
        });
    }
}

// Xử lý học thử miễn phí
var defaultFirstFreeLesson = <?= json_encode($first_free_lesson) ?>;

function openFirstTrialLesson() {
    if (defaultFirstFreeLesson) {
        openTrialLesson(defaultFirstFreeLesson);
    } else {
        openTrialLesson({
            title: 'Bài 1: Giới thiệu & Lộ trình học 2025',
            video_url: 'https://www.youtube.com/embed/dQw4w9WgXcQ'
        });
    }
}

function openTrialLesson(ls) {
    if (!ls) return;
    var titleEl = document.getElementById('trialModalTitle');
    if (titleEl) titleEl.innerText = 'Học thử: ' + (ls.title || 'Bài học xem trước');

    var container = document.getElementById('trialVideoContainer');
    var rawUrl = (ls.video_url || '').trim();

    if (!rawUrl) {
        rawUrl = 'https://www.youtube.com/embed/dQw4w9WgXcQ';
    }

    if (rawUrl.startsWith('uploads/') || rawUrl.endsWith('.mp4') || rawUrl.endsWith('.webm') || rawUrl.endsWith('.ogg')) {
        container.innerHTML = '<video controls autoplay style="width:100%; height:100%; object-fit:contain; background:#000;"><source src="' + rawUrl + '" type="video/mp4">Trình duyệt không hỗ trợ xem video.</video>';
    } else {
        var embedUrl = rawUrl;
        if (embedUrl.includes('watch?v=')) {
            embedUrl = embedUrl.replace('watch?v=', 'embed/');
        }
        container.innerHTML = '<iframe src="' + embedUrl + '" title="Video học thử" allowfullscreen style="width:100%; height:100%; border:0;"></iframe>';
    }

    new bootstrap.Modal(document.getElementById('trialModal')).show();
}

function stopTrialVideo() {
    var container = document.getElementById('trialVideoContainer');
    if (container) container.innerHTML = '';
}

document.getElementById('trialModal')?.addEventListener('hidden.bs.modal', function () {
    stopTrialVideo();
});
</script>

<?php
require_once 'includes/footer.php';
?>

