<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../category_helper.php';

require_admin('../login.php');

$page_title = 'Quản lý Danh mục Môn học';
$active_nav = 'danhmuc';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $cat_id      = (int)($_POST['cat_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'bi-folder');
        $description = trim($_POST['description'] ?? '');
        $sort_order  = (int)($_POST['sort_order'] ?? 0);
        $status      = ($_POST['status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';

        if ($name === '') {
            set_flash('Tên danh mục không được để trống!', 'danger');
        } else {
            $slug = slugify_category($name);
            if ($action === 'edit' && $cat_id > 0) {
                $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, description = ?, sort_order = ?, status = ? WHERE id = ?");
                $stmt->bind_param('ssssisi', $name, $slug, $icon, $description, $sort_order, $status, $cat_id);
                $stmt->execute();
                $stmt->close();
                set_flash('Đã cập nhật danh mục thành công!', 'success');
            } else {
                $res = create_course_category($conn, $name, $icon, $description);
                set_flash($res['message'], $res['success'] ? 'success' : 'danger');
            }
        }
        header("Location: ql_danhmuc.php");
        exit();
    }

    if ($action === 'toggle_status') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        $new_status = ($_POST['new_status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';
        if ($cat_id > 0) {
            $stmt = $conn->prepare("UPDATE categories SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $new_status, $cat_id);
            $stmt->execute();
            $stmt->close();
            set_flash($new_status === 'hidden' ? 'Đã ẩn danh mục.' : 'Đã hiển thị danh mục.', 'success');
        }
        header("Location: ql_danhmuc.php");
        exit();
    }

    if ($action === 'delete') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        if ($cat_id > 0) {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param('i', $cat_id);
            $stmt->execute();
            $stmt->close();
            set_flash('Đã xóa danh mục môn học.', 'success');
        }
        header("Location: ql_danhmuc.php");
        exit();
    }
}

$categories = get_all_categories($conn);
include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Danh mục Môn học</h4>
        <p>Thêm, sửa, ẩn/hiện hoặc xóa các danh mục khóa học</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick="resetCatForm()">
        <i class="bi bi-plus-circle me-1"></i> Thêm danh mục
    </button>
</div>

<div class="table-card">
    <div class="table-card-header">
        <div class="title">Danh sách Danh mục (<?= count($categories) ?>)</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên danh mục</th>
                    <th>Slug</th>
                    <th>Mô tả</th>
                    <th>Số khóa học</th>
                    <th>Thứ tự</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): foreach ($categories as $cat): ?>
                <tr>
                    <td class="text-muted small">#<?= (int)$cat['id'] ?></td>
                    <td class="fw-semibold">
                        <i class="bi <?= e($cat['icon']) ?> text-primary me-1"></i><?= e($cat['name']) ?>
                    </td>
                    <td class="small text-muted font-monospace"><?= e($cat['slug']) ?></td>
                    <td class="small text-secondary"><?= e($cat['description'] ?? '') ?></td>
                    <td><span class="badge bg-light text-dark border"><?= (int)$cat['course_count'] ?> khóa</span></td>
                    <td><?= (int)$cat['sort_order'] ?></td>
                    <td>
                        <?php if ($cat['status'] === 'active'): ?>
                            <span class="badge-active">Hiển thị</span>
                        <?php else: ?>
                            <span class="badge-inactive">Đang ẩn</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick='editCat(<?= json_encode($cat, JSON_UNESCAPED_UNICODE) ?>)'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $cat['status'] === 'active' ? 'hidden' : 'active' ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning" title="<?= $cat['status'] === 'active' ? 'Ẩn' : 'Hiện' ?>">
                                <i class="bi bi-<?= $cat['status'] === 'active' ? 'eye-slash' : 'eye' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa danh mục này? Các khóa học thuộc danh mục sẽ không bị xóa.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">Chưa có danh mục nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm / Sửa Danh mục -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="catModalTitle">Thêm danh mục mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="catAction" value="add">
                <input type="hidden" name="cat_id" id="catId" value="0">
                <div class="mb-3">
                    <label class="form-label">Tên danh mục *</label>
                    <input type="text" name="name" id="catName" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Icon (Bootstrap Icons)</label>
                        <input type="text" name="icon" id="catIcon" class="form-control" value="bi-folder">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Thứ tự</label>
                        <input type="number" name="sort_order" id="catOrder" class="form-control" value="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" id="catStatus" class="form-select">
                            <option value="active">Hiển thị</option>
                            <option value="hidden">Ẩn</option>
                        </select>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Mô tả</label>
                    <textarea name="description" id="catDesc" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu danh mục</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetCatForm() {
    document.getElementById('catModalTitle').innerText = 'Thêm danh mục mới';
    document.getElementById('catAction').value = 'add';
    document.getElementById('catId').value = '0';
    document.getElementById('catName').value = '';
    document.getElementById('catIcon').value = 'bi-folder';
    document.getElementById('catOrder').value = '1';
    document.getElementById('catStatus').value = 'active';
    document.getElementById('catDesc').value = '';
}
function editCat(cat) {
    document.getElementById('catModalTitle').innerText = 'Cập nhật danh mục';
    document.getElementById('catAction').value = 'edit';
    document.getElementById('catId').value = cat.id;
    document.getElementById('catName').value = cat.name;
    document.getElementById('catIcon').value = cat.icon || 'bi-folder';
    document.getElementById('catOrder').value = cat.sort_order || 1;
    document.getElementById('catStatus').value = cat.status || 'active';
    document.getElementById('catDesc').value = cat.description || '';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
</script>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
