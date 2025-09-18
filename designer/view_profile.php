<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$home_link = '../index.php'; // Default URL
if (isset($_SESSION['user_type'])) {
    switch ($_SESSION['user_type']) {
        case 'designer':
            $home_link = 'main.php';
            break;
        case 'client':
            $home_link = '../client/main.php';
            break;
        case 'admin':
            $home_link = '../admin/main.php';
            break;
    }
}

// --- ตรวจสอบสถานะการอัปเดตจาก URL ---
$show_success_popup = false;
$show_no_change_popup = false;
if (isset($_GET['update_status'])) {
    if ($_GET['update_status'] === 'success') {
        $show_success_popup = true;
    } elseif ($_GET['update_status'] === 'nochange') {
        $show_no_change_popup = true;
    }
}
// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}
$condb->set_charset("utf8mb4");

// ดึง user_id จาก URL และ Session
$user_id_to_view = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$current_user_id = $_SESSION['user_id'] ?? 0;
$loggedInUserName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้งาน';
$loggedInUserName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Designer';
$loggedInUserName = ''; // Initialize variable for logged-in user's name

// Fetch logged-in user's name if session is active
if (isset($_SESSION['user_id'])) {
    $loggedInUserName = $_SESSION['username'] ?? $_SESSION['full_name'] ?? '';
    if (empty($loggedInUserName)) {
        $user_id = $_SESSION['user_id'];
        $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
        $stmt_user = $condb->prepare($sql_user);
        if ($stmt_user) {
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            if ($result_user->num_rows === 1) {
                $user_info = $result_user->fetch_assoc();
                $loggedInUserName = $user_info['first_name'] . ' ' . $user_info['last_name'];
            }
            $stmt_user->close();
        }
    }
}
// (โค้ดดึงข้อมูลโปรไฟล์และโพสต์งานเหมือนเดิม)
$profile_data = null;
$job_postings_for_profile = [];
if ($user_id_to_view > 0) {
    // ดึงข้อมูลโปรไฟล์
    $sql_profile = "SELECT p.*, u.first_name, u.last_name, u.email, u.phone_number, u.username
                    FROM profiles p
                    JOIN users u ON p.user_id = u.user_id
                    WHERE p.user_id = ?";
    $stmt_profile = $condb->prepare($sql_profile);
    if ($stmt_profile) {
        $stmt_profile->bind_param("i", $user_id_to_view);
        $stmt_profile->execute();
        $profile_data = $stmt_profile->get_result()->fetch_assoc();
        $stmt_profile->close();
    }
}

if ($user_id_to_view > 0) {
    // ดึงข้อมูลโปรไฟล์
    $sql_profile = "SELECT p.*, u.first_name, u.last_name, u.email, u.phone_number, u.username
                    FROM profiles p
                    JOIN users u ON p.user_id = u.user_id
                    WHERE p.user_id = ?";
    $stmt_profile = $condb->prepare($sql_profile);
    if ($stmt_profile) {
        $stmt_profile->bind_param("i", $user_id_to_view);
        $stmt_profile->execute();
        $profile_data = $stmt_profile->get_result()->fetch_assoc();
        $stmt_profile->close();
    }

    // ดึงโพสต์งานพร้อม Path รูปภาพ
    $sql_job_postings_for_profile = "SELECT
                                        jp.post_id, jp.title, jp.description, jp.price_range, jp.posted_date,
                                        u.first_name, u.last_name, jc.category_name,
                                        uf.file_path AS job_image_path
                                    FROM job_postings AS jp
                                    JOIN users AS u ON jp.designer_id = u.user_id 
                                    LEFT JOIN job_categories AS jc ON jp.category_id = jc.category_id
                                    LEFT JOIN uploaded_files AS uf ON jp.main_image_id = uf.file_id
                                    WHERE jp.designer_id = ? AND jp.status = 'active'
                                    ORDER BY jp.posted_date DESC";

    $stmt_job_postings = $condb->prepare($sql_job_postings_for_profile);
    if ($stmt_job_postings) {
        $stmt_job_postings->bind_param("i", $user_id_to_view);
        $stmt_job_postings->execute();
        $job_postings_for_profile = $stmt_job_postings->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_job_postings->close();
    }
}

$condb->close();

// กำหนดค่าเริ่มต้นสำหรับแสดงผล
$display_name = trim(($profile_data['first_name'] ?? '') . ' ' . ($profile_data['last_name'] ?? '')) ?: ($profile_data['username'] ?? 'ไม่ระบุชื่อ');
$display_email = $profile_data['email'] ?? 'ไม่ระบุอีเมล';
$display_tel = $profile_data['phone_number'] ?? 'ไม่ระบุเบอร์โทรศัพท์';
$display_company_name = $profile_data['company_name'] ?? 'ไม่ระบุบริษัท';
$display_bio = $profile_data['bio'] ?? 'ยังไม่มีประวัติ';
$display_skills = !empty($profile_data['skills']) ? explode(',', $profile_data['skills']) : [];
$display_profile_pic = !empty($profile_data['profile_picture_url']) && file_exists(ltrim($profile_data['profile_picture_url'], '/')) ? $profile_data['profile_picture_url'] : '../dist/img/user8.jpg';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของ <?= htmlspecialchars($display_name) ?> | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Kanit', sans-serif;
        }

        .text-gradient {
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .carousel-content {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: calc(100% - 1rem);
            gap: 1.5rem;
            overflow-x: scroll;
            scroll-behavior: smooth;
            scrollbar-width: none;
        }

        .carousel-content::-webkit-scrollbar {
            display: none;
        }

        @media (min-width: 768px) {
            .carousel-content {
                grid-auto-columns: calc(50% - 0.75rem);
            }
        }

        @media (min-width: 1024px) {
            .carousel-content {
                grid-auto-columns: calc(33.333% - 1rem);
            }
        }

        .card-item {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            flex-shrink: 0;
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

        .carousel-button {
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 0.75rem 0.5rem;
            cursor: pointer;
            z-index: 10;
            border-radius: 9999px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .carousel-button.left {
            left: -1rem;
        }

        .carousel-button.right {
            right: -1rem;
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

        .btn-danger {
            background-color: #ef4444;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
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
    </style>
</head>

<body class="bg-slate-100 min-h-screen flex flex-col">

    <nav class="bg-white/90 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="<?= htmlspecialchars($home_link) ?>"><img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105"></a>
            <div class="space-x-4 flex items-center">
                <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                <a href="view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
                <a href="../logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto px-4 py-8">
        <?php if (!$profile_data): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-lg text-center">ไม่พบข้อมูลโปรไฟล์</div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-10">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6 md:gap-10 mb-8">
                    <img src="<?= htmlspecialchars($display_profile_pic) ?>" alt="รูปโปรไฟล์" class="w-32 h-32 md:w-40 md:h-40 rounded-full object-cover shadow-lg border-4 border-white">
                    <div class="text-center md:text-left flex-grow">
                        <h1 class="text-3xl sm:text-4xl font-bold text-gray-900"><?= htmlspecialchars($display_name) ?></h1>
                        <p class="text-md text-gray-600 mt-2"><i class="fas fa-envelope mr-2"></i><?= htmlspecialchars($display_email) ?></p>
                        <p class="text-md text-gray-600"><i class="fas fa-phone mr-2"></i><?= htmlspecialchars($display_tel) ?></p>
                        <p class="text-md text-gray-600"><i class="fas fa-building mr-2"></i><?= htmlspecialchars($display_company_name) ?></p>

                        <?php if ($user_id_to_view == $current_user_id): ?>
                            <div class="mt-4">
                                <a href="edit_profile.php" class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-6 py-2 rounded-lg font-medium text-sm shadow-md transition-colors">
                                    <i class="fas fa-pencil-alt mr-2"></i>แก้ไขโปรไฟล์
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-semibold text-gradient mb-4">เกี่ยวกับฉัน</h2>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($display_bio)) ?></p>
                </div>

                <?php if (!empty($display_skills)): ?>
                    <div class="mb-8">
                        <h2 class="text-2xl font-semibold text-gradient mb-4">ทักษะ</h2>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($display_skills as $skill): ?>
                                <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full"><?= htmlspecialchars(trim($skill)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-8">
                    <h2 class="text-2xl font-semibold text-gradient mb-4">โพสต์ประกาศงานของคุณ</h2>
                    <?php if (empty($job_postings_for_profile)): ?>
                        <div class="bg-blue-100 text-blue-700 px-4 py-3 rounded-lg text-center">ยังไม่มีงานที่ประกาศ</div>
                    <?php else: ?>
                        <div class="carousel-container relative">
                            <button id="prevBtn" class="carousel-button left"><i class="fas fa-chevron-left"></i></button>
                            <div class="carousel-content p-2" id="carouselContent">
                                <?php foreach ($job_postings_for_profile as $job): ?>
                                    <div class="card-item flex flex-col">
                                        <?php
                                        $image_source = !empty($job['job_image_path']) && file_exists(htmlspecialchars($job['job_image_path']))
                                            ? htmlspecialchars($job['job_image_path'])
                                            : '../dist/img/pdpa02.jpg';
                                        ?>
                                        <img src="<?= $image_source ?>" alt="ภาพประกอบงาน: <?= htmlspecialchars($job['title']) ?>" class="card-image">
                                        <div class="p-4 md:p-6 flex-grow flex flex-col justify-between">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900 line-clamp-2"><?= htmlspecialchars($job['title']) ?></h3>
                                                <p class="text-sm text-gray-500 mb-2">หมวดหมู่: <?= htmlspecialchars($job['category_name'] ?? 'ไม่ระบุ') ?></p>
                                                <p class="text-sm text-gray-700 line-clamp-3 font-light"><?= htmlspecialchars($job['description']) ?></p>
                                            </div>
                                            <div class="mt-4">
                                                <p class="text-lg font-semibold text-green-700">ราคา: <?= htmlspecialchars($job['price_range'] ?? 'สอบถาม') ?></p>
                                                <p class="text-xs text-gray-500">ประกาศเมื่อ: <?= date('d M Y', strtotime($job['posted_date'])) ?></p>

                                                <div class="mt-2 flex space-x-2">
                                                    <a href="../job_detail.php?id=<?= $job['post_id'] ?>&type=posting" class="flex-1 btn-primary text-white px-4 py-2 rounded-lg font-medium text-sm shadow-lg text-center">ดูรายละเอียด</a>

                                                    <?php if ($user_id_to_view == $current_user_id): ?>
                                                        <a href="edit_job_post.php?id=<?= $job['post_id'] ?>" class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-lg font-medium text-sm shadow-lg transition-colors"><i class="fas fa-pencil-alt"></i></a>

                                                        <a href="delete_job_post.php?id=<?= $job['post_id'] ?>" onclick="confirmDelete(event, <?= $job['post_id'] ?>)" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg font-medium text-sm shadow-lg transition-colors"><i class="fas fa-trash"></i></a>

                                                    <?php endif; ?>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button id="nextBtn" class="carousel-button right"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmDelete(event, postId) {
            event.preventDefault();

            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "คุณจะไม่สามารถกู้คืนโพสต์นี้ได้!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'delete_job_post.php?id=' + postId;
                }
            })
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($show_success_popup): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: 'อัปเดตโปรไฟล์ของคุณเรียบร้อยแล้ว',
                    timer: 2500,
                    showConfirmButton: false
                });
            <?php elseif ($show_no_change_popup): ?>
                Swal.fire({
                    icon: 'info',
                    title: 'ไม่มีการเปลี่ยนแปลง',
                    text: 'ข้อมูลของคุณเป็นปัจจุบันอยู่แล้ว',
                    timer: 2500,
                    showConfirmButton: false
                });
            <?php endif; ?>
        });
        document.addEventListener('DOMContentLoaded', function() {
            const showSuccessPopup = localStorage.getItem('showSuccessPopup') === 'true';
            if (showSuccessPopup) {
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: 'อัปเดตโปรไฟล์ของคุณเรียบร้อยแล้ว',
                    timer: 2500,
                    showConfirmButton: false
                });
            }
        });
        <?php if ($show_success_popup): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: 'อัปเดตโปรไฟล์ของคุณเรียบร้อยแล้ว',
                timer: 2500,
                showConfirmButton: false
            });
        <?php endif; ?>
        document.addEventListener('DOMContentLoaded', function() {
            const carouselContent = document.getElementById('carouselContent');
            if (!carouselContent) return;

            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');

            function updateButtons() {
                const {
                    scrollWidth,
                    clientWidth,
                    scrollLeft
                } = carouselContent;
                if (scrollWidth <= clientWidth) {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                    return;
                }
                prevBtn.style.display = scrollLeft > 0 ? 'flex' : 'none';
                nextBtn.style.display = scrollLeft < (scrollWidth - clientWidth - 1) ? 'flex' : 'none';
            }

            prevBtn.addEventListener('click', () => {
                const cardWidth = carouselContent.querySelector('.card-item').offsetWidth;
                carouselContent.scrollBy({
                    left: -(cardWidth + 24),
                    behavior: 'smooth'
                });
            });

            nextBtn.addEventListener('click', () => {
                const cardWidth = carouselContent.querySelector('.card-item').offsetWidth;
                carouselContent.scrollBy({
                    left: cardWidth + 24,
                    behavior: 'smooth'
                });
            });

            carouselContent.addEventListener('scroll', () => setTimeout(updateButtons, 250));
            window.addEventListener('resize', updateButtons);
            updateButtons();
        });
    </script>
</body>

</html>