<?php
session_start(); // เริ่มต้นเซสชัน

// เคลียร์ตัวแปรเซสชันทั้งหมด
$_SESSION = array(); // ล้างข้อมูลที่เก็บใน $_SESSION

// ถ้ามีการใช้คุกกี้เซสชันด้วย (ซึ่งเป็นค่าเริ่มต้นของ PHP)
// การทำลายคุกกี้เซสชันจะทำให้เซสชันนั้นไม่สามารถถูกกู้คืนได้อีก
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลายเซสชัน
session_destroy();

// เปลี่ยนเส้นทางไปยังหน้า index.php
header("Location: index.php");
exit(); // สำคัญ: ต้องใส่ exit() เพื่อหยุดการทำงานของสคริปต์หลังจาก header redirect
?>