<?php
require_once 'config/config.php';


// Redirect if already logged in
if (isLoggedIn()) {
    redirectTo('pages/dashboard.php');
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $rollNumber = sanitizeInput($_POST['roll_number']);
    $section = sanitizeInput($_POST['section']);
    $year = (int)$_POST['year'];
    $role = sanitizeInput($_POST['role']);

    // Validation
    $errors = [];

    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        $errors[] = 'All required fields must be filled';
    }

    if (!validateCollegeEmail($email)) {
        $errors[] = ERROR_INVALID_EMAIL;
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = ERROR_PASSWORD_TOO_SHORT;
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }

    if ($role === 'student' && (empty($rollNumber) || empty($section) || empty($year))) {
        $errors[] = 'Roll number, section, and year are required for students';
    }

    // ========== ADMIN FEATURE ADDITION START ==========
    // Validate section exists in database (for students)
    if ($role === 'student' && !empty($section)) {
        try {
            $stmt = $pdo->prepare("SELECT section_id FROM sections WHERE section_code = ? AND is_active = 1");
            $stmt->execute([$section]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid section selected. Please contact administrator.';
            }
        } catch (PDOException $e) {
            logError("Section validation error: " . $e->getMessage());
        }
    }
    // ========== ADMIN FEATURE ADDITION END ==========

    if (empty($errors)) {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists';
            } else {
                // Check if roll number already exists (for students)
                if ($role === 'student') {
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE roll_number = ?");
                    $stmt->execute([$rollNumber]);

                    if ($stmt->fetch()) {
                        $errors[] = 'An account with this roll number already exists';
                    }
                }

                if (empty($errors)) {
                    // ========== ADMIN FEATURE ADDITION START ==========
                    // Begin transaction for data integrity
                    $pdo->beginTransaction();
                    // ========== ADMIN FEATURE ADDITION END ==========

                    // Create new user
                    $passwordHash = hashPassword($password);
                    $verificationToken = generateToken(32);

                    $sql = "INSERT INTO users (email, password_hash, first_name, last_name, roll_number, section, year, role, verification_token) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $email, 
                        $passwordHash, 
                        $firstName, 
                        $lastName, 
                        $role === 'student' ? $rollNumber : null,
                        $role === 'student' ? $section : null,
                        $role === 'student' ? $year : null,
                        $role,
                        $verificationToken
                    ]);

                    // ========== ADMIN FEATURE ADDITION START ==========
                    $userId = $pdo->lastInsertId();

                    // Auto-assign student to section group
                    if ($role === 'student' && !empty($section) && $year > 0) {
                        // Check if section group exists
                        $stmt = $pdo->prepare("
                            SELECT group_id 
                            FROM groups 
                            WHERE group_type = 'section' 
                            AND section = ? 
                            AND year = ? 
                            AND is_active = 1
                        ");
                        $stmt->execute([$section, $year]);
                        $existingGroup = $stmt->fetch();

                        if ($existingGroup) {
                            // Group exists, add user to it
                            $groupId = $existingGroup['group_id'];
                            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                            $stmt->execute([$groupId, $userId]);
                        } else {
                            // Create new section group
                            $groupName = "Section " . strtoupper($section) . " - Year " . $year;
                            $description = "Auto-created group for Section " . strtoupper($section) . ", Year " . $year;

                            $stmt = $pdo->prepare("
                                INSERT INTO groups (group_name, description, group_type, section, year, created_by, is_section_group, auto_created, is_active, created_at) 
                                VALUES (?, ?, 'section', ?, ?, ?, 1, 1, 1, NOW())
                            ");
                            $stmt->execute([$groupName, $description, $section, $year, $userId]);

                            $groupId = $pdo->lastInsertId();

                            // Add user as first member
                            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                            $stmt->execute([$groupId, $userId]);
                        }
                    }

                    // Commit transaction
                    $pdo->commit();
                    // ========== ADMIN FEATURE ADDITION END ==========

                    // Send verification email (simplified for now)
                    // In a real implementation, you would send an actual email
                    $_SESSION['success_message'] = SUCCESS_REGISTRATION;
                    redirectTo('login.php');
                }
            }
        } catch (PDOException $e) {
            // ========== ADMIN FEATURE ADDITION START ==========
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // ========== ADMIN FEATURE ADDITION END ==========

            logError("Registration error: " . $e->getMessage());
            $errors[] = 'An error occurred. Please try again';
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

// ========== ADMIN FEATURE ADDITION START ==========
// Get active sections from database for dropdown
$activeSections = [];
try {
    $stmt = $pdo->query("SELECT section_code, section_name, department FROM sections WHERE is_active = 1 ORDER BY section_code");
    $activeSections = $stmt->fetchAll();
} catch (PDOException $e) {
    logError("Failed to load sections: " . $e->getMessage());
}
// ========== ADMIN FEATURE ADDITION END ==========
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Campus Chat</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }


        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            max-width: 600px;
            margin: 0 auto;
            padding: 40px;
            animation: slideInUp 0.6s ease-out;
        }


        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        .logo-section {
            text-align: center;
            margin-bottom: 35px;
        }


        .logo {
            font-size: 3.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            display: block;
        }


        .title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }


        .subtitle {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 0;
        }


        .form-floating {
            margin-bottom: 20px;
        }


        .form-floating > .form-control,
        .form-floating > .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafbfc;
        }


        .form-floating > .form-control:focus,
        .form-floating > .form-select:focus {
            border-color: #667eea;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.3);
            outline: none;
        }


        .form-floating > label {
            color: #6c757d;
            font-weight: 500;
            padding-left: 12px;
        }


        .form-floating > label i {
            color: #667eea;
            margin-right: 8px;
        }


        .btn-register {
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            margin-top: 10px;
        }


        .btn-register:hover {
            background: linear-gradient(135deg, #5a6ddd 0%, #643ea4 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
            color: white;
        }


        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }


        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }


        .form-check-label {
            color: #495057;
            font-size: 0.9rem;
        }


        .form-check-label a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }


        .form-check-label a:hover {
            text-decoration: underline;
        }


        .auth-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }


        .auth-links p {
            color: #6c757d;
            margin-bottom: 0;
        }


        .auth-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }


        .auth-links a:hover {
            text-decoration: underline;
        }


        #studentFields {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }


        #studentFields.show {
            animation: fadeInDown 0.4s ease-out;
        }


        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        .alert {
            border-radius: 12px;
            border: none;
            font-size: 0.9rem;
            margin-bottom: 25px;
        }


        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }


        .password-strength {
            margin-top: 8px;
            font-size: 0.8rem;
        }


        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: #e9ecef;
            margin-top: 5px;
            overflow: hidden;
        }


        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }


        .strength-weak { background: #dc3545; width: 20%; }
        .strength-fair { background: #fd7e14; width: 40%; }
        .strength-good { background: #ffc107; width: 60%; }
        .strength-strong { background: #198754; width: 80%; }
        .strength-excellent { background: #20c997; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="logo-section">
                <i class="fas fa-user-plus logo"></i>
                <h1 class="title">Join Campus Chat</h1>
                <p class="subtitle">Create your account to connect with your campus community</p>
            </div>


            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   placeholder="First Name" required 
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            <label for="first_name">
                                <i class="fas fa-user"></i> First Name
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   placeholder="Last Name" required 
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            <label for="last_name">
                                <i class="fas fa-user"></i> Last Name
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="name@college.edu" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <label for="email">
                        <i class="fas fa-envelope"></i> College Email
                    </label>
                    <div class="form-text">
                        Use your official college email address (e.g., @college.edu)
                    </div>
                </div>

                <div class="form-floating">
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>
                            Student
                        </option>
                        <option value="lecturer" <?php echo (isset($_POST['role']) && $_POST['role'] === 'lecturer') ? 'selected' : ''; ?>>
                            Lecturer
                        </option>
                    </select>
                    <label for="role">
                        <i class="fas fa-id-badge"></i> Role
                    </label>
                </div>

                <!-- Student-specific fields -->
                <div id="studentFields" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="roll_number" name="roll_number" 
                                       placeholder="Roll Number" 
                                       value="<?php echo isset($_POST['roll_number']) ? htmlspecialchars($_POST['roll_number']) : ''; ?>">
                                <label for="roll_number">
                                    <i class="fas fa-id-card"></i> Roll Number
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- ========== ADMIN FEATURE ADDITION START - Section Dropdown ========== -->
                            <div class="form-floating">
                                <select class="form-select" id="section" name="section">
                                    <option value="">Select Section</option>
                                    <?php if (!empty($activeSections)): ?>
                                        <?php foreach ($activeSections as $sec): ?>
                                            <option value="<?php echo htmlspecialchars($sec['section_code']); ?>"
                                                    <?php echo (isset($_POST['section']) && $_POST['section'] === $sec['section_code']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sec['section_code']); ?> 
                                                <?php if ($sec['section_name']): ?>
                                                    - <?php echo htmlspecialchars($sec['section_name']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No sections available - Contact admin</option>
                                    <?php endif; ?>
                                </select>
                                <label for="section">
                                    <i class="fas fa-users"></i> Section
                                </label>
                            </div>
                            <!-- ========== ADMIN FEATURE ADDITION END ========== -->
                        </div>
                    </div>

                    <div class="form-floating">
                        <select class="form-select" id="year" name="year">
                            <option value="">Select Year</option>
                            <option value="1" <?php echo (isset($_POST['year']) && $_POST['year'] == '1') ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2" <?php echo (isset($_POST['year']) && $_POST['year'] == '2') ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3" <?php echo (isset($_POST['year']) && $_POST['year'] == '3') ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4" <?php echo (isset($_POST['year']) && $_POST['year'] == '4') ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                        <label for="year">
                            <i class="fas fa-graduation-cap"></i> Academic Year
                        </label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Password" required>
                            <label for="password">
                                <i class="fas fa-lock"></i> Password
                            </label>
                        </div>
                        <div class="password-strength" id="passwordStrength" style="display: none;">
                            <span class="strength-text">Password strength: <span id="strengthLabel">Weak</span></span>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthBar"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm Password" required>
                            <label for="confirm_password">
                                <i class="fas fa-lock"></i> Confirm Password
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-text mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" onclick="showTerms()">Terms of Service</a> 
                        and <a href="#" onclick="showPrivacy()">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>

            <div class="auth-links">
                <p>
                    Already have an account? 
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt me-1"></i>Sign in here
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Show/hide student fields based on role selection
        function toggleStudentFields() {
            const role = $('#role').val();
            const studentFields = $('#studentFields');

            if (role === 'student') {
                studentFields.show().addClass('show');
                $('#roll_number, #section, #year').attr('required', true);
            } else {
                studentFields.hide().removeClass('show');
                $('#roll_number, #section, #year').attr('required', false);
            }
        }

        // Initialize on page load
        $(document).ready(function() {
            toggleStudentFields();

            // Set initial values if form was submitted with errors
            <?php if (isset($_POST['role'])): ?>
            toggleStudentFields();
            <?php endif; ?>
        });

        // Handle role change
        $('#role').on('change', toggleStudentFields);

        // Password strength indicator
        $('#password').on('input', function() {
            const password = $(this).val();
            const strength = getPasswordStrength(password);
            const strengthIndicator = $('#passwordStrength');
            const strengthBar = $('#strengthBar');
            const strengthLabel = $('#strengthLabel');

            if (password.length > 0) {
                strengthIndicator.show();

                // Remove existing strength classes
                strengthBar.removeClass('strength-weak strength-fair strength-good strength-strong strength-excellent');

                if (strength <= 1) {
                    strengthBar.addClass('strength-weak');
                    strengthLabel.text('Weak');
                } else if (strength === 2) {
                    strengthBar.addClass('strength-fair');
                    strengthLabel.text('Fair');
                } else if (strength === 3) {
                    strengthBar.addClass('strength-good');
                    strengthLabel.text('Good');
                } else if (strength === 4) {
                    strengthBar.addClass('strength-strong');
                    strengthLabel.text('Strong');
                } else {
                    strengthBar.addClass('strength-excellent');
                    strengthLabel.text('Excellent');
                }
            } else {
                strengthIndicator.hide();
            }
        });

        // Form validation
        $('#registerForm').on('submit', function(e) {
            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();
            const email = $('#email').val();
            const role = $('#role').val();

            // Password validation
            if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                e.preventDefault();
                alert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long');
                return false;
            }

            // Password match validation
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }

            // Email domain validation
            const allowedDomains = <?php echo json_encode(ALLOWED_EMAIL_DOMAINS); ?>;
            const emailDomain = email.split('@')[1];

            if (!allowedDomains.includes(emailDomain)) {
                e.preventDefault();
                alert('Please use a valid college email address');
                return false;
            }

            // Student fields validation
            if (role === 'student') {
                const rollNumber = $('#roll_number').val();
                const section = $('#section').val();
                const year = $('#year').val();

                if (!rollNumber || !section || !year) {
                    e.preventDefault();
                    alert('Please fill in all student information fields');
                    return false;
                }
            }
        });

        function getPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }

        function showTerms() {
            alert('Terms of Service modal will be implemented');
        }

        function showPrivacy() {
            alert('Privacy Policy modal will be implemented');
        }
    </script>
</body>
</html>