<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- ตรวจสอบการล็อกอิน ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    header("Location: ../login.php");
    exit();
}

// --- การเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";
$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) { die("Connection Failed: " . $condb->connect_error); }
$condb->set_charset("utf8mb4");

$user_id = $_SESSION['user_id'];
$error_message = '';
$designer_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Designer';

// --- ดึงข้อมูลโปรไฟล์ปัจจุบัน ---
$sql_fetch = "SELECT u.first_name, u.last_name, u.email, u.phone_number, p.company_name, p.bio, p.skills, p.profile_picture_url 
              FROM users u
              LEFT JOIN profiles p ON u.user_id = p.user_id
              WHERE u.user_id = ?";
$stmt_fetch = $condb->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $user_id);
$stmt_fetch->execute();
$profile_data = $stmt_fetch->get_result()->fetch_assoc();
$stmt_fetch->close();

if (!$profile_data) {
    $error_message = "ไม่พบข้อมูลโปรไฟล์ของคุณ";
}


// --- Logic การอัปเดตข้อมูล ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // รับข้อมูลจากฟอร์ม
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $company_name = $_POST['company_name'];
    $bio = $_POST['bio'];
    $skills = $_POST['skills'];

    // --- ตรวจสอบว่ามีการเปลี่ยนแปลงข้อมูลหรือไม่ ---
    $has_changed = false;
    if ($first_name !== $profile_data['first_name'] ||
        $last_name !== $profile_data['last_name'] ||
        $email !== $profile_data['email'] ||
        $phone_number !== $profile_data['phone_number'] ||
        $company_name !== $profile_data['company_name'] ||
        $bio !== $profile_data['bio'] ||
        $skills !== $profile_data['skills'] ||
        (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK)) {
        $has_changed = true;
    }

    if (!$has_changed) {
        // ถ้าไม่มีการเปลี่ยนแปลง ให้ redirect กลับพร้อมสถานะ nochange
        header("Location: view_profile.php?user_id=" . $user_id . "&update_status=nochange");
        exit();
    }

    // ถ้ามีการเปลี่ยนแปลง ให้เริ่มกระบวนการบันทึก
    $condb->begin_transaction();
    try {
        // อัปเดตตาราง users
        $sql_update_user = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ? WHERE user_id = ?";
        $stmt_user = $condb->prepare($sql_update_user);
        $stmt_user->bind_param("ssssi", $first_name, $last_name, $email, $phone_number, $user_id);
        if (!$stmt_user->execute()) { throw new Exception("Error updating users table: " . $stmt_user->error); }
        $stmt_user->close();
        
        // จัดการการอัปโหลดรูปโปรไฟล์ (ถ้ามี)
        $upload_path = $profile_data['profile_picture_url']; //ใช้ path เดิมเป็นค่าเริ่มต้น
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $file_info = $_FILES['profile_picture'];
            $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
            $new_file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_dir = '../uploads/profile_pictures/';
            
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            $upload_path = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file_info['tmp_name'], $upload_path)) {
                throw new Exception("ไม่สามารถอัปโหลดรูปโปรไฟล์ได้");
            }
        }
        
        // อัปเดตข้อมูลตาราง profiles
        $sql_update_profile = "UPDATE profiles SET company_name = ?, bio = ?, skills = ?, profile_picture_url = ? WHERE user_id = ?";
        $stmt_profile = $condb->prepare($sql_update_profile);
        $stmt_profile->bind_param("ssssi", $company_name, $bio, $skills, $upload_path, $user_id);
        if (!$stmt_profile->execute()) { throw new Exception("Error updating profiles table: " . $stmt_profile->error); }
        $stmt_profile->close();
        
        $condb->commit();
        
        $_SESSION['full_name'] = trim($first_name . ' ' . $last_name);

        header("Location: view_profile.php?user_id=" . $user_id . "&update_status=success");
        exit();

    } catch (Exception $e) {
        $condb->rollback();
        $error_message = $e->getMessage();
    }
}
// --- ดึงข้อมูลโปรไฟล์ปัจจุบันมาแสดงในฟอร์ม ---
$sql_fetch = "SELECT u.first_name, u.last_name, u.email, u.phone_number, p.company_name, p.bio, p.skills 
              FROM users u
              LEFT JOIN profiles p ON u.user_id = p.user_id
              WHERE u.user_id = ?";
$stmt_fetch = $condb->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $user_id);
$stmt_fetch->execute();
$profile_data = $stmt_fetch->get_result()->fetch_assoc();
$stmt_fetch->close();

if (!$profile_data) {
    $error_message = "ไม่พบข้อมูลโปรไฟล์ของคุณ";
}
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
$condb->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขโปรไฟล์ | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background-image: url('../dist/img/cover.png'); background-size: cover; background-position: center; background-attachment: fixed; }
        .btn-primary { background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%); color: white; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(13, 150, 210, 0.4); }
        .btn-danger { background-color: #ef4444; color: white; transition: all 0.3s ease; }
        .btn-danger:hover { background-color: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4); }
        .pixellink-logo-footer {
            font-weight: 700;
            font-size: 2.25rem;
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="text-slate-800 antialiased">

    <nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="main.php">
                <img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
            </a>
            <div class="space-x-4 flex items-center">
                <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                <a href="view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
                
                <a href="../logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
            </div>
        </div>
    </nav>
    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="mx-auto max-w-2xl">
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md mb-6" role="alert">
                    <p class="font-bold">เกิดข้อผิดพลาด</p>
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($profile_data): ?>
                <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/75">
                    <div class="p-8 sm:p-12">
                        <div class="text-center">
                            <h1 class="text-3xl md:text-4xl font-bold bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text text-transparent">แก้ไขโปรไฟล์ของคุณ</h1>
                            <p class="mt-2 text-sm text-slate-500">อัปเดตข้อมูลของคุณให้เป็นปัจจุบันอยู่เสมอ</p>
                        </div>

                        <form action="edit_profile.php" method="POST" enctype="multipart/form-data" class="mt-10 space-y-6">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="first_name" class="block text-gray-700 text-lg font-semibold mb-2">ชื่อจริง:</label>
                                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($profile_data['first_name']) ?>" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50" required>
                                </div>
                                <div>
                                    <label for="last_name" class="block text-gray-700 text-lg font-semibold mb-2">นามสกุล:</label>
                                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($profile_data['last_name']) ?>" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50" required>
                                </div>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-gray-700 text-lg font-semibold mb-2">อีเมล:</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile_data['email']) ?>" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50" required>
                            </div>
                            
                            <div>
                                <label for="phone_number" class="block text-gray-700 text-lg font-semibold mb-2">เบอร์โทรศัพท์:</label>
                                <input type="text" id="phone_number" name="phone_number" value="<?= htmlspecialchars($profile_data['phone_number']) ?>" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50">
                            </div>

                            <div>
                                <label for="company_name" class="block text-gray-700 text-lg font-semibold mb-2">บริษัท/สตูดิโอ (ถ้ามี):</label>
                                <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($profile_data['company_name']) ?>" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50">
                            </div>
                            
                            <div>
                                <label for="bio" class="block text-gray-700 text-lg font-semibold mb-2">เกี่ยวกับฉัน:</label>
                                <textarea id="bio" name="bio" rows="5" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50"><?= htmlspecialchars($profile_data['bio']) ?></textarea>
                            </div>

                            <div>
                                <label for="skills" class="block text-gray-700 text-lg font-semibold mb-2">ทักษะ (คั่นด้วย ,):</label>
                                <input type="text" id="skills" name="skills" value="<?= htmlspecialchars($profile_data['skills']) ?>" placeholder="เช่น Photoshop, Illustrator, UI/UX" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50">
                            </div>
                            
                            <div>
                                 <label for="profile_picture" class="block text-gray-700 text-lg font-semibold mb-2">เปลี่ยนรูปโปรไฟล์ (ถ้าต้องการ):</label>
                                 <div id="imagePreviewContainer" class="mt-2 flex justify-center items-center rounded-xl border-2 border-dashed border-gray-300 px-6 pt-8 pb-10 cursor-pointer hover:border-blue-400">
                                     <div class="text-center" id="placeholderContent">
                                         <i class="fa-solid fa-image text-4xl text-gray-300"></i>
                                         <p class="mt-4 text-sm text-slate-600">อัปโหลดไฟล์ใหม่ หรือลากมาวาง</p>
                                         <p class="text-xs text-slate-500">PNG, JPG, GIF ขนาดไม่เกิน 5MB</p>
                                     </div>
                                     <img id="imagePreview" src="#" alt="Image Preview" class="hidden max-h-48 rounded-lg">
                                 </div>
                                 <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="sr-only">
                            </div>

                            <div class="flex justify-end pt-4 space-x-4">
                                <a href="view_profile.php?user_id=<?= $user_id ?>" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-8 py-3 rounded-lg font-semibold transition-colors">ยกเลิก</a>
                                <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold text-lg shadow-lg hover:shadow-xl hover:scale-105 transition-all">
                                    <i class="fas fa-save mr-2"></i> บันทึกการเปลี่ยนแปลง
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
<footer class="bg-gray-900 text-gray-300 py-8 mt-auto">
        <div class="container mx-auto px-4 md:px-6 text-center">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <a href="main.php" class="pixellink-logo-footer mb-4 md:mb-0 transition duration-300 hover:opacity-80">Pixel<b>Link</b></a>
                <div class="flex flex-wrap justify-center space-x-2 md:space-x-6 text-sm md:text-base">
                    <a href="#" class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">เกี่ยวกับเรา</a>
                    <a href="#" class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">ติดต่อเรา</a>
                    <a href="#" class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">เงื่อนไขการใช้งาน</a>
                    <a href="#" class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">นโยบายความเป็นส่วนตัว</a>
                </div>
            </div>
            <hr class="border-gray-700 my-6">
            <p class="text-xs md:text-sm font-light">&copy; <?php echo date('Y'); ?> PixelLink. All rights reserved.</p>
        </div>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fileInput = document.getElementById('profile_picture');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const placeholderContent = document.getElementById('placeholderContent');

            function handleFiles(files) {
                const file = files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        imagePreview.src = e.target.result;
                        imagePreview.classList.remove('hidden');
                        placeholderContent.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            }

            fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
            imagePreviewContainer.addEventListener('click', () => fileInput.click());
            imagePreviewContainer.addEventListener('dragover', (e) => { e.preventDefault(); imagePreviewContainer.classList.add('border-blue-400'); });
            imagePreviewContainer.addEventListener('dragleave', () => { imagePreviewContainer.classList.remove('border-blue-400'); });
            imagePreviewContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                imagePreviewContainer.classList.remove('border-blue-400');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFiles(files);
                }
            });
        });
    </script>
</body>
</html>