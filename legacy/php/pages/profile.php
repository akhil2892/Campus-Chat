<?php
// ===============================================
// PROFILE PAGE
// Path: pages/profile.php
// ===============================================

require_once '../config/config.php';
require_once '../config/database.php';

if (!validateSession()) {
    redirectTo('../login.php');
}

$current_user_id = getCurrentUserId();
$user_role = getCurrentUserRole();

// Handle profile update
if ($_POST['action'] ?? '' === 'update_profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $profile_picture = null;

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profiles/';
        $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_types) && $_FILES['profile_picture']['size'] <= 5 * 1024 * 1024) {
            $filename = $current_user_id . '_' . time() . '.' . $file_ext;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filepath)) {
                $profile_picture = 'uploads/profiles/' . $filename;
            }
        }
    }

    try {
        if ($profile_picture) {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, profile_picture = ? WHERE user_id = ?");
            $stmt->execute([$first_name, $last_name, $profile_picture, $current_user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?");
            $stmt->execute([$first_name, $last_name, $current_user_id]);
        }

        $_SESSION['success_message'] = 'Profile updated successfully';

    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Failed to update profile';
    }

    header('Location: profile.php');
    exit;
}

// Get current user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$current_user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to load profile';
    redirectTo('dashboard.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Campus Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
        }
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-4">
                <!-- Profile Card -->
                <div class="profile-card">
                    <img src="../<?php echo $user['profile_picture'] ?: 'uploads/profiles/default-avatar.png'; ?>" 
                         alt="Profile Picture" class="profile-avatar mb-3">
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p class="mb-1"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="mb-1"><i class="fas fa-user-tag"></i> <?php echo ucfirst($user['role']); ?></p>
                    <?php if ($user['section']): ?>
                        <p class="mb-0"><i class="fas fa-graduation-cap"></i> Section <?php echo $user['section']; ?>, Year <?php echo $user['year']; ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-8">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Edit Profile Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="profile_picture" class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                       accept="image/*">
                                <div class="form-text">Max file size: 5MB. Allowed types: JPG, PNG, GIF</div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>