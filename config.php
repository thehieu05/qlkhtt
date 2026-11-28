<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đổi cổng 3307 theo cấu hình XAMPP hiện tại
$conn = new mysqli('localhost', 'root', '', 'db_khoahoc', 3307);
if ($conn->connect_error) {
    die("Kết nối CSDL thất bại: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/includes/helpers.php';
