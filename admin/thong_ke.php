<?php
require_once '../config.php';
require_once 'db_helper.php';
init_admin_database($conn);

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Thống kê & Báo cáo';
$active_nav = 'thongke';

// Số liệu tổng hợp
$total_courses   = (int)($conn->query("SELECT COUNT(*) FROM courses")->fetch_row()[0] ?? 0);
$total_students  = (int)($conn->query("SELECT COUNT(*) FROM users WHERE role = 0")->fetch_row()[0] ?? 0);
$total_lessons   = (int)($conn->query("SELECT COUNT(*) FROM lessons")->fetch_row()[0] ?? 0);
$total_questions = (int)($conn->query("SELECT COUNT(*) FROM questions")->fetch_row()[0] ?? 0);
$total_exams     = (int)($conn->query("SELECT COUNT(*) FROM exams")->fetch_row()[0] ?? 0);
$total_results   = (int)($conn->query("SELECT COUNT(*) FROM exam_results")->fetch_row()[0] ?? 0);
$passed_count    = (int)($conn->query("SELECT COUNT(*) FROM exam_results WHERE passed = 1")->fetch_row()[0] ?? 0);
$failed_count    = $total_results - $passed_count;

// Ước tính doanh thu từ các lượt ghi danh
$revenue_query = "SELECT SUM(c.price) as total_rev FROM enrollments e JOIN courses c ON c.id = e.course_id";
$total_revenue = (float)($conn->query($revenue_query)->fetch_row()[0] ?? 0);

// Thống kê học viên theo khóa học (dữ liệu cho biểu đồ)
$chart_courses_res = $conn->query("SELECT c.title, COUNT(e.id) as student_count 
    FROM courses c 
    LEFT JOIN enrollments e ON e.course_id = c.id 
    GROUP BY c.id 
    ORDER BY student_count DESC LIMIT 6");

$chart_labels = [];
$chart_data = [];
if ($chart_courses_res) {
    while ($r = $chart_courses_res->fetch_assoc()) {
        $chart_labels[] = mb_substr($r['title'], 0, 25) . '...';
        $chart_data[] = (int)$r['student_count'];
    }
}

// Top 5 khóa học được yêu thích nhất
$top_courses = [];
$top_res = $conn->query("SELECT c.*, COUNT(e.id) as total_students,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
    FROM courses c 
    LEFT JOIN enrollments e ON e.course_id = c.id 
    GROUP BY c.id 
    ORDER BY total_students DESC LIMIT 5");
if ($top_res) {
    while ($r = $top_res->fetch_assoc()) $top_courses[] = $r;
}

// Bảng xếp hạng học viên có điểm thi cao nhất
$leaderboard = [];
$lead_res = $conn->query("SELECT r.*, u.fullname, u.username, e.title as exam_title 
    FROM exam_results r 
    JOIN users u ON u.id = r.user_id 
    JOIN exams e ON e.id = r.exam_id 
    ORDER BY r.score DESC, r.time_taken_seconds ASC LIMIT 5");
if ($lead_res) {
    while ($r = $lead_res->fetch_assoc()) $leaderboard[] = $r;
}

include 'admin_layout.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Báo cáo & Thống kê Hệ thống (US-08)</h4>
        <p>Phân tích hiệu quả giảng dạy, số lượng học viên ghi danh, tỷ lệ thi đạt và doanh số ước tính</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> In báo cáo</button>
    </div>
</div>

<!-- 4 THẺ THỐNG KÊ DOANH THU & HIỆU SUẤT -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-success-subtle text-success">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div>
                <div class="stat-value text-dark" style="font-size: 1.45rem;"><?= number_format($total_revenue) ?> đ</div>
                <div class="stat-label">Doanh thu ghi danh</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-primary-subtle text-primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_students) ?></div>
                <div class="stat-label">Tổng học viên</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-warning-subtle text-warning">
                <i class="bi bi-journal-text"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_courses) ?></div>
                <div class="stat-label">Tổng khóa học</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="icon-wrap bg-info-subtle text-info">
                <i class="bi bi-award-fill"></i>
            </div>
            <div>
                <div class="stat-value text-dark"><?= number_format($total_results) ?></div>
                <div class="stat-label">Lượt nộp bài thi</div>
            </div>
        </div>
    </div>
</div>

<!-- 2 BIỂU ĐỒ TRỰC QUAN -->
<div class="row g-4 mb-4">
    <!-- BIỂU ĐỒ CỘT: HỌC VIÊN THEO KHÓA HỌC -->
    <div class="col-lg-8">
        <div class="table-card p-3 h-100">
            <div class="table-card-header px-0 pt-0">
                <div class="title"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Số lượng Học viên ghi danh theo Khóa học</div>
            </div>
            <div style="height: 280px; position: relative;">
                <canvas id="chartCourses"></canvas>
            </div>
        </div>
    </div>

    <!-- BIỂU ĐỒ TRÒN: TỶ LỆ ĐẬU / TRƯỢT THI -->
    <div class="col-lg-4">
        <div class="table-card p-3 h-100">
            <div class="table-card-header px-0 pt-0">
                <div class="title"><i class="bi bi-pie-chart-fill text-warning me-2"></i>Tỷ lệ Đậu / Rớt bài thi</div>
            </div>
            <div style="height: 220px; position: relative;">
                <canvas id="chartPassFail"></canvas>
            </div>
            <div class="d-flex justify-content-around text-center mt-3 pt-2 border-top">
                <div>
                    <div class="fw-bold text-success fs-5"><?= $passed_count ?></div>
                    <small class="text-muted">Đạt (Pass)</small>
                </div>
                <div>
                    <div class="fw-bold text-danger fs-5"><?= $failed_count ?></div>
                    <small class="text-muted">Chưa đạt</small>
                </div>
                <div>
                    <div class="fw-bold text-primary fs-5">
                        <?= $total_results > 0 ? round(($passed_count / $total_results) * 100) : 0 ?>%
                    </div>
                    <small class="text-muted">Tỷ lệ đậu</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- BẢNG TOP KHÓA HỌC -->
    <div class="col-lg-7">
        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-trophy text-primary me-2"></i>Top Khóa Học Nhiều Học Viên Nhất</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Khóa học</th>
                            <th>Học phí</th>
                            <th>Số bài học</th>
                            <th>Học viên</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_courses)): ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">Chưa có dữ liệu</td></tr>
                        <?php else: foreach ($top_courses as $tc): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($tc['title']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($tc['category'] ?? 'Lập trình') ?></small>
                                </td>
                                <td><span class="text-success fw-bold"><?= number_format($tc['price'] ?? 0) ?> đ</span></td>
                                <td><span class="badge bg-light text-dark border"><?= (int)$tc['lesson_count'] ?> bài</span></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fs-6 fw-bold">
                                        <i class="bi bi-people me-1"></i><?= (int)$tc['total_students'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- BẢNG THỦ KHOA THI TRỰC TUYẾN -->
    <div class="col-lg-5">
        <div class="table-card">
            <div class="table-card-header">
                <div class="title"><i class="bi bi-star-fill text-warning me-2"></i>Bảng Điểm Thủ Khoa</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Học viên</th>
                            <th>Đề thi</th>
                            <th>Điểm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaderboard)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted">Chưa có lượt nộp bài thi nào.</td></tr>
                        <?php else: foreach ($leaderboard as $idx => $lb): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-circle <?= $idx == 0 ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                                            <?= $idx + 1 ?>
                                        </span>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($lb['fullname']) ?></div>
                                            <small class="text-muted">@<?= htmlspecialchars($lb['username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-truncate d-block" style="max-width: 140px;"><?= htmlspecialchars($lb['exam_title']) ?></small>
                                </td>
                                <td>
                                    <span class="fw-bold text-success fs-6"><?= (int)$lb['score'] ?>đ</span>
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
// Vẽ biểu đồ học viên theo khóa học
const ctxCourses = document.getElementById('chartCourses').getContext('2d');
new Chart(ctxCourses, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Số học viên ghi danh',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: '#2563eb',
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});

// Vẽ biểu đồ tròn Đậu / Rớt
const ctxPassFail = document.getElementById('chartPassFail').getContext('2d');
new Chart(ctxPassFail, {
    type: 'doughnut',
    data: {
        labels: ['Đạt (Pass)', 'Chưa đạt'],
        datasets: [{
            data: [<?= $passed_count ?>, <?= $failed_count ?>],
            backgroundColor: ['#16a34a', '#dc2626'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

