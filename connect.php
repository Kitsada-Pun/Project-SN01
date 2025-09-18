<?php
// connect.php

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'pixellink');

// --- สร้างการเชื่อมต่อด้วย mysqli ---
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// --- ตรวจสอบการเชื่อมต่อ ---
if ($conn->connect_error) {
    // หากมีข้อผิดพลาด ให้หยุดการทำงานและแสดงข้อความ
    // error_log() จะช่วยบันทึก error ไว้ดูย้อนหลังใน server log ได้
    error_log("Database connection failed: " . $conn->connect_error);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง");
}

// --- ตั้งค่า charset เป็น utf8mb4 เพื่อรองรับภาษาไทย ---
$conn->set_charset("utf8mb4");

?>