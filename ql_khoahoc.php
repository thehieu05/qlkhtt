<?php
require_once 'header.php';

// 1. XỬ LÝ THÊM HOẶC CẬP NHẬT KHÓA HỌC (Phần việc của Dương)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['role'] == 1) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc  = mysqli_real_escape_string($conn, $_POST['description']);
    $img   = mysqli_real_escape_string($conn, $_POST['image_url']);
    
    if (!empty($_POST['course_id'])) {
        // Cập nhật khóa học
        $id = intval($_POST['course_id']);
        mysqli_query($conn, "UPDATE courses SET title='$title', description='$desc', image_url='$img' WHERE id=$id");
    } else {
        // Thêm mới khóa học
        mysqli_query($conn, "INSERT INTO courses (title, description, image_url) VALUES ('$title', '$desc', '$img')");
    }
    header("Location: ql_khoahoc.php");
    exit();
}

// 2. XỬ LÝ XÓA KHÓA HỌC (Phần việc của Dương)
if (isset($_GET['delete']) && $_SESSION['role'] == 1) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM courses WHERE id=$id");
    header("Location: ql_khoahoc.php");
    exit();
}

// Lấy thông tin khóa học nếu bấm nút "Sửa"
$edit_course = null;
if (isset($_GET['edit']) && $_SESSION['role'] == 1) {
    $id = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM courses WHERE id=$id");
    $edit_course = mysqli_fetch_assoc($res);
}

// Lấy danh sách toàn bộ khóa học
$courses = mysqli_query($conn, "SELECT * FROM courses ORDER BY id DESC");
?>

<div class="container py-4">
<div class="row">
    <!-- CỘT TRÁI: FORM THÊM / SỬA KHÓA HỌC (Chỉ hiện cho Giảng viên) -->
    <?php if ($_SESSION['role'] == 1): ?>
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white fw-bold">
                <?= $edit_course ? '✏️ SỬA KHÓA HỌC' : '➕ THÊM KHÓA HỌC MỚI' ?>
            </div>
            <div class="card-body">
                <form method="POST" action="ql_khoahoc.php">
                    <input type="hidden" name="course_id" value="<?= $edit_course['id'] ?? '' ?>">
                    <div class="mb-3">
                        <label class="form-label">Tên khóa học</label>
                        <input type="text" name="title" class="form-control" required value="<?= $edit_course['title'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả khóa học</label>
                        <textarea name="description" class="form-control" rows="3" required><?= $edit_course['description'] ?? '' ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link ảnh bìa (URL)</label>
                        <input type="text" name="image_url" class="form-control" placeholder="Dán link ảnh từ mạng..." value="<?= $edit_course['image_url'] ?? '' ?>">
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <?= $edit_course ? 'Lưu cập nhật' : 'Thêm khóa học' ?>
                    </button>
                    <?php if ($edit_course): ?>
                        <a href="ql_khoahoc.php" class="btn btn-secondary w-100 mt-2">Hủy sửa</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- CỘT PHẢI: DANH SÁCH KHÓA HỌC (Phần giao diện của Khải) -->
    <div class="<?= $_SESSION['role'] == 1 ? 'col-md-8' : 'col-md-12' ?>">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">📚 DANH SÁCH KHÓA HỌC HIỆN CÓ</div>
            <div class="card-body">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="100">Ảnh bìa</th>
                            <th>Tên & Mô tả khóa học</th>
                            <th width="160" class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($courses)): ?>
                        <tr>
                            <td>
                                <img src="<?= $row['image_url'] ?: 'https://via.placeholder.com/100x60' ?>" class="img-thumbnail" style="width: 90px; height: 60px; object-fit: cover;">
                            </td>
                            <td>
                                <h6 class="mb-1 text-primary fw-bold"><?= htmlspecialchars($row['title']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($row['description']) ?></small>
                            </td>
                            <td class="text-center">
                                <?php if ($_SESSION['role'] == 1): ?>
                                    <a href="ql_khoahoc.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Sửa</a>
                                    <a href="ql_khoahoc.php?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Xóa khóa học này?')">Xóa</a>
                                <?php else: ?>
                                    <a href="#" class="btn btn-primary btn-sm">Xem chi tiết</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>