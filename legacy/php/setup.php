# Create setup verification script
setup_script = '''<?php
// ===============================================
// CAMPUS CHAT: PHASE 1 SETUP VERIFICATION
// Place this file in your project root and run it in browser
// ===============================================

require_once 'config/config.php';
require_once 'config/database.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    h2 { color: #333; border-bottom: 2px solid #667eea; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

echo "<h1>🚀 Campus Chat - Phase 1 Setup Verification</h1>";

// Test 1: Database Connection
echo "<div class='test-section'>";
echo "<h2>1. Database Connection Test</h2>";
try {
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetchColumn();
    echo "<p class='success'>✅ Database connected successfully!</p>";
    echo "<p class='info'>MySQL version: $version</p>";
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Test 2: Check if new tables exist
echo "<div class='test-section'>";
echo "<h2>2. New Tables Verification</h2>";
$required_tables = ['user_friends', 'friend_requests'];
$all_tables_exist = true;

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "<p class='success'>✅ Table '$table' exists</p>";
        
        // Show table structure
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p class='info'>Columns: " . implode(', ', array_column($columns, 'Field')) . "</p>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Table '$table' missing</p>";
        $all_tables_exist = false;
    }
}
echo "</div>";

// Test 3: Check if columns were added to existing tables
echo "<div class='test-section'>";
echo "<h2>3. Column Additions Verification</h2>";

// Check users table
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'Field');
    
    if (in_array('profile_picture', $column_names)) {
        echo "<p class='success'>✅ 'profile_picture' column added to users table</p>";
    } else {
        echo "<p class='error'>❌ 'profile_picture' column missing from users table</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error checking users table: " . $e->getMessage() . "</p>";
}

// Check groups table
try {
    $stmt = $pdo->query("DESCRIBE groups");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'Field');
    $required_group_columns = ['is_section_group', 'auto_created', 'section', 'year'];
    
    foreach ($required_group_columns as $req_col) {
        if (in_array($req_col, $column_names)) {
            echo "<p class='success'>✅ '$req_col' column added to groups table</p>";
        } else {
            echo "<p class='error'>❌ '$req_col' column missing from groups table</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error checking groups table: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: Check if helper functions are available
echo "<div class='test-section'>";
echo "<h2>4. Helper Functions Verification</h2>";
$required_functions = [
    'createSectionGroup',
    'addUserToSectionGroup', 
    'areFriends',
    'getUserFriends',
    'searchUsers',
    'getPendingFriendRequests'
];

foreach ($required_functions as $function) {
    if (function_exists($function)) {
        echo "<p class='success'>✅ Function '$function' is available</p>";
    } else {
        echo "<p class='error'>❌ Function '$function' is missing - check config.php</p>";
    }
}
echo "</div>";

// Test 5: Check directory structure
echo "<div class='test-section'>";
echo "<h2>5. Directory Structure Verification</h2>";
$required_dirs = [
    'uploads/',
    'uploads/profiles/',
    'uploads/messages/',
    'uploads/groups/'
];

foreach ($required_dirs as $dir) {
    if (is_dir($dir)) {
        echo "<p class='success'>✅ Directory '$dir' exists</p>";
        
        // Check if writable
        if (is_writable($dir)) {
            echo "<p class='success'>✅ Directory '$dir' is writable</p>";
        } else {
            echo "<p class='warning'>⚠️ Directory '$dir' is not writable - fix permissions (chmod 755)</p>";
        }
    } else {
        echo "<p class='error'>❌ Directory '$dir' missing - will be created automatically</p>";
        // Try to create it
        if (mkdir($dir, 0755, true)) {
            echo "<p class='success'>✅ Created directory '$dir'</p>";
        } else {
            echo "<p class='error'>❌ Failed to create directory '$dir'</p>";
        }
    }
}

// Check for default avatar
$default_avatar = 'uploads/profiles/default-avatar.png';
if (file_exists($default_avatar)) {
    echo "<p class='success'>✅ Default avatar exists</p>";
} else {
    echo "<p class='warning'>⚠️ Default avatar missing - you should add a default-avatar.png file</p>";
}
echo "</div>";

// Test 6: Test section group creation (if functions exist)
echo "<div class='test-section'>";
echo "<h2>6. Section Group Creation Test</h2>";
if (function_exists('createSectionGroup')) {
    try {
        $test_group_id = createSectionGroup('TEST', 1);
        if ($test_group_id) {
            echo "<p class='success'>✅ Section group creation working! Test group ID: $test_group_id</p>";
            
            // Clean up test group
            $stmt = $pdo->prepare("DELETE FROM groups WHERE group_id = ? AND is_section_group = 1 AND section = 'TEST'");
            $stmt->execute([$test_group_id]);
            echo "<p class='info'>ℹ️ Test group cleaned up</p>";
        } else {
            echo "<p class='error'>❌ Section group creation failed</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Section group test error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ Cannot test - createSectionGroup function missing</p>";
}
echo "</div>";

// Test 7: Check existing users for profile pictures
echo "<div class='test-section'>";
echo "<h2>7. Existing Users Check</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, 
                         SUM(CASE WHEN profile_picture IS NOT NULL AND profile_picture != '' THEN 1 ELSE 0 END) as with_pics
                         FROM users");
    $user_stats = $stmt->fetch();
    
    echo "<p class='info'>Total users: {$user_stats['total']}</p>";
    echo "<p class='info'>Users with profile pictures: {$user_stats['with_pics']}</p>";
    
    if ($user_stats['total'] > 0 && $user_stats['with_pics'] < $user_stats['total']) {
        echo "<p class='warning'>⚠️ Some users don't have profile pictures - they'll use default</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error checking users: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Summary
echo "<div class='test-section' style='background-color: #f0f0f0;'>";
echo "<h2>8. Setup Summary</h2>";
echo "<p><strong>Phase 1 setup verification complete!</strong></p>";
if ($all_tables_exist && function_exists('createSectionGroup')) {
    echo "<p class='success'>🎉 Setup looks good! You can proceed to test the features.</p>";
    echo "<p class='info'>Next steps:</p>";
    echo "<ul>";
    echo "<li>Test user registration with section assignment</li>";
    echo "<li>Try the friend search and request features</li>";
    echo "<li>Upload a profile picture</li>";
    echo "</ul>";
} else {
    echo "<p class='warning'>⚠️ Some issues found. Please fix the red cross marks (❌) before proceeding.</p>";
}
echo "</div>";

?>'''

# Save the verification script
with open('verify_phase1_setup.php', 'w') as f:
    f.write(setup_script)

# Create a simple default avatar placeholder script
default_avatar_script = '''<?php
// ===============================================
// CREATE DEFAULT AVATAR PLACEHOLDER
// Run this once to create a simple default avatar
// ===============================================

// Create a simple default avatar (text-based)
$width = 150;
$height = 150;

// Create image
$image = imagecreate($width, $height);

// Colors
$bg_color = imagecolorallocate($image, 102, 126, 234); // #667eea
$text_color = imagecolorallocate($image, 255, 255, 255); // white

// Fill background
imagefill($image, 0, 0, $bg_color);

// Add text
$font_size = 5;
$text = "USER";
$text_width = imagefontwidth($font_size) * strlen($text);
$text_height = imagefontheight($font_size);

$x = ($width - $text_width) / 2;
$y = ($height - $text_height) / 2;

imagestring($image, $font_size, $x, $y, $text, $text_color);

// Create directory if it doesn't exist
if (!is_dir('uploads/profiles/')) {
    mkdir('uploads/profiles/', 0755, true);
}

// Save image
imagepng($image, 'uploads/profiles/default-avatar.png');
imagedestroy($image);

echo "Default avatar created successfully at: uploads/profiles/default-avatar.png";

?>'''

# Save the default avatar script
with open('create_default_avatar.php', 'w') as f:
    f.write(default_avatar_script)

print("✅ Setup & Verification Files Created:")
print("  - verify_phase1_setup.php (Run this in browser to verify setup)")
print("  - create_default_avatar.php (Run this to create default avatar)")

print("\n📁 Instructions:")
print("1. Place both files in your project root directory")
print("2. Run create_default_avatar.php once to create default avatar")
print("3. Run verify_phase1_setup.php to check if everything is working"