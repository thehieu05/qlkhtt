# EduNova — Hệ Thống Quản Lý & Đào Tạo Khóa Học Trực Tuyến

EduNova là nền tảng chia sẻ và quản trị khóa học trực tuyến được thiết kế và xây dựng bằng **PHP (MySQLi Object-Oriented)**, **MySQL**, **Bootstrap 5**, và **Chart.js**. Hệ thống hỗ trợ đầy đủ quy trình đào tạo từ duyệt khóa học, đặt đơn mô phỏng, xác nhận ghi danh, học tập video/tài liệu, theo dõi tiến độ, đến làm trắc nghiệm chấm điểm tự động.

---

## 👥 Vai Trò & Chức Năng Chính

### 1. Khách truy cập (Guest)
- Xem danh sách khóa học đang công khai (`published`).
- Tìm kiếm khóa học theo tên hoặc từ khóa.
- Lọc khóa học theo danh mục môn học hoặc khoảng giá.
- Xem chi tiết khóa học: mô tả, ảnh đại diện, học phí, danh mục, cấu trúc chương và bài học xem trước (`preview`).
- Đăng ký tài khoản mới và đăng nhập an toàn (mật khẩu băm chuẩn `bcrypt`).

### 2. Học viên (Student)
- Quản lý thông tin cá nhân cơ bản (họ tên, email, đổi mật khẩu).
- Đặt mua khóa học trực tiếp; hệ thống tạo đơn hàng mô phỏng (`pending`).
- Xem danh sách đơn hàng đã đặt và theo dõi trạng thái (`pending`, `confirmed`, `cancelled`).
- Xem danh sách khóa học đã được kích hoạt ghi danh (`enrollments.status = 'active'`).
- Truy cập không gian học tập trực quan: xem bài giảng video, tải tài liệu đính kèm.
- Đánh dấu hoàn thành bài học, tự động tính toán và cập nhật % tiến độ khóa học.
- Làm bài tập/bài kiểm tra trắc nghiệm, tự động chấm điểm tức thì, xem chi tiết đáp án và lịch sử điểm số.

### 3. Quản trị viên (Admin)
- **Quản lý danh mục**: Thêm, sửa, ẩn/hiện hoặc xóa danh mục môn học.
- **Quản lý khóa học**: Thêm, sửa, đổi trạng thái (`published`, `draft`, `hidden`), cập nhật giá, ảnh đại diện. Hỗ trợ quy tắc xóa mềm/ẩn an toàn nếu khóa học đã có học viên đăng ký hoặc đơn hàng.
- **Quản lý chương mục & bài học**: Tạo chương mục (`sections`), bài giảng (`lessons`), nhúng video, tải lên tài liệu, sắp xếp thứ tự và gắn cờ cho phép học thử.
- **Quản lý trắc nghiệm**: Tạo bài thi/kiểm tra (`quizzes`), ngân hàng câu hỏi (`questions`), các lựa chọn A/B/C/D, đáp án đúng và lời giải thích.
- **Quản lý đơn hàng mô phỏng**: Xem chi tiết đơn hàng, duyệt xác nhận đơn (`confirmed` — tự động kích hoạt quyền ghi danh vào `enrollments`) hoặc hủy đơn (`cancelled`).
- **Quản lý học viên**: Xem danh sách học viên, quản lý trạng thái tài khoản (`active` / `locked`), theo dõi chi tiết tiến độ học tập và điểm thi trung bình của từng học viên.
- **Báo cáo & Thống kê**: Tổng quan số khóa học, học viên, đơn hàng và doanh thu mô phỏng; biểu đồ doanh thu theo tháng và tỷ lệ đạt trắc nghiệm với Chart.js.

---

## 🔒 Quy Tắc Nghiệp Vụ Cốt Lõi

1. **Bắt buộc đăng nhập**: Khách phải đăng nhập tài khoản học viên để thực hiện đặt mua khóa học.
2. **Quyền truy cập học tập**: Học viên chỉ có quyền truy cập vào nội dung bài giảng của khóa học khi đơn hàng mô phỏng đã được Admin xác nhận (`enrollments.status = 'active'`).
3. **Vòng đời đơn hàng**: Đơn hàng có 3 trạng thái: `pending` (chờ xác nhận), `confirmed` (đã duyệt), `cancelled` (đã hủy).
4. **Bảo toàn lịch sử giá**: Giá trong bảng `order_items` lưu lại giá tại thời điểm đặt mua, đảm bảo tính nhất quán lịch sử khi Admin cập nhật giá khóa học sau này.
5. **Hiển thị khóa học**: Khách chỉ thấy các khóa học có trạng thái `published`. Các khóa ở trạng thái `draft` (bản nháp) hoặc `hidden` (bị ẩn) sẽ không xuất hiện trên giao diện khách.
6. **Công thức tính tiến độ**:
   $$\text{Tiến độ (\%)} = \left(\frac{\text{Số bài học hoàn thành}}{\text{Tổng số bài học trong khóa}}\right) \times 100\%$$
7. **Chấm điểm trắc nghiệm**: Hệ thống tự động đối chiếu câu trả lời của học viên với đáp án đúng đã lưu trong bảng `questions` trên thang điểm 10, đồng thời lưu lịch sử chi tiết vào `quiz_attempts` và `quiz_answers`.

---

## 🗄️ Cấu Trúc Cơ Sở Dữ Liệu (13 Bảng Chuẩn Hóa)

Cơ sở dữ liệu: `db_khoahoc` (MySQL / phpMyAdmin)

| Bảng | Chức năng chính |
| :--- | :--- |
| `users` | Tài khoản, mật khẩu băm (`password_hash`), họ tên, email, vai trò (`admin`, `student`), trạng thái (`active`, `locked`). |
| `categories` | Danh mục môn học, slug, mô tả, trạng thái kích hoạt (`status`). |
| `courses` | Thông tin khóa học, giá, ảnh bìa, danh mục, trạng thái (`published`, `draft`, `hidden`). |
| `sections` | Các chương/phần học trong khóa học và thứ tự hiển thị (`sort_order`). |
| `lessons` | Bài giảng thuộc từng chương: tiêu đề, loại nội dung, URL video, tài liệu, thời lượng, bài học xem trước (`is_preview`), thứ tự. |
| `orders` | Đơn hàng mô phỏng của học viên, mã đơn, tổng tiền, phương thức thanh toán mô phỏng, trạng thái (`pending`, `confirmed`, `cancelled`). |
| `order_items` | Khóa học nằm trong mỗi đơn và giá đóng băng tại thời điểm đặt (`price_at_purchase`). |
| `enrollments` | Quyền truy cập khóa học sau khi đơn được xác nhận, thời điểm kích hoạt, % tiến độ hoàn thành. |
| `lesson_progress` | Ghi nhận trạng thái hoàn thành từng bài học cụ thể của học viên. |
| `quizzes` | Bài trắc nghiệm/kiểm tra thuộc khóa học hoặc chương mục, điểm đạt (`pass_score`), thời gian làm bài (`duration_minutes`). |
| `questions` | Câu hỏi, 4 lựa chọn (A, B, C, D), đáp án đúng và giải thích đáp án. |
| `quiz_attempts` | Mỗi lượt làm bài của học viên: thời gian bắt đầu/nộp bài, điểm số, trạng thái đạt/chưa đạt. |
| `quiz_answers` | Chi tiết câu trả lời học viên đã chọn trong từng lần nộp bài. |

---

## 📁 Cấu Trúc Thư Mục & File Dự Án

```text
quanlykhoahoc/
├── admin/                     # Module quản trị viên (Admin Panel)
│   ├── admin_layout.php       # Giao diện khung dùng chung (Sidebar, Header)
│   ├── index.php              # Bảng điều khiển tổng quan (Dashboard KPIs)
│   ├── ql_chuongmuc.php       # Quản lý chương mục & bài giảng (Videos, Tài liệu)
│   ├── ql_danhmuc.php         # Quản lý danh mục môn học (Thêm/Sửa/Xóa/Ẩn)
│   ├── ql_donhang.php         # Quản lý & duyệt đơn hàng mô phỏng
│   ├── ql_hocvien.php         # Quản lý học viên, khóa/mở tài khoản, xem tiến độ & điểm
│   ├── ql_khoahoc.php         # Quản lý khóa học & trạng thái xuất bản
│   ├── ql_thi.php             # Quản lý bài trắc nghiệm, câu hỏi, đáp án & kết quả
│   └── thong_ke.php           # Thống kê phân tích & biểu đồ doanh thu Chart.js
│
├── includes/                  # Thành phần giao diện & trợ giúp dùng chung
│   ├── banner.php             # Banner trang chủ
│   ├── feedback_section.php   # Đánh giá & nhận xét từ học viên
│   ├── footer.php             # Chân trang website
│   ├── header.php             # Thanh điều hướng trên cùng, tìm kiếm & tài khoản
│   ├── helpers.php            # Hàm hỗ trợ xác thực, phân quyền, tiến độ, kích hoạt đơn
│   └── menu.php               # Menu danh mục môn học tự động nạp từ CSDL
│
├── uploads/                   # Thư mục lưu trữ ảnh bìa khóa học, tài liệu tải lên
├── category_helper.php        # Trợ giúp nạp danh mục nhanh cho các trang giao diện
├── chitiet_khoahoc.php        # Trang chi tiết khóa học, xem thử bài giảng & đặt mua
├── config.php                 # Kết nối CSDL MySQLi OOP, thiết lập UTF-8mb4
├── danh_muc.php               # Trang hiển thị khóa học theo danh mục, lọc giá & tìm kiếm
├── hoc.php                    # Không gian phòng học trực tuyến, video & tài liệu
├── hoc_quiz_data.php          # API trả lời câu hỏi trắc nghiệm dạng JSON trong phòng học
├── hoc_quiz_submit.php        # API nộp bài & chấm điểm trắc nghiệm tự động
├── index.php                  # Trang chủ hệ thống, danh sách khóa học nổi bật
├── login.php                  # Đăng nhập, đăng ký tài khoản (Bcrypt password hashing)
├── logout.php                 # Đăng xuất phiên làm việc
├── order_action.php           # Xử lý đặt mua trực tiếp & hủy đơn hàng mô phỏng
├── profile.php                # Hồ sơ cá nhân: thông tin, đơn hàng, khóa học đã đăng ký, điểm thi
├── quiz.php                   # Giao diện làm bài trắc nghiệm độc lập & xem kết quả
├── style.css                  # Toàn bộ mã định dạng CSS giao diện
└── README.md                  # Tài liệu hướng dẫn sử dụng & cấu trúc hệ thống
```

---

## 🚀 Hướng Dẫn Cài Đặt & Sử Dụng

### 1. Khởi động môi trường
- Mở **XAMPP Control Panel** và bật **Apache** cùng **MySQL**.
- Cấu hình kết nối MySQL trong file `config.php`:
  ```php
  $conn = new mysqli('127.0.0.1:3307', 'root', '', 'db_khoahoc');
  ```
  *(Thay đổi cổng `3307` thành `3306` nếu cài đặt MySQL XAMPP mặc định).*

### 2. Dữ liệu & Tài Khoản Thử Nghiệm Mặc Định
Cơ sở dữ liệu `db_khoahoc` đã được cấu hình và đồng bộ sẵn dữ liệu mẫu trong MySQL:

- **Tài khoản Quản trị viên (Admin)**:
  - Tên đăng nhập: `admin`
  - Mật khẩu: `123456`
  - Quyền hạn: Quản lý toàn bộ hệ thống (`http://localhost/quanlykhoahoc/admin/index.php`)
- **Tài khoản Học viên mẫu (Student)**:
  - Tên đăng nhập: `hocvien`
  - Mật khẩu: `123456`
  - Quyền hạn: Học tập, đặt mua khóa học, làm bài trắc nghiệm (`http://localhost/quanlykhoahoc/index.php`)
