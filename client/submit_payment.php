<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- Security Check: ตรวจสอบว่าผู้ใช้ล็อกอินในฐานะ 'client' หรือไม่ ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    // ส่งกลับไปหน้า login หากยังไม่ได้เข้าระบบ
    header("Location: ../login.php");
    exit();
}

require_once '../connect.php';

// --- ตรวจสอบว่าเป็น POST request และมีข้อมูลครบถ้วนหรือไม่ ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['request_id']) || !isset($_FILES['payment_slip'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง'];
    header('Location: my_requests.php');
    exit();
}

$client_id = $_SESSION['user_id'];
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$payment_slip = $_FILES['payment_slip'];

// --- ตรวจสอบความถูกต้องของไฟล์ที่อัปโหลด ---
if ($payment_slip['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'];
    header('Location: payment.php?request_id=' . $request_id);
    exit();
}

// ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
$max_file_size = 5 * 1024 * 1024; // 5 MB
if ($payment_slip['size'] > $max_file_size) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ขนาดไฟล์ต้องไม่เกิน 5MB'];
    header('Location: payment.php?request_id=' . $request_id);
    exit();
}

// ตรวจสอบประเภทไฟล์ (เฉพาะรูปภาพ)
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/jpg'];
$file_mime_type = mime_content_type($payment_slip['tmp_name']);
if (!in_array($file_mime_type, $allowed_mime_types)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'กรุณาอัปโหลดไฟล์รูปภาพ (JPG หรือ PNG) เท่านั้น'];
    header('Location: payment.php?request_id=' . $request_id);
    exit();
}


// --- เตรียมการย้ายไฟล์ ---
$upload_dir = '../uploads/payment_slips/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // สร้างโฟลเดอร์ถ้ายังไม่มี
}

// สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน
$file_extension = pathinfo($payment_slip['name'], PATHINFO_EXTENSION);
$unique_filename = 'slip_' . $request_id . '_' . time() . '.' . $file_extension;
$destination = $upload_dir . $unique_filename;

// --- เริ่ม Transaction ของฐานข้อมูล ---
$conn->begin_transaction();

try {
    // 1. ดึงข้อมูล contract_id และ designer_id จาก request_id
    $sql_contract = "SELECT contract_id, designer_id FROM contracts WHERE request_id = ? AND client_id = ?";
    $stmt_contract = $conn->prepare($sql_contract);
    $stmt_contract->bind_param("ii", $request_id, $client_id);
    $stmt_contract->execute();
    $result_contract = $stmt_contract->get_result();

    if ($result_contract->num_rows === 0) {
        throw new Exception("ไม่พบสัญญาสำหรับงานนี้");
    }
    $contract_data = $result_contract->fetch_assoc();
    $contract_id = $contract_data['contract_id'];
    $designer_id = $contract_data['designer_id'];
    $stmt_contract->close();

    // 2. ย้ายไฟล์ไปยังโฟลเดอร์ปลายทาง
    if (!move_uploaded_file($payment_slip['tmp_name'], $destination)) {
        throw new Exception("ไม่สามารถบันทึกไฟล์ได้");
    }

    // 3. บันทึกข้อมูลไฟล์ลงในตาราง uploaded_files
    $file_path_for_db = 'uploads/payment_slips/' . $unique_filename; // ตัด ../ ออกเพื่อเก็บใน DB
    $sql_insert_file = "INSERT INTO uploaded_files (contract_id, uploader_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert_file = $conn->prepare($sql_insert_file);
    $stmt_insert_file->bind_param("iisss", $contract_id, $client_id, $unique_filename, $file_path_for_db, $file_mime_type);
    
    if (!$stmt_insert_file->execute()) {
        throw new Exception("ไม่สามารถบันทึกข้อมูลไฟล์ลงฐานข้อมูลได้: " . $stmt_insert_file->error);
    }
    $stmt_insert_file->close();
    
    // 4. (ขั้นสูง) ส่งข้อความแจ้งเตือนไปให้นักออกแบบ
    $message_text = "ผู้ว่าจ้างได้ส่งหลักฐานการชำระเงินสำหรับงาน Request ID: #{$request_id} แล้ว กรุณาตรวจสอบและยืนยันเพื่อเริ่มงาน";
    $sql_message = "INSERT INTO messages (from_user_id, to_user_id, message) VALUES (?, ?, ?)";
    $stmt_message = $conn->prepare($sql_message);
    // from_user_id อาจจะเป็น 0 หรือ ID ของระบบ แต่ในที่นี้ใช้ client_id ไปก่อนได้
    $stmt_message->bind_param("iis", $client_id, $designer_id, $message_text);
    $stmt_message->execute();
    $stmt_message->close();

    // --- ถ้าทุกอย่างสำเร็จ ---
    $conn->commit();
    $_SESSION['message'] = ['type' => 'success', 'text' => 'ส่งหลักฐานการชำระเงินเรียบร้อยแล้ว! กรุณารอการตรวจสอบจากนักออกแบบ'];
    header('Location: my_requests.php');
    exit();

} catch (Exception $e) {
    // --- หากเกิดข้อผิดพลาด ---
    $conn->rollback(); // ย้อนกลับการกระทำในฐานข้อมูลทั้งหมด
    
    // ลบไฟล์ที่อาจจะอัปโหลดไปแล้ว
    if (file_exists($destination)) {
        unlink($destination);
    }
    
    $_SESSION['message'] = ['type' => 'error', 'text' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    header('Location: payment.php?request_id=' . $request_id);
    exit();
}
?>