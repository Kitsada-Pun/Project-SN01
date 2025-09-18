<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: ../login.php");
    exit();
}

require_once '../connect.php';

$client_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$loggedInUserName = $_SESSION['username'] ?? '';
if (empty($loggedInUserName)) {
    $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param("i", $current_user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($user_info = $result_user->fetch_assoc()) {
            $loggedInUserName = $user_info['first_name'] . ' ' . $user_info['last_name'];
        }
        $stmt_user->close();
    }
}

if ($request_id === 0) {
    die("ไม่พบคำของาน");
}

// 1. ดึงข้อมูลหลักของ Job Request
$job_request = null;
$sql_job = "SELECT cjr.title, cjr.description, cjr.budget, cjr.status, cjr.deadline, cjr.posted_date, cjr.attachment_path, jc.category_name 
            FROM client_job_requests cjr
            LEFT JOIN job_categories jc ON cjr.category_id = jc.category_id
            WHERE cjr.request_id = ? AND cjr.client_id = ?";
$stmt_job = $conn->prepare($sql_job);
$stmt_job->bind_param("ii", $request_id, $client_id);
$stmt_job->execute();
$result_job = $stmt_job->get_result();
if ($result_job->num_rows > 0) {
    $job_request = $result_job->fetch_assoc();
} else {
    die("ไม่พบคำของาน หรือคุณไม่มีสิทธิ์เข้าถึง");
}
$stmt_job->close();

// 2. ดึงใบเสนอราคา
$proposals = [];
$sql_proposals = "
    SELECT 
        ja.application_id,
        ja.designer_id,
        ja.proposal_text,
        ja.offered_price,
        ja.application_date,
        ja.status AS proposal_status,
        u.first_name,
        u.last_name,
        p.profile_picture_url
    FROM job_applications ja
    JOIN users u ON ja.designer_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    WHERE ja.request_id = ?
    ORDER BY ja.application_date DESC
";
$stmt_proposals = $conn->prepare($sql_proposals);
$stmt_proposals->bind_param("i", $request_id);
$stmt_proposals->execute();
$result_proposals = $stmt_proposals->get_result();
$proposals = $result_proposals->fetch_all(MYSQLI_ASSOC);
$stmt_proposals->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิจารณาข้อเสนอ - <?= htmlspecialchars($job_request['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf3 100%);
            color: #2c3e50;
            overflow-x: hidden;
        }

        .navbar {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 150, 210, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #0d96d2 0%, #0a5f97 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 150, 210, 0.5);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2);
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4);
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
        }

        .text-gradient {
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pixellink-logo,
        .pixellink-logo-footer {
            font-weight: 700;
            font-size: 2.25rem;
            background: linear-gradient(45deg, #0a5f97, #0d96d2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .pixellink-logo b,
        .pixellink-logo-footer b {
            color: #0d96d2;
        }

        .card-item {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .card-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .card-image {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }

        .hero-section {
            background-image: url('dist/img/cover.png');
            background-size: cover;
            background-position: center;
            position: relative;
            z-index: 1;
            padding: 8rem 0;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }
    </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="main.php">
                <img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
            </a>
            <div class="space-x-4 flex items-center">
                <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                <!-- <a href="view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a> -->
                <a href="../logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8 flex-grow">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                    <h2 class="text-2xl font-bold text-gray-800 border-b pb-3 mb-4">
                        <i class="fas fa-file-alt mr-2 text-blue-500"></i>
                        คำขอจ้างงานของคุณ
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-semibold text-gray-700">ชื่องาน:</h3>
                            <p class="text-gray-600"><?= htmlspecialchars($job_request['title']) ?></p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-700">ประเภทงาน:</h3>
                            <p class="text-gray-600"><?= htmlspecialchars($job_request['category_name'] ?? 'ไม่ระบุ') ?></p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-700">รายละเอียด:</h3>
                            <p class="text-gray-600 bg-gray-50 p-3 rounded-md"><?= nl2br(htmlspecialchars($job_request['description'])) ?></p>
                        </div>
                        
                        <?php if (!empty($job_request['attachment_path'])) : 
                            $file_path = '../' . ltrim(htmlspecialchars($job_request['attachment_path']), './');
                            $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                            $image_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                        ?>
                            <div>
                                <h3 class="font-semibold text-gray-700 mb-2">ไฟล์แนบ:</h3>
                                <?php if (in_array($file_extension, $image_extensions)) : ?>
                                    <img src="<?= $file_path ?>" alt="ไฟล์แนบ" class="rounded-lg border max-w-full h-auto">
                                <?php else : ?>
                                    <a href="<?= $file_path ?>" target="_blank" class="text-blue-600 hover:underline">
                                        <i class="fas fa-paperclip mr-1"></i> ดูไฟล์แนบ (<?= htmlspecialchars(basename($job_request['attachment_path'])) ?>)
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h3 class="font-semibold text-gray-700">งบประมาณ:</h3>
                            <p class="text-green-600 font-bold text-lg">฿<?= htmlspecialchars(number_format((float)$job_request['budget'], 2)) ?></p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-700">เดดไลน์:</h3>
                            <p class="text-gray-600"><?= date('d F Y', strtotime($job_request['deadline'])) ?></p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-700">ส่งคำขอจ้างงานเมื่อ:</h3>
                            <p class="text-gray-500 text-sm"><?= date('d F Y, H:i', strtotime($job_request['posted_date'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <?php if ($job_request['status'] !== 'open' && $job_request['status'] !== 'proposed') : ?>
                    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg shadow" role="alert">
                        <p class="font-bold">สถานะ: ดำเนินการแล้ว</p>
                        <p>คุณได้ตัดสินใจสำหรับงานนี้เรียบร้อยแล้ว</p>
                    </div>
                <?php elseif (empty($proposals)) : ?>
                    <div class="text-center bg-white rounded-lg shadow-sm p-12">
                        <i class="fas fa-hourglass-half fa-3x text-gray-300"></i>
                        <h3 class="mt-4 text-xl font-semibold text-gray-700">ยังไม่มีข้อเสนอเข้ามา</h3>
                        <p class="mt-1 text-gray-500">เมื่อมีนักออกแบบยื่นข้อเสนอสำหรับงานนี้ จะแสดงผลที่นี่</p>
                    </div>
                <?php else : ?>
                    <div class="space-y-8">
                        <?php foreach ($proposals as $proposal) : ?>
                            <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
                                <div class="bg-gray-50 p-4 border-b flex items-center gap-4">
                                    <a href="../designer/view_profile.php?user_id=<?= $proposal['designer_id'] ?>" target="_blank">
                                        <img src="<?= htmlspecialchars($proposal['profile_picture_url'] ?? '../dist/img/avatar.png') ?>" class="w-12 h-12 rounded-full object-cover hover:opacity-80 transition-opacity">
                                    </a>
                                    <div>
                                        <h3 class="font-bold text-lg text-gray-800">
                                            <a href="../designer/view_profile.php?user_id=<?= $proposal['designer_id'] ?>" class="hover:text-blue-600" target="_blank">
                                                ข้อเสนอจาก: <?= htmlspecialchars($proposal['first_name'] . ' ' . $proposal['last_name']) ?>
                                            </a>
                                        </h3>
                                        <p class="text-sm text-gray-500">ยื่นข้อเสนอเมื่อ: <?= date('d M Y, H:i', strtotime($proposal['application_date'])) ?></p>
                                    </div>
                                </div>
                                
                                <div class="p-6 space-y-4">
                                    <div class="border rounded-lg p-4">
                                        <h4 class="font-bold text-xl mb-4 text-center text-gray-700">ใบเสนอราคา</h4>
                                        <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                                            <dt class="font-semibold text-gray-600 md:col-span-1">ชื่องาน/โปรเจกต์:</dt>
                                            <dd class="text-gray-800 md:col-span-2"><?= htmlspecialchars($job_request['title']) ?></dd>
                                            
                                            <dt class="font-semibold text-gray-600 md:col-span-1">ประเภทงาน:</dt>
                                            <dd class="text-gray-800 md:col-span-2"><?= htmlspecialchars($job_request['category_name'] ?? 'ไม่ระบุ') ?></dd>
                                            
                                            <dt class="font-semibold text-gray-600 md:col-span-1">ส่งมอบงานภายในวันที่:</dt>
                                            <dd class="text-gray-800 md:col-span-2"><?= date('d F Y', strtotime($job_request['deadline'])) ?></dd>
                                        </dl>
                                        <hr class="my-4">
                                        <div>
                                            <h5 class="font-semibold text-gray-600 mb-2">ข้อความจากนักออกแบบ:</h5>
                                            <div class="bg-gray-50 p-3 rounded-md text-gray-700">
                                                <?= !empty($proposal['proposal_text']) ? nl2br(htmlspecialchars($proposal['proposal_text'])) : '<i>ไม่มีข้อความเพิ่มเติม</i>' ?>
                                            </div>
                                        </div>
                                        <div class="mt-6 border-t pt-4">
                                            <div class="flex justify-end items-baseline gap-4">
                                                <div class="text-right">
                                                    <p class="text-gray-600 text-sm">ค่ามัดจำ (20%)</p>
                                                    <p class="text-xl font-semibold text-blue-600">฿<?= htmlspecialchars(number_format((float)$proposal['offered_price'] * 0.20, 2)) ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-gray-600">ราคาที่เสนอทั้งหมด</p>
                                                    <p class="text-3xl font-bold text-green-600">฿<?= htmlspecialchars(number_format((float)$proposal['offered_price'], 2)) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
                                    <button class="action-btn px-5 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" data-action="reject" data-application-id="<?= $proposal['application_id'] ?>" data-request-id="<?= $request_id ?>" data-designer-id="<?= $proposal['designer_id'] ?>">
                                        <i class="fas fa-times mr-1"></i> ปฏิเสธ
                                    </button>
                                    <button class="action-btn px-5 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" data-action="accept" data-application-id="<?= $proposal['application_id'] ?>" data-request-id="<?= $request_id ?>" data-designer-id="<?= $proposal['designer_id'] ?>">
                                        <i class="fas fa-check mr-1"></i> ตอบตกลงและชำระเงิน
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            const isActionable = <?= json_encode($job_request['status'] === 'open' || $job_request['status'] === 'proposed') ?>;
            if (!isActionable) {
                $('.action-btn').prop('disabled', true);
            }

            $('.action-btn').on('click', function(e) {
                e.preventDefault();
                if (!isActionable) return;

                const button = $(this);
                const action = button.data('action');
                const application_id = button.data('application-id');
                const request_id = button.data('request-id');
                const designer_id = button.data('designer-id');

                let confirmTitle = action === 'accept' ? 'ยืนยันที่จะตอบตกลงข้อเสนอนี้?' : 'ยืนยันที่จะปฏิเสธข้อเสนอนี้?';
                let confirmText = action === 'accept' ?
                    'เมื่อตอบตกลงแล้ว ระบบจะนำคุณไปยังหน้าชำระเงินมัดจำเพื่อเริ่มงาน' :
                    'คุณแน่ใจหรือไม่ว่าต้องการปฏิเสธข้อเสนอนี้?';
                let confirmButtonText = action === 'accept' ? 'ใช่, ตอบตกลง' : 'ใช่, ปฏิเสธ';

                Swal.fire({
                    title: confirmTitle,
                    text: confirmText,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: (action === 'accept' ? '#16a34a' : '#dc2626'),
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: confirmButtonText,
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'กำลังดำเนินการ...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading()
                            }
                        });

                        $.ajax({
                            url: 'action_proposal.php',
                            method: 'POST',
                            data: {
                                application_id: application_id,
                                request_id: request_id,
                                designer_id: designer_id,
                                action: action
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    Swal.fire('สำเร็จ!', response.message, 'success').then(() => {
                                        if (response.redirectUrl) {
                                            window.location.href = response.redirectUrl;
                                        } else {
                                            window.location.href = 'my_requests.php';
                                        }
                                    });
                                } else {
                                    Swal.fire('ผิดพลาด!', response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์', 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>