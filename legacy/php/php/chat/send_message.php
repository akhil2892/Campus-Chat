<?php
require_once '../../config/config.php';
header('Content-Type: application/json');

if (!validateSession()) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = getCurrentUserId();
$groupId = (int)($_POST['group_id'] ?? 0);
$text = sanitizeInput($_POST['message_text'] ?? '');
$isAnon = !empty($_POST['is_anonymous']) ? 1 : 0;
$messageType = 'text';
$filePath = null;

if (!empty($_FILES['file']['tmp_name'])) {
    $uploadDir = __DIR__ . '/../../uploads/';
    $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
    $targetPath = $uploadDir . $fileName;
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($_FILES['file']['size'] > 10*1024*1024) {
        echo json_encode(['success' => false, 'error' => 'File too large']);
        exit;
    }
    $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','txt','zip'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Type not allowed']);
        exit;
    }
    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        $messageType = in_array($ext, ['jpg','jpeg','png','gif']) ? 'image' : 'file';
        $filePath = 'uploads/' . $fileName;
        if (empty($text)) $text = $_FILES['file']['name'];
    } else {
        echo json_encode(['success' => false, 'error' => 'Upload failed']);
        exit;
    }
}

if (empty($text) && !$filePath) {
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, group_id, message_text, message_type, file_path, is_anonymous)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $groupId, $text, $messageType, $filePath, $isAnon]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false]);
}
?>
