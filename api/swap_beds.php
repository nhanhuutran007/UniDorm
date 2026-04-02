<?php
/**
 * UniDorm – API: Swap beds for students
 * path: api/swap_beds.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId1 = (int)($input['user_id_1'] ?? 0);
$bedId1  = (int)($input['bed_id_1'] ?? 0);
$bedId2  = (int)($input['bed_id_2'] ?? 0);

if (!$userId1 || !$bedId1 || !$bedId2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Get student 2 (if any) currently at bedId2
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE bed_id = ? AND status IN ('active', 'pending')");
    $stmt->bind_param('i', $bedId2);
    $stmt->execute();
    $user2 = $stmt->get_result()->fetch_assoc();
    $userId2 = $user2 ? (int)$user2['user_id'] : 0;

    // 2. Update Student 1 to Bed 2
    $upd1 = $conn->prepare("UPDATE users SET bed_id = ? WHERE user_id = ?");
    $upd1->bind_param('ii', $bedId2, $userId1);
    $upd1->execute();

    if ($userId2) {
        // Case: Swap two students
        $upd2 = $conn->prepare("UPDATE users SET bed_id = ? WHERE user_id = ?");
        $upd2->bind_param('ii', $bedId1, $userId2);
        $upd2->execute();
    } else {
        // Case: Move student 1 to empty bed 2
        // Update occupancy
        $occ1 = $conn->prepare("UPDATE beds SET is_occupied = 0 WHERE id = ?");
        $occ1->bind_param('i', $bedId1);
        $occ1->execute();

        $occ2 = $conn->prepare("UPDATE beds SET is_occupied = 1 WHERE id = ?");
        $occ2->bind_param('i', $bedId2);
        $occ2->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Bed swap successful']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
