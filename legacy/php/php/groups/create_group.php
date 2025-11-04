<?php
// php/groups/create_group.php - FIXED VERSION
require_once '../../config/config.php';
require_once '../../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!validateSession()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get current user
    $userId = getCurrentUserId();

    // Check if it's JSON input (Phase 2 AJAX) or form POST
    $jsonInput = file_get_contents('php://input');
    $jsonData = json_decode($jsonInput, true);

    if ($jsonData && json_last_error() === JSON_ERROR_NONE) {
        // JSON input from Phase 2 enhanced groups.php
        $groupName = sanitizeInput($jsonData['group_name'] ?? '');
        $description = sanitizeInput($jsonData['description'] ?? '');
        $groupType = sanitizeInput($jsonData['group_type'] ?? '');
        $memberIds = $jsonData['member_ids'] ?? [];
        $section = null;
        $year = null;
    } else {
        // Form POST input (original)
        $groupName = sanitizeInput($_POST['group_name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $groupType = sanitizeInput($_POST['group_type'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        $year = (int)($_POST['year'] ?? 0);
        $memberIds = [];

        // Clear section/year for non-section groups
        if ($groupType !== 'section') {
            $section = null;
            $year = null;
        }
    }

    // Validation
    if (empty($groupName)) {
        echo json_encode(['success' => false, 'message' => 'Group name is required']);
        exit;
    }

    if (empty($groupType) || !in_array($groupType, ['section', 'interest', 'general'])) {
        echo json_encode(['success' => false, 'message' => 'Valid group type is required']);
        exit;
    }

    // Validate section groups
    if ($groupType === 'section') {
        if (empty($section) || $year <= 0) {
            echo json_encode(['success' => false, 'message' => 'Section and year are required for section groups']);
            exit;
        }

        // Check if section group already exists
        $stmt = $pdo->prepare("SELECT group_id FROM groups WHERE group_type = 'section' AND section = ? AND year = ? AND is_active = 1");
        $stmt->execute([$section, $year]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A group for this section and year already exists']);
            exit;
        }
    }

    // Validate friends for personal groups
    if (($groupType === 'interest' || $groupType === 'general') && !empty($memberIds)) {
        foreach ($memberIds as $memberId) {
            if (!areFriends($userId, $memberId)) {
                echo json_encode(['success' => false, 'message' => 'You can only add friends to personal groups']);
                exit;
            }
        }
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Create the group
    $sql = "INSERT INTO groups (group_name, group_type, section, year, description, created_by, is_section_group, auto_created, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, NOW())";

    $stmt = $pdo->prepare($sql);
    $isSection = ($groupType === 'section') ? 1 : 0;

    $stmt->execute([
        $groupName,
        $groupType,
        $section,
        $year,
        $description,
        $userId,
        $isSection
    ]);

    $groupId = $pdo->lastInsertId();

    // Add creator as admin member
    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'admin', NOW())");
    $stmt->execute([$groupId, $userId]);

    // Add selected friends to personal group
    if (($groupType === 'interest' || $groupType === 'general') && !empty($memberIds)) {
        $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
        foreach ($memberIds as $memberId) {
            if ($memberId != $userId) { // Don't add creator twice
                $stmt->execute([$groupId, $memberId]);
            }
        }
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Group created successfully',
        'group_id' => $groupId
    ]);

} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logError("Create group error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create group: ' . $e->getMessage()]);
}
?>