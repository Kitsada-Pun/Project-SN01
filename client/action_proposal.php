<?php
session_start();
require_once '../connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$client_id = $_SESSION['user_id'];
$application_id = $_POST['application_id'] ?? 0;
$request_id = $_POST['request_id'] ?? 0;
$designer_id = $_POST['designer_id'] ?? 0;
$action = $_POST['action'] ?? '';

if (empty($application_id) || empty($request_id) || empty($action) || empty($designer_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

$conn->begin_transaction();

try {
    // Verify that the job request belongs to the client
    $sql_verify = "SELECT status FROM client_job_requests WHERE request_id = ? AND client_id = ?";
    $stmt_verify = $conn->prepare($sql_verify);
    $stmt_verify->bind_param("ii", $request_id, $client_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();

    if ($result_verify->num_rows === 0) {
        throw new Exception('Job request not found or you do not have permission.');
    }
    $job_request = $result_verify->fetch_assoc();
    $stmt_verify->close();

    // Prevent action if the job is not in a state to be accepted/rejected
    if (!in_array($job_request['status'], ['open', 'proposed'])) {
        throw new Exception('This job has already been processed.');
    }

    if ($action === 'accept') {
        // 1. Update the main job request: set status to 'Awaiting Payment' and link the chosen designer
        $sql_update_job = "UPDATE client_job_requests SET status = 'Awaiting Payment', designer_id = ? WHERE request_id = ?";
        $stmt_update_job = $conn->prepare($sql_update_job);
        if (!$stmt_update_job) {
            throw new Exception("Prepare failed (update_job): " . $conn->error);
        }
        $stmt_update_job->bind_param("ii", $designer_id, $request_id);
        if (!$stmt_update_job->execute()) {
            throw new Exception("Error updating job request: " . $stmt_update_job->error);
        }
        $stmt_update_job->close();

        // 2. Update the accepted job_application status to 'accepted'
        $sql_accept_app = "UPDATE job_applications SET status = 'accepted' WHERE application_id = ?";
        $stmt_accept_app = $conn->prepare($sql_accept_app);
        if (!$stmt_accept_app) {
            throw new Exception("Prepare failed (accept_app): " . $conn->error);
        }
        $stmt_accept_app->bind_param("i", $application_id);
        if (!$stmt_accept_app->execute()) {
            throw new Exception("Error accepting application: " . $stmt_accept_app->error);
        }
        $stmt_accept_app->close();

        // 3. Reject all other applications for this job request
        $sql_reject_others = "UPDATE job_applications SET status = 'rejected' WHERE request_id = ? AND application_id != ?";
        $stmt_reject_others = $conn->prepare($sql_reject_others);
        if (!$stmt_reject_others) {
            throw new Exception("Prepare failed (reject_others): " . $conn->error);
        }
        $stmt_reject_others->bind_param("ii", $request_id, $application_id);
        if (!$stmt_reject_others->execute()) {
            throw new Exception("Error rejecting other applications: " . $stmt_reject_others->error);
        }
        $stmt_reject_others->close();
        
        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'ตอบรับข้อเสนอเรียบร้อยแล้ว! กรุณาชำระเงินมัดจำเพื่อเริ่มงาน',
            'redirectUrl' => 'my_requests.php' // Redirect back to my_requests to see the change
        ]);

    } elseif ($action === 'reject') {
        // Update job status to 'cancelled'
        $sql_update_job = "UPDATE client_job_requests SET status = 'cancelled' WHERE request_id = ?";
        $stmt_update_job = $conn->prepare($sql_update_job);
        $stmt_update_job->bind_param("i", $request_id);
        if (!$stmt_update_job->execute()) {
            throw new Exception("Error cancelling job request: " . $stmt_update_job->error);
        }
        $stmt_update_job->close();

        // Reject the specific application
        $sql_reject_app = "UPDATE job_applications SET status = 'rejected' WHERE application_id = ?";
        $stmt_reject_app = $conn->prepare($sql_reject_app);
        $stmt_reject_app->bind_param("i", $application_id);
        if (!$stmt_reject_app->execute()) {
            throw new Exception("Error rejecting application: " . $stmt_reject_app->error);
        }
        $stmt_reject_app->close();

        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'ข้อเสนอถูกปฏิเสธ และงานนี้ถูกย้ายไปที่รายการงานที่ถูกยกเลิกแล้ว',
            'redirectUrl' => 'my_requests.php'
        ]);
        
    } else {
        throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>