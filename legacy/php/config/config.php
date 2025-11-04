<?php
// config/config.php
// General configuration settings for Campus Talk

// Start session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

// Site configuration
define('SITE_NAME', 'Campus Chat');
define('SITE_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/campus-Chat/');

// Security settings
define('ENCRYPTION_KEY', 'your-secret-encryption-key-change-this');
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// File upload settings
define('UPLOAD_PATH', 'assets/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'));

// College email domains (add your college domains here)
define('ALLOWED_EMAIL_DOMAINS', array(
    'college.edu',
    'university.ac.in',
    'students.college.edu',
    'faculty.college.edu'
));

// Chat settings
define('MAX_MESSAGE_LENGTH', 1000);
define('MESSAGES_PER_PAGE', 50);
define('CHAT_REFRESH_INTERVAL', 3000); // 3 seconds in milliseconds

// User roles
define('ROLE_STUDENT', 'student');
define('ROLE_LECTURER', 'lecturer');
define('ROLE_ADMIN', 'admin');

// Message types
define('MSG_TYPE_TEXT', 'text');
define('MSG_TYPE_IMAGE', 'image');
define('MSG_TYPE_FILE', 'file');

// Group types
define('GROUP_TYPE_SECTION', 'section');
define('GROUP_TYPE_INTEREST', 'interest');
define('GROUP_TYPE_GENERAL', 'general');

// Error messages
define('ERROR_INVALID_EMAIL', 'Please use a valid college email address');
define('ERROR_PASSWORD_TOO_SHORT', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long');
define('ERROR_USER_NOT_FOUND', 'User not found');
define('ERROR_INVALID_CREDENTIALS', 'Invalid email or password');
define('ERROR_ACCESS_DENIED', 'Access denied');
define('ERROR_SESSION_EXPIRED', 'Your session has expired. Please login again');

// Success messages
define('SUCCESS_REGISTRATION', 'Registration successful! Please check your email to verify your account');
define('SUCCESS_LOGIN', 'Welcome back!');
define('SUCCESS_LOGOUT', 'You have been logged out successfully');
define('SUCCESS_MESSAGE_SENT', 'Message sent successfully');

// Timezone setting
date_default_timezone_set('Asia/Kolkata');

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// FIX: This was using 'user_role' but should be 'role' to match your database
function getCurrentUserRole() {
    return isset($_SESSION['role']) ? strtolower($_SESSION['role']) : null;
}

function isStudent() {
    return getCurrentUserRole() === ROLE_STUDENT;
}

function isLecturer() {
    return getCurrentUserRole() === ROLE_LECTURER;
}

function isAdmin() {
    return getCurrentUserRole() === ROLE_ADMIN;
}

function redirectTo($url) {
    header("Location: " . $url);
    exit();
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateCollegeEmail($email) {
    $domain = substr(strrchr($email, "@"), 1);
    return in_array($domain, ALLOWED_EMAIL_DOMAINS);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

function sendJsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function logError($message, $file = null) {
    $log_message = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($file) {
        $log_message .= " in " . $file;
    }
    error_log($log_message . PHP_EOL, 3, "error.log");
}

// Check if user session is valid
function validateSession() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}
// ===============================================
// PHASE 1: FRIENDS SYSTEM HELPER FUNCTIONS
// Add these functions to the END of your config/config.php file
// ===============================================

/**
 * Create automatic section group for students
 */
function createSectionGroup($section, $year) {
    global $pdo;

    $group_name = "Section {$section} - Year {$year}";
    $description = "Automatic group for students in Section {$section}, Year {$year}";

    try {
        // Check if section group already exists
        $stmt = $pdo->prepare("SELECT group_id FROM groups WHERE is_section_group = 1 AND section = ? AND year = ?");
        $stmt->execute([$section, $year]);
        $existing = $stmt->fetch();

        if ($existing) {
            return $existing['group_id'];
        }

        // Create new section group
        $stmt = $pdo->prepare("INSERT INTO groups (group_name, description, is_section_group, auto_created, section, year, created_by) VALUES (?, ?, 1, 1, ?, ?, 1)");
        $stmt->execute([$group_name, $description, $section, $year]);

        return $pdo->lastInsertId();

    } catch (PDOException $e) {
        error_log("Failed to create section group: " . $e->getMessage());
        return false;
    }
}

/**
 * Add user to section group automatically
 */
function addUserToSectionGroup($user_id, $section, $year) {
    global $pdo;

    // Get or create section group
    $group_id = createSectionGroup($section, $year);
    if (!$group_id) return false;

    try {
        // Check if user is already in the group
        $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        if ($stmt->fetchColumn()) {
            return true; // Already a member
        }

        // Add user to group
        $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())");
        $stmt->execute([$group_id, $user_id]);

        return true;

    } catch (PDOException $e) {
        error_log("Failed to add user to section group: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if two users are friends
 */
function areFriends($user1_id, $user2_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM user_friends 
            WHERE ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)) 
            AND status = 'accepted'
        ");
        $stmt->execute([$user1_id, $user2_id, $user2_id, $user1_id]);

        return $stmt->fetchColumn() ? true : false;

    } catch (PDOException $e) {
        error_log("Error checking friendship: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's friends list
 */
function getUserFriends($user_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.email, u.profile_picture, u.section, u.year,
                   uf.created_at as friends_since
            FROM user_friends uf
            JOIN users u ON (
                CASE 
                    WHEN uf.user1_id = ? THEN u.user_id = uf.user2_id
                    ELSE u.user_id = uf.user1_id
                END
            )
            WHERE (uf.user1_id = ? OR uf.user2_id = ?) 
            AND uf.status = 'accepted'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error getting user friends: " . $e->getMessage());
        return [];
    }
}

/**
 * Search users for potential friends
 */
function searchUsers($current_user_id, $search_term, $limit = 20) {
    global $pdo;

    $search = "%{$search_term}%";

    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.email, u.profile_picture, u.section, u.year,
                   CASE 
                       WHEN uf.friendship_id IS NOT NULL THEN 'friends'
                       WHEN fr.request_id IS NOT NULL AND fr.sender_id = ? THEN 'request_sent'
                       WHEN fr.request_id IS NOT NULL AND fr.receiver_id = ? THEN 'request_received'
                       ELSE 'none'
                   END as friendship_status
            FROM users u
            LEFT JOIN user_friends uf ON (
                (uf.user1_id = ? AND uf.user2_id = u.user_id) OR 
                (uf.user2_id = ? AND uf.user1_id = u.user_id)
            ) AND uf.status = 'accepted'
            LEFT JOIN friend_requests fr ON (
                (fr.sender_id = ? AND fr.receiver_id = u.user_id) OR 
                (fr.receiver_id = ? AND fr.sender_id = u.user_id)
            ) AND fr.status = 'pending'
            WHERE u.user_id != ? AND u.status = 'active'
            AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)
            ORDER BY u.first_name, u.last_name
            LIMIT ?
        ");
        $stmt->execute([
            $current_user_id, $current_user_id, $current_user_id, $current_user_id, 
            $current_user_id, $current_user_id, $current_user_id, 
            $search, $search, $search, $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error searching users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pending friend requests for a user
 */
function getPendingFriendRequests($user_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT fr.request_id, fr.sender_id, fr.message, fr.created_at,
                   u.first_name, u.last_name, u.profile_picture, u.section, u.year
            FROM friend_requests fr
            JOIN users u ON fr.sender_id = u.user_id
            WHERE fr.receiver_id = ? AND fr.status = 'pending'
            ORDER BY fr.created_at DESC
        ");
        $stmt->execute([$user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error getting friend requests: " . $e->getMessage());
        return [];
    }
}

/**
 * Create uploads directory structure
 */
function createUploadsDirectories() {
    $directories = [
        'uploads/profiles/',
        'uploads/messages/',
        'uploads/groups/'
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                // Create .htaccess for security
                $htaccess_content = "Options -Indexes\n<Files *.php>\n    Deny from all\n</Files>";
                file_put_contents($dir . '.htaccess', $htaccess_content);
                error_log("Created directory: " . $dir);
            } else {
                error_log("Failed to create directory: " . $dir);
            }
        }
    }
}

/**
 * Format time ago helper function
 */
if (!function_exists('formatTimeAgo')) {
    function formatTimeAgo($timestamp) {
        $time = time() - strtotime($timestamp);

        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60) . ' minutes ago';
        if ($time < 86400) return floor($time/3600) . ' hours ago';
        if ($time < 2592000) return floor($time/86400) . ' days ago';
        if ($time < 31536000) return floor($time/2592000) . ' months ago';

        return floor($time/31536000) . ' years ago';
    }
}

// Initialize upload directories when config is loaded
createUploadsDirectories();

// ===============================================
// END OF PHASE 1 CONFIG ADDITIONS
// ===============================================

// Include database configuration
require_once 'database.php';
?>
