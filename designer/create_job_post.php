<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- ตั้งค่าการแสดงผล Error สำหรับ Development (ลบออกเมื่อขึ้น Production) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    header("Location: ../login.php");
    exit();
}

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล (ใช้ mysqli) ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink"; // <--- ตรวจสอบว่าชื่อฐานข้อมูลถูกต้อง

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    error_log("Connection failed: " . $condb->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง");
}
$condb->set_charset("utf8mb4");

// ดึงข้อมูลผู้ใช้ปัจจุบัน (Designer)
$designer_id = $_SESSION['user_id'];
$designer_name = $_SESSION['full_name'] ?? 'Designer'; // ใช้ชื่อเต็มจาก Session

$success_message = '';
$error_message = '';
$categories = [];

// ดึงหมวดหมู่งานจากฐานข้อมูล
$sql_categories = "SELECT category_id, category_name FROM job_categories ORDER BY category_name";
$result_categories = $condb->query($sql_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    error_log("SQL Error fetching categories: " . $condb->error);
}

// ================================================================= //
// ======== LOGIC ที่แก้ไขให้ตรงกับ DB ของคุณ ======== //
// ================================================================= //
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $condb->begin_transaction();
    $main_image_id = NULL; // ID ของไฟล์ที่อัปโหลด (ค่าเริ่มต้น)
    $upload_full_path = null; // Path เต็มของไฟล์ที่อัปโหลดสำหรับใช้ลบกรณีเกิด error

    try {
        // รับข้อมูลจากฟอร์ม
        $title = $condb->real_escape_string($_POST['title']);
        $description = $condb->real_escape_string($_POST['description']);
        $price_range = $condb->real_escape_string($_POST['price_range']);
        $category_id = (int)$_POST['category'];
        $status = 'active';

        if (empty($title) || empty($description) || empty($price_range) || empty($category_id)) {
            throw new Exception('กรุณากรอกข้อมูลหลักให้ครบถ้วน');
        }

        // --- Step 1: จัดการการอัปโหลดไฟล์ (ถ้ามี) ---
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
            $file_info = $_FILES['main_image'];
            $file_name_original = $file_info['name'];
            $file_tmp_name = $file_info['tmp_name'];
            $file_size = $file_info['size'];
            $file_type = $file_info['type'];
            $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
            
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_ext, $allowed_ext)) {
                throw new Exception('อนุญาตเฉพาะไฟล์รูปภาพ (JPG, JPEG, PNG, GIF) เท่านั้น');
            }

            // --- สร้างชื่อและ Path ---
            $new_file_name = uniqid('job_img_') . time() . '.' . $file_ext;
            $upload_dir = '../uploads/job_images/';
            $upload_full_path = $upload_dir . $new_file_name;
            $file_path_for_db = '../uploads/job_images/' . $new_file_name; // Path ที่จะเก็บใน DB

            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            // --- ย้ายไฟล์ ---
            if (move_uploaded_file($file_tmp_name, $upload_full_path)) {
                
                // --- Step 2: บันทึกข้อมูลไฟล์ลงในตาราง `uploaded_files` ---
                $sql_insert_file = "INSERT INTO uploaded_files (uploader_id, file_name, file_path, file_size, file_type, uploaded_date) 
                                    VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt_file = $condb->prepare($sql_insert_file);
                $stmt_file->bind_param("issis", $designer_id, $new_file_name, $file_path_for_db, $file_size, $file_type);

                if (!$stmt_file->execute()) {
                    throw new Exception('ไม่สามารถบันทึกข้อมูลไฟล์ลงฐานข้อมูลได้: ' . $stmt_file->error);
                }
                
                // --- Step 3: ดึง ID ของไฟล์ที่เพิ่งบันทึกไป (file_id) ---
                $main_image_id = $condb->insert_id;
                $stmt_file->close();

            } else {
                throw new Exception('ไม่สามารถอัปโหลดไฟล์รูปภาพได้');
            }
        }

        // --- Step 4: บันทึกข้อมูลโพสต์ลงในตาราง `job_postings` พร้อมกับ `main_image_id` ---
        // สังเกตว่าใน VALUES ตัวสุดท้ายคือ `main_image_id` ที่เราได้มา
        $sql_insert_post = "INSERT INTO job_postings (designer_id, title, description, category_id, price_range, posted_date, status, main_image_id) 
                            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
        
        $stmt_post = $condb->prepare($sql_insert_post);
        // bind_param: i, s, s, i, s, s, i
        $stmt_post->bind_param("ississi", $designer_id, $title, $description, $category_id, $price_range, $status, $main_image_id);

        if (!$stmt_post->execute()) {
            throw new Exception("เกิดข้อผิดพลาดในการบันทึกประกาศงาน: " . $stmt_post->error);
        }
        $stmt_post->close();
        
        $condb->commit();
        $success_message = 'โพสต์ประกาศงานของคุณสำเร็จแล้ว!';

    } catch (Exception $e) {
        $condb->rollback();
        $error_message = $e->getMessage();
        error_log("Job Post Creation Failed: " . $e->getMessage());
        if ($upload_full_path && file_exists($upload_full_path)) {
            unlink($upload_full_path); // ถ้าเกิดข้อผิดพลาด ให้ลบไฟล์ที่อัปโหลดทิ้ง
        }
    }
}

$condb->close();
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
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างโพสต์งานของคุณ | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'kanit': ['Kanit', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-image: url('../dist/img/cover.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .btn-primary {
            background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 150, 210, 0.4);
        }
        .btn-danger {
            background-color: #ef4444; /* สีแดง Tailwind red-500 */
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover {
            background-color: #dc2626; /* สีแดงเข้มขึ้น Tailwind red-600 */
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
        }
        main {
            flex-grow: 1;
        }

        .navbar-original {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

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
            <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-slate-200/75">
                <div class="p-8 sm:p-12">
                    <div class="text-center">
                        <h1 class="text-3xl md:text-4xl font-bold bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text text-transparent">สร้างโพสต์งานของคุณ</h1>
                        <p class="mt-2 text-sm text-slate-500">ประกาศบริการและผลงานของคุณ เพื่อให้ผู้ว่าจ้างที่ใช่ติดต่อคุณได้ง่ายขึ้น</p>
                    </div>

                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="mt-10 space-y-6">

                        <div>
                            <label for="title" class="block text-gray-700 text-lg font-semibold mb-2">ชื่องาน/บริการ:</label>
                            <input type="text" id="title" name="title" placeholder="เช่น ออกแบบโลโก้, รับวาดภาพประกอบ"
                                   class="block w-full p-3 rounded-lg border-gray-400 shadow-md transition duration-150 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50"
                                   required>
                        </div>

                        <div>
                            <label for="description" class="block text-gray-700 text-lg font-semibold mb-2">รายละเอียด:</label>
                            <textarea id="description" name="description" rows="6"
                                      placeholder="อธิบายเกี่ยวกับบริการของคุณให้ชัดเจนและน่าสนใจ"
                                      class="block w-full p-3 rounded-lg border-gray-400 shadow-md transition duration-150 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50"
                                      required></textarea>
                        </div>

                        <div>
                            <label for="category" class="block text-gray-700 text-lg font-semibold mb-2">หมวดหมู่:</label>
                            <select id="category" name="category"
                                    class="block w-full p-3 rounded-lg border-gray-400 shadow-md transition duration-150 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50"
                                    required>
                                <option value="">-- เลือกหมวดหมู่ --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['category_id']) ?>">
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="price_range" class="block text-gray-700 text-lg font-semibold mb-2">ช่วงราคา (โดยประมาณ):</label>
                            <input type="text" id="price_range" name="price_range"
                                   placeholder="เช่น 1,500 - 3,000 บาท, เริ่มต้น 500 บาท"
                                   class="block w-full p-3 rounded-lg border-gray-400 shadow-md transition duration-150 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50"
                                   required>
                        </div>

                        <div>
                             <label for="main_image" class="block text-gray-700 text-lg font-semibold mb-2">แนบภาพประกอบ (หลัก):</label>
                             <div id="imagePreviewContainer" class="mt-2 flex justify-center items-center rounded-xl border-2 border-dashed border-gray-300 px-6 pt-8 pb-10 cursor-pointer hover:border-blue-400 transition-colors duration-200 min-h-[200px]">
                                 <div class="text-center" id="placeholderContent">
                                     <i class="fa-solid fa-image text-4xl text-gray-300"></i>
                                     <div class="mt-4 flex text-sm leading-6 text-slate-600 justify-center">
                                         <p class="relative font-semibold text-blue-600">
                                             <span>อัปโหลดไฟล์</span>
                                         </p>
                                         <p class="pl-1">หรือลากมาวาง</p>
                                     </div>
                                     <p class="text-xs leading-5 text-slate-500">PNG, JPG, GIF ขนาดไม่เกิน 5MB</p>
                                 </div>
                                 <img id="imagePreview" src="#" alt="Image Preview" class="hidden max-h-48 rounded-lg">
                             </div>
                             <input type="file" id="main_image" name="main_image" accept="image/*" class="sr-only">
                        </div>

                        <div class="flex justify-center pt-4">
                             <button type="submit" class="bg-gradient-to-r from-blue-600 to-cyan-500 text-white px-8 py-3 rounded-lg font-semibold text-xl shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-blue-300">
                                 <i class="fas fa-paper-plane mr-2"></i> โพสต์ประกาศงาน
                             </button>
                        </div>
                    </form>
                </div>
            </div>
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
        document.addEventListener('DOMContentLoaded', function () {
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
            imagePreviewContainer.addEventListener('dragover', (e) => { e.preventDefault(); imagePreviewContainer.classList.add('border-blue-400', 'bg-slate-50'); });
            imagePreviewContainer.addEventListener('dragleave', (e) => { e.preventDefault(); imagePreviewContainer.classList.remove('border-blue-400', 'bg-slate-50'); });
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