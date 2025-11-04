# Create alternative solutions for the default avatar issue

# Solution 1: Simple file-based default avatar
simple_avatar_script = '''<?php
// ===============================================
// SIMPLE DEFAULT AVATAR CREATOR (No GD Required)
// This creates a simple text placeholder instead of image
// ===============================================

// Create uploads/profiles directory if it doesn't exist
if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
}

if (!is_dir('uploads/profiles')) {
    mkdir('uploads/profiles', 0755, true);
}

// Create a simple text file indicator that the default avatar system is ready
$avatar_info = "Default avatar system initialized. Using CSS-based avatar placeholders.\\nCreated: " . date('Y-m-d H:i:s');
file_put_contents('uploads/profiles/default-avatar-info.txt', $avatar_info);

echo "<h2>✅ Default Avatar System Setup Complete!</h2>";
echo "<p><strong>Since GD extension is not available, we're using a CSS-based approach.</strong></p>";
echo "<p>✅ Directories created successfully</p>";
echo "<p>✅ Default avatar system initialized</p>";
echo "<p><strong>Next:</strong> Run verify_phase1_setup.php to continue</p>";
?>'''

# Save the simple version
with open('create_default_avatar_simple.php', 'w') as f:
    f.write(simple_avatar_script)

# Solution 2: SVG-based default avatar
svg_avatar_script = '''<?php
// ===============================================
// SVG DEFAULT AVATAR CREATOR (Alternative Method)
// This creates an SVG-based default avatar
// ===============================================

// Create directories
if (!is_dir('uploads')) mkdir('uploads', 0755, true);
if (!is_dir('uploads/profiles')) mkdir('uploads/profiles', 0755, true);

// Simple SVG default avatar as text content
$svg_content = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="150" height="150" viewBox="0 0 150 150" xmlns="http://www.w3.org/2000/svg">
  <rect width="150" height="150" fill="#667eea"/>
  <circle cx="75" cy="60" r="25" fill="white"/>
  <path d="M40 120 Q40 100 75 100 Q110 100 110 120 L110 150 L40 150 Z" fill="white"/>
  <text x="75" y="140" font-family="Arial" font-size="12" fill="#667eea" text-anchor="middle">USER</text>
</svg>';

// Save SVG as default avatar
file_put_contents('uploads/profiles/default-avatar.svg', $svg_content);

echo "<h2>✅ SVG Default Avatar Created Successfully!</h2>";
echo "<p>✅ Created: uploads/profiles/default-avatar.svg</p>";
echo "<p>✅ Directories created successfully</p>";
echo "<p><strong>Note:</strong> This creates an SVG file that works like an image</p>";
echo "<p><strong>Next:</strong> Run verify_phase1_setup.php to continue</p>";
?>'''

# Save the SVG version
with open('create_default_avatar_svg.php', 'w') as f:
    f.write(svg_avatar_script)

# Solution 3: Updated database to use SVG instead of PNG
database_fix = '''-- ===============================================
-- DATABASE FIX: Update default avatar path to SVG
-- Run this SQL if you use the SVG solution
-- ===============================================

-- Update existing users to use SVG default avatar
UPDATE users 
SET profile_picture = 'uploads/profiles/default-avatar.svg' 
WHERE profile_picture = 'uploads/profiles/default-avatar.png' 
   OR profile_picture IS NULL 
   OR profile_picture = '';

-- Update the default value for new users
ALTER TABLE users 
MODIFY profile_picture VARCHAR(255) DEFAULT 'uploads/profiles/default-avatar.svg';

SELECT "Default avatar paths updated to SVG" as status;'''

with open('database_fix_avatar.sql', 'w') as f:
    f.write(database_fix)

print("✅ Created Alternative Solutions for GD Extension Issue:")
print("\n📁 Files Created:")
print("1. create_default_avatar_simple.php - Simple directory setup")
print("2. create_default_avatar_svg.php - Creates SVG avatar") 
print("3. database_fix_avatar.sql - Updates database for SVG")

print("\n🎯 RECOMMENDED SOLUTION:")
print("Since GD extension is not available, use the SVG approach:")
print()
print("STEP 1: Run create_default_avatar_svg.php")
print("   This creates a nice SVG-based default avatar")
print()  
print("STEP 2: Run database_fix_avatar.sql")
print("   This updates your database to use the SVG file")
print()
print("STEP 3: Run verify_phase1_setup.php") 
print("   This will verify everything is working")

print("\n💡 Why SVG Solution is Better:")
print("✅ No PHP extensions required")
print("✅ Scalable vector graphics (looks good at any size)")
print("✅ Small file size")
print("✅ Works in all modern browsers")
print("✅ Easy to customize colors/design")