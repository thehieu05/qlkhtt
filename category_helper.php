<?php
/**
 * category_helper.php
 * Truy vấn và quản lý bảng `categories` trong CSDL `db_khoahoc`.
 */

/**
 * Lấy danh sách danh mục đang hiển thị (status = 'active') kèm số lượng khóa học đang công khai.
 */
function get_categories_with_counts(mysqli $conn): array
{
    $sql = "SELECT cat.id, cat.name, cat.slug, cat.icon, cat.sort_order,
                   COUNT(c.id) AS course_count
            FROM categories cat
            LEFT JOIN courses c
              ON c.category_id = cat.id AND c.status = 'published'
            WHERE cat.status = 'active'
            GROUP BY cat.id, cat.name, cat.slug, cat.icon, cat.sort_order
            ORDER BY cat.sort_order ASC, cat.id ASC";

    $result = $conn->query($sql);
    $list = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

/**
 * Lấy tất cả danh mục (cho trang Admin).
 */
function get_all_categories(mysqli $conn): array
{
    $sql = "SELECT cat.*,
                   COUNT(c.id) AS course_count
            FROM categories cat
            LEFT JOIN courses c ON c.category_id = cat.id
            GROUP BY cat.id
            ORDER BY cat.sort_order ASC, cat.id ASC";
    $result = $conn->query($sql);
    $list = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

/**
 * Tạo slug không dấu từ tên danh mục tiếng Việt.
 */
function slugify_category(string $str): string
{
    return slugify_text($str);
}

/**
 * Thêm danh mục đào tạo mới vào bảng `categories` (hoặc trả về danh mục đã tồn tại).
 */
function create_course_category(mysqli $conn, string $name, string $icon = 'bi-folder', string $description = ''): array
{
    $clean_name = trim($name);
    if ($clean_name === '') {
        return ['success' => false, 'message' => 'Tên danh mục không được để trống.'];
    }

    $stmt_chk = $conn->prepare("SELECT id, name, slug FROM categories WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1");
    if ($stmt_chk) {
        $stmt_chk->bind_param('s', $clean_name);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();
        if ($res_chk && $res_chk->num_rows > 0) {
            $existing = $res_chk->fetch_assoc();
            $stmt_chk->close();
            return [
                'success' => true,
                'existed' => true,
                'category' => $existing,
                'message' => 'Danh mục này đã tồn tại trong hệ thống.',
            ];
        }
        $stmt_chk->close();
    }

    $base_slug = slugify_category($clean_name);
    $slug = $base_slug;
    $suffix = 1;
    while (true) {
        $s_chk = $conn->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
        if (!$s_chk) {
            break;
        }
        $s_chk->bind_param('s', $slug);
        $s_chk->execute();
        $s_res = $s_chk->get_result();
        $exists = ($s_res && $s_res->num_rows > 0);
        $s_chk->close();
        if (!$exists) {
            break;
        }
        $suffix++;
        $slug = $base_slug . '-' . $suffix;
    }

    $max_res = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM categories");
    $next_order = ($max_res && ($r = $max_res->fetch_assoc())) ? (int)$r['next_order'] : 1;

    $stmt_ins = $conn->prepare("INSERT INTO categories (name, slug, icon, description, sort_order, status) VALUES (?, ?, ?, ?, ?, 'active')");
    if (!$stmt_ins) {
        return ['success' => false, 'message' => 'Không thể chuẩn bị câu lệnh thêm danh mục.'];
    }
    $stmt_ins->bind_param('ssssi', $clean_name, $slug, $icon, $description, $next_order);
    $ok = $stmt_ins->execute();
    $new_id = $stmt_ins->insert_id;
    $stmt_ins->close();

    if (!$ok) {
        return ['success' => false, 'message' => 'Không thể lưu danh mục mới vào cơ sở dữ liệu.'];
    }

    return [
        'success' => true,
        'existed' => false,
        'category' => [
            'id' => $new_id,
            'name' => $clean_name,
            'slug' => $slug,
            'icon' => $icon,
            'sort_order' => $next_order,
        ],
        'message' => 'Đã thêm danh mục mới thành công.',
    ];
}
