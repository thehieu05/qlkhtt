# EduNova — Hệ Thống Quản Lý & Đào Tạo Khóa Học Trực Tuyến

EduNova là nền tảng chia sẻ và quản trị khóa học trực tuyến được xây dựng bằng **PHP (MySQLi Object-Oriented)**, **MySQL**, **Bootstrap 5** và **Chart.js**.

---

## 📁 Cấu Trúc Thư Mục Dự Án

```text
quanlykhoahoc/
│
├── admin/                         # Module Quản trị viên & Giảng viên
│   ├── index.php                  # Bảng điều khiển (Dashboard) & Thống kê KPI
│   ├── ql_khoahoc.php             # US-02 & US-04: Quản lý thông tin & chi tiết khóa học
│   ├── ql_chuongmuc.php           # US-03: Quản lý chương mục & video bài giảng
│   ├── ql_hocvien.php             # US-05: Quản lý học trực tuyến & ghi danh học viên
│   ├── ngan_hang_cauhoi.php       # US-06: Ngân hàng câu hỏi trắc nghiệm
│   ├── ql_thi.php                 # US-07: Quản lý đề thi & kết quả làm bài
│   ├── thong_ke.php               # US-08: Thống kê báo cáo & biểu đồ Chart.js
│   ├── admin_layout.php           # Layout thanh điều hướng Sidebar & Topbar
│   ├── api_get_questions.php      # API trả lời JSON nạp câu hỏi vào đề thi
│   └── db_helper.php              # Tự động khởi tạo & đồng bộ cơ sở dữ liệu
│
├── includes/                      # Các thành phần giao diện dùng chung
│   ├── header.php                 # Đầu trang, logo, thanh tìm kiếm & dropdown tài khoản
│   ├── menu.php                   # Thanh điều hướng danh mục
│   ├── banner.php                 # Banner quảng bá & hero section trang chủ
│   ├── feedback_section.php       # Khung đánh giá phản hồi từ học viên
│   └── footer.php                 # Chân trang, cam kết & tiện ích hỗ trợ
│
├── config.php                     # Kết nối cơ sở dữ liệu (MySQLi Object-Oriented)
├── index.php                      # Trang chủ học viên & danh sách khóa học
├── login.php                      # Đăng nhập & Đăng ký tài khoản
├── logout.php                     # Đăng xuất phiên làm việc
├── profile.php                    # Trang hồ sơ cá nhân học viên
├── style.css                      # Toàn bộ mã nguồn CSS giao diện
└── README.md                      # Tài liệu hướng dẫn sử dụng
```

---

## 🚀 Hướng Dẫn Sử Dụng

1. **Khởi động XAMPP**: Bật Apache và MySQL.
2. **Cơ sở dữ liệu**: Import cơ sở dữ liệu `db_khoahoc`.
3. **Trang chủ học viên**: Truy cập `http://localhost/quanlykhoahoc/index.php`.
4. **Trang quản trị Giảng viên / Admin**: Truy cập `http://localhost/quanlykhoahoc/admin/index.php?quick=admin`.
