<?php
/**
 * includes/helpers.php
 * Các hàm dùng chung cho xác thực, phân quyền, flash message, định dạng và tính tiến độ.
 */

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        $role = $_SESSION['role'] ?? '';
        return is_logged_in() && ($role === 'admin' || (string)$role === '1');
    }
}

if (!function_exists('require_login')) {
    function require_login(string $redirect_to = 'login.php'): void
    {
        if (!is_logged_in()) {
            $target = $redirect_to;
            if (!empty($_SERVER['REQUEST_URI']) && strpos($redirect_to, 'redirect=') === false) {
                $target .= (strpos($redirect_to, '?') !== false ? '&' : '?') . 'redirect=' . urlencode($_SERVER['REQUEST_URI']);
            }
            header("Location: " . $target);
            exit();
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(string $login_path = '../login.php'): void
    {
        if (!is_admin()) {
            header("Location: " . $login_path);
            exit();
        }
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash_msg'] = $message;
        $_SESSION['flash_type'] = $type;
    }
}

if (!function_exists('format_vnd')) {
    function format_vnd($amount): string
    {
        return number_format((float)$amount, 0, ',', '.') . 'đ';
    }
}

if (!function_exists('e')) {
    function e(?string $str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('slugify_text')) {
    function slugify_text(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $unicode = [
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
        ];
        foreach ($unicode as $ascii => $pattern) {
            $str = preg_replace("/($pattern)/u", $ascii, $str);
        }
        $str = preg_replace('/[^a-z0-9]+/', '-', $str);
        $str = trim($str, '-');
        return $str !== '' ? $str : ('item-' . time());
    }
}

/**
 * Kiểm tra học viên đã được cấp quyền học khóa học (đơn confirmed -> enrollment active) chưa.
 */
if (!function_exists('has_course_access')) {
    function has_course_access(mysqli $conn, int $user_id, int $course_id): bool
    {
        if ($user_id <= 0 || $course_id <= 0) {
            return false;
        }
        $stmt = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $user_id, $course_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $ok = ($res && $res->num_rows > 0);
        $stmt->close();
        return $ok;
    }
}

/**
 * Tính lại tiến độ khóa học (%) dựa trên số bài học đã hoàn thành / tổng số bài học.
 */
if (!function_exists('recalculate_course_progress')) {
    function recalculate_course_progress(mysqli $conn, int $user_id, int $course_id): int
    {
        $total = 0;
        $done = 0;

        $stmt_total = $conn->prepare("SELECT COUNT(*) AS total FROM lessons WHERE course_id = ?");
        if ($stmt_total) {
            $stmt_total->bind_param('i', $course_id);
            $stmt_total->execute();
            $r = $stmt_total->get_result()->fetch_assoc();
            $total = (int)($r['total'] ?? 0);
            $stmt_total->close();
        }

        $stmt_done = $conn->prepare("SELECT COUNT(*) AS done FROM lesson_progress WHERE user_id = ? AND course_id = ? AND completed = 1");
        if ($stmt_done) {
            $stmt_done->bind_param('ii', $user_id, $course_id);
            $stmt_done->execute();
            $r = $stmt_done->get_result()->fetch_assoc();
            $done = (int)($r['done'] ?? 0);
            $stmt_done->close();
        }

        $pct = ($total > 0) ? (int)min(100, round(($done / $total) * 100)) : 0;

        $stmt_upd = $conn->prepare("UPDATE enrollments SET progress_percent = ? WHERE user_id = ? AND course_id = ?");
        if ($stmt_upd) {
            $stmt_upd->bind_param('iii', $pct, $user_id, $course_id);
            $stmt_upd->execute();
            $stmt_upd->close();
        }

        return $pct;
    }
}

/**
 * Xác nhận đơn hàng mô phỏng và cấp quyền học (enrollments) cho các khóa học trong đơn.
 */
if (!function_exists('confirm_order_and_enroll')) {
    function confirm_order_and_enroll(mysqli $conn, int $order_id): bool
    {
        $stmt = $conn->prepare("SELECT id, user_id, status FROM orders WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return false;
        }

        $user_id = (int)$order['user_id'];

        $conn->begin_transaction();
        try {
            $stmt_upd = $conn->prepare("UPDATE orders SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?");
            $stmt_upd->bind_param('i', $order_id);
            $stmt_upd->execute();
            $stmt_upd->close();

            $stmt_items = $conn->prepare("SELECT course_id FROM order_items WHERE order_id = ?");
            $stmt_items->bind_param('i', $order_id);
            $stmt_items->execute();
            $res_items = $stmt_items->get_result();

            $stmt_enr = $conn->prepare("INSERT INTO enrollments (user_id, course_id, order_id, progress_percent, status)
                                        VALUES (?, ?, ?, 0, 'active')
                                        ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), status = 'active'");
            while ($item = $res_items->fetch_assoc()) {
                $cid = (int)$item['course_id'];
                if ($cid > 0) {
                    $stmt_enr->bind_param('iii', $user_id, $cid, $order_id);
                    $stmt_enr->execute();
                }
            }
            $stmt_enr->close();
            $stmt_items->close();

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            return false;
        }
    }
}
