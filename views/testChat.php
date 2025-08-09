<?php
// Path: views/testChat.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/ChatController.php';
require_once __DIR__ . '/../controllers/UserController.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /network-management/views/auth/login.php");
    exit();
}

$userModel = new UserModel($conn);
$userId = $_SESSION['user_id'];

// Lấy thông tin người dùng
$query = "SELECT user_id, fullname, profile_picture, status FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData) {
    session_destroy();
    header("Location: /network-management/views/auth/login.php?error=user_not_found");
    exit();
}

$userController = new UserController();
$users = $userController->getUsers(['status' => 'active']);

// Tạo mảng tạm để sắp xếp theo thời gian tin nhắn gần nhất
$usersWithLastMessage = [];
foreach ($users as $user) {
    if ($user['user_id'] == $userData['user_id']) continue;
    $lastMessage = $userModel->getLastMessage($userData['user_id'], $user['user_id']);
    $usersWithLastMessage[] = [
        'user' => $user,
        'last_message_time' => $lastMessage ? strtotime($lastMessage['created_at']) : 0
    ];
}

// Sắp xếp mảng theo thời gian tin nhắn (giảm dần)
usort($usersWithLastMessage, function ($a, $b) {
    return $b['last_message_time'] - $a['last_message_time'];
});

// Gán lại $users sau khi sắp xếp
$users = array_column($usersWithLastMessage, 'user');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $postData = !empty($input) ? json_decode($input, true) : $_POST;
    $action = $postData['action'] ?? $_POST['action'] ?? null;
    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing action']);
        exit;
    }
    $chatController = new ChatController($userId);
    error_log("POST action: $action, data: " . json_encode($postData));
    switch ($action) {
        case 'getMessages':
            echo $chatController->getMessages();
            exit;
        case 'storeMessage':
            echo $chatController->storeMessage($postData);
            exit;
        case 'deleteMessage':
            echo $chatController->deleteMessage();
            exit;
        case 'getUserList':
            $usersWithLastMessage = [];
            foreach ($users as $user) {
                if ($user['user_id'] == $userData['user_id']) continue;
                $lastMessage = $userModel->getLastMessage($userData['user_id'], $user['user_id']);
                $plainTextContent = $lastMessage ? strip_tags($lastMessage['content']) : null;
                $usersWithLastMessage[] = [
                    'user' => $user,
                    'last_message' => $lastMessage ? [
                        'content' => $plainTextContent,
                        'created_at' => $lastMessage['created_at']
                    ] : null,
                    'last_message_time' => $lastMessage ? strtotime($lastMessage['created_at']) : 0
                ];
            }
            usort($usersWithLastMessage, function ($a, $b) {
                return $b['last_message_time'] - $a['last_message_time'];
            });
            echo json_encode(['success' => true, 'data' => $usersWithLastMessage]);
            exit;
        case 'checkNewMessages':
            $lastMessageTimes = [];
            foreach ($users as $user) {
                if ($user['user_id'] == $userData['user_id']) continue;
                $lastMessage = $userModel->getLastMessage($userData['user_id'], $user['user_id']);
                $lastMessageTimes[$user['user_id']] = $lastMessage ? strtotime($lastMessage['created_at']) : 0;
            }
            echo json_encode(['success' => true, 'data' => $lastMessageTimes]);
            exit;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $chatController = new ChatController($userId);
    if ($action === 'getMessages') {
        echo $chatController->getMessages();
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="POS - Bootstrap Admin Template">
    <meta name="keywords"
        content="admin, estimates, bootstrap, business, corporate, creative, management, minimal, modern, html5, responsive">
    <meta name="author" content="Dreamguys - Bootstrap Admin Template">
    <meta name="robots" content="noindex, nofollow">
    <title>Tin nhắn hệ thống</title>

    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css">
    <style>
    /* Định dạng chung cho tin nhắn */
    .chat-message-right,
    .chat-message-left {
        display: flex;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    /* Tin nhắn bên phải (người gửi) */
    .chat-message-right {
        justify-content: flex-end;
    }

    .chat-message-right .message-content {
        max-width: 70%;
        text-align: right;
    }

    .chat-message-right .message-bubble {
        background-color: #e6f0fa;
        color: #333;
        border-radius: 15px;
        padding: 8px 12px;
        display: inline-block;
        word-wrap: break-word;
    }

    .chat-message-right .sender-time {
        font-size: 0.75rem;
        color: #666;
        margin-top: 5px;
        text-align: right;
    }

    /* Tin nhắn bên trái (người phản hồi) */
    .chat-message-left {
        justify-content: flex-start;
    }

    .chat-message-left .avatar {
        flex-shrink: 0;
    }

    .chat-message-left .message-content {
        max-width: 70%;
    }

    .chat-message-left .message-bubble {
        background-color: #f1f1f1;
        color: #333;
        border-radius: 15px;
        padding: 8px 12px;
        display: inline-block;
        word-wrap: break-word;
    }

    .chat-message-left .receiver-time {
        font-size: 0.75rem;
        color: #666;
        margin-top: 5px;
    }

    /* Đảm bảo khu vực tin nhắn có thể cuộn */
    .msg_card_body {
        min-height: 400px;
        max-height: 500px;
        overflow-y: auto;
    }

    /* Định dạng Summernote trong tin nhắn */
    .message-bubble p {
        margin: 0;
    }

    .message-bubble b,
    .message-bubble strong {
        font-weight: bold;
    }

    .message-bubble i,
    .message-bubble em {
        font-style: italic;
    }

    .message-bubble u {
        text-decoration: underline;
    }

    .message-bubble ul,
    .message-bubble ol {
        margin: 5px 0;
        padding-left: 20px;
    }

    .message-bubble a {
        color: #007bff;
        text-decoration: underline;
    }

    .message-bubble table {
        border-collapse: collapse;
        margin: 5px 0;
    }

    .message-bubble th,
    .message-bubble td {
        border: 1px solid #ddd;
        padding: 5px;
    }

    /* Đảm bảo avatar căn chỉnh đúng */
    .avatar {
        flex-shrink: 0;
    }

    .mr-2 {
        margin-right: 0.5rem;
    }

    .ml-2 {
        margin-left: 0.5rem;
    }
    </style>
</head>

<body>
    <div id="global-loader">
        <div class="whirly-loader"></div>
    </div>

    <div class="main-wrapper">
        <?php include(__DIR__ . '/../includes/header.php'); ?>
        <?php include(__DIR__ . '/../includes/sidebarAll.php'); ?>
        <div class="page-wrapper">
            <div class="content">
                <div class="col-lg-12">
                    <div class="row chat-window">
                        <div class="col-lg-5 col-xl-4 chat-cont-left">
                            <div class="card mb-sm-3 mb-md-0 contacts_card flex-fill">
                                <div class="card-header chat-search">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="search_btn"><i class="fas fa-search"></i></span>
                                        </div>
                                        <input type="text" placeholder="Search"
                                            class="form-control search-chat rounded-pill">
                                    </div>
                                </div>
                                <div class="card-body contacts_body chat-users-list chat-scroll">
                                    <?php foreach ($users as $user) {
                                        if ($user['user_id'] == $userData['user_id']) continue;
                                        $lastMessage = $userModel->getLastMessage($userData['user_id'], $user['user_id']);
                                    ?>
                                    <a href="javascript:void(0);" class="media d-flex"
                                        data-user-id="<?php echo htmlspecialchars($user['user_id']); ?>"
                                        data-user-name="<?php echo htmlspecialchars($user['fullname']); ?>"
                                        data-user-picture="<?php echo htmlspecialchars($user['profile_picture'] ?? 'images/default.jpg'); ?>">
                                        <div class="media-img-wrap flex-shrink-0">
                                            <div
                                                class="avatar avatar-<?php echo $user['status'] == 'active' ? 'online' : 'offline'; ?>">
                                                <img src="../assets/<?php echo htmlspecialchars($user['profile_picture'] ?? 'images/default.jpg'); ?>"
                                                    onerror="this.src='../assets/images/default.jpg'" alt="User Image"
                                                    class="avatar-img rounded-circle">
                                            </div>
                                        </div>
                                        <div class="media-body flex-grow-1">
                                            <div>
                                                <div class="user-name">
                                                    <?php echo htmlspecialchars($user['fullname']); ?></div>
                                                <div class="user-last-chat">
                                                    <?php echo $lastMessage ? strip_tags($lastMessage['content']) : 'No messages yet'; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="last-chat-time">
                                                    <?php echo $lastMessage ? date('h:i A', strtotime($lastMessage['created_at'])) : ''; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7 col-xl-8 chat-cont-right">
                            <div class="card mb-0">
                                <div class="card-header msg_head">
                                    <div class="d-flex bd-highlight">
                                        <a id="back_user_list" href="javascript:void(0)" class="back-user-list">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                        <div class="img_cont">
                                            <img class="rounded-circle user_img" src="../assets/images/default.jpg"
                                                id="receiver_image" onerror="this.src='../assets/images/default.jpg'"
                                                alt="Receiver Image">
                                        </div>
                                        <div class="user_info">
                                            <span><strong id="receiver_name" data-user_id="0">Select a
                                                    user</strong></span>
                                            <p class="mb-0">Messages</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body msg_card_body chat-scroll">
                                    <ul class="list-unstyled"></ul>
                                </div>
                                <div class="card-footer">
                                    <div class="input-group">
                                        <textarea class="form-control type_msg mh-auto empty_check" id="summernote"
                                            placeholder="Type your message..."></textarea>
                                        <button class="btn btn-primary btn_send"><i class="fa fa-paper-plane"
                                                aria-hidden="true"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="searchpart">
        <div class="searchcontent">
            <div class="searchhead">
                <h3>Search</h3>
                <a id="closesearch"><i class="fa fa-times-circle" aria-hidden="true"></i></a>
            </div>
            <div class="searchcontents">
                <div class="searchparts">
                    <input type="text" placeholder="search here">
                    <a class="btn btn-searchs">Search</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous">
    </script>
    <script src="/network-management/assets/js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script>
    <script>
    $(document).ready(function() {
        // Kiểm tra thư viện
        if (typeof jQuery === 'undefined') {
            console.error('jQuery không tải được');
            alert('Lỗi: Không tải được jQuery');
            return;
        }
        if (typeof $.fn.summernote === 'undefined') {
            console.error('Summernote không tải được');
            alert('Lỗi: Không tải được Summernote');
            return;
        }
        feather.replace();

        // Khởi tạo Summernote với toolbar giới hạn
        $('#summernote').summernote({
            placeholder: 'Type your message...',
            tabsize: 2,
            height: 100,
            toolbar: [
                ['font', ['bold', 'underline', 'italic']],
                ['para', ['ul', 'ol']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview']]
            ],
            callbacks: {
                onInit: function() {
                    console.log('Summernote khởi tạo thành công');
                },
                onChange: function(contents) {
                    console.log('Summernote nội dung:', contents);
                }
            }
        });

        // Tìm kiếm người dùng
        $('.search-chat').on('input', function() {
            var search = $(this).val().toLowerCase();
            $('.chat-users-list .media').each(function() {
                var name = $(this).data('user-name').toLowerCase();
                $(this).toggle(name.includes(search));
            });
        });

        // Hàm hiển thị tin nhắn
        function displayMessages(messages) {
            var userId = <?php echo json_encode($userData['user_id']); ?>;
            var $messageList = $('.msg_card_body ul').empty();
            if (messages.length === 0) {
                $messageList.append('<li class="text-center">No messages yet</li>');
                return;
            }
            messages.forEach(function(msg) {
                var isSender = msg.sender_id == userId;
                var messageClass = isSender ? 'chat-message-right' : 'chat-message-left';
                var time = new Date(msg.created_at).toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                var senderImage = isSender ?
                    '../assets/<?php echo htmlspecialchars($userData['profile_picture'] ?? 'images/default.jpg'); ?>' :
                    $('#receiver_image').attr('src');
                var messageHtml = `
            <li class="${messageClass} mb-3">
                ${!isSender ? `
                <div class="avatar mr-2">
                    <img src="${senderImage}" 
                         class="rounded-circle" alt="User Image" width="40" height="40" 
                         onerror="this.src='../assets/images/default.jpg'">
                </div>` : ''}
                <div class="message-content">
                    <div class="message-bubble">
                        <div class="message-text">${msg.content}</div>
                        <div class="message-time ${isSender ? 'sender-time' : 'receiver-time'}">${time}</div>
                    </div>
                </div>
                ${isSender ? `
                <div class="avatar ml-2">
                    <img src="${senderImage}" 
                         class="rounded-circle" alt="User Image" width="40" height="40" 
                         onerror="this.src='../assets/images/default.jpg'">
                </div>` : ''}
            </li>`;
                $messageList.append(messageHtml);
            });
            $('.msg_card_body').scrollTop($('.msg_card_body')[0].scrollHeight);
        }

        // Hàm cập nhật danh sách người dùng
        function updateUserList() {
            var userId = <?php echo json_encode($userData['user_id']); ?>;
            var activeUserId = $('.chat-users-list .media.active').data('user-id') || null;

            $.ajax({
                url: 'testChat.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'getUserList'
                }),
                success: function(response) {
                    console.log('Phản hồi từ getUserList:', response);
                    try {
                        var data = JSON.parse(response);
                        if (data.success && data.data) {
                            var $userList = $('.chat-users-list').empty();
                            data.data.forEach(function(item) {
                                var user = item.user;
                                var lastMessage = item.last_message;
                                var isActive = user.user_id == activeUserId ? 'active' : '';
                                var userHtml = `
                            <a href="javascript:void(0);" class="media d-flex ${isActive}"
                                data-user-id="${user.user_id}"
                                data-user-name="${user.fullname}"
                                data-user-picture="${user.profile_picture || 'images/default.jpg'}">
                                <div class="media-img-wrap flex-shrink-0">
                                    <div class="avatar avatar-${user.status == 'active' ? 'online' : 'offline'}">
                                        <img src="../assets/${user.profile_picture || 'images/default.jpg'}"
                                            onerror="this.src='../assets/images/default.jpg'" alt="User Image"
                                            class="avatar-img rounded-circle">
                                    </div>
                                </div>
                                <div class="media-body flex-grow-1">
                                    <div>
                                        <div class="user-name">${user.fullname}</div>
                                        <div class="user-last-chat">
                                            ${lastMessage ? lastMessage.content : 'No messages yet'}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="last-chat-time">
                                            ${lastMessage ? new Date(lastMessage.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
                                        </div>
                                    </div>
                                </div>
                            </a>`;
                                $userList.append(userHtml);
                            });

                            // Gắn lại sự kiện click cho danh sách người dùng
                            $('.chat-users-list .media').off('click').on('click', function() {
                                var userId =
                                    <?php echo json_encode($userData['user_id']); ?>;
                                var recipientId = $(this).data('user-id');
                                var recipientName = $(this).data('user-name');
                                var recipientPicture = $(this).data('user-picture');

                                $('#receiver_name').text(recipientName).data('user_id',
                                    recipientId);
                                $('#receiver_image').attr('src', '../assets/' +
                                    recipientPicture);

                                $('.chat-users-list .media').removeClass('active');
                                $(this).addClass('active');

                                $.ajax({
                                    url: 'testChat.php',
                                    type: 'GET',
                                    data: {
                                        action: 'getMessages',
                                        user_id: userId,
                                        recipient_id: recipientId
                                    },
                                    beforeSend: function() {
                                        $('.msg_card_body ul').html(
                                            '<li class="text-center">Loading messages...</li>'
                                        );
                                    },
                                    success: function(response) {
                                        console.log('Phản hồi từ getMessages:',
                                            response);
                                        try {
                                            var data = JSON.parse(response);
                                            if (data.success && data.data &&
                                                data.data.messages) {
                                                displayMessages(data.data
                                                    .messages);
                                            } else {
                                                $('.msg_card_body ul').empty()
                                                    .append(
                                                        '<li class="text-center">No messages yet</li>'
                                                    );
                                                console.warn(
                                                    'Không có tin nhắn:',
                                                    data.message);
                                            }
                                        } catch (e) {
                                            console.error('Lỗi phân tích JSON:',
                                                e, 'Phản hồi:', response);
                                            alert(
                                                'Lỗi khi tải tin nhắn: Phản hồi không hợp lệ'
                                            );
                                            $('.msg_card_body ul').empty()
                                                .append(
                                                    '<li class="text-center">Lỗi khi tải tin nhắn</li>'
                                                );
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('Lỗi khi tải tin nhắn:',
                                            error, 'Mã trạng thái:', xhr
                                            .status);
                                        alert('Lỗi khi tải tin nhắn: ' + xhr
                                            .status);
                                        $('.msg_card_body ul').empty().append(
                                            '<li class="text-center">Lỗi khi tải tin nhắn</li>'
                                        );
                                    }
                                });
                            });
                        } else {
                            console.warn('Không có danh sách người dùng:', data.message);
                        }
                    } catch (e) {
                        console.error('Lỗi phân tích JSON:', e, 'Phản hồi:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Lỗi khi tải danh sách người dùng:', error, 'Mã trạng thái:', xhr
                        .status);
                }
            });
        }

        // Hàm kiểm tra tin nhắn mới
        function checkNewMessages() {
            var userId = <?php echo json_encode($userData['user_id']); ?>;
            var lastCheckedTimes = {};

            $.ajax({
                url: 'testChat.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'checkNewMessages'
                }),
                success: function(response) {
                    console.log('Phản hồi từ checkNewMessages:', response);
                    try {
                        var data = JSON.parse(response);
                        if (data.success && data.data) {
                            var hasNewMessage = false;
                            $.each(data.data, function(userId, lastMessageTime) {
                                if (!lastCheckedTimes[userId] || lastMessageTime > (
                                        lastCheckedTimes[userId] || 0)) {
                                    hasNewMessage = true;
                                    lastCheckedTimes[userId] = lastMessageTime;
                                }
                            });
                            if (hasNewMessage) {
                                updateUserList();
                            }
                        }
                    } catch (e) {
                        console.error('Lỗi phân tích JSON:', e, 'Phản hồi:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Lỗi khi kiểm tra tin nhắn mới:', error, 'Mã trạng thái:', xhr
                        .status);
                }
            });
        }

        // Bắt đầu polling mỗi 5 giây
        setInterval(checkNewMessages, 5000);

        // Nhấp vào người dùng
        $('.chat-users-list .media').off('click').on('click', function() {
            var userId = <?php echo json_encode($userData['user_id']); ?>;
            var recipientId = $(this).data('user-id');
            var recipientName = $(this).data('user-name');
            var recipientPicture = $(this).data('user-picture');

            $('#receiver_name').text(recipientName).data('user_id', recipientId);
            $('#receiver_image').attr('src', '../assets/' + recipientPicture);

            $('.chat-users-list .media').removeClass('active');
            $(this).addClass('active');

            $.ajax({
                url: 'testChat.php',
                type: 'GET',
                data: {
                    action: 'getMessages',
                    user_id: userId,
                    recipient_id: recipientId
                },
                beforeSend: function() {
                    $('.msg_card_body ul').html(
                        '<li class="text-center">Loading messages...</li>');
                },
                success: function(response) {
                    console.log('Phản hồi từ getMessages:', response);
                    try {
                        var data = JSON.parse(response);
                        if (data.success && data.data && data.data.messages) {
                            displayMessages(data.data.messages);
                        } else {
                            $('.msg_card_body ul').empty().append(
                                '<li class="text-center">No messages yet</li>');
                            console.warn('Không có tin nhắn:', data.message);
                        }
                    } catch (e) {
                        console.error('Lỗi phân tích JSON:', e, 'Phản hồi:', response);
                        alert('Lỗi khi tải tin nhắn: Phản hồi không hợp lệ');
                        $('.msg_card_body ul').empty().append(
                            '<li class="text-center">Lỗi khi tải tin nhắn</li>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Lỗi khi tải tin nhắn:', error, 'Mã trạng thái:', xhr
                        .status);
                    alert('Lỗi khi tải tin nhắn: ' + xhr.status);
                    $('.msg_card_body ul').empty().append(
                        '<li class="text-center">Lỗi khi tải tin nhắn</li>');
                }
            });
        });

        // Gửi tin nhắn
        $('.btn_send').off('click').on('click', function() {
            var userId = <?php echo json_encode($userData['user_id']); ?>;
            var recipientId = $('#receiver_name').data('user_id');
            var content = $('#summernote').summernote('code').trim();

            if (!recipientId || recipientId == 0) {
                alert('Vui lòng chọn người nhận');
                return;
            }
            if (!content || $('#summernote').summernote('isEmpty')) {
                alert('Vui lòng nhập nội dung tin nhắn');
                return;
            }

            $.ajax({
                url: 'testChat.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'storeMessage',
                    sender_id: userId,
                    recipient_id: recipientId,
                    content: content
                }),
                beforeSend: function() {
                    $('.btn_send').prop('disabled', true).html(
                        '<i class="fa fa-spinner fa-spin"></i>');
                },
                success: function(response) {
                    console.log('Phản hồi từ storeMessage:', response);
                    try {
                        var data = JSON.parse(response);
                        if (data.success) {
                            $('#summernote').summernote('reset');
                            $('.chat-users-list .media.active').trigger('click');
                            updateUserList();
                        } else {
                            alert('Lỗi khi gửi tin nhắn: ' + data.message);
                        }
                    } catch (e) {
                        console.error('Lỗi phân tích JSON:', e, 'Phản hồi:', response);
                        alert('Lỗi khi gửi tin nhắn: Phản hồi không hợp lệ');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Lỗi khi gửi tin nhắn:', error, 'Mã trạng thái:', xhr
                        .status);
                    alert('Lỗi khi gửi tin nhắn: ' + xhr.status);
                },
                complete: function() {
                    $('.btn_send').prop('disabled', false).html(
                        '<i class="fa fa-paper-plane" aria-hidden="true"></i>');
                }
            });
        });

        // Tự động chọn người dùng đầu tiên
        if ($('.chat-users-list .media').length > 0) {
            $('.chat-users-list .media').first().trigger('click');
        }
    });
    </script>
</body>

</html>