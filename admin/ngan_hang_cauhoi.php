<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Ngân hàng Câu hỏi';
$active_nav = 'cauhoi';

// XỬ LÝ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $q_id        = (int)($_POST['question_id'] ?? 0);
        $course_id   = (int)($_POST['course_id'] ?? 0);
        $chapter_id  = (int)($_POST['chapter_id'] ?? 0);
        $lesson_id   = (int)($_POST['lesson_id'] ?? 0);
        $question_text = trim($_POST['question_text'] ?? '');
        $opt_a       = trim($_POST['option_a'] ?? '');
        $opt_b       = trim($_POST['option_b'] ?? '');
        $opt_c       = trim($_POST['option_c'] ?? '');
        $opt_d       = trim($_POST['option_d'] ?? '');
        $correct     = strtoupper(trim($_POST['correct_answer'] ?? 'A'));
        $difficulty  = trim($_POST['difficulty'] ?? 'medium');
        $explanation = trim($_POST['explanation'] ?? '');

        if (empty($question_text) || empty($opt_a) || empty($opt_b) || $course_id <= 0) {
            $_SESSION['flash_msg']  = "Vui lòng nhập đầy đủ nội dung câu hỏi, khóa học và ít nhất 2 đáp án (A, B)!";
            $_SESSION['flash_type'] = "danger";
        } else {
            $s_q   = $conn->real_escape_string($question_text);
            $s_a   = $conn->real_escape_string($opt_a);
            $s_b   = $conn->real_escape_string($opt_b);
            $s_c   = $conn->real_escape_string($opt_c);
            $s_d   = $conn->real_escape_string($opt_d);
            $s_cor = $conn->real_escape_string($correct);
            $s_dif = $conn->real_escape_string($difficulty);
            $s_exp = $conn->real_escape_string($explanation);

            if ($action === 'edit' && $q_id > 0) {
                $sql = "UPDATE questions SET
                        course_id=$course_id, chapter_id=$chapter_id, lesson_id=$lesson_id,
                        question_text='$s_q', option_a='$s_a', option_b='$s_b',
                        option_c='$s_c', option_d='$s_d',
                        correct_answer='$s_cor', difficulty='$s_dif', explanation='$s_exp'
                        WHERE id=$q_id";
                if ($conn->query($sql)) {
                    $_SESSION['flash_msg']  = "Cập nhật thành công câu hỏi #$q_id!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $_SESSION['flash_msg']  = "Lỗi: " . $conn->error;
                    $_SESSION['flash_type'] = "danger";
                }
            } else {
                $sql = "INSERT INTO questions (course_id, chapter_id, lesson_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty, explanation)
                        VALUES ($course_id, $chapter_id, $lesson_id, '$s_q', '$s_a', '$s_b', '$s_c', '$s_d', '$s_cor', '$s_dif', '$s_exp')";
                if ($conn->query($sql)) {
                    $new_id = $conn->insert_id;
                    $_SESSION['flash_msg']  = "Thêm thành công câu hỏi #$new_id vào ngân hàng!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $_SESSION['flash_msg']  = "Lỗi: " . $conn->error;
                    $_SESSION['flash_type'] = "danger";
                }
            }
        }
        header("Location: ngan_hang_cauhoi.php?course_id=$course_id&chapter_id=$chapter_id&lesson_id=$lesson_id");
        exit();
    }

    // Xử lý nhập câu hỏi hàng loạt từ file Excel / CSV
    if ($action === 'import_excel') {
        $course_id   = (int)($_POST['course_id'] ?? 0);
        $chapter_id  = (int)($_POST['chapter_id'] ?? 0);
        $lesson_id   = (int)($_POST['lesson_id'] ?? 0);
        $questions_json = $_POST['questions_json'] ?? '';

        $imported = 0;
        $failed = 0;
        $items = [];

        if (!empty($questions_json)) {
            $items = json_decode($questions_json, true) ?: [];
        } elseif (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            // Đọc trực tiếp nếu là file CSV
            $tmp = $_FILES['excel_file']['tmp_name'];
            $handle = fopen($tmp, 'r');
            if ($handle !== false) {
                fgetcsv($handle); // bỏ qua dòng tiêu đề
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) >= 3 && !empty(trim($row[0]))) {
                        $items[] = [
                            'question_text' => trim($row[0] ?? ''),
                            'option_a'      => trim($row[1] ?? ''),
                            'option_b'      => trim($row[2] ?? ''),
                            'option_c'      => trim($row[3] ?? ''),
                            'option_d'      => trim($row[4] ?? ''),
                            'correct_answer'=> trim($row[5] ?? 'A'),
                            'difficulty'    => trim($row[6] ?? 'medium'),
                            'explanation'   => trim($row[7] ?? '')
                        ];
                    }
                }
                fclose($handle);
            }
        }

        if (empty($items) || $course_id <= 0) {
            $_SESSION['flash_msg'] = "Không có câu hỏi hợp lệ nào để nhập hoặc chưa chọn khóa học!";
            $_SESSION['flash_type'] = "danger";
        } else {
            foreach ($items as $item) {
                $q_text = trim($item['question_text'] ?? $item['cau_hoi'] ?? $item['question'] ?? '');
                $op_a   = trim($item['option_a'] ?? $item['dap_an_a'] ?? '');
                $op_b   = trim($item['option_b'] ?? $item['dap_an_b'] ?? '');
                $op_c   = trim($item['option_c'] ?? $item['dap_an_c'] ?? '');
                $op_d   = trim($item['option_d'] ?? $item['dap_an_d'] ?? '');
                $cor    = strtoupper(trim($item['correct_answer'] ?? $item['dap_an_dung'] ?? 'A'));
                $dif    = strtolower(trim($item['difficulty'] ?? $item['do_kho'] ?? 'medium'));
                $exp    = trim($item['explanation'] ?? $item['giai_thich'] ?? '');

                // Chuẩn hóa độ khó
                if (in_array($dif, ['de', 'dễ', 'easy', '1'])) $dif = 'easy';
                elseif (in_array($dif, ['kho', 'khó', 'hard', '3'])) $dif = 'hard';
                else $dif = 'medium';

                // Chuẩn hóa đáp án đúng
                if (!in_array($cor, ['A', 'B', 'C', 'D'])) $cor = 'A';

                if (!empty($q_text) && !empty($op_a) && !empty($op_b)) {
                    $s_q   = $conn->real_escape_string($q_text);
                    $s_a   = $conn->real_escape_string($op_a);
                    $s_b   = $conn->real_escape_string($op_b);
                    $s_c   = $conn->real_escape_string($op_c);
                    $s_d   = $conn->real_escape_string($op_d);
                    $s_exp = $conn->real_escape_string($exp);

                    $sql = "INSERT INTO questions (course_id, chapter_id, lesson_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty, explanation)
                            VALUES ($course_id, $chapter_id, $lesson_id, '$s_q', '$s_a', '$s_b', '$s_c', '$s_d', '$cor', '$dif', '$s_exp')";
                    if ($conn->query($sql)) {
                        $imported++;
                    } else {
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            }

            if ($imported > 0) {
                $_SESSION['flash_msg'] = "Đã nhập thành công <b>$imported</b> câu hỏi từ file Excel vào hệ thống!" . ($failed > 0 ? " ($failed dòng bị lỗi hoặc thiếu đáp án bị bỏ qua)" : "");
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "Không nhập được câu hỏi nào. Vui lòng kiểm tra lại định dạng file Excel!";
                $_SESSION['flash_type'] = "danger";
            }
        }
        header("Location: ngan_hang_cauhoi.php?course_id=$course_id&chapter_id=$chapter_id&lesson_id=$lesson_id");
        exit();
    }

    if ($action === 'delete') {
        $q_id = (int)($_POST['question_id'] ?? 0);
        $c_id = (int)($_POST['cur_course_id'] ?? 0);
        $ch_id= (int)($_POST['cur_chapter_id'] ?? 0);
        $l_id = (int)($_POST['cur_lesson_id'] ?? 0);
        if ($q_id > 0) {
            $conn->query("DELETE FROM exam_questions WHERE question_id=$q_id");
            $conn->query("DELETE FROM questions WHERE id=$q_id");
            $_SESSION['flash_msg']  = "Đã xóa câu hỏi #$q_id khỏi ngân hàng!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: ngan_hang_cauhoi.php?course_id=$c_id&chapter_id=$ch_id&lesson_id=$l_id");
        exit();
    }
}

// 1. Danh sách Khóa học
$courses_res = $conn->query("SELECT id, title FROM courses ORDER BY id DESC");
$courses_list = [];
if ($courses_res) while ($r = $courses_res->fetch_assoc()) $courses_list[] = $r;

// Khóa học hiện tại đang xem (mặc định lấy khóa đầu tiên nếu chưa chọn)
$current_course_id = (int)($_GET['course_id'] ?? 0);
if ($current_course_id <= 0 && !empty($courses_list)) {
    $current_course_id = (int)$courses_list[0]['id'];
}

$current_chapter_id = (int)($_GET['chapter_id'] ?? 0);
$current_lesson_id  = (int)($_GET['lesson_id']  ?? 0);
$search_q           = trim($_GET['q'] ?? '');
$filter_diff        = trim($_GET['difficulty'] ?? '');

// 2. Lấy toàn bộ cây Chương & Bài học của khóa học hiện tại kèm số câu hỏi
$tree_chapters = [];
$total_course_questions = 0;

if ($current_course_id > 0) {
    // Tổng số câu hỏi của cả khóa
    $cq = $conn->query("SELECT COUNT(*) FROM questions WHERE course_id = $current_course_id");
    $total_course_questions = $cq ? (int)$cq->fetch_row()[0] : 0;

    // Danh sách chương
    $ch_res = $conn->query("
        SELECT ch.id, ch.title, ch.sort_order,
               (SELECT COUNT(*) FROM questions q WHERE q.chapter_id = ch.id) as q_count
        FROM chapters ch
        WHERE ch.course_id = $current_course_id
        ORDER BY ch.sort_order ASC, ch.id ASC
    ");
    if ($ch_res) {
        while ($ch = $ch_res->fetch_assoc()) {
            $ch_id = (int)$ch['id'];
            // Lấy các bài học trong chương
            $ls_res = $conn->query("
                SELECT l.id, l.title, l.sort_order,
                       (SELECT COUNT(*) FROM questions q WHERE q.lesson_id = l.id) as q_count
                FROM lessons l
                WHERE l.chapter_id = $ch_id
                ORDER BY l.sort_order ASC, l.id ASC
            ");
            $lessons = [];
            if ($ls_res) {
                while ($ls = $ls_res->fetch_assoc()) {
                    $lessons[] = $ls;
                }
            }
            $ch['lessons'] = $lessons;
            $tree_chapters[] = $ch;
        }
    }
}

// 3. Lọc danh sách câu hỏi cần hiển thị bên phải
$where = "WHERE q.course_id = $current_course_id";
if ($current_chapter_id > 0) {
    $where .= " AND q.chapter_id = $current_chapter_id";
}
if ($current_lesson_id > 0) {
    $where .= " AND q.lesson_id = $current_lesson_id";
}
if (!empty($filter_diff)) {
    $sd = $conn->real_escape_string($filter_diff);
    $where .= " AND q.difficulty = '$sd'";
}
if (!empty($search_q)) {
    $sq = $conn->real_escape_string($search_q);
    $where .= " AND (q.question_text LIKE '%$sq%' OR q.explanation LIKE '%$sq%')";
}

$questions_res = $conn->query("
    SELECT q.*, 
           c.title  AS course_title,
           ch.title AS chapter_title,
           l.title  AS lesson_title
    FROM questions q
    LEFT JOIN courses  c  ON c.id  = q.course_id
    LEFT JOIN chapters ch ON ch.id = q.chapter_id
    LEFT JOIN lessons  l  ON l.id  = q.lesson_id
    $where
    ORDER BY q.id DESC
");
$questions = [];
if ($questions_res) while ($r = $questions_res->fetch_assoc()) $questions[] = $r;

// Lấy thông tin tiêu đề hiện tại đang chọn
$view_title = "Toàn bộ khóa học";
if ($current_chapter_id > 0) {
    foreach ($tree_chapters as $tc) {
        if ($tc['id'] == $current_chapter_id) {
            $view_title = $tc['title'];
            if ($current_lesson_id > 0) {
                foreach ($tc['lessons'] as $tl) {
                    if ($tl['id'] == $current_lesson_id) {
                        $view_title = $tc['title'] . " ❯ " . $tl['title'];
                        break;
                    }
                }
            }
            break;
        }
    }
}

include 'admin_layout.php';
?>

<style>
.tree-nav-box {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.tree-header {
    background: #f8fafc;
    padding: 12px 16px;
    font-weight: 600;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.92rem;
}
.tree-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 14px;
    color: #334155;
    text-decoration: none;
    font-size: 0.88rem;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.15s ease;
}
.tree-item:hover {
    background: #f0fdfa;
    color: #0f766e;
}
.tree-item.active {
    background: #0d9488;
    color: #fff !important;
    font-weight: 600;
}
.tree-item.active .badge {
    background: #fff !important;
    color: #0d9488 !important;
}
.tree-chapter-title {
    background: #f1f5f9;
    font-weight: 600;
    color: #1e293b;
    padding: 10px 14px;
    font-size: 0.89rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.15s;
}
.tree-chapter-title:hover {
    background: #e2e8f0;
}
.tree-chapter-title.active {
    background: #0f766e;
    color: #fff;
}
.tree-chapter-title.active .badge {
    background: #fff !important;
    color: #0f766e !important;
}
.lesson-item {
    padding-left: 28px !important;
    font-size: 0.84rem;
}
.q-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 14px;
    transition: box-shadow 0.15s;
}
.q-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.opt-box {
    display: flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.86rem;
    margin-bottom: 4px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.opt-box.correct {
    background: #dcfce7;
    border-color: #86efac;
    color: #166534;
    font-weight: 600;
}
</style>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-patch-question-fill text-primary me-2"></i>Ngân hàng Câu hỏi</h4>
        <p class="mb-0 text-muted">Quản lý câu hỏi phân cấp theo từng <b>Chương</b> và <b>Bài học</b></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success" onclick="openImportModalDirect()">
            <i class="bi bi-file-earmark-excel me-1"></i> Nhập từ Excel
        </button>
        <button class="btn btn-primary" onclick="openAddModalDirect()">
            <i class="bi bi-plus-circle me-1"></i> Soạn Câu Hỏi Mới
        </button>
    </div>
</div>

<?php if (!empty($_SESSION['flash_msg'])): ?>
<div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['flash_msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash_msg'], $_SESSION['flash_type']); endif; ?>

<!-- THANH CHỌN KHÓA HỌC & TÌM KIẾM -->
<div class="table-card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <label class="form-label small fw-bold mb-1"><i class="bi bi-book-half text-primary me-1"></i>Chọn Khóa học:</label>
            <select name="course_id" class="form-select fw-semibold" onchange="this.form.submit()">
                <?php foreach ($courses_list as $co): ?>
                    <option value="<?= $co['id'] ?>" <?= $co['id'] == $current_course_id ? 'selected' : '' ?>>
                        #<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold mb-1"><i class="bi bi-search me-1"></i>Tìm kiếm câu hỏi:</label>
            <input type="text" name="q" class="form-control" placeholder="Nhập từ khóa câu hỏi..." value="<?= htmlspecialchars($search_q) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold mb-1">Độ khó:</label>
            <select name="difficulty" class="form-select" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                <option value="easy"   <?= $filter_diff==='easy'   ? 'selected':'' ?>>Dễ</option>
                <option value="medium" <?= $filter_diff==='medium' ? 'selected':'' ?>>Trung bình</option>
                <option value="hard"   <?= $filter_diff==='hard'   ? 'selected':'' ?>>Khó</option>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end" style="height: 60px;">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i>Lọc</button>
        </div>
        <input type="hidden" name="chapter_id" value="<?= $current_chapter_id ?>">
        <input type="hidden" name="lesson_id" value="<?= $current_lesson_id ?>">
    </form>
</div>

<!-- GIAO DIỆN PHÂN CẤP 2 CỘT -->
<div class="row g-3">
    <!-- CỘT TRÁI: DANH SÁCH CHƯƠNG & BÀI HỌC (TREE NAV) -->
    <div class="col-lg-4 col-md-5">
        <div class="tree-nav-box shadow-sm mb-3">
            <div class="tree-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-3 me-2 text-primary"></i>Mục lục Chương & Bài</span>
                <span class="badge bg-secondary"><?= $total_course_questions ?> câu</span>
            </div>

            <!-- Nút xem tất cả của khóa -->
            <a href="ngan_hang_cauhoi.php?course_id=<?= $current_course_id ?>" 
               class="tree-item <?= ($current_chapter_id == 0 && $current_lesson_id == 0) ? 'active' : '' ?>">
                <span><i class="bi bi-collection-play me-2"></i><b>Tất cả câu hỏi khóa này</b></span>
                <span class="badge bg-primary bg-opacity-25 text-primary"><?= $total_course_questions ?></span>
            </a>

            <!-- Accordion từng chương & bài -->
            <?php if (empty($tree_chapters)): ?>
                <div class="p-3 text-center text-muted small">Khóa học này chưa có chương mục nào.</div>
            <?php else: foreach ($tree_chapters as $idx => $ch): 
                $is_ch_active = ($current_chapter_id == $ch['id']);
                $has_active_lesson = false;
                foreach ($ch['lessons'] as $ls) {
                    if ($current_lesson_id == $ls['id']) { $has_active_lesson = true; break; }
                }
                $open_collapse = ($is_ch_active || $has_active_lesson) ? 'show' : '';
            ?>
                <!-- Header Chương -->
                <div class="tree-chapter-title <?= ($is_ch_active && $current_lesson_id == 0) ? 'active' : '' ?>"
                     data-bs-toggle="collapse" data-bs-target="#ch_collapse_<?= $ch['id'] ?>">
                    <div class="text-truncate me-2">
                        <i class="bi bi-folder2-open me-1 text-warning"></i>
                        <span><?= htmlspecialchars($ch['title']) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="badge <?= $ch['q_count'] > 0 ? 'bg-info bg-opacity-25 text-dark' : 'bg-light text-muted' ?>">
                            <?= $ch['q_count'] ?> câu
                        </span>
                        <i class="bi bi-chevron-down small text-muted"></i>
                    </div>
                </div>

                <!-- Danh sách bài học trong chương (Collapsible) -->
                <div class="collapse <?= $open_collapse ?>" id="ch_collapse_<?= $ch['id'] ?>">
                    <!-- Tùy chọn xem câu hỏi chung của cả chương -->
                    <a href="ngan_hang_cauhoi.php?course_id=<?= $current_course_id ?>&chapter_id=<?= $ch['id'] ?>"
                       class="tree-item lesson-item <?= ($is_ch_active && $current_lesson_id == 0) ? 'active' : '' ?>">
                        <span class="text-truncate"><i class="bi bi-arrow-right-short me-1"></i>Toàn bộ chương này</span>
                        <span class="badge bg-light text-dark border"><?= $ch['q_count'] ?></span>
                    </a>

                    <?php if (empty($ch['lessons'])): ?>
                        <div class="py-1 px-4 text-muted small fst-italic">Chưa có bài giảng nào</div>
                    <?php else: foreach ($ch['lessons'] as $ls): 
                        $is_ls_active = ($current_lesson_id == $ls['id']);
                    ?>
                        <!-- Ấn vào bài học nào thì hiện câu hỏi của bài đó -->
                        <a href="ngan_hang_cauhoi.php?course_id=<?= $current_course_id ?>&chapter_id=<?= $ch['id'] ?>&lesson_id=<?= $ls['id'] ?>"
                           class="tree-item lesson-item <?= $is_ls_active ? 'active' : '' ?>">
                            <span class="text-truncate">
                                <i class="bi bi-play-circle me-1 <?= $is_ls_active ? 'text-white' : 'text-success' ?>"></i>
                                <?= htmlspecialchars($ls['title']) ?>
                            </span>
                            <span class="badge <?= $ls['q_count'] > 0 ? 'bg-success bg-opacity-25 text-success' : 'bg-light text-muted' ?>">
                                <?= $ls['q_count'] ?> câu
                            </span>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- CỘT PHẢI: DANH SÁCH CÂU HỎI THEO MỤC ĐÃ ẤN -->
    <div class="col-lg-8 col-md-7">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-3 bg-white border rounded shadow-sm">
            <div>
                <span class="text-muted small d-block">Đang hiển thị câu hỏi của:</span>
                <h5 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-pin-angle-fill me-1"></i><?= htmlspecialchars($view_title) ?>
                </h5>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-success" onclick="openImportModalForCurrentSelection()">
                    <i class="bi bi-file-earmark-excel me-1"></i> Nhập Excel vào đây
                </button>
                <button class="btn btn-sm btn-success" onclick="openAddModalForCurrentSelection()">
                    <i class="bi bi-plus-lg me-1"></i> Soạn Câu Hỏi Vào Đây
                </button>
            </div>
        </div>

        <!-- DANH SÁCH CÂU HỎI -->
        <?php if (empty($questions)): ?>
            <div class="p-5 text-center bg-white border rounded shadow-sm text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary opacity-50"></i>
                <h5>Mục này chưa có câu hỏi nào</h5>
                <p class="small text-muted mb-3">Hãy tạo câu hỏi đầu tiên cho bài học/chương này để học viên làm bài kiểm tra!</p>
                <button class="btn btn-primary" onclick="openAddModalForCurrentSelection()">
                    <i class="bi bi-plus-circle me-1"></i> Soạn Câu Hỏi Ngay
                </button>
            </div>
        <?php else: ?>
            <div class="mb-2 text-muted small fw-semibold">
                Tìm thấy <b><?= count($questions) ?></b> câu hỏi:
            </div>
            <?php foreach ($questions as $q): ?>
                <div class="q-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-secondary">#<?= $q['id'] ?></span>
                            <?php if ($q['difficulty'] === 'easy'): ?>
                                <span class="badge-easy">Dễ</span>
                            <?php elseif ($q['difficulty'] === 'hard'): ?>
                                <span class="badge-hard">Khó</span>
                            <?php else: ?>
                                <span class="badge-medium">Trung bình</span>
                            <?php endif; ?>

                            <?php if (!empty($q['chapter_title'])): ?>
                                <span class="badge bg-info bg-opacity-10 text-dark border">
                                    <i class="bi bi-folder me-1"></i><?= htmlspecialchars($q['chapter_title']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($q['lesson_title'])): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border">
                                    <i class="bi bi-play-circle me-1"></i><?= htmlspecialchars($q['lesson_title']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick='openEditModal(<?= json_encode($q) ?>)' title="Sửa">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $q['id'] ?>)" title="Xóa">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Nội dung câu hỏi -->
                    <div class="fw-bold fs-6 mb-3 text-dark">
                        <?= htmlspecialchars($q['question_text']) ?>
                    </div>

                    <!-- 4 Phương án -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <div class="opt-box <?= $q['correct_answer'] === 'A' ? 'correct' : '' ?>">
                                <span class="badge bg-secondary me-2">A</span>
                                <span><?= htmlspecialchars($q['option_a']) ?></span>
                                <?php if ($q['correct_answer'] === 'A'): ?><i class="bi bi-check-circle-fill text-success ms-auto"></i><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="opt-box <?= $q['correct_answer'] === 'B' ? 'correct' : '' ?>">
                                <span class="badge bg-secondary me-2">B</span>
                                <span><?= htmlspecialchars($q['option_b']) ?></span>
                                <?php if ($q['correct_answer'] === 'B'): ?><i class="bi bi-check-circle-fill text-success ms-auto"></i><?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($q['option_c'])): ?>
                        <div class="col-md-6">
                            <div class="opt-box <?= $q['correct_answer'] === 'C' ? 'correct' : '' ?>">
                                <span class="badge bg-secondary me-2">C</span>
                                <span><?= htmlspecialchars($q['option_c']) ?></span>
                                <?php if ($q['correct_answer'] === 'C'): ?><i class="bi bi-check-circle-fill text-success ms-auto"></i><?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($q['option_d'])): ?>
                        <div class="col-md-6">
                            <div class="opt-box <?= $q['correct_answer'] === 'D' ? 'correct' : '' ?>">
                                <span class="badge bg-secondary me-2">D</span>
                                <span><?= htmlspecialchars($q['option_d']) ?></span>
                                <?php if ($q['correct_answer'] === 'D'): ?><i class="bi bi-check-circle-fill text-success ms-auto"></i><?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($q['explanation'])): ?>
                        <div class="small bg-light p-2 rounded text-muted mt-2">
                            <i class="bi bi-info-circle me-1 text-primary"></i><b>Giải thích:</b> <?= htmlspecialchars($q['explanation']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===== MODAL THÊM CÂU HỎI ===== -->
<div class="modal fade" id="modalAddQuestion" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Soạn Câu Hỏi Trắc Nghiệm Mới</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- PHÂN CẤP: Khóa học → Chương → Bài học -->
                    <div class="p-3 mb-3 rounded border bg-light">
                        <div class="fw-bold mb-2 text-primary"><i class="bi bi-diagram-3 me-1"></i>Chọn vị trí bài học áp dụng</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Khóa học <span class="text-danger">*</span></label>
                                <select name="course_id" id="add_course_id" class="form-select" required onchange="loadChapters('add', this.value)">
                                    <option value="">-- Chọn khóa học --</option>
                                    <?php foreach ($courses_list as $co): ?>
                                        <option value="<?= $co['id'] ?>" <?= $co['id'] == $current_course_id ? 'selected' : '' ?>>
                                            #<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="bi bi-folder me-1 text-warning"></i>Chương <span class="text-muted small">(tùy chọn)</span></label>
                                <select name="chapter_id" id="add_chapter_id" class="form-select" onchange="loadLessons('add', this.value)">
                                    <option value="0">-- Dùng cho cả khóa học --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="bi bi-play-circle me-1 text-success"></i>Bài học <span class="text-muted small">(tùy chọn)</span></label>
                                <select name="lesson_id" id="add_lesson_id" class="form-select">
                                    <option value="0">-- Dùng cho cả chương --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- NỘI DUNG CÂU HỎI -->
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nội dung câu hỏi <span class="text-danger">*</span></label>
                            <textarea name="question_text" class="form-control" rows="3" required placeholder="Nhập câu hỏi trắc nghiệm..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đáp án A <span class="text-danger">*</span></label>
                            <input type="text" name="option_a" class="form-control" required placeholder="Nội dung phương án A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đáp án B <span class="text-danger">*</span></label>
                            <input type="text" name="option_b" class="form-control" required placeholder="Nội dung phương án B">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đáp án C <span class="text-muted small">(tùy chọn)</span></label>
                            <input type="text" name="option_c" class="form-control" placeholder="Nội dung phương án C (nếu có)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đáp án D <span class="text-muted small">(tùy chọn)</span></label>
                            <input type="text" name="option_d" class="form-control" placeholder="Nội dung phương án D (nếu có)">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-success">Chọn Đáp án đúng <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 mt-1">
                                <?php foreach (['A','B','C','D'] as $ltr): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="correct_answer" id="radAdd<?= $ltr ?>" value="<?= $ltr ?>" <?= $ltr==='A' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="radAdd<?= $ltr ?>"><?= $ltr ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mức độ khó</label>
                            <select name="difficulty" class="form-select">
                                <option value="easy">Dễ (Nhận biết)</option>
                                <option value="medium" selected>Trung bình (Thông hiểu)</option>
                                <option value="hard">Khó (Vận dụng cao)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Giải thích chi tiết <span class="text-muted small">(Hiển thị sau khi học viên nộp bài)</span></label>
                            <textarea name="explanation" class="form-control" rows="2" placeholder="Giải thích vì sao đáp án này đúng..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu Vào Ngân Hàng</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL SỬA CÂU HỎI ===== -->
<div class="modal fade" id="modalEditQuestion" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="question_id" id="edit_q_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Cập nhật Câu Hỏi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 mb-3 rounded border bg-light">
                        <div class="fw-bold mb-2 text-primary"><i class="bi bi-diagram-3 me-1"></i>Vị trí bài học áp dụng</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Khóa học <span class="text-danger">*</span></label>
                                <select name="course_id" id="edit_q_course" class="form-select" required onchange="loadChapters('edit', this.value)">
                                    <?php foreach ($courses_list as $co): ?>
                                        <option value="<?= $co['id'] ?>">#<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="bi bi-folder me-1 text-warning"></i>Chương</label>
                                <select name="chapter_id" id="edit_q_chapter" class="form-select" onchange="loadLessons('edit', this.value)">
                                    <option value="0">-- Dùng cho cả khóa học --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="bi bi-play-circle me-1 text-success"></i>Bài học</label>
                                <select name="lesson_id" id="edit_q_lesson" class="form-select">
                                    <option value="0">-- Dùng cho cả chương --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nội dung câu hỏi <span class="text-danger">*</span></label>
                            <textarea name="question_text" id="edit_q_text" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đáp án A <span class="text-danger">*</span></label>
                            <input type="text" name="option_a" id="edit_q_a" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đáp án B <span class="text-danger">*</span></label>
                            <input type="text" name="option_b" id="edit_q_b" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đáp án C</label>
                            <input type="text" name="option_c" id="edit_q_c" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đáp án D</label>
                            <input type="text" name="option_d" id="edit_q_d" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-success">Chọn Đáp án đúng <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 mt-1">
                                <?php foreach (['A','B','C','D'] as $ltr): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="correct_answer" id="radEdit<?= $ltr ?>" value="<?= $ltr ?>">
                                    <label class="form-check-label fw-bold" for="radEdit<?= $ltr ?>"><?= $ltr ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mức độ khó</label>
                            <select name="difficulty" id="edit_q_difficulty" class="form-select">
                                <option value="easy">Dễ</option>
                                <option value="medium">Trung bình</option>
                                <option value="hard">Khó</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Giải thích chi tiết</label>
                            <textarea name="explanation" id="edit_q_exp" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning fw-bold"><i class="bi bi-save me-1"></i>Lưu Thay Đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL NHẬP CÂU HỎI TỪ EXCEL ===== -->
<div class="modal fade" id="modalImportExcel" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="formImportExcel" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_excel">
                <input type="hidden" name="questions_json" id="import_questions_json">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i>Nhập Câu Hỏi Hàng Loạt Bằng File Excel (.xlsx / .csv)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <!-- 1. Vị trí áp dụng -->
                    <div class="p-3 mb-3 rounded border bg-light">
                        <div class="fw-bold mb-2 text-success"><i class="bi bi-geo-alt-fill me-1"></i>Chọn vị trí lưu câu hỏi</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Khóa học <span class="text-danger">*</span></label>
                                <select name="course_id" id="import_course_id" class="form-select" required onchange="loadChapters('import', this.value)">
                                    <option value="">-- Chọn khóa học --</option>
                                    <?php foreach ($courses_list as $co): ?>
                                        <option value="<?= $co['id'] ?>" <?= $co['id'] == $current_course_id ? 'selected' : '' ?>>
                                            #<?= $co['id'] ?> - <?= htmlspecialchars($co['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="bi bi-folder me-1 text-warning"></i>Chương <span class="text-muted small">(tùy chọn)</span></label>
                                <select name="chapter_id" id="import_chapter_id" class="form-select" onchange="loadLessons('import', this.value)">
                                    <option value="0">-- Dùng cho cả khóa học --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="bi bi-play-circle me-1 text-success"></i>Bài học <span class="text-muted small">(tùy chọn)</span></label>
                                <select name="lesson_id" id="import_lesson_id" class="form-select">
                                    <option value="0">-- Dùng cho cả chương --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Hướng dẫn & Tải file mẫu -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center p-3 mb-3 bg-success-subtle border border-success-subtle rounded">
                        <div>
                            <div class="fw-bold text-success mb-1"><i class="bi bi-info-circle-fill me-1"></i>Định dạng cột file Excel:</div>
                            <small class="text-dark">
                                Cột bắt buộc: <b>Câu hỏi</b>, <b>Đáp án A</b>, <b>Đáp án B</b>, <b>Đáp án đúng</b> (A, B, C, D).<br>
                                Cột tùy chọn: <b>Đáp án C</b>, <b>Đáp án D</b>, <b>Độ khó</b> (Dễ / Trung bình / Khó), <b>Giải thích</b>.
                            </small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-success fw-semibold shadow-sm" onclick="downloadSampleExcel()">
                                <i class="bi bi-download me-1"></i> Tải File Excel Mẫu (.xlsx)
                            </button>
                        </div>
                    </div>

                    <!-- 3. Khu vực chọn / kéo thả file -->
                    <div class="border border-2 border-dashed rounded p-4 text-center bg-white mb-3" id="dropZoneExcel" style="cursor: pointer; border-color: #198754 !important;">
                        <input type="file" id="inputExcelFile" accept=".xlsx, .xls, .csv" class="d-none" onchange="handleExcelFile(event)">
                        <i class="bi bi-file-earmark-excel text-success" style="font-size: 3rem;"></i>
                        <h6 class="fw-bold mt-2 mb-1">Kéo thả file Excel vào đây hoặc <span class="text-success text-decoration-underline">chọn từ máy tính</span></h6>
                        <small class="text-muted">Hỗ trợ định dạng: .xlsx, .xls, .csv</small>
                        <div id="fileSelectedInfo" class="mt-2 fw-semibold text-primary d-none"></div>
                    </div>

                    <!-- 4. Bảng xem trước dữ liệu (Preview) -->
                    <div id="excelPreviewContainer" class="d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark"><i class="bi bi-eye me-1"></i>Xem trước câu hỏi phát hiện được:</span>
                            <span id="previewBadge" class="badge bg-success fs-6">0 câu hỏi hợp lệ</span>
                        </div>
                        <div class="table-responsive border rounded" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Câu hỏi</th>
                                        <th>Phương án (A/B/C/D)</th>
                                        <th style="width: 90px;">Đ/A Đúng</th>
                                        <th style="width: 100px;">Độ khó</th>
                                        <th>Giải thích</th>
                                    </tr>
                                </thead>
                                <tbody id="excelPreviewTbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" id="btnSubmitImport" class="btn btn-success fw-bold" disabled>
                        <i class="bi bi-check2-circle me-1"></i> Xác Nhận Nhập (<span id="importCountTxt">0</span> câu hỏi)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORM ẨN XÓA -->
<form id="formDelete" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="question_id" id="delete_q_id">
    <input type="hidden" name="cur_course_id" value="<?= $current_course_id ?>">
    <input type="hidden" name="cur_chapter_id" value="<?= $current_chapter_id ?>">
    <input type="hidden" name="cur_lesson_id" value="<?= $current_lesson_id ?>">
</form>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
// ===== Helper lấy Select Element theo Mode =====
function getChSel(mode) {
    if (mode === 'add') return document.getElementById('add_chapter_id');
    if (mode === 'import') return document.getElementById('import_chapter_id');
    return document.getElementById('edit_q_chapter');
}
function getLsSel(mode) {
    if (mode === 'add') return document.getElementById('add_lesson_id');
    if (mode === 'import') return document.getElementById('import_lesson_id');
    return document.getElementById('edit_q_lesson');
}

// ===== AJAX: Load chương theo khóa học =====
function loadChapters(mode, courseId, defaultChId = 0, callback = null) {
    const chSel = getChSel(mode);
    const lsSel = getLsSel(mode);
    if (!chSel) return;
    chSel.innerHTML = '<option value="0">-- Dùng cho cả khóa học --</option>';
    if (lsSel) lsSel.innerHTML = '<option value="0">-- Dùng cho cả chương --</option>';
    if (!courseId || courseId == 0) return;

    fetch('api_get_questions.php?action=chapters&course_id=' + courseId)
        .then(r => r.json())
        .then(data => {
            data.forEach(ch => {
                const opt = document.createElement('option');
                opt.value = ch.id;
                opt.textContent = ch.title + (ch.question_count > 0 ? ' (' + ch.question_count + ' câu)' : '');
                if (defaultChId > 0 && ch.id == defaultChId) opt.selected = true;
                chSel.appendChild(opt);
            });
            if (callback) callback();
        }).catch(() => {});
}

// ===== AJAX: Load bài học theo chương =====
function loadLessons(mode, chapterId, defaultLsId = 0, callback = null) {
    const lsSel = getLsSel(mode);
    if (!lsSel) return;
    lsSel.innerHTML = '<option value="0">-- Dùng cho cả chương --</option>';
    if (!chapterId || chapterId == 0) return;

    fetch('api_get_questions.php?action=lessons&chapter_id=' + chapterId)
        .then(r => r.json())
        .then(data => {
            data.forEach(ls => {
                const opt = document.createElement('option');
                opt.value = ls.id;
                opt.textContent = ls.title + (ls.question_count > 0 ? ' (' + ls.question_count + ' câu)' : '');
                if (defaultLsId > 0 && ls.id == defaultLsId) opt.selected = true;
                lsSel.appendChild(opt);
            });
            if (callback) callback();
        }).catch(() => {});
}

// ===== Mở Modal Thêm mới chung =====
function openAddModalDirect() {
    const cId = <?= $current_course_id ?>;
    document.getElementById('add_course_id').value = cId;
    loadChapters('add', cId);
    new bootstrap.Modal(document.getElementById('modalAddQuestion')).show();
}

// ===== Mở Modal Thêm mới điền sẵn Khóa học, Chương, Bài học đang chọn =====
function openAddModalForCurrentSelection() {
    const cId  = <?= $current_course_id ?>;
    const chId = <?= $current_chapter_id ?>;
    const lsId = <?= $current_lesson_id ?>;

    document.getElementById('add_course_id').value = cId;
    loadChapters('add', cId, chId, function() {
        if (chId > 0) {
            loadLessons('add', chId, lsId);
        }
    });

    new bootstrap.Modal(document.getElementById('modalAddQuestion')).show();
}

// ===== Mở Modal Nhập Excel Chung =====
function openImportModalDirect() {
    const cId = <?= $current_course_id ?>;
    document.getElementById('import_course_id').value = cId;
    loadChapters('import', cId);
    resetExcelImportUI();
    new bootstrap.Modal(document.getElementById('modalImportExcel')).show();
}

// ===== Mở Modal Nhập Excel điền sẵn Chương & Bài học đang chọn =====
function openImportModalForCurrentSelection() {
    const cId  = <?= $current_course_id ?>;
    const chId = <?= $current_chapter_id ?>;
    const lsId = <?= $current_lesson_id ?>;

    document.getElementById('import_course_id').value = cId;
    loadChapters('import', cId, chId, function() {
        if (chId > 0) {
            loadLessons('import', chId, lsId);
        }
    });
    resetExcelImportUI();
    new bootstrap.Modal(document.getElementById('modalImportExcel')).show();
}

// ===== Reset giao diện Modal Import Excel =====
function resetExcelImportUI() {
    document.getElementById('inputExcelFile').value = '';
    document.getElementById('fileSelectedInfo').classList.add('d-none');
    document.getElementById('fileSelectedInfo').innerHTML = '';
    document.getElementById('excelPreviewContainer').classList.add('d-none');
    document.getElementById('excelPreviewTbody').innerHTML = '';
    document.getElementById('btnSubmitImport').disabled = true;
    document.getElementById('import_questions_json').value = '';
    document.getElementById('importCountTxt').textContent = '0';
}

// ===== Xử lý kéo thả file Excel =====
const dropZone = document.getElementById('dropZoneExcel');
if (dropZone) {
    dropZone.addEventListener('click', () => document.getElementById('inputExcelFile').click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.borderColor = '#0d6efd'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = '#198754'; });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#198754';
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleExcelFile({ target: { files: e.dataTransfer.files } });
        }
    });
}

// ===== Tải File Excel Mẫu (.xlsx) =====
function downloadSampleExcel() {
    const sampleData = [
        {
            "Câu hỏi": "Trong PHP, ký tự nào bắt buộc dùng để bắt đầu tên của một biến?",
            "Đáp án A": "$ (Dollar)",
            "Đáp án B": "@ (At)",
            "Đáp án C": "# (Hash)",
            "Đáp án D": "& (Ampersand)",
            "Đáp án đúng": "A",
            "Độ khó": "Dễ",
            "Giải thích": "Tất cả các biến trong ngôn ngữ PHP đều phải bắt đầu bằng ký tự $."
        },
        {
            "Câu hỏi": "Phương pháp nào sau đây là hiệu quả nhất để phòng chống tấn công SQL Injection?",
            "Đáp án A": "Sử dụng hàm addslashes()",
            "Đáp án B": "Dùng Prepared Statements (PDO hoặc MySQLi)",
            "Đáp án C": "Mã hóa bằng MD5",
            "Đáp án D": "Không cần làm gì cả",
            "Đáp án đúng": "B",
            "Độ khó": "Trung bình",
            "Giải thích": "Prepared Statements tách biệt câu lệnh SQL và tham số dữ liệu, ngăn ngừa hoàn toàn SQL Injection."
        },
        {
            "Câu hỏi": "Cơ chế nào giúp ngăn chặn tấn công giả mạo yêu cầu chéo trang CSRF?",
            "Đáp án A": "CSRF Token bí mật cho mỗi phiên hoặc form",
            "Đáp án B": "Dùng phương thức GET thay vì POST",
            "Đáp án C": "Tắt tính năng Session",
            "Đáp án D": "Tăng dung lượng RAM máy chủ",
            "Đáp án đúng": "A",
            "Độ khó": "Khó",
            "Giải thích": "CSRF Token là chuỗi ngẫu nhiên duy nhất được máy chủ kiểm tra mỗi khi người dùng gửi form."
        }
    ];

    const ws = XLSX.utils.json_to_sheet(sampleData);
    ws['!cols'] = [
        { wch: 45 }, { wch: 25 }, { wch: 25 }, { wch: 25 },
        { wch: 25 }, { wch: 14 }, { wch: 12 }, { wch: 40 }
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "NganHangCauHoi");
    XLSX.writeFile(wb, "mau_nhap_cau_hoi_trac_nghiem.xlsx");
}

// ===== Xử lý File Excel được tải lên =====
function handleExcelFile(e) {
    const file = e.target.files ? e.target.files[0] : null;
    if (!file) return;

    const fileInfo = document.getElementById('fileSelectedInfo');
    fileInfo.classList.remove('d-none');
    fileInfo.innerHTML = '<i class="bi bi-file-earmark-check-fill text-success me-1"></i> ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';

    const reader = new FileReader();
    reader.onload = function(evt) {
        try {
            const data = new Uint8Array(evt.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            const rawRows = XLSX.utils.sheet_to_json(worksheet, { defval: '' });
            processRawExcelRows(rawRows);
        } catch (err) {
            alert('Không thể đọc file: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
}

// ===== Tìm giá trị cột linh hoạt theo nhiều tên khác nhau =====
function getColVal(row, aliases) {
    for (let k in row) {
        const cleanK = k.toString().trim().toLowerCase().replace(/_/g, ' ');
        for (let a of aliases) {
            if (cleanK === a.toLowerCase()) {
                const val = row[k];
                return (val !== undefined && val !== null) ? val.toString().trim() : '';
            }
        }
    }
    return '';
}

// ===== Xử lý & Preview các dòng câu hỏi trích xuất từ Excel =====
function processRawExcelRows(rows) {
    const validQuestions = [];

    rows.forEach((r, idx) => {
        // Tìm linh hoạt các cột theo tiếng Việt và tiếng Anh
        const q_text = getColVal(r, ['câu hỏi', 'cau hoi', 'nội dung câu hỏi', 'question', 'question_text', 'noi dung', 'content']);
        const opt_a  = getColVal(r, ['đáp án a', 'dap an a', 'phương án a', 'a', 'option_a', 'option a']);
        const opt_b  = getColVal(r, ['đáp án b', 'dap an b', 'phương án b', 'b', 'option_b', 'option b']);
        const opt_c  = getColVal(r, ['đáp án c', 'dap an c', 'phương án c', 'c', 'option_c', 'option c']);
        const opt_d  = getColVal(r, ['đáp án d', 'dap an d', 'phương án d', 'd', 'option_d', 'option d']);
        let cor      = getColVal(r, ['đáp án đúng', 'dap an dung', 'correct', 'correct_answer', 'kết quả', 'đ/a đúng']).toUpperCase();
        let diff     = getColVal(r, ['độ khó', 'do kho', 'difficulty', 'mức độ', 'level']).toLowerCase();
        const exp    = getColVal(r, ['giải thích', 'giai thich', 'explanation', 'ghi chú', 'ghi chu']);

        // Chuẩn hóa đáp án đúng: tìm A, B, C, D trong chuỗi
        if (cor.includes('A')) cor = 'A';
        else if (cor.includes('B')) cor = 'B';
        else if (cor.includes('C')) cor = 'C';
        else if (cor.includes('D')) cor = 'D';
        else cor = 'A';

        // Chuẩn hóa độ khó
        if (diff.includes('dễ') || diff.includes('de') || diff === 'easy' || diff === '1') diff = 'easy';
        else if (diff.includes('khó') || diff.includes('kho') || diff === 'hard' || diff === '3') diff = 'hard';
        else diff = 'medium';

        // Điều kiện hợp lệ: có nội dung câu hỏi và ít nhất 2 đáp án A, B
        if (q_text !== '' && opt_a !== '' && opt_b !== '') {
            validQuestions.push({
                question_text: q_text,
                option_a: opt_a,
                option_b: opt_b,
                option_c: opt_c,
                option_d: opt_d,
                correct_answer: cor,
                difficulty: diff,
                explanation: exp
            });
        }
    });

    // Render xem trước
    const tbody = document.getElementById('excelPreviewTbody');
    tbody.innerHTML = '';

    if (validQuestions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Không phát hiện câu hỏi hợp lệ nào trong file! Vui lòng kiểm tra lại cột "Câu hỏi", "Đáp án A", "Đáp án B".</td></tr>';
        document.getElementById('previewBadge').className = 'badge bg-danger fs-6';
        document.getElementById('previewBadge').textContent = '0 câu hỏi hợp lệ';
        document.getElementById('btnSubmitImport').disabled = true;
    } else {
        validQuestions.forEach((q, i) => {
            let diffBadge = '<span class="badge bg-secondary">TB</span>';
            if (q.difficulty === 'easy') diffBadge = '<span class="badge bg-success">Dễ</span>';
            if (q.difficulty === 'hard') diffBadge = '<span class="badge bg-danger">Khó</span>';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold text-muted">${i + 1}</td>
                <td><div class="fw-semibold text-dark">${escapeHtml(q.question_text)}</div></td>
                <td>
                    <div class="small">
                        <span class="me-2 ${q.correct_answer === 'A' ? 'text-success fw-bold' : ''}"><b>A:</b> ${escapeHtml(q.option_a)}</span>
                        <span class="me-2 ${q.correct_answer === 'B' ? 'text-success fw-bold' : ''}"><b>B:</b> ${escapeHtml(q.option_b)}</span>
                        ${q.option_c ? `<span class="me-2 ${q.correct_answer === 'C' ? 'text-success fw-bold' : ''}"><b>C:</b> ${escapeHtml(q.option_c)}</span>` : ''}
                        ${q.option_d ? `<span class="${q.correct_answer === 'D' ? 'text-success fw-bold' : ''}"><b>D:</b> ${escapeHtml(q.option_d)}</span>` : ''}
                    </div>
                </td>
                <td class="text-center"><span class="badge bg-success fs-6">${q.correct_answer}</span></td>
                <td>${diffBadge}</td>
                <td><small class="text-muted">${escapeHtml(q.explanation || '--')}</small></td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('previewBadge').className = 'badge bg-success fs-6';
        document.getElementById('previewBadge').textContent = validQuestions.length + ' câu hỏi hợp lệ';
        document.getElementById('importCountTxt').textContent = validQuestions.length;
        document.getElementById('btnSubmitImport').disabled = false;
        document.getElementById('import_questions_json').value = JSON.stringify(validQuestions);
    }

    document.getElementById('excelPreviewContainer').classList.remove('d-none');
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// ===== Mở Modal Sửa =====
function openEditModal(q) {
    document.getElementById('edit_q_id').value        = q.id;
    document.getElementById('edit_q_text').value      = q.question_text || '';
    document.getElementById('edit_q_a').value         = q.option_a || '';
    document.getElementById('edit_q_b').value         = q.option_b || '';
    document.getElementById('edit_q_c').value         = q.option_c || '';
    document.getElementById('edit_q_d').value         = q.option_d || '';
    document.getElementById('edit_q_exp').value       = q.explanation || '';
    document.getElementById('edit_q_difficulty').value = q.difficulty || 'medium';

    var cor = (q.correct_answer || 'A').toUpperCase();
    var radEl = document.getElementById('radEdit' + cor);
    if (radEl) radEl.checked = true;

    document.getElementById('edit_q_course').value = q.course_id;
    loadChapters('edit', q.course_id, q.chapter_id, function() {
        if (q.chapter_id > 0) {
            loadLessons('edit', q.chapter_id, q.lesson_id);
        }
    });

    new bootstrap.Modal(document.getElementById('modalEditQuestion')).show();
}

// ===== Xác nhận Xóa =====
function confirmDelete(id) {
    if (confirm('Bạn có chắc muốn xóa câu hỏi #' + id + ' khỏi ngân hàng?\n(Nếu câu này có trong đề thi, nó cũng sẽ được rút ra)')) {
        document.getElementById('delete_q_id').value = id;
        document.getElementById('formDelete').submit();
    }
}
</script>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
