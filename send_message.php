<?php
session_start();
require_once 'connect.php'; // ใช้ require_once เหมือนกัน

// ตั้งค่า header ให้ response เป็น JSON
header('Content-Type: application/json');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

// ตรวจสอบว่าข้อมูลถูกส่งมาแบบ POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$from_user_id = $_SESSION['user_id'];
$to_user_id = isset($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// ตรวจสอบว่าข้อมูลที่จำเป็นครบถ้วนหรือไม่
if (empty($to_user_id) || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing recipient or message.']);
    exit();
}

// บันทึกข้อความลงฐานข้อมูล
$stmt = $conn->prepare("INSERT INTO messages (from_user_id, to_user_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $from_user_id, $to_user_id, $message);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    // error_log("Send message failed: " . $stmt->error); // สำหรับ debug
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message.']);
}

$stmt->close();
$conn->close();

?>