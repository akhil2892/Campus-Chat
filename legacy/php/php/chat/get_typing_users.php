<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/typing_users_error.log');
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

$chat_type = $_GET['chat_type']  ?? '';
$chat_id   = (int)($_GET['chat_id'] ?? 0);
// Use your helper:
$user_id   = getCurrentUserId();

if (!in_array($chat_type, ['group','room','direct']) || $chat_id <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            ts.user_id, 
            u.first_name, 
            u.last_name, 
            u.is_anonymous
        FROM typing_status ts
        JOIN users u 
          ON ts.user_id = u.user_id
        WHERE ts.chat_type = ? 
          AND ts.chat_id   = ?
          AND ts.user_id  != ?
          AND ts.is_typing = 1
          AND ts.last_typing_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
        ORDER BY ts.last_typing_at DESC
    ");
    $stmt->execute([$chat_type, $chat_id, $user_id]);

    $typing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = $row['is_anonymous']
            ? 'Anonymous'
            : trim($row['first_name'].' '.$row['last_name']);
        $typing[] = [
            'user_id'      => $row['user_id'],
            'name'         => $name,
            'is_anonymous' => (bool)$row['is_anonymous']
        ];
    }

    ob_end_clean();
    echo json_encode([
        'success'      => true,
        'typing_users' => $typing,
        'count'        => count($typing)
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
