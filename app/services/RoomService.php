<?php
// path: services/RoomService.php

require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/BedModel.php';

class RoomService {
    private $db;
    private $roomModel;
    private $bedModel;

    public function __construct($db) {
        $this->db = $db;
        $this->roomModel = new RoomModel($db);
        $this->bedModel = new BedModel($db);
    }

    /**
     * Tự động gán sinh viên vào phòng và giường trống
     */
    public function assignStudentAuto($studentId, $roomId) {
        // 1. Kiểm tra phòng tồn tại
        $room = $this->roomModel->getRoomById($roomId);
        if (!$room) {
            return ['success' => false, 'message' => "Phòng không tồn tại."];
        }

        // 2. Tìm giường trống đầu tiên
        $bed = $this->bedModel->getAvailableBedInRoom($roomId);
        if (!$bed) {
            // Cập nhật trạng thái phòng thành FULL nếu chưa update
            if ($room['status'] !== 'full') {
                $this->roomModel->updateRoomStatus($roomId, 'full');
            }
            return ['success' => false, 'message' => "Phòng {$room['room_code']} đã đầy."];
        }

        // 3. Thực hiện gán giường (Bọc trong transaction nếu cần)
        $this->db->begin_transaction();
        try {
            $success = $this->bedModel->assignStudentToBed($bed['id'], $studentId);
            if (!$success) {
                throw new Exception("Không thể cập nhật thông tin giường.");
            }

            // Kiểm tra xem sau khi gán, phòng còn giường không
            $nextBed = $this->bedModel->getAvailableBedInRoom($roomId);
            if (!$nextBed) {
                $this->roomModel->updateRoomStatus($roomId, 'full');
            }

            $this->db->commit();
            return [
                'success' => true, 
                'message' => "Đã xếp sinh viên vào giường số {$bed['bed_number']} của phòng {$room['room_code']}.",
                'bed_id' => $bed['id']
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => "Lỗi hệ thống: " . $e->getMessage()];
        }
    }

    /**
     * Giải phóng giường khi sinh viên rời đi
     */
    public function releaseStudentFromBed($studentId) {
        // Tìm giường mà sinh viên đang ở
        $sql = "SELECT id, room_id FROM beds WHERE student_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bed = $result->fetch_assoc();
        $stmt->close();

        if ($bed) {
            $this->db->begin_transaction();
            try {
                $this->bedModel->removeStudentFromBed($bed['id']);
                // Cập nhật phòng thành Available nếu nó đang Full
                $this->roomModel->updateRoomStatus($bed['room_id'], 'available');
                $this->db->commit();
                return ['success' => true, 'message' => "Đã giải phóng giường."];
            } catch (Exception $e) {
                $this->db->rollback();
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
        return ['success' => false, 'message' => "Sinh viên không có giường được gán."];
    }
}
