<?php
require_once 'includes/header.php';
require_once 'includes/menu.php';
$where_sql = "";
if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $where_sql = "WHERE title LIKE '%$safe_search%' OR description LIKE '%$safe_search%'";
}
$sql_courses = "SELECT * FROM courses $where_sql ORDER BY id DESC";
$result_courses = $conn->query($sql_courses);
$courses_list = [];
if ($result_courses && $result_courses->num_rows > 0) {
    while ($row = $result_courses->fetch_assoc()) {
        $courses_list[] = $row;
    }
}
$sample_tags = ['Công nghệ thông tin', 'Combo Khóa Học', 'Marketing', 'Trí tuệ nhân tạo', 'Ngoại ngữ', 'Thiết kế đồ họa'];
?>
<div class="container pb-5">
    <?php
    if ($search === '') {
        require_once 'includes/banner.php';
    }
    ?>
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
                    $course_url = "chitiet_khoahoc.php?id=" . $course['id'];
                    $c_price = (float)($course['price'] > 0 ? $course['price'] : 149999);
                    $c_flash = round($c_price * 0.95);
                    $c_old = (float)($course['old_price'] > 0 ? $course['old_price'] : 1500000);
            ?>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="course-card border-0 shadow-sm">
                    <div class="course-img-wrap">
                        <span class="discount-badge">-5% FLASH</span>
                        <a href="<?= $course_url ?>">
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                        </a>
                    </div>
                    <div class="card-body d-flex flex-column p-3">
                        <span class="text-primary fw-semibold small mb-1"><?= $tag ?></span>
                        <a href="<?= $course_url ?>" class="course-title mb-2"><?= htmlspecialchars($course['title']) ?></a>
                        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                            <div>
                                <div class="price-new"><?= number_format($c_flash, 0, ',', '.') ?> ₫</div>
                                <div class="price-old"><?= number_format($c_old, 0, ',', '.') ?> ₫</div>
                            </div>
                            <a href="<?= $course_url ?>" class="btn btn-danger btn-sm fw-semibold px-3 rounded-pill">
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
                <a href="#khoa-hoc" class="btn btn-outline-primary btn-sm fw-semibold">Xem tất cả <i class="bi bi-arrow-down"></i></a>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if (!empty($courses_list)): ?>
                <?php foreach ($courses_list as $index => $row): 
                    $tag = $sample_tags[$index % count($sample_tags)];
                    $img = !empty($row['image_url']) ? $row['image_url'] : 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600';
                    $row_url = "chitiet_khoahoc.php?id=" . $row['id'];
                    $r_price = (float)($row['price'] > 0 ? $row['price'] : 149999);
                    $r_old = (float)($row['old_price'] > 0 ? $row['old_price'] : 2500000);
                ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="course-card">
                        <div class="course-img-wrap">
                            <span class="discount-badge bg-primary">HOT</span>
                            <a href="<?= $row_url ?>">
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                            </a>
                        </div>
                        <div class="card-body d-flex flex-column p-3">
                            <span class="text-muted small fw-semibold mb-1"><i class="bi bi-bookmark-star text-primary me-1"></i><?= $tag ?></span>
                            <a href="<?= $row_url ?>" class="course-title mb-1"><?= htmlspecialchars($row['title']) ?></a>
                            <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= htmlspecialchars($row['description']) ?>
                            </p>
                            <div class="mt-auto pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-baseline mb-2">
                                    <span class="price-new"><?= number_format($r_price, 0, ',', '.') ?> ₫</span>
                                    <span class="price-old"><?= number_format($r_old, 0, ',', '.') ?> ₫</span>
                                </div>
                                <div class="d-grid gap-2 d-flex">
                                    <a href="<?= $row_url ?>" class="btn btn-outline-primary btn-sm flex-grow-1 fw-semibold">Xem chi tiết</a>
                                    <a href="<?= $row_url ?>" class="btn btn-primary btn-sm fw-semibold"><i class="bi bi-cart-plus"></i> Học ngay</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning text-center py-4">
                        Không tìm thấy khóa học nào phù hợp. Hãy vào mục <a href="admin/ql_khoahoc.php" class="fw-bold">Quản lý Khóa học</a> để thêm khóa học mới!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
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
    <?php
    if ($search === '') {
        require_once 'includes/feedback_section.php';
    }
    ?>
<?php
require_once 'includes/footer.php';
?>