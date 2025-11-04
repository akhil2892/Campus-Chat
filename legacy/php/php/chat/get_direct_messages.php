<?php
require_once '../../config/config.php';
header('Content-Type: application/json');

if (!validateSession()) {
    echo json_encode([]);
    exit;
}

$currentUserId = getCurrentUserId();
$convId = (int)($_GET['conv_id'] ?? 0);

// Get conversation participants and validate access
$stmt = $pdo->prepare("SELECT user1_id, user2_id FROM direct_conversations WHERE conversation_id = ?");
$stmt->execute([$convId]);
$conv = $stmt->fetch();
if (!$conv || ($conv['user1_id'] != $currentUserId && $conv['user2_id'] != $currentUserId)) {
    echo json_encode([]);
    exit;
}

$otherId = ($conv['user1_id'] == $currentUserId) ? $conv['user2_id'] : $conv['user1_id'];

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
        CASE WHEN m.sender_id = ? AND recipient_read.read_id IS NOT NULL THEN 1 ELSE 0 END AS is_read
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    LEFT JOIN message_read_status recipient_read 
      ON recipient_read.message_id = m.message_id
      AND recipient_read.user_id = m.receiver_id
    WHERE m.group_id IS NULL
      AND m.room_id IS NULL
      AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
    ORDER BY m.sent_at ASC
");
$stmt->execute([
    $currentUserId,
    $currentUserId, $otherId,
    $otherId, $currentUserId
]);
echo json_encode($stmt->fetchAll());
?>
