<?php
/**
 * Script sửa phòng thiếu giường trên hosting
 * Chạy 1 lần rồi XÓA ngay sau khiใช้งาน
 * Truy cập: https://your-domain/database/fix_missing_beds.php
 */
require_once __DIR__ . '/../includes/db.php';

$fixedRooms = [];
$skippedRooms = [];
$output = '';

// Lay tat ca phong
$rooms = $conn->query("SELECT id, room_code, max_capacity FROM rooms ORDER BY room_code ASC")->fetch_all(MYSQLI_ASSOC);

foreach ($rooms as $room) {
    $roomId = $room['id'];
    $roomCode = $room['room_code'];
    $maxCap = (int)$room['max_capacity'];

    // Dem so luong bed hien co
    $cntStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM beds WHERE room_id = ?");
    $cntStmt->bind_param('i', $roomId);
    $cntStmt->execute();
    $existingBeds = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
    $cntStmt->close();

    if ($existingBeds >= $maxCap) {
        $skippedRooms[] = "$roomCode (đã có $existingBeds/$maxCap giường)";
        continue;
    }

    // Lay cac bed_label da co trong phong
    $bedStmt = $conn->prepare("SELECT bed_label FROM beds WHERE room_id = ?");
    $bedStmt->bind_param('i', $roomId);
    $bedStmt->execute();
    $existingLabels = [];
    while ($row = $bedStmt->get_result()->fetch_assoc()) {
        $existingLabels[] = $row['bed_label'];
    }
    $bedStmt->close();

    // Tao cac bed thieu
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

// Cap nhat is_occupied cho cac bed bi sai
$conn->query("
    UPDATE beds b
    SET b.is_occupied = IF(
        EXISTS (SELECT 1 FROM users u WHERE u.bed_id = b.id AND u.status IN ('active', 'pending')),
        1, 0
    )
");

// Cap nhat trang thai phong
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

$totalFixed = count($fixedRooms);
$totalSkipped = count($skippedRooms);
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Fix Missing Beds</title></head>
<body style="font-family:monospace; padding:20px; max-width:700px;">
<h2>Kết quả sửa giường</h2>

<?php if ($totalFixed > 0): ?>
<div style="background:#d4edda; padding:12px; border-radius:8px; margin-bottom:16px;">
    <strong>Đã sửa <?php echo $totalFixed; ?> phòng:</strong>
    <ul style="margin:8px 0 0 20px;">
    <?php foreach ($fixedRooms as $msg): ?>
        <li><?php echo htmlspecialchars($msg); ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php else: ?>
<div style="background:#d4edda; padding:12px; border-radius:8px; margin-bottom:16px;">
    <strong>Không có phòng nào thiếu giường.</strong>
</div>
<?php endif; ?>

<?php if ($totalSkipped > 0): ?>
<div style="background:#e2e3e5; padding:12px; border-radius:8px; margin-bottom:16px;">
    <strong>Đã bỏ qua <?php echo $totalSkipped; ?> phòng (đủ giường):</strong>
    <ul style="margin:8px 0 0 20px; max-height:300px; overflow:auto;">
    <?php foreach ($skippedRooms as $msg): ?>
        <li style="font-size:12px;"><?php echo htmlspecialchars($msg); ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<hr>
<p style="color:red; font-weight:bold;">XÓA FILE NÀY SAU KHI SỬA XONG!</p>
</body>
</html>
