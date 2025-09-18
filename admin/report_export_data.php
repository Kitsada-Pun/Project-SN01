<?php
session_start();

// Enable error reporting for debugging (should be off in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_export_error_log.log'); // Ensure this path is writable by the web server

// Include your database connection file
include 'conn.php'; // Make sure this path is correct and defines $condb

// --- Security Check: Only logged-in Admins can export data ---
// In your schema, user_type is ENUM('admin', 'designer', 'client').
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    // Optionally redirect or show an error
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'คุณไม่มีสิทธิ์ส่งออกข้อมูล!'
    ];
    header('Location: index.php'); // Redirect to login or home page
    exit();
}

// Get the type of data to export from the URL parameter
$export_type = $_GET['type'] ?? 'users'; // Default to 'users' if no type is specified

$filename = "export_" . $export_type . "_" . date('Ymd_His') . ".csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM (Byte Order Mark) to ensure Excel opens CSV with correct encoding
// This helps with Thai characters
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

try {
    switch ($export_type) {
        case 'users':
            // Headers for Users CSV
            fputcsv($output, ['User ID', 'Username', 'Email', 'First Name', 'Last Name', 'Phone Number', 'User Type', 'Registration Date', 'Is Approved', 'Is Active', 'Last Login']);

            // Fetch Users data
            $stmt = $condb->prepare("SELECT user_id, username, email, first_name, last_name, phone_number, user_type, registration_date, is_approved, is_active, last_login FROM users");
            if ($stmt === false) {
                error_log("Prepare failed for users export: " . $condb->error);
                throw new Exception("SQL Prepare Failed for users export: " . $condb->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                // Convert boolean values to human-readable strings
                $row['is_approved'] = $row['is_approved'] ? 'Yes' : 'No';
                $row['is_active'] = $row['is_active'] ? 'Yes' : 'No';
                fputcsv($output, $row);
            }
            $stmt->close();
            break;

        case 'client_job_requests':
            // Headers for Client Job Requests CSV
            fputcsv($output, ['Request ID', 'Client ID', 'Client Username', 'Title', 'Description', 'Category Name', 'Budget', 'Deadline', 'Posted Date', 'Status']);

            // Fetch Client Job Requests data (with join to get client username and category name)
            $stmt = $condb->prepare("
                SELECT
                    cjr.request_id,
                    cjr.client_id,
                    u.username AS client_username,
                    cjr.title,
                    cjr.description,
                    jc.category_name,
                    cjr.budget,
                    cjr.deadline,
                    cjr.posted_date,
                    cjr.status
                FROM client_job_requests cjr
                JOIN users u ON cjr.client_id = u.user_id
                LEFT JOIN job_categories jc ON cjr.category_id = jc.category_id
            ");
            if ($stmt === false) {
                error_log("Prepare failed for client_job_requests export: " . $condb->error);
                throw new Exception("SQL Prepare Failed for client_job_requests export: " . $condb->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            $stmt->close();
            break;

        case 'contracts':
            // Headers for Contracts CSV
            fputcsv($output, ['Contract ID', 'Request ID', 'Designer ID', 'Designer Username', 'Client ID', 'Client Username', 'Agreed Price', 'Start Date', 'End Date', 'Contract Status', 'Payment Status']);

            // Fetch Contracts data (with joins to get designer and client usernames)
            $stmt = $condb->prepare("
                SELECT
                    c.contract_id,
                    c.request_id,
                    c.designer_id,
                    du.username AS designer_username,
                    c.client_id,
                    cu.username AS client_username,
                    c.agreed_price,
                    c.start_date,
                    c.end_date,
                    c.contract_status,
                    c.payment_status
                FROM contracts c
                JOIN users du ON c.designer_id = du.user_id -- Designer User
                JOIN users cu ON c.client_id = cu.user_id   -- Client User
            ");
            if ($stmt === false) {
                error_log("Prepare failed for contracts export: " . $condb->error);
                throw new Exception("SQL Prepare Failed for contracts export: " . $condb->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            $stmt->close();
            break;

        // Add more cases here for other tables like 'job_postings', 'transactions', 'reviews', 'reports'
        /*
        case 'job_postings':
            fputcsv($output, ['Post ID', 'Designer ID', 'Designer Username', 'Title', 'Description', 'Category Name', 'Price Range', 'Posted Date', 'Status']);
            $stmt = $condb->prepare("SELECT jp.post_id, jp.designer_id, u.username AS designer_username, jp.title, jp.description, jc.category_name, jp.price_range, jp.posted_date, jp.status FROM job_postings jp JOIN users u ON jp.designer_id = u.user_id LEFT JOIN job_categories jc ON jp.category_id = jc.category_id");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            $stmt->close();
            break;

        case 'transactions':
            fputcsv($output, ['Transaction ID', 'Contract ID', 'Payer ID', 'Payer Username', 'Payee ID', 'Payee Username', 'Amount', 'Transaction Date', 'Payment Method', 'Status']);
            $stmt = $condb->prepare("SELECT t.transaction_id, t.contract_id, t.payer_id, pu.username AS payer_username, t.payee_id, pyu.username AS payee_username, t.amount, t.transaction_date, t.payment_method, t.status FROM transactions t JOIN users pu ON t.payer_id = pu.user_id JOIN users pyu ON t.payee_id = pyu.user_id");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            $stmt->close();
            break;
        */

        default:
            // Handle unknown export types
            error_log("Unknown export type requested: " . $export_type);
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'ประเภทการส่งออกข้อมูลไม่ถูกต้อง!'
            ];
            // Close output and redirect as it's an error scenario
            fclose($output);
            header('Location: report.php');
            exit();
    }

} catch (Exception $e) {
    // Log the error and inform the user without exposing sensitive details
    error_log("Data export failed for type " . $export_type . ": " . $e->getMessage());
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'เกิดข้อผิดพลาดในการส่งออกข้อมูล: ' . htmlspecialchars($e->getMessage()) . '<br>โปรดตรวจสอบไฟล์ log สำหรับรายละเอียดเพิ่มเติม.'
    ];
    // Close output and redirect to prevent partially downloaded corrupt files
    fclose($output);
    header('Location: report.php'); // Redirect back to the report page
    exit();
} finally {
    // Ensure the file pointer is closed
    if (is_resource($output)) {
        fclose($output);
    }
    // Close the database connection
    if ($condb && $condb->ping()) {
        $condb->close();
    }
}
exit(); // Ensure no further output after CSV is sent
?>