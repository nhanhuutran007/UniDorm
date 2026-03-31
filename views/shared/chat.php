<?php
/**
 * UniDorm – Chat riêng tư (Admin ↔ Student)
 * path: views/shared/chat.php
 * Refactored từ views/testChat.php (Network Management legacy)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$conn = require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/UserModel.php';
require_once __DIR__ . '/../../app/models/MessageModel.php';
require_once __DIR__ . '/../../app/controllers/ChatController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /UniDorm/views/auth/login.php');
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$userRole  = strtolower($_SESSION['role'] ?? 'student');
$userModel = new UserModel($conn);
$userData  = $userModel->getUserById($userId);

if (!$userData) {
    session_destroy();
    header('Location: /UniDorm/views/auth/login.php?error=user_not_found');
    exit;
}

// Xử lý các AJAX actions trước khi xuất HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Kiểm tra session
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Không có quyền']);
        exit;
    }

    $chatController = new ChatController($userId);
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'getMessages':
            echo $chatController->getMessages();
            exit;
        case 'storeMessage':
            echo $chatController->storeMessage($input);
            exit;
        case 'deleteMessage':
            echo $chatController->deleteMessage();
            exit;
        case 'getUserList':
            // Lấy danh sách user chat (dựa trên role)
            if ($userRole === 'admin') {
                // Admin thấy tất cả sinh viên active có lịch sử chat
                $stmt = $conn->prepare("
                    SELECT DISTINCT u.user_id, u.fullname, u.profile_picture, u.status, u.role, u.student_code
                    FROM users u
                    WHERE u.status = 'active' AND u.user_id != ?
                    ORDER BY u.role ASC, u.fullname ASC
                ");
                $stmt->bind_param('i', $userId);
            } else {
                // Student chỉ thấy admin
                $stmt = $conn->prepare("
                    SELECT u.user_id, u.fullname, u.profile_picture, u.status, u.role, u.student_code
                    FROM users u
                    WHERE u.role = 'admin' AND u.user_id != ?
                    ORDER BY u.fullname ASC
                ");
                $stmt->bind_param('i', $userId);
            }
            $stmt->execute();
            $chatUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Gắn tin nhắn cuối cho mỗi user
            $result = [];
            foreach ($chatUsers as $u) {
                $lastMsg = $userModel->getLastMessage($userId, $u['user_id']);
                $result[] = [
                    'user'              => $u,
                    'last_message'      => $lastMsg ? ['content' => strip_tags($lastMsg['content']), 'created_at' => $lastMsg['created_at']] : null,
                    'last_message_time' => $lastMsg ? strtotime($lastMsg['created_at']) : 0,
                ];
            }
            usort($result, fn($a, $b) => $b['last_message_time'] - $a['last_message_time']);
            echo json_encode(['success' => true, 'data' => $result]);
            exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Pre-select user nếu có ?with=<user_id>
$preSelectUserId = (int)($_GET['with'] ?? 0);
$preSelectUser   = $preSelectUserId ? $userModel->getUserById($preSelectUserId) : null;

// Xây dựng danh sách chat users ban đầu (server-side)
if ($userRole === 'admin') {
    $stmt = $conn->prepare("SELECT u.user_id, u.fullname, u.profile_picture, u.status, u.role, u.student_code FROM users u WHERE u.role = 'student' AND u.user_id != ? ORDER BY u.fullname ASC");
} else {
    $stmt = $conn->prepare("SELECT u.user_id, u.fullname, u.profile_picture, u.status, u.role, u.student_code FROM users u WHERE u.role = 'admin' AND u.user_id != ? ORDER BY u.fullname ASC");
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$chatUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Sort by last message time
usort($chatUsers, function ($a, $b) use ($userModel, $userId) {
    $la = $userModel->getLastMessage($userId, $a['user_id']);
    $lb = $userModel->getLastMessage($userId, $b['user_id']);
    return ($lb ? strtotime($lb['created_at']) : 0) - ($la ? strtotime($la['created_at']) : 0);
});

$pageTitle  = 'Tin nhắn';
$breadcrumbs = [['label' => 'Tin nhắn', 'url' => '#']];
ob_start();
?>

<style>
/* ============ Chat Layout ============ */
.chat-wrapper {
    height: calc(100vh - 200px);
    min-height: 500px;
    display: flex;
    gap: 0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 16px rgba(0,0,0,.08);
    background: #fff;
}

/* Panel trái – danh sách contacts */
.chat-sidebar {
    width: 300px;
    min-width: 260px;
    border-right: 1px solid #f0f0f0;
    display: flex;
    flex-direction: column;
    background: #fff;
}
.chat-sidebar-header {
    padding: 16px;
    border-bottom: 1px solid #f0f0f0;
    background: #fff;
}
.chat-contacts {
    flex: 1;
    overflow-y: auto;
}
.chat-contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: background .15s;
    border-left: 3px solid transparent;
    text-decoration: none;
    color: inherit;
}
.chat-contact-item:hover { background: #f8f9fa; }
.chat-contact-item.active {
    background: #eff6ff;
    border-left-color: #2563eb;
}
.chat-contact-avatar {
    position: relative;
    flex-shrink: 0;
}
.chat-contact-avatar img {
    width: 42px; height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e5e7eb;
}
.chat-online-dot {
    width: 10px; height: 10px;
    background: #22c55e;
    border-radius: 50%;
    border: 2px solid #fff;
    position: absolute;
    bottom: 0; right: 0;
}
.chat-contact-info { flex: 1; min-width: 0; }
.chat-contact-name { font-size: 13px; font-weight: 600; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-contact-preview { font-size: 11px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-contact-time { font-size: 10px; color: #9ca3af; flex-shrink: 0; }

/* Panel phải – khu vực chat */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    min-width: 0;
}
.chat-main-header {
    padding: 16px 20px;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 12px;
}
.chat-messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.chat-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    text-align: center;
}

/* Bubbles */
.msg-row { display: flex; align-items: flex-end; gap: 8px; }
.msg-row.sent { flex-direction: row-reverse; }
.msg-bubble {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 18px;
    font-size: 13px;
    line-height: 1.5;
    word-break: break-word;
}
.msg-row.received .msg-bubble { background: #fff; color: #1f2937; border-radius: 4px 18px 18px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.msg-row.sent     .msg-bubble { background: #2563eb; color: #fff; border-radius: 18px 18px 4px 18px; }
.msg-time { font-size: 10px; color: #9ca3af; padding: 0 4px; white-space: nowrap; }
.msg-avatar img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }

/* Input area */
.chat-input-area {
    padding: 16px 20px;
    background: #fff;
    border-top: 1px solid #f0f0f0;
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.chat-input-area textarea {
    flex: 1;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    padding: 10px 16px;
    font-size: 13px;
    resize: none;
    max-height: 120px;
    outline: none;
    transition: border-color .2s;
    background: #f9fafb;
}
.chat-input-area textarea:focus { border-color: #2563eb; background: #fff; }
.btn-send-msg {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: #2563eb;
    color: #fff;
    border: none;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    cursor: pointer;
    transition: background .2s, transform .1s;
}
.btn-send-msg:hover { background: #1d4ed8; }
.btn-send-msg:active { transform: scale(.95); }
.btn-send-msg:disabled { background: #93c5fd; cursor: not-allowed; }

/* Responsive */
@media (max-width: 768px) {
    .chat-sidebar { position: fixed; z-index: 100; left: 0; top: 0; bottom: 0; transform: translateX(-100%); transition: transform .3s; width: 280px; }
    .chat-sidebar.open { transform: translateX(0); }
    .chat-back-btn { display: flex !important; }
}
</style>

<div class="chat-wrapper">

    <!-- ===== LEFT: Contacts ===== -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="chat-sidebar-header">
            <div class="d-flex align-items-center gap-2 mb-2">
                <h6 class="fw-bold mb-0 flex-grow-1">
                    <?php echo $userRole === 'student' ? 'Ban quản lý' : 'Hội thoại'; ?>
                </h6>
            </div>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="contactSearch" class="form-control bg-light border-start-0"
                       placeholder="Tìm người dùng...">
            </div>
        </div>

        <div class="chat-contacts" id="contactsList">
            <?php if (empty($chatUsers)): ?>
            <div class="text-center py-4 text-muted small">Không có người dùng nào</div>
            <?php else: ?>
            <?php foreach ($chatUsers as $cu):
                $lastMsg = $userModel->getLastMessage($userId, $cu['user_id']);
                $isActive = $preSelectUserId == $cu['user_id'];
                $avatar  = '/UniDorm/' . ($cu['profile_picture'] ?? 'assets/images/default-avatar.jpg');
                $roleLabel = match($cu['role']) { 'admin'=>'Quản trị viên', default=>'Sinh viên' };
            ?>
            <div class="chat-contact-item <?php echo $isActive ? 'active' : ''; ?>"
                 data-user-id="<?php echo $cu['user_id']; ?>"
                 data-user-name="<?php echo htmlspecialchars($cu['fullname']); ?>"
                 data-user-avatar="<?php echo htmlspecialchars($avatar); ?>"
                 data-user-role="<?php echo htmlspecialchars($roleLabel); ?>">
                <div class="chat-contact-avatar">
                    <img src="<?php echo htmlspecialchars($avatar); ?>"
                         onerror="this.src='/UniDorm/assets/images/default-avatar.jpg'" alt="">
                    <?php if ($cu['status'] === 'active'): ?>
                    <span class="chat-online-dot"></span>
                    <?php endif; ?>
                </div>
                <div class="chat-contact-info">
                    <div class="chat-contact-name"><?php echo htmlspecialchars($cu['fullname']); ?></div>
                    <div class="chat-contact-preview">
                        <?php if ($lastMsg): echo htmlspecialchars(mb_strimwidth(strip_tags($lastMsg['content']), 0, 30, '...'));
                        else: echo '<span class="text-muted fst-italic">' . $roleLabel . '</span>'; endif; ?>
                    </div>
                </div>
                <div class="chat-contact-time">
                    <?php echo $lastMsg ? date('H:i', strtotime($lastMsg['created_at'])) : ''; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== RIGHT: Chat area ===== -->
    <div class="chat-main">
        <!-- Header -->
        <div class="chat-main-header" id="chatHeader">
            <button class="btn btn-sm btn-outline-secondary d-none chat-back-btn" id="chatBackBtn" onclick="closeChatPanel()">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div id="chatHeaderAvatar" class="flex-shrink-0" style="display:none!important;">
                <img id="chatReceiverAvatar" src="/UniDorm/assets/images/default-avatar.jpg"
                     class="rounded-circle" width="38" height="38" style="object-fit:cover;"
                     onerror="this.src='/UniDorm/assets/images/default-avatar.jpg'">
            </div>
            <div id="chatHeaderInfo" class="flex-grow-1">
                <p class="mb-0 text-muted" id="chatPlaceholderText">
                    <i class="bi bi-chat-dots me-2"></i>Chọn một người để bắt đầu nhắn tin
                </p>
                <div id="chatReceiverInfo" style="display:none;">
                    <p class="mb-0 fw-bold" id="chatReceiverName"></p>
                    <small class="text-muted" id="chatReceiverRole"></small>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages-area" id="chatMessages">
            <div class="chat-empty-state" id="chatEmptyState">
                <i class="bi bi-chat-square-dots fs-1 mb-3 opacity-25"></i>
                <p class="mb-0 small">Chọn một cuộc trò chuyện để xem tin nhắn</p>
            </div>
        </div>

        <!-- Input -->
        <div class="chat-input-area" id="chatInputArea" style="display:none!important;">
            <textarea id="msgInput" rows="1" placeholder="Nhập tin nhắn..." maxlength="1000"
                      onkeydown="handleMsgKeydown(event)" oninput="autoResize(this)"></textarea>
            <button class="btn-send-msg" id="btnSend" onclick="sendMessage()" title="Gửi (Enter)">
                <i class="bi bi-send-fill" style="font-size:15px;"></i>
            </button>
        </div>

    </div><!-- /.chat-main -->
</div><!-- /.chat-wrapper -->

<script>
const SELF_ID     = <?php echo $userId; ?>;
const SELF_NAME   = <?php echo json_encode($userData['fullname']); ?>;
const SELF_AVATAR = <?php echo json_encode('/UniDorm/' . ($userData['profile_picture'] ?? 'assets/images/default-avatar.jpg')); ?>;
const CHAT_URL    = '/UniDorm/views/shared/chat.php';

let currentReceiverId   = 0;
let currentReceiverName = '';
let pollInterval        = null;
let lastMsgCount        = 0;

// ── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Gắn event click cho contacts
    bindContactClicks();

    // Pre-select từ query string
    <?php if ($preSelectUser): ?>
    selectContact(<?php echo $preSelectUser['user_id']; ?>,
                  <?php echo json_encode($preSelectUser['fullname']); ?>,
                  '/UniDorm/' + (<?php echo json_encode($preSelectUser['profile_picture'] ?? 'assets/images/default-avatar.jpg'); ?>),
                  '<?php echo match(strtolower($preSelectUser['role']??'')) { 'admin'=>'Quản trị viên', default=>'Sinh viên' }; ?>');
    <?php else: ?>
    // Tự động mở contact đầu tiên nếu có
    const first = document.querySelector('.chat-contact-item');
    if (first) first.click();
    <?php endif; ?>

    // Tìm kiếm contact
    document.getElementById('contactSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.chat-contact-item').forEach(el => {
            el.style.display = el.dataset.userName.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});

// ── Contact click ─────────────────────────────────────────────────────
function bindContactClicks() {
    document.querySelectorAll('.chat-contact-item').forEach(el => {
        el.addEventListener('click', () => {
            selectContact(
                el.dataset.userId,
                el.dataset.userName,
                el.dataset.userAvatar,
                el.dataset.userRole
            );
        });
    });
}

function selectContact(uid, name, avatar, role) {
    currentReceiverId   = parseInt(uid);
    currentReceiverName = name;

    // Update header
    document.getElementById('chatPlaceholderText').style.display    = 'none';
    document.getElementById('chatReceiverInfo').style.display        = '';
    document.getElementById('chatHeaderAvatar').style.removeProperty('display');
    document.getElementById('chatReceiverName').textContent          = name;
    document.getElementById('chatReceiverRole').textContent          = role;
    document.getElementById('chatReceiverAvatar').src                = avatar;
    document.getElementById('chatInputArea').style.removeProperty('display');
    document.getElementById('chatEmptyState').style.display          = 'none';

    // Active state
    document.querySelectorAll('.chat-contact-item').forEach(el => el.classList.remove('active'));
    const target = document.querySelector(`.chat-contact-item[data-user-id="${uid}"]`);
    if (target) target.classList.add('active');

    // Load messages
    loadMessages();

    // Start polling
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(loadMessages, 4000);
}

// ── Load messages ─────────────────────────────────────────────────────
function loadMessages() {
    if (!currentReceiverId) return;

    fetch(`${CHAT_URL}?action=getMessages&user_id=${SELF_ID}&recipient_id=${currentReceiverId}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data || !res.data.messages) return;
            renderMessages(res.data.messages);
        })
        .catch(console.error);
}

function renderMessages(messages) {
    const area = document.getElementById('chatMessages');
    const wasAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 80;

    if (messages.length === 0) {
        area.innerHTML = `<div class="chat-empty-state">
            <i class="bi bi-chat-square-text fs-1 mb-3 opacity-25"></i>
            <p class="small mb-0">Chưa có tin nhắn. Hãy bắt đầu cuộc trò chuyện!</p>
        </div>`;
        return;
    }

    // Chỉ re-render nếu số tin nhắn thay đổi
    if (messages.length === lastMsgCount) return;
    lastMsgCount = messages.length;

    area.innerHTML = messages.map(msg => {
        const isSent  = msg.sender_id == SELF_ID;
        const time    = new Date(msg.created_at.replace(' ', 'T')).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        const avatar  = isSent ? SELF_AVATAR : document.getElementById('chatReceiverAvatar').src;
        const content = msg.content;

        return `<div class="msg-row ${isSent ? 'sent' : 'received'}">
            ${!isSent ? `<div class="msg-avatar"><img src="${avatar}" onerror="this.src='/UniDorm/assets/images/default-avatar.jpg'" alt=""></div>` : ''}
            <div class="msg-bubble">${content}</div>
            <div class="msg-time">${time}</div>
            ${isSent ? `<div class="msg-avatar"><img src="${SELF_AVATAR}" onerror="this.src='/UniDorm/assets/images/default-avatar.jpg'" alt=""></div>` : ''}
        </div>`;
    }).join('');

    if (wasAtBottom || lastMsgCount === messages.length) {
        area.scrollTop = area.scrollHeight;
    }
}

// ── Send message ──────────────────────────────────────────────────────
function sendMessage() {
    const input   = document.getElementById('msgInput');
    const content = input.value.trim();
    const btn     = document.getElementById('btnSend');

    if (!currentReceiverId) return;
    if (!content) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';

    fetch(CHAT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'storeMessage',
            sender_id: SELF_ID,
            recipient_id: currentReceiverId,
            content: content
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            input.value  = '';
            input.style.height = '';
            lastMsgCount = 0; // Force re-render
            loadMessages();
        } else {
            alert('Lỗi: ' + (res.message || 'Không gửi được tin nhắn'));
        }
    })
    .catch(() => alert('Lỗi kết nối'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill" style="font-size:15px;"></i>';
    });
}

function handleMsgKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function autoResize(el) {
    el.style.height = '';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function closeChatPanel() {
    document.getElementById('chatSidebar').classList.toggle('open');
}
</script>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin .6s linear infinite; display: inline-block; }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';