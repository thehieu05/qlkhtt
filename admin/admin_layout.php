<?php
// File layout dùng chung cho tất cả trang admin
// Dùng: include 'admin_layout.php' rồi đặt $page_title và $active_nav trước khi include

// Phải đặt $page_title và $active_nav trước khi include file này
$page_title = $page_title ?? 'Admin Panel';
$active_nav = $active_nav ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — EduNova Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        /* ===== SIDEBAR ===== */
        .admin-sidebar {
            width: 240px; min-height: 100vh; background: #1e293b;
            position: fixed; top: 0; left: 0; z-index: 1000;
            display: flex; flex-direction: column;
            overflow-y: auto;
        }
        .admin-sidebar .sidebar-brand {
            padding: 20px 16px 16px;
            border-bottom: 1px solid #334155;
        }
        .admin-sidebar .sidebar-brand .brand-name {
            font-size: 1.15rem; font-weight: 700; color: #fff;
        }
        .admin-sidebar .sidebar-brand .brand-sub {
            font-size: 0.72rem; color: #64748b; margin-top: 2px;
        }
        .admin-sidebar .sidebar-nav { padding: 10px 0; flex: 1; }
        .admin-sidebar .nav-section-label {
            font-size: 0.65rem; letter-spacing: 1.5px; text-transform: uppercase;
            color: #475569; padding: 10px 20px 4px; font-weight: 600;
        }
        .admin-sidebar .nav-link {
            color: #94a3b8; padding: 9px 12px 9px 16px;
            border-radius: 8px; margin: 1px 8px;
            font-size: 0.875rem; display: flex; align-items: center; gap: 10px;
            text-decoration: none; transition: all .15s;
        }
        .admin-sidebar .nav-link i { font-size: 1rem; flex-shrink: 0; }
        .admin-sidebar .nav-link:hover { background: #334155; color: #e2e8f0; }
        .admin-sidebar .nav-link.active { background: #2563eb; color: #fff; }
        .admin-sidebar .nav-link.nav-logout { color: #f87171; }
        .admin-sidebar .nav-link.nav-logout:hover { background: #450a0a; color: #fca5a5; }

        /* ===== MAIN CONTENT ===== */
        .admin-content { margin-left: 240px; min-height: 100vh; }

        /* ===== TOP BAR ===== */
        .admin-topbar {
            background: #fff; border-bottom: 1px solid #e2e8f0;
            padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .admin-topbar .breadcrumb { margin: 0; font-size: 0.82rem; }
        .admin-topbar .breadcrumb-item + .breadcrumb-item::before { color: #94a3b8; }
        .admin-topbar .topbar-user { display: flex; align-items: center; gap: 8px; }
        .admin-topbar .topbar-user .avatar {
            width: 32px; height: 32px; background: #2563eb;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 0.85rem;
        }

        /* ===== STAT CARDS ===== */
        .stat-card {
            background: #fff; border-radius: 14px; padding: 20px 22px;
            border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 16px;
        }
        .stat-card .icon-wrap {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;
        }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1; }
        .stat-card .stat-label { font-size: 0.8rem; color: #64748b; margin-top: 2px; }

        /* ===== TABLE CARD ===== */
        .table-card {
            background: #fff; border-radius: 14px;
            border: 1px solid #e2e8f0; overflow: hidden;
        }
        .table-card .table-card-header {
            padding: 16px 20px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
        }
        .table-card .table-card-header .title {
            font-size: 0.95rem; font-weight: 600; color: #1e293b;
        }
        .table-card table { margin: 0; }
        .table-card table th {
            font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.5px; color: #64748b;
            background: #f8fafc; border-bottom: 1px solid #e2e8f0;
            padding: 10px 16px; font-weight: 600;
        }
        .table-card table td { padding: 11px 16px; vertical-align: middle; border-color: #f1f5f9; }
        .table-card table tr:last-child td { border-bottom: none; }

        /* ===== BADGES ===== */
        .badge-easy { background: #dcfce7; color: #16a34a; font-size: 0.72rem; padding: 3px 8px; border-radius: 999px; font-weight: 600; }
        .badge-medium { background: #fef9c3; color: #ca8a04; font-size: 0.72rem; padding: 3px 8px; border-radius: 999px; font-weight: 600; }
        .badge-hard { background: #fee2e2; color: #dc2626; font-size: 0.72rem; padding: 3px 8px; border-radius: 999px; font-weight: 600; }
        .badge-active { background: #dcfce7; color: #15803d; font-size: 0.72rem; padding: 3px 8px; border-radius: 999px; font-weight: 600; }
        .badge-inactive { background: #f1f5f9; color: #64748b; font-size: 0.72rem; padding: 3px 8px; border-radius: 999px; font-weight: 600; }
        .badge-passed { background: #dcfce7; color: #15803d; }
        .badge-failed { background: #fee2e2; color: #dc2626; }

        /* ===== FORM CONTROLS ===== */
        .form-control, .form-select { border-radius: 8px; border-color: #e2e8f0; font-size: 0.875rem; }
        .form-control:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .form-label { font-size: 0.82rem; font-weight: 600; color: #374151; }

        /* ===== BUTTONS ===== */
        .btn { border-radius: 8px; font-size: 0.85rem; }
        .btn-primary { background: #2563eb; border-color: #2563eb; }
        .btn-primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .btn-sm { padding: 4px 10px; font-size: 0.8rem; }

        /* ===== MODAL ===== */
        .modal-content { border-radius: 14px; border: none; }
        .modal-header { border-bottom: 1px solid #f1f5f9; padding: 16px 20px; }
        .modal-footer { border-top: 1px solid #f1f5f9; }

        /* ===== PAGE BODY ===== */
        .admin-page-body { padding: 24px; }
        .page-header { margin-bottom: 20px; }
        .page-header h4 { font-weight: 700; color: #0f172a; margin: 0; }
        .page-header p { color: #64748b; margin: 4px 0 0; font-size: 0.85rem; }
    </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<div class="admin-sidebar">
    <div class="sidebar-brand">
        <div class="brand-name">EduNova <span style="color:#fbbf24;">✦</span></div>
        <div class="brand-sub">Bảng điều khiển quản trị</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Tổng quan</div>
        <a class="nav-link <?= $active_nav==='dashboard'?'active':'' ?>" href="index.php">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section-label">Nội dung</div>
        <a class="nav-link <?= $active_nav==='khoahoc'?'active':'' ?>" href="ql_khoahoc.php">
            <i class="bi bi-collection-play-fill"></i> Khóa học
        </a>
        <a class="nav-link <?= $active_nav==='chuongmuc'?'active':'' ?>" href="ql_chuongmuc.php">
            <i class="bi bi-journals"></i> Chương mục & Bài giảng
        </a>

        <div class="nav-section-label">Học viên & Thi</div>
        <a class="nav-link <?= $active_nav==='hocvien'?'active':'' ?>" href="ql_hocvien.php">
            <i class="bi bi-people-fill"></i> Học viên
        </a>
        <a class="nav-link <?= $active_nav==='cauhoi'?'active':'' ?>" href="ngan_hang_cauhoi.php">
            <i class="bi bi-patch-question-fill"></i> Ngân hàng Câu hỏi
        </a>
        <a class="nav-link <?= $active_nav==='thi'?'active':'' ?>" href="ql_thi.php">
            <i class="bi bi-clipboard2-check-fill"></i> Quản lý Thi
        </a>

        <div class="nav-section-label">Báo cáo</div>
        <a class="nav-link <?= $active_nav==='thongke'?'active':'' ?>" href="thong_ke.php">
            <i class="bi bi-bar-chart-line-fill"></i> Thống kê & Báo cáo
        </a>

        <hr style="border-color:#334155; margin:12px 16px;">
        <a class="nav-link" href="../index.php" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i> Xem Website
        </a>
        <a class="nav-link nav-logout" href="../logout.php">
            <i class="bi bi-box-arrow-right"></i> Đăng xuất
        </a>
    </nav>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="admin-content">
    <!-- Top Bar -->
    <div class="admin-topbar">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Admin</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($page_title) ?></li>
            </ol>
        </nav>
        <div class="topbar-user">
            <div class="avatar"><?= mb_strtoupper(mb_substr($_SESSION['fullname'] ?? 'A', 0, 1)) ?></div>
            <div>
                <div style="font-size:0.82rem; font-weight:600;"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></div>
                <div style="font-size:0.72rem; color:#64748b;">Quản trị viên</div>
            </div>
        </div>
    </div>

    <!-- Page Body -->
    <div class="admin-page-body">
        <!-- Flash Message -->
        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show d-flex align-items-center gap-2 rounded-3 mb-4" role="alert">
                <i class="bi <?= ($_SESSION['flash_type'] ?? '') === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' ?> flex-shrink-0"></i>
                <div><?= $_SESSION['flash_msg'] ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_msg'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

