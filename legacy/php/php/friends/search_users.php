<?php
// ===============================================
// FRIENDS API: Search Users for Friend Requests
// Path: php/friends/search_users.php
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
$search_term = $_GET['q'] ?? '';
$limit = intval($_GET['limit'] ?? 20);

if (strlen($search_term) < 2) {
    echo json_encode([
        'success' => true,
        'users' => [],
        'message' => 'Enter at least 2 characters to search'
    ]);
    exit;
}

$users = searchUsers($current_user_id, $search_term, $limit);

echo json_encode([
    'success' => true,
    'users' => $users,
    'count' => count($users)
]);
?>