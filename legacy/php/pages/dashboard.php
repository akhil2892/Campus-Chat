<?php
require_once '../config/config.php';

// Ensure user is logged in
if (!validateSession()) {
    redirectTo('../login.php');
}

// Get user info
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Get current user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        session_destroy();
        redirectTo('../login.php');
    }

    // Update last activity
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE user_id = ?");
    $stmt->execute([$userId]);

} catch (PDOException $e) {
    logError("Dashboard user fetch error: " . $e->getMessage());
    redirectTo('../login.php');
}

// Get dashboard statistics
try {
    // Get user's groups count
    $stmt = $pdo->prepare("SELECT COUNT(*) as group_count FROM group_members WHERE user_id = ?");
    $stmt->execute([$userId]);
    $groupCount = $stmt->fetch()['group_count'];

    // Get unread messages count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM messages m
        LEFT JOIN message_read_status mrs ON m.message_id = mrs.message_id AND mrs.user_id = ?
        WHERE (
            (m.group_id IN (SELECT group_id FROM group_members WHERE user_id = ?)) OR
            (m.receiver_id = ?) OR
            (m.room_id IN (SELECT room_id FROM room_participants WHERE user_id = ?))
        ) AND m.sender_id != ? AND mrs.read_id IS NULL
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $unreadCount = $stmt->fetch()['unread_count'];

    // Get recent messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name, u.is_anonymous, g.group_name, r.room_name,
        CASE 
            WHEN m.group_id IS NOT NULL THEN 'group'
            WHEN m.room_id IS NOT NULL THEN 'room'
            ELSE 'direct'
        END as message_type
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        LEFT JOIN groups g ON m.group_id = g.group_id
        LEFT JOIN chat_rooms r ON m.room_id = r.room_id
        WHERE (
            (m.group_id IN (SELECT group_id FROM group_members WHERE user_id = ?)) OR
            (m.receiver_id = ?) OR
            (m.room_id IN (SELECT room_id FROM room_participants WHERE user_id = ?))
        )
        ORDER BY m.sent_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $recentMessages = $stmt->fetchAll();

    // Get active chat rooms
    $stmt = $pdo->prepare("
        SELECT r.*, COUNT(rp.user_id) as participant_count
        FROM chat_rooms r
        LEFT JOIN room_participants rp ON r.room_id = rp.room_id
        WHERE r.is_active = 1
        GROUP BY r.room_id
        ORDER BY participant_count DESC, r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $activeRooms = $stmt->fetchAll();

} catch (PDOException $e) {
    logError("Dashboard error: " . $e->getMessage());
    $groupCount = 0;
    $unreadCount = 0;
    $recentMessages = [];
    $activeRooms = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard – Campus Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #212529;
            margin: 0;
            padding: 0;
        }
        /* Navbar */

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 20px rgba(102,126,234,0.4);
        }
        .online-indicator {
            width: 10px;
            height: 10px;
            background-color: #28a745;
            border-radius: 50%;
            display: inline-block;
            margin-left: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        /* Stats Cards */
        .stats-card, .stats-card-2, .stats-card-3, .stats-card-4 {
            border-radius: 15px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stats-card:hover, .stats-card-2:hover, .stats-card-3:hover, .stats-card-4:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stats-card-2 {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stats-card-3 {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stats-card-4 {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        /* Quick Action Buttons */
        .quick-action-btn {
            padding: 20px 0;
            font-size: 1.2rem;
            font-weight: 700;
            gap: 15px;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .quick-action-btn i {
            font-size: 2.5rem;
        }
        .quick-action-btn:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.4);
        }
        /* Recent Messages */
        .message-item {
            padding: 10px 15px;
            border-radius: 12px;
            transition: background-color 0.2s ease;
        }
        .message-item:hover {
            background-color: #e9ecef;
        }
        .message-item strong {
            color: #343a40;
        }
        .badge {
            font-size: 0.8rem;
            user-select: none;
        }
        /* Active Rooms */
        .card .card-header h5 {
            font-weight: 700;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
     <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <!-- Welcome Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body d-flex justify-content-between">
                        <div>
                            <h2><i class="fas fa-hand-wave me-2"></i>Welcome back, <?php echo htmlspecialchars($currentUser['first_name']); ?>!</h2>
                            <p class="mb-0"><i class="fas fa-user-tag me-1"></i><?php echo ucfirst($currentUser['role']); ?> <span class="online-indicator"></span> • Last seen: <?php echo $currentUser['last_seen'] ? formatTimeAgo($currentUser['last_seen']) : 'just now'; ?></p>
                        </div>
                        <div class="fs-1 text-white-50"><i class="fas fa-comments"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card text-center p-4">
                    <i class="fas fa-users fa-3x mb-2"></i>
                    <h3><?php echo $groupCount; ?></h3>
                    <p>My Groups</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card-2 text-center p-4">
                    <i class="fas fa-envelope fa-3x mb-2"></i>
                    <h3><?php echo $unreadCount; ?></h3>
                    <p>Unread Messages</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card-3 text-center p-4">
                    <i class="fas fa-door-open fa-3x mb-2"></i>
                    <h3><?php echo count($activeRooms); ?></h3>
                    <p>Active Rooms</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card-4 text-center p-4">
                    <i class="fas fa-clock fa-3x mb-2"></i>
                    <h3 id="currentTime"><?php echo date('H:i'); ?></h3>
                    <p>Current Time</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt text-primary me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body d-grid gap-3">
                        <a href="groups.php" class="btn btn-outline-primary quick-action-btn">
                            <i class="fas fa-users fa-lg me-2"></i>Join Groups
                        </a>
                        <a href="rooms.php" class="btn btn-outline-success quick-action-btn">
                            <i class="fas fa-door-open fa-lg me-2"></i>Browse Rooms
                        </a>
                        <a href="chat.php" class="btn btn-outline-info quick-action-btn">
                            <i class="fas fa-comments fa-lg me-2"></i>Direct Chat
                        </a>
                        <a href="profile.php" class="btn btn-outline-warning quick-action-btn">
                            <i class="fas fa-user-edit fa-lg me-2"></i>Edit Profile
                        </a>
                        <?php if(in_array(getCurrentUserRole(), ['faculty','lecturer','teacher'])): ?>
                        <a href="reports_dashboard.php" class="btn btn-outline-danger quick-action-btn">
                            <i class="fas fa-flag fa-lg me-2"></i>View Reports
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header">
                        <h5><i class="fas fa-clock text-primary me-2"></i>Recent Messages</h5>
                    </div>
                    <div class="list-group list-group-flush overflow-auto" style="max-height: 440px;">
                        <?php if (empty($recentMessages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comment-slash fa-3x mb-3"></i>
                                <p>No recent messages</p>
                                <small>Join a group to start chatting</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentMessages as $msg): ?>
                                <div class="list-group-item message-item border-0 px-3 py-2 d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                        <?php if ($msg['is_anonymous']): ?>
                                            <span class="badge bg-secondary me-2">
                                                <i class="fas fa-user-secret"></i> Anonymous
                                            </span>
                                        <?php else: ?>
                                            <strong class="text-primary me-2">
                                                <?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?>
                                            </strong>
                                        <?php endif; ?>

                                        <?php if ($msg['message_type'] == 'group'): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-users"></i> <?php echo htmlspecialchars($msg['group_name']); ?>
                                            </span>
                                        <?php elseif ($msg['message_type'] == 'room'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($msg['room_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-comment"></i> Direct
                                            </span>
                                        <?php endif; ?>
                                        </div>

                                        <p class="mb-0 text-truncate" style="max-width: 280px;">
                                            <?php echo htmlspecialchars($msg['message_text']); ?>
                                        </p>
                                    </div>
                                    <small class="text-muted ms-3 fw-light">
                                        <?php echo formatTimeAgo($msg['sent_at']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Chat Rooms -->
        <?php if (!empty($activeRooms)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5><i class="fas fa-fire text-danger me-2"></i>Popular Chat Rooms</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($activeRooms as $room): ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-body">
                                            <h6>
                                                <i class="fas fa-door-open text-success me-1"></i>
                                                <?php echo htmlspecialchars($room['room_name']); ?>
                                            </h6>
                                            <p class="text-muted small mb-3">
                                                <?php echo htmlspecialchars($room['room_topic']); ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-users me-1"></i> <?php echo $room['participant_count']; ?> members
                                                </small>
                                                <a href="room_chat.php?id=<?php echo $room['room_id']; ?>" class="btn btn-primary btn-sm">
                                                    Join <i class="fas fa-arrow-right"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update current time every minute
        function updateTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            document.getElementById('currentTime').textContent = `${hours}:${minutes}`;
        }
        setInterval(updateTime, 60000);

        // Auto-refresh to update unread count every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
