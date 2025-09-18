    <?php
    session_start();
    date_default_timezone_set('Asia/Bangkok');

    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
        header("Location: ../login.php");
        exit();
    }

    require_once '../connect.php';

    $designer_id = $_SESSION['user_id'];
    $loggedInUserName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Designer';

    // --- ดึงชื่อผู้ใช้ที่ล็อกอิน ---
    if (isset($_SESSION['user_id'])) {
        $loggedInUserName = $_SESSION['username'] ?? $_SESSION['full_name'] ?? '';
        if (empty($loggedInUserName)) {
            $user_id = $_SESSION['user_id'];
            $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";

            // [จุดที่แก้ไข] เปลี่ยนจาก $condb เป็น $conn
            $stmt_user = $conn->prepare($sql_user);

            if ($stmt_user) {
                $stmt_user->bind_param("i", $user_id);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();
                if ($result_user->num_rows === 1) {
                    $user_info = $result_user->fetch_assoc();
                    $loggedInUserName = $user_info['first_name'] . ' ' . $user_info['last_name'];
                }
                $stmt_user->close();
            }
        }
    }

    $offers = [];

    // --- [ปรับแก้ SQL Query] ---
    // ดึงข้อมูลจาก client_job_requests ที่ส่งถึงนักออกแบบคนนี้โดยตรง
    $sql = "
        SELECT 
            cjr.request_id,
            cjr.title,
            cjr.description,
            cjr.budget AS price,
            cjr.posted_date AS offer_date,
            cjr.status,
            u.user_id AS client_id,
            CONCAT(u.first_name, ' ', u.last_name) AS client_name,
            p.profile_picture_url AS client_avatar
        FROM client_job_requests cjr
        JOIN users u ON cjr.client_id = u.user_id
        LEFT JOIN profiles p ON u.user_id = p.user_id
        WHERE cjr.designer_id = ?
        ORDER BY cjr.posted_date DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $designer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $offers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("SQL Error (my_offers): " . $conn->error);
        die("เกิดข้อผิดพลาดในการดึงข้อมูล");
    }
    // นับจำนวนข้อเสนอที่รอการตอบรับจากข้อมูลที่มีอยู่แล้ว
    // --- [เพิ่มเข้ามา] นับจำนวนงานในแต่ละสถานะ ---
    $counts = [
        'pending'   => 0,
        'submitted' => 0,
        'active'    => 0,
        'completed' => 0,
        'cancelled' => 0,
        'rejected'  => 0, // [ใหม่]
    ];

    foreach ($offers as $offer) {
        $statusInfo = getStatusInfo($offer['status']);
        $tabKey = $statusInfo['tab'];
        if (isset($counts[$tabKey])) {
            $counts[$tabKey]++;
        }
    }
    $conn->close();

    // --- [ปรับแก้ Function] ---
    function getStatusInfo($status)
    {
        switch ($status) {
            case 'open':
                return ['text' => 'รอการตอบรับ', 'color' => 'bg-yellow-100 text-yellow-800', 'tab' => 'pending'];
            case 'proposed':
                return ['text' => 'รอผู้ว่าจ้างพิจารณา', 'color' => 'bg-blue-100 text-blue-800', 'tab' => 'submitted'];
            case 'assigned':
                return ['text' => 'กำลังดำเนินการ', 'color' => 'bg-blue-100 text-blue-800', 'tab' => 'active'];
            case 'completed':
                return ['text' => 'เสร็จสมบูรณ์', 'color' => 'bg-green-100 text-green-800', 'tab' => 'completed'];
            case 'cancelled':
                // สำหรับงานที่ "ผู้ว่าจ้าง" เป็นคนยกเลิก
                return ['text' => 'ผู้ว่าจ้างยกเลิก', 'color' => 'bg-gray-100 text-gray-800', 'tab' => 'cancelled'];
            case 'rejected':
                // [ใหม่] สำหรับงานที่ "เรา" เป็นคนปฏิเสธ
                return ['text' => 'ปฏิเสธโดยคุณ', 'color' => 'bg-red-100 text-red-800', 'tab' => 'rejected'];
            default:
                return ['text' => 'ไม่ระบุ', 'color' => 'bg-gray-100 text-gray-800', 'tab' => 'all'];
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="th" class="h-full">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>งานของฉัน - PixelLink</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                font-family: 'Kanit', sans-serif;
            }

            .line-clamp-2 {
                overflow: hidden;
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
            }

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

    <body class="bg-slate-50 flex flex-col min-h-screen">

        <?php include '../includes/nav.php'; ?>

        <main class="container mx-auto px-4 py-8 flex-grow">
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-slate-800">งานของฉัน</h1>
                <p class="text-slate-500 mt-1">ติดตามและจัดการงานที่ผู้ว่าจ้างยื่นข้อเสนอให้คุณที่นี่</p>
            </div>

            <div x-data="{ tab: 'all', isModalOpen: false, modalData: {} }">
                <div class="mb-6 p-1.5 bg-slate-200/60 rounded-xl flex flex-wrap items-center gap-2">
                    <button @click="tab = 'all'" :class="tab === 'all' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-slate-300/60'" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all">ทั้งหมด</button>
                    <button @click="tab = 'pending'" :class="tab === 'pending' ? 'bg-white text-yellow-600 shadow-sm' : 'text-slate-600 hover:bg-slate-300/60'" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg transition-all">ข้อเสนองาน
                        <?php if ($counts['pending'] > 0): ?><span class="ml-2 h-5 w-5 rounded-full bg-red-500 text-xs font-bold text-white flex items-center justify-center"><?= $counts['pending'] ?></span><?php endif; ?>
                    </button>
                    <button @click="tab = 'submitted'" :class="tab === 'submitted' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-slate-300/60'" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg transition-all">ยื่นใบเสนอราคา
                        <?php if ($counts['submitted'] > 0): ?><span class="ml-2 h-5 w-5 rounded-full bg-blue-500 text-xs font-bold text-white flex items-center justify-center"><?= $counts['submitted'] ?></span><?php endif; ?>
                    </button>
                    <button @click="tab = 'active'" :class="tab === 'active' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:bg-slate-300/60'" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all">กำลังดำเนินการ</button>
                    <button @click="tab = 'completed'" :class="tab === 'completed' ? 'bg-white text-green-600 shadow-sm' : 'text-slate-600 hover:bg-slate-300/60'" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all">เสร็จสมบูรณ์</button>

                    <button @click="tab = 'rejected'" :class="tab === 'rejected' ? 'bg-white text-red-600 shadow-sm' : 'text-slate-600 hover:bg-slate-300/60'" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all">งานที่ปฏิเสธ</button>

                    <button @click="tab = 'cancelled'" :class="tab === 'cancelled' ? 'bg-white text-gray-600 shadow-sm' : 'text-slate-600 hover:bg-slate-300/60'" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all">ถูกยกเลิก</button>
                </div>

                <div class="space-y-5">
                    <?php if (empty($offers)): ?>
                        <div class="text-center bg-white rounded-lg shadow-sm p-12">
                            <div class="mx-auto w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-paper-plane fa-2x text-slate-400"></i>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold text-slate-700">ยังไม่มีข้อเสนองานเข้ามา</h3>
                            <p class="mt-1 text-slate-500">เมื่อมีผู้ว่าจ้างสนใจคุณ ข้อเสนอจะแสดงที่นี่</p>
                        </div>

                    <?php else: ?>

                        <?php foreach ($offers as $offer): ?>
                            <?php
                            $statusInfo = getStatusInfo($offer['status']);
                            $data_status = $statusInfo['tab'];
                            ?>
                            <div x-show="tab === 'all' || tab === '<?= $data_status ?>'"
                                class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                                <div class="flex flex-col sm:flex-row gap-6">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                                            <h2 class="text-xl font-bold text-slate-800 leading-tight">
                                                <a href="#" class="view-details-btn hover:text-blue-600" data-request-id="<?= $offer['request_id'] ?>">
                                                    <?= htmlspecialchars($offer['title']) ?>
                                                </a>
                                            </h2>
                                            <span class="text-xs font-semibold px-3 py-1 rounded-full <?= $statusInfo['color'] ?>">
                                                <?= htmlspecialchars($statusInfo['text']) ?>
                                            </span>
                                        </div>
                                        <p class="text-slate-500 text-sm mb-4 line-clamp-2">
                                            <?= htmlspecialchars($offer['description']) ?>
                                        </p>
                                        <div class="text-sm space-y-2 text-slate-600">
                                            <p><i class="fa-solid fa-user-tie w-5 text-slate-400 mr-1"></i> ผู้ว่าจ้าง: <a href="../view_profile.php?user_id=<?= $offer['client_id'] ?>" class="font-semibold text-blue-600 hover:underline"><?= htmlspecialchars($offer['client_name']) ?></a></p>
                                            <p><i class="fa-solid fa-calendar-day w-5 text-slate-400 mr-1"></i> ยื่นข้อเสนอเมื่อ: <?= date('d M Y, H:i', strtotime($offer['offer_date'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 sm:text-right sm:border-l sm:pl-6 border-slate-200/80 w-full sm:w-auto">
                                        <div class="text-2xl font-bold text-green-600 mb-4">
                                            ฿<?= !empty($offer['price']) ? number_format((float)$offer['price'], 2) : 'N/A' ?>
                                        </div>
                                        <div class="flex flex-col sm:items-end gap-2">

                                            <?php if ($offer['status'] === 'open'): ?>

                                                <button
                                                    @click="isModalOpen = true; modalData = <?= htmlspecialchars(json_encode($offer), ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-full sm:w-auto text-center px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors">
                                                    <i class="fa-solid fa-file-invoice-dollar mr-1"></i> ยื่นใบเสนอราคา
                                                </button>

                                                <a href="../messages.php?to_user=<?= $offer['client_id'] ?>"
                                                    class="w-full sm:w-auto text-center px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-semibold hover:bg-green-600 transition-colors">
                                                    <i class="fa-solid fa-comments mr-1"></i> ติดต่อผู้ว่าจ้าง
                                                </a>

                                                <button
                                                    data-request-id="<?= $offer['request_id'] ?>"
                                                    data-action="reject"
                                                    class="offer-action-btn w-full sm:w-auto text-center px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-semibold hover:bg-red-600 transition-colors">
                                                    <i class="fa-solid fa-times mr-1"></i> ปฏิเสธ
                                                </button>

                                            <?php elseif ($offer['status'] === 'proposed'): ?>
                                                <a href="#" class="view-details-btn w-full sm:w-auto text-center px-4 py-2 bg-slate-600 text-white rounded-lg text-sm font-semibold hover:bg-slate-700" data-request-id="<?= $offer['request_id'] ?>">
                                                    <i class="fa-solid fa-search mr-1"></i> ดูรายละเอียดงาน
                                                </a>

                                                <button
                                                    data-request-id="<?= $offer['request_id'] ?>"
                                                    class="view-proposal-btn w-full sm:w-auto text-center px-4 py-2 bg-purple-500 text-white rounded-lg text-sm font-semibold hover:bg-purple-600 transition-colors">
                                                    <i class="fa-solid fa-eye mr-1"></i> ดูใบเสนอราคาของฉัน
                                                </button>

                                                <a href="../messages.php?to_user=<?= $offer['client_id'] ?>"
                                                    class="w-full sm:w-auto text-center px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-semibold hover:bg-green-600 transition-colors">
                                                    <i class="fa-solid fa-comments mr-1"></i> ติดต่อผู้ว่าจ้าง
                                                </a>

                                            <?php elseif ($offer['status'] === 'assigned'): ?>

                                                <a href="../messages.php?to_user=<?= $offer['client_id'] ?>" class="w-full sm:w-auto text-center px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-semibold hover:bg-green-600 transition-colors">
                                                    <i class="fa-solid fa-comments mr-1"></i> ติดต่อผู้ว่าจ้าง
                                                </a>

                                            <?php else: ?>

                                                <a href="../job_detail.php?request_id=<?= $offer['request_id'] ?>" class="w-full sm:w-auto text-center px-4 py-2 bg-slate-600 text-white rounded-lg text-sm font-semibold hover:bg-slate-700 transition-colors">
                                                    <i class="fa-solid fa-search mr-1"></i> ดูรายละเอียด
                                                </a>

                                            <?php endif; ?>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div x-show="tab === 'pending' && <?= $counts['pending'] ?> === 0" class="text-center bg-white rounded-lg shadow-sm p-12">
                        <i class="fa-solid fa-inbox fa-2x text-slate-400"></i>
                        <h3 class="mt-4 text-xl font-semibold text-slate-700">ยังไม่มีข้อเสนองานใหม่</h3>
                        <p class="mt-1 text-slate-500">เมื่อมีผู้ว่าจ้างส่งคำขอจ้างงานให้คุณโดยตรง จะแสดงที่นี่</p>
                    </div>

                    <div x-show="tab === 'submitted' && <?= $counts['submitted'] ?> === 0" class="text-center bg-white rounded-lg shadow-sm p-12">
                        <i class="fa-solid fa-paper-plane fa-2x text-slate-400"></i>
                        <h3 class="mt-4 text-xl font-semibold text-slate-700">ยังไม่มียื่นใบเสนอราคา</h3>
                        <p class="mt-1 text-slate-500">รายการที่คุณยื่นเสนอราคาไปแล้วจะแสดงที่นี่</p>
                    </div>

                    <div x-show="tab === 'active' && <?= $counts['active'] ?> === 0" class="text-center bg-white rounded-lg shadow-sm p-12">
                        <i class="fa-solid fa-person-digging fa-2x text-slate-400"></i>
                        <h3 class="mt-4 text-xl font-semibold text-slate-700">ยังไม่มีงานที่กำลังดำเนินการ</h3>
                        <p class="mt-1 text-slate-500">งานที่ผู้ว่าจ้างตอบตกลงแล้วจะแสดงที่นี่</p>
                    </div>

                    <div x-show="tab === 'completed' && <?= $counts['completed'] ?> === 0" class="text-center bg-white rounded-lg shadow-sm p-12">
                        <i class="fa-solid fa-circle-check fa-2x text-slate-400"></i>
                        <h3 class="mt-4 text-xl font-semibold text-slate-700">ยังไม่มีงานที่เสร็จสมบูรณ์</h3>
                        <p class="mt-1 text-slate-500">งานที่คุณทำเสร็จแล้วจะถูกย้ายมาที่นี่</p>
                    </div>

                    <div x-show="tab === 'cancelled' && <?= $counts['cancelled'] ?> === 0" class="text-center bg-white rounded-lg shadow-sm p-12">
                        <i class="fa-solid fa-circle-xmark fa-2x text-slate-400"></i>
                        <h3 class="mt-4 text-xl font-semibold text-slate-700">ไม่มีงานที่ถูกยกเลิก</h3>
                        <p class="mt-1 text-slate-500">รายการงานที่ถูกยกเลิกจะแสดงที่นี่</p>
                    </div>
                </div>
            </div>
            </div>
            <div x-show="isModalOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center px-4 py-6" style="display: none;">

                <div @click.away="isModalOpen = false" class="bg-gray-50 rounded-xl shadow-2xl w-full max-w-2xl mx-auto max-h-full overflow-y-auto">

                    <form id="proposal-form" action="submit_proposal.php" method="POST">
                        <div class="px-6 py-5 sm:p-8">
                            <div class="text-center mb-6">
                                <h3 class="text-2xl leading-6 font-bold text-gray-900">
                                    ใบเสนอราคา
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">ตรวจสอบรายละเอียดและกรอกราคาที่คุณเสนอ</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-6">
                                <div>
                                    <p class="font-semibold text-gray-800">จาก (นักออกแบบ):</p>
                                    <p><?php echo htmlspecialchars($loggedInUserName); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-gray-800">ถึง (ผู้ว่าจ้าง):</p>
                                    <p x-text="modalData.client_name"></p>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">เลขที่คำขอ:</p>
                                    <p>#<span x-text="modalData.request_id"></span></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-gray-800">วันที่เสนอราคา:</p>
                                    <p><?php echo date("d / m / Y"); ?></p>
                                </div>
                            </div>

                            <hr class="my-6">

                            <div class="space-y-5">
                                <input type="hidden" name="request_id" :value="modalData.request_id">
                                <input type="hidden" name="client_id" :value="modalData.client_id">

                                <div>
                                    <label class="block text-sm font-bold text-gray-700">ชื่องาน / โปรเจกต์</label>
                                    <input type="text" :value="modalData.title" class="mt-1 block w-full px-3 py-2 bg-slate-100 border border-gray-300 rounded-md shadow-sm" readonly>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700">รายละเอียดที่ผู้ว่าจ้างแจ้ง</label>
                                    <div x-text="modalData.description" class="mt-1 block w-full p-3 bg-slate-100 border border-gray-300 rounded-md shadow-sm text-sm text-gray-600 min-h-[80px]"></div>
                                </div>

                                <hr class="my-6 border-t-2 border-dashed">

                                <div>
                                    <label for="proposal_text" class="block text-sm font-bold text-gray-700">ข้อความถึงผู้ว่าจ้าง (Optional)</label>
                                    <textarea id="proposal_text" name="proposal_text" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="แนะนำแนวทางการทำงาน, สิ่งที่คุณจะทำให้, หรือรายละเอียดอื่นๆ เพิ่มเติม..."></textarea>
                                </div>

                                <div>
                                    <label for="offered_price" class="block text-sm font-bold text-gray-700">เสนอราคา (บาท)</label>
                                    <input type="number" id="offered_price" name="offered_price" min="0" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="ระบุราคาที่คุณเสนอสำหรับงานนี้">
                                </div>

                                <div id="deposit-calculation" class="p-3 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-800" style="display: none;">
                                    <p>ยอดมัดจำ 20%: <strong id="deposit-amount" class="font-bold">0.00</strong> บาท</p>
                                    <p class="text-xs text-blue-600 mt-1">ยอดนี้จะถูกเรียกเก็บจากผู้ว่าจ้างเมื่อมีการตกลงจ้างงาน</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-100 px-4 py-4 sm:px-8 sm:flex sm:flex-row-reverse rounded-b-xl">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                                <i class="fas fa-paper-plane mr-2"></i>ส่งใบเสนอราคา
                            </button>
                            <button type="button" @click="isModalOpen = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                                ยกเลิก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </div>
        </main>

        <?php include '../includes/footer.php'; ?>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            $(document).ready(function() {

                // --- 1. จัดการการส่งฟอร์มใบเสนอราคาด้วย AJAX ---
                $('#proposal-form').on('submit', function(e) {
                    e.preventDefault();
                    const form = $(this);
                    const formData = form.serialize();

                    Swal.fire({
                        title: 'ยืนยันการส่งใบเสนอราคา?',
                        text: "กรุณาตรวจสอบข้อมูลให้ถูกต้องก่อนส่ง",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'ยืนยันและส่ง',
                        cancelButtonText: 'ยกเลิก'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'กำลังส่งข้อมูล...',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            $.ajax({
                                url: 'submit_proposal.php', // ตรวจสอบว่าไฟล์นี้มีอยู่จริง
                                method: 'POST',
                                data: formData,
                                dataType: 'json',
                                success: function(response) {
                                    if (response.status === 'success') {
                                        Swal.fire({
                                                icon: 'success',
                                                title: 'สำเร็จ!',
                                                text: response.message
                                            })
                                            .then(() => {
                                                location.reload();
                                            });
                                    } else {
                                        Swal.fire('เกิดข้อผิดพลาด!', response.message, 'error');
                                    }
                                },
                                error: function() {
                                    Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                                }
                            });
                        }
                    });
                });

                // --- 2. จัดการการคลิกปุ่ม "ปฏิเสธ" ---
                $('.offer-action-btn').on('click', function() {
                    const button = $(this);
                    const requestId = button.data('request-id');
                    const action = button.data('action'); // action should be 'reject'

                    Swal.fire({
                        title: 'ยืนยันการปฏิเสธ?',
                        text: "คุณแน่ใจหรือไม่ที่จะปฏิเสธข้อเสนอนี้",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'ใช่, ปฏิเสธเลย',
                        cancelButtonText: 'ยกเลิก'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'action_offer.php', // ตรวจสอบว่าไฟล์นี้มีอยู่จริง
                                method: 'POST',
                                data: {
                                    request_id: requestId,
                                    action: action
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.status === 'success') {
                                        Swal.fire('สำเร็จ!', response.message, 'success')
                                            .then(() => location.reload());
                                    } else {
                                        Swal.fire('เกิดข้อผิดพลาด!', response.message, 'error');
                                    }
                                },
                                error: function() {
                                    Swal.fire('เกิดข้อผิดพลาด!', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                                }
                            });
                        }
                    });
                });

                // --- 3. จัดการการคลิกที่ชื่องานเพื่อดูรายละเอียด ---
                $('.view-details-btn').on('click', function(e) {
                    e.preventDefault();
                    const requestId = $(this).data('request-id');
                    Swal.fire({
                        title: 'กำลังโหลดข้อมูล...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    $.ajax({
                        url: '../get_request_details.php',
                        method: 'GET',
                        data: {
                            request_id: requestId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                const details = response.data;
                                const deadline = new Date(details.deadline).toLocaleDateString('th-TH', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                });
                                let attachmentHtml = '';
                                if (details.attachment_path && details.attachment_path.trim() !== '') {
                                    const filePath = details.attachment_path.startsWith('../') ? details.attachment_path.substring(3) : details.attachment_path;
                                    attachmentHtml = `<hr style="margin: 1rem 0;"><p><strong>ไฟล์แนบ:</strong></p><a href="../${filePath}" target="_blank"><img src="../${filePath}" alt="ไฟล์แนบ" style="max-width: 100%; max-height: 250px; margin-top: 5px; border-radius: 5px; border: 1px solid #ddd;"></a>`;
                                }
                                Swal.fire({
                                    title: `<strong>รายละเอียดคำขอจ้างงาน</strong>`,
                                    html: `<div style="text-align: left; padding: 0 1rem;"><p><strong>ชื่องาน:</strong> ${details.title}</p><p><strong>ประเภทงาน:</strong> ${details.category_name || 'ไม่ได้ระบุ'}</p><p><strong>รายละเอียด:</strong></p><div style="white-space: pre-wrap; background-color: #f9f9f9; border: 1px solid #ddd; padding: 10px; border-radius: 5px; max-height: 150px; overflow-y: auto;">${details.description}</div>${attachmentHtml}<hr style="margin: 1rem 0;"><p><strong>งบประมาณ:</strong> ${details.budget ? details.budget + ' บาท' : 'ไม่ได้ระบุ'}</p><p><strong>ส่งมอบงานภายใน:</strong> ${deadline}</p></div>`,
                                    confirmButtonText: 'ปิด',
                                    width: '600px'
                                });
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถดึงข้อมูลได้', 'error');
                        }
                    });
                });

                // --- 4. จัดการการคลิกปุ่ม "ดูใบเสนอราคาของฉัน" ---
                $('.view-proposal-btn').on('click', function(e) {
                    e.preventDefault();
                    const requestId = $(this).data('request-id');
                    Swal.fire({
                        title: 'กำลังโหลดข้อมูล...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    $.ajax({
                        url: '../get_proposal_details.php',
                        method: 'GET',
                        data: {
                            request_id: requestId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                const details = response.data;
                                const offeredPrice = parseFloat(details.offered_price);
                                const deposit = offeredPrice * 0.20;
                                const formattedOfferedPrice = offeredPrice.toLocaleString('th-TH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                const formattedDeposit = deposit.toLocaleString('th-TH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                const applicationDate = new Date(details.application_date).toLocaleDateString('th-TH', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                                const proposalHtml = `
                                <div class="text-center mb-6">
                                    <h3 class="text-2xl leading-6 font-bold text-gray-900">ใบเสนอราคา (ที่ยื่นไปแล้ว)</h3>
                                    <p class="mt-1 text-sm text-gray-500">นี่คือรายละเอียดที่คุณได้ยื่นเสนอไป</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-6 text-left">
                                    <div>
                                        <p class="font-semibold text-gray-800">จาก (นักออกแบบ):</p>
                                        <p><?php echo htmlspecialchars($loggedInUserName); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-800">ถึง (ผู้ว่าจ้าง):</p>
                                        <p>${details.client_name || ''}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800">เลขที่คำขอ:</p>
                                        <p>#${requestId}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-800">วันที่เสนอราคา:</p>
                                        <p>${applicationDate}</p>
                                    </div>
                                </div>
                                <hr class="my-6">
                                <div class="space-y-5 text-left">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">ชื่องาน / โปรเจกต์</label>
                                        <div class="mt-1 p-3 bg-slate-100 border border-gray-300 rounded-md">${details.job_title}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">ข้อความถึงผู้ว่าจ้าง</label>
                                        <div class="mt-1 p-3 bg-slate-100 border border-gray-300 rounded-md min-h-[80px]">${details.proposal_text || '<em>ไม่ได้ระบุข้อความเพิ่มเติม</em>'}</div>
                                    </div>
                                    <hr class="my-6 border-t-2 border-dashed">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700">ราคาที่เสนอ (บาท)</label>
                                        <div class="mt-1 p-3 bg-slate-100 border border-gray-300 rounded-md font-bold text-green-600">${formattedOfferedPrice}</div>
                                    </div>
                                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-800">
                                        <p>ยอดมัดจำ 20%: <strong class="font-bold">${formattedDeposit}</strong> บาท</p>
                                    </div>
                                </div>
                            `;
                                Swal.fire({
                                    html: proposalHtml,
                                    showConfirmButton: true,
                                    confirmButtonText: 'ปิด',
                                    width: '600px'
                                });
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถดึงข้อมูลใบเสนอราคาได้', 'error');
                        }
                    });
                });

                // --- 4. คำนวณมัดจำ 20% ---
                $('#proposal-form').on('input', '#offered_price', function() {
                    const price = parseFloat($(this).val());
                    const depositContainer = $('#deposit-calculation');
                    const depositAmountSpan = $('#deposit-amount');

                    if (price && price > 0) {
                        const deposit = price * 0.20;
                        const formattedDeposit = deposit.toLocaleString('th-TH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        depositAmountSpan.text(formattedDeposit);
                        depositContainer.slideDown();
                    } else {
                        depositContainer.slideUp();
                    }
                });

                // --- [เพิ่มโค้ดส่วนนี้] --- 
                // Clear ค่ามัดจำเมื่อปิด Modal
                $('[data-modal-hide="offerModal"], [data-dismiss="modal"], .btn-cancel, [x-show="isModalOpen"]').on('click', function() {
                    $('#deposit-calculation').hide();
                    $('#deposit-amount').text('0.00');
                });

                // --- [แก้ไข] เมื่อคลิกปุ่ม "ดูใบเสนอราคาของฉัน" ---
                $('.view-proposal-btn').on('click', function(e) {
                    e.preventDefault();
                    const requestId = $(this).data('request-id');

                    Swal.fire({
                        title: 'กำลังโหลดข้อมูล...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: '../get_proposal_details.php',
                        method: 'GET',
                        data: {
                            request_id: requestId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                const details = response.data;
                                const offeredPrice = parseFloat(details.offered_price);

                                // คำนวณมัดจำ 20%
                                const deposit = offeredPrice * 0.20;

                                // จัดรูปแบบตัวเลขทั้งหมด
                                const formattedOfferedPrice = offeredPrice.toLocaleString('th-TH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                const formattedDeposit = deposit.toLocaleString('th-TH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                const applicationDate = new Date(details.application_date).toLocaleDateString('th-TH', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });

                                // สร้าง HTML ให้เหมือนฟอร์ม
                                const proposalHtml = `
                        <div class="text-center mb-6">
                            <h3 class="text-2xl leading-6 font-bold text-gray-900">ใบเสนอราคา (ที่ยื่นไปแล้ว)</h3>
                            <p class="mt-1 text-sm text-gray-500">นี่คือรายละเอียดที่คุณได้ยื่นเสนอไป</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-6 text-left">
                            <div>
                                <p class="font-semibold text-gray-800">จาก (นักออกแบบ):</p>
                                <p><?php echo htmlspecialchars($loggedInUserName); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-800">ถึง (ผู้ว่าจ้าง):</p>
                                <p>${details.client_name || ''}</p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">เลขที่คำขอ:</p>
                                <p>#${requestId}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-800">วันที่เสนอราคา:</p>
                                <p>${applicationDate}</p>
                            </div>
                        </div>
                        <hr class="my-6">
                        <div class="space-y-5 text-left">
                            <div>
                                <label class="block text-sm font-bold text-gray-700">ชื่องาน / โปรเจกต์</label>
                                <div class="mt-1 p-3 bg-slate-100 border border-gray-300 rounded-md">${details.job_title}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">ข้อความถึงผู้ว่าจ้าง</label>
                                <div class="mt-1 p-3 bg-slate-100 border border-gray-300 rounded-md min-h-[80px]">${details.proposal_text || '<em>ไม่ได้ระบุข้อความเพิ่มเติม</em>'}</div>
                            </div>
                            <hr class="my-6 border-t-2 border-dashed">
                            <div>
                                <label class="block text-sm font-bold text-gray-700">ราคาที่เสนอ (บาท)</label>
                                <div class="mt-1 p-3 bg-slate-100 border border-gray-300 rounded-md font-bold text-green-600">${formattedOfferedPrice}</div>
                            </div>
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-800">
                                <p>ยอดมัดจำ 20%: <strong class="font-bold">${formattedDeposit}</strong> บาท</p>
                            </div>
                        </div>
                    `;

                                Swal.fire({
                                    html: proposalHtml,
                                    showConfirmButton: true,
                                    confirmButtonText: 'ปิด',
                                    width: '600px' // หรือ 'max-w-2xl'
                                });

                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถดึงข้อมูลใบเสนอราคาได้', 'error');
                        }
                    });
                });
            });
        </script>

    </body>

    </html>