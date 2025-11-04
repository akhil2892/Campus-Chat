<?php
// php/user/toggle_anonymous.php
require_once '../../config/config.php';

// Check if user is logged in
if (!validateSession()) {
    sendJsonResponse(['error' => 'Unauthorized'], 401);
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Invalid request method'], 405);
}

$isAnonymous = isset($_POST['anonymous']) ? (bool)$_POST['anonymous'] : false;
$userId = getCurrentUserId();

try {
    // Update user's anonymous preference in database
    $stmt = $pdo->prepare("UPDATE users SET is_anonymous = ? WHERE user_id = ?");
    $stmt->execute([$isAnonymous, $userId]);
    
    // Update session
    $_SESSION['is_anonymous'] = $isAnonymous;
    
    sendJsonResponse([
        'success' => true,
        'message' => $isAnonymous ? 'Anonymous mode activated' : 'Anonymous mode deactivated',
        'anonymous' => $isAnonymous
    ]);
    
} catch (PDOException $e) {
    logError("Toggle anonymous error: " . $e->getMessage());
    sendJsonResponse(['error' => 'Database error'], 500);
}
?>