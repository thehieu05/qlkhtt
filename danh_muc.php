<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/category_helper.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/menu.php';

// Lấy tham số lọc & sắp xếp từ URL
$cat_id     = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$cat_filter = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$max_price  = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;
$min_price  = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$sort       = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page   = 12;

// Xây dựng URL giữ nguyên bộ lọc hiện tại
function build_catalog_url(array $overrides = []): string
{
    $params = [
        'cat_id'    => $_GET['cat_id'] ?? '',
        'cat'       => $_GET['cat'] ?? '',
        'min_price' => $_GET['min_price'] ?? '',
        'max_price' => $_GET['max_price'] ?? '',
        'sort'      => $_GET['sort'] ?? '',
        'q'         => $_GET['q'] ?? '',
        'page'      => $_GET['page'] ?? '',
    ];
    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }
    $clean = [];
    foreach ($params as $k => $v) {
        if ($v !== '' && $v !== null && $v !== 0 && $v !== '0') {
            $clean[$k] = $v;
        }
    }
    return 'danh_muc.php' . (!empty($clean) ? ('?' . http_build_query($clean)) : '');
}

// Lấy danh sách danh mục đang hiển thị từ bảng `categories` kèm số lượng khóa học công khai
$categories_db = get_categories_with_counts($conn);
$all_categories = [];
foreach ($categories_db as $cat_row) {
    $all_categories[$cat_row['name']] = (int)$cat_row['course_count'];
    if ($cat_id === 0 && $cat_filter !== '' && mb_strtolower($cat_row['name'], 'UTF-8') === mb_strtolower($cat_filter, 'UTF-8')) {
        $cat_id = (int)$cat_row['id'];
    }
    if ($cat_id > 0 && (int)$cat_row['id'] === $cat_id && $cat_filter === '') {
        $cat_filter = $cat_row['name'];
    }
}

// Xây dựng điều kiện WHERE (chỉ hiển thị khóa học status = 'published')
$where_clauses = ["c.status = 'published'"];
$types = '';
$params = [];

if ($search !== '') {
    $like_q = '%' . $search . '%';
    $where_clauses[] = "(c.title LIKE ? OR c.description LIKE ? OR cat.name LIKE ?)";
    $types .= 'sss';
    $params[] = $like_q;
    $params[] = $like_q;
    $params[] = $like_q;
}
if ($cat_id > 0) {
    $where_clauses[] = "c.category_id = ?";
    $types .= 'i';
    $params[] = $cat_id;
} elseif ($cat_filter !== '') {
    $like_cat = '%' . $cat_filter . '%';
    $where_clauses[] = "(cat.name LIKE ? OR c.title LIKE ?)";
    $types .= 'ss';
    $params[] = $like_cat;
    $params[] = $like_cat;
}
if ($min_price > 0) {
    $where_clauses[] = "c.price >= ?";
    $types .= 'i';
    $params[] = $min_price;
}
if ($max_price > 0) {
    $where_clauses[] = "c.price <= ?";
    $types .= 'i';
    $params[] = $max_price;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Sắp xếp
$order_sql = "ORDER BY c.id DESC";
if ($sort === 'bestseller') {
    $order_sql = "ORDER BY c.id ASC";
} elseif ($sort === 'price_asc') {
    $order_sql = "ORDER BY c.price ASC, c.id DESC";
} elseif ($sort === 'price_desc') {
    $order_sql = "ORDER BY c.price DESC, c.id DESC";
}

// Tổng số khóa học khớp bộ lọc
$total_courses = 0;
$stmt_cnt = $conn->prepare("SELECT COUNT(*) AS total FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id $where_sql");
if ($stmt_cnt) {
    if ($types !== '') {
        $stmt_cnt->bind_param($types, ...$params);
    }
    $stmt_cnt->execute();
    $cnt_row = $stmt_cnt->get_result()->fetch_assoc();
    $total_courses = (int)($cnt_row['total'] ?? 0);
    $stmt_cnt->close();
}

$total_pages = max(1, (int)ceil($total_courses / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$courses_list = [];
$stmt_list = $conn->prepare("SELECT c.*, cat.name AS category_name FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id $where_sql $order_sql LIMIT ? OFFSET ?");
if ($stmt_list) {
    $list_types = $types . 'ii';
    $list_params = array_merge($params, [$per_page, $offset]);
    $stmt_list->bind_param($list_types, ...$list_params);
    $stmt_list->execute();
    $list_res = $stmt_list->get_result();
    while ($row = $list_res->fetch_assoc()) {
        $courses_list[] = $row;
    }
    $stmt_list->close();
}

$page_heading = $cat_filter !== '' ? $cat_filter : ($search !== '' ? 'Kết quả tìm kiếm: "' . $search . '"' : 'Tất cả khóa học');

$sample_instructors = [
    ['name' => '28tech', 'count' => 16, 'color' => '#1e3a8a'],
    ['name' => 'Hỏi Dân IT', 'count' => 12, 'color' => '#047857'],
    ['name' => 'Chu Minh Hạnh', 'count' => 9, 'color' => '#b45309'],
    ['name' => 'Phạm Minh Hoàng', 'count' => 8, 'color' => '#6d28d9'],
    ['name' => 'Dư Thanh Được', 'count' => 11, 'color' => '#dc2626'],
    ['name' => 'Duy Muồi', 'count' => 7, 'color' => '#0284c7'],
];
?>

<div class="catalog-page py-4">
    <div class="container">

        <!-- 1. BREADCRUMB & TIÊU ĐỀ -->
        <nav aria-label="breadcrumb" class="detail-breadcrumb mb-2">
            <a href="index.php"><i class="bi bi-house-door me-1"></i>Trang chủ</a>
            <span class="mx-2 text-muted">›</span>
            <a href="danh_muc.php">Khóa học</a>
            <?php if ($cat_filter !== ''): ?>
                <span class="mx-2 text-muted">›</span>
                <span class="text-dark fw-semibold"><?= htmlspecialchars($cat_filter) ?></span>
            <?php endif; ?>
        </nav>

        <div class="mb-3">
            <h1 class="catalog-page-title mb-1"><?= htmlspecialchars($page_heading) ?></h1>
            <div class="text-muted small"><?= number_format($total_courses, 0, ',', '.') ?> khóa học</div>
        </div>

        <!-- 2. THANH CAM KẾT 4 CỘT -->
        <div class="catalog-guarantee-strip mb-4">
            <div class="cg-item">
                <i class="bi bi-clock-history text-warning-emphasis"></i>
                <span>Nhận Drive khoảng 30 giây</span>
            </div>
            <div class="cg-item">
                <i class="bi bi-shield-check text-warning-emphasis"></i>
                <span>Bảo hành link trọn đời</span>
            </div>
            <div class="cg-item">
                <i class="bi bi-credit-card text-warning-emphasis"></i>
                <span>Thanh toán tự động 24/7</span>
            </div>
            <div class="cg-item">
                <i class="bi bi-headset text-warning-emphasis"></i>
                <span>Zalo hỗ trợ nhanh chóng</span>
            </div>
        </div>

        <!-- 3. BỐ CỤC 2 CỘT: SIDEBAR LỌC (TRÁI) + LƯỚI KHÓA HỌC (PHẢI) -->
        <div class="row g-4">

            <!-- CỘT TRÁI: SIDEBAR DANH MỤC & BỘ LỌC -->
            <aside class="col-lg-3">
                <div class="catalog-sidebar">

                    <!-- Nhóm 1: DANH MỤC -->
                    <div class="sidebar-filter-group">
                        <div class="sidebar-group-title">DANH MỤC</div>
                        <div class="sidebar-cat-list">
                            <a href="<?= build_catalog_url(['cat' => '', 'page' => 1]) ?>"
                               class="sidebar-cat-item <?= $cat_filter === '' ? 'is-active' : '' ?>">
                                <span>Tất cả danh mục</span>
                            </a>
                            <?php foreach ($all_categories as $cat_name => $cat_cnt): ?>
                                <a href="<?= build_catalog_url(['cat' => $cat_name, 'page' => 1]) ?>"
                                   class="sidebar-cat-item <?= strcasecmp($cat_filter, $cat_name) === 0 ? 'is-active' : '' ?>">
                                    <span class="text-truncate"><?= htmlspecialchars($cat_name) ?></span>
                                    <?php if ($cat_cnt > 0): ?>
                                        <span class="sidebar-cat-count"><?= $cat_cnt ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Nhóm 2: GÓI HỌC & TÀI NGUYÊN -->
                    <div class="sidebar-filter-group">
                        <div class="sidebar-group-title">GÓI HỌC &amp; TÀI NGUYÊN</div>
                        <div class="sidebar-group-sub">Học nhiều hơn với chi phí tiết kiệm.</div>
                        <div class="sidebar-cat-list mt-2">
                            <a href="index.php#goi-hoi-vien" class="sidebar-cat-item">
                                <span>Trọn bộ 2.000+ khóa học</span>
                                <span class="sidebar-cat-count fw-bold text-dark">599K</span>
                            </a>
                            <a href="index.php#goi-hoi-vien" class="sidebar-cat-item">
                                <span>Hội viên Start</span>
                                <span class="sidebar-cat-count">99.000 đ</span>
                            </a>
                            <a href="index.php#goi-hoi-vien" class="sidebar-cat-item">
                                <span>Hội viên VIP</span>
                                <span class="sidebar-cat-count">490.000 đ</span>
                            </a>
                            <a href="<?= build_catalog_url(['cat' => 'Combo Khóa Học', 'page' => 1]) ?>" class="sidebar-cat-item">
                                <span>Combo Khóa Học</span>
                                <span class="sidebar-cat-count"><?= $all_categories['Combo Khóa Học'] ?? 0 ?></span>
                            </a>
                        </div>
                    </div>

                    <!-- Nhóm 3: MỨC GIÁ -->
                    <div class="sidebar-filter-group">
                        <div class="sidebar-group-title">MỨC GIÁ</div>
                        <div class="sidebar-cat-list">
                            <a href="<?= build_catalog_url(['max_price' => '', 'page' => 1]) ?>"
                               class="sidebar-cat-item <?= $max_price === 0 ? 'is-active' : '' ?>">
                                <span>Tất cả mức giá</span>
                            </a>
                            <?php foreach ([50000 => 'Dưới 50.000₫', 100000 => 'Dưới 100.000₫', 300000 => 'Dưới 300.000₫', 500000 => 'Dưới 500.000₫'] as $mp => $mp_label): ?>
                                <a href="<?= build_catalog_url(['max_price' => $mp, 'page' => 1]) ?>"
                                   class="sidebar-cat-item <?= $max_price === $mp ? 'is-active' : '' ?>">
                                    <span><?= $mp_label ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Nhóm 4: GIẢNG VIÊN -->
                    <div class="sidebar-filter-group border-bottom-0 pb-0 mb-0">
                        <div class="sidebar-group-title">GIẢNG VIÊN</div>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="instructorSearchInput" class="form-control border-start-0 ps-0" placeholder="Tìm giảng viên...">
                        </div>
                        <div class="sidebar-instructor-list" id="instructorList">
                            <?php foreach ($sample_instructors as $inst): ?>
                                <a href="<?= build_catalog_url(['q' => $inst['name'], 'page' => 1]) ?>" class="sidebar-instructor-item" data-name="<?= htmlspecialchars(mb_strtolower($inst['name'], 'UTF-8')) ?>">
                                    <span class="inst-avatar" style="background: <?= $inst['color'] ?>;">
                                        <?= mb_strtoupper(mb_substr($inst['name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                                    </span>
                                    <span class="inst-name text-truncate"><?= htmlspecialchars($inst['name']) ?></span>
                                    <span class="sidebar-cat-count ms-auto"><?= $inst['count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </aside>

            <!-- CỘT PHẢI: THANH SẮP XẾP & LƯỚI KHÓA HỌC 3 CỘT -->
            <div class="col-lg-9">

                <!-- Thanh sắp xếp -->
                <div class="catalog-sort-bar mb-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="<?= build_catalog_url(['sort' => 'newest', 'page' => 1]) ?>"
                           class="sort-pill <?= $sort === 'newest' ? 'is-active' : '' ?>">Mới nhất</a>
                        <a href="<?= build_catalog_url(['sort' => 'bestseller', 'page' => 1]) ?>"
                           class="sort-pill <?= $sort === 'bestseller' ? 'is-active' : '' ?>">Bán chạy</a>
                        <a href="<?= build_catalog_url(['sort' => 'price_asc', 'page' => 1]) ?>"
                           class="sort-pill <?= $sort === 'price_asc' ? 'is-active' : '' ?>">Giá thấp → cao</a>
                        <a href="<?= build_catalog_url(['sort' => 'price_desc', 'page' => 1]) ?>"
                           class="sort-pill <?= $sort === 'price_desc' ? 'is-active' : '' ?>">Giá cao → thấp</a>
                    </div>
                    <?php if ($cat_filter !== '' || $max_price > 0 || $search !== ''): ?>
                        <a href="danh_muc.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                            <i class="bi bi-x-circle me-1"></i>Xóa bộ lọc
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Lưới khóa học -->
                <div class="row g-3">
                    <?php if (!empty($courses_list)): ?>
                        <?php foreach ($courses_list as $course):
                            $c_id    = (int)$course['id'];
                            $c_cat   = !empty($course['category']) ? $course['category'] : 'Khóa học trực tuyến';
                            $c_img   = !empty($course['image_url']) ? $course['image_url'] : 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600';
                            $c_price = (float)($course['price'] > 0 ? $course['price'] : 149999);
                            $c_old   = (float)($course['old_price'] > 0 ? $course['old_price'] : 2500000);
                            $c_url   = "chitiet_khoahoc.php?id=" . $c_id;
                        ?>
                        <div class="col-12 col-sm-6 col-xl-4">
                            <div class="catalog-course-card">
                                <a href="<?= $c_url ?>" class="catalog-card-thumb">
                                    <img src="<?= htmlspecialchars($c_img) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                                </a>
                                <div class="catalog-card-body">
                                    <div class="catalog-card-cat"><?= htmlspecialchars(mb_strtoupper($c_cat, 'UTF-8')) ?></div>
                                    <a href="<?= $c_url ?>" class="catalog-card-title"><?= htmlspecialchars($course['title']) ?></a>

                                    <div class="catalog-card-prices">
                                        <span class="catalog-price-new"><?= number_format($c_price, 0, ',', '.') ?> ₫</span>
                                        <?php if ($c_old > $c_price): ?>
                                            <span class="catalog-price-old"><?= number_format($c_old, 0, ',', '.') ?> ₫</span>
                                        <?php endif; ?>
                                    </div>

                                    <a href="<?= $c_url ?>" class="btn btn-primary btn-sm w-100 fw-bold py-2 rounded-3 mt-2">
                                        <span>Xem chi tiết & Đặt mua</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="bg-white border rounded-4 p-5 text-center text-muted">
                                <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-bold text-dark">Không tìm thấy khóa học phù hợp</h5>
                                <p class="small mb-3">Thử chọn danh mục khác hoặc xóa bộ lọc hiện tại.</p>
                                <a href="danh_muc.php" class="btn btn-primary btn-sm rounded-pill px-4">Xem tất cả khóa học</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Phân trang khóa học" class="mt-4 d-flex justify-content-center">
                    <ul class="pagination catalog-pagination mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= build_catalog_url(['page' => max(1, $page - 1)]) ?>">‹</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= build_catalog_url(['page' => $p]) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= build_catalog_url(['page' => min($total_pages, $page + 1)]) ?>">›</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
// Tìm kiếm nhanh giảng viên trong sidebar
const instSearch = document.getElementById('instructorSearchInput');
if (instSearch) {
    instSearch.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.sidebar-instructor-item').forEach(item => {
            const name = item.dataset.name || '';
            item.style.display = name.includes(q) ? 'flex' : 'none';
        });
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
