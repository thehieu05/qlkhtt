<?php
require_once __DIR__ . '/../config.php';

require_admin('../login.php');

$page_title = 'Thống kê & Báo cáo';
$active_nav = 'thongke';

// Số liệu tổng hợp theo kiến trúc mới
$total_courses   = (int)($conn->query("SELECT COUNT(*) FROM courses")->fetch_row()[0] ?? 0);
$total_students  = (int)($conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetch_row()[0] ?? 0);
$total_orders    = (int)($conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0] ?? 0);
$confirmed_orders= (int)($conn->query("SELECT COUNT(*) FROM orders WHERE status = 'confirmed'")->fetch_row()[0] ?? 0);
$pending_orders  = (int)($conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetch_row()[0] ?? 0);

// Doanh thu mô phỏng (từ đơn hàng confirmed)
$total_revenue   = (float)($conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'confirmed'")->fetch_row()[0] ?? 0);

// Thống kê bài kiểm tra trắc nghiệm
$total_quizzes   = (int)($conn->query("SELECT COUNT(*) FROM quizzes")->fetch_row()[0] ?? 0);
$total_attempts  = (int)($conn->query("SELECT COUNT(*) FROM quiz_attempts")->fetch_row()[0] ?? 0);
$passed_attempts = (int)($conn->query("SELECT COUNT(*) FROM quiz_attempts WHERE passed = 1")->fetch_row()[0] ?? 0);
$failed_attempts = $total_attempts - $passed_attempts;
$pass_rate       = $total_attempts > 0 ? round(($passed_attempts / $total_attempts) * 100, 1) : 0;

// Thống kê học viên theo từng khóa học (dữ liệu biểu đồ)
$chart_courses_res = $conn->query("SELECT c.title, COUNT(e.id) as student_count
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'active'
    GROUP BY c.id
    ORDER BY student_count DESC LIMIT 6");

$chart_labels = [];
$chart_data = [];
if ($chart_courses_res) {
    while ($r = $chart_courses_res->fetch_assoc()) {
        $chart_labels[] = mb_substr($r['title'], 0, 22) . '...';
        $chart_data[] = (int)$r['student_count'];
    }
}

// Top khóa học được đăng ký nhiều nhất
$top_courses = [];
$top_res = $conn->query("SELECT c.*, cat.name AS category_name,
    COUNT(e.id) as total_students,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
    (SELECT COALESCE(SUM(oi.price), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.course_id = c.id AND o.status = 'confirmed') as course_revenue
    FROM courses c
    LEFT JOIN categories cat ON cat.id = c.category_id
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'active'
    GROUP BY c.id
    ORDER BY total_students DESC, c.id DESC LIMIT 5");
if ($top_res) {
    while ($r = $top_res->fetch_assoc()) $top_courses[] = $r;
}

// Bảng xếp hạng học viên có điểm bài kiểm tra cao nhất
$leaderboard = [];
$lead_res = $conn->query("SELECT qa.*, u.fullname, u.username, q.title as quiz_title
    FROM quiz_attempts qa
    JOIN users u ON u.id = qa.user_id
    JOIN quizzes q ON q.id = qa.quiz_id
    ORDER BY qa.score DESC, qa.id DESC LIMIT 5");
if ($lead_res) {
    while ($r = $lead_res->fetch_assoc()) $leaderboard[] = $r;
}

include 'admin_layout.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Báo cáo & Thống kê Hệ thống</h4>
        <p>Tổng hợp hiệu quả đào tạo, học viên, đơn hàng và doanh thu mô phỏng</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> In báo cáo</button>
    </div>
</div>

<!-- 4 THẺ THỐNG KÊ CHÍNH -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-success-subtle text-success">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div>
                <div class="stat-value text-dark" style="font-size: 1.45rem;"><?= number_format($total_revenue, 0, ',', '.') ?> đ</div>
                <div class="stat-label">Doanh thu mô phỏng</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-primary-subtle text-primary">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_orders) ?></div>
                <div class="stat-label">Đơn hàng (<?= $confirmed_orders ?> xác nhận, <?= $pending_orders ?> chờ)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-info-subtle text-info">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_students) ?></div>
                <div class="stat-label">Học viên tham gia</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-warning-subtle text-warning">
                <i class="bi bi-clipboard2-check-fill"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= $pass_rate ?>%</div>
                <div class="stat-label">Tỷ lệ đạt trắc nghiệm (<?= $passed_attempts ?>/<?= $total_attempts ?>)</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- BIỂU ĐỒ SỐ LƯỢNG HỌC VIÊN -->
    <div class="col-lg-8">
        <div class="table-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Số lượng học viên theo khóa học</h6>
            </div>
            <div style="height: 280px;">
                <canvas id="chartCourses"></canvas>
            </div>
        </div>
    </div>

    <!-- TỶ LỆ KẾT QUẢ TRẮC NGHIỆM -->
    <div class="col-lg-4">
        <div class="table-card p-3 h-100">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill text-warning me-2"></i>Kết quả trắc nghiệm</h6>
            <div style="height: 220px;" class="d-flex align-items-center justify-content-center">
                <canvas id="chartExamPie"></canvas>
            </div>
            <div class="d-flex justify-content-around mt-3 small text-center">
                <div>
                    <span class="badge bg-success-subtle text-success">Đạt: <?= $passed_attempts ?></span>
                </div>
                <div>
                    <span class="badge bg-danger-subtle text-danger">Chưa đạt: <?= $failed_attempts ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- TOP KHÓA HỌC -->
    <div class="col-lg-7">
        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-trophy-fill text-warning me-2"></i>Top khóa học được học viên quan tâm nhất</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Khóa học</th>
                            <th>Danh mục</th>
                            <th>Học viên</th>
                            <th>Doanh thu mô phỏng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_courses)): ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">Chưa có dữ liệu.</td></tr>
                        <?php else: foreach ($top_courses as $c): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($c['title']) ?></div>
                                    <small class="text-muted"><?= (int)$c['lesson_count'] ?> bài giảng</small>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary"><?= e($c['category_name'] ?? 'Khóa học') ?></span></td>
                                <td><span class="fw-bold text-success"><?= (int)$c['total_students'] ?></span></td>
                                <td class="fw-bold text-danger"><?= number_format((float)$c['course_revenue'], 0, ',', '.') ?> đ</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- BẢNG XẾP HẠNG ĐIỂM SỐ -->
    <div class="col-lg-5">
        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-star-fill text-warning me-2"></i>Điểm trắc nghiệm xuất sắc</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Học viên</th>
                            <th>Bài kiểm tra</th>
                            <th>Điểm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaderboard)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted">Chưa có lượt thi trắc nghiệm nào.</td></tr>
                        <?php else: foreach ($leaderboard as $lb): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($lb['fullname']) ?></div>
                                    <small class="text-muted">@<?= e($lb['username']) ?></small>
                                </td>
                                <td class="small text-truncate" style="max-width: 140px;"><?= e($lb['quiz_title']) ?></td>
                                <td>
                                    <span class="badge <?= (float)$lb['score'] >= 80 ? 'bg-success' : 'bg-primary' ?> fs-6 fw-bold">
                                        <?= number_format((float)$lb['score'], 1) ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const ctxBar = document.getElementById('chartCourses').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Số học viên',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: '#2563eb',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});

const ctxPie = document.getElementById('chartExamPie').getContext('2d');
new Chart(ctxPie, {
    type: 'doughnut',
    data: {
        labels: ['Đạt', 'Chưa đạt'],
        datasets: [{
            data: [<?= $passed_attempts ?>, <?= $failed_attempts ?>],
            backgroundColor: ['#16a34a', '#dc2626']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
