<?php
session_start();
header('Content-Type: application/json');

// ตรวจสอบว่าเป็น designer ที่ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

require_once '../connect.php';

$designer_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if ($request_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่ได้ระบุรหัสคำของาน']);
    exit();
}

try {
    // 1. ค้นหา contract_id และตรวจสอบว่า designer เป็นเจ้าของงานนี้จริง
    $sql_contract = "
        SELECT c.contract_id 
        FROM contracts c
        JOIN client_job_requests cjr ON c.request_id = cjr.request_id
        WHERE c.request_id = ? AND cjr.designer_id = ?
    ";
    $stmt_contract = $conn->prepare($sql_contract);
    $stmt_contract->bind_param("ii", $request_id, $designer_id);
    $stmt_contract->execute();
    $result_contract = $stmt_contract->get_result();

    if ($result_contract->num_rows === 0) {
        throw new Exception("ไม่พบสัญญาสำหรับงานนี้ หรือคุณไม่มีสิทธิ์เข้าถึง");
    }
    $contract_data = $result_contract->fetch_assoc();
    $contract_id = $contract_data['contract_id'];
    $stmt_contract->close();

    // 2. ค้นหาไฟล์สลิปที่เกี่ยวข้องกับ contract_id นี้ (เอาไฟล์ล่าสุด)
    // เราอาจจะต้องกำหนด file_type ที่ชัดเจนขึ้นในอนาคต แต่ตอนนี้จะค้นหาจาก path
    $sql_file = "
        SELECT file_path 
        FROM uploaded_files 
        WHERE contract_id = ? AND file_path LIKE 'uploads/payment_slips/%'
        ORDER BY uploaded_at DESC 
        LIMIT 1
    ";
    $stmt_file = $conn->prepare($sql_file);
    $stmt_file->bind_param("i", $contract_id);
    $stmt_file->execute();
    $result_file = $stmt_file->get_result();

    if ($result_file->num_rows > 0) {
        $file_data = $result_file->fetch_assoc();
        // ส่งที่อยู่ไฟล์กลับไปให้ JavaScript
        echo json_encode(['status' => 'success', 'filePath' => '../' . $file_data['file_path']]);
    } else {
        throw new Exception("ไม่พบไฟล์หลักฐานการชำระเงินสำหรับงานนี้");
    }
    $stmt_file->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>