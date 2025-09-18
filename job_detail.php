<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- การเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";
$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}
$condb->set_charset("utf8mb4");

// --- ตัวแปรเริ่มต้น ---
$job_data = null;
$designer_profile = null;
$other_works = [];
$error_message = '';
$job_type = '';
$loggedInUserName = '';

// --- ดึงชื่อผู้ใช้ที่ล็อกอิน ---
if (isset($_SESSION['user_id'])) {
    // (ส่วนนี้เหมือนเดิม)
    $loggedInUserName = $_SESSION['username'] ?? $_SESSION['full_name'] ?? '';
    if (empty($loggedInUserName)) {
        $user_id = $_SESSION['user_id'];
        $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
        $stmt_user = $condb->prepare($sql_user);
        if ($stmt_user) {
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            if ($user_info = $result_user->fetch_assoc()) {
                $loggedInUserName = $user_info['first_name'] . ' ' . $user_info['last_name'];
            }
            $stmt_user->close();
        }
    }
}


// --- [จุดที่แก้ไข 1] ดึงข้อมูลหลักของงาน และข้อมูลโปรไฟล์ของนักออกแบบ ---
if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['type']) && $_GET['type'] === 'posting') {
    $job_id = (int)$_GET['id'];
    $job_type = $_GET['type'];

    // SQL หลัก: ดึงข้อมูลงานพร้อมกับข้อมูลโปรไฟล์ของ Designer
    $sql = "SELECT
                jp.post_id AS id, jp.title, jp.description, jp.price_range, jp.posted_date,
                u.user_id AS owner_id, u.first_name, u.last_name,
                jc.category_name, 
                uf.file_path AS job_image_path,
                p.bio, p.skills, p.profile_picture_url
            FROM job_postings AS jp
            JOIN users AS u ON jp.designer_id = u.user_id
            LEFT JOIN profiles AS p ON u.user_id = p.user_id
            LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
            LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id
            WHERE jp.post_id = ? AND jp.status = 'active'";

    $stmt = $condb->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $job_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $job_data = $result->fetch_assoc();

            // --- [จุดที่แก้ไข 2] ดึงข้อมูลผลงานอื่นๆ ของนักออกแบบคนเดียวกัน ---
            $designer_id = $job_data['owner_id'];
            $sql_other_works = "SELECT 
                                    jp.post_id, jp.title, uf.file_path 
                                FROM job_postings jp
                                LEFT JOIN uploaded_files uf ON jp.main_image_id = uf.file_id
                                WHERE jp.designer_id = ? AND jp.post_id != ? AND jp.status = 'active'
                                ORDER BY jp.posted_date DESC
                                LIMIT 6";

            $stmt_other = $condb->prepare($sql_other_works);
            if ($stmt_other) {
                $stmt_other->bind_param("ii", $designer_id, $job_id);
                $stmt_other->execute();
                $other_works_result = $stmt_other->get_result();
                while ($row = $other_works_result->fetch_assoc()) {
                    $other_works[] = $row;
                }
                $stmt_other->close();
            }
        } else {
            $error_message = "ไม่พบประกาศรับงานนี้ หรือประกาศถูกปิดไปแล้ว";
        }
        $stmt->close();
    }
} else {
    $error_message = "URL ไม่ถูกต้อง: ไม่พบ ID หรือประเภทของงาน";
}

$condb->close();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลงาน: <?= $job_data ? htmlspecialchars($job_data['title']) : 'ไม่พบงาน' ?> | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 150, 210, 0.4);
        }

        * {
            font-family: 'Kanit', sans-serif;
            font-style: normal;
            font-weight: 400;
        }

        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf3 100%);
            color: #2c3e50;
            overflow-x: hidden;
        }

        .navbar {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 150, 210, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #0d96d2 0%, #0a5f97 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 150, 210, 0.5);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2);
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4);
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
        }

        .text-gradient {
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pixellink-logo,
        .pixellink-logo-footer {
            font-weight: 700;
            font-size: 2.25rem;
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pixellink-logo b,
        .pixellink-logo-footer b {
            color: #0d96d2;
        }

        .card-item {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .card-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .card-image {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }

        .hero-section {
            background-image: url('dist/img/cover.png');
            background-size: cover;
            background-position: center;
            position: relative;
            z-index: 1;
            padding: 8rem 0;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }
    </style>
</head>

<body class="bg-gray-100">

    <?php
    // วาง Logic นี้ไว้ด้านบนสุดของไฟล์ nav.php หรือก่อนส่วน HTML ของ Navbar

    // 1. กำหนด URL เริ่มต้นสำหรับผู้ที่ยังไม่ล็อกอิน
    $home_link = 'index.php'; // หรือ /index.php ตามโครงสร้างเว็บของคุณ

    // 2. ตรวจสอบว่ามีการล็อกอินหรือยัง
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
        // 3. กำหนด URL ตามประเภทผู้ใช้
        switch ($_SESSION['user_type']) {
            case 'designer':
                $home_link = 'designer/main.php';
                break;
            case 'client':
                $home_link = 'client/main.php';
                break;
            case 'admin':
                $home_link = 'admin/main.php';
                break;
        }
    }
    ?>

    <nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">

            <a href="<?= htmlspecialchars($home_link) ?>">
                <img src="dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
            </a>

            <div class="space-x-4 flex items-center">
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>

                    <?php
                    $profile_url = '#'; // URL เริ่มต้น
                    if ($_SESSION['user_type'] === 'designer') {
                        $profile_url = 'designer/view_profile.php?user_id=' . $_SESSION['user_id'];
                    } elseif ($_SESSION['user_type'] === 'client') {
                        // หากมีหน้าโปรไฟล์ผู้ว่าจ้าง ให้แก้ลิงก์ตรงนี้
                        $profile_url = 'client/view_profile.php?user_id=' . $_SESSION['user_id'];
                    }
                    ?>
                    <a href="<?= htmlspecialchars($profile_url); ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
                    <a href="logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>

                <?php else : ?>
                    <a href="login.php" class="font-semibold text-slate-600 hover:text-blue-600 transition-colors">เข้าสู่ระบบ</a>
                    <a href="register.php" class="btn-primary text-white px-5 py-2 rounded-lg font-semibold shadow-md">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <?php if (!empty($error_message)) : ?>
            <div class="text-center py-20">
                <p class="text-red-500 text-lg"><?= htmlspecialchars($error_message) ?></p>
                <a href="index.php" class="mt-4 inline-block btn-primary text-white px-6 py-2 rounded-lg font-semibold shadow-lg">กลับหน้าหลัก</a>
            </div>
        <?php elseif ($job_data) : ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-12">

                <div class="lg:col-span-2 space-y-8">
                    <div>
                        <?php
                        $image_source = 'dist/img/pdpa02.jpg'; // ภาพเริ่มต้น
                        if (!empty($job_data['job_image_path'])) {
                            $correct_path = preg_replace('/^\.\.\//', '', $job_data['job_image_path']);
                            if (file_exists($correct_path)) {
                                $image_source = htmlspecialchars($correct_path);
                            }
                        }
                        ?>
                        <img src="<?= $image_source ?>" alt="ภาพประกอบงาน" class="w-full h-auto rounded-xl shadow-lg object-cover aspect-[16/9]">
                    </div>

                    <div class="bg-white p-6 sm:p-8 rounded-xl shadow-md">
                        <h2 class="text-2xl font-bold text-slate-800 mb-4">รายละเอียดบริการ</h2>
                        <div class="prose prose-lg max-w-none text-slate-600 leading-relaxed">
                            <?= nl2br(htmlspecialchars($job_data['description'])) ?>
                        </div>
                    </div>

                    <?php if (!empty($other_works)): ?>
                        <div class="bg-white p-6 sm:p-8 rounded-xl shadow-md">
                            <h2 class="text-2xl font-bold text-slate-800 mb-6">ผลงานอื่นๆ ของนักออกแบบ</h2>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <?php foreach ($other_works as $work): ?>
                                    <a href="job_detail.php?id=<?= $work['post_id'] ?>&type=posting" class="block group">
                                        <?php
                                        $work_image = 'dist/img/pdpa02.jpg';
                                        if (!empty($work['file_path'])) {
                                            $correct_path = preg_replace('/^\.\.\//', '', $work['file_path']);
                                            if (file_exists($correct_path)) {
                                                $work_image = htmlspecialchars($correct_path);
                                            }
                                        }
                                        ?>
                                        <img src="<?= $work_image ?>" alt="<?= htmlspecialchars($work['title']) ?>"
                                            class="w-full h-full object-cover rounded-lg shadow-sm aspect-square transition-transform duration-300 group-hover:scale-105">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="lg:col-span-1 space-y-8">
                    <div class="bg-white p-6 sm:p-8 rounded-xl shadow-md sticky top-28">
                        <div class="border-b border-gray-200 pb-6 mb-6">
                            <span class="bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full"><?= htmlspecialchars($job_data['category_name'] ?? 'ไม่ระบุ') ?></span>
                            <h1 class="text-3xl font-bold text-slate-900 mt-3"><?= htmlspecialchars($job_data['title']) ?></h1>
                            <p class="text-3xl font-bold text-green-600 mt-4">
                                <?= htmlspecialchars($job_data['price_range'] ?? 'สอบถามราคา') ?>
                            </p>
                        </div>

                        <div class="flex items-center space-x-4 mb-6">
                            <?php
                            $profile_pic = 'dist/img/avatar.png'; // ภาพโปรไฟล์เริ่มต้น
                            if (!empty($job_data['profile_picture_url'])) {
                                $correct_path = preg_replace('/^\.\.\//', '', $job_data['profile_picture_url']);
                                if (file_exists($correct_path)) {
                                    $profile_pic = htmlspecialchars($correct_path);
                                }
                            }
                            ?>
                            <img src="<?= $profile_pic ?>" alt="โปรไฟล์" class="w-16 h-16 rounded-full object-cover">
                            <div>
                                <h3 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($job_data['first_name'] . ' ' . $job_data['last_name']) ?></h3>
                                <a href="designer/view_profile.php?user_id=<?= $job_data['owner_id'] ?>" class="text-sm text-blue-600 hover:underline">ดูโปรไฟล์ทั้งหมด</a>
                            </div>
                        </div>

                        <?php if (!empty($job_data['bio'])): ?>
                            <div class="mb-6">
                                <h4 class="font-bold text-slate-700 mb-2">เกี่ยวกับนักออกแบบ</h4>
                                <p class="text-slate-600 text-sm leading-relaxed line-clamp-3">
                                    <?= htmlspecialchars($job_data['bio']) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($job_data['skills'])): ?>
                            <div class="mb-8">
                                <h4 class="font-bold text-slate-700 mb-3">ทักษะและความสามารถ</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php
                                    $skills = explode(',', $job_data['skills']);
                                    foreach ($skills as $skill):
                                    ?>
                                        <span class="bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-full">
                                            <?= htmlspecialchars(trim($skill)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="flex flex-col gap-3">
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $job_data['owner_id']): ?>
                                <a href="messages.php?to_user=<?= $job_data['owner_id'] ?>" class="w-full text-center btn-primary text-white px-6 py-3 rounded-lg font-semibold text-lg shadow-lg flex items-center justify-center">
                                    <i class="fas fa-comments mr-2"></i> ติดต่อจ้างงาน
                                </a>
                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                <a href="login.php" class="w-full text-center btn-primary text-white px-6 py-3 rounded-lg font-semibold text-lg shadow-lg">
                                    เข้าสู่ระบบเพื่อติดต่อ
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>

            </div>
        <?php endif; ?>
    </main>

    <?php include('includes/footer.php'); ?>

</body>

</html>