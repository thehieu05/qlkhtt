<?php
require_once 'config.php';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduNova - Nền tảng Học tập & Chia sẻ Khóa học Trực tuyến</title>
    <!-- Bootstrap 5 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="style.css" rel="stylesheet">
</head>
<body>

<!-- TOP PROMO BAR -->
<div class="top-promo-bar text-center">
    <div class="container d-flex justify-content-center align-items-center flex-wrap gap-2">
        <span>✨ <b>EduNova — Trọn bộ 2.000+ khóa học</b> — Truy cập trọn đời, cập nhật miễn phí, chỉ <b>599K</b></span>
        <a href="index.php#goi-hoi-vien" class="btn btn-warning btn-sm fw-bold py-0 px-2 rounded-pill" style="font-size: 0.8rem;">Xem ngay</a>
    </div>
</div>

<!-- MAIN HEADER -->
<nav class="navbar navbar-expand-lg main-navbar sticky-top py-3">
    <div class="container">
        <!-- Logo EduNova -->
        <a class="navbar-brand edunova-brand me-3" href="index.php">
            <div class="brand-icon-wrap">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 3L2 8.5L12 14L22 8.5L12 3Z" fill="#ffffff" />
                    <path d="M6 11.2V16.5C6 16.5 8.5 19 12 19C15.5 19 18 16.5 18 16.5V11.2L12 14.5L6 11.2Z" fill="#ffffff" fill-opacity="0.85" />
                    <path d="M22 9.5V15.5" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M19.5 3C19.5 4.3 20.2 5 21.5 5C20.2 5 19.5 5.7 19.5 7C19.5 5.7 18.8 5 17.5 5C18.8 5 19.5 4.3 19.5 3Z" fill="#fbbf24" />
                </svg>
            </div>
            <div class="brand-text-wrap">
                <span class="brand-name">Edu<span class="brand-nova">Nova</span><span class="brand-spark">✦</span></span>
                <span class="brand-tagline">Học tập & Phát triển</span>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainHeader">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainHeader">
            <!-- Nút Danh mục Dropdown -->
            <div class="dropdown mx-lg-3 my-2 my-lg-0">
                <button class="btn btn-light border dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-grid-fill text-primary me-1"></i> Danh mục
                </button>
                <ul class="dropdown-menu shadow-sm border-0 mt-2">
                    <li><a class="dropdown-item py-2" href="index.php#khoa-hoc"><i class="bi bi-layers me-2 text-primary"></i>Combo Khóa Học</a></li>
                    <li><a class="dropdown-item py-2" href="index.php#khoa-hoc"><i class="bi bi-code-square me-2 text-success"></i>Công nghệ thông tin</a></li>
                    <li><a class="dropdown-item py-2" href="index.php#khoa-hoc"><i class="bi bi-megaphone me-2 text-danger"></i>Kinh doanh & Marketing</a></li>
                    <li><a class="dropdown-item py-2" href="index.php#khoa-hoc"><i class="bi bi-palette me-2 text-warning"></i>Thiết kế đồ họa</a></li>
                    <li><a class="dropdown-item py-2" href="index.php#khoa-hoc"><i class="bi bi-translate me-2 text-info"></i>Ngoại ngữ</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item fw-bold text-primary" href="index.php#khoa-hoc">Xem tất cả khóa học →</a></li>
                </ul>
            </div>

            <!-- Thanh Tìm kiếm bo tròn hiện đại đồng bộ màu xanh EduNova -->
            <form class="edunova-search-form flex-grow-1 mx-lg-3 my-2 my-lg-0" method="GET" action="index.php">
                <div class="edunova-search-box">
                    <i class="bi bi-search search-box-icon"></i>
                    <input type="text" 
                           name="q" 
                           class="search-box-input" 
                           placeholder="Thử tìm: Ngoại ngữ, Lập trình, Marketing..." 
                           value="<?= htmlspecialchars($search) ?>" 
                           autocomplete="off">
                    <?php if (!empty($search)): ?>
                        <a href="index.php" class="search-box-clear" title="Xóa tìm kiếm">
                            <i class="bi bi-x-circle-fill"></i>
                        </a>
                    <?php endif; ?>
                    <button class="search-box-btn" type="submit">
                        Tìm
                    </button>
                </div>
            </form>

            <!-- Khu vực Tài khoản -->
            <div class="d-flex align-items-center gap-2 ms-lg-3 mt-2 mt-lg-0">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="small text-muted me-1">Chào, <b><?= htmlspecialchars($_SESSION['fullname']) ?></b></span>
                    <?php if ($_SESSION['role'] == 1): ?>
                        <a href="ql_khoahoc.php" class="btn btn-warning btn-sm fw-semibold">
                            <i class="bi bi-gear-fill"></i> Quản trị
                        </a>
                    <?php else: ?>
                        <a href="ql_khoahoc.php" class="btn btn-outline-primary btn-sm fw-semibold">
                            <i class="bi bi-journal-play"></i> Khóa học
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-danger btn-sm">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary fw-semibold btn-sm px-3">
                        <i class="bi bi-person-circle me-1"></i> Đăng nhập
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
