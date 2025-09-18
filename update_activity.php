<?php
session_start();
require 'connect.php';

if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    
    // อัปเดตเวลาล่าสุดของผู้ใช้คนปัจจุบัน
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE user_id = ?");
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['status' => 'success']);
}
$conn->close();
?>