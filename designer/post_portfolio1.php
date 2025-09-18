<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ และเป็น 'designer'
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    // ถ้าไม่ได้ล็อกอินหรือไม่ใช่ designer ให้เปลี่ยนเส้นทางไปหน้า login
    header("Location: ../login.php");
    exit();
}

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล (ใช้ mysqli) ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink"; // <--- เปลี่ยนเป็นชื่อฐานข้อมูล 'pixellink'

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    error_log("Connection failed: " . $condb->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง");
}
$condb->set_charset("utf8mb4");

// ดึงข้อมูลผู้ใช้ปัจจุบัน (Designer)
$designer_id = $_SESSION['user_id'];
$designer_name = $_SESSION['username'] ?? $_SESSION['full_name']; // ใช้ full_name ถ้ามี, ไม่งั้นใช้ username

$message = '';
$message_type = ''; // 'success' or 'error'

// ดึงค่า portfolio_url ปัจจุบันจากตาราง profiles เพื่อแสดงในฟอร์ม
$current_portfolio_url = '';
$sql_get_profile = "SELECT portfolio_url FROM profiles WHERE user_id = ?";
$stmt_get_profile = $condb->prepare($sql_get_profile);
if ($stmt_get_profile) {
    $stmt_get_profile->bind_param("i", $designer_id);
    $stmt_get_profile->execute();
    $result_get_profile = $stmt_get_profile->get_result();
    if ($row_profile = $result_get_profile->fetch_assoc()) {
        $current_portfolio_url = htmlspecialchars($row_profile['portfolio_url']);
    }
    $stmt_get_profile->close();
} else {
    error_log("Error preparing get profile statement: " . $condb->error);
}


// --- Logic สำหรับจัดการการส่งฟอร์ม Portfolio (อัปเดต portfolio_url ในตาราง profiles) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_portfolio'])) {
    $project_url = $condb->real_escape_string($_POST['project_url']); // ตอนนี้คือลิงก์พอร์ตโฟลิโอหลัก

    // อัปเดต portfolio_url ในตาราง profiles
    $sql_update_profile = "UPDATE profiles SET portfolio_url = ? WHERE user_id = ?";
    $stmt_update_profile = $condb->prepare($sql_update_profile);

    if ($stmt_update_profile) {
        $stmt_update_profile->bind_param("si", $project_url, $designer_id);
        if ($stmt_update_profile->execute()) {
            $message = "บันทึกลิงก์ผลงานสำเร็จแล้ว!";
            $message_type = "success";
            $current_portfolio_url = htmlspecialchars($project_url); // อัปเดตค่าที่แสดง
        } else {
            $message = "เกิดข้อผิดพลาดในการบันทึกลิงก์ผลงาน: " . $stmt_update_profile->error;
            $message_type = "error";
            error_log("Portfolio URL Update Error: " . $stmt_update_profile->error);
        }
        $stmt_update_profile->close();
    } else {
        $message = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $condb->error;
        $message_type = "error";
        error_log("Portfolio URL Prepare Error: " . $condb->error);
    }
}

$condb->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการลิงก์ผลงาน | PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

    <style>
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

        .text-gradient {
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pixellink-logo {
            font-weight: 700;
            font-size: 2.25rem;
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pixellink-logo b {
            color: #0d96d2;
        }

        /* Form specific styles */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            color: #374151;
            background-color: #f9fafb;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            border-color: #0d96d2;
            box-shadow: 0 0 0 3px rgba(13, 150, 210, 0.2);
            outline: none;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar .px-5 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .navbar .py-2 {
                padding-top: 0.3rem;
                padding-bottom: 0.3rem;
            }

            .pixellink-logo {
                font-size: 1.6rem;
            }

            h1 {
                font-size: 2.5rem;
            }

            h2 {
                font-size: 1.8rem;
            }

            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .px-6 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .py-12 {
                padding-top: 2rem;
                padding-bottom: 2rem;
            }
        }

        @media (max-width: 480px) {
            .pixellink-logo {
                font-size: 1.4rem;
            }

            h1 {
                font-size: 2rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .form-input,
            .form-textarea,
            .form-select {
                padding: 0.6rem 0.8rem;
                font-size: 0.9rem;
            }

            .btn-primary,
            .btn-secondary {
                padding: 0.75rem 1.25rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">

    <nav class="navbar p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="main.php" class="transition duration-300 hover:opacity-80">
                <img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12">
            </a>
            <div class="space-x-2 sm:space-x-4 flex items-center">
                <span class="text-gray-700 font-medium">สวัสดี, <?= htmlspecialchars($designer_name) ?>!</span>
                <a href="../logout.php" class="
                    bg-red-500 text-white
                    px-3 py-1.5 sm:px-5 sm:py-2
                    rounded-lg font-medium
                    shadow-md hover:shadow-lg hover:scale-105 transition-all duration-300
                    focus:outline-none focus:ring-2 focus:ring-red-300
                ">
                    <i class="fas fa-sign-out-alt mr-1"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </nav>

    <main class="flex-grow py-12 md:py-16">
        <div class="container mx-auto px-4 md:px-6">
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extralight text-center mb-8 md:mb-12 text-gradient">
                จัดการลิงก์ผลงานของคุณ
            </h1>

            <?php if (!empty($message)) : ?>
                <div class="mb-6 p-4 rounded-lg text-center
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="form-container max-w-3xl mx-auto p-6 md:p-8 lg:p-10">
                <form action="post_portfolio.php" method="POST">
                    <div class="mb-6">
                        <label for="project_url" class="form-label">ลิงก์พอร์ตโฟลิโอหลัก (เช่น Behance, Dribbble, เว็บไซต์ส่วนตัว)</label>
                        <input type="url" id="project_url" name="project_url" class="form-input"
                            placeholder="เช่น https://www.behance.net/yourusername"
                            value="<?= $current_portfolio_url ?>">
                        <p class="text-sm text-gray-500 mt-2">โปรดระบุลิงก์ไปยังหน้าพอร์ตโฟลิโอออนไลน์ของคุณ เพื่อให้ผู้ว่าจ้างสามารถดูผลงานทั้งหมดได้</p>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="main.php" class="btn-secondary px-6 py-3 rounded-lg font-medium">
                            ยกเลิก
                        </a>
                        <button type="submit" name="submit_portfolio" class="btn-primary px-6 py-3 rounded-lg font-medium">
                            <i class="fas fa-save mr-2"></i> บันทึกลิงก์ผลงาน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer class="bg-gray-900 text-gray-300 py-8 mt-auto">
        <div class="container mx-auto px-4 md:px-6 text-center">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <a href="main.php"
                    class="text-2xl sm:text-3xl font-bold pixellink-logo mb-4 md:mb-0 transition duration-300 hover:opacity-80">Pixel<b>Link</b></a>
                <div class="flex flex-wrap justify-center space-x-2 md:space-x-6 text-sm md:text-base footer-links">
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">เกี่ยวกับเรา</a>
                    <a href="#" class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">ติดต่อเรา</a>
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">เงื่อนไขการใช้งาน</a>
                    <a href="#"
                        class="hover:text-white transition duration-300 mb-2 md:mb-0 font-light">นโยบายความเป็นส่วนตัว</a>
                </div>
            </div>
            <hr class="border-gray-700 my-6">
            <p class="text-xs md:text-sm font-light">&copy; <?php echo date('Y'); ?> PixelLink. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (!empty($message)) : ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '<?= $message_type ?>',
                    title: '<?= $message_type === 'success' ? 'สำเร็จ!' : 'เกิดข้อผิดพลาด!' ?>',
                    text: '<?= htmlspecialchars($message) ?>',
                    confirmButtonText: 'ตกลง',
                    customClass: {
                        confirmButton: 'btn-primary'
                    },
                    buttonsStyling: false
                });
            });
        </script>
    <?php endif; ?>
</body>

</html>