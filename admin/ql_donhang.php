<?php
require_once __DIR__ . '/../config.php';

require_admin('../login.php');

$page_title = 'Quản lý Đơn hàng Mô phỏng';
$active_nav = 'donhang';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($action === 'confirm' && $order_id > 0) {
        if (confirm_order_and_enroll($conn, $order_id)) {
            set_flash("Đã xác nhận đơn hàng #$order_id và cấp quyền học cho học viên!", 'success');
        } else {
            set_flash("Không thể xác nhận đơn hàng #$order_id.", 'danger');
        }
        header("Location: ql_donhang.php");
        exit();
    }

    if ($action === 'cancel' && $order_id > 0) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $stmt->close();
        set_flash("Đã hủy đơn hàng #$order_id.", 'warning');
        header("Location: ql_donhang.php");
        exit();
    }
}

$status_filter = $_GET['status'] ?? '';
$where_sql = "";
if (in_array($status_filter, ['pending', 'confirmed', 'cancelled'], true)) {
    $where_sql = "WHERE o.status = '" . $conn->real_escape_string($status_filter) . "'";
}

$orders = [];
$sql = "SELECT o.*, u.fullname, u.username, u.email,
               GROUP_CONCAT(CONCAT(c.title, ' (', FORMAT(oi.price, 0), 'đ)') SEPARATOR '<br>') AS items_desc
        FROM orders o
        JOIN users u ON u.id = o.user_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN courses c ON c.id = oi.course_id
        $where_sql
        GROUP BY o.id
        ORDER BY o.id DESC";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) $orders[] = $r;
}

include 'admin_layout.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h4>Quản lý Đơn hàng Mô phỏng</h4>
        <p>Xác nhận đơn hàng để tự động cấp quyền học (enrollments) hoặc hủy đơn</p>
    </div>
    <div class="btn-group">
        <a href="ql_donhang.php" class="btn btn-sm <?= $status_filter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">Tất cả</a>
        <a href="ql_donhang.php?status=pending" class="btn btn-sm <?= $status_filter === 'pending' ? 'btn-warning' : 'btn-outline-secondary' ?>">Chờ xác nhận</a>
        <a href="ql_donhang.php?status=confirmed" class="btn btn-sm <?= $status_filter === 'confirmed' ? 'btn-success' : 'btn-outline-secondary' ?>">Đã xác nhận</a>
        <a href="ql_donhang.php?status=cancelled" class="btn btn-sm <?= $status_filter === 'cancelled' ? 'btn-danger' : 'btn-outline-secondary' ?>">Đã hủy</a>
    </div>
</div>

<div class="table-card">
    <div class="table-card-header">
        <div class="title">Danh sách Đơn hàng (<?= count($orders) ?>)</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Học viên</th>
                    <th>Khóa học đặt mua</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-bold font-monospace">#<?= e($o['order_code']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($o['fullname']) ?></div>
                        <div class="small text-muted"><?= e($o['email']) ?></div>
                    </td>
                    <td class="small"><?= $o['items_desc'] ?: '—' ?></td>
                    <td class="fw-bold text-danger"><?= number_format((float)$o['total_amount'], 0, ',', '.') ?> đ</td>
                    <td>
                        <?php if ($o['status'] === 'confirmed'): ?>
                            <span class="badge-active">Đã xác nhận</span>
                        <?php elseif ($o['status'] === 'cancelled'): ?>
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-2">Đã hủy</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2">Chờ xác nhận</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if ($o['status'] === 'pending'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="confirm">
                                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Xác nhận đơn hàng này và cấp quyền học?')">
                                    <i class="bi bi-check-lg me-1"></i>Xác nhận
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hủy đơn hàng này?')">
                                    Hủy
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="small text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">Không có đơn hàng nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
