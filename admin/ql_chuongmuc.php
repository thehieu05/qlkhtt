<?php
require_once '../config.php';
require_once 'db_helper.php';
init_admin_database($conn);

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Quản lý Chương mục & Bài giảng';
$active_nav = 'chuongmuc';

// Lấy danh sách khóa học để chọn
$courses_res = $conn->query("SELECT id, title FROM courses ORDER BY id DESC");
$courses_list = [];
if ($courses_res) {
    while ($r = $courses_res->fetch_assoc()) $courses_list[] = $r;
}

// Khóa học đang được chọn
$selected_course_id = (int)($_GET['course_id'] ?? 0);
if ($selected_course_id === 0 && !empty($courses_list)) {
    $selected_course_id = (int)$courses_list[0]['id'];
}

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Thêm chương mục mới
    if ($action === 'add_chapter') {
        $c_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 1);

        if (!empty($title) && $c_id > 0) {
            $s_title = $conn->real_escape_string($title);
            $conn->query("INSERT INTO chapters (course_id, title, sort_order) VALUES ($c_id, '$s_title', $sort_order)");
            $_SESSION['flash_msg'] = "Đã thêm chương mới: <b>" . htmlspecialchars($title) . "</b>!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 2. Sửa chương mục
    if ($action === 'edit_chapter') {
        $ch_id = (int)($_POST['chapter_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 1);

        if (!empty($title) && $ch_id > 0) {
            $s_title = $conn->real_escape_string($title);
            $conn->query("UPDATE chapters SET title = '$s_title', sort_order = $sort_order WHERE id = $ch_id");
            $_SESSION['flash_msg'] = "Đã cập nhật chương mục!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 3. Xóa chương mục
    if ($action === 'delete_chapter') {
        $ch_id = (int)($_POST['chapter_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        if ($ch_id > 0) {
            $conn->query("DELETE FROM lessons WHERE chapter_id = $ch_id");
            $conn->query("DELETE FROM chapters WHERE id = $ch_id");
            $_SESSION['flash_msg'] = "Đã xóa chương mục và các bài giảng bên trong!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 4. Thêm bài giảng
    if ($action === 'add_lesson') {
        $ch_id = (int)($_POST['chapter_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 15);
        $sort_order = (int)($_POST['sort_order'] ?? 1);
        $is_free = isset($_POST['is_free']) ? 1 : 0;

        if (!empty($title) && $ch_id > 0 && $c_id > 0) {
            $s_title = $conn->real_escape_string($title);
            $s_vid   = $conn->real_escape_string($video_url);
            $s_cnt   = $conn->real_escape_string($content);
            $conn->query("INSERT INTO lessons (chapter_id, course_id, title, video_url, content, duration_minutes, sort_order, is_free)
                VALUES ($ch_id, $c_id, '$s_title', '$s_vid', '$s_cnt', $duration, $sort_order, $is_free)");
            $_SESSION['flash_msg'] = "Đã thêm bài giảng: <b>" . htmlspecialchars($title) . "</b>!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 5. Sửa bài giảng
    if ($action === 'edit_lesson') {
        $lesson_id = (int)($_POST['lesson_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 15);
        $sort_order = (int)($_POST['sort_order'] ?? 1);
        $is_free = isset($_POST['is_free']) ? 1 : 0;

        if (!empty($title) && $lesson_id > 0) {
            $s_title = $conn->real_escape_string($title);
            $s_vid   = $conn->real_escape_string($video_url);
            $s_cnt   = $conn->real_escape_string($content);
            $conn->query("UPDATE lessons SET title = '$s_title', video_url = '$s_vid', content = '$s_cnt', 
                duration_minutes = $duration, sort_order = $sort_order, is_free = $is_free WHERE id = $lesson_id");
            $_SESSION['flash_msg'] = "Đã cập nhật bài giảng!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 6. Xóa bài giảng
    if ($action === 'delete_lesson') {
        $lesson_id = (int)($_POST['lesson_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        if ($lesson_id > 0) {
            $conn->query("DELETE FROM lessons WHERE id = $lesson_id");
            $_SESSION['flash_msg'] = "Đã xóa bài giảng!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }
}

// Lấy thông tin khóa học hiện tại
$current_course = null;
if ($selected_course_id > 0) {
    $res = $conn->query("SELECT * FROM courses WHERE id = $selected_course_id");
    if ($res && $res->num_rows > 0) $current_course = $res->fetch_assoc();
}

// Lấy danh sách chương và bài giảng
$chapters = [];
if ($selected_course_id > 0) {
    $ch_res = $conn->query("SELECT * FROM chapters WHERE course_id = $selected_course_id ORDER BY sort_order ASC, id ASC");
    if ($ch_res) {
        while ($ch = $ch_res->fetch_assoc()) {
            $ch_id = (int)$ch['id'];
            $lessons_res = $conn->query("SELECT * FROM lessons WHERE chapter_id = $ch_id ORDER BY sort_order ASC, id ASC");
            $lessons = [];
            if ($lessons_res) {
                while ($ls = $lessons_res->fetch_assoc()) $lessons[] = $ls;
            }
            $ch['lessons'] = $lessons;
            $chapters[] = $ch;
        }
    }
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Chương mục & Bài giảng (US-03)</h4>
        <p>Soạn đề cương, sắp xếp thứ tự chương học, cập nhật video bài giảng và tài liệu học tập</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddChapter">
            <i class="bi bi-plus-circle me-1"></i> Thêm Chương Mục Mới
        </button>
    </div>
</div>

<!-- CHỌN KHÓA HỌC ĐỂ SOẠN GIÁO TRÌNH -->
<div class="table-card p-3 mb-4">
    <div class="row align-items-center g-2">
        <div class="col-md-3">
            <label class="form-label mb-0 fw-bold"><i class="bi bi-book-half text-primary me-1"></i> Khóa học đang chọn:</label>
        </div>
        <div class="col-md-7">
            <select class="form-select fw-semibold" onchange="window.location.href='ql_chuongmuc.php?course_id=' + this.value">
                <?php foreach ($courses_list as $co): ?>
                    <option value="<?= $co['id'] ?>" <?= $co['id'] == $selected_course_id ? 'selected' : '' ?>>
                        #<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 text-md-end">
            <a href="ql_khoahoc.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-gear me-1"></i> Sửa khóa
            </a>
        </div>
    </div>
</div>

<!-- NỘI DUNG GIÁO TRÌNH CÁC CHƯƠNG -->
<?php if (empty($chapters)): ?>
    <div class="table-card p-5 text-center">
        <i class="bi bi-journals text-muted" style="font-size: 3rem;"></i>
        <h5 class="fw-bold text-dark mt-3">Chưa có chương mục nào</h5>
        <p class="text-muted small">Khóa học này chưa được tạo nội dung bài giảng. Bấm nút bên dưới để tạo chương đầu tiên!</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddChapter">
            <i class="bi bi-plus-circle me-1"></i> Tạo Chương Mục Đầu Tiên
        </button>
    </div>
<?php else: ?>
    <?php foreach ($chapters as $idx => $ch): ?>
        <div class="table-card mb-4">
            <div class="table-card-header bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary fs-6 px-2.5 py-1">Chương <?= $idx + 1 ?></span>
                    <span class="title fs-6 fw-bold text-dark"><?= htmlspecialchars($ch['title']) ?></span>
                    <span class="badge bg-secondary-subtle text-secondary small">(Thứ tự: <?= (int)$ch['sort_order'] ?>)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-success" onclick="openAddLessonModal(<?= $ch['id'] ?>, '<?= htmlspecialchars(addslashes($ch['title'])) ?>')">
                        <i class="bi bi-plus-lg me-1"></i> Thêm Bài giảng
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick='openEditChapterModal(<?= json_encode($ch) ?>)'>
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteChapter(<?= $ch['id'] ?>, '<?= htmlspecialchars(addslashes($ch['title'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Thứ tự</th>
                            <th>Tên bài giảng</th>
                            <th>Thời lượng</th>
                            <th>Video / Tài liệu</th>
                            <th>Học thử</th>
                            <th class="text-end" style="width: 140px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ch['lessons'])): ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted">Chưa có bài giảng nào trong chương này. Hãy bấm "Thêm Bài giảng".</td></tr>
                        <?php else: foreach ($ch['lessons'] as $l_idx => $ls): ?>
                            <tr>
                                <td class="fw-bold text-muted text-center"><?= (int)$ls['sort_order'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><i class="bi bi-play-circle text-primary me-2"></i><?= htmlspecialchars($ls['title']) ?></div>
                                    <?php if (!empty($ls['content'])): ?>
                                        <small class="text-muted text-truncate d-block" style="max-width: 350px;"><?= htmlspecialchars(mb_substr($ls['content'], 0, 70)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-clock me-1"></i><?= (int)$ls['duration_minutes'] ?> phút
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($ls['video_url'])): ?>
                                        <a href="<?= htmlspecialchars($ls['video_url']) ?>" target="_blank" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 0.75rem;">
                                            <i class="bi bi-youtube me-1"></i> Link Video
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Tài liệu đọc</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($ls['is_free'] ?? 0) == 1): ?>
                                        <span class="badge bg-success-subtle text-success">Miễn phí</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary">Khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick='openEditLessonModal(<?= json_encode($ls) ?>)' title="Sửa bài giảng">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="confirmDeleteLesson(<?= $ls['id'] ?>, '<?= htmlspecialchars(addslashes($ls['title'])) ?>')" title="Xóa">
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
    <?php endforeach; ?>
<?php endif; ?>

<!-- MODAL THÊM CHƯƠNG MỤC -->
<div class="modal fade" id="modalAddChapter" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_chapter">
                <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Thêm Chương Mục Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tên chương mục <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Ví dụ: Chương 1: Căn bản lập trình">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Thứ tự hiển thị</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= count($chapters) + 1 ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Chương Mục</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SỬA CHƯƠNG MỤC -->
<div class="modal fade" id="modalEditChapter" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_chapter">
                <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
                <input type="hidden" name="chapter_id" id="edit_chapter_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Sửa Chương Mục</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tên chương mục</label>
                        <input type="text" name="title" id="edit_chapter_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Thứ tự hiển thị</label>
                        <input type="number" name="sort_order" id="edit_chapter_sort" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL THÊM BÀI GIẢNG -->
<div class="modal fade" id="modalAddLesson" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_lesson">
                <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
                <input type="hidden" name="chapter_id" id="add_lesson_chapter_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-play-circle text-primary me-2"></i>Thêm Bài Giảng Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <div class="text-muted small mb-1">Đang thêm vào: <b id="add_lesson_chapter_name" class="text-dark"></b></div>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Tên bài giảng <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Ví dụ: Bài 1: Cài đặt công cụ và biến môi trường">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Thứ tự</label>
                        <input type="number" name="sort_order" class="form-control" value="1">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Link Video (YouTube / MP4 / Vimeo)</label>
                        <input type="text" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Thời lượng (Phút)</label>
                        <input type="number" name="duration_minutes" class="form-control" value="15">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Tóm tắt / Nội dung bài học</label>
                        <textarea name="content" class="form-control" rows="3" placeholder="Nhập ghi chú quan trọng, link tài liệu hoặc bài tập kèm theo..."></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_free" id="chkIsFree">
                            <label class="form-check-label fw-semibold" for="chkIsFree">Cho phép học viên xem thử miễn phí (Preview)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Bài Giảng</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SỬA BÀI GIẢNG -->
<div class="modal fade" id="modalEditLesson" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_lesson">
                <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
                <input type="hidden" name="lesson_id" id="edit_lesson_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Sửa Bài Giảng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-9">
                        <label class="form-label">Tên bài giảng <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_lesson_title" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Thứ tự</label>
                        <input type="number" name="sort_order" id="edit_lesson_sort" class="form-control">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Link Video</label>
                        <input type="text" name="video_url" id="edit_lesson_video" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Thời lượng (Phút)</label>
                        <input type="number" name="duration_minutes" id="edit_lesson_duration" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Tóm tắt / Nội dung</label>
                        <textarea name="content" id="edit_lesson_content" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_free" id="edit_lesson_isfree">
                            <label class="form-check-label fw-semibold" for="edit_lesson_isfree">Cho phép xem thử miễn phí</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Thay Đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORM ẨN XỬ LÝ XÓA -->
<form id="formActionDelete" method="POST" style="display:none;">
    <input type="hidden" name="action" id="del_action">
    <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
    <input type="hidden" name="chapter_id" id="del_chapter_id">
    <input type="hidden" name="lesson_id" id="del_lesson_id">
</form>

<script>
function openEditChapterModal(ch) {
    document.getElementById('edit_chapter_id').value = ch.id;
    document.getElementById('edit_chapter_title').value = ch.title;
    document.getElementById('edit_chapter_sort').value = ch.sort_order;
    new bootstrap.Modal(document.getElementById('modalEditChapter')).show();
}

function openAddLessonModal(chId, chTitle) {
    document.getElementById('add_lesson_chapter_id').value = chId;
    document.getElementById('add_lesson_chapter_name').innerText = chTitle;
    new bootstrap.Modal(document.getElementById('modalAddLesson')).show();
}

function openEditLessonModal(ls) {
    document.getElementById('edit_lesson_id').value = ls.id;
    document.getElementById('edit_lesson_title').value = ls.title;
    document.getElementById('edit_lesson_sort').value = ls.sort_order;
    document.getElementById('edit_lesson_video').value = ls.video_url || '';
    document.getElementById('edit_lesson_duration').value = ls.duration_minutes || 15;
    document.getElementById('edit_lesson_content').value = ls.content || '';
    document.getElementById('edit_lesson_isfree').checked = (parseInt(ls.is_free) === 1);
    new bootstrap.Modal(document.getElementById('modalEditLesson')).show();
}

function confirmDeleteChapter(chId, chTitle) {
    if (confirm('Bạn có chắc chắn muốn xóa chương "' + chTitle + '"?\nTẤT CẢ BÀI GIẢNG bên trong cũng sẽ bị xóa vĩnh viễn!')) {
        document.getElementById('del_action').value = 'delete_chapter';
        document.getElementById('del_chapter_id').value = chId;
        document.getElementById('formActionDelete').submit();
    }
}

function confirmDeleteLesson(lsId, lsTitle) {
    if (confirm('Bạn có chắc muốn xóa bài giảng "' + lsTitle + '"?')) {
        document.getElementById('del_action').value = 'delete_lesson';
        document.getElementById('del_lesson_id').value = lsId;
        document.getElementById('formActionDelete').submit();
    }
}
</script>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

