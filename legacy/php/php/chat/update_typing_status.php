<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/typing_status_error.log');
error_reporting(E_ALL);

ob_start();
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!validateSession()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Use your helper function to get logged in user ID:
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

$chat_type = $input['chat_type'] ?? '';
$chat_id   = (int)($input['chat_id'] ?? 0);
$is_typing = (int)($input['is_typing'] ?? 0);

if (!in_array($chat_type, ['group','room','direct']) || $chat_id <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

try {
    if ($is_typing) {
        $stmt = $pdo->prepare("
            INSERT INTO typing_status 
                (user_id, chat_type, chat_id, is_typing, last_typing_at)
            VALUES (?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE is_typing=1, last_typing_at=NOW()
        ");
        $stmt->execute([$user_id, $chat_type, $chat_id]);
    } else {
        $stmt = $pdo->prepare("
            DELETE FROM typing_status 
            WHERE user_id = ? AND chat_type = ? AND chat_id = ?
        ");
        $stmt->execute([$user_id, $chat_type, $chat_id]);
    }
    // Cleanup stale entries
    $pdo->prepare("
        DELETE FROM typing_status 
        WHERE last_typing_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)
    ")->execute();

    ob_end_clean();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
