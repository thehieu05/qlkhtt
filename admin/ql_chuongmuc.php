<?php
require_once __DIR__ . '/../config.php';

require_admin('../login.php');

$page_title = 'Quản lý Chương & Bài giảng';
$active_nav = 'chuongmuc';

// Lấy danh sách khóa học
$courses_res = $conn->query("SELECT id, title FROM courses ORDER BY id DESC");
$courses_list = [];
if ($courses_res) {
    while ($r = $courses_res->fetch_assoc()) $courses_list[] = $r;
}

$selected_course_id = (int)($_GET['course_id'] ?? 0);
if ($selected_course_id === 0 && !empty($courses_list)) {
    $selected_course_id = (int)$courses_list[0]['id'];
}

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Thêm chương mới
    if ($action === 'add_chapter') {
        $c_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 1);

        if (!empty($title) && $c_id > 0) {
            $stmt = $conn->prepare("INSERT INTO sections (course_id, title, sort_order) VALUES (?, ?, ?)");
            $stmt->bind_param('isi', $c_id, $title, $sort_order);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã thêm chương mới: <b>" . e($title) . "</b>!", "success");
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 2. Sửa chương
    if ($action === 'edit_chapter') {
        $ch_id = (int)($_POST['chapter_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 1);

        if (!empty($title) && $ch_id > 0) {
            $stmt = $conn->prepare("UPDATE sections SET title = ?, sort_order = ? WHERE id = ?");
            $stmt->bind_param('sii', $title, $sort_order, $ch_id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã cập nhật chương!", "success");
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 3. Xóa chương
    if ($action === 'delete_chapter') {
        $ch_id = (int)($_POST['chapter_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        if ($ch_id > 0) {
            $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->bind_param('i', $ch_id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã xóa chương và các bài giảng bên trong!", "success");
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 4. Thêm bài giảng
    if ($action === 'add_lesson') {
        $sec_id = (int)($_POST['chapter_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $document_url = trim($_POST['document_url'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 15);
        $sort_order = (int)($_POST['sort_order'] ?? 1);
        $is_preview = isset($_POST['is_preview']) || isset($_POST['is_free']) ? 1 : 0;

        // Upload tài liệu nếu có
        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $d_tmp = $_FILES['doc_file']['tmp_name'];
            $d_name = $_FILES['doc_file']['name'];
            $d_ext = strtolower(pathinfo($d_name, PATHINFO_EXTENSION));
            $d_dir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($d_dir)) mkdir($d_dir, 0777, true);
            $new_d_name = 'doc_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $d_ext;
            if (move_uploaded_file($d_tmp, $d_dir . $new_d_name)) {
                $document_url = 'uploads/documents/' . $new_d_name;
            }
        }

        if (!empty($title) && $sec_id > 0 && $c_id > 0) {
            $stmt = $conn->prepare("INSERT INTO lessons (course_id, section_id, title, content, video_url, document_url, duration_minutes, sort_order, is_preview)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissssiii', $c_id, $sec_id, $title, $content, $video_url, $document_url, $duration, $sort_order, $is_preview);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã thêm bài giảng: <b>" . e($title) . "</b>!", "success");
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 5. Sửa bài giảng
    if ($action === 'edit_lesson') {
        $lesson_id = (int)($_POST['lesson_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        $sec_id = (int)($_POST['chapter_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $document_url = trim($_POST['document_url'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 15);
        $sort_order = (int)($_POST['sort_order'] ?? 1);
        $is_preview = isset($_POST['is_preview']) || isset($_POST['is_free']) ? 1 : 0;

        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $d_tmp = $_FILES['doc_file']['tmp_name'];
            $d_name = $_FILES['doc_file']['name'];
            $d_ext = strtolower(pathinfo($d_name, PATHINFO_EXTENSION));
            $d_dir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($d_dir)) mkdir($d_dir, 0777, true);
            $new_d_name = 'doc_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $d_ext;
            if (move_uploaded_file($d_tmp, $d_dir . $new_d_name)) {
                $document_url = 'uploads/documents/' . $new_d_name;
            }
        }

        if (!empty($title) && $lesson_id > 0) {
            $stmt = $conn->prepare("UPDATE lessons SET section_id = ?, title = ?, content = ?, video_url = ?, document_url = ?, duration_minutes = ?, sort_order = ?, is_preview = ? WHERE id = ?");
            $stmt->bind_param('issssiiii', $sec_id, $title, $content, $video_url, $document_url, $duration, $sort_order, $is_preview, $lesson_id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã cập nhật bài giảng!", "success");
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }

    // 6. Xóa bài giảng
    if ($action === 'delete_lesson') {
        $lesson_id = (int)($_POST['lesson_id'] ?? 0);
        $c_id = (int)($_POST['course_id'] ?? 0);
        if ($lesson_id > 0) {
            $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
            $stmt->bind_param('i', $lesson_id);
            $stmt->execute();
            $stmt->close();
            set_flash("Đã xóa bài giảng!", "success");
        }
        header("Location: ql_chuongmuc.php?course_id=$c_id");
        exit();
    }
}

// Lấy danh sách chương & bài giảng của khóa học được chọn
$chapters = [];
if ($selected_course_id > 0) {
    $stmt_sec = $conn->prepare("SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt_sec->bind_param('i', $selected_course_id);
    $stmt_sec->execute();
    $sec_res = $stmt_sec->get_result();
    $stmt_ls = $conn->prepare("SELECT * FROM lessons WHERE section_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt_qz = $conn->prepare("SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count, (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) AS attempt_count FROM quizzes q WHERE q.section_id = ? ORDER BY q.id ASC");
    while ($sec = $sec_res->fetch_assoc()) {
        $sid = (int)$sec['id'];
        $sec['lessons'] = [];
        $sec['quizzes'] = [];
        $stmt_ls->bind_param('i', $sid);
        $stmt_ls->execute();
        $ls_res = $stmt_ls->get_result();
        while ($l = $ls_res->fetch_assoc()) {
            $sec['lessons'][] = $l;
        }
        if ($stmt_qz) {
            $stmt_qz->bind_param('i', $sid);
            $stmt_qz->execute();
            $qz_res = $stmt_qz->get_result();
            while ($q = $qz_res->fetch_assoc()) {
                $sec['quizzes'][] = $q;
            }
        }
        $chapters[] = $sec;
    }
    if ($stmt_qz) $stmt_qz->close();
    $stmt_ls->close();
    $stmt_sec->close();
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Chương & Bài giảng</h4>
        <p>Quản lý cấu trúc chương học, bài giảng video, tài liệu đính kèm và thứ tự hiển thị</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddChapter">
            <i class="bi bi-folder-plus me-1"></i> Thêm Chương Mới
        </button>
    </div>
</div>

<!-- CHỌN KHÓA HỌC -->
<div class="table-card mb-4 p-3">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-8">
            <label class="form-label small fw-semibold text-muted mb-1">Khóa học đang quản lý:</label>
            <select name="course_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($courses_list as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selected_course_id === (int)$c['id'] ? 'selected' : '' ?>>
                        #<?= (int)$c['id'] ?> - <?= e($c['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <a href="ql_khoahoc.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-left me-1"></i> Quay lại Khóa học
            </a>
        </div>
    </form>
</div>

<!-- DANH SÁCH CHƯƠNG VÀ BÀI HỌC -->
<?php if (empty($chapters)): ?>
    <div class="table-card p-5 text-center text-muted">
        <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
        Khóa học này chưa có chương mục nào. Hãy bấm <b>"Thêm Chương Mới"</b> để bắt đầu xây dựng giáo trình!
    </div>
<?php else: ?>
    <?php foreach ($chapters as $ch_idx => $ch): ?>
        <div class="table-card mb-4">
            <div class="table-card-header bg-light d-flex justify-content-between align-items-center">
                <div class="title fw-bold text-dark">
                    <span class="badge bg-primary me-2">Thứ tự: <?= (int)$ch['sort_order'] ?></span>
                    <i class="bi bi-folder2-open text-warning me-1"></i> <?= e($ch['title']) ?>
                    <span class="text-muted small fw-normal ms-2">(<?= count($ch['lessons']) ?> bài giảng)</span>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddLesson" onclick="setAddLessonChapter(<?= (int)$ch['id'] ?>, '<?= e(addslashes($ch['title'])) ?>')">
                        <i class="bi bi-plus-circle me-1"></i> Thêm bài giảng
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick='openEditChapterModal(<?= json_encode($ch, JSON_UNESCAPED_UNICODE) ?>)'>
                        <i class="bi bi-pencil"></i> Sửa
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa chương này và tất cả bài giảng bên trong?');">
                        <input type="hidden" name="action" value="delete_chapter">
                        <input type="hidden" name="chapter_id" value="<?= (int)$ch['id'] ?>">
                        <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
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
                            <tr><td colspan="6" class="text-center py-3 text-muted">Chưa có bài giảng nào trong chương này.</td></tr>
                        <?php else: foreach ($ch['lessons'] as $ls): ?>
                            <tr>
                                <td class="fw-bold text-muted text-center"><?= (int)$ls['sort_order'] ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ls['title']) ?></div>
                                    <?php if (!empty($ls['content'])): ?>
                                        <small class="text-muted text-truncate d-block" style="max-width: 380px;"><?= e(mb_substr($ls['content'], 0, 70)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$ls['duration_minutes'] ?> phút</td>
                                <td>
                                    <?php if (!empty($ls['video_url'])): ?>
                                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-play-circle me-1"></i>Video</span>
                                    <?php endif; ?>
                                    <?php if (!empty($ls['document_url'])): ?>
                                        <a href="../<?= e($ls['document_url']) ?>" target="_blank" class="badge bg-info-subtle text-info text-decoration-none ms-1"><i class="bi bi-file-earmark-arrow-down me-1"></i>Tài liệu</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($ls['is_preview'])): ?>
                                        <span class="badge bg-success-subtle text-success">Xem thử</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick='openEditLessonModal(<?= json_encode($ls, JSON_UNESCAPED_UNICODE) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa bài giảng này?');">
                                        <input type="hidden" name="action" value="delete_lesson">
                                        <input type="hidden" name="lesson_id" value="<?= (int)$ls['id'] ?>">
                                        <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- BÀI TRẮC NGHIỆM CỦA CHƯƠNG -->
            <div class="p-3 bg-light border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                        <i class="bi bi-patch-question-fill me-1"></i>Trắc nghiệm chương
                    </span>
                    <?php if (!empty($ch['quizzes'])): ?>
                        <?php foreach ($ch['quizzes'] as $cq): ?>
                            <span class="fw-semibold text-dark small"><?= e($cq['title']) ?></span>
                            <span class="badge bg-white text-muted border small"><?= (int)$cq['question_count'] ?> câu</span>
                            <span class="badge bg-white text-muted border small"><?= (int)$cq['duration_minutes'] ?>p</span>
                            <a href="ql_thi.php?course_id=<?= $selected_course_id ?>&quiz_id=<?= (int)$cq['id'] ?>&tab=questions" class="btn btn-sm btn-outline-info py-0 px-2" title="Soạn câu hỏi">
                                <i class="bi bi-list-check me-1"></i>Soạn câu hỏi
                            </a>
                            <a href="../quiz.php?quiz_id=<?= (int)$cq['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Xem đề thi">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted small fst-italic">Chương này chưa có bài trắc nghiệm.</span>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="ql_thi.php?course_id=<?= $selected_course_id ?>&tab=quizzes" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-circle me-1"></i> Quản lý / Tạo trắc nghiệm
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- MODAL THÊM CHƯƠNG -->
<div class="modal fade" id="modalAddChapter" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add_chapter">
            <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Thêm Chương Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tên chương *</label>
                    <input type="text" name="title" class="form-control" required placeholder="Ví dụ: Chương 1: Giới thiệu căn bản">
                </div>
                <div class="mb-3">
                    <label class="form-label">Thứ tự hiển thị</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= count($chapters) + 1 ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu Chương</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SỬA CHƯƠNG -->
<div class="modal fade" id="modalEditChapter" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit_chapter">
            <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
            <input type="hidden" name="chapter_id" id="edit_chapter_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Cập Nhật Chương</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tên chương *</label>
                    <input type="text" name="title" id="edit_chapter_title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Thứ tự hiển thị</label>
                    <input type="number" name="sort_order" id="edit_chapter_order" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu Thay Đổi</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL THÊM BÀI GIẢNG -->
<div class="modal fade" id="modalAddLesson" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action" value="add_lesson">
            <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
            <input type="hidden" name="chapter_id" id="add_lesson_chapter_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Thêm Bài Giảng vào: <span id="add_lesson_chapter_name" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-9">
                    <label class="form-label">Tên bài giảng *</label>
                    <input type="text" name="title" class="form-control" required placeholder="Ví dụ: Bài 1: Cài đặt và cấu hình">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Thứ tự</label>
                    <input type="number" name="sort_order" class="form-control" value="1">
                </div>
                <div class="col-md-8">
                    <label class="form-label">URL Video (Youtube Embed / MP4 link)</label>
                    <input type="text" name="video_url" class="form-control" placeholder="https://www.youtube.com/embed/...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Thời lượng (phút)</label>
                    <input type="number" name="duration_minutes" class="form-control" value="15">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Tài liệu đính kèm (PDF, ZIP, DOCX...)</label>
                    <input type="file" name="doc_file" class="form-control">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Nội dung / Ghi chú bài giảng</label>
                    <textarea name="content" class="form-control" rows="3" placeholder="Tóm tắt kiến thức hoặc hướng dẫn bài tập..."></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_preview" id="chkPreviewAdd">
                        <label class="form-check-label fw-semibold" for="chkPreviewAdd">Cho phép học thử / xem giới thiệu miễn phí</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu Bài Giảng</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SỬA BÀI GIẢNG -->
<div class="modal fade" id="modalEditLesson" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action" value="edit_lesson">
            <input type="hidden" name="course_id" value="<?= $selected_course_id ?>">
            <input type="hidden" name="lesson_id" id="edit_lesson_id">
            <input type="hidden" name="chapter_id" id="edit_lesson_chapter_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Cập Nhật Bài Giảng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-9">
                    <label class="form-label">Tên bài giảng *</label>
                    <input type="text" name="title" id="edit_lesson_title" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Thứ tự</label>
                    <input type="number" name="sort_order" id="edit_lesson_order" class="form-control">
                </div>
                <div class="col-md-8">
                    <label class="form-label">URL Video</label>
                    <input type="text" name="video_url" id="edit_lesson_video" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Thời lượng (phút)</label>
                    <input type="number" name="duration_minutes" id="edit_lesson_duration" class="form-control">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Tài liệu đính kèm mới (nếu muốn thay thế)</label>
                    <input type="file" name="doc_file" class="form-control">
                    <input type="hidden" name="document_url" id="edit_lesson_document_url">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Nội dung / Ghi chú bài giảng</label>
                    <textarea name="content" id="edit_lesson_content" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_preview" id="edit_lesson_preview">
                        <label class="form-check-label fw-semibold" for="edit_lesson_preview">Cho phép học thử / xem giới thiệu miễn phí</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu Thay Đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
function setAddLessonChapter(chId, chTitle) {
    document.getElementById('add_lesson_chapter_id').value = chId;
    document.getElementById('add_lesson_chapter_name').innerText = chTitle;
}
function openEditChapterModal(ch) {
    document.getElementById('edit_chapter_id').value = ch.id;
    document.getElementById('edit_chapter_title').value = ch.title || '';
    document.getElementById('edit_chapter_order').value = ch.sort_order || 1;
    new bootstrap.Modal(document.getElementById('modalEditChapter')).show();
}
function openEditLessonModal(ls) {
    document.getElementById('edit_lesson_id').value = ls.id;
    document.getElementById('edit_lesson_chapter_id').value = ls.section_id;
    document.getElementById('edit_lesson_title').value = ls.title || '';
    document.getElementById('edit_lesson_video').value = ls.video_url || '';
    document.getElementById('edit_lesson_document_url').value = ls.document_url || '';
    document.getElementById('edit_lesson_content').value = ls.content || '';
    document.getElementById('edit_lesson_duration').value = ls.duration_minutes || 15;
    document.getElementById('edit_lesson_order').value = ls.sort_order || 1;
    document.getElementById('edit_lesson_preview').checked = (parseInt(ls.is_preview) === 1);
    new bootstrap.Modal(document.getElementById('modalEditLesson')).show();
}
</script>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
