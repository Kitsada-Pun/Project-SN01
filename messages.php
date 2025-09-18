<?php
session_start();
require_once 'connect.php'; // Use require_once for robustness
require_once 'includes/header.php';

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_type = $_SESSION['user_type'] ?? ''; // ดึง user_type จาก session
$to_user_id = isset($_GET['to_user']) ? (int)$_GET['to_user'] : 0;

$to_user_info = null;
if ($to_user_id > 0) {
    // Fetch the info of the person we are chatting with
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.user_type, p.profile_picture_url 
        FROM users u
        LEFT JOIN profiles p ON u.user_id = p.user_id   
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $to_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $to_user_info = $result->fetch_assoc();
    }
    $stmt->close();

    // --- [เพิ่มโค้ดส่วนนี้เข้าไป] ---
    // ดึงข้อมูลหมวดหมู่งานทั้งหมดเพื่อใช้ในฟอร์ม
    $categories = [];
    $sql_categories = "SELECT category_id, category_name FROM job_categories ORDER BY category_name ASC";
    $result_cat = $conn->query($sql_categories);
    if ($result_cat) {
        $categories = $result_cat->fetch_all(MYSQLI_ASSOC);
    }
    // --- [สิ้นสุดโค้ดที่ให้เพิ่ม] ---
}
$designer_id = $_SESSION['user_id'];
$loggedInUserName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Designer';

// --- ดึงชื่อผู้ใช้ที่ล็อกอิน ---
if (isset($_SESSION['user_id'])) {
    $loggedInUserName = $_SESSION['username'] ?? $_SESSION['full_name'] ?? '';
    if (empty($loggedInUserName)) {
        $user_id = $_SESSION['user_id'];
        $sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
        $stmt_user = $condb->prepare($sql_user);
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* --- CSS เดิมทั้งหมด --- */
        * {
            font-family: 'Kanit', sans-serif;
            font-style: normal;
            font-weight: 400;
        }

        .chat-container {
            display: flex;
            height: calc(100vh - 80px);
            /* Adjust based on your header's height */
            border: 1px solid #ddd;
            max-width: 1200px;
            margin: 10px auto;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .user-list-container {
            width: 30%;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            background-color: #f9f9f9;
        }

        .user-list-header {
            padding: 15px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            background-color: #fff;
        }

        #user-list {
            overflow-y: auto;
            flex-grow: 1;
        }

        .user-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .user-item:hover {
            background-color: #e9e9e9;
        }

        .user-item.active {
            background-color: #007bff;
            color: white;
        }

        .user-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
        }

        .unread-badge {
            background-color: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: auto;
            font-weight: bold;
        }

        .chat-area {
            width: 70%;
            display: flex;
            flex-direction: column;
        }

        #chat-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
        }

        #chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
        }

        #chat-box {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #e5ddd5;
        }

        .message {
            margin-bottom: 15px;
            max-width: 70%;
            clear: both;
        }

        .message p {
            margin: 0;
            padding: 10px 15px;
            border-radius: 18px;
            line-height: 1.4;
        }

        .message.sent {
            float: right;
        }

        .message.sent p {
            background-color: #dcf8c6;
        }

        .message.received {
            float: left;
        }

        .message.received p {
            background-color: #fff;
        }

        .message-time {
            font-size: 0.75rem;
            color: #888;
            margin: 4px 8px 0;
            display: block;
        }

        .message.sent .message-time {
            text-align: right;
        }

        .read-receipt {
            font-size: 0.75rem;
            color: #34b7f1;
            margin: 2px 8px 0;
            text-align: right;
            height: 14px;
        }

        #chat-input {
            display: flex;
            padding: 10px;
            border-top: 1px solid #ddd;
            background-color: #f0f0f0;
            align-items: center;
        }

        #message-input {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 20px;
            margin: 0 10px;
            resize: none;
        }

        #send-btn,
        #offer-btn {
            padding: 10px 15px;
            border: none;
            background-color: #007bff;
            color: white;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
        }

        #offer-btn {
            background-color: #28a745;
            /* Green color for offer button */
            margin-left: 5px;
        }

        .no-chat-selected {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: #888;
            font-size: 1.2rem;
        }

        * {
            font-family: 'Kanit', sans-serif;
            font-style: normal;
            font-weight: 400;
        }

        body {
            background-image: url('dist/img/cover.png');
            /* เปลี่ยนเป็นรูปภาพ */
            background-size: cover;
            /* ทำให้ภาพเต็มพื้นที่ */
            background-attachment: fixed;
            /* ทำให้ภาพพื้นหลังอยู่กับที่เมื่อเลื่อนเว็บ */
            background-position: center;
            /* จัดภาพให้อยู่กลาง */
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

<body>
    <nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="client/main.php">
                <img src="dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
            </a>
            <div class="space-x-4 flex items-center">
                <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                <!-- <a href="view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a> -->
                <a href="logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="chat-container">
        <div class="user-list-container">
            <div class="user-list-header">แชท</div>
            <div id="user-list"></div>
        </div>
        <div class="chat-area">
            <?php if ($to_user_info): ?>
                <div id="chat-header">
                    <img src="<?php echo htmlspecialchars(str_replace('../', '', $to_user_info['profile_picture_url'] ?? 'dist/img/avatar.png')); ?>" alt="User Avatar">
                    <strong><?php echo htmlspecialchars($to_user_info['username']); ?></strong>
                </div>
                <div id="chat-box"></div>
                <div id="chat-input">
                    <?php if ($current_user_type === 'client' && isset($to_user_info['user_type']) && $to_user_info['user_type'] === 'designer'): ?>
                        <button id="offer-btn" title="ยื่นข้อเสนอ"><i class="fas fa-file-signature"></i></button>
                    <?php endif; ?>

                    <textarea id="message-input" placeholder="Type a message..." rows="1"></textarea>
                    <button id="send-btn"><i class="fas fa-paper-plane"></i></button>
                </div>
            <?php else: ?>
                <div class="no-chat-selected">
                    เลือกแชทเพื่อเริ่มสนทนา
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="offerModal" class="modal hidden fixed z-[100] inset-0 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-800 opacity-75"></div>
            </div>

            <div class="inline-block align-bottom bg-gray-50 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full" role="dialog" aria-modal="true">
                <form id="offer-form" enctype="multipart/form-data">
                    <div class="px-4 pt-5 pb-4 sm:p-6">
                        <div class="text-center">
                            <h3 class="text-2xl leading-6 font-bold text-gray-900">
                                ส่งคำขอจ้างงาน
                            </h3>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-4 text-sm text-graฟy-600">
                            <div>
                                <p class="font-semibold text-gray-800">จาก:</p>
                                <p><?php echo htmlspecialchars($loggedInUserName); ?></p>
                                <p>ผู้ว่าจ้าง</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-800">ถึง:</p>
                                <p><?php echo htmlspecialchars($to_user_info['username'] ?? 'Designer'); ?></p>
                                <p>นักออกแบบ</p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">เลขที่ใบเสนอราคา:</p>
                                <p><?php echo uniqid('QT-'); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-800">วันที่เสนอราคา:</p>
                                <p><?php echo date("d / m / Y"); ?></p>
                            </div>
                        </div>

                        <hr class="my-5">

                        <div class="space-y-4">
                            <input type="hidden" name="designer_id" value="<?php echo $to_user_id; ?>">

                            <div>
                                <label for="offer-title" class="block text-sm font-medium text-gray-700">ชื่องาน / โปรเจกต์</label>
                                <input type="text" id="offer-title" name="title" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="offer-category" class="block text-sm font-medium text-gray-700">ประเภทงาน</label>
                                <select id="offer-category" name="category_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">-- กรุณาเลือกประเภทงาน --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="offer-description" class="block text-sm font-medium text-gray-700">รายละเอียดขอบเขตงาน (Scope of Work)</label>
                                <textarea id="offer-description" name="description" rows="4" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="ระบุรายละเอียดงานที่ต้องการให้ชัดเจน..."></textarea>
                            </div>
                            <div>
                                <label for="offer-attachment" class="block text-sm font-medium text-gray-700">แนบไฟล์ตัวอย่าง (ถ้ามี)</label>
                                <input type="file" id="offer-attachment" name="attachment" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                                <p class="mt-1 text-xs text-gray-500">อนุญาตไฟล์ประเภท: JPG, PNG, PDF, ZIP (สูงสุด 5MB)</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="offer-budget" class="block text-sm font-medium text-gray-700">งบประมาณ (บาท)</label>
                                    <input type="number" id="offer-budget" name="budget" min="0" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="0">
                                </div>
                                <div>
                                    <label for="offer-deadline" class="block text-sm font-medium text-gray-700">ส่งมอบงานภายในวันที่</label>
                                    <input type="date" id="offer-deadline" name="deadline" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-100 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse rounded-b-lg">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-paper-plane mr-2"></i>ส่งใบเสนอราคา
                        </button>
                        <button type="button" class="btn-cancel mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                            ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            const currentUserId = <?php echo json_encode($current_user_id); ?>;
            const toUserId = <?php echo json_encode($to_user_id); ?>;
            let isLoadingMessages = false;

            function updateActivity() {
                $.ajax({
                    url: 'update_activity.php',
                    method: 'POST'
                });
            }

            function loadUsers() {
                $.ajax({
                    url: 'get_users.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(users) {
                        const userList = $('#user-list');
                        userList.empty();
                        users.forEach(user => {
                            const activeClass = user.user_id == toUserId ? 'active' : '';
                            const profilePic = user.profile_picture_url ? user.profile_picture_url.replace('../', '') : 'dist/img/avatar.png';
                            const unreadBadge = user.unread_count > 0 ? `<span class="unread-badge">${user.unread_count}</span>` : '';
                            const userElement = `
                        <div class="user-item ${activeClass}" data-userid="${user.user_id}">
                            <img src="${profilePic}" alt="${user.username}">
                            <span>${user.username}</span>
                            ${unreadBadge}
                        </div>`;
                            userList.append(userElement);
                        });
                    }
                });
            }

            function loadMessages() {
                if (!toUserId || isLoadingMessages) return;
                isLoadingMessages = true;
                $.ajax({
                    url: `fetch_messages.php?to_user=${toUserId}`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(messages) {
                        const chatBox = $('#chat-box');
                        const shouldScroll = chatBox[0].scrollHeight - chatBox.scrollTop() <= chatBox.outerHeight() + 50;
                        chatBox.empty();

                        messages.forEach((msg, index) => {
                            const messageClass = msg.from_user_id == currentUserId ? 'sent' : 'received';
                            // The message content is now trusted to be safe from the backend
                            const messageElement = `
                        <div class="message ${messageClass}">
                            <p>${msg.message}</p> 
                            <div class="message-time">${msg.time}</div>
                        </div>`;
                            chatBox.append(messageElement);
                        });

                        if (shouldScroll) {
                            chatBox.scrollTop(chatBox[0].scrollHeight);
                        }
                    },
                    complete: function() {
                        isLoadingMessages = false;
                    }
                });
            }

            function sendMessage() {
                const messageInput = $('#message-input');
                const message = messageInput.val().trim();
                if (message === '' || !toUserId) return;

                $('#send-btn').prop('disabled', true);
                $.ajax({
                    url: 'send_message.php',
                    method: 'POST',
                    data: {
                        to_user_id: toUserId,
                        message: message
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            messageInput.val('').css('height', 'auto');
                            loadMessages();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'An error occurred.', 'error');
                    },
                    complete: function() {
                        $('#send-btn').prop('disabled', false);
                    }
                });
            }

            // --- Event Handlers ---
            $('#user-list').on('click', '.user-item', function() {
                window.location.href = 'messages.php?to_user=' + $(this).data('userid');
            });

            $('#send-btn').click(sendMessage);
            $('#message-input').on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            }).keypress(function(e) {
                if (e.which == 13 && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // --- Offer Modal Logic ---
            const modal = $('#offerModal');
            $('#offer-btn').on('click', function() {
                const today = new Date().toISOString().split('T')[0];
                $('#offer-deadline').attr('min', today);
                modal.removeClass('hidden');
            });

            $('.btn-cancel').on('click', function() {
                modal.addClass('hidden');
                $('#offer-form')[0].reset();
            });

            // จัดการการส่งข้อมูลฟอร์ม
            $('#offer-form').on('submit', function(e) {
                e.preventDefault();

                // ใช้ FormData เพื่อให้ส่งข้อมูลและไฟล์ได้
                const formData = new FormData(this);

                // แสดงสถานะกำลังโหลด...
                Swal.fire({
                    title: 'กำลังส่งข้อเสนอ...',
                    text: 'กรุณารอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'submit_offer.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    contentType: false, // << สำคัญมาก: ไม่ต้องตั้งค่า content-type header
                    processData: false, // << สำคัญมาก: ไม่ต้องประมวลผลข้อมูล
                    success: function(response) {
                        if (response.status === 'success') {
                            modal.addClass('hidden');
                            $('#offer-form')[0].reset();
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            setTimeout(loadMessages, 500);
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                    }
                });
            });

            // --- [แก้ไข] Event Listener สำหรับคลิกลิงก์ดูรายละเอียด ---
            $('#chat-box').on('click', '.view-request-details', function(e) {
                e.preventDefault();
                const requestId = $(this).data('request-id');

                $.ajax({
                    url: 'get_request_details.php',
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

                            // สร้างส่วนแสดงผลไฟล์แนบ
                            let attachmentHtml = '';
                            if (details.attachment_path && details.attachment_path.trim() !== '') {

                                // ---- [ส่วนที่ปรับแก้] ทำให้รองรับ cả path เก่าและใหม่ ----
                                // ไม่ว่า path จะเป็น '../uploads/...' หรือ 'uploads/...' ก็จะแสดงผลถูกต้อง
                                const filePath = details.attachment_path.startsWith('../') ?
                                    details.attachment_path.substring(3) :
                                    details.attachment_path;

                                attachmentHtml = `
                        <hr style="margin: 1rem 0;">
                        <p><strong>ไฟล์แนบ:</strong></p>
                        <a href="${filePath}" target="_blank">
                            <img src="${filePath}" alt="ไฟล์แนบ" style="max-width: 100%; max-height: 250px; margin-top: 5px; border-radius: 5px; border: 1px solid #ddd;">
                        </a>
                    `;
                            }

                            Swal.fire({
                                title: `<strong>รายละเอียดคำขอจ้างงาน</strong>`,
                                html: `
                        <div style="text-align: left; padding: 0 1rem;">
                            <p><strong>ชื่องาน:</strong> ${details.title}</p>
                            <p><strong>ประเภทงาน:</strong> ${details.category_name || 'ไม่ได้ระบุ'}</p>
                            <p><strong>รายละเอียด:</strong></p>
                            <div style="white-space: pre-wrap; background-color: #f9f9f9; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">${details.description}</div>

                            ${attachmentHtml}

                            <hr style="margin: 1rem 0;">
                            <p><strong>งบประมาณ:</strong> ${Number(details.budget).toLocaleString('th-TH')} บาท</p>
                            <p><strong>ส่งมอบงานภายใน:</strong> ${deadline}</p>
                        </div>`,
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

            // --- Initial Load & Periodic Refreshing ---
            loadUsers();
            updateActivity();
            if (toUserId > 0) {
                loadMessages();
                setInterval(loadMessages, 3000);
                setInterval(loadUsers, 5000);
            }
            setInterval(updateActivity, 60000);
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>

</html>