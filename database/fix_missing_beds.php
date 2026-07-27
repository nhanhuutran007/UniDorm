<?php
/**
 * Script sửa phòng thiếu giường trên hosting
 * Truy cập: https://your-domain/fix_beds (qua route trong index.php)
 * XÓA SAU KHI DÙNG XONG
 */
if (!isset($conn)) {
    require_once __DIR__ . '/../includes/db.php';
}

$fixedRooms = [];
$skippedRooms = [];

$rooms = $conn->query("SELECT id, room_code, max_capacity FROM rooms ORDER BY room_code ASC")->fetch_all(MYSQLI_ASSOC);

foreach ($rooms as $room) {
    $roomId = $room['id'];
    $roomCode = $room['room_code'];
    $maxCap = (int)$room['max_capacity'];

    $cntStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM beds WHERE room_id = ?");
    $cntStmt->bind_param('i', $roomId);
    $cntStmt->execute();
    $existingBeds = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
    $cntStmt->close();

    if ($existingBeds >= $maxCap) {
        $skippedRooms[] = "$roomCode (đã có $existingBeds/$maxCap giường)";
        continue;
    }

    $bedStmt = $conn->prepare("SELECT bed_label FROM beds WHERE room_id = ?");
    $bedStmt->bind_param('i', $roomId);
    $bedStmt->execute();
    $existingLabels = [];
    while ($row = $bedStmt->get_result()->fetch_assoc()) {
        $existingLabels[] = $row['bed_label'];
    }
    $bedStmt->close();

    $added = 0;
    $ins = $conn->prepare("INSERT IGNORE INTO beds (room_id, bed_label, is_occupied) VALUES (?, ?, 0)");
    for ($j = 1; $j <= $maxCap; $j++) {
        $label = 'G' . $j;
        if (!in_array($label, $existingLabels)) {
            $ins->bind_param('is', $roomId, $label);
            $ins->execute();
            if ($ins->affected_rows > 0) {
                $added++;
            }
        }
    }
    $ins->close();

    if ($added > 0) {
        $fixedRooms[] = "$roomCode: thêm $added giường (tổng $maxCap)";
    }
}

$conn->query("
    UPDATE beds b
    SET b.is_occupied = IF(
        EXISTS (SELECT 1 FROM users u WHERE u.bed_id = b.id AND u.status IN ('active', 'pending')),
        1, 0
    )
");

$conn->query("
    UPDATE rooms r
    SET r.status = CASE
        WHEN (
            SELECT COUNT(b.id) FROM beds b WHERE b.room_id = r.id
        ) = (
            SELECT COUNT(u.user_id) FROM users u
            JOIN beds b ON u.bed_id = b.id
            WHERE b.room_id = r.id AND u.status IN ('active', 'pending')
        ) AND (SELECT COUNT(b.id) FROM beds b WHERE b.room_id = r.id) > 0
        THEN 'full'
        ELSE 'available'
    END
    WHERE r.status != 'maintenance'
");
?>

<div class="card border-0 shadow-sm mx-auto" style="border-radius:14px; max-width:700px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-tools me-2 text-success"></i>Kết quả sửa giường thiếu</h5>

        <?php if (!empty($fixedRooms)): ?>
        <div class="alert alert-success rounded-3">
            <strong>Đã sửa <?php echo count($fixedRooms); ?> phòng:</strong>
            <ul class="mb-0 mt-2 ps-3">
            <?php foreach ($fixedRooms as $msg): ?>
                <li><?php echo htmlspecialchars($msg); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php else: ?>
        <div class="alert alert-success rounded-3 mb-0">
            <strong>Không có phòng nào thiếu giường.</strong> Tất cả phòng đều đủ giường.
        </div>
        <?php endif; ?>

        <?php if (!empty($skippedRooms)): ?>
        <div class="alert alert-light border rounded-3 mt-3" style="max-height:250px; overflow:auto;">
            <small class="text-muted fw-semibold">Bỏ qua <?php echo count($skippedRooms); ?> phòng (đủ giường):</small>
            <ul class="mb-0 mt-1 ps-3" style="font-size:12px;">
            <?php foreach ($skippedRooms as $msg): ?>
                <li class="text-muted"><?php echo htmlspecialchars($msg); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="alert alert-warning rounded-3 mt-3 mb-0">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>Nhớ xóa route <code>fix_beds</code> trong index.php và file database/fix_missing_beds.php sau khi dùng xong!</strong>
        </div>
    </div>
</div>
