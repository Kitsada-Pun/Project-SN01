<?php
session_start();

// เปิดการแสดงข้อผิดพลาดสำหรับการดีบัก (ควรปิดใน Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_log.log');

// รวมไฟล์เชื่อมต่อฐานข้อมูล
include 'conn.php'; // ตรวจสอบให้แน่ใจว่าไฟล์นี้สร้างตัวแปร $condb อย่างถูกต้อง

// กำหนด Timezone
date_default_timezone_set('Asia/Bangkok');

$errors = []; // สำหรับเก็บข้อผิดพลาดในการ validate input
$success_message = ''; // สำหรับแสดงข้อความสำเร็จ

// ตัวแปรสำหรับเก็บค่าฟอร์ม (ค่าเริ่มต้นว่างเปล่า)
$user_id = 0;
$username = '';
$first_name = ''; 
$last_name = ''; 
$email = '';
$phone_number = '';
$user_type = '';
$current_password_db_value = ''; // ใช้เก็บค่ารหัสผ่านเดิมจาก DB (อาจเป็น hashed หรือ plain ขึ้นอยู่กับ DB)

// --- การตรวจสอบสิทธิ์ (เฉพาะ Admin เท่านั้น) ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้!'
    ];
    header('Location: index.php'); // Redirect ไปหน้าหลักหรือหน้า Login
    exit();
}

// --- กำหนดค่า user_id ให้สคริปต์รู้จัก ไม่ว่าจะมาจาก GET หรือ POST ---
// ถ้าเป็น POST request และมี user_id มาด้วย ให้ใช้ user_id จาก POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
} 
// ถ้าไม่ใช่ POST หรือ POST ไม่มี user_id แต่เป็น GET request และมี id มาด้วย ให้ใช้ id จาก GET
elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];
}

// หาก user_id ยังคงไม่ถูกต้องหลังจากตรวจสอบทั้ง GET และ POST ให้ Redirect กลับ
if ($user_id <= 0) {
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'รหัสผู้ใช้ไม่ถูกต้องสำหรับการแก้ไข!'
    ];
    header('Location: manage_users.php');
    exit();
}

// --- ดึงข้อมูลผู้ใช้จากฐานข้อมูล (สำหรับแสดงในฟอร์ม และเก็บค่าปัจจุบัน) ---
// ตรวจสอบชื่อคอลัมน์ใน SELECT statement ของคุณให้ตรงกับในฐานข้อมูล!
// ตัวอย่าง: ถ้าใน DB คือ first_name, last_name, phone_number
$sql_select_user = "SELECT user_id, username, email, first_name, last_name, phone_number, user_type, password FROM users WHERE user_id = ?";
// หากใน DB เป็น first_name_th, last_name_th, phone ให้ใช้แบบนี้แทน:
// $sql_select_user = "SELECT user_id, username, email, first_name_th, last_name_th, phone, user_type, password FROM users WHERE user_id = ?";


$stmt = $condb->prepare($sql_select_user);

if ($stmt === false) {
    error_log("Edit User (Fetch): Error preparing select statement: " . $condb->error);
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'ระบบไม่สามารถเตรียมการดึงข้อมูลได้ กรุณาลองใหม่ภายหลัง.'
    ];
    header('Location: manage_users.php');
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user_data = $result->fetch_assoc();
    // นำข้อมูลที่ดึงมาได้ไปใส่ในตัวแปรสำหรับแสดงในฟอร์ม
    $username = $user_data['username'];
    $first_name = $user_data['first_name']; 
    $last_name = $user_data['last_name'];   
    $email = $user_data['email'];
    $phone_number = $user_data['phone_number']; 
    $user_type = $user_data['user_type'];
    $current_password_db_value = $user_data['password']; // เก็บค่ารหัสผ่านจาก DB (อาจเป็น hashed หรือ plain)
} else {
    // กรณีที่ user_id ถูกต้อง แต่ไม่พบข้อมูลผู้ใช้ใน DB
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'ไม่พบข้อมูลผู้ใช้ที่ต้องการแก้ไข!'
    ];
    header('Location: manage_users.php');
    exit();
}
$stmt->close();


// --- จัดการการส่งฟอร์ม (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    // ค่าเหล่านี้จะถูกดึงจาก $_POST และจะใช้เติมฟอร์มในกรณีที่เกิด Validation Error
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name_th'] ?? ''); // ใช้ first_name_th จากฟอร์ม
    $last_name = trim($_POST['last_name_th'] ?? ''); // ใช้ last_name_th จากฟอร์ม
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone'] ?? ''); // ใช้ phone จากฟอร์ม
    $user_type = trim($_POST['user_type'] ?? '');
    $new_password = $_POST['password'] ?? ''; // รหัสผ่านใหม่ (อาจจะว่างเปล่า)
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- การตรวจสอบข้อมูล (Validation) ---

    // Username
    if (empty($username)) {
        $errors['username'] = "กรุณากรอกชื่อผู้ใช้";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = "ชื่อผู้ใช้ต้องเป็นตัวอักษรภาษาอังกฤษ, ตัวเลข, หรือ _ เท่านั้น";
    } else {
        // ตรวจสอบชื่อผู้ใช้ซ้ำ (ยกเว้นชื่อผู้ใช้ปัจจุบันที่กำลังแก้ไข)
        $stmt = $condb->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['username'] = "ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว";
        }
        $stmt->close();
    }

    // Email
    if (empty($email)) {
        $errors['email'] = "กรุณากรอกอีเมล";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "รูปแบบอีเมลไม่ถูกต้อง (ตัวอย่าง: example@domain.com)";
    } else {
        // ตรวจสอบอีเมลซ้ำ (ยกเว้นอีเมลผู้ใช้ปัจจุบันที่กำลังแก้ไข)
        $stmt = $condb->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = "อีเมลนี้มีผู้ใช้งานแล้วในระบบ";
        }
        $stmt->close();
    }

    // First Name
    if (empty($first_name)) {
        $errors['first_name_th'] = "กรุณากรอกชื่อ (ภาษาไทย)";
    }

    // Last Name
    if (empty($last_name)) {
        $errors['last_name_th'] = "กรุณากรอกนามสกุล (ภาษาไทย)";
    }

    // Phone
    if (empty($phone_number)) {
        $errors['phone'] = "กรุณากรอกเบอร์โทรศัพท์";
    } elseif (!preg_match('/^[0-9]{9,10}$/', $phone_number)) {
        $errors['phone'] = "เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลักเท่านั้น";
    }

    // User Type
    if (empty($user_type) || !in_array($user_type, ['client', 'designer', 'admin'])) {
        $errors['user_type'] = "กรุณาเลือกสถานะผู้ใช้งาน (ผู้ว่าจ้าง หรือ นักออกแบบ)";
    }

    // Password (ถ้ามีการกรอกรหัสผ่านใหม่)
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $errors['password'] = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 อักขระ";
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $errors['password'] = "รหัสผ่านต้องประกอบด้วยอักษรพิมพ์เล็กอย่างน้อย 1 ตัว";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $errors['password'] = "รหัสผ่านต้องประกอบด้วยอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $errors['password'] = "รหัสผ่านต้องประกอบด้วยตัวเลขอย่างน้อย 1 ตัว";
        } elseif (!preg_match('/[!@#$%^&*]/', $new_password)) {
            $errors['password'] = "รหัสผ่านต้องประกอบด้วยอักขระพิเศษอย่างน้อย 1 ตัวใน (!@#$%^&*)";
        }

        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
        }
    }

    // หากไม่มีข้อผิดพลาด (ทั้งจากการ Validation) ให้บันทึกข้อมูลลงฐานข้อมูล
    if (empty($errors)) {
        // เตรียม SQL Query แบบมีเงื่อนไขสำหรับ password
        $sql = "UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone_number = ?, user_type = ? ";
        $types = "ssssss"; // สำหรับ username, email, first_name, last_name, phone_number, user_type
        $params = [
            $username,
            $email,
            $first_name,
            $last_name,
            $phone_number,
            $user_type
        ];

        if (!empty($new_password)) {
            // ถ้ามีการกรอกรหัสผ่านใหม่: เพิ่ม password field เข้าไปใน UPDATE query
            $sql .= ", password = ? ";
            $types .= "s"; // เพิ่มประเภทสำหรับ password (plain text)
            $params[] = $new_password; // บันทึกเป็น plain text ตามที่ผู้ใช้ต้องการ
        }

        $sql .= " WHERE user_id = ?";
        $types .= "i"; // เพิ่มประเภทสำหรับ user_id
        $params[] = $user_id;

        $stmt = $condb->prepare($sql);

        if ($stmt === false) {
            error_log("Edit User (Update): Error preparing update statement: " . $condb->error);
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'ระบบไม่สามารถเตรียมการอัปเดตข้อมูลได้ กรุณาลองใหม่ภายหลัง.'
            ];
            // ไม่ต้อง Redirect จะแสดง error บนหน้านี้
        } else {
            // Dynamically bind parameters
            // call_user_func_array ต้องการ reference สำหรับ parameters
            $bind_names = [$types];
            for ($i = 0; $i < count($params); $i++) {
                $bind_names[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);

            if ($stmt->execute()) {
                $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => 'อัปเดตข้อมูลผู้ใช้สำเร็จแล้ว!'
                ];
                // Redirect ไปยังหน้าจัดการผู้ใช้หลังจากอัปเดต
                header('Location: manage_users.php');
                exit();
            } else {
                error_log("Edit User (Update): Error executing update statement for UserID " . $user_id . ": " . $stmt->error);
                $_SESSION['message'] = [
                    'type' => 'error', // ต้องเป็น 'error'
                    'text' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูลผู้ใช้: ' . htmlspecialchars($stmt->error)
                ];
                // ไม่ต้อง Redirect; จะคงอยู่บนหน้า edit_user.php เพื่อแสดงข้อผิดพลาด
            }
            $stmt->close();
        }
    }
    // หากมีข้อผิดพลาดจากการ POST และไม่ได้ Redirect ไปแล้ว
    // จะแสดงฟอร์มพร้อมข้อผิดพลาดที่ปรากฏ
}

// ปิดการเชื่อมต่อฐานข้อมูล (ถ้ายังไม่ได้ปิด)
if ($condb && $condb->ping()) {
    $condb->close();
}

// --- Include Header, Navbar, Sidebar ---
// ตรวจสอบให้แน่ใจว่าไฟล์เหล่านี้ไม่มี Unclosed PHP tags หรือ HTML ที่ผิดรูป
include 'header.php';
include 'navbar.php';
include 'sidebar_menu.php';
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลผู้ใช้ - แอดมิน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">

    <style>
        /* Global Font */
        body,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .navbar-nav,
        .content-wrapper,
        .main-sidebar,
        .btn,
        table,
        label,
        .form-control {
            font-family: 'Kanit', sans-serif !important;
            font-weight: 400;
        }

        /* AdminLTE Layout Overrides */
        .content-wrapper {
            padding-top: 20px;
            padding-left: 20px;
            padding-right: 20px;
            padding-bottom: 20px;
            min-height: calc(100vh - (var(--main-header-height, 60px) + var(--main-footer-height, 57px)));
            display: flex;
            flex-direction: column;
        }

        .content {
            flex-grow: 1;
        }

        html,
        body {
            height: 100%;
        }

        .wrapper {
            min-height: 100%;
        }

        /* Custom Message Container (Ant Design-like - Top-Right Fixed) */
        .message-container {
            position: fixed;
            top: 65px; /* Adjust based on your navbar height */
            right: 24px;
            z-index: 10000;
            pointer-events: none; /* Allows clicks to pass through when not active */
            opacity: 0;
            transform: translateZ(0); /* For smoother animation */
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
            max-width: 350px; /* Limit width */
            width: auto;
        }

        .message-container.show {
            opacity: 1;
            pointer-events: auto; /* Allow interaction when shown */
        }

        .message-wrapper {
            display: flex;
            align-items: center;
            padding: 9px 16px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
            min-height: 40px; /* Minimum height for consistency */
        }

        .message-icon-wrapper {
            margin-right: 8px;
            display: flex; /* To center the icon vertically */
            align-items: center;
        }

        .message-icon {
            font-size: 16px;
            line-height: 1; /* Ensure icon doesn't add extra line height */
        }

        .message-text {
            font-size: 14px;
            color: rgba(0, 0, 0, 0.88);
            word-break: break-word; /* Ensure long messages wrap */
        }

        /* Message Type Specific Colors */
        .success-type .message-icon {
            color: #52c41a; /* Ant Green */
        }
        .error-type .message-icon {
            color: #ff4d4f; /* Ant Red */
        }
        /* You can add more types like warning, info etc. */

        /* Content Container for Form */
        .content-container {
            padding: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: rgba(0, 0, 0, 0.1) 0px 0px 10px;
        }

        /* Form Specific Styles (from register.php) */
        .form-input-group {
            display: flex;
            gap: 10px; /* ระยะห่างระหว่างคอลัมน์ */
            width: 100%;
        }

        .form-input-group > div {
            flex: 1; /* ทำให้แต่ละ input ในกลุ่มมีขนาดเท่ากัน */
        }

        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.6rem 0.95rem; /* ปรับขนาดช่องกรอกข้อมูลให้ใหญ่ขึ้น */
            width: 100%;
            height: 40px; /* ปรับความสูงของช่องกรอกข้อมูล */
            font-size: 1rem; /* ปรับขนาดฟอนต์ในช่องกรอกข้อมูล */
            font-weight: 300;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #ffffff;
            box-sizing: border-box;
            line-height: normal;
        }

        .form-input:focus {
            outline: none;
            border-color: #4096ff; /* Ant Design Blue */
            box-shadow: 0 0 0 2px rgba(5, 145, 255, 0.2); /* Ant Design shadow */
        }

        .form-label {
            font-weight: 400;
            color: #374151;
            margin-bottom: 6px; /* เพิ่มระยะห่าง label กับ input */
            display: block;
            font-size: 1rem; /* ปรับขนาดฟอนต์สำหรับ label */
        }

        textarea.form-input {
            height: auto;
            min-height: 80px;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .section-header {
            font-weight: 600;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .error-message {
            color: #ef4444; /* red-500 */
            font-size: 0.8rem; /* text-sm */
            margin-top: 4px;
            font-weight: 300;
            display: block; /* สำคัญ: เพื่อให้ min-height ทำงาน */
            min-height: 20px; /* สำคัญ: จองพื้นที่ไว้เสมอเพื่อไม่ให้ Layout ขยับ */
        }

        /* Buttons */
        .btn-action {
            height: 48px; /* ปรับความสูงของปุ่ม */
            width: 100%;
            font-family: 'Kanit', sans-serif;
            color: white;
            background: #1890ff; /* Ant Design Blue */
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem; /* ปรับขนาดฟอนต์ของปุ่ม */
            margin-top: 20px; /* Space above button */
        }

        .btn-action:hover {
            opacity: 0.9;
            box-shadow: 0 4px 8px rgba(24, 144, 255, 0.3); /* Adjust shadow color */
        }

        .btn-cancel {
            height: 48px;
            width: 100%;
            font-family: 'Kanit', sans-serif;
            color: rgba(0, 0, 0, 0.88);
            background: #fff;
            border: 1px solid #d9d9d9;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-top: 10px; /* Space above button */
        }

        .btn-cancel:hover {
            color: #40a9ff;
            border-color: #40a9ff;
            box-shadow: 0 2px 0 rgba(0, 0, 0, 0.045);
        }

        /* Password Toggle Icon */
        .ant-form-item-control-input-content {
            position: relative; /* สำหรับ icon รหัสผ่าน */
        }

        .ant-input-password-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0a0a0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-input-group {
                flex-direction: column;
                gap: 0;
            }
            .form-input-group > div {
                margin-bottom: 15px; /* Add margin between stacked inputs */
            }
        }

    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">

        <!-- Custom Message Container for Success -->
        <div id="custom-success-message-container" class="message-container success-type" style="display: none;">
            <div class="message-wrapper">
                <div class="message-icon-wrapper">
                    <i class="fas fa-check-circle message-icon"></i>
                </div>
                <div class="message-text"></div>
            </div>
        </div>

        <!-- Custom Message Container for Error -->
        <div id="custom-error-message-container" class="message-container error-type" style="display: none;">
            <div class="message-wrapper">
                <div class="message-icon-wrapper">
                    <i class="fas fa-times-circle message-icon"></i>
                </div>
                <div class="message-text"></div>
            </div>
        </div>

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">แก้ไขข้อมูลผู้ใช้</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="main.php">หน้าหลัก</a></li>
                                <li class="breadcrumb-item"><a href="manage_users.php">จัดการข้อมูลผู้ใช้</a></li>
                                <li class="breadcrumb-item active">แก้ไขข้อมูลผู้ใช้</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <main class="content">
                <div class="content-container">
                    <div class="text-center mb-6">
                        <h4 class="font-semibold text-gray-800 kanit-tag header-text">แบบฟอร์มแก้ไขข้อมูลผู้ใช้</h4>
                        <p class="text-gray-500 text-sm mt-2">โปรดกรอกข้อมูลที่ต้องการแก้ไขให้ครบถ้วน</p>
                    </div>

                    <form action="edit_user.php" method="POST">
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_id) ?>">

                        <div class="mb-4">
                            <label for="user_type" class="form-label">สถานะผู้ใช้งาน</label>
                            <select id="user_type" name="user_type" class="form-input" required
                                data-validation-rules='{"empty": "กรุณาเลือกสถานะผู้ใช้งาน"}'>
                                <option value="" disabled hidden>เลือกสถานะผู้ใช้งาน</option>
                                <option value="client" <?= ($user_type == 'client') ? 'selected' : '' ?>>ผู้ว่าจ้าง</option>
                                <option value="designer" <?= ($user_type == 'designer') ? 'selected' : '' ?>>นักออกแบบ</option>
                                <option value="admin" <?= ($user_type == 'admin') ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                            </select>
                            <span id="user_type-error" class="error-message">
                                <?php if (isset($errors['user_type'])) : ?>
                                    <?= htmlspecialchars($errors['user_type']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <p class="section-header kanit-tag mb-4">ข้อมูลส่วนตัว</p>
                        <div class="form-input-group mb-4">
                            <div>
                                <label for="first_name_th" class="form-label">ชื่อ (ภาษาไทย)</label>
                                <input type="text" id="first_name_th" name="first_name_th" class="form-input"
                                    value="<?= htmlspecialchars($first_name) ?>" placeholder="ชื่อจริง" required
                                    data-validation-rules='{"empty": "กรุณากรอกชื่อ (ภาษาไทย)"}'>
                                <span id="first_name_th-error" class="error-message">
                                    <?php if (isset($errors['first_name_th'])) : ?>
                                        <?= htmlspecialchars($errors['first_name_th']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div>
                                <label for="last_name_th" class="form-label">นามสกุล (ภาษาไทย)</label>
                                <input type="text" id="last_name_th" name="last_name_th" class="form-input"
                                    value="<?= htmlspecialchars($last_name) ?>" placeholder="นามสกุล" required
                                    data-validation-rules='{"empty": "กรุณากรอกนามสกุล (ภาษาไทย)"}'>
                                <span id="last_name_th-error" class="error-message">
                                    <?php if (isset($errors['last_name_th'])) : ?>
                                        <?= htmlspecialchars($errors['last_name_th']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <p class="section-header kanit-tag mb-4">ข้อมูลติดต่อ</p>
                        <div class="mb-4">
                            <label for="email" class="form-label">อีเมล</label>
                            <input type="email" id="email" name="email" class="form-input"
                                value="<?= htmlspecialchars($email) ?>" placeholder="your.email@example.com" required
                                data-validation-rules='{"empty": "กรุณากรอกอีเมล", "format": "รูปแบบอีเมลไม่ถูกต้อง (ตัวอย่าง: example@domain.com)", "duplicate": "อีเมลนี้มีผู้ใช้งานแล้วในระบบ"}'>
                            <span id="email-error" class="error-message">
                                <?php if (isset($errors['email'])) : ?>
                                    <?= htmlspecialchars($errors['email']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="mb-4">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" id="phone" name="phone" class="form-input"
                                value="<?= htmlspecialchars($phone_number) ?>" placeholder="0XXXXXXXXX" required
                                data-validation-rules='{"empty": "กรุณากรอกเบอร์โทรศัพท์", "format": "เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลักเท่านั้น"}'>
                            <span id="phone-error" class="error-message">
                                <?php if (isset($errors['phone'])) : ?>
                                    <?= htmlspecialchars($errors['phone']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <p class="section-header kanit-tag mb-4">ข้อมูลบัญชี</p>
                        <div class="mb-4">
                            <label for="username_account" class="form-label">ชื่อผู้ใช้</label>
                            <input type="text" id="username_account" name="username" class="form-input"
                                value="<?= htmlspecialchars($username) ?>" placeholder="Username" required
                                data-validation-rules='{"empty": "กรุณากรอกชื่อผู้ใช้", "format": "ชื่อผู้ใช้ต้องเป็นตัวอักษรภาษาอังกฤษ, ตัวเลข, หรือ _ เท่านั้น", "duplicate": "ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว"}'>
                            <span id="username_account-error" class="error-message">
                                <?php if (isset($errors['username'])) : ?>
                                    <?= htmlspecialchars($errors['username']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">รหัสผ่าน (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</label>
                            <div class="ant-form-item-control-input-content">
                                <input type="password" id="password" name="password" class="form-input" placeholder="รหัสผ่านใหม่ (ไม่บังคับ)"
                                    data-validation-rules='{"length": "รหัสผ่านต้องมีความยาวอย่างน้อย 8 อักขระ", "lowercase": "รหัสผ่านต้องประกอบด้วยอักษรพิมพ์เล็กอย่างน้อย 1 ตัว", "uppercase": "รหัสผ่านต้องประกอบด้วยอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว", "digit": "รหัสผ่านต้องประกอบด้วยตัวเลขอย่างน้อย 1 ตัว", "special": "รหัสผ่านต้องประกอบด้วยอักขระพิเศษอย่างน้อย 1 ตัวใน (!@#$%^&*)"}'>
                                <span class="ant-input-password-icon" onclick="togglePasswordVisibility('password')">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </span>
                            </div>
                            <span id="password-error" class="error-message">
                                <?php if (isset($errors['password'])) : ?>
                                    <?= htmlspecialchars($errors['password']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="mb-6">
                            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <div class="ant-form-item-control-input-content">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="ยืนยันรหัสผ่านใหม่ (ไม่บังคับ)"
                                    data-validation-rules='{"match": "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน"}'>
                                <span class="ant-input-password-icon" onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </span>
                            </div>
                            <span id="confirm_password-error" class="error-message">
                                <?php if (isset($errors['confirm_password'])) : ?>
                                    <?= htmlspecialchars($errors['confirm_password']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <button type="submit" class="btn-action">
                                <i class="fas fa-save mr-2"></i> บันทึกการเปลี่ยนแปลง
                            </button>
                            <a href="manage_users.php" class="btn-cancel">
                                <i class="fas fa-times mr-2"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
        <!-- /.content-wrapper -->

        <?php include 'footer.php'; // Assuming you have a footer.php ?>
    </div>
    <!-- ./wrapper -->

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="dist/js/adminlte.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Function สำหรับซ่อน/แสดงรหัสผ่าน
        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i'); // Get the <i> element inside the span
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            }
        }

        // Custom Message Display (Ant Design-like)
        function showCustomMessage(type, text) {
            const containerId = type === 'success' ? 'custom-success-message-container' : 'custom-error-message-container';
            const container = document.getElementById(containerId);
            if (container) {
                const messageText = container.querySelector('.message-text');
                messageText.textContent = text;
                container.style.display = 'block'; // Show the container
                setTimeout(() => {
                    container.classList.add('show');
                }, 10); // Small delay for animation
                setTimeout(() => {
                    container.classList.remove('show');
                    setTimeout(() => {
                        container.style.display = 'none'; // Hide after animation
                    }, 300);
                }, 3000); // Hide after 3 seconds
            }
        }

        // Handle messages from PHP sessions (e.g., after redirect from edit/delete)
        <?php if (isset($_SESSION['message'])) : ?>
            window.onload = function() {
                showCustomMessage('<?= $_SESSION['message']['type'] ?>', '<?= htmlspecialchars($_SESSION['message']['text']) ?>');
            };
            <?php unset($_SESSION['message']); // Clear the session message after displaying ?>
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input:not([type="submit"]):not([type="hidden"]), select, textarea');

            function displayError(inputElement, message) {
                const errorSpan = document.getElementById(inputElement.id + '-error');
                if (errorSpan) {
                    errorSpan.textContent = message;
                    // ไม่ต้องใช้ display: 'none' เพราะเราใช้ min-height ใน CSS แล้ว
                    
                    inputElement.classList.toggle('border-red-500', !!message);
                    inputElement.classList.toggle('focus:border-red-500', !!message);
                    inputElement.classList.toggle('ring-red-200', !!message);
                    inputElement.classList.toggle('focus:ring-2', !!message);
                }
            }

            function validateUsername(input) {
                const value = input.value.trim();
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                let errorMessage = '';

                if (value === '') {
                    errorMessage = rules.empty || 'กรุณากรอกชื่อผู้ใช้';
                } else if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                    errorMessage = rules.format || 'ชื่อผู้ใช้ต้องเป็นตัวอักษรภาษาอังกฤษ, ตัวเลข, หรือ _ เท่านั้น';
                }
                // Client-side: ไม่ได้ตรวจสอบชื่อผู้ใช้ซ้ำแบบ Real-time ด้วย AJAX ที่นี่
                // การตรวจสอบซ้ำจะทำที่ Server-side เมื่อ submit ฟอร์ม
                
                displayError(input, errorMessage);
                return !errorMessage;
            }

            function validatePassword(input) {
                const value = input.value;
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                let errorMessage = '';

                // รหัสผ่านไม่บังคับในการแก้ไข หากว่างเปล่า ไม่ต้อง Validate เพิ่มเติม
                if (value === '') {
                    displayError(input, ''); // เคลียร์ error ถ้าว่างเปล่า
                    return true;
                }

                if (value.length < 8) {
                    errorMessage = rules.length || 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 อักขระ';
                } else if (!/[a-z]/.test(value)) {
                    errorMessage = rules.lowercase || 'รหัสผ่านต้องประกอบด้วยอักษรพิมพ์เล็กอย่างน้อย 1 ตัว';
                } else if (!/[A-Z]/.test(value)) {
                    errorMessage = rules.uppercase || 'รหัสผ่านต้องประกอบด้วยอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว';
                } else if (!/[0-9]/.test(value)) {
                    errorMessage = rules.digit || 'รหัสผ่านต้องประกอบด้วยตัวเลขอย่างน้อย 1 ตัว';
                } else if (!/[!@#$%^&*]/.test(value)) {
                    errorMessage = rules.special || 'รหัสผ่านต้องประกอบด้วยอักขระพิเศษอย่างน้อย 1 ตัวใน (!@#$%^&*)';
                }
                
                displayError(input, errorMessage);
                return !errorMessage;
            }

            function validateConfirmPassword(input) {
                const passwordInput = document.getElementById('password');
                // ตรวจสอบเฉพาะถ้ามีการกรอกรหัสผ่านใหม่
                if (passwordInput.value === '') {
                    displayError(input, ''); // ถ้า password ว่าง ก็ clear confirm password error
                    return true;
                }

                const errorMessage = (input.value !== passwordInput.value) ? 
                                       (JSON.parse(input.dataset.validationRules || '{}').match || 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน') : '';
                displayError(input, errorMessage);
                return !errorMessage;
            }

            function validateEmail(input) {
                const value = input.value.trim();
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                let errorMessage = '';

                if (value === '') {
                    errorMessage = rules.empty || 'กรุณากรอกอีเมล';
                } else if (!/^\S+@\S+\.\S+$/.test(value)) { // Basic email regex
                    errorMessage = rules.format || 'รูปแบบอีเมลไม่ถูกต้อง (ตัวอย่าง: example@domain.com)';
                }
                // Client-side: ไม่ได้ตรวจสอบอีเมลซ้ำแบบ Real-time ด้วย AJAX ที่นี่
                // การตรวจสอบซ้ำจะทำที่ Server-side เมื่อ submit ฟอร์ม
                
                displayError(input, errorMessage);
                return !errorMessage;
            }
            
            function validatePhone(input) {
                const value = input.value.trim();
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                let errorMessage = '';

                if (value === '') {
                    errorMessage = rules.empty || 'กรุณากรอกเบอร์โทรศัพท์';
                } else if (!/^[0-9]{9,10}$/.test(value)) {
                    errorMessage = rules.format || 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลักเท่านั้น';
                }
                displayError(input, errorMessage);
                return !errorMessage;
            }

            function validateText(input) {
                const value = input.value.trim();
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                const errorMessage = value === '' ? (rules.empty || 'กรุณากรอกข้อมูลนี้') : '';
                displayError(input, errorMessage);
                return !errorMessage;
            }

            function validateUserType(input) {
                const value = input.value;
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                const errorMessage = value === '' ? (rules.empty || 'กรุณาเลือกสถานะผู้ใช้งาน') : '';
                displayError(input, errorMessage);
                return !errorMessage;
            }

            // เพิ่ม Event Listeners ให้กับทุก Input Field
            inputs.forEach(input => {
                const inputId = input.id;

                input.addEventListener('input', function() {
                    // เรียก validation function ที่เหมาะสม
                    if (inputId === 'username_account') {
                        validateUsername(this);
                    } else if (inputId === 'password') {
                        validatePassword(this);
                        // ตรวจสอบ confirm password ด้วย หาก password เปลี่ยน
                        const confirmPasswordInput = document.getElementById('confirm_password');
                        if (confirmPasswordInput) {
                            validateConfirmPassword(confirmPasswordInput); 
                        }
                    } else if (inputId === 'confirm_password') {
                        validateConfirmPassword(this);
                    } else if (inputId === 'email') {
                        validateEmail(this);
                    } else if (inputId === 'phone') {
                        validatePhone(this);
                    } else if (inputId === 'first_name_th' || inputId === 'last_name_th') {
                        validateText(this);
                    } else if (inputId === 'user_type') {
                        validateUserType(this);
                    }
                });

                // เพิ่ม Event Listener สำหรับ blur (เมื่อออกจากช่อง) เพื่อให้แน่ใจว่าได้ตรวจสอบ
                input.addEventListener('blur', function() {
                    this.dispatchEvent(new Event('input')); 
                });
            });

            // ตรวจสอบฟอร์มทั้งหมดก่อน submit (Client-side final validation)
            form.addEventListener('submit', function(event) {
                let formIsValid = true;
                inputs.forEach(input => {
                    const inputId = input.id;
                    let fieldIsValid = true;

                    // เรียก validation function สำหรับทุก input ที่ต้องการตรวจสอบ
                    if (inputId === 'username_account') {
                        fieldIsValid = validateUsername(input);
                    } else if (inputId === 'password') {
                        // ไม่ต้องบังคับให้กรอก password ถ้าเป็นการแก้ไข และไม่ได้กรอก password ใหม่
                        // validatePassword จะคืนค่า true หาก field ว่างเปล่า
                        fieldIsValid = validatePassword(input);
                    } else if (inputId === 'confirm_password') {
                        fieldIsValid = validateConfirmPassword(input);
                    } else if (inputId === 'email') {
                        fieldIsValid = validateEmail(input);
                    } else if (inputId === 'phone') {
                        fieldIsValid = validatePhone(input);
                    } else if (inputId === 'first_name_th' || inputId === 'last_name_th') {
                        fieldIsValid = validateText(input);
                    } else if (inputId === 'user_type') {
                        fieldIsValid = validateUserType(input);
                    }

                    if (!fieldIsValid) {
                        formIsValid = false;
                    }
                });

                if (!formIsValid) {
                    event.preventDefault(); // หยุดการส่งฟอร์มถ้ามีข้อผิดพลาด
                    showCustomMessage('error', 'กรุณาตรวจสอบข้อมูลที่กรอกให้ถูกต้องและครบถ้วน!');
                }
            });

            // หากมีการโหลดหน้าเข้ามาแล้วมี error จาก PHP เดิมอยู่แล้ว ให้แสดงทันที
            <?php if (!empty($errors)) : ?>
            // Loop through PHP errors to apply client-side highlighting
            <?php foreach ($errors as $field => $error_message) : ?>
                const inputElement = document.getElementById('<?= $field ?>');
                if (inputElement) {
                    displayError(inputElement, '<?= htmlspecialchars($error_message) ?>');
                }
                // Handle special cases where ID in PHP errors might not directly map to input ID
                // For 'first_name_th' and 'last_name_th', the IDs are the same as PHP error keys
                // For 'phone', the ID is 'phone' which matches
                // For 'username', the ID is 'username_account'
                if ('<?= $field ?>' === 'username') {
                    const usernameInput = document.getElementById('username_account');
                    if (usernameInput) {
                        displayError(usernameInput, '<?= htmlspecialchars($error_message) ?>');
                    }
                }
            <?php endforeach; ?>
            // Show a general error message if there were PHP errors on load
            showCustomMessage('error', 'มีข้อผิดพลาดในการบันทึกข้อมูล กรุณาตรวจสอบ!');
            <?php endif; ?>
        });
    </script>
</body>

</html>
