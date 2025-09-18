<?php
session_start();
date_default_timezone_set('Asia/Bangkok'); // ตั้งค่าโซนเวลาเป็นกรุงเทพฯ

// --- ข้อมูลการเชื่อมต่อฐานข้อมูล ---
// ข้อควรระวัง: ในการใช้งานจริง ควรเก็บข้อมูลเหล่านี้ไว้ในไฟล์ config แยกต่างหากเพื่อความปลอดภัย
$servername = "localhost";
$username = "root";
$password = ""; // รหัสผ่านฐานข้อมูลของคุณ
$dbname = "pixellink"; // ชื่อฐานข้อมูลของคุณ

$condb = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if ($condb->connect_error) {
    // บันทึกข้อผิดพลาดภายใน (ไม่แสดงให้ผู้ใช้เห็นโดยตรง) และแสดงข้อความทั่วไป
    error_log("Database connection failed: " . $condb->connect_error);
    die("เกิดข้อผิดพลาดที่ไม่คาดคิด โปรดลองอีกครั้งในภายหลัง");
}

/**
 * ฟังก์ชันสำหรับบันทึกประวัติการเข้าสู่ระบบลงในตาราง 'logs'
 *
 * @param mysqli $condb อ็อบเจกต์การเชื่อมต่อฐานข้อมูล
 * @param int|null $userId ID ของผู้ใช้ที่ดำเนินการ หรือ null หากไม่มี
 * @param string $action การดำเนินการ (เช่น 'Login', 'Login Attempt Failed')
 * @param string $details รายละเอียดเพิ่มเติมเกี่ยวกับการดำเนินการ
 */
function saveLog(mysqli $condb, ?int $userId, string $action, string $details = '') {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'; // รับ IP Address ของผู้ใช้
    // ตรวจสอบให้แน่ใจว่าตาราง 'logs' มีอยู่จริงก่อนที่จะพยายามบันทึก
    // โดยทั่วไปควรสร้างตารางในฐานข้อมูลไปเลยจะดีกว่า
    $sql = "INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $condb->prepare($sql);

    if ($stmt) {
        // 'i' สำหรับ integer (user_id), 's' สำหรับ string (action, details, ip_address)
        $stmt->bind_param("isss", $userId, $action, $details, $ipAddress);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Failed to prepare log insertion statement: " . $condb->error);
        // สามารถเพิ่มการจัดการข้อผิดพลาดสำหรับผู้ใช้ได้ที่นี่ หากต้องการ
    }
}

// ตั้งค่า HTTP Headers เพื่อเพิ่มความปลอดภัย (ป้องกัน Clickjacking, XSS เป็นต้น)
header("X-Frame-Options: DENY"); // ป้องกันการโหลดหน้าใน iframe (Clickjacking)
header("X-XSS-Protection: 1; mode=block"); // เปิดใช้งานตัวกรอง XSS ของเบราว์เซอร์
header("X-Content-Type-Options: nosniff"); // ป้องกันการเดา MIME-type
header("Referrer-Policy: no-referrer-when-downgrade"); // ควบคุมข้อมูล Referrer

$loginSuccess = false;
$redirectUrl = '';
$errorMsg = '';
$successMsg = '';
$displayCustomMessage = false; // ธงสำหรับควบคุมการแสดงข้อความแบบกำหนดเอง

// ตรวจสอบเมื่อมีการส่งข้อมูลแบบ POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $personal_username = trim($_POST["personal_username"] ?? ''); // รับชื่อผู้ใช้/เบอร์โทรศัพท์ และตัดช่องว่าง
    $personal_password = $_POST["personal_password"] ?? ''; // รับรหัสผ่าน

    // ตรวจสอบเบื้องต้นว่าช่องว่างหรือไม่
    if (empty($personal_username) || empty($personal_password)) {
        $errorMsg = "โปรดกรอกชื่อผู้ใช้และรหัสผ่านของคุณ";
        $displayCustomMessage = true;
        saveLog($condb, null, 'Login Attempt Failed', 'Empty credentials submitted.');
    } else {
        // คำสั่ง SQL เพื่อดึงข้อมูลผู้ใช้จากตาราง 'users' โดยตรง (ไม่ต้อง Join กับ roles)
        $sql_check_auth = "SELECT
                                u.user_id,
                                u.username,
                                u.password,     -- ดึงคอลัมน์ password โดยตรง
                                u.user_type,    -- ดึงคอลัมน์ user_type แทน role_id/role_name
                                u.is_active,
                                u.is_approved,  -- เพิ่มคอลัมน์ is_approved ที่นี่
                                u.first_name,
                                u.last_name
                            FROM users AS u
                            WHERE u.username = ? OR u.phone_number = ?";

        $stmt = $condb->prepare($sql_check_auth);
        if ($stmt === false) {
            error_log("SQL Error preparing auth statement: " . $condb->error);
            $errorMsg = "เกิดข้อผิดพลาดในการตรวจสอบสิทธิ์ โปรดลองอีกครั้ง";
            $displayCustomMessage = true;
        } else {
            $stmt->bind_param("ss", $personal_username, $personal_username);
            $stmt->execute();
            $result_check_auth = $stmt->get_result();

            if ($result_check_auth->num_rows === 1) {
                $userData = $result_check_auth->fetch_assoc();

                // *** คำเตือน: การเปรียบเทียบรหัสผ่านโดยตรง (ไม่ปลอดภัยอย่างยิ่ง) ***
                // วิธีนี้เสี่ยงต่อการถูกเจาะฐานข้อมูล หากฐานข้อมูลถูกบุกรุก
                // รหัสผ่านของผู้ใช้ทั้งหมดจะถูกเปิดเผยเป็นข้อความธรรมดา
                // สำหรับระบบที่ใช้งานจริง ควรใช้ password_hash() และ password_verify() เสมอ
                if ($personal_password === $userData["password"]) {
                    if (!$userData['is_active']) {
                        $errorMsg = "บัญชีผู้ใช้งานถูกระงับ กรุณาติดต่อผู้ดูแลระบบ";
                        $displayCustomMessage = true;
                        saveLog($condb, $userData['user_id'], 'Login Attempt Failed', 'Inactive account: ' . $personal_username);
                    } elseif ($userData['is_approved'] == 0) { // ตรวจสอบ is_approved
                        $errorMsg = "บัญชีผู้ใช้งานยังไม่ได้รับการอนุมัติ กรุณาติดต่อผู้ดูแลระบบ";
                        $displayCustomMessage = true;
                        saveLog($condb, $userData['user_id'], 'Login Attempt Failed', 'Account not approved: ' . $personal_username);
                    } else {
                        // สร้าง Session ID ใหม่เพื่อป้องกัน Session Fixation
                        session_regenerate_id(true);

                        // กำหนดค่าตัวแปร Session
                        $_SESSION["user_id"] = $userData["user_id"];
                        $_SESSION["username"] = $userData["username"];
                        $_SESSION["user_type"] = $userData["user_type"]; // ใช้ user_type แทน role_id/role_name

                        // กำหนดชื่อเต็มสำหรับ Session
                        if (!empty($userData["first_name"]) && !empty($userData["last_name"])) {
                            $_SESSION["full_name"] = $userData["first_name"] . " " . $userData["last_name"];
                        } else {
                            $_SESSION["full_name"] = $userData["username"];
                        }

                        saveLog($condb, $userData["user_id"], 'Login Successful', 'User logged in: ' . $personal_username);

                        // กำหนด URL สำหรับ Redirect ตาม user_type
                        switch ($_SESSION["user_type"]) { // ใช้ user_type ในการ switch
                            case 'admin': // ต้องตรงกับค่าใน enum ของ user_type ในฐานข้อมูล
                                $redirectUrl = "admin/main.php";
                                break;
                            case 'designer': // ต้องตรงกับค่าใน enum ของ user_type ในฐานข้อมูล
                                $redirectUrl = "designer/main.php";
                                break;
                            case 'client': // ต้องตรงกับค่าใน enum ของ user_type ในฐานข้อมูล
                                $redirectUrl = "client/main.php"; // หรือเปลี่ยนเป็น client/main.php ถ้ามี
                                break;
                            default:
                                $redirectUrl = ''; // ไม่มีการ redirect สำหรับ user_type ที่ไม่รู้จัก
                        }
                        if ($redirectUrl !== '') {
                            $loginSuccess = true;
                            $successMsg = "เข้าสู่ระบบสำเร็จ";
                            $displayCustomMessage = true;
                        } else {
                            $errorMsg = "สิทธิ์การใช้งานไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
                            $displayCustomMessage = true;
                            saveLog($condb, $userData['user_id'], 'Login Attempt Failed', 'Invalid user type: ' . $_SESSION["user_type"] . ' for user ' . $personal_username);
                            // ล้าง Session หาก user_type ไม่ถูกต้อง
                            session_unset();
                            session_destroy();
                        }
                    }
                } else {
                    $errorMsg = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                    $displayCustomMessage = true;
                    saveLog($condb, $userData['user_id'] ?? null, 'Login Attempt Failed', 'Incorrect password for: ' . $personal_username);
                }
            } else {
                $errorMsg = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                $displayCustomMessage = true;
                saveLog($condb, null, 'Login Attempt Failed', 'Username not found: ' . $personal_username);
            }
            $stmt->close();
        }
    }
}

$condb->close(); // ปิดการเชื่อมต่อฐานข้อมูล
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PixelLink</title>

    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

    <style>
        body,
        html {
            height: 100%;
            font-family: 'Kanit', sans-serif;
            font-style: normal;
            font-weight: 400;
            background-image: url('dist/img/cover.png');
            /* อัปเดตเส้นทางภาพพื้นหลังที่นี่! */
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-position: center center;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .login-wrapper {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            width: 360px;
            text-align: center;
        }

        .login-header {
            margin-bottom: 24px;
        }

        .login-header img {
            height: 60px;
            margin-bottom: 8px;
        }

        .login-header h2 {
            font-size: 18px;
            color: rgba(0, 0, 0, 0.88);
            margin-top: 8px;
            font-weight: 600;
        }

        .login-header p {
            font-size: 14px;
            margin: 0;
            color: rgba(0, 0, 0, 0.65);
        }

        .login-form .form-group {
            margin-bottom: 16px;
            position: relative;
        }

        .login-form .form-control {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #d9d9d9;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1.5714;
            color: rgba(0, 0, 0, 0.88);
            box-sizing: border-box;
            transition: all 0.2s;
        }

        .login-form .form-control:focus {
            border-color: #4096ff;
            box-shadow: 0 0 0 2px rgba(5, 145, 255, 0.1);
            outline: none;
        }

        .login-form .form-control::placeholder {
            font-family: 'Kanit', sans-serif;
            font-weight: 400;
        }

        .login-form .form-control::-webkit-input-placeholder {
            font-family: 'Kanit', sans-serif;
            font-weight: 400;
        }

        .login-form .form-control::-moz-placeholder {
            font-family: 'Kanit', sans-serif;
            font-weight: 400;
        }

        .login-form .form-control:-ms-input-placeholder {
            font-family: 'Kanit', sans-serif;
            font-weight: 400;
        }

        .form-group .anticon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(0, 0, 0, 0.45);
        }

        .login-form .anticon-eye-invisible,
        .login-form .anticon-eye {
            cursor: pointer;
            left: auto;
            right: 12px;
            z-index: 2;
        }

        .login-form .form-check {
            text-align: left;
            margin-bottom: 16px;
            font-size: 14px;
            color: rgba(0, 0, 0, 0.88);
        }

        .login-form .form-check-input {
            margin-right: 8px;
            border-radius: 2px;
            border: 1px solid #d9d9d9;
        }

        .login-form .login-button {
            width: 100%;
            padding: 8px 15px;
            height: 40px;
            background-color: rgb(0, 12, 72);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-family: 'Kanit', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .login-form .login-button:hover {
            background-color: rgb(0, 5, 74);
        }

        .login-footer {
            margin-top: 16px;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
        }

        .login-footer a {
            color: rgb(0, 23, 128);
            text-decoration: none;
        }

        .login-footer a:hover {
            color: rgb(0, 23, 128);
            text-decoration: underline;
        }

        /* รูปแบบข้อความแสดงผลแบบกำหนดเอง */
        .message-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            display: flex;
            justify-content: center;
            box-sizing: border-box;
            max-width: 90%;
        }

        .message-container.show-message {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
            pointer-events: all;
        }

        .message-wrapper {
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 6px 16px 0 rgba(0, 0, 0, 0.08), 0 3px 6px -4px rgba(0, 0, 0, 0.12), 0 9px 28px 8px rgba(0, 0, 0, 0.05);
            background: #fff;
            display: flex;
            align-items: center;
            width: fit-content;
            text-align: left;
        }

        .message-icon-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .message-icon {
            font-size: 18px;
        }

        .message-text {
            color: rgba(0, 0, 0, 0.88);
            font-size: 14px;
            line-height: 1.5;
            flex-grow: 1;
        }

        /* รูปแบบสำหรับข้อความสำเร็จ */
        .success-type .message-icon {
            color: #52c41a;
        }

        /* รูปแบบสำหรับข้อความผิดพลาด */
        .error-type .message-icon {
            color: #ff4d4f;
        }
    </style>
</head>

<body>

    <div class="login-wrapper">
        <div class="login-header">
            <img src="dist/img/logo.png" alt="SUT Logo" style="height: 60px;" onerror="this.onerror=null; this.src='https://placehold.co/60x60/cccccc/ffffff?text=LOGO';">
            <p style="font-size: 12px; margin: 0; color: rgba(0,0,0,0.65);">Rajamangala University of Technology Isan
            </p>
            <h2 style="font-size: 20px; font-weight: 700; margin-top: 5px;"> ระบบจัดการการจ้างงานนักออกแบบออนไลน์: กรณีศึกษาการจ้างงานด้านกราฟิกออนไลน์
            </h2>
            <p style="font-size: 14px; margin-top: 15px; color: rgba(0,0,0,0.65);">PixelLink co. ltd.
            </p>
        </div>

        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <span class="anticon anticon-user"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" name="personal_username" placeholder="ชื่อผู้ใช้งาน หรือ เบอร์โทรศัพท์" required autofocus />
            </div>
            <div class="form-group">
                <span class="anticon anticon-lock"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="personal_password" name="personal_password" placeholder="รหัสผ่าน" required />
                <span class="anticon anticon-eye-invisible" id="togglePassword">
                    <i class="fas fa-eye-slash"></i>
                </span>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="rememberMe">
                <label class="form-check-label" for="rememberMe">จดจำฉันไว้</label>
            </div>
            <button type="submit" class="login-button">เข้าสู่ระบบ</button>
        </form>

        <div class="login-footer">
            <a href="forgot_password.php">ลืมรหัสผ่าน?</a>
            <a href="register.php">ยังไม่มีบัญชี? สมัครสมาชิก</a>
        </div>

        <div id="custom-success-message-container" class="message-container success-type" style="display: none;">
            <div class="message-wrapper">
                <div class="message-icon-wrapper">
                    <i class="fas fa-check-circle message-icon"></i>
                </div>
                <div class="message-text"></div>
            </div>
        </div>

        <div id="custom-error-message-container" class="message-container error-type" style="display: none;">
            <div class="message-wrapper">
                <div class="message-icon-wrapper">
                    <i class="fas fa-times-circle message-icon"></i>
                </div>
                <div class="message-text"></div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // ฟังก์ชันสลับการมองเห็นรหัสผ่าน
            $('#togglePassword').on('click', function() {
                const passwordField = $('#personal_password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });

            // ฟังก์ชันสำหรับแสดงข้อความแจ้งเตือนแบบกำหนดเอง
            function showCustomMessage(type, text, redirectUrl = '') {
                const containerId = type === 'success' ? '#custom-success-message-container' : '#custom-error-message-container';
                const $container = $(containerId);
                const $messageText = $container.find('.message-text');

                $messageText.text(text);
                $container.css('display', 'flex'); // แสดง container (ใช้ flex เพื่อจัดกึ่งกลาง)

                // กระตุ้น Animation
                setTimeout(() => {
                    $container.addClass('show-message');
                }, 50); // หน่วงเวลาเล็กน้อยเพื่อให้แน่ใจว่า display:flex ถูกนำไปใช้ก่อน transition

                const hideDelay = type === 'success' ? 2500 : 3000; // 2.5 วินาทีสำหรับสำเร็จ, 3 วินาทีสำหรับผิดพลาด
                const transitionDuration = 300; // ระยะเวลา transition ของ CSS สำหรับ opacity/transform

                setTimeout(() => {
                    $container.removeClass('show-message');
                    $container.one('transitionend', function() {
                        $container.hide(); // ซ่อนอย่างสมบูรณ์หลัง transition
                        if (redirectUrl) {
                            window.location.href = redirectUrl; // Redirect หากมี URL กำหนด
                        }
                    });
                }, hideDelay);
            }

            // แสดงข้อความตามผลลัพธ์จาก PHP
            <?php if ($displayCustomMessage) : ?>
                <?php if ($loginSuccess) : ?>
                    showCustomMessage('success', "<?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>", "<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>");
                <?php else : ?>
                    showCustomMessage('error', "<?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>");
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>