<?php
// pages/admin_dashboard.php
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!validateSession()) {
    redirectTo('../login.php');
}

$currentUserId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'admin') {
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    redirectTo('dashboard.php');
}

// Get statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $totalUsers = $stmt->fetchColumn();

    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status = 'active'");
    $totalStudents = $stmt->fetchColumn();

    // Total faculty
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role IN ('faculty', 'lecturer', 'teacher') AND status = 'active'");
    $totalFaculty = $stmt->fetchColumn();

    // Total groups
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM groups WHERE is_active = 1");
    $totalGroups = $stmt->fetchColumn();

    // Total rooms
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_rooms WHERE is_active = 1");
    $totalRooms = $stmt->fetchColumn();

    // Total messages
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
    $totalMessages = $stmt->fetchColumn();

    // Total sections
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sections WHERE is_active = 1");
    $totalSections = $stmt->fetchColumn();

    // Recent users (last 10)
    $stmt = $pdo->query("SELECT user_id, first_name, last_name, email, role, section, year, created_at FROM users WHERE status = 'active' ORDER BY created_at DESC LIMIT 10");
    $recentUsers = $stmt->fetchAll();

    // Recent activity logs
    $stmt = $pdo->query("
        SELECT al.*, u.first_name, u.last_name 
        FROM admin_logs al 
        JOIN users u ON al.admin_id = u.user_id 
        ORDER BY al.created_at DESC 
        LIMIT 15
    ");
    $recentLogs = $stmt->fetchAll();

} catch (PDOException $e) {
    logError("Admin dashboard error: " . $e->getMessage());
    $totalUsers = $totalStudents = $totalFaculty = $totalGroups = $totalRooms = $totalMessages = $totalSections = 0;
    $recentUsers = [];
    $recentLogs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .bg-primary-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-success-gradient { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .bg-warning-gradient { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
        .bg-info-gradient { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .bg-danger-gradient { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }

        .admin-nav-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            display: block;
        }
        .admin-nav-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            color: #667eea;
        }
        .admin-nav-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Admin Header -->
        <div class="admin-header">
            <h2><i class="fas fa-shield-alt me-2"></i>Admin Dashboard</h2>
            <p class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! Manage your campus chat system.</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary-gradient me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $totalUsers; ?></h3>
                            <p class="text-muted mb-0">Total Users</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success-gradient me-3">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $totalStudents; ?></h3>
                            <p class="text-muted mb-0">Students</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning-gradient me-3">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $totalFaculty; ?></h3>
                            <p class="text-muted mb-0">Faculty</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info-gradient me-3">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $totalSections; ?></h3>
                            <p class="text-muted mb-0">Sections</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h4 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h4>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <a href="admin_users.php" class="admin-nav-card">
                    <div class="admin-nav-icon"><i class="fas fa-users-cog"></i></div>
                    <h5>Manage Users</h5>
                    <p class="text-muted small">View, edit, and delete users</p>
                </a>
            </div>

            <div class="col-md-3 mb-3">
                <a href="admin_sections.php" class="admin-nav-card">
                    <div class="admin-nav-icon"><i class="fas fa-th-list"></i></div>
                    <h5>Manage Sections</h5>
                    <p class="text-muted small">Create and manage sections</p>
                </a>
            </div>

            <div class="col-md-3 mb-3">
                <a href="admin_groups.php" class="admin-nav-card">
                    <div class="admin-nav-icon"><i class="fas fa-users"></i></div>
                    <h5>Manage Groups</h5>
                    <p class="text-muted small">View and manage all groups</p>
                </a>
            </div>

            <div class="col-md-3 mb-3">
                <a href="admin_settings.php" class="admin-nav-card">
                    <div class="admin-nav-icon"><i class="fas fa-cog"></i></div>
                    <h5>System Settings</h5>
                    <p class="text-muted small">Configure system settings</p>
                </a>
            </div>
        </div>

        <!-- Recent Users and Activity -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Recent Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Section</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentUsers)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No recent users</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentUsers as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span></td>
                                                <td><?php echo $user['section'] ? $user['section'] . '-Y' . $user['year'] : 'N/A'; ?></td>
                                                <td><small><?php echo formatTimeAgo($user['created_at']); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($recentLogs)): ?>
                            <p class="text-center text-muted">No activity logs yet</p>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <div class="d-flex mb-3 pb-3 border-bottom">
                                    <div class="me-3">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong>
                                        <p class="mb-1 small"><?php echo htmlspecialchars($log['description']); ?></p>
                                        <small class="text-muted"><?php echo formatTimeAgo($log['created_at']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>