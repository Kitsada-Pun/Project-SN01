<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- Security Check: ตรวจสอบว่าเป็น designer ที่ล็อกอินอยู่หรือไม่ ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

require_once '../connect.php';

// --- ตรวจสอบว่าเป็น POST request และมีข้อมูลครบถ้วนหรือไม่ ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['request_id']) || !isset($_FILES['draft_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง']);
    exit();
}

$designer_id = $_SESSION['user_id'];
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$draft_file = $_FILES['draft_file'];
$message_to_client = $_POST['message_to_client'] ?? ''; // รับข้อความเพิ่มเติม

// --- ตรวจสอบความถูกต้องของไฟล์ที่อัปโหลด ---
if ($draft_file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์']);
    exit();
}

// ตรวจสอบขนาดไฟล์ (ตัวอย่าง: ไม่เกิน 25MB)
$max_file_size = 25 * 1024 * 1024; // 25 MB
if ($draft_file['size'] > $max_file_size) {
    echo json_encode(['status' => 'error', 'message' => 'ขนาดไฟล์ต้องไม่เกิน 25MB']);
    exit();
}

// --- เตรียมการย้ายไฟล์ ---
$upload_dir = '../uploads/work_files/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // สร้างโฟลเดอร์ถ้ายังไม่มี
}

// สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน
$file_extension = pathinfo($draft_file['name'], PATHINFO_EXTENSION);
$original_filename = pathinfo($draft_file['name'], PATHINFO_FILENAME);
$unique_filename = 'draft_' . $request_id . '_' . time() . '.' . $file_extension;
$destination = $upload_dir . $unique_filename;

// --- เริ่ม Transaction ของฐานข้อมูล ---
$conn->begin_transaction();

try {
    // 1. ดึงข้อมูล contract_id และ client_id และตรวจสอบสิทธิ์
    $sql_verify = "SELECT c.contract_id, cjr.client_id FROM contracts c JOIN client_job_requests cjr ON c.request_id = cjr.request_id WHERE c.request_id = ? AND c.designer_id = ? AND cjr.status = 'assigned'";
    $stmt_verify = $conn->prepare($sql_verify);
    $stmt_verify->bind_param("ii", $request_id, $designer_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();

    if ($result_verify->num_rows === 0) {
        throw new Exception("ไม่พบงานที่กำลังดำเนินการ หรือคุณไม่มีสิทธิ์ส่งงานนี้");
    }
    $contract_data = $result_verify->fetch_assoc();
    $contract_id = $contract_data['contract_id'];
    $client_id = $contract_data['client_id'];
    $stmt_verify->close();

    // 2. ย้ายไฟล์ไปยังโฟลเดอร์ปลายทาง
    if (!move_uploaded_file($draft_file['tmp_name'], $destination)) {
        throw new Exception("ไม่สามารถบันทึกไฟล์ได้");
    }

    // 3. บันทึกข้อมูลไฟล์ลงในตาราง uploaded_files
    $file_path_for_db = 'uploads/work_files/' . $unique_filename;
    $file_mime_type = mime_content_type($destination);
    $sql_insert_file = "INSERT INTO uploaded_files (contract_id, uploader_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert_file = $conn->prepare($sql_insert_file);
    $stmt_insert_file->bind_param("iisss", $contract_id, $designer_id, $draft_file['name'], $file_path_for_db, $file_mime_type);
    
    if (!$stmt_insert_file->execute()) {
        throw new Exception("ไม่สามารถบันทึกข้อมูลไฟล์ลงฐานข้อมูลได้");
    }
    $stmt_insert_file->close();

    // 4. อัปเดตสถานะของงาน (client_job_requests) เป็น 'awaiting_final_payment'
    $new_status = 'awaiting_final_payment';
    $sql_update_req = "UPDATE client_job_requests SET status = ? WHERE request_id = ?";
    $stmt_update_req = $conn->prepare($sql_update_req);
    $stmt_update_req->bind_param("si", $new_status, $request_id);
    if (!$stmt_update_req->execute()) {
        throw new Exception("ไม่สามารถอัปเดตสถานะงานได้");
    }
    $stmt_update_req->close();
    
    // 5. ส่งข้อความแจ้งเตือนไปหาผู้ว่าจ้าง
    $final_message = "นักออกแบบได้ส่งมอบงานฉบับร่างแล้ว กรุณาตรวจสอบและดำเนินการชำระเงินส่วนที่เหลือ";
    if (!empty($message_to_client)) {
        $final_message .= "\n\nข้อความจากนักออกแบบ:\n" . $message_to_client;
    }
    $sql_message = "INSERT INTO messages (from_user_id, to_user_id, message) VALUES (?, ?, ?)";
    $stmt_message = $conn->prepare($sql_message);
    $stmt_message->bind_param("iis", $designer_id, $client_id, $final_message);
    $stmt_message->execute();
    $stmt_message->close();

    // --- ถ้าทุกอย่างสำเร็จ ---
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'ส่งมอบงานฉบับร่างเรียบร้อยแล้ว']);
    exit();

} catch (Exception $e) {
    // --- หากเกิดข้อผิดพลาด ---
    $conn->rollback();
    if (isset($destination) && file_exists($destination)) {
        unlink($destination); // ลบไฟล์ที่อาจจะอัปโหลดไปแล้ว
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}