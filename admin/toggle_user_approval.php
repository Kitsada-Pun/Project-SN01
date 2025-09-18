<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging (should be off in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_log.log');

include 'conn.php'; // Database connection

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    $response['message'] = 'คุณไม่มีสิทธิ์ดำเนินการนี้!';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'userId', FILTER_VALIDATE_INT);
    $isApproved = filter_input(INPUT_POST, 'isApproved', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    if ($userId === null || $userId === false || $isApproved === null) {
        $response['message'] = 'ข้อมูลไม่ถูกต้อง!';
        echo json_encode($response);
        exit();
    }

    // Optional: Prevent changing approval for current admin or other admins
    // This logic should match what's in manage_users.php for consistency
    if ($userId == $_SESSION['user_id']) {
        $response['message'] = 'ไม่สามารถแก้ไขสถานะอนุมัติของบัญชีผู้ใช้ของคุณเองได้!';
        echo json_encode($response);
        exit();
    }

    // Additional check: Prevent admin from changing approval of another admin (if desired)
    $stmt_check_type = $condb->prepare("SELECT user_type FROM users WHERE user_id = ?");
    if ($stmt_check_type) {
        $stmt_check_type->bind_param("i", $userId);
        $stmt_check_type->execute();
        $result_check_type = $stmt_check_type->get_result();
        $userToUpdateData = $result_check_type->fetch_assoc();
        $stmt_check_type->close();

        if ($userToUpdateData && $userToUpdateData['user_type'] == 'admin') {
            $response['message'] = 'ไม่สามารถแก้ไขสถานะอนุมัติของผู้ดูแลระบบท่านอื่นได้!';
            echo json_encode($response);
            exit();
        }
    }


    $sql = "UPDATE users SET is_approved = ? WHERE user_id = ?";
    $stmt = $condb->prepare($sql);

    if ($stmt === false) {
        error_log("toggle_user_approval.php: Error preparing statement: " . $condb->error);
        $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมการอัปเดตข้อมูล.';
        echo json_encode($response);
        exit();
    }

    // Convert boolean to integer (1 for true, 0 for false) for MySQL TINYINT
    $isApprovedInt = $isApproved ? 1 : 0;
    $stmt->bind_param("ii", $isApprovedInt, $userId);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'อัปเดตสถานะอนุมัติสำเร็จ!';
    } else {
        error_log("toggle_user_approval.php: Error executing statement for UserID " . $userId . ": " . $stmt->error);
        $response['message'] = 'เกิดข้อผิดพลาดในการอัปเดตฐานข้อมูล: ' . htmlspecialchars($stmt->error);
    }

    $stmt->close();
} else {
    $response['message'] = 'Invalid request method.';
}

if ($condb && $condb->ping()) {
    $condb->close();
}

echo json_encode($response);
?>