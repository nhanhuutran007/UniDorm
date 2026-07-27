<?php
/**
 * Sửa phòng thiếu giường - XÓA SAU KHI DÙNG XONG
 */
if (!isset($conn)) {
    require_once __DIR__ . '/../includes/db.php';
}

$fixedRooms = [];
$error = null;

try {
    $rooms = $conn->query("SELECT id, room_code, max_capacity FROM rooms ORDER BY room_code ASC");
    if (!$rooms) throw new Exception("Query rooms fail: " . $conn->error);

    while ($room = $rooms->fetch_assoc()) {
        $roomId = (int)$room['id'];
        $roomCode = $room['room_code'];
        $maxCap = (int)$room['max_capacity'];

        $cntRes = $conn->query("SELECT COUNT(*) as cnt FROM beds WHERE room_id = $roomId");
        if (!$cntRes) continue;
        $existingBeds = (int)$cntRes->fetch_assoc()['cnt'];

        if ($existingBeds >= $maxCap) continue;

        $bedRes = $conn->query("SELECT bed_label FROM beds WHERE room_id = $roomId");
        $existingLabels = [];
        if ($bedRes) {
            while ($row = $bedRes->fetch_assoc()) {
                $existingLabels[] = $row['bed_label'];
            }
        }

        $added = 0;
        for ($j = 1; $j <= $maxCap; $j++) {
            $label = 'G' . $j;
            if (!in_array($label, $existingLabels)) {
                $conn->query("INSERT IGNORE INTO beds (room_id, bed_label, is_occupied) VALUES ($roomId, '$label', 0)");
                if ($conn->affected_rows > 0) $added++;
            }
        }

        if ($added > 0) {
            $fixedRooms[] = "$roomCode: +$added giường (tổng $maxCap)";
        }
    }

    $conn->query("UPDATE beds b SET b.is_occupied = IF(EXISTS(SELECT 1 FROM users u WHERE u.bed_id = b.id AND u.status IN ('active','pending')),1,0)");
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<div class="card border-0 shadow-sm mx-auto" style="border-radius:14px; max-width:700px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-tools me-2 text-success"></i>Kết quả sửa giường thiếu</h5>
        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (!empty($fixedRooms)): ?>
        <div class="alert alert-success rounded-3">
            <strong>Đã sửa <?php echo count($fixedRooms); ?> phòng:</strong>
            <ul class="mb-0 mt-2 ps-3"><?php foreach ($fixedRooms as $m): ?><li><?php echo htmlspecialchars($m); ?></li><?php endforeach; ?></ul>
        </div>
        <?php else: ?>
        <div class="alert alert-success rounded-3 mb-0"><strong>Không có phòng nào thiếu giường.</strong></div>
        <?php endif; ?>
        <div class="alert alert-warning rounded-3 mt-3 mb-0 small">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Nhớ xóa route <code>fix_beds</code> trong index.php và file <code>views/admin/fix_missing_beds.php</code> sau khi dùng!
        </div>
    </div>
</div>
