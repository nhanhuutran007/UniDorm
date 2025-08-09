<?php
// Path: network-management/controllers/ChatController.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../models/MessageModel.php';
// require_once __DIR__ . '/../functions/device_notification/DeviceNotificationManager.php';

class ChatController
{
    private $db;
    private $userId;
    private $deviceNotificationManager;

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->db = $this->getDatabaseConnection();
        if (!$this->db instanceof mysqli) {
            throw new Exception("Database connection failed: " . mysqli_connect_error());
        }
        // $this->deviceNotificationManager = new DeviceNotificationManager($this->db);
    }

    private function getDatabaseConnection()
    {
        return require __DIR__ . '/../includes/db.php';
    }

    public function storeMessage($data = null)
{
    if (!$this->userId) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'User not authenticated']);
    }

    if (!$data) {
        $input = file_get_contents('php://input');
        $data = !empty($input) ? json_decode($input, true) : $_POST;
    }

    if (empty($data)) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Không nhận được dữ liệu đầu vào']);
    }

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: ' . json_last_error_msg()]);
    }

    if (!isset($data['sender_id']) || !isset($data['recipient_id']) || !isset($data['content'])) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Thiếu sender_id, recipient_id hoặc content']);
    }

    if (!$this->isAuthenticated($data['sender_id'])) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'Không có quyền gửi tin nhắn']);
    }

    $content = $this->sanitizeContent($data['content']);
    if (strlen($content) > 1000) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Nội dung tin nhắn quá dài']);
    }

    $message = new MessageModel($this->db, [
        'sender_id' => $data['sender_id'],
        'recipient_id' => $data['recipient_id'],
        'content' => $content
    ]);

    try {
        $messageId = $message->save();

        // Gửi thông báo sau khi lưu tin nhắn thành công
        $notificationData = [
            'device_id' => null,
            'notification_type' => 'message',
            'message' => "Bạn có tin nhắn mới từ người dùng ID {$data['sender_id']}.",
            'is_read' => false,
            'target_user_id' => $data['recipient_id'],
            'created_by' => $this->userId
        ];
        $notificationResult = $this->deviceNotificationManager->handleAction('insert_notification', [
            'data' => $notificationData
        ]);

        if (!$notificationResult['success']) {
            error_log("Không thể gửi thông báo cho tin nhắn ID $messageId: " . $notificationResult['message']);
        }

        return json_encode([
            'success' => true,
            'message' => 'Message sent',
            'data' => ['message_id' => $messageId]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(['success' => false, 'message' => 'Lỗi khi lưu tin nhắn: ' . $e->getMessage()]);
    }
}

    public function getMessages()
    {
        if (!$this->userId) {
            http_response_code(403);
            return json_encode(['success' => false, 'message' => 'User not authenticated']);
        }

        $userId = $_GET['user_id'] ?? null;
        $recipientId = $_GET['recipient_id'] ?? null;

        if (!$userId || !$recipientId) {
            http_response_code(400);
            return json_encode(['success' => false, 'message' => 'Thiếu user_id hoặc recipient_id']);
        }

        if (!$this->isAuthenticated($userId)) {
            http_response_code(403);
            return json_encode(['success' => false, 'message' => 'Không có quyền xem tin nhắn']);
        }

        try {
            $messages = MessageModel::getMessageModelsBetween($this->db, $userId, $recipientId);
            $messagesArray = array_map(function ($message) {
                return $message->toArray();
            }, $messages);

            return json_encode([
                'success' => true,
                'message' => 'Lấy tin nhắn thành công',
                'data' => ['messages' => $messagesArray]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['success' => false, 'message' => 'Lỗi khi lấy tin nhắn: ' . $e->getMessage()]);
        }
    }

    public function deleteMessage()
    {
        error_log("Entering deleteMessage function");
        if (!$this->userId) {
            error_log("User not authenticated: this->userId is " . ($this->userId ?? 'null'));
            http_response_code(403);
            return json_encode(['success' => false, 'message' => 'User not authenticated']);
        }

        $input = file_get_contents('php://input');
        $data = !empty($input) ? json_decode($input, true) : $_POST;
        error_log("Input data: " . print_r($data, true));

        if (empty($data)) {
            error_log("No input data received");
            http_response_code(400);
            return json_encode(['success' => false, 'message' => 'Không nhận được dữ liệu đầu vào']);
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error_log("Invalid JSON data: " . json_last_error_msg());
            http_response_code(400);
            return json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ: ' . json_last_error_msg()]);
        }

        $messageId = $data['message_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        error_log("Extracted: messageId = $messageId, userId = $userId");

        if (!$messageId || !$userId) {
            error_log("Missing message_id or user_id");
            http_response_code(400);
            return json_encode(['success' => false, 'message' => 'Thiếu message_id hoặc user_id']);
        }

        if (!$this->isAuthenticated($userId)) {
            error_log("Authentication failed: Session user_id = " . ($_SESSION['user_id'] ?? 'null') . ", Request user_id = $userId");
            http_response_code(403);
            return json_encode(['success' => false, 'message' => 'Không có quyền xóa tin nhắn']);
        }

        try {
            $message = MessageModel::find($this->db, $messageId);
            error_log("Message found: " . print_r($message ? $message->toArray() : 'null', true));
            if (!$message || ($message->getSenderId() != $userId && $message->getRecipientId() != $userId)) {
                error_log("Permission denied: Condition evaluated to true");
                http_response_code(403);
                return json_encode(['success' => false, 'message' => 'Không có quyền xóa tin nhắn này']);
            }

            if (MessageModel::delete($this->db, $messageId)) {
                error_log("Message deleted successfully: id = $messageId");
                return json_encode([
                    'success' => true,
                    'message' => 'Message deleted',
                    'data' => null
                ]);
            } else {
                error_log("Delete failed: " . $this->db->error);
                http_response_code(500);
                return json_encode(['success' => false, 'message' => 'Lỗi khi xóa tin nhắn']);
            }
        } catch (Exception $e) {
            error_log("Exception during delete: " . $e->getMessage());
            http_response_code(500);
            return json_encode(['success' => false, 'message' => 'Lỗi khi xóa tin nhắn: ' . $e->getMessage()]);
        }
    }

    private function sanitizeContent($content)
    {
        // Danh sách các thẻ HTML được phép
        $allowedTags = '<p><br><b><i><u><strong><em><ul><ol><li><a><span><table><thead><tbody><tr><th><td>';
        
        // Loại bỏ tất cả các thẻ không được phép
        $cleanContent = strip_tags($content, $allowedTags);

        // Lọc thuộc tính nguy hiểm và làm sạch nội dung
        $cleanContent = preg_replace('/\bon\w+?\s*=\s*["\'][^"\']*["\']/i', '', $cleanContent); // Loại bỏ thuộc tính sự kiện (onclick, onerror, v.v.)
        $cleanContent = preg_replace('/<a[^>]*href=["\']javascript:[^"\']*["\'][^>]*>/i', '<a>', $cleanContent); // Loại bỏ href chứa JavaScript
        $cleanContent = preg_replace('/<[^>]+style=["\'][^"\']*["\'][^>]*>/i', '', $cleanContent); // Loại bỏ thuộc tính style (tạm thời, để đơn giản)

        // Chỉ cho phép href trong thẻ <a> với các giao thức an toàn
        $cleanContent = preg_replace('/<a[^>]*href=["\'](?!(http|https):\/\/)[^"\']*["\'][^>]*>/i', '<a>', $cleanContent);

        // Loại bỏ các thẻ <script> hoặc nội dung JavaScript
        $cleanContent = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $cleanContent);

        // Trả về nội dung đã làm sạch
        return trim($cleanContent);
    }

    private function isAuthenticated($userId)
    {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId;
    }
}