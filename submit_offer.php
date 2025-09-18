<?php
session_start();
header('Content-Type: application/json');
require_once 'connect.php';

$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (ส่วนรับข้อมูลเหมือนเดิม) ...
    $client_id = $_SESSION['user_id'];
    $designer_id = filter_input(INPUT_POST, 'designer_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $budget = trim($_POST['budget'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $attachment_path = null;

    // --- [ส่วนที่ปรับแก้] แก้ไข Path การอัปโหลด ---
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        
        // แก้ไข upload_dir ให้ถูกต้อง ชี้จาก root ของโปรเจกต์
        $upload_dir = 'uploads/job_attachments/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // ใช้ 0777 เพื่อให้แน่ใจว่าเขียนไฟล์ได้
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $unique_filename = 'job_' . uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $unique_filename;

        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $attachment_path = $target_file; // path ที่จะเก็บลง DB คือ 'uploads/job_attachments/...'
        } else {
            $response['message'] = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
            echo json_encode($response);
            exit();
        }
    }
    // --- [สิ้นสุดส่วนที่ปรับแก้] ---

    if ($designer_id && !empty($title) && !empty($description) && $category_id && !empty($budget) && !empty($deadline)) {
        try {
            $stmt = $conn->prepare("INSERT INTO client_job_requests (client_id, designer_id, title, description, attachment_path, category_id, budget, deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')");
            $stmt->bind_param("iisssiss", $client_id, $designer_id, $title, $description, $attachment_path, $category_id, $budget, $deadline);

            if ($stmt->execute()) {
                $request_id = $conn->insert_id;
                // ... (ส่วนของการส่งข้อความในแชทเหมือนเดิม) ...
                $message_data = json_encode(['type' => 'job_offer', 'title' => $title, 'request_id' => $request_id]);
                $system_message = 'SYSTEM_JOB_OFFER::' . $message_data;
                
                $msg_stmt = $conn->prepare("INSERT INTO messages (from_user_id, to_user_id, message) VALUES (?, ?, ?)");
                $msg_stmt->bind_param("iis", $client_id, $designer_id, $system_message);
                $msg_stmt->execute();
                $msg_stmt->close();
                
                $response['status'] = 'success';
                $response['message'] = 'ส่งข้อเสนอเรียบร้อยแล้ว';
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['message'] = 'An exception occurred: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>