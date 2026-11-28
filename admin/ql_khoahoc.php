<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../category_helper.php';

require_admin('../login.php');

$page_title = 'Quản lý Khóa học';
$active_nav = 'khoahoc';

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'quick_add_category') {
        header('Content-Type: application/json; charset=utf-8');
        $new_cat_name = trim($_POST['category_name'] ?? '');
        $res = create_course_category($conn, $new_cat_name);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($action === 'add' || $action === 'edit') {
        $course_id   = (int)($_POST['course_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url   = trim($_POST['image_url'] ?? '');
        $price       = floatval($_POST['price'] ?? 0);
        $old_price   = floatval($_POST['old_price'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $status      = $_POST['status'] ?? 'published';
        if (!in_array($status, ['published', 'draft', 'hidden'], true)) {
            $status = 'published';
        }

        // Xử lý upload ảnh bìa từ máy tính
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES['image_file']['tmp_name'];
            $file_name = $_FILES['image_file']['name'];
            $file_size = $_FILES['image_file']['size'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

            if (!in_array($ext, $allowed_exts, true)) {
                set_flash("Định dạng file ảnh không hợp lệ!", "danger");
                header("Location: ql_khoahoc.php");
                exit();
            }
            if ($file_size > 5 * 1024 * 1024) {
                set_flash("Dung lượng ảnh vượt quá 5MB!", "danger");
                header("Location: ql_khoahoc.php");
                exit();
            }

            $upload_dir = __DIR__ . '/../uploads/courses/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_file_name = 'course_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                $image_url = 'uploads/courses/' . $new_file_name;
            }
        }

        if ($action === 'edit' && $course_id > 0 && $image_url === '') {
            $stmt_old = $conn->prepare("SELECT image_url FROM courses WHERE id = ? LIMIT 1");
            $stmt_old->bind_param('i', $course_id);
            $stmt_old->execute();
            $old_row = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();
            if ($old_row) {
                $image_url = $old_row['image_url'] ?? '';
            }
        }

        if ($image_url === '') {
            $image_url = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80';
        }

        if ($title === '') {
            set_flash("Vui lòng nhập tên khóa học!", "danger");
        } else {
            $slug = slugify_text($title);
            $cat_param = $category_id > 0 ? $category_id : null;

            if ($action === 'edit' && $course_id > 0) {
                $stmt = $conn->prepare("UPDATE courses SET category_id = ?, title = ?, slug = ?, description = ?, image_url = ?, price = ?, old_price = ?, status = ? WHERE id = ?");
                $stmt->bind_param('issssddsi', $cat_param, $title, $slug, $description, $image_url, $price, $old_price, $status, $course_id);
                if ($stmt->execute()) {
                    set_flash("Cập nhật thành công khóa học: <b>" . e($title) . "</b>!", "success");
                } else {
                    set_flash("Lỗi khi cập nhật: " . $conn->error, "danger");
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO courses (category_id, title, slug, description, image_url, price, old_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('issssdds', $cat_param, $title, $slug, $description, $image_url, $price, $old_price, $status);
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    set_flash("Thêm mới thành công khóa học (#$new_id): <b>" . e($title) . "</b>!", "success");
                } else {
                    set_flash("Lỗi khi thêm khóa học: " . $conn->error, "danger");
                }
                $stmt->close();
            }
        }
        header("Location: ql_khoahoc.php");
        exit();
    }

    if ($action === 'delete') {
        $del_id = (int)($_POST['course_id'] ?? 0);
        if ($del_id > 0) {
            // Kiểm tra lịch sử đơn hàng / học viên trước khi xóa (Quy tắc xóa mềm)
            $stmt_chk = $conn->prepare("SELECT
                (SELECT COUNT(*) FROM enrollments WHERE course_id = ?) AS enr_cnt,
                (SELECT COUNT(*) FROM order_items WHERE course_id = ?) AS ord_cnt");
            $stmt_chk->bind_param('ii', $del_id, $del_id);
            $stmt_chk->execute();
            $chk = $stmt_chk->get_result()->fetch_assoc();
            $stmt_chk->close();

            if (($chk['enr_cnt'] ?? 0) > 0 || ($chk['ord_cnt'] ?? 0) > 0) {
                $stmt_hide = $conn->prepare("UPDATE courses SET status = 'hidden' WHERE id = ?");
                $stmt_hide->bind_param('i', $del_id);
                $stmt_hide->execute();
                $stmt_hide->close();
                set_flash("Khóa học #$del_id đã có học viên hoặc đơn hàng nên được chuyển sang trạng thái <b>Ẩn</b> để bảo toàn dữ liệu lịch sử.", "warning");
            } else {
                $stmt_del = $conn->prepare("DELETE FROM courses WHERE id = ?");
                $stmt_del->bind_param('i', $del_id);
                $stmt_del->execute();
                $stmt_del->close();
                set_flash("Đã xóa khóa học #$del_id!", "success");
            }
        }
        header("Location: ql_khoahoc.php");
        exit();
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['course_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE courses SET status = IF(status='published', 'draft', 'published') WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã thay đổi trạng thái khóa học #$id!", "success");
        }
        header("Location: ql_khoahoc.php");
        exit();
    }
}

// Tìm kiếm & lọc
$q = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat_id'] ?? 0);

$where_clauses = [];
$types = '';
$params = [];
if ($q !== '') {
    $like_q = '%' . $q . '%';
    $where_clauses[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $types .= 'ss';
    $params[] = $like_q;
    $params[] = $like_q;
}
if ($cat_filter > 0) {
    $where_clauses[] = "c.category_id = ?";
    $types .= 'i';
    $params[] = $cat_filter;
}
$where_sql = !empty($where_clauses) ? ('WHERE ' . implode(' AND ', $where_clauses)) : '';

$courses = [];
$sql = "SELECT c.*, cat.name AS category_name,
    (SELECT COUNT(*) FROM sections WHERE course_id = c.id) AS chapter_count,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS lesson_count,
    (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'active') AS student_count,
    (SELECT COUNT(*) FROM quizzes WHERE course_id = c.id) AS exam_count
    FROM courses c
    LEFT JOIN categories cat ON cat.id = c.category_id
    $where_sql
    ORDER BY c.id DESC";
$stmt_list = $conn->prepare($sql);
if ($stmt_list) {
    if ($types !== '') {
        $stmt_list->bind_param($types, ...$params);
    }
    $stmt_list->execute();
    $res = $stmt_list->get_result();
    while ($r = $res->fetch_assoc()) $courses[] = $r;
    $stmt_list->close();
}

$all_categories = get_all_categories($conn);

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý thông tin khóa học</h4>
        <p>Thêm mới, chỉnh sửa thông tin, giá bán, chi tiết cấu hình và chương trình giảng dạy</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCourse">
            <i class="bi bi-plus-circle me-1"></i> Thêm Khóa Học Mới
        </button>
    </div>
</div>

<!-- THANH TÌM KIẾM & BỘ LỌC -->
<div class="table-card mb-4 p-3">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Tìm theo tên hoặc mô tả khóa học..." value="<?= htmlspecialchars($q) ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="cat_id" id="filter_category" class="form-select">
                <option value="">-- Tất cả danh mục --</option>
                <?php foreach ($all_categories as $cat_item): ?>
                    <option value="<?= (int)$cat_item['id'] ?>" <?= $cat_filter === (int)$cat_item['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat_item['name']) ?> (<?= (int)$cat_item['course_count'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Lọc</button>
            <?php if (!empty($q) || $cat_filter > 0): ?>
                <a href="ql_khoahoc.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- BẢNG DANH SÁCH KHÓA HỌC -->
<div class="table-card">
    <div class="table-card-header">
        <div class="title"><i class="bi bi-collection-play me-2 text-primary"></i>Danh sách Khóa học (<?= count($courses) ?>)</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Khóa học</th>
                    <th>Danh mục</th>
                    <th>Học phí</th>
                    <th>Nội dung</th>
                    <th>Học viên</th>
                    <th>Trạng thái</th>
                    <th class="text-end" style="width: 170px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">Không tìm thấy khóa học nào phù hợp.</td></tr>
                <?php else: foreach ($courses as $c): ?>
                    <tr>
                        <td class="fw-bold text-muted">#<?= $c['id'] ?></td>
                        <td>
                            <?php 
                                $c_img = $c['image_url'] ?? '';
                                if (!empty($c_img) && !preg_match('#^https?://#i', $c_img) && strpos($c_img, '/') !== 0) {
                                    $c_img = '../' . $c_img;
                                }
                                if (empty($c_img)) {
                                    $c_img = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80';
                                }
                            ?>
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?= htmlspecialchars($c_img) ?>" style="width: 60px; height: 42px; object-fit: cover; border-radius: 6px;" onerror="this.src='https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80'">
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($c['title']) ?></div>
                                    <div class="text-muted small text-truncate" style="max-width: 250px;">
                                        <?= htmlspecialchars(mb_substr($c['description'] ?? '', 0, 80)) ?>...
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($c['category_name'] ?? 'Chưa phân loại') ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-success"><?= number_format($c['price'] ?? 0) ?> đ</div>
                            <?php if (!empty($c['old_price']) && $c['old_price'] > 0): ?>
                                <small class="text-muted text-decoration-line-through"><?= number_format($c['old_price']) ?> đ</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="ql_chuongmuc.php?course_id=<?= $c['id'] ?>" class="text-decoration-none fw-semibold">
                                <i class="bi bi-journals me-1"></i><?= (int)$c['chapter_count'] ?> chương • <?= (int)$c['lesson_count'] ?> bài
                            </a>
                            <div class="text-muted small"><i class="bi bi-clipboard2-check me-1"></i><?= (int)$c['exam_count'] ?> bài kiểm tra</div>
                        </td>
                        <td>
                            <span class="badge bg-success-subtle text-success fs-6 fw-bold">
                                <i class="bi bi-people me-1"></i><?= (int)$c['student_count'] ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $cst = $c['status'] ?? 'published';
                            $st_badge = $cst === 'published' ? 'btn-success' : ($cst === 'draft' ? 'btn-warning' : 'btn-secondary');
                            $st_label = $cst === 'published' ? 'Công khai' : ($cst === 'draft' ? 'Bản nháp' : 'Đang ẩn');
                            ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $st_badge ?> rounded-pill" style="font-size: 0.75rem; padding: 2px 10px;">
                                    <?= $st_label ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="ql_chuongmuc.php?course_id=<?= $c['id'] ?>" class="btn btn-outline-info" title="Soạn giáo trình">
                                    <i class="bi bi-list-task"></i>
                                </a>
                                <button class="btn btn-outline-primary" onclick='openEditModal(<?= json_encode($c) ?>)' title="Chỉnh sửa">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL THÊM KHÓA HỌC MỚI -->
<div class="modal fade" id="modalAddCourse" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Thêm Khóa Học Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Tên khóa học <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Ví dụ: Lập trình Web Fullstack với PHP & MySQL">
                    </div>
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Danh mục đào tạo</label>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" onclick="toggleQuickCategory('add')">
                                <i class="bi bi-plus-circle me-1"></i>Thêm danh mục mới
                            </button>
                        </div>
                        <select name="category_id" id="add_category" class="form-select">
                            <?php foreach ($all_categories as $cat_item): ?>
                                <option value="<?= (int)$cat_item['id'] ?>"><?= htmlspecialchars($cat_item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="add_cat_inline" class="input-group input-group-sm mt-2 d-none">
                            <input type="text" id="add_new_cat_name" class="form-control" placeholder="Nhập tên danh mục mới...">
                            <button type="button" class="btn btn-success fw-semibold" onclick="saveQuickCategory('add')">Lưu</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleQuickCategory('add', false)">Hủy</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giá bán ưu đãi (VNĐ)</label>
                        <input type="number" name="price" class="form-control" value="499000" step="10000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giá gốc hiển thị (VNĐ)</label>
                        <input type="number" name="old_price" class="form-control" value="899000" step="10000">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Ảnh bìa khóa học</label>
                        <div class="card p-3 bg-light border">
                            <div class="row align-items-center g-3">
                                <div class="col-auto">
                                    <img id="add_preview_img" src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80" 
                                         style="width: 130px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6;" alt="Xem trước ảnh">
                                </div>
                                <div class="col">
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold mb-1"><i class="bi bi-upload text-primary me-1"></i>Tải ảnh lên từ máy tính:</label>
                                        <input type="file" name="image_file" class="form-control form-control-sm" accept="image/*" onchange="previewImageFile(this, 'add_preview_img')">
                                        <div class="form-text small">Hỗ trợ JPG, PNG, GIF, WEBP (Tối đa 5MB)</div>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1"><i class="bi bi-link-45deg text-secondary me-1"></i>Hoặc nhập liên kết ảnh (URL):</label>
                                        <input type="text" name="image_url" id="add_image_url" class="form-control form-control-sm" placeholder="https://..." value="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80" oninput="previewImageUrl(this, 'add_preview_img')">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Mô tả tóm tắt khóa học</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Nhập lộ trình, đối tượng học viên và mục tiêu đạt được sau khóa học..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Trạng thái khóa học</label>
                        <select name="status" class="form-select">
                            <option value="published" selected>Công khai (Hiển thị cho khách & học viên)</option>
                            <option value="draft">Bản nháp (Ẩn khỏi trang công khai)</option>
                            <option value="hidden">Đang ẩn</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Lưu Khóa Học</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SỬA KHÓA HỌC -->
<div class="modal fade" id="modalEditCourse" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Cập nhật Khóa học</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Tên khóa học <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Danh mục đào tạo</label>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" onclick="toggleQuickCategory('edit')">
                                <i class="bi bi-plus-circle me-1"></i>Thêm danh mục mới
                            </button>
                        </div>
                        <select name="category_id" id="edit_category" class="form-select">
                            <?php foreach ($all_categories as $cat_item): ?>
                                <option value="<?= (int)$cat_item['id'] ?>"><?= htmlspecialchars($cat_item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="edit_cat_inline" class="input-group input-group-sm mt-2 d-none">
                            <input type="text" id="edit_new_cat_name" class="form-control" placeholder="Nhập tên danh mục mới...">
                            <button type="button" class="btn btn-success fw-semibold" onclick="saveQuickCategory('edit')">Lưu</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleQuickCategory('edit', false)">Hủy</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giá bán ưu đãi (VNĐ)</label>
                        <input type="number" name="price" id="edit_price" class="form-control" step="10000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giá gốc hiển thị (VNĐ)</label>
                        <input type="number" name="old_price" id="edit_old_price" class="form-control" step="10000">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Ảnh bìa khóa học</label>
                        <div class="card p-3 bg-light border">
                            <div class="row align-items-center g-3">
                                <div class="col-auto">
                                    <img id="edit_preview_img" src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80" 
                                         style="width: 130px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6;" alt="Xem trước ảnh">
                                </div>
                                <div class="col">
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold mb-1"><i class="bi bi-upload text-primary me-1"></i>Tải ảnh mới từ máy tính (nếu muốn đổi):</label>
                                        <input type="file" name="image_file" id="edit_image_file" class="form-control form-control-sm" accept="image/*" onchange="previewImageFile(this, 'edit_preview_img')">
                                        <div class="form-text small">Hỗ trợ JPG, PNG, GIF, WEBP (Tối đa 5MB)</div>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1"><i class="bi bi-link-45deg text-secondary me-1"></i>Hoặc nhập liên kết ảnh (URL):</label>
                                        <input type="text" name="image_url" id="edit_image_url" class="form-control form-control-sm" placeholder="https://..." oninput="previewImageUrl(this, 'edit_preview_img')">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Mô tả tóm tắt khóa học</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Trạng thái khóa học</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="published">Công khai</option>
                            <option value="draft">Bản nháp</option>
                            <option value="hidden">Đang ẩn</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Lưu Thay Đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORM ẨN ĐỂ XÓA -->
<form id="formDeleteCourse" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="course_id" id="delete_course_id">
</form>

<script>
function previewImageFile(input, previewId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById(previewId);
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewImageUrl(input, previewId) {
    var val = input.value.trim();
    if (val !== '') {
        var img = document.getElementById(previewId);
        if (img) img.src = val;
    }
}

function toggleQuickCategory(prefix, forceShow = null) {
    var box = document.getElementById(prefix + '_cat_inline');
    var input = document.getElementById(prefix + '_new_cat_name');
    if (!box) return;
    var show = (forceShow !== null) ? forceShow : box.classList.contains('d-none');
    box.classList.toggle('d-none', !show);
    if (show && input) {
        input.value = '';
        input.focus();
    }
}

async function saveQuickCategory(prefix) {
    var input = document.getElementById(prefix + '_new_cat_name');
    var name = input ? input.value.trim() : '';
    if (!name) {
        alert('Vui lòng nhập tên danh mục đào tạo mới!');
        if (input) input.focus();
        return;
    }

    try {
        var fd = new FormData();
        fd.append('action', 'quick_add_category');
        fd.append('category_name', name);

        var res = await fetch('ql_khoahoc.php', { method: 'POST', body: fd });
        var data = await res.json();

        if (!data.success) {
            alert(data.message || 'Không thể thêm danh mục mới.');
            return;
        }

        // Thêm option mới vào tất cả select danh mục nếu chưa có
        ['add_category', 'edit_category', 'filter_category'].forEach(function(selId) {
            var sel = document.getElementById(selId);
            if (!sel) return;
            var exists = Array.from(sel.options).some(function(opt) {
                return opt.value.toLowerCase() === data.name.toLowerCase();
            });
            if (!exists) {
                var opt = document.createElement('option');
                opt.value = data.name;
                opt.textContent = (selId === 'filter_category') ? (data.name + ' (0)') : data.name;
                sel.appendChild(opt);
            }
        });

        var targetSelect = document.getElementById(prefix + '_category');
        if (targetSelect) targetSelect.value = data.name;

        toggleQuickCategory(prefix, false);
    } catch (e) {
        alert('Lỗi kết nối khi lưu danh mục mới.');
    }
}

function openEditModal(c) {
    document.getElementById('edit_course_id').value = c.id;
    document.getElementById('edit_title').value = c.title || '';
    document.getElementById('edit_description').value = c.description || '';
    document.getElementById('edit_image_url').value = c.image_url || '';
    document.getElementById('edit_price').value = c.price || 0;
    document.getElementById('edit_old_price').value = c.old_price || 0;

    var editCatSel = document.getElementById('edit_category');
    var catVal = c.category || 'Khóa học khác';
    if (editCatSel) {
        var found = Array.from(editCatSel.options).some(function(opt) { return opt.value === catVal; });
        if (!found && catVal !== '') {
            var extraOpt = document.createElement('option');
            extraOpt.value = catVal;
            extraOpt.textContent = catVal;
            editCatSel.appendChild(extraOpt);
        }
        editCatSel.value = catVal;
    }
    toggleQuickCategory('edit', false);

    document.getElementById('edit_is_active').checked = (parseInt(c.is_active) === 1);

    var fileInput = document.getElementById('edit_image_file');
    if (fileInput) fileInput.value = '';

    var previewImg = document.getElementById('edit_preview_img');
    if (previewImg) {
        var rawUrl = c.image_url || '';
        if (rawUrl) {
            var fullUrl = (rawUrl.startsWith('http://') || rawUrl.startsWith('https://') || rawUrl.startsWith('/')) 
                ? rawUrl 
                : '../' + rawUrl;
            previewImg.src = fullUrl;
        } else {
            previewImg.src = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80';
        }
    }

    var modal = new bootstrap.Modal(document.getElementById('modalEditCourse'));
    modal.show();
}

function confirmDelete(id, title) {
    if (confirm('Bạn có chắc chắn muốn xóa vĩnh viễn khóa học "' + title + '" (ID #' + id + ')?\nThao tác này sẽ xóa toàn bộ chương mục, bài giảng liên quan!')) {
        document.getElementById('delete_course_id').value = id;
        document.getElementById('formDeleteCourse').submit();
    }
}
</script>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

