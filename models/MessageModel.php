<?php
// Path: D:\Học tập\network-management\models\MessageModel.php

class MessageModel
{
    private $db;
    private $id;
    private $sender_id;
    private $recipient_id;
    private $content;
    private $created_at;

    public function __construct($db, $data = [])
    {
        $this->db = $db;
        $this->id = $data['id'] ?? null;
        $this->sender_id = $data['sender_id'] ?? null;
        $this->recipient_id = $data['recipient_id'] ?? null;
        $this->content = $data['content'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
    }

    /**
     * Lưu tin nhắn mới vào cơ sở dữ liệu
     *
     * @return int ID của tin nhắn vừa lưu
     * @throws Exception Nếu có lỗi khi lưu
     */
    public function save()
    {
        if (!$this->sender_id || !$this->recipient_id || !$this->content) {
            throw new Exception('Thiếu sender_id, recipient_id hoặc content');
        }

        $stmt = $this->db->prepare('
            INSERT INTO messages (sender_id, recipient_id, content, created_at)
            VALUES (?, ?, ?, NOW())
        ');
        if (!$stmt) {
            throw new Exception('Lỗi prepare save: ' . $this->db->error);
        }
        $stmt->bind_param('iis', $this->sender_id, $this->recipient_id, $this->content);
        if (!$stmt->execute()) {
            throw new Exception('Lỗi execute save: ' . $this->db->error);
        }
        return $this->db->insert_id;
    }

    /**
     * Lấy danh sách tin nhắn giữa hai người dùng
     *
     * @param mysqli $db
     * @param int $userId
     * @param int $recipientId
     * @param int $limit
     * @return MessageModel[]
     */
    public static function getMessageModelsBetween($db, $userId, $recipientId, $limit = 50)
    {
        $stmt = $db->prepare('
            SELECT id, sender_id, recipient_id, content, created_at
            FROM messages
            WHERE (sender_id = ? AND recipient_id = ?)
               OR (sender_id = ? AND recipient_id = ?)
            ORDER BY created_at ASC
            LIMIT ?
        ');
        if (!$stmt) {
            error_log("Lỗi prepare getMessageModelsBetween: " . $db->error);
            return [];
        }
        $stmt->bind_param('iiiii', $userId, $recipientId, $recipientId, $userId, $limit); // Sửa thành 'iiiii'
        $stmt->execute();
        $result = $stmt->get_result();

        $messageModels = [];
        while ($data = $result->fetch_assoc()) {
            $messageModels[] = new MessageModel($db, $data);
        }
        return $messageModels;
    }

    /**
     * Tìm tin nhắn theo ID (dùng cho xóa tin nhắn)
     *
     * @param mysqli $db
     * @param int $id
     * @return MessageModel|null
     */
    public static function find($db, $id)
    {
        $stmt = $db->prepare('
            SELECT id, sender_id, recipient_id, content, created_at
            FROM messages
            WHERE id = ?
        ');
        if (!$stmt) {
            error_log("Lỗi prepare find: " . $db->error);
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        error_log("Find result for id = $id: " . print_r($data, true));

        if ($data) {
            return new MessageModel($db, $data);
        }
        return null;
    }

    /**
     * Xóa tin nhắn theo ID
     *
     * @param mysqli $db
     * @param int $id
     * @return bool
     */
    public static function delete($db, $id)
    {
        $stmt = $db->prepare('DELETE FROM messages WHERE id = ?');
        if (!$stmt) {
            error_log("Lỗi prepare delete: " . $db->error);
            return false;
        }
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    /**
     * Chuyển đối tượng thành mảng để trả về JSON
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'content' => $this->content,
            'created_at' => $this->created_at
        ];
    }

    /**
     * Getter cho sender_id
     *
     * @return int|null
     */
    public function getSenderId()
    {
        return $this->sender_id;
    }

    /**
     * Getter cho recipient_id
     *
     * @return int|null
     */
    public function getRecipientId()
    {
        return $this->recipient_id;
    }
}