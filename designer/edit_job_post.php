<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- ตรวจสอบการล็อกอินและสิทธิ์ ---
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
if ($condb->connect_error) {
    die("Connection Failed: " . $condb->connect_error);
}
$condb->set_charset("utf8mb4");

$designer_id = $_SESSION['user_id'];
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$job_data = null;
$error_message = '';
$success_message = '';
$designer_name = $_SESSION['full_name'] ?? 'Designer';

// --- 1. ดึงข้อมูลโพสต์เดิม ---
if ($post_id > 0) {
    $sql_fetch = "SELECT jp.*, uf.file_path 
                  FROM job_postings jp
                  LEFT JOIN uploaded_files uf ON jp.main_image_id = uf.file_id
                  WHERE jp.post_id = ? AND jp.designer_id = ?";
    $stmt_fetch = $condb->prepare($sql_fetch);
    $stmt_fetch->bind_param("ii", $post_id, $designer_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
        $job_data = $result->fetch_assoc();
    } else {
        $error_message = "ไม่พบโพสต์ที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์";
    }
    $stmt_fetch->close();
} else {
    $error_message = "ID ของโพสต์ไม่ถูกต้อง";
}

// --- 2. ดึงหมวดหมู่งานทั้งหมด ---
$categories = [];
$sql_categories = "SELECT category_id, category_name FROM job_categories ORDER BY category_name";
$result_categories = $condb->query($sql_categories);
if ($result_categories) {
    $categories = $result_categories->fetch_all(MYSQLI_ASSOC);
}

// --- 3. Logic การอัปเดตข้อมูล ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_id'])) {
    
    // รับข้อมูลจากฟอร์ม
    $post_id_update = (int)$_POST['post_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price_range = $_POST['price_range'];
    $category_id = (int)$_POST['category'];

    // --- ตรวจสอบว่ามีการเปลี่ยนแปลงข้อมูลหรือไม่ ---
    $has_changed = false;
    if ($title !== $job_data['title'] ||
        $description !== $job_data['description'] ||
        $price_range !== $job_data['price_range'] ||
        $category_id !== (int)$job_data['category_id'] ||
        (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK)) {
        $has_changed = true;
    }

    if (!$has_changed) {
        header("Location: view_profile.php?user_id=" . $designer_id . "&update_status=nochange");
        exit();
    }

    // ถ้ามีการเปลี่ยนแปลง ให้เริ่มบันทึก
    $condb->begin_transaction();
    try {
        $main_image_id = $job_data['main_image_id'];

        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
            $file_info = $_FILES['main_image'];
            $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
            $new_file_name = uniqid('job_img_') . time() . '.' . $file_ext;
            $upload_dir = '../uploads/job_images/';
            $upload_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_info['tmp_name'], $upload_path)) {
                $sql_insert_file = "INSERT INTO uploaded_files (uploader_id, file_name, file_path, file_size, file_type, uploaded_date) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt_file = $condb->prepare($sql_insert_file);
                $stmt_file->bind_param("issis", $designer_id, $new_file_name, $upload_path, $file_info['size'], $file_info['type']);
                $stmt_file->execute();
                $main_image_id = $condb->insert_id;
                $stmt_file->close();
            }
        }

        $sql_update = "UPDATE job_postings SET title = ?, description = ?, category_id = ?, price_range = ?, main_image_id = ? WHERE post_id = ? AND designer_id = ?";
        $stmt_update = $condb->prepare($sql_update);
        $stmt_update->bind_param("ssisiii", $title, $description, $category_id, $price_range, $main_image_id, $post_id_update, $designer_id);
        if (!$stmt_update->execute()) { throw new Exception("Error updating job post: " . $stmt_update->error); }
        $stmt_update->close();
        
        $condb->commit();
        header("Location: view_profile.php?user_id=" . $designer_id . "&update_status=success");
        exit();

    } catch (Exception $e) {
        $condb->rollback();
        $error_message = $e->getMessage();
    }
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
    <title>แก้ไขโพสต์งาน | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-image: url('../dist/img/cover.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .btn-primary { background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%); color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(13, 150, 210, 0.3); }
        .btn-primary:hover { background: linear-gradient(45deg, #0d96d2 0%, #0a5f97 100%); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(13, 150, 210, 0.5); }
        .btn-danger { background-color: #ef4444; color: white; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
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
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md" role="alert">
                    <p class="font-bold">เกิดข้อผิดพลาด</p>
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php elseif ($job_data): ?>
                <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/75">
                    <div class="p-8 sm:p-12">
                        <div class="text-center">
                            <h1 class="text-3xl md:text-4xl font-bold bg-gradient-to-r from-amber-600 to-orange-400 bg-clip-text text-transparent">แก้ไขโพสต์งาน</h1>
                            <p class="mt-2 text-sm text-slate-500">อัปเดตรายละเอียดบริการของคุณให้เป็นปัจจุบัน</p>
                        </div>

                        <form action="edit_job_post.php?id=<?= $post_id ?>" method="POST" enctype="multipart/form-data" class="mt-10 space-y-6">
                            <input type="hidden" name="post_id" value="<?= $job_data['post_id'] ?>">

                            <div>
                                <label for="title" class="block text-gray-700 text-lg font-semibold mb-2">ชื่องาน/บริการ:</label>
                                <input type="text" id="title" name="title" value="<?= htmlspecialchars($job_data['title']) ?>" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50" required>
                            </div>

                            <div>
                                <label for="description" class="block text-gray-700 text-lg font-semibold mb-2">รายละเอียด:</label>
                                <textarea id="description" name="description" rows="6" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50" required><?= htmlspecialchars($job_data['description']) ?></textarea>
                            </div>

                            <div>
                                <label for="category" class="block text-gray-700 text-lg font-semibold mb-2">หมวดหมู่:</label>
                                <select id="category" name="category" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>" <?= ($job_data['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="price_range" class="block text-gray-700 text-lg font-semibold mb-2">ช่วงราคา (โดยประมาณ):</label>
                                <input type="text" id="price_range" name="price_range" value="<?= htmlspecialchars($job_data['price_range']) ?>" class="block w-full p-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50" required>
                            </div>

                            <div>
                                <label for="main_image" class="block text-gray-700 text-lg font-semibold mb-2">เปลี่ยนภาพประกอบ (ถ้าต้องการ):</label>
                                <div id="imagePreviewContainer" class="mt-2 flex justify-center items-center rounded-xl border-2 border-dashed border-gray-300 px-6 pt-8 pb-10 cursor-pointer hover:border-blue-400">
                                    <?php $image_source = !empty($job_data['file_path']) && file_exists($job_data['file_path']) ? $job_data['file_path'] : ''; ?>
                                    <div class="text-center" id="placeholderContent" <?= !empty($image_source) ? 'class="hidden"' : '' ?>>
                                        <i class="fa-solid fa-image text-4xl text-gray-300"></i>
                                        <p class="mt-4 text-sm text-slate-600">อัปโหลดไฟล์ใหม่ หรือลากมาวาง</p>
                                        <p class="text-xs text-slate-500">PNG, JPG, GIF ขนาดไม่เกิน 5MB</p>
                                    </div>
                                    <img id="imagePreview" src="<?= htmlspecialchars($image_source) ?>" alt="Image Preview" class="<?= empty($image_source) ? 'hidden' : '' ?> max-h-48 rounded-lg">
                                </div>
                                <input type="file" id="main_image" name="main_image" accept="image/*" class="sr-only">
                            </div>

                            <div class="flex justify-end pt-4 space-x-4">
                                <a href="view_profile.php?user_id=<?= $designer_id ?>" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-6 py-3 rounded-lg font-semibold transition-colors">ยกเลิก</a>
                                <button type="submit" class="bg-gradient-to-r from-blue-600 to-cyan-500 text-white px-8 py-3 rounded-lg font-semibold text-lg shadow-lg hover:shadow-xl hover:scale-105 transition-all">
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // === SweetAlert2 Popups ===
            <?php if (!empty($success_message)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: '<?= addslashes($success_message) ?>',
                    showConfirmButton: false,
                    timer: 2500
                }).then(() => {
                    window.location.href = 'main.php';
                });
            <?php elseif (!empty($error_message)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด!',
                    text: '<?= addslashes($error_message) ?>',
                    confirmButtonText: 'รับทราบ'
                });
            <?php endif; ?>

            // === Image Preview & Drop Zone Logic ===
            const fileInput = document.getElementById('main_image');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const placeholderContent = document.getElementById('placeholderContent');

            function handleFiles(files) {
                const file = files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.classList.remove('hidden');
                        placeholderContent.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            }

            fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
            imagePreviewContainer.addEventListener('click', () => fileInput.click());
            imagePreviewContainer.addEventListener('dragover', (e) => {
                e.preventDefault();
                imagePreviewContainer.classList.add('border-blue-400', 'bg-slate-50');
            });
            imagePreviewContainer.addEventListener('dragleave', (e) => {
                e.preventDefault();
                imagePreviewContainer.classList.remove('border-blue-400', 'bg-slate-50');
            });
            imagePreviewContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                imagePreviewContainer.classList.remove('border-blue-400', 'bg-slate-50');
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