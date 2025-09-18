<?php
// get_request_details.php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit();
}

$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
$current_user_id = $_SESSION['user_id'];

if ($request_id > 0) {
    // ---- [ส่วนที่ปรับแก้] ----
    // เพิ่ม cjr.attachment_path เข้าไปใน SELECT statement
    $sql = "SELECT 
                cjr.title, 
                cjr.description, 
                cjr.budget, 
                cjr.deadline,
                cjr.attachment_path,
                jc.category_name,
                u_client.username AS client_username
            FROM client_job_requests cjr
            LEFT JOIN job_categories jc ON cjr.category_id = jc.category_id
            LEFT JOIN users u_client ON cjr.client_id = u_client.user_id
            WHERE cjr.request_id = ? AND (cjr.client_id = ? OR cjr.designer_id = ?)";
    // ---- [สิ้นสุดส่วนที่ปรับแก้] ----
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $request_id, $current_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $details = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'data' => $details]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์เข้าถึง']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Request ID ไม่ถูกต้อง']);
}
$conn->close();
?>