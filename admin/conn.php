<?php
// session_start(); // ลบหรือ Comment บรรทัดนี้ออกไป เพื่อไม่ให้มีการเรียกซ้ำซ้อน

// ข้อมูลการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pixellink";

// สร้างการเชื่อมต่อ
$condb = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}

// *** เพิ่มบรรทัดนี้เพื่อตั้งค่า Charset เป็น utf8mb4 ***
$condb->set_charset("utf8mb4");

?>