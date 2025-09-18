<?php
session_start();
require_once '../connect.php'; // Path to connect.php is one level up
require_once '../includes/header.php'; // Path to header.php is one level up

// Redirect if not logged in or not a designer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'designer') {
    header("Location: ../login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$to_user_id = isset($_GET['to_user']) ? (int)$_GET['to_user'] : 0;

$to_user_info = null;
if ($to_user_id > 0) {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, p.profile_picture_url 
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
}

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
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* --- ใช้ CSS ชุดเดียวกับของ Client ได้เลย --- */
        body { font-family: 'Kanit', sans-serif; margin: 0; background-image: url('../dist/img/cover.png'); /* เปลี่ยนเป็นรูปภาพ */
    background-size: cover; /* ทำให้ภาพเต็มพื้นที่ */
    background-attachment: fixed; /* ทำให้ภาพพื้นหลังอยู่กับที่เมื่อเลื่อนเว็บ */
    background-position: center; /* จัดภาพให้อยู่กลาง */
    color: #2c3e50;
    overflow-x: hidden; }
        .chat-container { display: flex; height: calc(100vh - 80px); border: 1px solid #ddd; max-width: 1200px; margin: 10px auto; background-color: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .user-list-container { width: 30%; border-right: 1px solid #ddd; display: flex; flex-direction: column; background-color: #f9f9f9; }
        .user-list-header { padding: 15px; font-weight: bold; border-bottom: 1px solid #ddd; background-color: #fff; }
        #user-list { overflow-y: auto; flex-grow: 1; }
        .user-item { display: flex; align-items: center; padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #eee; transition: background-color 0.2s; }
        .user-item:hover { background-color: #e9e9e9; }
        .user-item.active { background-color: #007bff; color: white; }
        .user-item img { width: 40px; height: 40px; border-radius: 50%; margin-right: 12px; object-fit: cover; }
        .unread-badge { background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; margin-left: auto; font-weight: bold; }
        .chat-area { width: 70%; display: flex; flex-direction: column; }
        #chat-header { padding: 15px; border-bottom: 1px solid #ddd; display: flex; align-items: center; }
        #chat-header img { width: 40px; height: 40px; border-radius: 50%; margin-right: 12px; object-fit: cover; }
        #chat-box { flex-grow: 1; padding: 20px; overflow-y: auto; background-color: #e5ddd5; }
        .message { margin-bottom: 15px; max-width: 70%; clear: both; display: flex; flex-direction: column; }
        .message p { margin: 0; padding: 10px 15px; border-radius: 18px; line-height: 1.4; word-wrap: break-word; }
        .message.sent { float: right; align-items: flex-end; }
        .message.sent p { background-color: #dcf8c6; }
        .message.received { float: left; align-items: flex-start; }
        .message.received p { background-color: #fff; }
        .message-time { font-size: 0.75rem; color: #888; margin: 4px 8px 0; display: block; }
        .read-receipt { font-size: 0.75rem; color: #34b7f1; margin: 2px 8px 0; text-align: right; height: 14px; }
        #chat-input { display: flex; padding: 10px; border-top: 1px solid #ddd; background-color: #f0f0f0; }
        #message-input { flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 20px; margin-right: 10px; resize: none; }
        #send-btn { padding: 10px 15px; border: none; background-color: #007bff; color: white; border-radius: 50%; cursor: pointer; font-size: 1.2rem; }
        .no-chat-selected { display: flex; justify-content: center; align-items: center; height: 100%; color: #888; font-size: 1.2rem; }
        
    </style>
</head>
<body>
<nav class="bg-white/80 backdrop-blur-sm p-4 shadow-md sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="main.php">
                <img src="../dist/img/logo.png" alt="PixelLink Logo" class="h-12 transition-transform hover:scale-105">
            </a>
            <div class="space-x-4 flex items-center">
                <span class="font-medium text-slate-700">สวัสดี, <?= htmlspecialchars($loggedInUserName) ?>!</span>
                <a href="view_profile.php?user_id=<?= $_SESSION['user_id']; ?>" class="btn-primary text-white px-5 py-2 rounded-lg font-medium shadow-md">ดูโปรไฟล์</a>
                <a href="../logout.php" class="btn-danger text-white px-5 py-2 rounded-lg font-medium shadow-md">ออกจากระบบ</a>
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
                <img src="../<?php echo htmlspecialchars(str_replace('../', '', $to_user_info['profile_picture_url'] ?? 'dist/img/avatar.png')); ?>" alt="User Avatar">
                <strong><?php echo htmlspecialchars($to_user_info['username']); ?></strong>
            </div>
            <div id="chat-box"></div>
            <div id="chat-input">
                <input id="message-input" placeholder="Type a message..."></input>
                <button id="send-btn"><i class="fas fa-paper-plane"></i></button>
            </div>
        <?php else: ?>
            <div class="no-chat-selected">เลือกแชทเพื่อเริ่มสนทนา</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    const currentUserId = <?php echo json_encode($current_user_id); ?>;
    const toUserId = <?php echo json_encode($to_user_id); ?>;

    function loadUsers() {
        $.ajax({
            url: '../get_users.php', // Use the same script
            method: 'GET',
            dataType: 'json',
            success: function(users) {
                const userList = $('#user-list');
                userList.empty();
                users.forEach(user => {
                    const activeClass = user.user_id == toUserId ? 'active' : '';
                    const profilePic = user.profile_picture_url ? '../' + user.profile_picture_url.replace('../', '') : '../dist/img/avatar.png';
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
        if (!toUserId) return;
        $.ajax({
            url: `../fetch_messages.php?to_user=${toUserId}`, // Use the same script
            method: 'GET',
            dataType: 'json',
            success: function(messages) {
                const chatBox = $('#chat-box');
                chatBox.empty();
                let lastSentMessageIndex = -1;
                messages.forEach((msg, index) => {
                    const messageClass = msg.from_user_id == currentUserId ? 'sent' : 'received';
                    if (messageClass === 'sent') {
                        lastSentMessageIndex = index;
                    }
                    const messageElement = `
                        <div class="message ${messageClass}" id="msg-${index}">
                            <p>${msg.message.replace(/\\n/g, '<br>')}</p>
                            <div class="message-time">${msg.time}</div>
                            ${messageClass === 'sent' ? '<div class="read-receipt"></div>' : ''}
                        </div>`;
                    chatBox.append(messageElement);
                });

                if (lastSentMessageIndex !== -1 && messages[lastSentMessageIndex].is_read == 1) {
                    $(`#msg-${lastSentMessageIndex} .read-receipt`).text('อ่านแล้ว');
                }
                chatBox.scrollTop(chatBox[0].scrollHeight);
            }
        });
    }

    function sendMessage() {
        const messageInput = $('#message-input');
        const message = messageInput.val().trim();
        if (message === '' || !toUserId) return;

        $.ajax({
            url: '../send_message.php', // Use the same script
            method: 'POST',
            data: { to_user_id: toUserId, message: message },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    messageInput.val('');
                    loadMessages();
                } else {
                    alert('Error: ' + (response.message || 'Could not send message.'));
                }
            }
        });
    }

    $('#user-list').on('click', '.user-item', function() {
        window.location.href = 'messages.php?to_user=' + $(this).data('userid');
    });

    $('#send-btn').click(sendMessage);
    $('#message-input').keypress(function(e) {
        if (e.which == 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    loadUsers();
    if (toUserId > 0) {
        loadMessages();
        setInterval(loadMessages, 3000);
        setInterval(loadUsers, 5000);
    }
});
</script>
</body>
<br>
<?php include '../includes/footer.php'; ?>
</html>