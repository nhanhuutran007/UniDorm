<?php
// path: models/RoomModel.php

class RoomModel {
    private $db;

    public function __construct($db) {
        if (!($db instanceof mysqli)) {
            throw new InvalidArgumentException("Đối số db phải là một đối tượng mysqli hợp lệ.");
        }
        $this->db = $db;
    }

    public function getAllRooms($filters = []) {
        $sql = "SELECT r.*, f.floor_number, b.name as building_name, 
                (SELECT COUNT(*) FROM beds WHERE room_id = r.id AND is_occupied = 1) as occupied_count
                FROM rooms r
                JOIN floors f ON r.floor_id = f.id
                JOIN buildings b ON f.building_id = b.id
                WHERE 1=1";
        
        $params = [];
        $types = "";

        if (!empty($filters['floor_id'])) {
            $sql .= " AND r.floor_id = ?";
            $params[] = $filters['floor_id'];
            $types .= "i";
        }

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }

        $sql .= " ORDER BY r.room_code ASC";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $rooms = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $rooms;
    }

    public function getRoomById($id) {
        $sql = "SELECT r.*, f.floor_number, b.name as building_name 
                FROM rooms r
                JOIN floors f ON r.floor_id = f.id
                JOIN buildings b ON f.building_id = b.id
                WHERE r.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $room = $result->fetch_assoc();
        $stmt->close();
        return $room;
    }

    public function updateRoomStatus($id, $status) {
        $sql = "UPDATE rooms SET status = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $status, $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getBuildings() {
        $result = $this->db->query("SELECT * FROM buildings ORDER BY name ASC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getFloorsByBuilding($buildingId) {
        $stmt = $this->db->prepare("SELECT * FROM floors WHERE building_id = ? ORDER BY floor_number ASC");
        $stmt->bind_param("i", $buildingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function countRoomsByStatus($status = 'all') {
        if ($status === 'all') {
            $result = $this->db->query("SELECT COUNT(*) as total FROM rooms");
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM rooms WHERE status = ?");
            $stmt->bind_param("s", $status);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $row = $result->fetch_assoc();
        return (int) $row['total'];
    }
}
