<?php
// ===============================================
// FRIENDS API: Get User's Friends List
// Path: php/friends/get_friends.php
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
$friends = getUserFriends($current_user_id);

echo json_encode([
    'success' => true,
    'friends' => $friends,
    'count' => count($friends)
]);
?>