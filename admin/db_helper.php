<?php
// Tự động kiểm tra và khởi tạo các bảng CSDL cho module Admin nếu chưa có
function init_admin_database($conn) {
    // 1. Kiểm tra & thêm cột cho bảng courses
    $cols = [];
    $cr = $conn->query("SHOW COLUMNS FROM courses");
    if ($cr) {
        while ($col = $cr->fetch_assoc()) {
            $cols[] = $col['Field'];
        }
    }
    if (!empty($cols)) {
        if (!in_array('price', $cols)) {
            $conn->query("ALTER TABLE courses ADD COLUMN price DECIMAL(10,2) DEFAULT 499000 AFTER description");
        }
        if (!in_array('old_price', $cols)) {
            $conn->query("ALTER TABLE courses ADD COLUMN old_price DECIMAL(10,2) DEFAULT 899000 AFTER price");
        }
        if (!in_array('level', $cols)) {
            $conn->query("ALTER TABLE courses ADD COLUMN level VARCHAR(50) DEFAULT 'Cơ bản đến Nâng cao' AFTER old_price");
        }
        if (!in_array('category', $cols)) {
            $conn->query("ALTER TABLE courses ADD COLUMN category VARCHAR(100) DEFAULT 'Lập trình' AFTER level");
        }
        if (!in_array('is_active', $cols)) {
            $conn->query("ALTER TABLE courses ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER category");
        }
        if (!in_array('created_at', $cols)) {
            $conn->query("ALTER TABLE courses ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER is_active");
        }
    }

    // 2. Tạo bảng chapters (Chương mục khóa học)
    $conn->query("CREATE TABLE IF NOT EXISTS chapters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Tạo bảng lessons (Bài giảng)
    $conn->query("CREATE TABLE IF NOT EXISTS lessons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chapter_id INT NOT NULL DEFAULT 0,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        video_url VARCHAR(500) DEFAULT '',
        content TEXT DEFAULT '',
        duration_minutes INT DEFAULT 15,
        sort_order INT DEFAULT 0,
        is_free TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Đảm bảo các cột cần thiết trong lessons luôn tồn tại
    $l_cols = [];
    $l_res = $conn->query("SHOW COLUMNS FROM lessons");
    if ($l_res) {
        while ($col = $l_res->fetch_assoc()) $l_cols[] = $col['Field'];
    }
    if (!empty($l_cols)) {
        if (!in_array('chapter_id', $l_cols)) {
            $conn->query("ALTER TABLE lessons ADD COLUMN chapter_id INT NOT NULL DEFAULT 0 AFTER id");
        }
        if (!in_array('title', $l_cols)) {
            $conn->query("ALTER TABLE lessons ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER course_id");
            if (in_array('lesson_title', $l_cols)) {
                $conn->query("UPDATE lessons SET title = lesson_title WHERE title = '' OR title IS NULL");
            }
        }
        if (!in_array('video_url', $l_cols)) {
            $conn->query("ALTER TABLE lessons ADD COLUMN video_url VARCHAR(500) DEFAULT '' AFTER title");
            if (in_array('youtube_embed_url', $l_cols)) {
                $conn->query("UPDATE lessons SET video_url = youtube_embed_url WHERE video_url = '' OR video_url IS NULL");
            }
        }
        if (!in_array('content', $l_cols)) {
            $conn->query("ALTER TABLE lessons ADD COLUMN content TEXT DEFAULT '' AFTER video_url");
        }
        if (!in_array('duration_minutes', $l_cols)) {
            $conn->query("ALTER TABLE lessons ADD COLUMN duration_minutes INT DEFAULT 15 AFTER content");
        }
        if (!in_array('sort_order', $l_cols)) {
            $conn->query("ALTER TABLE lessons ADD COLUMN sort_order INT DEFAULT 0 AFTER duration_minutes");
        }
        if (!in_array('is_free', $l_cols)) {
            $conn->query("ALTER TABLE lessons ADD COLUMN is_free TINYINT(1) DEFAULT 0 AFTER sort_order");
        }
        if (!in_array('created_at', $l_cols)) {
            $conn->query("ALTER TABLE lessons ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER is_free");
        }
    }

    // 4. Tạo bảng enrollments (Học viên đăng ký học)
    $conn->query("CREATE TABLE IF NOT EXISTS enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        progress_percent INT DEFAULT 0,
        status VARCHAR(50) DEFAULT 'active',
        UNIQUE KEY unique_user_course (user_id, course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. Tạo bảng questions (Ngân hàng câu hỏi trắc nghiệm)
    $conn->query("CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        chapter_id INT DEFAULT 0,
        question_text TEXT NOT NULL,
        option_a VARCHAR(500) NOT NULL,
        option_b VARCHAR(500) NOT NULL,
        option_c VARCHAR(500) DEFAULT '',
        option_d VARCHAR(500) DEFAULT '',
        correct_answer CHAR(1) NOT NULL DEFAULT 'A',
        difficulty VARCHAR(50) DEFAULT 'medium',
        explanation TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Đảm bảo các cột cần thiết trong questions luôn tồn tại
    $q_cols = [];
    $q_res = $conn->query("SHOW COLUMNS FROM questions");
    if ($q_res) {
        while ($col = $q_res->fetch_assoc()) $q_cols[] = $col['Field'];
    }
    if (!empty($q_cols)) {
        if (!in_array('correct_answer', $q_cols)) {
            $conn->query("ALTER TABLE questions ADD COLUMN correct_answer CHAR(1) NOT NULL DEFAULT 'A' AFTER option_d");
            if (in_array('correct_option', $q_cols)) {
                $conn->query("UPDATE questions SET correct_answer = correct_option WHERE correct_answer = 'A' OR correct_answer IS NULL");
            }
        }
        if (!in_array('difficulty', $q_cols)) {
            $conn->query("ALTER TABLE questions ADD COLUMN difficulty VARCHAR(50) DEFAULT 'medium' AFTER correct_answer");
        }
        if (!in_array('explanation', $q_cols)) {
            $conn->query("ALTER TABLE questions ADD COLUMN explanation TEXT DEFAULT '' AFTER difficulty");
        }
        if (!in_array('chapter_id', $q_cols)) {
            $conn->query("ALTER TABLE questions ADD COLUMN chapter_id INT DEFAULT 0 AFTER course_id");
        }
        if (!in_array('created_at', $q_cols)) {
            $conn->query("ALTER TABLE questions ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER explanation");
        }
    }

    // 6. Tạo bảng exams (Quản lý đề thi)
    $conn->query("CREATE TABLE IF NOT EXISTS exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT '',
        duration_minutes INT DEFAULT 45,
        pass_score INT DEFAULT 70,
        max_attempts INT DEFAULT 3,
        shuffle_questions TINYINT(1) DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 7. Tạo bảng exam_questions (Câu hỏi trong đề thi)
    $conn->query("CREATE TABLE IF NOT EXISTS exam_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        question_id INT NOT NULL,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 8. Tạo bảng exam_results (Kết quả thi của học viên)
    $conn->query("CREATE TABLE IF NOT EXISTS exam_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        user_id INT NOT NULL,
        score INT DEFAULT 0,
        total_questions INT DEFAULT 0,
        correct_answers INT DEFAULT 0,
        time_taken_seconds INT DEFAULT 0,
        passed TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Nạp dữ liệu mẫu nếu chưa có chương mục nào
    $ch_cnt = $conn->query("SELECT COUNT(*) FROM chapters")->fetch_row()[0] ?? 0;
    if ($ch_cnt == 0) {
        seed_sample_data($conn);
    }

    // Đồng bộ chapter_id cho các bài giảng chưa có chương
    $first_ch = $conn->query("SELECT id, course_id FROM chapters ORDER BY id ASC LIMIT 1")->fetch_assoc();
    if ($first_ch) {
        $ch_id = (int)$first_ch['id'];
        $conn->query("UPDATE lessons SET chapter_id = $ch_id WHERE chapter_id = 0 OR chapter_id IS NULL");
    }
}

// Hàm nạp dữ liệu mẫu thực tế
function seed_sample_data($conn) {
    // Lấy ID khóa học đầu tiên
    $res = $conn->query("SELECT id FROM courses ORDER BY id ASC LIMIT 3");
    $c_ids = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) $c_ids[] = (int)$r['id'];
    }
    if (empty($c_ids)) return;

    $c1 = $c_ids[0];

    // Tạo chương mục mẫu cho khóa học 1
    $conn->query("INSERT INTO chapters (course_id, title, sort_order) VALUES 
        ($c1, 'Chương 1: Tổng quan và Cài đặt môi trường', 1),
        ($c1, 'Chương 2: Cú pháp cơ bản và Tư duy lập trình', 2),
        ($c1, 'Chương 3: Làm việc với CSDL MySQL thực tế', 3),
        ($c1, 'Chương 4: Xây dựng Dự án thực chiến từ A-Z', 4)");

    $res_ch = $conn->query("SELECT id FROM chapters WHERE course_id = $c1 ORDER BY sort_order ASC");
    $chapters = [];
    if ($res_ch) {
        while ($r = $res_ch->fetch_assoc()) $chapters[] = (int)$r['id'];
    }

    if (!empty($chapters)) {
        $ch1 = $chapters[0];
        $ch2 = $chapters[1] ?? $ch1;
        $ch3 = $chapters[2] ?? $ch1;

        // Tạo bài giảng mẫu
        $conn->query("INSERT INTO lessons (chapter_id, course_id, title, video_url, content, duration_minutes, sort_order, is_free) VALUES
            ($ch1, $c1, 'Bài 1: Giới thiệu khóa học & Lộ trình chi tiết', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'Chào mừng bạn đến với khóa học!', 10, 1, 1),
            ($ch1, $c1, 'Bài 2: Hướng dẫn cài đặt XAMPP, VS Code & Công cụ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'Cài đặt môi trường phát triển cục bộ.', 15, 2, 1),
            ($ch2, $c1, 'Bài 3: Biến, Kiểu dữ liệu và Toán tử cơ bản', '', 'Học cú pháp cốt lõi và tư duy xử lý logic.', 25, 3, 0),
            ($ch2, $c1, 'Bài 4: Cấu trúc điều kiện & Vòng lặp nâng cao', '', 'Vận dụng if/else, switch case và vòng lặp for/while.', 30, 4, 0),
            ($ch3, $c1, 'Bài 5: Kết nối MySQLi và xử lý CRUD dữ liệu', '', 'Thực hành kết nối Database và thao tác dữ liệu an toàn.', 40, 5, 0)");
    }

    // Tạo câu hỏi mẫu
    $conn->query("INSERT INTO questions (course_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty, explanation) VALUES
        ($c1, 'Trong PHP, ký tự nào bắt đầu tên một biến?', '$ (Dollar)', '@ (At)', '# (Hash)', '& (Ampersand)', 'A', 'easy', 'Tất cả các biến trong PHP đều bắt đầu bằng dấu $ (ví dụ: $ten_bien).'),
        ($c1, 'Để chống tấn công SQL Injection trong PHP, phương pháp nào tốt nhất?', 'Prepared Statement (PDO hoặc MySQLi)', 'Dùng addslashes()', 'Không cần làm gì', 'Dùng hàm md5()', 'A', 'medium', 'Prepared Statements giúp tách biệt lệnh SQL và dữ liệu đầu vào, ngăn chặn triệt để SQL Injection.'),
        ($c1, 'Hàm nào trong PHP dùng để bắt đầu một phiên làm việc (Session)?', 'session_start()', 'start_session()', 'session_init()', 'open_session()', 'A', 'easy', 'Hàm session_start() dùng để khởi tạo hoặc phục hồi session hiện tại.'),
        ($c1, 'Phương thức HTTP nào an toàn hơn khi gửi dữ liệu mật khẩu từ Form?', 'POST', 'GET', 'PUT', 'DELETE', 'A', 'easy', 'Phương thức POST gửi dữ liệu trong thân request (body), không lộ dữ liệu trên thanh địa chỉ URL.'),
        ($c1, 'Khóa chính (Primary Key) trong MySQL có đặc điểm nào sau đây?', 'Giá trị duy nhất và không được NULL', 'Có thể trùng lặp', 'Có thể nhận giá trị NULL', 'Tự động xóa sau 30 ngày', 'A', 'medium', 'Khóa chính bắt buộc phải duy nhất và không được phép chứa giá trị NULL.')");

    // Tạo đề thi mẫu
    $conn->query("INSERT INTO exams (course_id, title, description, duration_minutes, pass_score, max_attempts, is_active) VALUES
        ($c1, 'Kiểm tra Đánh giá Năng lực - Phần 1', 'Đề thi trắc nghiệm 5 câu hỏi cốt lõi để đánh giá mức độ hiểu bài của học viên.', 30, 70, 3, 1)");

    $exam_id = $conn->insert_id;
    if ($exam_id > 0) {
        $q_res = $conn->query("SELECT id FROM questions WHERE course_id = $c1 LIMIT 5");
        if ($q_res) {
            $ord = 1;
            while ($qr = $q_res->fetch_assoc()) {
                $qid = (int)$qr['id'];
                $conn->query("INSERT INTO exam_questions (exam_id, question_id, sort_order) VALUES ($exam_id, $qid, $ord)");
                $ord++;
            }
        }
    }

    // Đăng ký mẫu học viên vào khóa học
    $u_res = $conn->query("SELECT id FROM users WHERE role = 0 LIMIT 5");
    if ($u_res) {
        while ($ur = $u_res->fetch_assoc()) {
            $uid = (int)$ur['id'];
            $conn->query("INSERT IGNORE INTO enrollments (user_id, course_id, progress_percent, status) VALUES ($uid, $c1, 45, 'active')");
        }
    }
}
