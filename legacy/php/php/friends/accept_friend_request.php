<?php
// ===============================================
// FRIENDS API: Accept/Reject Friend Request
// Path: php/friends/accept_friend_request.php
// ===============================================

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!validateSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$current_user_id = getCurrentUserId();
$input = json_decode(file_get_contents('php://input'), true);

$request_id = intval($input['request_id'] ?? 0);
$action = $input['action'] ?? ''; // 'accept' or 'reject'

if (!$request_id || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Get friend request details
    $stmt = $pdo->prepare("
        SELECT sender_id, receiver_id FROM friend_requests 
        WHERE request_id = ? AND receiver_id = ? AND status = 'pending'
    ");
    $stmt->execute([$request_id, $current_user_id]);
    $request = $stmt->fetch();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    $pdo->beginTransaction();

    if ($action === 'accept') {
        // Create friendship (ensure user1_id < user2_id for consistency)
        $user1_id = min($request['sender_id'], $current_user_id);
        $user2_id = max($request['sender_id'], $current_user_id);

        $stmt = $pdo->prepare("
            INSERT INTO user_friends (user1_id, user2_id, status, requested_by) 
            VALUES (?, ?, 'accepted', ?)
        ");
        $stmt->execute([$user1_id, $user2_id, $request['sender_id']]);

        // Update request status
        $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted' WHERE request_id = ?");
        $stmt->execute([$request_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Friend request accepted']);

    } else {
        // Reject request
        $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'rejected' WHERE request_id = ?");
        $stmt->execute([$request_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Friend request rejected']);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error handling friend request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>