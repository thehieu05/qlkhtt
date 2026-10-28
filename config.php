<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "db_khoahoc";

$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) {
    die("Kết nối CSDL thất bại: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

// Hàm kiểm tra đăng nhập (dùng ở đầu các trang cần bảo mật)
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>