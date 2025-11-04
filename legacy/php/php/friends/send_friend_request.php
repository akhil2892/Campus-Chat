<?php
// ===============================================
// FRIENDS API: Send Friend Request
// Path: php/friends/send_friend_request.php
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

$receiver_id = intval($input['receiver_id'] ?? 0);
$message = trim($input['message'] ?? '');

if (!$receiver_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
    exit;
}

if ($receiver_id === $current_user_id) {
    echo json_encode(['success' => false, 'message' => 'Cannot send friend request to yourself']);
    exit;
}

// Check if users are already friends
if (areFriends($current_user_id, $receiver_id)) {
    echo json_encode(['success' => false, 'message' => 'Already friends']);
    exit;
}

try {
    // Check for existing pending request
    $stmt = $pdo->prepare("
        SELECT 1 FROM friend_requests 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND status = 'pending'
    ");
    $stmt->execute([$current_user_id, $receiver_id, $receiver_id, $current_user_id]);

    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Friend request already exists']);
        exit;
    }

    // Send friend request
    $stmt = $pdo->prepare("
        INSERT INTO friend_requests (sender_id, receiver_id, message, status) 
        VALUES (?, ?, ?, 'pending')
    ");
    $stmt->execute([$current_user_id, $receiver_id, $message]);

    echo json_encode(['success' => true, 'message' => 'Friend request sent']);

} catch (PDOException $e) {
    error_log("Error sending friend request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>