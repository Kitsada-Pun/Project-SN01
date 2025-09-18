<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once 'connect.php';

$request_id = $_GET['request_id'] ?? 0;
$designer_id = $_SESSION['user_id'];

if (!$request_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request ID']);
    exit();
}

// [แก้ไข SQL] เพิ่ม JOIN เพื่อดึงชื่อผู้ว่าจ้าง
$sql = "
    SELECT 
        ja.proposal_text,
        ja.offered_price,
        ja.application_date,
        cjr.title AS job_title,
        CONCAT(u.first_name, ' ', u.last_name) AS client_name
    FROM job_applications ja
    JOIN client_job_requests cjr ON ja.request_id = cjr.request_id
    JOIN users u ON cjr.client_id = u.user_id
    WHERE ja.request_id = ? AND ja.designer_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $designer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'data' => $data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลใบเสนอราคา']);
}

$stmt->close();
$conn->close();
?>