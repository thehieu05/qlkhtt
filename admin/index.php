<?php
require_once '../config.php';
require_once 'db_helper.php';

// Tự động khởi tạo database nếu các bảng admin chưa có
init_admin_database($conn);

// Hỗ trợ truy cập nhanh để kiểm thử
if (isset($_GET['quick']) && $_GET['quick'] === 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['fullname'] = 'Thầy Giáo Hiếu';
    $_SESSION['username'] = 'giangvien';
    $_SESSION['role'] = 1;
}

// Kiểm tra quyền Admin (role = 1)
if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Dashboard Tổng Quan';
$active_nav = 'dashboard';

// Thống kê số liệu
$total_courses  = (int)($conn->query("SELECT COUNT(*) FROM courses")->fetch_row()[0] ?? 0);
$total_users    = (int)($conn->query("SELECT COUNT(*) FROM users WHERE role = 0")->fetch_row()[0] ?? 0);
$total_lessons  = (int)($conn->query("SELECT COUNT(*) FROM lessons")->fetch_row()[0] ?? 0);
$total_chapters = (int)($conn->query("SELECT COUNT(*) FROM chapters")->fetch_row()[0] ?? 0);
$total_questions = (int)($conn->query("SELECT COUNT(*) FROM questions")->fetch_row()[0] ?? 0);
$total_exams    = (int)($conn->query("SELECT COUNT(*) FROM exams")->fetch_row()[0] ?? 0);
$total_enrolls  = (int)($conn->query("SELECT COUNT(*) FROM enrollments")->fetch_row()[0] ?? 0);

// Danh sách học viên đăng ký mới nhất
$recent_enroll = [];
$res_en = $conn->query("SELECT u.fullname, u.username, c.title AS course_title, e.enrolled_at, e.progress_percent, e.status
    FROM enrollments e
    JOIN users u ON u.id = e.user_id
    JOIN courses c ON c.id = e.course_id
    ORDER BY e.enrolled_at DESC LIMIT 6");
if ($res_en) {
    while ($row = $res_en->fetch_assoc()) $recent_enroll[] = $row;
}

// Khóa học mới nhất
$recent_courses = [];
$res_c = $conn->query("SELECT c.*, 
    (SELECT COUNT(*) FROM chapters WHERE course_id = c.id) as chapter_count,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
    (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
    FROM courses c ORDER BY c.id DESC LIMIT 4");
if ($res_c) {
    while ($row = $res_c->fetch_assoc()) $recent_courses[] = $row;
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Bảng Điều Khiển Hệ Thống</h4>
        <p>Tổng quan hoạt động đào tạo, quản lý khóa học và học viên trực tuyến</p>
    </div>
    <div class="d-flex gap-2">
        <a href="ql_khoahoc.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Quản lý Khóa học</a>
        <a href="../index.php" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> Xem Web</a>
    </div>
</div>

<!-- 4 THẺ THỐNG KÊ CHÍNH (KPI CARDS) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-primary-subtle text-primary">
                <i class="bi bi-collection-play-fill"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_courses) ?></div>
                <div class="stat-label">Khóa học hoạt động</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-success-subtle text-success">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_users) ?></div>
                <div class="stat-label">Học viên tham gia</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-warning-subtle text-warning">
                <i class="bi bi-play-btn-fill"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_lessons) ?></div>
                <div class="stat-label">Bài giảng đã xuất bản</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-info-subtle text-info">
                <i class="bi bi-patch-question-fill"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_questions) ?></div>
                <div class="stat-label">Câu hỏi trắc nghiệm</div>
            </div>
        </div>
    </div>
</div>

<!-- LỐI TẮT CHỨC NĂNG (US-02 ĐẾN US-08) -->
<div class="table-card mb-4">
    <div class="table-card-header">
        <div class="title"><i class="bi bi-grid-fill text-primary me-2"></i>Chức năng Quản trị & Giảng viên (Đầy đủ theo yêu cầu)</div>
    </div>
    <div class="p-3">
        <div class="row g-2">
            <div class="col-md-4 col-lg-3">
                <a href="ql_khoahoc.php" class="btn btn-outline-primary w-100 text-start p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold mb-1"><i class="bi bi-journal-code me-1"></i> US-02: Thông tin Khóa học</div>
                        <small class="text-muted">Thêm mới, sửa, xóa, hình ảnh, giá & trạng thái</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary mt-2 align-self-start">Quản lý →</span>
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="ql_chuongmuc.php" class="btn btn-outline-success w-100 text-start p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold mb-1"><i class="bi bi-journals me-1"></i> US-03: Chương mục & Bài giảng</div>
                        <small class="text-muted">Tổ chức chương, video bài giảng, tài liệu & thời lượng</small>
                    </div>
                    <span class="badge bg-success-subtle text-success mt-2 align-self-start">Soạn giáo trình →</span>
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="ql_khoahoc.php" class="btn btn-outline-info w-100 text-start p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold mb-1"><i class="bi bi-card-checklist me-1"></i> US-04: Chi tiết Khóa học</div>
                        <small class="text-muted">Cấu hình cấp độ, lộ trình, nội dung chi tiết khóa học</small>
                    </div>
                    <span class="badge bg-info-subtle text-info mt-2 align-self-start">Xem chi tiết →</span>
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="ql_hocvien.php" class="btn btn-outline-warning w-100 text-start p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold mb-1"><i class="bi bi-people me-1"></i> US-05: Quản lý Học trực tuyến</div>
                        <small class="text-muted">Danh sách học viên, tiến độ hoàn thành, cấp quyền khóa học</small>
                    </div>
                    <span class="badge bg-warning-subtle text-warning mt-2 align-self-start">Theo dõi học tập →</span>
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="ngan_hang_cauhoi.php" class="btn btn-outline-secondary w-100 text-start p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold mb-1"><i class="bi bi-question-diamond me-1"></i> US-06: Ngân hàng Câu hỏi</div>
                        <small class="text-muted">Tạo câu hỏi trắc nghiệm, 4 đáp án, mức độ khó & giải thích</small>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary mt-2 align-self-start">Kho câu hỏi →</span>
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="ql_thi.php" class="btn btn-outline-danger w-100 text-start p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold mb-1"><i class="bi bi-clipboard2-check me-1"></i> US-07: Quản lý Thi</div>
                        <small class="text-muted">Tạo đề thi trắc nghiệm, thời gian, điểm chuẩn, kết quả thi</small>
                    </div>
                    <span class="badge bg-danger-subtle text-danger mt-2 align-self-start">Tổ chức thi →</span>
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="thong_ke.php" class="btn btn-outline-dark w-100 text-start p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold mb-1"><i class="bi bi-bar-chart-line me-1"></i> US-08: Thống kê Báo cáo</div>
                        <small class="text-muted">Biểu đồ học viên, doanh thu, tỷ lệ đậu trượt các bài thi</small>
                    </div>
                    <span class="badge bg-dark-subtle text-dark mt-2 align-self-start">Xem số liệu →</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- DANH SÁCH KHÓA HỌC MỚI NHẤT -->
    <div class="col-lg-7">
        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-collection-play me-2 text-primary"></i>Khóa học hiện có</div>
                <a href="ql_khoahoc.php" class="btn btn-sm btn-link text-decoration-none">Xem tất cả (<?= $total_courses ?>) →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Khóa học</th>
                            <th>Nội dung</th>
                            <th>Học viên</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_courses)): ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">Chưa có khóa học nào.</td></tr>
                        <?php else: foreach ($recent_courses as $c): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= htmlspecialchars($c['image_url']) ?>" style="width: 44px; height: 32px; object-fit: cover; border-radius: 6px;">
                                        <div>
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;"><?= htmlspecialchars($c['title']) ?></div>
                                            <span class="badge-active"><?= htmlspecialchars($c['category'] ?? 'Khóa học') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= (int)$c['chapter_count'] ?> chương • <?= (int)$c['lesson_count'] ?> bài
                                    </small>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= (int)$c['student_count'] ?></span>
                                </td>
                                <td>
                                    <a href="ql_chuongmuc.php?course_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Soạn bài giảng">
                                        <i class="bi bi-journals"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- HỌC VIÊN THAM GIA GẦN ĐÂY -->
    <div class="col-lg-5">
        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-person-check me-2 text-success"></i>Học viên đăng ký mới</div>
                <a href="ql_hocvien.php" class="btn btn-sm btn-link text-decoration-none">Tất cả →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Học viên</th>
                            <th>Khóa học</th>
                            <th>Tiến độ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_enroll)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted">Chưa có học viên nào đăng ký.</td></tr>
                        <?php else: foreach ($recent_enroll as $en): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($en['fullname']) ?></div>
                                    <small class="text-muted">@<?= htmlspecialchars($en['username']) ?></small>
                                </td>
                                <td>
                                    <div class="text-truncate text-secondary small" style="max-width: 140px;">
                                        <?= htmlspecialchars($en['course_title']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="progress" style="height: 6px; width: 60px;">
                                        <div class="progress-bar bg-success" style="width: <?= (int)$en['progress_percent'] ?>%"></div>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?= (int)$en['progress_percent'] ?>%</small>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

    </div> <!-- đóng .admin-page-body -->
</div> <!-- đóng .admin-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
