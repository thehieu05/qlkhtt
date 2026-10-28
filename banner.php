<?php
// Dữ liệu Danh mục "Học theo mục tiêu"
$categories = [
    ['icon' => 'bi-megaphone-fill', 'color' => 'text-danger', 'bg' => 'bg-danger-subtle', 'name' => 'Kinh doanh & Marketing', 'desc' => 'Bán hàng, quảng cáo, xây dựng thương hiệu', 'count' => '287 khóa học'],
    ['icon' => 'bi-palette-fill', 'color' => 'text-warning', 'bg' => 'bg-warning-subtle', 'name' => 'Thiết kế & Sáng tạo', 'desc' => 'Đồ họa, video, dựng phim và nhiếp ảnh', 'count' => '356 khóa học'],
    ['icon' => 'bi-code-slash', 'color' => 'text-primary', 'bg' => 'bg-primary-subtle', 'name' => 'Công nghệ & Lập trình', 'desc' => 'Website, ứng dụng, dữ liệu và công nghệ', 'count' => '92 khóa học'],
    ['icon' => 'bi-file-earmark-spreadsheet-fill', 'color' => 'text-success', 'bg' => 'bg-success-subtle', 'name' => 'Tin học Văn phòng', 'desc' => 'Excel, Word, PowerPoint và kỹ năng làm việc', 'count' => '171 khóa học'],
    ['icon' => 'bi-translate', 'color' => 'text-info', 'bg' => 'bg-info-subtle', 'name' => 'Ngoại ngữ', 'desc' => 'Tiếng Anh, Trung, Nhật, Hàn và giao tiếp', 'count' => '256 khóa học'],
    ['icon' => 'bi-lightbulb-fill', 'color' => 'text-secondary', 'bg' => 'bg-secondary-subtle', 'name' => 'Kỹ năng & Phát triển', 'desc' => 'Giao tiếp, tư duy và kỹ năng mềm', 'count' => '90 khóa học'],
    ['icon' => 'bi-graph-up-arrow', 'color' => 'text-success', 'bg' => 'bg-success-subtle', 'name' => 'Tài chính & Đầu tư', 'desc' => 'Chứng khoán, tài chính cá nhân và đầu tư', 'count' => '122 khóa học'],
    ['icon' => 'bi-cash-coin', 'color' => 'text-warning', 'bg' => 'bg-warning-subtle', 'name' => 'Kiếm Tiền và MMO', 'desc' => 'MMO, affiliate và xây dựng thu nhập online', 'count' => '122 khóa học'],
];
?>

<!-- HERO BANNER SECTION -->
<div class="hero-section shadow-sm">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="badge bg-primary px-3 py-2 rounded-pill mb-3">🎓 Giá sinh viên · Học trọn đời</span>
            <h1 class="display-6 fw-bold text-dark mb-3">
                Khóa học online giá rẻ.<br>
                <span class="text-primary">Học ngay sau thanh toán.</span>
            </h1>
            <p class="lead text-secondary mb-4" style="font-size: 1.05rem;">
                Marketing, thiết kế, công nghệ thông tin, ngoại ngữ. Chọn khóa học yêu thích, đăng ký tài khoản và bắt đầu học trực tuyến hoặc làm bài thi trắc nghiệm ngay lập tức.
            </p>
            <div class="d-flex flex-wrap gap-3">
                <a href="#khoa-hoc" class="btn btn-primary btn-lg px-4 fw-semibold rounded-3 shadow-sm">
                    Khám phá khóa học <i class="bi bi-arrow-right ms-1"></i>
                </a>
                <a href="#goi-hoi-vien" class="btn btn-white bg-white border btn-lg px-4 fw-semibold rounded-3">
                    Cách đăng ký học
                </a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="row g-3">
                <div class="col-12">
                    <div class="bg-white p-3 rounded-4 shadow-sm border d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle text-primary p-3 rounded-3 fs-4"><i class="bi bi-collection-play-fill"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Gói trọn bộ hơn 2.000+ khóa học</h6>
                            <p class="small text-muted mb-0">Truy cập không giới hạn, cập nhật khóa mới miễn phí</p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="bg-white p-3 rounded-4 shadow-sm border d-flex align-items-center gap-3">
                        <div class="bg-success-subtle text-success p-3 rounded-3 fs-4"><i class="bi bi-lightning-fill"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Kích hoạt tự động trong 30 giây</h6>
                            <p class="small text-muted mb-0">Xem video bài giảng YouTube & tài liệu Google Drive</p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="bg-white p-3 rounded-4 shadow-sm border d-flex align-items-center gap-3">
                        <div class="bg-warning-subtle text-warning p-3 rounded-3 fs-4"><i class="bi bi-patch-check-fill"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Thi trắc nghiệm & Chấm điểm tự động</h6>
                            <p class="small text-muted mb-0">Kiểm tra kiến thức sau khóa học với ngân hàng câu hỏi</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-5 pt-2">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <span class="text-primary fw-bold small text-uppercase">Học theo mục tiêu</span>
            <h3 class="fw-bold mb-1">Bạn muốn nâng cao kỹ năng nào?</h3>
            <p class="text-muted mb-0 small">Chọn lĩnh vực gần nhất với công việc và mục tiêu bạn đang theo đuổi.</p>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($categories as $cat): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="#khoa-hoc" class="text-decoration-none">
                <div class="cat-card p-3 h-100">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="<?= $cat['bg'] ?> <?= $cat['color'] ?> rounded-3 p-2 fs-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi <?= $cat['icon'] ?>"></i>
                        </div>
                        <span class="badge bg-light text-secondary border"><?= $cat['count'] ?></span>
                    </div>
                    <h6 class="fw-bold text-dark mb-1"><?= $cat['name'] ?></h6>
                    <p class="text-muted small mb-0"><?= $cat['desc'] ?></p>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>