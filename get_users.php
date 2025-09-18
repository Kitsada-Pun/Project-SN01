<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$current_user_id = $_SESSION['user_id'];

// SQL Query ที่แก้ไขใหม่ ให้ดึงข้อมูลรูปโปรไฟล์มาด้วย
$sql = "
    SELECT 
        u.user_id, 
        u.username, 
        p.profile_picture_url,
        (SELECT COUNT(*) FROM messages m WHERE m.from_user_id = u.user_id AND m.to_user_id = ? AND m.is_read = 0) AS unread_count
    FROM (
        SELECT DISTINCT from_user_id AS user_id FROM messages WHERE to_user_id = ?
        UNION
        SELECT DISTINCT to_user_id AS user_id FROM messages WHERE from_user_id = ?
    ) AS distinct_users
    JOIN users u ON distinct_users.user_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    WHERE u.user_id != ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // Handle error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($users);
?>