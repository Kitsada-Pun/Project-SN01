<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- ตรวจสอบสิทธิ์การเข้าถึง ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: ../login.php");
    exit();
}

require_once '../connect.php';

$client_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

// --- ตรวจสอบว่ามี request_id ส่งมาหรือไม่ ---
if ($request_id === 0) {
    die("Error: ไม่พบรหัสคำขอจ้างงาน (Invalid Request ID)");
}

// --- ดึงข้อมูลที่จำเป็นสำหรับหน้าชำระเงิน ---
$payment_data = null;
$sql = "
    SELECT 
        cjr.title AS request_title,
        cjr.status,
        u.user_id AS designer_id,
        CONCAT(u.first_name, ' ', u.last_name) AS designer_name,
        up.payment_qr_code_path,
        ja.offered_price
    FROM 
        client_job_requests cjr
    JOIN 
        users u ON cjr.designer_id = u.user_id
    JOIN 
        user_profiles up ON u.user_id = up.user_id
    JOIN 
        job_applications ja ON cjr.request_id = ja.request_id AND ja.designer_id = cjr.designer_id
    WHERE 
        cjr.request_id = ? AND cjr.client_id = ? AND ja.status = 'accepted'
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $request_id, $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $payment_data = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    die("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error);
}

// --- หากไม่พบข้อมูล หรือสถานะไม่ถูกต้อง ให้แสดงข้อผิดพลาด ---
if (!$payment_data) {
    die("ไม่พบข้อมูลการชำระเงิน หรือคุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}
if ($payment_data['status'] !== 'Awaiting Payment') {
    // ถ้างานไม่ได้อยู่ในสถานะรอชำระเงิน ให้ส่งกลับไปหน้า my_requests
    $_SESSION['message'] = ['type' => 'info', 'text' => 'งานนี้ไม่อยู่ในสถานะที่ต้องชำระเงินมัดจำ'];
    header('Location: my_requests.php');
    exit();
}


// คำนวณยอดมัดจำ 50%
$deposit_amount = $payment_data['offered_price'] * 0.50;
$loggedInUserName = $_SESSION['username'] ?? 'ผู้ว่าจ้าง';

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงินมัดจำ - PixelLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        .payment-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(45deg, #0a5f97 0%, #0d96d2 100%);
            color: white;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-slate-100">
    <nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="main.php">
                <img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12">
            </a>
            <div class="space-x-4 flex items-center">
                <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                <a href="../logout.php" class="px-5 py-2 rounded-lg font-medium bg-red-500 text-white hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-12">
        <div class="max-w-2xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-slate-800">ชำระเงินมัดจำเพื่อเริ่มโปรเจกต์</h1>
                <p class="text-slate-500 mt-1">กรุณาชำระเงินและอัปโหลดหลักฐานเพื่อให้นักออกแบบเริ่มทำงาน</p>
            </div>
            
            <div class="payment-card p-8">
                <div class="border-b pb-4 mb-6">
                    <h2 class="text-xl font-semibold text-slate-700"><?= htmlspecialchars($payment_data['request_title']) ?></h2>
                    <p class="text-sm text-slate-500">ชำระเงินให้กับ: <strong><?= htmlspecialchars($payment_data['designer_name']) ?></strong></p>
                </div>

                <div class="grid md:grid-cols-2 gap-8 items-center">
                    <div class="text-center">
                        <h3 class="font-semibold text-slate-600 mb-2">สแกน QR Code เพื่อชำระเงิน</h3>
                        <?php if (!empty($payment_data['payment_qr_code_path']) && file_exists('..' . $payment_data['payment_qr_code_path'])): ?>
                            <img src="<?= '..' . htmlspecialchars($payment_data['payment_qr_code_path']) ?>" alt="QR Code" class="mx-auto w-48 h-48 object-contain border rounded-lg p-2">
                        <?php else: ?>
                            <div class="mx-auto w-48 h-48 bg-slate-200 flex items-center justify-center rounded-lg">
                                <p class="text-slate-500 text-sm">ไม่มี QR Code</p>
                            </div>
                        <?php endif; ?>
                        <div class="mt-4">
                            <p class="text-sm text-slate-500">ยอดชำระเงินมัดจำ (50%)</p>
                            <p class="text-3xl font-bold text-green-600">฿<?= number_format($deposit_amount, 2) ?></p>
                        </div>
                    </div>

                    <div>
                        <form action="submit_payment.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="request_id" value="<?= $request_id ?>">
                            <input type="hidden" name="amount" value="<?= $deposit_amount ?>">
                            <input type="hidden" name="designer_id" value="<?= $payment_data['designer_id'] ?>">


                            <div>
                                <label for="payment_slip" class="block text-sm font-medium text-slate-700 mb-2">อัปโหลดสลิปการโอนเงิน</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <img id="slip-preview" src="" alt="Preview" class="mx-auto h-32 hidden mb-4 rounded"/>
                                        <i id="upload-icon" class="fas fa-cloud-upload-alt fa-3x text-slate-400"></i>
                                        <div class="flex text-sm text-slate-600">
                                            <label for="payment_slip_input" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                                <span>เลือกไฟล์</span>
                                                <input id="payment_slip_input" name="payment_slip" type="file" class="sr-only" required accept="image/png, image/jpeg, image/gif">
                                            </label>
                                            <p class="pl-1">หรือลากมาวาง</p>
                                        </div>
                                        <p id="file-name" class="text-xs text-slate-500">PNG, JPG, GIF ขนาดไม่เกิน 5MB</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6">
                                <button type="submit" class="w-full btn-primary font-bold py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-check-circle"></i> ยืนยันการชำระเงิน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="my_requests.php" class="text-sm text-slate-600 hover:text-blue-500">&larr; กลับไปหน้ารายการของฉัน</a>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('payment_slip_input').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                const preview = document.getElementById('slip-preview');
                const icon = document.getElementById('upload-icon');
                const fileNameDisplay = document.getElementById('file-name');

                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    icon.classList.add('hidden');
                }

                reader.readAsDataURL(file);
                fileNameDisplay.textContent = file.name;
            }
        });
    </script>
</body>
</html>