<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

// --- การเชื่อมต่อฐานข้อมูล ---
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "pixellink";
$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}
$condb->set_charset("utf8mb4");

// --- ตรวจสอบสิทธิ์ Admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้!'];
    header('Location: ../login.php');
    exit();
}

$admin_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';

// --- จัดการการอัปเดตสถานะและการลบ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // อัปเดตสถานะ
    if (isset($_POST['change_status'], $_POST['post_id'], $_POST['new_status'])) {
        $post_id = (int)$_POST['post_id'];
        $new_status = $_POST['new_status'];
        
        $stmt = $condb->prepare("UPDATE job_postings SET status = ? WHERE post_id = ?");
        $stmt->bind_param("si", $new_status, $post_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'อัปเดตสถานะโพสต์สำเร็จ!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'เกิดข้อผิดพลาดในการอัปเดตสถานะ'];
        }
        $stmt->close();
        header('Location: manage_posts.php');
        exit();
    }
    // ลบโพสต์
    if (isset($_POST['delete_post'], $_POST['post_id'])) {
        $post_id = (int)$_POST['post_id'];
        
        // (Optional: Logic to delete associated image file can be added here)
        
        $stmt = $condb->prepare("DELETE FROM job_postings WHERE post_id = ?");
        $stmt->bind_param("i", $post_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'ลบโพสต์สำเร็จ!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'เกิดข้อผิดพลาดในการลบโพสต์'];
        }
        $stmt->close();
        header('Location: manage_posts.php');
        exit();
    }
}


// --- Pagination ---
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- Filter และ Search ---
$search_query = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';

$sql_where = " WHERE 1=1";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql_where .= " AND (jp.title LIKE ? OR jp.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_param = "%" . $search_query . "%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
    $types .= 'ssss';
}
if (!empty($filter_status)) {
    $sql_where .= " AND jp.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

// --- นับจำนวนทั้งหมดสำหรับ Pagination ---
$sql_count = "SELECT COUNT(jp.post_id) as total FROM job_postings jp JOIN users u ON jp.designer_id = u.user_id" . $sql_where;
$stmt_count = $condb->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt_count->close();


// --- ดึงข้อมูลโพสต์ทั้งหมดมาแสดง ---
$all_posts = [];
$sql_fetch_all = "SELECT
                    jp.post_id, jp.title, jp.status, jp.posted_date,
                    u.first_name, u.last_name,
                    jc.category_name
                  FROM job_postings AS jp
                  JOIN users AS u ON jp.designer_id = u.user_id
                  LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                  $sql_where
                  ORDER BY jp.posted_date DESC
                  LIMIT ? OFFSET ?";

$types .= 'ii';
$params[] = $records_per_page;
$params[] = $offset;

$stmt_fetch = $condb->prepare($sql_fetch_all);
if ($stmt_fetch) {
    if (!empty($params)) {
        $stmt_fetch->bind_param($types, ...$params);
    }
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    $all_posts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_fetch->close();
}
$condb->close();

function getStatusColorClass($status) {
    switch ($status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        case 'inactive': return 'bg-gray-100 text-gray-800';
        case 'completed': return 'bg-blue-100 text-blue-800';
        default: return 'bg-slate-100 text-slate-800';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการโพสต์งาน - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f1f5f9; }
        .btn { transition: all 0.3s ease; }
    </style>
</head>
<body class="min-h-screen flex">

    <aside class="w-64 bg-slate-800 text-white flex flex-col">
        <div class="p-6 text-center text-2xl font-bold border-b border-slate-700">Admin Panel</div>
        <nav class="flex-grow">
            <a href="main.php" class="block py-3 px-6 hover:bg-slate-700"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
            <a href="manage_users.php" class="block py-3 px-6 hover:bg-slate-700"><i class="fas fa-users mr-2"></i>จัดการผู้ใช้</a>
            <a href="manage_posts.php" class="block py-3 px-6 bg-slate-900"><i class="fas fa-file-alt mr-2"></i>จัดการโพสต์</a>
        </nav>
        <div class="p-6 border-t border-slate-700">
            <a href="../logout.php" class="block text-center bg-red-500 hover:bg-red-600 rounded-lg py-2">ออกจากระบบ</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="bg-white shadow p-6 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-slate-800">จัดการประกาศรับงาน</h1>
            <span class="text-slate-600">สวัสดี, <?= htmlspecialchars($admin_name) ?>!</span>
        </header>

        <main class="flex-grow p-6">
            
            <form method="GET" class="mb-6 p-4 bg-white rounded-lg shadow">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="ค้นหาชื่องาน, รายละเอียด, ชื่อคน..." class="w-full px-4 py-2 border rounded-lg">
                    <select name="status" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">-- ทุกสถานะ --</option>
                        <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">ค้นหา</button>
                </div>
            </form>

            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-500">
                        <thead class="text-xs text-slate-700 uppercase bg-slate-100">
                            <tr>
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">ชื่องาน</th>
                                <th class="px-6 py-3">ผู้ประกาศ</th>
                                <th class="px-6 py-3">สถานะ</th>
                                <th class="px-6 py-3">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_posts as $post): ?>
                            <tr class="bg-white border-b hover:bg-slate-50">
                                <td class="px-6 py-4 font-medium text-slate-900"><?= $post['post_id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($post['title']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($post['first_name'] . ' ' . $post['last_name']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= getStatusColorClass($post['status']) ?>">
                                        <?= htmlspecialchars($post['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 flex items-center space-x-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                        <select name="new_status" onchange="this.form.submit()" class="text-xs border rounded">
                                            <option value="" disabled selected>เปลี่ยนสถานะ</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                        <input type="hidden" name="change_status" value="1">
                                    </form>
                                    <form method="POST" class="inline" onsubmit="confirmDelete(event, <?= $post['post_id'] ?>)">
                                        <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                        <input type="hidden" name="delete_post" value="1">
                                        <button type="submit" class="btn text-red-500 hover:text-red-700" title="ลบ"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                 <div class="p-4">
                    <nav class="flex justify-center">
                        <ul class="flex items-center -space-x-px h-8 text-sm">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&status=<?= urlencode($filter_status) ?>" 
                                   class="flex items-center justify-center px-3 h-8 leading-tight <?= $current_page == $i ? 'text-blue-600 bg-blue-50 border-blue-300' : 'text-gray-500 bg-white border-gray-300' ?> hover:bg-gray-100 hover:text-gray-700">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </main>
    </div>

    <script>
    function confirmDelete(event, postId) {
        event.preventDefault();
        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: `คุณต้องการลบโพสต์ ID: ${postId} ใช่หรือไม่?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                event.target.submit();
            }
        });
    }
    </script>
</body>
</html>