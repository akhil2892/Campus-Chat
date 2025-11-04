<!-- header.php -->
<?php
if (!isset($_SESSION)) session_start();
require_once '../config/config.php';
$userRole = $_SESSION['role'] ?? getCurrentUserRole(); // Use session for efficiency, fallback to helper if needed


// ========== PHASE 2 ADDITION START - Get friend request count for badge ==========
$friendRequestCount = 0;
if (function_exists('getPendingFriendRequests') && isset($_SESSION['user_id'])) {
    $currentUserId = $_SESSION['user_id'];
    $pendingRequests = getPendingFriendRequests($currentUserId);
    $friendRequestCount = count($pendingRequests);
}
// ========== PHASE 2 ADDITION END ==========
?>



<nav class="navbar navbar-expand-lg navbar-dark"
  style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:0.3rem 0; font-size:1rem;">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php">
      <i class="fas fa-comments me-2"></i>Campus Chat
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='dashboard.php')echo' active';?>" href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='groups.php')echo' active';?>" href="groups.php"><i class="fas fa-users me-1"></i>Groups</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='rooms.php')echo' active';?>" href="rooms.php"><i class="fas fa-door-open me-1"></i>Chat Rooms</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='chat.php')echo' active';?>" href="chat.php"><i class="fas fa-comments me-1"></i>Direct Messages</a>
        </li>
        <!-- ========== PHASE 2 ADDITION START - Friends Link with Badge ========== -->
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='friends.php')echo' active';?>" href="friends.php">
            <i class="fas fa-user-friends me-1"></i>Friends
            <?php if ($friendRequestCount > 0): ?>
              <span class="badge bg-warning text-dark ms-1"><?php echo $friendRequestCount; ?></span>
            <?php endif; ?>
          </a>
        </li>
        <!-- ========== PHASE 2 ADDITION END ========== -->
        <?php if (in_array($userRole, ['faculty','lecturer','teacher'])): ?>
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='reports_dashboard.php')echo' active';?>" href="reports_dashboard.php"><i class="fas fa-flag me-1"></i>Reports</a>
        </li>
        <?php endif; ?>
        <!-- ========== ADMIN PANEL ADDITION START - Admin Link ========== -->
        <?php if ($userRole === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='admin_dashboard.php' || basename($_SERVER['PHP_SELF'])=='admin_sections.php' || basename($_SERVER['PHP_SELF'])=='admin_users.php' || basename($_SERVER['PHP_SELF'])=='admin_groups.php' || basename($_SERVER['PHP_SELF'])=='admin_settings.php')echo' active';?>" href="admin_dashboard.php">
            <i class="fas fa-shield-alt me-1"></i>Admin
          </a>
        </li>
        <?php endif; ?>
        <!-- ========== ADMIN PANEL ADDITION END ========== -->
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['first_name']); ?>
            <!-- ========== PHASE 2 ADDITION START - Friend Request Badge in Dropdown ========== -->
            <?php if ($friendRequestCount > 0): ?>
              <span class="badge bg-warning text-dark ms-1"><?php echo $friendRequestCount; ?></span>
            <?php endif; ?>
            <!-- ========== PHASE 2 ADDITION END ========== -->
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <!-- ========== PHASE 2 ADDITION START - Friends Menu Item ========== -->
            <li>
              <a class="dropdown-item" href="friends.php">
                <i class="fas fa-user-friends"></i> My Friends
                <?php if ($friendRequestCount > 0): ?>
                  <span class="badge bg-warning text-dark ms-1"><?php echo $friendRequestCount; ?></span>
                <?php endif; ?>
              </a>
            </li>
            <!-- ========== PHASE 2 ADDITION END ========== -->
            <!-- ========== ADMIN PANEL ADDITION START - Admin Menu Items ========== -->
            <?php if ($userRole === 'admin'): ?>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header"><i class="fas fa-shield-alt me-1"></i>Admin Panel</h6></li>
            <li><a class="dropdown-item" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a class="dropdown-item" href="admin_sections.php"><i class="fas fa-th-list"></i> Sections</a></li>
            <li><a class="dropdown-item" href="admin_users.php"><i class="fas fa-users-cog"></i> Users</a></li>
            <li><a class="dropdown-item" href="admin_groups.php"><i class="fas fa-users"></i> Groups</a></li>
            <li><a class="dropdown-item" href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <?php endif; ?>
            <!-- ========== ADMIN PANEL ADDITION END ========== -->
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../php/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>


<!-- ========== PHASE 2 ADDITION START - Enhanced Styling for Badges ========== -->
<style>
/* Make badges more visible and professional */
.nav-link .badge {
  font-size: 0.75rem;
  padding: 0.25em 0.5em;
  border-radius: 10px;
  font-weight: 600;
  animation: pulse 2s infinite;
}


@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}


.dropdown-item .badge {
  font-size: 0.7rem;
  padding: 0.2em 0.4em;
}


/* Ensure active link styling works with badge */
.nav-link.active {
  font-weight: 600;
  border-bottom: 2px solid white;
}


/* Responsive navbar improvements */
@media (max-width: 991.98px) {
  .navbar-nav .nav-link {
    padding: 0.7rem 1rem;
  }


  .nav-link .badge {
    position: relative;
    top: -1px;
  }
}

/* ========== ADMIN PANEL ADDITION START - Admin Menu Styling ========== */
.dropdown-header {
  color: #667eea !important;
  font-weight: 600;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.5rem 1rem;
}

.dropdown-item i {
  width: 20px;
  text-align: center;
  margin-right: 8px;
  color: #667eea;
}

.dropdown-item:hover i {
  color: white;
}
/* ========== ADMIN PANEL ADDITION END ========== */
</style>
<!-- ========== PHASE 2 ADDITION END ========== -->