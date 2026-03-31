<?php
// path: models/BedModel.php

class BedModel {
    private $db;

    public function __construct($db) {
        if (!($db instanceof mysqli)) {
            throw new InvalidArgumentException("Đối số db phải là một đối tượng mysqli hợp lệ.");
        }
        $this->db = $db;
    }

    public function getBedById($bedId) {
        $sql = "SELECT b.*, r.room_code, r.id as room_id
                FROM beds b
                JOIN rooms r ON b.room_id = r.id
                WHERE b.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $bedId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bed = $result->fetch_assoc();
        $stmt->close();
        return $bed;
    }

    public function getBedsByRoomId($roomId) {
        $sql = "SELECT b.*, u.fullname as student_name, u.username as student_username, u.student_code
                FROM beds b
                LEFT JOIN users u ON u.bed_id = b.id
                WHERE b.room_id = ?
                ORDER BY b.bed_label ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $result = $stmt->get_result();
        $beds = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $beds;
    }

    public function assignStudentToBed($bedId, $studentId) {
        $sql = "UPDATE beds SET student_id = ?, is_occupied = 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $studentId, $bedId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function removeStudentFromBed($bedId) {
        $sql = "UPDATE beds SET student_id = NULL, is_occupied = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $bedId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getAvailableBedInRoom($roomId) {
        $sql = "SELECT * FROM beds WHERE room_id = ? AND is_occupied = 0 ORDER BY bed_label ASC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bed = $result->fetch_assoc();
        $stmt->close();
        return $bed;
    }
}
