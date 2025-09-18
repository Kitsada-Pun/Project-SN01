<?php
session_start();

// Enable error reporting for debugging (should be off in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_log.log'); // Ensure this path is writable by the web server

// Include your database connection file
include 'conn.php'; // Using $condb from conn.php

// --- Security Check: Only logged-in Admins can access reports ---
// Assuming 'admin' user_type has access. Adjust as per your 'users' table structure.
// In your schema, user_type is ENUM('admin', 'designer', 'client').
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้!'
    ];
    header('Location: index.php'); // Redirect to login or home page
    exit();
}

// --- Initialize Data Arrays for Charts ---
$job_postings_status_counts = [];
$client_requests_status_counts = [];
$application_status_counts = [];
$contract_status_counts = [];
$payment_status_counts = [];
$total_users_by_type = [];
$reviews_rating_distribution = [
    '1.0' => 0,
    '1.5' => 0,
    '2.0' => 0,
    '2.5' => 0,
    '3.0' => 0,
    '3.5' => 0,
    '4.0' => 0,
    '4.5' => 0,
    '5.0' => 0
];
$total_transactions = 0;
$total_contracts_value = 0.00;
// New variable to hold count of active job postings
$active_job_postings_count = 0;

try {
    // --- 1. Job Postings by Status (Designer's offers) ---
    $stmt = $condb->prepare("SELECT status, COUNT(*) AS count FROM job_postings GROUP BY status");
    if ($stmt === false) {
        error_log("Prepare failed for job_postings status: " . $condb->error);
        throw new Exception("SQL Prepare Failed: job_postings status - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $job_postings_status_counts[$row['status']] = $row['count'];
    }
    $stmt->close();

    // --- Retrieve the count of 'active' job_postings for the summary card ---
    $stmt = $condb->prepare("SELECT COUNT(*) AS count FROM job_postings WHERE status = 'active'");
    if ($stmt === false) {
        error_log("Prepare failed for active job_postings: " . $condb->error);
        throw new Exception("SQL Prepare Failed: active job_postings - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $active_job_postings_count = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();

    // --- 2. Client Job Requests by Status ---
    $stmt = $condb->prepare("SELECT status, COUNT(*) AS count FROM client_job_requests GROUP BY status");
    if ($stmt === false) {
        error_log("Prepare failed for client_job_requests status: " . $condb->error);
        throw new Exception("SQL Prepare Failed: client_job_requests status - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $client_requests_status_counts[$row['status']] = $row['count'];
    }
    $stmt->close();

    // --- 3. Job Applications by Status ---
    $stmt = $condb->prepare("SELECT status, COUNT(*) AS count FROM job_applications GROUP BY status");
    if ($stmt === false) {
        error_log("Prepare failed for job_applications status: " . $condb->error);
        throw new Exception("SQL Prepare Failed: job_applications status - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $application_status_counts[$row['status']] = $row['count'];
    }
    $stmt->close();

    // --- 4. Contracts by Status ---
    $stmt = $condb->prepare("SELECT contract_status, COUNT(*) AS count FROM contracts GROUP BY contract_status");
    if ($stmt === false) {
        error_log("Prepare failed for contract_status: " . $condb->error);
        throw new Exception("SQL Prepare Failed: contract_status - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $contract_status_counts[$row['contract_status']] = $row['count'];
    }
    $stmt->close();

    // --- 5. Contracts by Payment Status ---
    $stmt = $condb->prepare("SELECT payment_status, COUNT(*) AS count FROM contracts GROUP BY payment_status");
    if ($stmt === false) {
        error_log("Prepare failed for payment_status: " . $condb->error);
        throw new Exception("SQL Prepare Failed: payment_status - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $payment_status_counts[$row['payment_status']] = $row['count'];
    }
    $stmt->close();

    // --- 6. Total Users by Type ---
    $stmt = $condb->prepare("SELECT user_type, COUNT(*) AS count FROM users GROUP BY user_type");
    if ($stmt === false) {
        error_log("Prepare failed for user_type: " . $condb->error);
        throw new Exception("SQL Prepare Failed: user_type - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $total_users_by_type[$row['user_type']] = $row['count'];
    }
    $stmt->close();

    // --- 7. Reviews Rating Distribution ---
    $stmt = $condb->prepare("SELECT rating, COUNT(*) AS count FROM reviews GROUP BY rating ORDER BY rating ASC");
    if ($stmt === false) {
        error_log("Prepare failed for reviews rating: " . $condb->error);
        throw new Exception("SQL Prepare Failed: reviews rating - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reviews_rating_distribution[number_format((float)$row['rating'], 1)] = $row['count'];
    }
    $stmt->close();

    // --- 8. Total Transactions (Count) ---
    $stmt = $condb->prepare("SELECT COUNT(*) AS total FROM transactions");
    if ($stmt === false) {
        error_log("Prepare failed for total_transactions: " . $condb->error);
        throw new Exception("SQL Prepare Failed: total_transactions - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $total_transactions = $result->fetch_assoc()['total'];
    $stmt->close();

    // --- 9. Total Value of Completed Contracts ---
    $stmt = $condb->prepare("SELECT SUM(agreed_price) AS total_value FROM contracts WHERE contract_status = 'completed'");
    if ($stmt === false) {
        error_log("Prepare failed for total_contracts_value: " . $condb->error);
        throw new Exception("SQL Prepare Failed: total_contracts_value - " . $condb->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $total_contracts_value = $result->fetch_assoc()['total_value'] ?? 0.00;
    $stmt->close();
} catch (Exception $e) {
    error_log("Report Page: Error fetching summary data (main block): " . $e->getMessage());
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'เกิดข้อผิดพลาดในการดึงข้อมูลสรุป: ' . htmlspecialchars($e->getMessage()) . '<br>โปรดตรวจสอบไฟล์ log สำหรับรายละเอียดเพิ่มเติม.'
    ];
} finally {
    // Close database connection
    if ($condb && $condb->ping()) {
        $condb->close();
    }
}

// --- Include Header, Navbar, Sidebar ---
include 'header.php';
include 'navbar.php';
include 'sidebar_menu.php';

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานและแดชบอร์ด - ระบบจัดการงานออกแบบ</title>
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Global Font */
        body,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .navbar-nav,
        .content-wrapper,
        .main-sidebar,
        .btn,
        table,
        label,
        .form-control {
            font-family: 'Kanit', sans-serif !important;
            font-weight: 400;
        }

        /* AdminLTE Layout Overrides */
        .content-wrapper {
            padding-top: 20px;
            padding-left: 20px;
            padding-right: 20px;
            padding-bottom: 20px;
            min-height: calc(100vh - (var(--main-header-height, 60px) + var(--main-footer-height, 57px)));
            display: flex;
            flex-direction: column;
        }

        .content {
            flex-grow: 1;
        }

        html,
        body {
            height: 100%;
        }

        .wrapper {
            min-height: 100%;
        }

        /* Ant Design-like Message Container (Top-Right Fixed) */
        .ant-message-top.css-qnu6hi {
            position: fixed;
            top: 65px;
            right: 24px;
            left: auto;
            transform: none;
            width: auto;
            max-width: 400px;
            min-width: 250px;
            z-index: 10000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            display: none;
            /* Hidden by default */
        }

        .ant-message-top.css-qnu6hi.show {
            opacity: 1;
            pointer-events: auto;
        }

        /* Ant Design Message Styles */
        .ant-message-notice-wrapper {
            overflow: hidden;
            margin-bottom: 16px;
        }

        .ant-message-notice {
            padding: 8px 16px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
            display: block;
            pointer-events: auto;
            text-align: left;
        }

        .ant-message-custom-content {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ant-message-success .anticon-msg {
            color: #52c41a;
        }

        .ant-message-error .anticon-msg {
            color: #ff4d4f;
        }

        .anticon-msg svg {
            width: 1em;
            height: 1em;
            vertical-align: -0.125em;
        }

        /* Content Container for Table and Controls (The "Frame") */
        .content-container {
            padding: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: rgba(0, 0, 0, 0.1) 0px 0px 10px;
        }

        /* Custom Styles for Summary Cards to mimic Banna Cafe */
        .summary-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            /* Adjusted min-width for larger cards */
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background-color: #fff;
            /* White background */
            border: none;
            /* No border */
            border-radius: 8px;
            /* Slightly rounded corners */
            padding: 20px 24px;
            /* More padding */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.05);
            /* Softer shadow */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 120px;
            /* Fixed height for consistency */
        }

        .summary-card h3 {
            margin-top: 0;
            color: #888;
            /* Lighter color for label */
            font-size: 0.9em;
            /* Smaller font size for label */
            font-weight: 500;
        }

        .summary-card p {
            font-size: 2.2em;
            /* Larger font size for value */
            font-weight: 700;
            /* Bolder value */
            color: #333;
            /* Darker color for value */
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-card p i {
            font-size: 0.8em;
            /* Smaller icon size relative to number */
        }

        /* Specific colors for trend icons */
        .summary-card .trend-up {
            color: #52c41a;
        }

        /* Green for increase */
        .summary-card .trend-down {
            color: #ff4d4f;
        }

        /* Red for decrease */
        .summary-card .neutral {
            color: #888;
        }

        /* Grey for no significant change */

        /* Button Styles (Modern/Ant Design-like) */
        .btn-modern {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 8px 16px;
            /* Slightly larger padding */
            border-radius: 6px;
            /* More rounded */
            font-size: 14px;
            font-weight: 500;
            /* Medium weight */
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s cubic-bezier(0.645, 0.045, 0.355, 1);
            text-decoration: none;
        }

        .btn-primary-modern {
            color: #fff;
            background-color: #1890ff;
            border-color: #1890ff;
            box-shadow: 0 2px 0 rgba(0, 0, 0, 0.045);
        }

        .btn-primary-modern:hover {
            color: #fff;
            background-color: #40a9ff;
            border-color: #40a9ff;
        }

        .btn-default-modern {
            color: rgba(0, 0, 0, 0.85);
            background-color: #fff;
            border-color: #d9d9d9;
            box-shadow: 0 2px 0 rgba(0, 0, 0, 0.015);
        }

        .btn-default-modern:hover {
            color: #40a9ff;
            background-color: #fff;
            border-color: #40a9ff;
        }

        /* General layout adjustments to match the image's clean feel */
        .content-header {
            margin-bottom: 20px;
            /* Space below header */
        }

        .ant-divider {
            /* Simplified, could be a simple hr tag with custom style */
            border-top: 1px solid #f0f0f0;
            margin: 24px 0;
        }

        /* Chart specific styles */
        .chart-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
            height: 400px;
            /* Fixed height for consistency */
            display: flex;
            flex-direction: column;
        }

        .chart-container h5 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-weight: 600;
        }

        .chart-canvas-wrapper {
            flex-grow: 1;
            position: relative;
            /* Needed for Chart.js responsiveness */
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">

        <div id="ant-error-message-container" class="ant-message ant-message-top css-qnu6hi">
            <div class="ant-message-notice-wrapper">
                <div class="ant-message-notice ant-message-notice-error">
                    <div class="ant-message-notice-content">
                        <div class="ant-message-custom-content ant-message-error">
                            <span role="img" aria-label="close-circle" class="anticon-msg anticon-close-circle">
                                <svg fill-rule="evenodd" viewBox="64 64 896 896" focusable="false" data-icon="close-circle" width="1em" height="1em" fill="currentColor" aria-hidden="true">
                                    <path d="M512 64c247.4 0 448 200.6 448 448S759.4 960 512 960 64 759.4 64 512 264.6 64 512 64zm127.98 274.82h-.04l-.08.06L512 466.75 384.14 338.88c-.04-.05-.06-.06-.08-.06a.12.12 0 00-.07 0c-.03 0-.05.01-.09.05l-45.02 45.02a.2.2 0 00-.05.09.12.12 0 000 .07v.02a.27.27 0 00.06.06L466.75 512 338.88 639.86c-.05.04-.06.06-.06.08a.12.12 0 000 .07c0 .03.01.05.05.09l45.02 45.02a.2.2 0 00.09.05.12.12 0 00.07 0c.02 0 .04-.01.08-.05L512 557.25l127.86 127.87c.04.04.06.05.08.05a.12.12 0 00.07 0c.03 0 .05-.01.05-.09l45.02-45.02a.2.2 0 00.05-.09.12.12 0 000-.07v-.02a.27.27 0 00-.05-.06L557.25 512l127.87-127.86c.04-.04.05-.06.05-.08a.12.12 0 000-.07c0-.03-.01-.05-.05-.09l-45.02-45.02a.2.2 0 00-.09-.05.12.12 0 00-.07 0z"></path>
                                </svg>
                            </span>
                            <span id="ant-error-message-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="ant-success-message-container" class="ant-message ant-message-top css-qnu6hi">
            <div class="ant-message-notice-wrapper">
                <div class="ant-message-notice ant-message-notice-success">
                    <div class="ant-message-notice-content">
                        <div class="ant-message-custom-content ant-message-success">
                            <span role="img" aria-label="check-circle" class="anticon-msg anticon-check-circle">
                                <svg fill-rule="evenodd" viewBox="64 64 896 896" focusable="false" data-icon="check-circle" width="1em" height="1em" fill="currentColor" aria-hidden="true">
                                    <path d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64zm193.5 301.2l-1.4 1.6-141.2 141.6L363.6 653.2l-1.4 1.6a.2.2 0 00-.08.12c-.01.02-.02.04-.03.05-.03.04-.04.06-.07.1-.01.02-.02.03-.03.05a.15.15 0 00-.03.06L309.8 680a.2.2 0 00.08.2c.01.02.03.03.05.05.02.01.04.03.06.04.03.02.05.04.08.05a.15.15 0 00.06.03L310 680.5l45.9 45.9a.2.2 0 00.09.05c.03 0 .05-.01.09-.05l150-150 206.6-206.6a.2.2 0 00.05-.09c0-.03-.01-.05-.05-.09l-45.9-45.9a.2.2 0 00-.09-.05c-.03 0-.05.01-.05-.09l-150 150z"></path>
                                </svg>
                            </span>
                            <span id="ant-success-message-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <main class="content">
                <div class="content-container">
                    <div class="row align-items-center mb-3">
                        <div class="col-md-6">
                            <h4>รายงานและสถิติภาพรวม</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <button class="btn-modern btn-primary-modern" onclick="window.print();">
                                <i class="fas fa-print"></i> พิมพ์รายงาน
                            </button>
                            <a href="report_export_data.php" class="btn-modern btn-default-modern">
                                <i class="fas fa-file-excel"></i> ส่งออกข้อมูลทั้งหมด
                            </a>
                        </div>
                    </div>
                    <hr class="ant-divider">
                    <div class="summary-cards-grid">
                        <div class="summary-card">
                            <h3>ผู้ใช้งานทั้งหมด</h3>
                            <p><?php echo number_format(array_sum($total_users_by_type)); ?> <i class="fas fa-users neutral"></i></p>
                        </div>
                        <div class="summary-card">
                            <h3>มูลค่าสัญญางานที่สำเร็จ</h3>
                            <p><?php echo number_format($total_contracts_value, 2); ?> บาท <i class="fas fa-baht-sign trend-up"></i></p>
                        </div>
                        <div class="summary-card">
                            <h3>จำนวนการทำธุรกรรม</h3>
                            <p><?php echo number_format($total_transactions); ?> ครั้ง <i class="fas fa-exchange-alt neutral"></i></p>
                        </div>
                        <div class="summary-card">
                            <h3>งานประกาศที่กำลังเปิด</h3>
                            <p><?php echo number_format($active_job_postings_count); ?> รายการ <i class="fas fa-bullhorn trend-up"></i></p>
                        </div>
                    </div>

                    <hr class="ant-divider">

                    <div class="row">
                        <div class="col-lg-6 col-md-12">
                            <div class="chart-container">
                                <h5>สถานะการประกาศรับงานของนักออกแบบ</h5>
                                <div class="chart-canvas-wrapper">
                                    <canvas id="jobPostingStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <div class="chart-container">
                                <h5>สถานะคำของานของผู้ว่าจ้าง</h5>
                                <div class="chart-canvas-wrapper">
                                    <canvas id="clientRequestStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <div class="chart-container">
                                <h5>สถานะการสมัคร/เสนอราคา</h5>
                                <div class="chart-canvas-wrapper">
                                    <canvas id="applicationStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <div class="chart-container">
                                <h5>สถานะสัญญาจ้างงาน</h5>
                                <div class="chart-canvas-wrapper">
                                    <canvas id="contractStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <div class="chart-container">
                                <h5>สถานะการชำระเงินของสัญญา</h5>
                                <div class="chart-canvas-wrapper">
                                    <canvas id="paymentStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <div class="chart-container">
                                <h5>จำนวนผู้ใช้งานตามประเภท</h5>
                                <div class="chart-canvas-wrapper">
                                    <canvas id="userTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-12 col-md-12">
                            <div class="chart-container" style="height: 450px;">
                                <h5>การกระจายคะแนนรีวิว</h5>
                                <div class="chart-canvas-wrapper">
                                    <canvas id="reviewRatingChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>

        <?php include 'footer.php'; ?>
    </div>

    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

    <script>
        $(document).ready(function() {
            // Function to display Ant Design-like messages
            function showAntMessage(type, messageText, delay = 3000) {
                let containerId = `#ant-${type}-message-container`;
                let textId = `#ant-${type}-message-text`;

                $(textId).html(messageText); // Use .html() to allow <br> tags
                $(containerId).css('display', 'block').addClass('show');

                setTimeout(function() {
                    $(containerId).removeClass('show');
                    setTimeout(function() {
                        $(containerId).css('display', 'none');
                    }, 300);
                }, delay);
            }

            // Check for session messages on page load and display them
            <?php
            if (isset($_SESSION['message'])) {
                $msg_type = $_SESSION['message']['type'];
                $msg_text = $_SESSION['message']['text'];
                unset($_SESSION['message']); // Clear the session message after display
            ?>
                showAntMessage('<?php echo $msg_type; ?>', `<?php echo addslashes($msg_text); ?>`);
            <?php
            }
            ?>

            // --- Data from PHP for Charts ---
            const jobPostingStatusData = <?php echo json_encode($job_postings_status_counts); ?>;
            const clientRequestStatusData = <?php echo json_encode($client_requests_status_counts); ?>;
            const applicationStatusData = <?php echo json_encode($application_status_counts); ?>;
            const contractStatusData = <?php echo json_encode($contract_status_counts); ?>;
            const paymentStatusData = <?php echo json_encode($payment_status_counts); ?>;
            const userTypeData = <?php echo json_encode($total_users_by_type); ?>;
            const reviewRatingData = <?php echo json_encode($reviews_rating_distribution); ?>;

            // --- Chart Utility Function ---
            function createChart(ctx, type, labels, data, title, backgroundColor) {
                new Chart(ctx, {
                    type: type,
                    data: {
                        labels: labels,
                        datasets: [{
                            label: title,
                            data: data,
                            backgroundColor: backgroundColor || [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                                'rgba(255, 159, 64, 0.7)',
                                'rgba(199, 199, 199, 0.7)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)',
                                'rgba(255, 159, 64, 1)',
                                'rgba(199, 199, 199, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Allows flexible height based on container
                        plugins: {
                            legend: {
                                position: type === 'doughnut' || type === 'pie' ? 'right' : 'top',
                                labels: {
                                    font: {
                                        family: 'Kanit' // Apply Kanit font to legend
                                    }
                                }
                            },
                            title: {
                                display: false, // Title is in h5 tag
                                text: title,
                                font: {
                                    family: 'Kanit'
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    font: {
                                        family: 'Kanit'
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        family: 'Kanit'
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // --- Render Charts ---

            // Chart 1: Job Posting Status (Designer)
            const jpLabels = Object.keys(jobPostingStatusData).map(status => {
                const statusMap = {
                    'active': 'กำลังประกาศ',
                    'inactive': 'ไม่ใช้งาน',
                    'completed': 'เสร็จสิ้น'
                };
                return statusMap[status] || status;
            });
            const jpCounts = Object.values(jobPostingStatusData);
            const jpCtx = document.getElementById('jobPostingStatusChart').getContext('2d');
            createChart(jpCtx, 'pie', jpLabels, jpCounts, 'สถานะการประกาศรับงาน');

            // Chart 2: Client Request Status
            const crLabels = Object.keys(clientRequestStatusData).map(status => {
                const statusMap = {
                    'open': 'เปิดรับงาน',
                    'assigned': 'มอบหมายแล้ว',
                    'completed': 'เสร็จสิ้น',
                    'cancelled': 'ยกเลิกแล้ว'
                };
                return statusMap[status] || status;
            });
            const crCounts = Object.values(clientRequestStatusData);
            const crCtx = document.getElementById('clientRequestStatusChart').getContext('2d');
            createChart(crCtx, 'doughnut', crLabels, crCounts, 'สถานะคำของานผู้ว่าจ้าง');

            // Chart 3: Application Status
            const appLabels = Object.keys(applicationStatusData).map(status => {
                const statusMap = {
                    'pending': 'รอดำเนินการ',
                    'accepted': 'ยอมรับแล้ว',
                    'rejected': 'ถูกปฏิเสธ',
                    'cancelled': 'ยกเลิก'
                };
                return statusMap[status] || status;
            });
            const appCounts = Object.values(applicationStatusData);
            const appCtx = document.getElementById('applicationStatusChart').getContext('2d');
            createChart(appCtx, 'bar', appLabels, appCounts, 'สถานะการสมัคร/เสนอราคา');

            // Chart 4: Contract Status
            const contractLabels = Object.keys(contractStatusData).map(status => {
                const statusMap = {
                    'pending': 'รอดำเนินการ',
                    'active': 'กำลังดำเนินการ',
                    'completed': 'เสร็จสิ้น',
                    'cancelled': 'ยกเลิก'
                };
                return statusMap[status] || status;
            });
            const contractCounts = Object.values(contractStatusData);
            const contractCtx = document.getElementById('contractStatusChart').getContext('2d');
            createChart(contractCtx, 'bar', contractLabels, contractCounts, 'สถานะสัญญาจ้างงาน');

            // Chart 5: Payment Status
            const paymentLabels = Object.keys(paymentStatusData).map(status => {
                const statusMap = {
                    'pending': 'รอชำระเงิน',
                    'paid': 'ชำระเงินแล้ว',
                    'partially_paid': 'ชำระบางส่วน',
                    'refunded': 'คืนเงินแล้ว'
                };
                return statusMap[status] || status;
            });
            const paymentCounts = Object.values(paymentStatusData);
            const paymentCtx = document.getElementById('paymentStatusChart').getContext('2d');
            createChart(paymentCtx, 'pie', paymentLabels, paymentCounts, 'สถานะการชำระเงินของสัญญา');


            // Chart 6: User Type Distribution
            const userTypeLabels = Object.keys(userTypeData).map(type => {
                const typeMap = {
                    'admin': 'ผู้ดูแลระบบ',
                    'designer': 'นักออกแบบ',
                    'client': 'ผู้ว่าจ้าง'
                };
                return typeMap[type] || type;
            });
            const userTypeCounts = Object.values(userTypeData);
            const userTypeCtx = document.getElementById('userTypeChart').getContext('2d');
            createChart(userTypeCtx, 'doughnut', userTypeLabels, userTypeCounts, 'จำนวนผู้ใช้งานตามประเภท');

            // Chart 7: Review Rating Distribution
            const reviewLabels = Object.keys(reviewRatingData);
            const reviewCounts = Object.values(reviewRatingData);
            const reviewCtx = document.getElementById('reviewRatingChart').getContext('2d');
            createChart(reviewCtx, 'bar', reviewLabels, reviewCounts, 'การกระจายคะแนนรีวิว',
                'rgba(75, 192, 192, 0.7)' // Use a single color for ratings
            );
        });
    </script>
</body>

</html>