<?php
require_once 'config.php';

// Quy tắc 1: Khách phải đăng nhập mới được đặt mua
require_login('login.php');

$user_id = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'direct_order') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    if ($course_id <= 0) {
        set_flash('Khóa học không hợp lệ.', 'danger');
        header("Location: danh_muc.php");
        exit();
    }

    // Kiểm tra khóa học tồn tại và đang công khai
    $stmt_c = $conn->prepare("SELECT id, title, price, status FROM courses WHERE id = ? AND status = 'published' LIMIT 1");
    $stmt_c->bind_param('i', $course_id);
    $stmt_c->execute();
    $course = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();

    if (!$course) {
        set_flash('Khóa học không tồn tại hoặc chưa công khai.', 'danger');
        header("Location: danh_muc.php");
        exit();
    }

    // Nếu đã được cấp quyền học thì chuyển thẳng vào học
    if (has_course_access($conn, $user_id, $course_id)) {
        set_flash('Bạn đã sở hữu khóa học này rồi.', 'info');
        header("Location: hoc.php?course_id=" . $course_id);
        exit();
    }

    // Kiểm tra xem đã có đơn hàng pending chứa khóa học này chưa
    $stmt_chk = $conn->prepare("SELECT o.order_code FROM orders o
                                JOIN order_items oi ON oi.order_id = o.id
                                WHERE o.user_id = ? AND oi.course_id = ? AND o.status = 'pending' LIMIT 1");
    $stmt_chk->bind_param('ii', $user_id, $course_id);
    $stmt_chk->execute();
    $existing = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if ($existing) {
        set_flash('Bạn đã có đơn hàng <b>' . e($existing['order_code']) . '</b> cho khóa học này đang chờ xác nhận.', 'warning');
        header("Location: profile.php?tab=donhang");
        exit();
    }

    $price = (float)$course['price'];
    $order_code = 'ORD' . date('YmdHis') . rand(10, 99);
    $note = trim($_POST['note'] ?? 'Đặt mua trực tiếp khóa học');

    $conn->begin_transaction();
    try {
        $stmt_ord = $conn->prepare("INSERT INTO orders (order_code, user_id, total_amount, payment_method, note, status)
                                    VALUES (?, ?, ?, 'simulated', ?, 'pending')");
        $stmt_ord->bind_param('sids', $order_code, $user_id, $price, $note);
        $stmt_ord->execute();
        $order_id = $stmt_ord->insert_id;
        $stmt_ord->close();

        // Lưu giá tại thời điểm đặt mua vào order_items
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, course_id, price) VALUES (?, ?, ?)");
        $stmt_item->bind_param('iid', $order_id, $course_id, $price);
        $stmt_item->execute();
        $stmt_item->close();

        $conn->commit();
        set_flash('Tạo đơn hàng mô phỏng <b>' . e($order_code) . '</b> thành công! Đơn hàng đang chờ quản trị viên xác nhận để cấp quyền học.', 'success');
    } catch (Throwable $e) {
        $conn->rollback();
        set_flash('Không thể tạo đơn hàng. Vui lòng thử lại.', 'danger');
    }

    header("Location: profile.php?tab=donhang");
    exit();
}

// Học viên tự hủy đơn hàng đang ở trạng thái pending
if ($action === 'cancel_order') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id > 0) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $order_id, $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            set_flash('Đã hủy đơn hàng thành công.', 'success');
        } else {
            set_flash('Không thể hủy đơn hàng này.', 'warning');
        }
        $stmt->close();
    }
    header("Location: profile.php?tab=donhang");
    exit();
}

header("Location: profile.php");
exit();
