<?php
require_once '../config.php';
require_once 'db_helper.php';
init_admin_database($conn);

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Quản lý Khóa học';
$active_nav = 'khoahoc';

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $course_id   = (int)($_POST['course_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url   = trim($_POST['image_url'] ?? '');
        $price       = floatval($_POST['price'] ?? 0);
        $old_price   = floatval($_POST['old_price'] ?? 0);
        $level       = trim($_POST['level'] ?? 'Cơ bản đến Nâng cao');
        $category    = trim($_POST['category'] ?? 'Lập trình');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if (empty($image_url)) {
            $image_url = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80';
        }

        if (empty($title)) {
            $_SESSION['flash_msg'] = "Vui lòng nhập tên khóa học!";
            $_SESSION['flash_type'] = "danger";
        } else {
            $s_title = $conn->real_escape_string($title);
            $s_desc  = $conn->real_escape_string($description);
            $s_img   = $conn->real_escape_string($image_url);
            $s_level = $conn->real_escape_string($level);
            $s_cat   = $conn->real_escape_string($category);

            if ($action === 'edit' && $course_id > 0) {
                $sql = "UPDATE courses SET 
                        title = '$s_title', 
                        description = '$s_desc', 
                        image_url = '$s_img', 
                        price = $price, 
                        old_price = $old_price, 
                        level = '$s_level', 
                        category = '$s_cat', 
                        is_active = $is_active 
                        WHERE id = $course_id";
                if ($conn->query($sql)) {
                    $_SESSION['flash_msg'] = "Cập nhật thành công khóa học: <b>" . htmlspecialchars($title) . "</b>!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $_SESSION['flash_msg'] = "Lỗi khi cập nhật: " . $conn->error;
                    $_SESSION['flash_type'] = "danger";
                }
            } else {
                $sql = "INSERT INTO courses (title, description, image_url, price, old_price, level, category, is_active) 
                        VALUES ('$s_title', '$s_desc', '$s_img', $price, $old_price, '$s_level', '$s_cat', $is_active)";
                if ($conn->query($sql)) {
                    $new_id = $conn->insert_id;
                    $_SESSION['flash_msg'] = "Thêm mới thành công khóa học (#$new_id): <b>" . htmlspecialchars($title) . "</b>!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $_SESSION['flash_msg'] = "Lỗi khi thêm khóa học: " . $conn->error;
                    $_SESSION['flash_type'] = "danger";
                }
            }
        }
        header("Location: ql_khoahoc.php");
        exit();
    }

    if ($action === 'delete') {
        $del_id = (int)($_POST['course_id'] ?? 0);
        if ($del_id > 0) {
            $del_res = $conn->query("DELETE FROM courses WHERE id = $del_id");
            if ($del_res) {
                $_SESSION['flash_msg'] = "Đã xóa vĩnh viễn khóa học #$del_id!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "Lỗi khi xóa: " . $conn->error;
                $_SESSION['flash_type'] = "danger";
            }
        }
        header("Location: ql_khoahoc.php");
        exit();
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['course_id'] ?? 0);
        $conn->query("UPDATE courses SET is_active = IF(is_active=1, 0, 1) WHERE id = $id");
        $_SESSION['flash_msg'] = "Đã thay đổi trạng thái kích hoạt khóa học #$id!";
        $_SESSION['flash_type'] = "success";
        header("Location: ql_khoahoc.php");
        exit();
    }
}

// Tìm kiếm & lọc
$q = trim($_GET['q'] ?? '');
$cat_filter = trim($_GET['cat'] ?? '');

$where = "WHERE 1=1";
if (!empty($q)) {
    $sq = $conn->real_escape_string($q);
    $where .= " AND (title LIKE '%$sq%' OR description LIKE '%$sq%')";
}
if (!empty($cat_filter)) {
    $sc = $conn->real_escape_string($cat_filter);
    $where .= " AND category = '$sc'";
}

$courses_res = $conn->query("SELECT c.*, 
    (SELECT COUNT(*) FROM chapters WHERE course_id = c.id) as chapter_count,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
    (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
    (SELECT COUNT(*) FROM exams WHERE course_id = c.id) as exam_count
    FROM courses c $where ORDER BY c.id DESC");

$courses = [];
if ($courses_res) {
    while ($r = $courses_res->fetch_assoc()) $courses[] = $r;
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Khóa học (US-02 & US-04)</h4>
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
            <select name="cat" class="form-select">
                <option value="">-- Tất cả danh mục --</option>
                <option value="Lập trình" <?= $cat_filter === 'Lập trình' ? 'selected' : '' ?>>Lập trình</option>
                <option value="Thiết kế" <?= $cat_filter === 'Thiết kế' ? 'selected' : '' ?>>Thiết kế UI/UX</option>
                <option value="Marketing" <?= $cat_filter === 'Marketing' ? 'selected' : '' ?>>Marketing Online</option>
                <option value="Ngoại ngữ" <?= $cat_filter === 'Ngoại ngữ' ? 'selected' : '' ?>>Ngoại ngữ</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Lọc</button>
            <?php if (!empty($q) || !empty($cat_filter)): ?>
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
                    <th>Danh mục & Cấp độ</th>
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
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?= htmlspecialchars($c['image_url']) ?>" style="width: 60px; height: 42px; object-fit: cover; border-radius: 6px;">
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($c['title']) ?></div>
                                    <div class="text-muted small text-truncate" style="max-width: 250px;">
                                        <?= htmlspecialchars(mb_substr($c['description'] ?? '', 0, 80)) ?>...
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($c['category'] ?? 'Lập trình') ?></span>
                            <div class="text-muted small mt-1"><?= htmlspecialchars($c['level'] ?? 'Cơ bản') ?></div>
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
                            <div class="text-muted small"><i class="bi bi-clipboard2-check me-1"></i><?= (int)$c['exam_count'] ?> đề thi</div>
                        </td>
                        <td>
                            <span class="badge bg-success-subtle text-success fs-6 fw-bold">
                                <i class="bi bi-people me-1"></i><?= (int)$c['student_count'] ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= ($c['is_active'] ?? 1) == 1 ? 'btn-success' : 'btn-secondary' ?> rounded-pill" style="font-size: 0.75rem; padding: 2px 10px;">
                                    <?= ($c['is_active'] ?? 1) == 1 ? 'Hiển thị' : 'Ẩn' ?>
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
            <form method="POST">
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
                    <div class="col-md-6">
                        <label class="form-label">Danh mục đào tạo</label>
                        <select name="category" class="form-select">
                            <option value="Lập trình">Lập trình Công nghệ thông tin</option>
                            <option value="Thiết kế">Thiết kế Đồ họa & UI/UX</option>
                            <option value="Marketing">Marketing Online & Kinh doanh</option>
                            <option value="Ngoại ngữ">Ngoại ngữ & Kỹ năng mềm</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cấp độ học viên</label>
                        <select name="level" class="form-select">
                            <option value="Cơ bản cho người mới">Cơ bản cho người mới</option>
                            <option value="Trung cấp">Trung cấp</option>
                            <option value="Nâng cao & Chuyên sâu">Nâng cao & Chuyên sâu</option>
                            <option value="Mọi cấp độ (All levels)">Mọi cấp độ (All levels)</option>
                        </select>
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
                        <label class="form-label">Link ảnh bìa (URL)</label>
                        <input type="text" name="image_url" class="form-control" placeholder="https://images.unsplash.com/..." value="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Mô tả tóm tắt khóa học</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Nhập lộ trình, đối tượng học viên và mục tiêu đạt được sau khóa học..."></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="chkActiveAdd" checked>
                            <label class="form-check-label fw-semibold" for="chkActiveAdd">Kích hoạt hiển thị cho học viên đăng ký ngay</label>
                        </div>
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
            <form method="POST">
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
                    <div class="col-md-6">
                        <label class="form-label">Danh mục đào tạo</label>
                        <select name="category" id="edit_category" class="form-select">
                            <option value="Lập trình">Lập trình Công nghệ thông tin</option>
                            <option value="Thiết kế">Thiết kế Đồ họa & UI/UX</option>
                            <option value="Marketing">Marketing Online & Kinh doanh</option>
                            <option value="Ngoại ngữ">Ngoại ngữ & Kỹ năng mềm</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cấp độ học viên</label>
                        <select name="level" id="edit_level" class="form-select">
                            <option value="Cơ bản cho người mới">Cơ bản cho người mới</option>
                            <option value="Trung cấp">Trung cấp</option>
                            <option value="Nâng cao & Chuyên sâu">Nâng cao & Chuyên sâu</option>
                            <option value="Mọi cấp độ (All levels)">Mọi cấp độ (All levels)</option>
                        </select>
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
                        <label class="form-label">Link ảnh bìa (URL)</label>
                        <input type="text" name="image_url" id="edit_image_url" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Mô tả tóm tắt khóa học</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                            <label class="form-check-label fw-semibold" for="edit_is_active">Kích hoạt hiển thị cho học viên</label>
                        </div>
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
function openEditModal(c) {
    document.getElementById('edit_course_id').value = c.id;
    document.getElementById('edit_title').value = c.title || '';
    document.getElementById('edit_description').value = c.description || '';
    document.getElementById('edit_image_url').value = c.image_url || '';
    document.getElementById('edit_price').value = c.price || 0;
    document.getElementById('edit_old_price').value = c.old_price || 0;
    document.getElementById('edit_category').value = c.category || 'Lập trình';
    document.getElementById('edit_level').value = c.level || 'Cơ bản cho người mới';
    document.getElementById('edit_is_active').checked = (parseInt(c.is_active) === 1);

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

