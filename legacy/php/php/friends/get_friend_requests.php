<?php
// ===============================================
// FRIENDS API: Get Pending Friend Requests
// Path: php/friends/get_friend_requests.php
// ===============================================

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!validateSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = getCurrentUserId();
$requests = getPendingFriendRequests($current_user_id);

echo json_encode([
    'success' => true,
    'requests' => $requests,
    'count' => count($requests)
]);
?>