<?php
require_once '../../config/config.php';
header('Content-Type: application/json');

if (!validateSession()) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = getCurrentUserId();
$messageId = (int)($_POST['message_id'] ?? 0);

if ($messageId <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    // Check if this user already marked this message as read
    $checkStmt = $pdo->prepare("SELECT read_id FROM message_read_status WHERE message_id = ? AND user_id = ?");
    $checkStmt->execute([$messageId, $userId]);
    
    if (!$checkStmt->fetch()) {
        // Insert new read status
        $stmt = $pdo->prepare("INSERT INTO message_read_status (message_id, user_id, read_at) VALUES (?, ?, NOW())");
        $stmt->execute([$messageId, $userId]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
