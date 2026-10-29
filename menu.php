<!-- SUB-NAVBAR (Thanh menu điều hướng phụ) -->
<div class="sub-navbar d-none d-lg-block ">
    <div class="container d-flex justify-content-between align-items-center">
        <ul class="nav">
            <li class="nav-item">
                <a class="nav-link" href="index.php"><i class="bi bi-house-door-fill text-primary me-1"></i>Trang chủ</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="index.php#flash-sale"><i class="bi bi-lightning-charge-fill text-danger me-1"></i>Flash Sale</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="index.php#khoa-hoc">Mới cập nhật</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="index.php#khoa-hoc">Combo tiết kiệm</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="index.php#goi-hoi-vien">Hội viên <span class="badge bg-danger rounded-pill">Mới</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="index.php#phan-hoi"><i class="bi bi-star-fill text-warning me-1"></i>Phản hồi</a>
            </li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 1): ?>
                <li class="nav-item">
                    <a class="nav-link text-primary fw-bold" href="ql_khoahoc.php">+ Quản lý Khóa học</a>
                </li>
            <?php endif; ?>
        </ul>
        <div class="text-muted small fw-semibold">
            <i class="bi bi-telephone-inbound-fill text-success me-1"></i> Zalo hỗ trợ: <span class="text-dark">0773 308 885</span>
        </div>
    </div>
</div>