<?php
require_once '../config/config.php';


// Check if user is logged in
if (!validateSession()) {
    redirectTo('../login.php');
}


$currentUserId = getCurrentUserId();
// ========== PHASE 2 ADDITION START ==========
// Get current user info for display purposes
$currentUserInfo = $pdo->prepare("SELECT section, year, role, first_name, last_name FROM users WHERE user_id = ?");
$currentUserInfo->execute([$currentUserId]);
$currentUser = $currentUserInfo->fetch();
// ========== PHASE 2 ADDITION END ==========


// Handle room join request
if (isset($_GET['action']) && $_GET['action'] == 'join' && isset($_GET['room_id'])) {
    $roomId = (int)$_GET['room_id'];
    try {
        $stmt = $pdo->prepare("SELECT participant_id FROM room_participants WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$roomId, $currentUserId]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO room_participants (room_id, user_id) VALUES (?, ?)");
            $stmt->execute([$roomId, $currentUserId]);
            $_SESSION['success_message'] = 'Successfully joined the chat room!';
        } else {
            $_SESSION['error_message'] = 'You are already in this chat room';
        }
    } catch (PDOException $e) {
        logError("Room join error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to join room';
    }
    redirectTo('rooms.php');
}


// Handle room leave request
if (isset($_GET['action']) && $_GET['action'] == 'leave' && isset($_GET['room_id'])) {
    $roomId = (int)$_GET['room_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM room_participants WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$roomId, $currentUserId]);
        $_SESSION['success_message'] = 'Successfully left the chat room';
    } catch (PDOException $e) {
        logError("Room leave error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to leave room';
    }
    redirectTo('rooms.php');
}


// Get user's joined rooms
try {
    $stmt = $pdo->prepare("
        SELECT r.*, rp.joined_at,
               (SELECT COUNT(*) FROM room_participants rp2 WHERE rp2.room_id = r.room_id) AS participant_count,
               (SELECT COUNT(*) FROM messages m WHERE m.room_id = r.room_id AND m.sent_at > rp.joined_at) AS new_messages,
               u.first_name, u.last_name, u.role as creator_role
        FROM chat_rooms r
        JOIN room_participants rp ON r.room_id = rp.room_id
        LEFT JOIN users u ON r.created_by = u.user_id
        WHERE rp.user_id = ? AND r.is_active = 1
        ORDER BY rp.last_activity DESC
    ");
    $stmt->execute([$currentUserId]);
    $joinedRooms = $stmt->fetchAll();


    // Get available rooms to join
    // ========== PHASE 2 MODIFICATION START - Enhanced room info ==========
    $stmt = $pdo->prepare("
        SELECT r.*,
               (SELECT COUNT(*) FROM room_participants rp WHERE rp.room_id = r.room_id) AS participant_count,
               (SELECT COUNT(*) FROM messages m WHERE m.room_id = r.room_id AND m.sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS recent_messages,
               (SELECT COUNT(DISTINCT rp.user_id) FROM room_participants rp 
                JOIN users u ON rp.user_id = u.user_id 
                WHERE rp.room_id = r.room_id AND u.section IS NOT NULL) as section_diversity,
               u.first_name, u.last_name, u.role as creator_role, u.section as creator_section
        FROM chat_rooms r
        LEFT JOIN users u ON r.created_by = u.user_id
        WHERE r.is_active = 1
          AND r.room_id NOT IN (SELECT room_id FROM room_participants WHERE user_id = ?)
        ORDER BY participant_count DESC, r.created_at DESC
        LIMIT 10
    ");
    // ========== PHASE 2 MODIFICATION END ==========
    $stmt->execute([$currentUserId]);
    $availableRooms = $stmt->fetchAll();


} catch (PDOException $e) {
    logError("Rooms page error: " . $e->getMessage());
    $joinedRooms = [];
    $availableRooms = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Chat Rooms – <?php echo SITE_NAME; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <style>
    body {
      background: #f8f9fa;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
    }
    .main-container {
      padding: 24px;
    }
    .card-rooms {
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      margin-bottom: 24px;
    }
    .card-rooms .card-header {
      background: #fff;
      border-bottom: none;
      font-weight: 600;
    }
    .room-card {
      border-radius: 12px;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .room-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }
    .badge {
      font-size: 0.85rem;
      user-select: none;
    }
    .btn-primary,
    .btn-outline-primary {
      border-radius: 8px;
    }
    /* ========== PHASE 2 ADDITION START ========== */
    .universal-access-banner {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 1.5rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
    }
    .activity-indicator {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-right: 6px;
    }
    .activity-high {
      background: #28a745;
      box-shadow: 0 0 8px rgba(40, 167, 69, 0.6);
    }
    .activity-medium {
      background: #ffc107;
      box-shadow: 0 0 8px rgba(255, 193, 7, 0.6);
    }
    .activity-low {
      background: #6c757d;
    }
    /* ========== PHASE 2 ADDITION END ========== */
  </style>
</head>
<body>
  <!-- Unified Navigation Bar -->
  <?php include 'navbar.php'; ?>


  <div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
      <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
          <h2><i class="fas fa-door-open text-primary me-2"></i> Chat Rooms</h2>
          <p class="text-muted">Join public chat rooms to connect across sections</p>
        </div>
      </div>
    </div>

    <!-- ========== PHASE 2 ADDITION START - Universal Access Banner ========== -->
    <div class="universal-access-banner">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h5 class="mb-2"><i class="fas fa-globe me-2"></i>Campus-Wide Open Discussions</h5>
          <p class="mb-0 small">
            <i class="fas fa-info-circle me-1"></i>
            All chat rooms are accessible to <strong>all students and faculty</strong> regardless of section or year. 
            Connect with the entire campus community!
          </p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <div class="badge bg-light text-dark px-3 py-2">
            <i class="fas fa-user me-1"></i> 
            <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
            <?php if ($currentUser['section']): ?>
              <span class="ms-2">
                <i class="fas fa-graduation-cap me-1"></i>
                Sec <?php echo $currentUser['section']; ?>, Y<?php echo $currentUser['year']; ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <!-- ========== PHASE 2 ADDITION END ========== -->


    <!-- Alerts -->
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


    <div class="row">
      <!-- Joined Rooms -->
      <div class="col-lg-8 mb-4">
        <div class="card card-rooms">
          <div class="card-header">
            My Chat Rooms (<?php echo count($joinedRooms); ?>)
          </div>
          <div class="card-body">
            <?php if (empty($joinedRooms)): ?>
              <div class="text-center py-5 text-muted">
                <i class="fas fa-door-open fa-2x mb-3"></i>
                <h5>No Chat Rooms Joined</h5>
                <button class="btn btn-outline-primary" onclick="scrollToAvailableRooms()">
                  <i class="fas fa-search"></i> Browse Rooms
                </button>
              </div>
            <?php else: ?>
              <div class="row">
                <?php foreach ($joinedRooms as $room): ?>
                  <div class="col-md-6 mb-3">
                    <div class="card room-card">
                      <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                          <h6 class="mb-0">
                            <!-- ========== PHASE 2 ADDITION START - Activity Indicator ========== -->
                            <?php 
                            $activityClass = 'activity-low';
                            if ($room['participant_count'] > 20) $activityClass = 'activity-high';
                            elseif ($room['participant_count'] > 5) $activityClass = 'activity-medium';
                            ?>
                            <span class="activity-indicator <?php echo $activityClass; ?>"></span>
                            <!-- ========== PHASE 2 ADDITION END ========== -->
                            <i class="fas fa-door-open me-1"></i><?php echo htmlspecialchars($room['room_name']); ?>
                          </h6>
                          <span class="badge bg-info"><?php echo htmlspecialchars($room['room_topic']); ?></span>
                        </div>
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($room['description'] ?: 'No description'); ?></p>
                        <!-- ========== PHASE 2 ADDITION START - Creator Info ========== -->
                        <?php if ($room['creator_role']): ?>
                        <div class="mb-2">
                          <small class="text-muted">
                            <i class="fas fa-user-circle me-1"></i>
                            Created by <?php echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']); ?>
                            <span class="badge bg-secondary" style="font-size: 0.7rem;">
                              <?php echo ucfirst($room['creator_role']); ?>
                            </span>
                          </small>
                        </div>
                        <?php endif; ?>
                        <!-- ========== PHASE 2 ADDITION END ========== -->
                        <div class="d-flex justify-content-between align-items-center">
                          <div>
                            <small class="text-muted"><i class="fas fa-users me-1"></i><?php echo $room['participant_count']; ?> participants</small>
                            <?php if ($room['new_messages'] > 0): ?>
                              <br /><small class="text-danger"><i class="fas fa-comment"></i> <?php echo $room['new_messages']; ?> new messages</small>
                            <?php endif; ?>
                          </div>
                          <div>
                            <a href="room_chat.php?id=<?php echo $room['room_id']; ?>" class="btn btn-sm btn-primary me-2">
                              <i class="fas fa-comment"></i> Enter
                            </a>
                            <div class="btn-group">
                              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-cog"></i>
                              </button>
                              <ul class="dropdown-menu">
                                <li>
                                  <a class="dropdown-item text-danger" href="rooms.php?action=leave&room_id=<?php echo $room['room_id']; ?>" onclick="return confirm('Leave this room?')">
                                    <i class="fas fa-sign-out-alt"></i> Leave Room
                                  </a>
                                </li>
                              </ul>
                            </div>
                          </div>
                        </div>
                        <div class="mt-2">
                          <small class="text-muted">Joined <?php echo formatTimeAgo($room['joined_at']); ?></small>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>


      <!-- Available Rooms -->
      <div class="col-lg-4 mb-4" id="availableRooms">
        <div class="card card-rooms">
          <div class="card-header">
            Available Rooms
            <!-- ========== PHASE 2 ADDITION START ========== -->
            <small class="text-muted">(Campus-wide)</small>
            <!-- ========== PHASE 2 ADDITION END ========== -->
          </div>
          <div class="card-body">
            <?php if (empty($availableRooms)): ?>
              <div class="text-center py-5 text-muted">
                <i class="fas fa-search fa-2x mb-3"></i>
                <h5>No new rooms to join</h5>
              </div>
            <?php else: ?>
              <div style="max-height:600px; overflow-y:auto;">
                <?php foreach ($availableRooms as $room): ?>
                  <div class="room-item border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="flex-grow-1">
                        <h6 class="mb-1">
                          <!-- ========== PHASE 2 ADDITION START - Activity Indicator ========== -->
                          <?php 
                          $activityClass = 'activity-low';
                          if ($room['participant_count'] > 20) $activityClass = 'activity-high';
                          elseif ($room['participant_count'] > 5) $activityClass = 'activity-medium';
                          ?>
                          <span class="activity-indicator <?php echo $activityClass; ?>"></span>
                          <!-- ========== PHASE 2 ADDITION END ========== -->
                          <?php echo htmlspecialchars($room['room_name']); ?>
                          <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($room['room_topic']); ?></span>
                        </h6>
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($room['description'] ?: 'No description'); ?></p>
                        <!-- ========== PHASE 2 ADDITION START - Enhanced Room Info ========== -->
                        <div class="mb-2">
                          <?php if ($room['first_name']): ?>
                          <small class="text-muted d-block">
                            <i class="fas fa-user-circle me-1"></i>
                            Created by <?php echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']); ?>
                            <?php if ($room['creator_section']): ?>
                              (Sec <?php echo $room['creator_section']; ?>)
                            <?php endif; ?>
                          </small>
                          <?php endif; ?>
                        </div>
                        <!-- ========== PHASE 2 ADDITION END ========== -->
                        <small class="text-muted"><i class="fas fa-users me-1"></i><?php echo $room['participant_count']; ?> participants</small>
                        <?php if ($room['recent_messages'] > 0): ?>
                          • <small class="text-success"><i class="fas fa-comment"></i> <?php echo $room['recent_messages']; ?> recent</small>
                        <?php endif; ?>
                        <!-- ========== PHASE 2 ADDITION START - Cross-section indicator ========== -->
                        <?php if ($room['section_diversity'] > 1): ?>
                          <br><small class="text-info">
                            <i class="fas fa-users-cog me-1"></i>
                            Multi-section discussion
                          </small>
                        <?php endif; ?>
                        <!-- ========== PHASE 2 ADDITION END ========== -->
                      </div>
                      <a href="rooms.php?action=join&room_id=<?php echo $room['room_id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus"></i> Join
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function scrollToAvailableRooms() {
      document.getElementById('availableRooms').scrollIntoView({ behavior: 'smooth' });
    }
  </script>
</body>
</html>