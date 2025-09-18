<?php
// ไฟล์ connect.php สำหรับเชื่อมต่อฐานข้อมูล
include 'connect.php'; // <<< แก้ไขตรงนี้

// กำหนด Timezone
date_default_timezone_set('Asia/Bangkok');

$errors = []; // สำหรับเก็บข้อผิดพลาดในการ validate input
$success_message = ''; // สำหรับแสดงข้อความสำเร็จ

// เก็บค่าฟอร์มที่กรอกมาแล้ว เพื่อให้ไม่ต้องกรอกใหม่เมื่อมีข้อผิดพลาด
$username = '';
$first_name_th = ''; // ใช้สำหรับรับค่าจากฟอร์ม, จะแมปกับ first_name ใน DB
$last_name_th = '';  // ใช้สำหรับรับค่าจากฟอร์m, จะแมปกับ last_name ใน DB
$email = '';
$phone = '';
$user_type = '';
$password = '';
$confirm_password = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name_th = trim($_POST['first_name_th'] ?? '');
    $last_name_th = trim($_POST['last_name_th'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $user_type = trim($_POST['user_type'] ?? '');

    // --- การตรวจสอบข้อมูล (Validation) ---

    // Username
    if (empty($username)) {
        $errors['username'] = "กรุณากรอกชื่อผู้ใช้";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = "ชื่อผู้ใช้ต้องเป็นตัวอักษรภาษาอังกฤษ, ตัวเลข, หรือ _ เท่านั้น";
    } else {
        // ตรวจสอบชื่อผู้ใช้ซ้ำในฐานข้อมูล
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?"); // <<< ใช้ $conn
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['username'] = "ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว";
        }
        $stmt->close();
    }

    // Password
    if (empty($password)) {
        $errors['password'] = "กรุณากรอกรหัสผ่าน";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 อักขระ";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = "รหัสผ่านต้องประกอบด้วยอักษรพิมพ์เล็กอย่างน้อย 1 ตัว";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = "รหัสผ่านต้องประกอบด้วยอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = "รหัสผ่านต้องประกอบด้วยตัวเลขอย่างน้อย 1 ตัว";
    } elseif (!preg_match('/[!@#$%^&*]/', $password)) {
        $errors['password'] = "รหัสผ่านต้องประกอบด้วยอักขระพิเศษอย่างน้อย 1 ตัวใน (!@#$%^&*)";
    }

    // Confirm Password
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
    }

    // First Name (TH)
    if (empty($first_name_th)) {
        $errors['first_name_th'] = "กรุณากรอกชื่อ (ภาษาไทย)";
    }

    // Last Name (TH)
    if (empty($last_name_th)) {
        $errors['last_name_th'] = "กรุณากรอกนามสกุล (ภาษาไทย)";
    }

    // Email
    if (empty($email)) {
        $errors['email'] = "กรุณากรอกอีเมล";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "รูปแบบอีเมลไม่ถูกต้อง (ตัวอย่าง: example@domain.com)";
    } else {
        // ตรวจสอบอีเมลซ้ำในฐานข้อมูล
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?"); // <<< ใช้ $conn
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = "อีเมลนี้มีผู้ใช้งานแล้วในระบบ";
        }
        $stmt->close();
    }

    // Phone
    if (empty($phone)) {
        $errors['phone'] = "กรุณากรอกเบอร์โทรศัพท์";
    } elseif (!preg_match('/^[0-9]{9,10}$/', $phone)) {
        $errors['phone'] = "เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลักเท่านั้น";
    }

    // User Type
    if (empty($user_type) || !in_array($user_type, ['client', 'designer', 'admin'])) {
        $errors['user_type'] = "กรุณาเลือกสถานะผู้ใช้งาน (ผู้ว่าจ้าง หรือ นักออกแบบ)";
    }

    // หากไม่มีข้อผิดพลาด ให้บันทึกข้อมูลลงฐานข้อมูล
    if (empty($errors)) {
        // ไม่มีการ Hash รหัสผ่าน (เก็บเป็น Plain Text - ไม่แนะนำอย่างยิ่ง!)
        $plain_password = $password; 

        // SQL INSERT INTO users
        // Note: registration_date, is_approved, is_active will use default values from DB.
        // last_login is nullable, so no need to include in insert if not provided.
        $sql = "INSERT INTO users (username, password, email, first_name, last_name, phone_number, user_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql); // <<< ใช้ $conn

        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error)); // <<< ใช้ $connt->error
        }

        // Bind parameters
        $stmt->bind_param(
            "sssssss", // 7 's' for 7 string parameters
            $username,
            $plain_password,
            $email,
            $first_name_th, // Mapped to first_name in DB
            $last_name_th,  // Mapped to last_name in DB
            $phone,         // Mapped to phone_number in DB
            $user_type
        );

        if ($stmt->execute()) {
            $success_message = "บัญชีผู้ใช้ของคุณถูกสร้างสำเร็จแล้ว คุณสามารถเข้าสู่ระบบได้ทันที";
            // เคลียร์ค่าฟอร์มหลังจากสมัครสำเร็จ
            $username = $first_name_th = $last_name_th = $email = $phone = $user_type = $password = $confirm_password = '';
        } else {
            $errors['db'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน - Design Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* --- Global Styles & Typography --- */
        body {
            font-family: 'Kanit', sans-serif;
            font-weight: 300; /* กำหนด font-weight เริ่มต้นที่ 300 (light) */
            background-color: #f0f2f5; /* สีพื้นหลังอ่อนๆ */
        }

        .kanit-tag {
            font-family: 'Kanit', sans-serif;
        }

        /* --- Main Container & Form Styles --- */
        .main-container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 1200px;
            width: 100%;
        }

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
            border-radius: 6px; /* ปรับลดนิดหน่อยเพื่อให้เข้ากับ Ant Design */
            padding: 0.4rem 0.75rem; /* ปรับ padding ด้านบน/ล่างเล็กน้อย เพื่อเพิ่มพื้นที่ */
            width: 100%;
            height: 42px; /* กำหนดความสูงตาม Ant Design */
            font-size: 0.95rem; /* ปรับขนาดฟอนต์ให้เข้ากับความสูง */
            font-weight: 300; /* ทำให้ฟอนต์ใน input ผอมลง (light) */
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #ffffff; /* พื้นหลัง input สีขาว */
            box-sizing: border-box; /* สำคัญเพื่อให้ padding ไม่ดัน height */
            line-height: normal; /* เพิ่มบรรทัดนี้ เพื่อให้ line-height เป็นค่าปกติ */
        }

        .form-input:focus {
            outline: none;
            border-color: #4096ff; /* Ant Design Blue */
            box-shadow: 0 0 0 2px rgba(5, 145, 255, 0.2); /* Ant Design shadow */
        }

        .form-label {
            font-weight: 400; /* Regular weight for labels */
            color: #374151; /* gray-700 */
            margin-bottom: 4px; /* ลด margin-bottom */
            display: block;
            font-size: 0.95rem; /* ขนาดฟอนต์สำหรับ label */
        }

        textarea.form-input {
            height: auto; /* textarea ไม่ต้องกำหนด height ตายตัว */
            min-height: 80px; /* กำหนดความสูงขั้นต่ำ */
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        /* --- Buttons --- */
        .btn-primary {
            background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%); /* Deep Blue to Sky Blue */
            color: white;
            transition: all 0.3s ease; /* ให้มีการเปลี่ยนสีแบบ smooth */
            box-shadow: 0 4px 15px rgba(13, 150, 210, 0.3); /* กำหนดเงาเริ่มต้น */
            display: inline-flex; /* ใช้ flexbox เพื่อจัด icon และ text ให้อยู่ตรงกลางได้ง่ายขึ้น */
            align-items: center; /* จัดให้อยู่ตรงกลางในแนวตั้ง */
            justify-content: center; /* จัดให้อยู่ตรงกลางในแนวนอน */
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #0d96d2 0%, #0a5f97 100%); /* Invert gradient on hover */
            box-shadow: 0 4px 15px rgba(13, 150, 210, 0.3); /* ทำให้ box-shadow เท่าเดิม เพื่อขนาดคงที่ */
        }

        .btn-register {
            height: 40px;
            width: 100%;
            font-family: 'Kanit', sans-serif;
            color: white;
            background: #4096ff; /* สีฟ้า Ant Design */
            border-radius: 6px;
            font-weight: 500; /* Semi-bold สำหรับปุ่ม */
            transition: all 0.2s ease-in-out;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .btn-register:hover {
            opacity: 0.9;
            /* transform: translateY(-1px); */ /* ถ้าไม่ต้องการให้ขยับ ให้ลบออก */
            box-shadow: 0 4px 8px rgba(64, 150, 255, 0.3); /* ปรับให้เข้ากับสีปุ่ม #4096ff */
        }

        /* --- Messages & Headers --- */
        .error-message {
            color: #ef4444; /* red-500 */
            font-size: 0.8rem; /* text-sm */
            margin-top: 4px;
            font-weight: 300;
        }

        .success-message {
            background-color: #d1fae5;
            border: 1px solid #34d399;
            color: #065f46;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 400;
        }

        .header-text {
            color: #2c3e50;
            font-weight: 600;
        }

        .section-header {
            font-weight: 600;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .text-sm-note {
            font-size: 0.8rem;
            color: #6b7280;
            font-weight: 300;
        }

        .divider {
            border-color: #cccccc; /* สีตาม Ant Design divider */
            margin-top: 4px;
            margin-bottom: 14px;
        }

        /* --- Password Toggle Icon --- */
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

        /* --- Responsive Adjustments for Buttons (from previous answer) --- */
        @media (max-width: 768px) {
            .hero-section .btn-primary,
            .hero-section .btn-secondary {
                width: 90%;
                max-width: none;
                font-size: 0.9rem;
                padding: 0.75rem 1.25rem;
            }
            .btn-primary, .btn-secondary { /* สำหรับปุ่มทั่วไป */
                padding: 0.75rem 1.25rem; /* ปรับ padding ให้เหมาะสมถ้าจำเป็น */
            }
        }
        @media (max-width: 480px) {
            .btn-primary, .btn-secondary {
                padding: 0.6rem 1rem; /* ปรับ padding ให้เหมาะสมกับหน้าจอที่เล็กมากๆ */
                font-size: 0.875rem; /* text-sm */
            }
        }

        /* --- Custom Menu (Ant Design related styles) --- */
        .custom-menu .ant-menu-item:hover,
        .custom-menu .ant-menu-submenu-title:hover {
            background-color: #5e0017 !important; /* สีโฮเวอร์ของเมนูหลักและซับเมนู */
        }

        .custom-menu .ant-menu-item,
        .custom-menu .ant-menu-submenu-title {
            transition: background-color 0.4s ease !important; /* เพิ่ม transition ให้เนียนขึ้น */
        }

        .custom-menu .ant-menu-item-selected {
            background-color: #5e0017 !important; /* สีของเมนูที่ถูกเลือก */
            color: white !important;
        }

        .custom-menu .ant-menu-item-selected:hover {
            background-color: #c90e3c !important; /* สีโฮเวอร์ของเมนูที่ถูกเลือก */
            color: white !important;
        }

        /* --- Ant Design Layout Overrides --- */
        .ant-menu-dark.ant-menu-inline .ant-menu-sub.ant-menu-inline {
            background: none; /* ทำให้พื้นหลังของ Submenu เป็นโปร่งใสใน Dark Theme */
        }

        .ant-layout .ant-layout-sider-trigger {
            background-color: transparent; /* ทำให้พื้นหลังของ Trigger button ใน Sider เป็นโปร่งใส */
        }

        .ant-layout.ant-layout-has-sider > .ant-layout {
            width: auto;
            height: auto;
        }

        /* --- Ant Design Form Item Overrides --- */
        .ant-form-item .ant-form-item-explain-error {
            text-align: start; /* จัดข้อความ error ให้อยู่ชิดซ้าย */
            color: #ff4d4f; /* Ant Design error color */
        }
        /* Specificity for Ant Design, if you are using Ant Design's compiled CSS directly */
        :where(.css-1pq2us1).ant-form-item .ant-form-item-explain-error {
            color: #ff4d4f; /* Specificity to override Ant Design defaults if needed */
        }


        /* --- Ant Design Modal Overrides --- */
        .ant-modal-footer .ant-btn {
            box-shadow: none !important; /* ลบ box-shadow ของปุ่มใน Modal Footer */
        }

        /* --- Privacy Buttons/Text --- */
        .privacy-button {
            cursor: pointer;
            color: white;
            font-size: 16px; /* ใส่ px เพื่อความชัดเจน */
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
            transition: color 0.3s ease;
        }

        .privacy-button:hover {
            color: #800020; /* สี Dark Maroon */
        }

        .privacy-text {
            cursor: pointer;
            color: #800020; /* สี Dark Maroon */
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.3s ease;
        }

        .privacy-text:hover {
            color: #a32638; /* สี Maroon ที่สว่างขึ้นเล็กน้อย */
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="main-container flex flex-col md:flex-row w-full max-w-4xl lg:max-w-6xl">
        <div class="hidden md:block md:w-1/2 bg-gray-100 rounded-l-lg overflow-hidden">
            <img src="dist/img/cover.png" alt="Register Background"
                class="w-full h-full object-cover object-center rounded-l-lg">
        </div>

        <div class="w-full md:w-1/2 p-8 lg:p-12">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-semibold text-gray-800 kanit-tag header-text">สมัครสมาชิก</h1>
                <p class="text-gray-500 text-sm mt-2">สร้างบัญชีของคุณเพื่อเข้าสู่ระบบ</p>
            </div>

            <?php if (!empty($success_message)) : ?>
                <div class="success-message">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="mb-4">
                    <label for="user_type" class="form-label">ฉันต้องการสมัครเป็นสมาชิก</label>
                    <select id="user_type" name="user_type" class="form-input" required
                        data-validation-rules='{"empty": "กรุณาเลือกสถานะผู้ใช้งาน (ผู้ว่าจ้าง หรือ นักออกแบบ)"}'>
                        <option value="" disabled selected hidden>เลือกสถานะผู้ใช้งาน</option>
                        <option value="client" <?= ($user_type == 'client') ? 'selected' : '' ?>>ผู้ว่าจ้าง</option>
                        <option value="designer" <?= ($user_type == 'designer') ? 'selected' : '' ?>>นักออกแบบ</option>
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
                            value="<?= htmlspecialchars($first_name_th) ?>" placeholder="ชื่อจริง" required
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
                            value="<?= htmlspecialchars($last_name_th) ?>" placeholder="นามสกุล" required
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
                        value="<?= htmlspecialchars($phone) ?>" placeholder="0XXXXXXXXX" required
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
                    <label for="password" class="form-label">รหัสผ่าน</label>
                    <div class="ant-form-item-control-input-content">
                        <input type="password" id="password" name="password" class="form-input" placeholder="รหัสผ่าน" required
                            data-validation-rules='{"empty": "กรุณากรอกรหัสผ่าน", "length": "รหัสผ่านต้องมีความยาวอย่างน้อย 8 อักขระ", "lowercase": "รหัสผ่านต้องประกอบด้วยอักษรพิมพ์เล็กอย่างน้อย 1 ตัว", "uppercase": "รหัสผ่านต้องประกอบด้วยอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว", "digit": "รหัสผ่านต้องประกอบด้วยตัวเลขอย่างน้อย 1 ตัว", "special": "รหัสผ่านต้องประกอบด้วยอักขระพิเศษอย่างน้อย 1 ตัวใน (!@#$%^&*)"}'>
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
                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน</label>
                    <div class="ant-form-item-control-input-content">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="ยืนยันรหัสผ่าน" required
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

                <button type="submit" class="btn-register kanit-tag">
                    <i class="fas fa-user-plus mr-2"></i> สมัครสมาชิก
                </button>
            </form>

            <div class="text-center mt-6 text-gray-600 text-sm">
                มีบัญชีอยู่แล้ว? <a href="index.php" class="text-blue-600 hover:underline">เข้าสู่ระบบที่นี่</a>
            </div>
        </div>
    </div>

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

        // SweetAlert สำหรับ Success
        <?php if (!empty($success_message)) : ?>
        Swal.fire({
            icon: 'success',
            title: 'สมัครสมาชิกสำเร็จ!',
            text: '<?= htmlspecialchars($success_message) ?>',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'login.php'; // พาไปหน้า login หลังจากกด OK
            }
        });
        <?php endif; ?>

        // SweetAlert สำหรับ Error จาก Server (ถ้ามี)
        <?php if (!empty($errors) && !isset($errors['db'])) : ?>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด!',
            html: 'กรุณากรอกข้อมูลให้ถูกต้องและครบถ้วน:<br><ul><?php foreach ($errors as $error) { echo "<li>" . htmlspecialchars($error) . "</li>"; } ?></ul>',
            confirmButtonText: 'แก้ไขข้อมูล'
        });
        <?php endif; ?>

        // SweetAlert สำหรับ Database Error โดยเฉพาะ
        <?php if (isset($errors['db'])) : ?>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด!',
            text: '<?= htmlspecialchars($errors['db']) ?>',
            confirmButtonText: 'ตกลง'
        });
        <?php endif; ?>

        // --- JavaScript สำหรับ Real-time Validation ---
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form'); // เลือกฟอร์มของคุณ
            // เลือกทุก input, select, textarea ยกเว้น type="submit" และ type="hidden"
            const inputs = form.querySelectorAll('input:not([type="submit"]):not([type="hidden"]), select, textarea'); 

            // ฟังก์ชันสำหรับแสดงข้อความ error
            function displayError(inputElement, message) {
                const errorSpan = document.getElementById(inputElement.id + '-error');
                if (errorSpan) {
                    errorSpan.textContent = message;
                    // บรรทัดนี้ถูกคอมเมนต์ออกไป เพราะเราจะใช้ min-height ใน CSS แทน
                    // errorSpan.style.display = message ? 'block' : 'none'; 
                    
                    // Toggle class สำหรับ highlight input ที่มี error
                    // ใช้ class ที่มีอยู่แล้ว หรือเพิ่ม class เพื่อให้มี border สีแดง
                    inputElement.classList.toggle('border-red-500', !!message);
                    inputElement.classList.toggle('focus:border-red-500', !!message);
                    inputElement.classList.toggle('ring-red-200', !!message); // สำหรับ ring on focus
                    inputElement.classList.toggle('focus:ring-2', !!message);
                }
            }

            // ฟังก์ชันสำหรับตรวจสอบ Username
            function validateUsername(input) {
                const value = input.value.trim();
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                let errorMessage = '';

                if (value === '') {
                    errorMessage = rules.empty || 'กรุณากรอกชื่อผู้ใช้';
                } else if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                    errorMessage = rules.format || 'ชื่อผู้ใช้ต้องเป็นตัวอักษรภาษาอังกฤษ, ตัวเลข, หรือ _ เท่านั้น';
                }
                // *** การตรวจสอบชื่อผู้ใช้ซ้ำ จำเป็นต้องทำ AJAX call ไปยัง Server-side ***
                // *** หากต้องการให้ทำจริง ต้องเพิ่มโค้ด AJAX ที่นี่ ***
                
                displayError(input, errorMessage);
                return !errorMessage; // คืนค่า true หากไม่มีข้อผิดพลาด
            }

            // ฟังก์ชันสำหรับตรวจสอบ Password
            function validatePassword(input) {
                const value = input.value;
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                let errorMessage = '';

                if (value === '') {
                    errorMessage = rules.empty || 'กรุณากรอกรหัสผ่าน';
                } else if (value.length < 8) {
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
                
                // หากมี error message ที่เจาะจง ให้ใช้ตัวนั้น
                displayError(input, errorMessage);
                return !errorMessage;
            }

            // ฟังก์ชันสำหรับตรวจสอบ Confirm Password
            function validateConfirmPassword(input) {
                const passwordInput = document.getElementById('password');
                const errorMessage = (input.value !== passwordInput.value) ? 
                                       (JSON.parse(input.dataset.validationRules || '{}').match || 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน') : '';
                displayError(input, errorMessage);
                return !errorMessage;
            }

            // ฟังก์ชันสำหรับตรวจสอบ Email
            function validateEmail(input) {
                const value = input.value.trim();
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                let errorMessage = '';

                if (value === '') {
                    errorMessage = rules.empty || 'กรุณากรอกอีเมล';
                } else if (!/^\S+@\S+\.\S+$/.test(value)) { // Basic email regex
                    errorMessage = rules.format || 'รูปแบบอีเมลไม่ถูกต้อง (ตัวอย่าง: example@domain.com)';
                }
                // *** การตรวจสอบอีเมลซ้ำต้องใช้ AJAX เช่นกัน ***

                displayError(input, errorMessage);
                return !errorMessage;
            }
            
            // ฟังก์ชันสำหรับตรวจสอบ Phone
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

            // ฟังก์ชันสำหรับตรวจสอบ First/Last Name (TH) หรือ Text ทั่วไปที่ไม่ว่างเปล่า
            function validateText(input) {
                const value = input.value.trim();
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                const errorMessage = value === '' ? (rules.empty || 'กรุณากรอกข้อมูลนี้') : '';
                displayError(input, errorMessage);
                return !errorMessage;
            }

            // ฟังก์ชันสำหรับตรวจสอบ User Type (select box)
            function validateUserType(input) {
                const value = input.value;
                const rules = JSON.parse(input.dataset.validationRules || '{}');
                const errorMessage = value === '' ? (rules.empty || 'กรุณาเลือกสถานะผู้ใช้งาน') : '';
                displayError(input, errorMessage);
                return !errorMessage;
            }

            // เพิ่ม Event Listeners ให้กับทุก Input Field
            inputs.forEach(input => {
                const inputName = input.name;
                const inputId = input.id; // ใช้ id เพื่อระบุ input field

                input.addEventListener('input', function() { // ตรวจสอบเมื่อมีการพิมพ์
                    // ใช้ switch case หรือ if/else if เพื่อเรียก validation function ที่เหมาะสม
                    if (inputId === 'username_account') { // ใช้ id ที่กำหนดใน HTML
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
                    // คุณสามารถเพิ่ม validation สำหรับ field อื่นๆ ได้ที่นี่
                });

                // เพิ่ม Event Listener สำหรับ blur (เมื่อออกจากช่อง) เพื่อให้แน่ใจว่าได้ตรวจสอบ
                input.addEventListener('blur', function() {
                    // เรียก validation function เดียวกัน
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
                    // ตรวจสอบ field อื่นๆ ที่นี่

                    if (!fieldIsValid) {
                        formIsValid = false;
                    }
                });

                if (!formIsValid) {
                    event.preventDefault(); // หยุดการส่งฟอร์มถ้ามีข้อผิดพลาด
                    Swal.fire({ // แสดง SweetAlert แจ้งเตือนเมื่อ submit และมี error
                        icon: 'error',
                        title: 'ข้อมูลไม่ถูกต้อง!',
                        text: 'กรุณาตรวจสอบข้อมูลที่กรอกให้ถูกต้องก่อนส่ง',
                        confirmButtonText: 'ตกลง'
                    });
                }
            });

            // หากมีการโหลดหน้าเข้ามาแล้วมี error จาก PHP เดิมอยู่แล้ว ให้แสดงทันที
            inputs.forEach(input => {
                const errorSpan = document.getElementById(input.id + '-error');
                if (errorSpan && errorSpan.textContent.trim() !== '') {
                    input.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-200');
                }
            });
        });
    </script>

    <style>
        .error-message {
            color: #ef4444; /* สีแดงสำหรับข้อความ error */
            font-size: 0.875rem; /* ขนาดฟอนต์ */
            margin-top: 4px; /* ระยะห่างด้านบน */
            display: block; /* ทำให้ error message เป็น block level element */
            min-height: 20px; /* **นี่คือส่วนสำคัญที่เพิ่มเข้ามา** เพื่อจองพื้นที่ไว้เสมอ */
        }
        /* คลาสอื่นๆ ที่คุณใช้อยู่แล้ว เช่น form-label, form-input, btn-register, etc. */
        /* และ class จาก Tailwind CSS ที่คุณใช้ เช่น mb-4, text-center, flex, etc. */
    </style>
</body>
</html>