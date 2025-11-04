<?php
require_once '../../config/config.php';
header('Content-Type: application/json');

if (!validateSession()) {
    echo json_encode([]);
    exit;
}

$userId = getCurrentUserId();
$roomId = (int)($_GET['room_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT 
        m.message_id,
        m.message_text,
        m.sent_at,
        m.is_anonymous,
        m.message_type,
        m.file_path,
        u.first_name,
        u.last_name,
        m.sender_id,
        CASE WHEN rs.read_id IS NULL THEN 0 ELSE 1 END AS is_read
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    LEFT JOIN message_read_status rs
      ON rs.message_id = m.message_id
      AND rs.user_id = ?
    WHERE m.room_id = ?
    ORDER BY m.sent_at ASC
");
$stmt->execute([$userId, $roomId]);
echo json_encode($stmt->fetchAll());
?>
