<?php
require_once 'config/config.php';

// If user is already logged in, redirect to dashboard
if (validateSession()) {
    redirectTo('pages/dashboard.php');
}

// Initialize error message
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email) || empty($password)) {
        $_SESSION['error_message'] = 'Please fill in all fields';
    } else {
        try {
            // Database connection
            require_once 'config/database.php';
            
            // Check user credentials
            $stmt = $pdo->prepare("
                SELECT user_id, email, password_hash, first_name, last_name, role, is_verified, status 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password_hash'])) {
                // Skip email verification check for now
                // if (!$user['is_verified']) {
                //     $_SESSION['error_message'] = 'Please verify your email address before logging in';
                // }
                
                if ($user['status'] !== 'active') {
                    $_SESSION['error_message'] = 'Your account has been suspended. Please contact administrator';
                } else {
                    // Successful login
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = strtolower($user['role']); // FIX: Changed from 'user_role' to 'role' and made lowercase
                    $_SESSION['is_anonymous'] = false;
                    $_SESSION['last_activity'] = time();
                    
                    // Update last seen
                    $updateStmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE user_id = ?");
                    $updateStmt->execute([$user['user_id']]);
                    
                    $_SESSION['success_message'] = SUCCESS_LOGIN;
                    redirectTo('pages/dashboard.php');
                }
            } else {
                $_SESSION['error_message'] = ERROR_INVALID_CREDENTIALS;
            }
            
        } catch (PDOException $e) {
            logError("Login error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred. Please try again';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Campus Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
            margin: 0;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgb(0 0 0 / 0.15);
            max-width: 420px;
            width: 100%;
            padding: 40px 35px;
            animation: fadeInScale 0.5s ease forwards;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .logo {
            font-size: 3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            user-select: none;
        }

        h2.title {
            font-weight: 700;
            margin-bottom: 8px;
            color: #343a40;
            user-select: none;
        }

        p.subtitle {
            color: #666f83;
            margin-bottom: 30px;
            font-size: 0.9rem;
            user-select: none;
        }

        .form-label i {
            color: #667eea;
            margin-right: 8px;
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #dee2e6;
            padding: 14px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 8px rgb(102 126 234 / 0.5);
            background-color: #f8f9fa;
        }

        .btn-login {
            width: 100%;
            border-radius: 12px;
            padding: 13px;
            font-weight: 600;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgb(102 126 234 / 0.4);
            user-select: none;
        }

        .btn-login:hover,
        .btn-login:focus {
            background: linear-gradient(135deg, #5a6ddd 0%, #643ea4 100%);
            box-shadow: 0 7px 20px rgb(102 126 234 / 0.6);
        }

        .social-login {
            background: #f1f3f7;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 12px;
            font-weight: 500;
            letter-spacing: 0.02em;
            color: #495057;
            cursor: pointer;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            user-select: none;
        }

        .social-login i {
            font-size: 1.2rem;
        }

        .social-login:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }

        .text-decoration-none {
            text-decoration: none !important;
        }

        .text-decoration-none:hover {
            text-decoration: underline !important;
        }

        .bottom-text {
            font-size: 0.95rem;
            color: #6c757d;
            user-select: none;
        }

        .btn-close {
            position: relative;
            top: -1px;
        }
    </style>
</head>
<body>
    <div class="login-container shadow">
        <div class="text-center mb-4">
            <i class="fas fa-comments logo"></i>
            <h2 class="title">Campus Chat</h2>
            <p class="subtitle">Welcome back! Please sign in to your account</p>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope"></i>Email Address
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="Enter your college email" required 
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i>Password
                </label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Enter your password" required>
            </div>

            <div class="mb-3 form-check d-flex justify-content-between align-items-center">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label mb-0" for="remember">Remember me</label>
                <a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>

        <div class="text-center my-3">
            <small class="text-muted">or continue with</small>
        </div>

        <div class="d-flex gap-3 mb-4">
            <button class="social-login flex-fill">
                <i class="fab fa-google text-danger"></i> Google
            </button>
            <button class="social-login flex-fill">
                <i class="fab fa-microsoft text-primary"></i> Microsoft
            </button>
        </div>

        <div class="text-center bottom-text">
            <p class="mb-0">
                Don't have an account? 
                <a href="register.php" class="text-decoration-none fw-bold">Sign up here</a>
            </p>
        </div>

        <div class="text-center mt-4">
            <p class="text-muted small">
                Connect with your college community through secure group chats,
                open chat rooms, and anonymous discussions. Bridge the communication gap between students and sections.
            </p>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-focus email field
    document.getElementById('email').focus();

    // Animate input labels
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.style.transform = 'translateY(-4px)';
            input.parentElement.style.transition = 'transform 0.3s ease';
        });
        input.addEventListener('blur', () => {
            input.parentElement.style.transform = 'translateY(0)';
        });
    });
</script>
</body>
</html>

