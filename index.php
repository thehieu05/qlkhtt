<?php
require_once 'includes/header.php';
require_once 'includes/menu.php';

$courses_list = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT c.*, cat.name AS category_name
                            FROM courses c
                            LEFT JOIN categories cat ON c.category_id = cat.id
                            WHERE c.status = 'published' AND (c.title LIKE ? OR c.description LIKE ?)
                            ORDER BY c.id DESC");
    if ($stmt) {
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $courses_list[] = $row;
        }
        $stmt->close();
    }
} else {
    $sql_courses = "SELECT c.*, cat.name AS category_name
                    FROM courses c
                    LEFT JOIN categories cat ON c.category_id = cat.id
                    WHERE c.status = 'published'
                    ORDER BY c.id DESC";
    $result_courses = $conn->query($sql_courses);
    if ($result_courses && $result_courses->num_rows > 0) {
        while ($row = $result_courses->fetch_assoc()) {
            $courses_list[] = $row;
        }
    }
}
?>
<div class="container pb-5">
    <?php
    if ($search === '') {
        require_once 'includes/banner.php';
    }
    ?>
    <?php if ($search === ''):
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        // Xác định khung giờ Flash Sale hiện tại (09:00, 12:00, 15:00, 18:00, 21:00)
        $fs_slots = [
            ['hour' => 9,  'label' => '09:00'],
            ['hour' => 12, 'label' => '12:00'],
            ['hour' => 15, 'label' => '15:00'],
            ['hour' => 18, 'label' => '18:00'],
            ['hour' => 21, 'label' => '21:00'],
        ];
        $now_hour = (int)date('G');
        $active_slot_hour = null;
        foreach ($fs_slots as $s) {
            if ($now_hour >= $s['hour'] && $now_hour < $s['hour'] + 3) {
                $active_slot_hour = $s['hour'];
                break;
            }
        }
        $flash_discounts = [9, 8, 6, 10, 7, 7, 6, 10];
    ?>
    <section id="flash-sale" class="fs-section-wrap mt-5">
        <!-- HEADER GRADIENT ĐỎ - CAM -->
        <div class="fs-header-bar">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-icon-box">
                    <i class="bi bi-lightning-fill"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h3 class="fs-header-title mb-0">FLASH SALE</h3>
                        <span class="fs-pill-tag">THEO KHUNG GIỜ</span>
                    </div>
                    <p class="fs-header-sub mb-0">Giảm thêm 5–10% · áp giá thật lúc thanh toán</p>
                </div>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mt-md-0">
                <span class="fs-countdown-label">KẾT THÚC SAU</span>
                <div class="fs-timer-boxes" data-end-hour="<?= $active_slot_hour + 3 ?>">
                    <span class="fs-timer-box" id="fsHours">01</span>
                    <span class="fs-timer-sep">:</span>
                    <span class="fs-timer-box" id="fsMinutes">27</span>
                    <span class="fs-timer-sep">:</span>
                    <span class="fs-timer-box" id="fsSeconds">25</span>
                </div>
                <span class="fs-status-pill" id="fsHeaderStatusPill">
                    <span class="fs-status-dot"></span> ĐANG SALE
                </span>
            </div>
        </div>

        <!-- THANH 5 KHUNG GIỜ -->
        <div class="fs-slots-bar">
            <?php foreach ($fs_slots as $slot):
                $s_hour = $slot['hour'];
                if ($active_slot_hour === null) {
                    $slot_state = ($now_hour < $s_hour) ? 'upcoming' : 'past';
                } elseif ($s_hour < $active_slot_hour) {
                    $slot_state = 'past';
                } elseif ($s_hour === $active_slot_hour) {
                    $slot_state = 'active';
                } else {
                    $slot_state = 'upcoming';
                }
                $slot_sub = $slot_state === 'active' ? 'ĐANG SALE' : ($slot_state === 'past' ? 'ĐÃ QUA' : 'SẮP MỞ');
            ?>
            <button type="button"
                    class="fs-slot-tab <?= $slot_state === 'active' ? 'is-active' : '' ?> state-<?= $slot_state ?>"
                    data-slot-hour="<?= $s_hour ?>"
                    data-slot-state="<?= $slot_state ?>"
                    data-slot-label="<?= $slot['label'] ?>"
                    <?= $slot_state !== 'active' ? 'disabled aria-disabled="true"' : '' ?>
                    onclick="selectFlashSlot(this)">
                <div class="fs-slot-time"><?= $slot['label'] ?></div>
                <div class="fs-slot-status"><?= $slot_sub ?></div>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- LƯỚI KHÓA HỌC FLASH SALE (TỐI ĐA 8 KHÓA) -->
        <div class="fs-cards-body">
            <div class="row g-3">
                <?php
                $flash_courses = array_slice($courses_list, 0, 8);
                if (!empty($flash_courses)):
                    foreach ($flash_courses as $idx => $course):
                        $tag = !empty($course['category_name']) ? $course['category_name'] : 'Khóa học';
                        $img = !empty($course['image_url']) ? $course['image_url'] : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600';
                        $course_url = "chitiet_khoahoc.php?id=" . (int)$course['id'];
                        $disc_pct = $flash_discounts[$idx % count($flash_discounts)];
                        $c_price = (float)($course['price'] > 0 ? $course['price'] : 149999);
                        $c_flash = round($c_price * (1 - $disc_pct / 100));
                        $c_old = (float)($course['old_price'] > 0 ? $course['old_price'] : 1500000);
                ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="fs-course-card">
                        <div class="fs-card-img-wrap">
                            <span class="fs-discount-pill">-<?= $disc_pct ?>%</span>
                            <button type="button" class="fs-fav-btn" onclick="toggleFlashFav(this)" title="Yêu thích">
                                <i class="bi bi-heart"></i>
                            </button>
                            <a href="<?= $course_url ?>" class="d-block h-100">
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                            </a>
                        </div>
                        <div class="fs-card-body">
                            <div class="fs-card-cat"><?= htmlspecialchars(mb_strtoupper($tag, 'UTF-8')) ?></div>
                            <a href="<?= $course_url ?>" class="fs-card-title"><?= htmlspecialchars($course['title']) ?></a>
                            <div class="fs-card-price-row">
                                <span class="fs-price-new"><?= number_format($c_flash, 0, ',', '.') ?> ₫</span>
                                <span class="fs-price-old"><?= number_format($c_old, 0, ',', '.') ?> ₫</span>
                            </div>
                            <a href="<?= $course_url ?>" class="fs-buy-btn">
                                <span>Mua giá Flash</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                    <div class="col-12 text-center py-4 text-muted">Chưa có khóa học nào trong cơ sở dữ liệu.</div>
                <?php endif; ?>
            </div>

            <div class="fs-footer-note">
                Giá Flash chỉ hiệu lực trong khung đang diễn ra và được tính lại lúc tạo đơn.
            </div>
        </div>
    </section>
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
                <a href="danh_muc.php" class="btn btn-outline-primary btn-sm fw-semibold">Xem tất cả <i class="bi bi-arrow-right"></i></a>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if (!empty($courses_list)): ?>
                <?php foreach ($courses_list as $index => $row): 
                    $tag = !empty($row['category_name']) ? $row['category_name'] : 'Khóa học';
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
                                <a href="<?= $row_url ?>" class="btn btn-primary w-100 fw-bold py-2 rounded-3 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.84rem;">
                                    <span>Xem chi tiết & Đặt mua</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
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
                    <button type="button" onclick="addMembershipToCart('start')" class="btn btn-outline-primary w-100 fw-semibold">Đăng ký gói Start</button>
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
                    <button type="button" onclick="addMembershipToCart('lifetime')" class="btn btn-primary w-100 fw-semibold">Nhận gói 599K ngay</button>
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
                    <button type="button" onclick="addMembershipToCart('vip')" class="btn btn-outline-primary w-100 fw-semibold">Đăng ký gói VIP</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    if ($search === '') {
        require_once 'includes/feedback_section.php';

        // Chuẩn bị dữ liệu cho 3 section sau phần phản hồi
        $combo_courses = array_values(array_filter($courses_list, function ($c) {
            $haystack = mb_strtolower(($c['category'] ?? '') . ' ' . ($c['title'] ?? ''), 'UTF-8');
            return strpos($haystack, 'combo') !== false || strpos($haystack, 'trọn bộ') !== false;
        }));
        if (count($combo_courses) < 8) {
            $existing_ids = array_column($combo_courses, 'id');
            foreach ($courses_list as $c) {
                if (!in_array($c['id'], $existing_ids, true)) {
                    $combo_courses[] = $c;
                }
                if (count($combo_courses) >= 8) break;
            }
        }

        $ai_courses = array_values(array_filter($courses_list, function ($c) {
            $haystack = mb_strtolower(($c['category'] ?? '') . ' ' . ($c['title'] ?? ''), 'UTF-8');
            foreach (['ai', 'coding', 'lập trình', 'công nghệ', 'web', 'claude', 'python', 'javascript'] as $kw) {
                if (strpos($haystack, $kw) !== false) return true;
            }
            return false;
        }));
        if (count($ai_courses) < 8) {
            $existing_ids = array_column($ai_courses, 'id');
            foreach ($courses_list as $c) {
                if (!in_array($c['id'], $existing_ids, true)) {
                    $ai_courses[] = $c;
                }
                if (count($ai_courses) >= 8) break;
            }
        }

        $home_sections = [
            [
                'id'       => 'moi-cap-nhat',
                'eyebrow'  => 'MỚI CẬP NHẬT',
                'title'    => 'Khóa học mới dành cho bạn',
                'subtitle' => 'Nội dung mới được bổ sung và cập nhật thường xuyên',
                'link'     => 'danh_muc.php?sort=newest',
                'items'    => array_slice($courses_list, 0, 8),
            ],
            [
                'id'       => 'combo-gia-re',
                'eyebrow'  => 'KHÁM PHÁ THEO CHỦ ĐỀ',
                'title'    => 'Combo Khóa Học - Giá Siêu Rẻ',
                'subtitle' => 'Giải pháp học tập toàn diện với mức giá tiết kiệm nhất',
                'link'     => 'danh_muc.php?cat=' . urlencode('Combo Khóa Học'),
                'items'    => array_slice($combo_courses, 0, 8),
            ],
            [
                'id'       => 'vibe-ai-coding',
                'eyebrow'  => 'KHÁM PHÁ THEO CHỦ ĐỀ',
                'title'    => 'Vibe Coding & AI Coding',
                'subtitle' => 'Học cách xây dựng Website, App, AI Agent và phần mềm thực tế bằng các công cụ AI hiện đại',
                'link'     => 'danh_muc.php?cat=' . urlencode('Công nghệ thông tin'),
                'items'    => array_slice($ai_courses, 0, 8),
            ],
        ];

        foreach ($home_sections as $sec):
    ?>
    <section id="<?= $sec['id'] ?>" class="home-curated-section mt-5 pt-3">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <div class="home-curated-eyebrow">
                    <span class="eyebrow-dash"></span><?= htmlspecialchars($sec['eyebrow']) ?>
                </div>
                <h3 class="home-curated-title mb-1"><?= htmlspecialchars($sec['title']) ?></h3>
                <p class="text-muted small mb-0"><?= htmlspecialchars($sec['subtitle']) ?></p>
            </div>
            <a href="<?= $sec['link'] ?>" class="home-curated-seeall">
                Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-3">
            <?php if (!empty($sec['items'])): ?>
                <?php foreach ($sec['items'] as $idx => $item):
                    $c_id    = (int)$item['id'];
                    $c_cat   = !empty($item['category_name']) ? $item['category_name'] : (!empty($item['category']) ? $item['category'] : 'Khóa học');
                    $c_img   = !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600';
                    $c_price = (float)($item['price'] > 0 ? $item['price'] : 149999);
                    $c_old   = (float)($item['old_price'] > 0 ? $item['old_price'] : 2500000);
                    $c_url   = 'chitiet_khoahoc.php?id=' . $c_id;
                ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="catalog-course-card">
                        <a href="<?= $c_url ?>" class="catalog-card-thumb">
                            <img src="<?= htmlspecialchars($c_img) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                        </a>
                        <div class="catalog-card-body">
                            <div class="catalog-card-cat"><?= htmlspecialchars(mb_strtoupper($c_cat, 'UTF-8')) ?></div>
                            <a href="<?= $c_url ?>" class="catalog-card-title"><?= htmlspecialchars($item['title']) ?></a>

                            <div class="catalog-card-prices">
                                <span class="catalog-price-new"><?= number_format($c_price, 0, ',', '.') ?> ₫</span>
                                <?php if ($c_old > $c_price): ?>
                                    <span class="catalog-price-old"><?= number_format($c_old, 0, ',', '.') ?> ₫</span>
                                <?php endif; ?>
                            </div>

                            <a href="<?= $c_url ?>" class="catalog-detail-link">Xem chi tiết →</a>

                            <button type="button" class="btn btn-primary w-100 fw-bold py-2 rounded-3 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.84rem;" onclick="addCourseToCart(<?= $c_id ?>)">
                                <i class="bi bi-cart3"></i>
                                <span>Thêm vào giỏ</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-4 text-muted small">Chưa có khóa học nào trong mục này.</div>
            <?php endif; ?>
        </div>
    </section>
    <?php
        endforeach;
    }
    ?>
<?php
require_once 'includes/footer.php';
?>