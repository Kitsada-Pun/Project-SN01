<?php
session_start();
header('Content-Type: application/json');
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_type = $_SESSION['user_type'] ?? ''; // ดึง user_type มาจาก session
$to_user_id = isset($_GET['to_user']) ? (int)$_GET['to_user'] : 0;

if ($to_user_id === 0) {
    echo json_encode([]);
    exit();
}

// Mark messages as read
$update_sql = "UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("ii", $to_user_id, $current_user_id);
$update_stmt->execute();
$update_stmt->close();

// Fetch messages
$sql = "SELECT from_user_id, message, timestamp FROM messages 
        WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
        ORDER BY timestamp ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $current_user_id, $to_user_id, $to_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {

    // ---- [ส่วนที่ปรับแก้] ----
    // ตรวจสอบว่าข้อความนี้เป็นข้อความพิเศษจากระบบหรือไม่
    if (strpos($row['message'], 'SYSTEM_JOB_OFFER::') === 0) {
        // แยก Prefix ออกไป เอาเฉพาะส่วนข้อมูล JSON
        $json_data = str_replace('SYSTEM_JOB_OFFER::', '', $row['message']);
        $data = json_decode($json_data, true);
        
        $title = htmlspecialchars($data['title'] ?? '');
        $request_id = (int)($data['request_id'] ?? 0);

        // ตรวจสอบ user_type เพื่อแสดงผลต่างกัน
        if ($current_user_type === 'client') {
            // ถ้าเป็นผู้ว่าจ้าง ให้แสดงลิงก์
            $row['message'] = "คุณได้ส่งคำขอจ้างงาน: '{$title}' <a href='#' class='view-request-details' data-request-id='{$request_id}'>คลิกเพื่อดูรายละเอียด</a>";
        } else if ($current_user_type === 'designer') {
            // ถ้าเป็นนักออกแบบ ให้แสดงข้อความธรรมดา
            $row['message'] = "คุณได้รับคำขอจ้างงานใหม่ กรุณาตรวจสอบในหน้า 'งานของฉัน'";
        } else {
            // กรณีอื่นๆ (เช่น แอดมิน)
            $row['message'] = "[ข้อความอัตโนมัติ: มีการส่งคำของาน]";
        }

    } else {
        // ถ้าเป็นข้อความธรรมดา ให้แปลงอักขระพิเศษเพื่อความปลอดภัย
        $row['message'] = htmlspecialchars($row['message']);
        $row['message'] = nl2br($row['message']); // แปลง \n เป็น <br>
    }
    // ---- [สิ้นสุดส่วนที่ปรับแก้] ----

    $messages[] = [
        'from_user_id' => $row['from_user_id'],
        'message' => $row['message'],
        'time' => date('H:i', strtotime($row['timestamp']))
    ];
}

$stmt->close();
$conn->close();

echo json_encode($messages);
?>