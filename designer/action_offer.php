<?php
session_start();
header('Content-Type: application/json');

require_once '../connect.php';

// ตรวจสอบว่าเป็น designer ที่ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$designer_id = $_SESSION['user_id'];
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

// [แก้ไข] เพิ่ม action ใหม่ๆ ที่เราจะใช้
$allowed_actions = ['reject', 'confirm_deposit', 'submit_work', 'confirm_final_payment'];
if (!$request_id || !in_array($action, $allowed_actions)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง']);
    exit();
}

$conn->begin_transaction();

try {
    // ดึงข้อมูลงานเพื่อตรวจสอบสิทธิ์และความถูกต้องของสถานะ
    $sql_verify = "SELECT status, client_id, title FROM client_job_requests WHERE request_id = ? AND designer_id = ?";
    $stmt_verify = $conn->prepare($sql_verify);
    $stmt_verify->bind_param("ii", $request_id, $designer_id);
    $stmt_verify->execute();
    $result = $stmt_verify->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('ไม่พบข้อเสนองานนี้ หรือคุณไม่มีสิทธิ์จัดการ');
    }
    $job = $result->fetch_assoc();
    $current_status = $job['status'];
    $client_id = $job['client_id'];
    $job_title = $job['title'];
    $stmt_verify->close();

    $new_status = '';
    $message = '';

    switch ($action) {
        case 'reject':
            if ($current_status !== 'open') {
                throw new Exception('ไม่สามารถปฏิเสธงานที่ตอบรับไปแล้วได้');
            }
            $new_status = 'rejected';
            $message = "คุณได้ปฏิเสธข้อเสนองาน '{$job_title}' เรียบร้อยแล้ว";
            break;

        case 'confirm_deposit':
            if ($current_status !== 'awaiting_deposit_verification') {
                throw new Exception('ไม่สามารถยืนยันงานที่ไม่ได้อยู่ในสถานะ "รอตรวจสอบมัดจำ" ได้');
            }
            $new_status = 'assigned'; // เปลี่ยนสถานะเป็น "กำลังดำเนินการ"
            $message = "ยืนยันการชำระเงินมัดจำสำเร็จ! งานได้ย้ายไปที่ 'กำลังดำเนินการ'";
            
            // อัปเดตสถานะสัญญา (Contract) เป็น 'active'
            $sql_update_contract = "UPDATE contracts SET contract_status = 'active' WHERE request_id = ?";
            $stmt_update_contract = $conn->prepare($sql_update_contract);
            $stmt_update_contract->bind_param("i", $request_id);
            $stmt_update_contract->execute();
            $stmt_update_contract->close();
            break;
            
        case 'submit_work':
            if ($current_status !== 'assigned') {
                throw new Exception('ไม่สามารถส่งงานที่ไม่ได้อยู่ในสถานะ "กำลังดำเนินการ" ได้');
            }
            $new_status = 'awaiting_final_payment'; // เปลี่ยนสถานะเป็น "รอชำระเงินส่วนที่เหลือ"
            $message = "ส่งมอบงานเรียบร้อยแล้ว! กรุณารอผู้ว่าจ้างตรวจสอบและชำระเงินส่วนที่เหลือ";
            break;

        case 'confirm_final_payment':
            if ($current_status !== 'awaiting_final_payment') {
                throw new Exception('ไม่สามารถยืนยันงานที่ไม่ได้อยู่ในสถานะ "รอชำระเงินส่วนที่เหลือ" ได้');
            }
            $new_status = 'completed'; // เปลี่ยนสถานะเป็น "เสร็จสมบูรณ์"
            $message = "ยืนยันการชำระเงินงวดสุดท้ายเรียบร้อยแล้ว! งานนี้เสร็จสมบูรณ์";

            // อัปเดตสถานะสัญญา (Contract) เป็น 'completed'
            $sql_update_contract_final = "UPDATE contracts SET contract_status = 'completed', end_date = ? WHERE request_id = ?";
            $end_date = date('Y-m-d');
            $stmt_update_contract_final = $conn->prepare($sql_update_contract_final);
            $stmt_update_contract_final->bind_param("si", $end_date, $request_id);
            $stmt_update_contract_final->execute();
            $stmt_update_contract_final->close();
            break;
    }

    // --- อัปเดตสถานะของงานในฐานข้อมูล ---
    if (!empty($new_status)) {
        $sql_update = "UPDATE client_job_requests SET status = ? WHERE request_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $new_status, $request_id);
        if (!$stmt_update->execute()) {
            throw new Exception("เกิดข้อผิดพลาดในการอัปเดตสถานะงาน");
        }
        $stmt_update->close();
    }
    
    // (ทางเลือก) ส่งข้อความแจ้งเตือนไปหาผู้ว่าจ้าง
    // ... สามารถเพิ่มโค้ดส่งข้อความแจ้งเตือนได้ที่นี่ ...

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => $message]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();