<?php
// ไฟล์นี้จะถูก include ไปใช้ในหน้าอื่น
// ดังนั้นจึงสันนิษฐานว่า session_start() และการเชื่อมต่อ DB ($condb) ได้ถูกเรียกใช้ก่อนหน้านี้แล้ว

// กำหนด Base URL ของโปรเจกต์ (สำคัญมาก!)
// ให้เปลี่ยน '/pixellink-assistants-main' เป็นชื่อโฟลเดอร์โปรเจกต์ของคุณ
define('BASE_URL', '/pixellink-assistants-main');

$loggedInUserName = 'Guest'; // ตั้งค่าเริ่มต้นเผื่อกรณีไม่พบข้อมูล

// ตรวจสอบว่ามี session user_id หรือไม่
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // เตรียมคำสั่ง SQL เพื่อดึงชื่อผู้ใช้
    $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
    $stmt_user = $condb->prepare($sql_user);

    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();

        if ($result_user->num_rows === 1) {
            $user_info = $result_user->fetch_assoc();
            // ใช้ htmlspecialchars เพื่อความปลอดภัยตั้งแต่ตอนดึงข้อมูล
            $loggedInUserName = htmlspecialchars($user_info['first_name']) . ' ' . htmlspecialchars($user_info['last_name']);
        }
        $stmt_user->close();
    }
}
?>

<nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
    <div class="container mx-auto flex justify-between items-center">
        <a href="<?= BASE_URL ?>/main.php">
            <img src="<?= BASE_URL ?>/dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
        </a>
        <div class="space-x-4 flex items-center">
            <span class="font-medium text-slate-700">สวัสดี, <?= $loggedInUserName ?>!</span>
            <a href="<?= BASE_URL ?>/view_profile.php?user_id=<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '' ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
            <a href="<?= BASE_URL ?>/logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
        </div>
    </div>
</nav>