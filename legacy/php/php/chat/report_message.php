<?php
require_once '../../config/config.php';
header('Content-Type: application/json');

if (!validateSession()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$reporterUserId = getCurrentUserId();
$messageId = (int)($_POST['message_id'] ?? 0);
$facultyUserId = (int)($_POST['faculty_id'] ?? 0);
$reason = sanitizeInput($_POST['reason'] ?? '');
$details = sanitizeInput($_POST['details'] ?? '');

if (!$messageId || !$facultyUserId || !$reason) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    // Get the message and sender info
    $stmt = $pdo->prepare("
        SELECT m.sender_id, m.message_text, m.message_type, m.file_path,
               m.group_id, m.room_id, m.receiver_id, m.is_anonymous,
               u.first_name, u.last_name
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.message_id = ?
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();

    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit;
    }

    // Verify faculty exists and has correct role
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role IN ('faculty','lecturer','teacher') AND status = 'active'");
    $stmt->execute([$facultyUserId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Invalid faculty selected']);
        exit;
    }

    // Check if already reported by this user
    $stmt = $pdo->prepare("SELECT report_id FROM message_reports WHERE message_id = ? AND reporter_user_id = ?");
    $stmt->execute([$messageId, $reporterUserId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You have already reported this message']);
        exit;
    }

    // Insert the report with real user IDs (even if message was anonymous)
    $stmt = $pdo->prepare("
        INSERT INTO message_reports 
        (message_id, reporter_user_id, reported_user_id, faculty_user_id, report_reason, report_text, reported_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $messageId,
        $reporterUserId,
        $message['sender_id'], // Real sender ID, even if message was anonymous
        $facultyUserId,
        $reason,
        $details
    ]);

    echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>
