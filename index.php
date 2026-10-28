<?php
// 1. Nhúng Header và Menu
require_once 'header.php';
require_once 'menu.php';

// 2. Truy vấn danh sách khóa học từ bảng courses (có lọc theo từ khóa tìm kiếm nếu có)
$where_sql = "";
if ($search !== '') {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $where_sql = "WHERE title LIKE '%$safe_search%' OR description LIKE '%$safe_search%'";
}

$sql_courses = "SELECT * FROM courses $where_sql ORDER BY id DESC";
$result_courses = mysqli_query($conn, $sql_courses);
$courses_list = [];
if ($result_courses) {
    while ($row = mysqli_fetch_assoc($result_courses)) {
        $courses_list[] = $row;
    }
}

$sample_tags = ['Công nghệ thông tin', 'Combo Khóa Học', 'Marketing', 'Trí tuệ nhân tạo', 'Ngoại ngữ', 'Thiết kế đồ họa'];
?>

<!-- Mở container chính (sẽ được đóng ở trong footer.php) -->
<div class="container pb-5">

    <?php
    // 3. Nhúng Banner và Danh mục (Chỉ hiển thị khi người dùng không gõ tìm kiếm)
    if ($search === '') {
        require_once 'banner.php';
    }
    ?>

    <!-- 4. KHUNG FLASH SALE THEO KHUNG GIỜ -->
    <?php if ($search === ''): ?>
    <div id="flash-sale" class="flash-sale-box mt-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h3 class="fw-bold text-danger mb-0"><i class="bi bi-lightning-charge-fill"></i> FLASH SALE Theo khung giờ</h3>
                    <span class="badge bg-danger rounded-pill">Đang sale</span>
                </div>
                <p class="text-secondary small mb-0 mt-1">Giảm thêm 5–10% · Áp giá ưu đãi trực tiếp khi đăng ký hôm nay</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold text-dark small">Kết thúc sau:</span>
                <span class="timer-badge" id="countdown-timer">02:19:16</span>
            </div>
        </div>

        <div class="row g-3">
            <?php 
            $flash_courses = array_slice($courses_list, 0, 4);
            if (!empty($flash_courses)):
                foreach ($flash_courses as $idx => $course): 
                    $tag = $sample_tags[$idx % count($sample_tags)];
                    $img = !empty($course['image_url']) ? $course['image_url'] : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600';
            ?>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="course-card border-0 shadow-sm">
                    <div class="course-img-wrap">
                        <span class="discount-badge">-10% FLASH</span>
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                    </div>
                    <div class="card-body d-flex flex-column p-3">
                        <span class="text-primary fw-semibold small mb-1"><?= $tag ?></span>
                        <a href="ql_khoahoc.php" class="course-title mb-2"><?= htmlspecialchars($course['title']) ?></a>
                        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                            <div>
                                <div class="price-new">89.000 ₫</div>
                                <div class="price-old">1.500.000 ₫</div>
                            </div>
                            <a href="ql_khoahoc.php" class="btn btn-danger btn-sm fw-semibold px-3 rounded-pill">
                                Mua giá Flash
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
                <div class="col-12 text-center py-3 text-muted">Chưa có khóa học nào trong cơ sở dữ liệu.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 5. DANH SÁCH KHÓA HỌC TRONG CSDL (BÁN CHẠY / MỚI CẬP NHẬT) -->
    <div id="khoa-hoc" class="mt-5 pt-2">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="text-primary fw-bold small text-uppercase">Bán chạy & Mới cập nhật</span>
                <h3 class="fw-bold mb-1">
                    <?= $search !== '' ? 'Kết quả tìm kiếm cho: "' . htmlspecialchars($search) . '"' : 'Khóa học được quan tâm nhiều nhất' ?>
                </h3>
                <p class="text-muted mb-0 small">Danh sách khóa học lấy trực tiếp từ CSDL MySQL</p>
            </div>
            <?php if ($search !== ''): ?>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Xóa bộ lọc</a>
            <?php else: ?>
                <a href="ql_khoahoc.php" class="btn btn-outline-primary btn-sm fw-semibold">Xem tất cả <i class="bi bi-arrow-right"></i></a>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if (!empty($courses_list)): ?>
                <?php foreach ($courses_list as $index => $row): 
                    $tag = $sample_tags[$index % count($sample_tags)];
                    $img = !empty($row['image_url']) ? $row['image_url'] : 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600';
                ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="course-card">
                        <div class="course-img-wrap">
                            <span class="discount-badge bg-primary">HOT</span>
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                        </div>
                        <div class="card-body d-flex flex-column p-3">
                            <span class="text-muted small fw-semibold mb-1"><i class="bi bi-bookmark-star text-primary me-1"></i><?= $tag ?></span>
                            <a href="ql_khoahoc.php" class="course-title mb-1"><?= htmlspecialchars($row['title']) ?></a>
                            <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= htmlspecialchars($row['description']) ?>
                            </p>
                            <div class="mt-auto pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-baseline mb-2">
                                    <span class="price-new">99.000 ₫</span>
                                    <span class="price-old">2.500.000 ₫</span>
                                </div>
                                <div class="d-grid gap-2 d-flex">
                                    <a href="ql_khoahoc.php" class="btn btn-outline-primary btn-sm flex-grow-1 fw-semibold">Xem chi tiết</a>
                                    <a href="ql_khoahoc.php" class="btn btn-primary btn-sm fw-semibold"><i class="bi bi-cart-plus"></i> Học ngay</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning text-center py-4">
                        Không tìm thấy khóa học nào phù hợp. Hãy vào mục <a href="ql_khoahoc.php" class="fw-bold">Quản lý Khóa học</a> để thêm khóa học mới!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6. ĐẶC QUYỀN HỘI VIÊN (BẢNG GIÁ) -->
    <div id="goi-hoi-vien" class="mt-5 pt-4">
        <div class="text-center mb-4">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold mb-2">ĐẶC QUYỀN HỘI VIÊN</span>
            <h3 class="fw-bold">Học đều mỗi ngày, tiết kiệm hơn mỗi khóa</h3>
            <p class="text-muted small">Chủ động nhận những khóa học phù hợp với mục tiêu của bạn. Khóa đã nhận được lưu trong tài khoản để học trọn đời.</p>
        </div>

        <div class="row g-4 justify-content-center">
            <div class="col-md-4">
                <div class="pricing-card p-4 h-100 d-flex flex-column">
                    <span class="badge bg-light text-secondary border align-self-start mb-2">GÓI 01 · TRẢI NGHIỆM</span>
                    <h5 class="fw-bold">Hội viên Start</h5>
                    <p class="text-muted small">Phù hợp để bắt đầu học đều đặn với chi phí thấp.</p>
                    <div class="my-3">
                        <span class="fs-3 fw-bold text-dark">99.000 ₫</span>
                        <span class="text-muted small">/ tháng</span>
                    </div>
                    <ul class="list-unstyled small text-secondary mb-4 flex-grow-1">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Mỗi ngày nhận <b>1 khóa học</b></li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Giá khóa tối đa 50.000 ₫</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Học trọn đời các khóa đã nhận</li>
                    </ul>
                    <a href="login.php" class="btn btn-outline-primary w-100 fw-semibold">Đăng ký gói Start</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="pricing-card popular p-4 h-100 d-flex flex-column shadow">
                    <span class="badge bg-primary align-self-start mb-2">TRỌN BỘ WEBSITE · TIẾT KIỆM NHẤT</span>
                    <h5 class="fw-bold text-primary">Trọn bộ 2.000+ khóa học</h5>
                    <p class="text-muted small">Truy cập toàn bộ kho khóa học trên website và nhận nội dung mới liên tục.</p>
                    <div class="my-3">
                        <span class="fs-3 fw-bold text-danger">599.999 ₫</span>
                        <span class="text-muted small">/ Trọn đời</span>
                    </div>
                    <ul class="list-unstyled small text-dark mb-4 flex-grow-1">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i><b>Quyền truy cập toàn bộ website</b></li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Thời hạn sử dụng: <b>Vĩnh viễn</b></li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Cập nhật khóa học mới miễn phí</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Hỗ trợ Zalo ưu tiên 24/7</li>
                    </ul>
                    <a href="login.php" class="btn btn-primary w-100 fw-semibold">Nhận gói 599K ngay</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="pricing-card p-4 h-100 d-flex flex-column">
                    <span class="badge bg-warning-subtle text-warning-emphasis border align-self-start mb-2">GÓI 02 · PHỔ BIẾN</span>
                    <h5 class="fw-bold">Hội viên VIP</h5>
                    <p class="text-muted small">Quyền lựa chọn rộng nhất trong 360 ngày, bao gồm cả các khóa Combo.</p>
                    <div class="my-3">
                        <span class="fs-3 fw-bold text-dark">490.000 ₫</span>
                        <span class="text-muted small">/ năm</span>
                    </div>
                    <ul class="list-unstyled small text-secondary mb-4 flex-grow-1">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Mỗi ngày nhận <b>5 khóa học</b></li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Bao gồm danh mục <b>Combo Khóa Học</b></li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Làm bài thi trắc nghiệm không giới hạn</li>
                    </ul>
                    <a href="login.php" class="btn btn-outline-primary w-100 fw-semibold">Xem quyền lợi VIP</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. PHẢN HỒI THẬT TỪ KHÁCH HÀNG (MOCKUP TIN NHẮN & BILL CHUYỂN KHOẢN) -->
    <?php
    if ($search === '') {
        require_once 'feedback_section.php';
    }
    ?>

<?php
// 8. Nhúng Footer (Đóng container, hiển thị Cam kết dịch vụ & Chân trang)
require_once 'footer.php';
?>