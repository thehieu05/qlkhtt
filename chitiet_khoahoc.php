<?php
require_once 'config.php';

// Lấy ID khóa học từ query string
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Truy vấn khóa học từ CSDL
$course = null;
if ($course_id > 0) {
    $res = $conn->query("SELECT * FROM courses WHERE id = $course_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $course = $res->fetch_assoc();
    }
}

// Nếu không tìm thấy khóa học theo ID, lấy khóa học đầu tiên làm mặc định
if (!$course) {
    $res_default = $conn->query("SELECT * FROM courses ORDER BY id ASC LIMIT 1");
    if ($res_default && $res_default->num_rows > 0) {
        $course = $res_default->fetch_assoc();
        $course_id = (int)$course['id'];
    }
}

// Nếu trong CSDL chưa có khóa học nào, tạo dữ liệu mẫu
if (!$course) {
    $course = [
        'id' => 1,
        'title' => 'Khóa Học Facebook Ads Chu Minh Hạnh – Level 1 Chuyên Sâu 2025',
        'category' => 'Khóa học Facebook',
        'level' => 'Level 1 Chuyên Sâu 2025',
        'price' => 149999,
        'old_price' => 6000000,
        'image_url' => 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=800&auto=format&fit=crop&q=80',
        'description' => 'Facebook Ads là nền tảng quảng cáo quyền năng giúp tiếp cận hàng triệu khách hàng mục tiêu một cách chính xác. Khóa học này được thiết kế chuyên sâu cho năm 2025, giúp bạn làm chủ từ tư duy mô hình kinh doanh đến kỹ thuật chạy quảng cáo thực chiến. Dù bạn là người mới bắt đầu hay đã từng chạy nhưng không hiệu quả, khóa học sẽ giúp bạn tối ưu chi phí, tăng tỷ lệ chuyển đổi và làm chủ các công cụ bổ trợ như AI, Capcut để bứt phá doanh thu.'
    ];
    $course_id = 1;
}

// Tính toán giá bán Flash Sale (-5% so với giá thường)
$regular_price = (float)($course['price'] > 0 ? $course['price'] : 149999);
$old_price     = (float)($course['old_price'] > 0 ? $course['old_price'] : 6000000);
$flash_price   = round($regular_price * 0.95);
if ($old_price > $flash_price) {
    $discount_percent = round((1 - ($flash_price / $old_price)) * 100);
    $savings = $old_price - $flash_price;
} else {
    $discount_percent = 50;
    $savings = 500000;
}

// Đếm số bài học và học viên thực tế
$lesson_count_res = $conn->query("SELECT COUNT(*) AS total FROM lessons WHERE course_id = $course_id");
$lesson_count_row = $lesson_count_res ? $lesson_count_res->fetch_assoc() : null;
$total_lessons = ($lesson_count_row && (int)$lesson_count_row['total'] > 0) ? (int)$lesson_count_row['total'] : 38;

$enroll_count_res = $conn->query("SELECT COUNT(*) AS total FROM enrollments WHERE course_id = $course_id");
$enroll_count_row = $enroll_count_res ? $enroll_count_res->fetch_assoc() : null;
$total_students = ($enroll_count_row && (int)$enroll_count_row['total'] > 0) ? (300 + (int)$enroll_count_row['total']) : 349;

// Lấy danh sách chương & bài học
$chapters_data = [];
$ch_query = $conn->query("SELECT * FROM chapters WHERE course_id = $course_id ORDER BY sort_order ASC, id ASC");
if ($ch_query && $ch_query->num_rows > 0) {
    while ($ch = $ch_query->fetch_assoc()) {
        $ch_id = (int)$ch['id'];
        $lessons_query = $conn->query("SELECT * FROM lessons WHERE chapter_id = $ch_id ORDER BY sort_order ASC, id ASC");
        $ch['lessons'] = [];
        if ($lessons_query && $lessons_query->num_rows > 0) {
            while ($ls = $lessons_query->fetch_assoc()) {
                $ch['lessons'][] = $ls;
            }
        }
        $chapters_data[] = $ch;
    }
}

// Nếu khóa học này chưa có chapters trong DB, tạo danh mục chuẩn đẹp mắt
if (empty($chapters_data)) {
    $chapters_data = [
        [
            'id' => 101,
            'title' => 'Chương 1: Tư duy nền tảng & Thiết lập môi trường thực chiến 2025',
            'lessons' => [
                ['id' => 1, 'title' => 'Bài 1: Giới thiệu tổng quan và lộ trình chinh phục', 'duration_minutes' => 15, 'is_free' => 1, 'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
                ['id' => 2, 'title' => 'Bài 2: Phân tích thuật toán & cập nhật chính sách mới', 'duration_minutes' => 24, 'is_free' => 0, 'video_url' => ''],
                ['id' => 3, 'title' => 'Bài 3: Chuẩn bị tài nguyên, tài khoản và công cụ cần thiết', 'duration_minutes' => 18, 'is_free' => 0, 'video_url' => ''],
            ]
        ],
        [
            'id' => 102,
            'title' => 'Chương 2: Nghiên cứu thị trường, tìm "Mồi Ngon" & Target chuẩn xác',
            'lessons' => [
                ['id' => 4, 'title' => 'Bài 4: Công thức tìm kiếm sản phẩm và thị trường ngách', 'duration_minutes' => 30, 'is_free' => 0, 'video_url' => ''],
                ['id' => 5, 'title' => 'Bài 5: Vẽ chân dung khách hàng mục tiêu & Hành vi mua hàng', 'duration_minutes' => 22, 'is_free' => 0, 'video_url' => ''],
                ['id' => 6, 'title' => 'Bài 6: Xây dựng tệp đối tượng tùy chỉnh (Custom & Lookalike)', 'duration_minutes' => 28, 'is_free' => 0, 'video_url' => ''],
            ]
        ],
        [
            'id' => 103,
            'title' => 'Chương 3: Sáng tạo Content chuyển đổi cao bằng AI & CapCut',
            'lessons' => [
                ['id' => 7, 'title' => 'Bài 7: Viết kịch bản video ngắn triệu view chạm cảm xúc', 'duration_minutes' => 25, 'is_free' => 0, 'video_url' => ''],
                ['id' => 8, 'title' => 'Bài 8: Biên tập video và hình ảnh bắt mắt bằng Capcut & Canva', 'duration_minutes' => 35, 'is_free' => 0, 'video_url' => ''],
                ['id' => 9, 'title' => 'Bài 9: Test và A/B testing tiêu đề, hook thu hút người xem', 'duration_minutes' => 20, 'is_free' => 0, 'video_url' => ''],
            ]
        ],
        [
            'id' => 104,
            'title' => 'Chương 4: Kỹ thuật Setup chiến dịch, Đo lường chỉ số & Tối ưu ngân sách',
            'lessons' => [
                ['id' => 10, 'title' => 'Bài 10: Quy trình lên camp mượt mà, hạn chế tối đa khóa nick', 'duration_minutes' => 32, 'is_free' => 0, 'video_url' => ''],
                ['id' => 11, 'title' => 'Bài 11: Đọc và phân tích chỉ số CTR, CPC, CPM, CPA chuyên nghiệp', 'duration_minutes' => 27, 'is_free' => 0, 'video_url' => ''],
                ['id' => 12, 'title' => 'Bài 12: Chiến thuật Scale ngân sách x3 x5 doanh thu an toàn', 'duration_minutes' => 40, 'is_free' => 0, 'video_url' => ''],
            ]
        ]
    ];
}

// Hình ảnh khóa học
$course_img = !empty($course['image_url']) ? $course['image_url'] : 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=800&auto=format&fit=crop&q=80';
$category_name = !empty($course['category']) ? $course['category'] : 'Khóa học Facebook';

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
                                                        <?php if (!empty($lesson['is_free'])): ?>
                                                            <span class="lesson-badge-free" data-bs-toggle="modal" data-bs-target="#trialModal">
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
                                        <?php else: ?>
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

                        <!-- Danh sách các nút hành động thiết kế chuẩn ảnh mẫu 100% -->
                        <div class="detail-actions-box">

                            <!-- Nút 1: Mua giá Flash màu đỏ nổi bật -->
                            <button type="button" class="btn btn-flash-buy" data-bs-toggle="modal" data-bs-target="#buyModal">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="main-text">⚡ Mua giá Flash — <?= number_format($flash_price, 0, ',', '.') ?> đ</span>
                                    <i class="bi bi-arrow-right fs-5"></i>
                                </div>
                                <span class="sub-text">Nhận link Google Drive ngay sau thanh toán</span>
                            </button>

                            <!-- Hàng 2 nút: Thêm vào giỏ + Trái tim Yêu thích -->
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary flex-grow-1 fw-bold py-2 rounded-3 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.88rem;" onclick="handleAddToCart('<?= htmlspecialchars(addslashes($course['title'])) ?>')">
                                    <i class="bi bi-cart3"></i> Thêm vào giỏ
                                </button>
                                <button type="button" class="btn btn-outline-secondary px-3 py-2 rounded-3" title="Thêm vào yêu thích" onclick="toggleFavorite(this)">
                                    <i class="bi bi-heart"></i>
                                </button>
                            </div>

                            <!-- Nút 3: Học thử miễn phí màu tím nhạt -->
                            <a href="javascript:void(0)" class="btn-trial-purple" data-bs-toggle="modal" data-bs-target="#trialModal">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-play-circle fs-4 text-purple"></i>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.92rem; color: #6d28d9;">Học thử miễn phí</div>
                                        <div style="font-size: 0.72rem; color: #7c3aed;">Trải nghiệm trước khi thanh toán</div>
                                    </div>
                                </div>
                                <i class="bi bi-box-arrow-up-right small text-purple"></i>
                            </a>

                            <!-- Nút 4: Vào học ngay màu xanh đen sang trọng -->
                            <a href="javascript:void(0)" class="btn-study-green" onclick="handleDirectAccess()">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-mortarboard fs-4 text-success-emphasis"></i>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.92rem;">Vào học ngay</div>
                                        <div style="font-size: 0.72rem; opacity: 0.85;">Mở khóa học trên Google Drive</div>
                                    </div>
                                </div>
                                <i class="bi bi-box-arrow-up-right small"></i>
                            </a>

                            <!-- Nút 5: Kiếm tiền từ khóa học này / Affiliate màu vàng -->
                            <a href="javascript:void(0)" class="btn-affiliate-gold" data-bs-toggle="modal" data-bs-target="#affiliateModal">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-share fs-4 text-warning-emphasis"></i>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.92rem; color: #854d0e;">Kiếm tiền từ khóa học này</div>
                                        <div style="font-size: 0.72rem; color: #a16207;">Nhận từ 30% hoa hồng khi có đơn thành công</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right small text-warning-emphasis"></i>
                            </a>

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
                        <h6 class="modal-title fw-bold text-dark mb-0">Học thử: Bài 1 - Giới thiệu & Lộ trình học 2025</h6>
                        <span class="text-muted small"><?= htmlspecialchars($course['title']) ?></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ratio ratio-16x9 bg-black">
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
                           value="http://<?= $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] ?>?id=<?= $course_id ?>&ref=<?= $_SESSION['user_id'] ?? 1088 ?>">
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

// Xử lý thêm vào giỏ hàng
function handleAddToCart(courseTitle) {
    showToast(`🛒 Đã thêm "<b>${courseTitle}</b>" vào giỏ hàng thành công!`, 'bg-success');
    const badge = document.querySelector('.cart-badge-count');
    if (badge) {
        let count = parseInt(badge.innerText || '0') + 1;
        badge.innerText = count;
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

// Vào học ngay
function handleDirectAccess() {
    <?php if (isset($_SESSION['user_id'])): ?>
        showToast('🎓 Đang chuyển hướng bạn đến thư mục Google Drive của khóa học...', 'bg-primary');
        setTimeout(() => {
            window.open('https://drive.google.com/', '_blank');
        }, 1000);
    <?php else: ?>
        showToast('Vui lòng đăng nhập để mở quyền truy cập khóa học!', 'bg-warning text-dark');
        setTimeout(() => {
            window.location.href = 'login.php';
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
</script>

<?php
require_once 'includes/footer.php';
?>

