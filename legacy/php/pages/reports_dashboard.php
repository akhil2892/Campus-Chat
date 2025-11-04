<?php
require_once '../config/config.php';

if (!isset($_SESSION)) session_start();
$userRole = $_SESSION['role'] ?? getCurrentUserRole();

if (!validateSession()) {
    redirectTo('../login.php');
}
$currentUserId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Check if user is faculty/lecturer/teacher
if (!in_array($userRole, ['faculty', 'lecturer', 'teacher'])) {
    $_SESSION['error_message'] = 'Access denied. Faculty only.';
    redirectTo('dashboard.php');
}

// Handle report status updates
if (($_POST['action'] ?? '') === 'update_status') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($reportId && in_array($status, ['pending', 'reviewed'])) {
        try {
            $stmt = $pdo->prepare("UPDATE message_reports SET status = ? WHERE report_id = ? AND faculty_user_id = ?");
            $stmt->execute([$status, $reportId, $currentUserId]);
            $_SESSION['success_message'] = 'Report status updated successfully';
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Failed to update report status';
        }
    }
    header('Location: reports_dashboard.php');
    exit;
}

// Fetch reports assigned to this faculty member
$stmt = $pdo->prepare("
    SELECT 
        mr.report_id,
        mr.message_id,
        mr.report_reason,
        mr.report_text,
        mr.reported_at,
        mr.status,
        m.message_text,
        m.message_type,
        m.file_path,
        m.sent_at as message_sent_at,
        m.is_anonymous as message_was_anonymous,
        reporter.first_name as reporter_first_name,
        reporter.last_name as reporter_last_name,
        reporter.role as reporter_role,
        reporter.section as reporter_section,
        reported.first_name as reported_first_name,
        reported.last_name as reported_last_name,
        reported.role as reported_role,
        reported.section as reported_section,
        CASE WHEN m.group_id IS NOT NULL THEN 'Group'
             WHEN m.room_id IS NOT NULL THEN 'Room'
             ELSE 'Direct Message' END as chat_type,
        COALESCE(g.group_name, cr.room_name, 'Direct Chat') as chat_name
    FROM message_reports mr
    JOIN messages m ON mr.message_id = m.message_id
    JOIN users reporter ON mr.reporter_user_id = reporter.user_id
    JOIN users reported ON mr.reported_user_id = reported.user_id
    LEFT JOIN groups g ON m.group_id = g.group_id
    LEFT JOIN chat_rooms cr ON m.room_id = cr.room_id
    WHERE mr.faculty_user_id = ?
    ORDER BY mr.reported_at DESC
");
$stmt->execute([$currentUserId]);
$reports = $stmt->fetchAll();

$pendingCount = count(array_filter($reports, fn($r) => $r['status'] === 'pending'));
$reviewedCount = count(array_filter($reports, fn($r) => $r['status'] === 'reviewed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Reports Dashboard – <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .navbar.bg-gradient-primary {
        background: linear-gradient(135deg,#377dff 0,#667eea 74%);
    }
    .dashboard-sidebar {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 24px;
        min-height: 600px;
    }
    .dashboard-main {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 24px;
        min-height: 600px;
        padding: 1.5rem;
    }
    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
        border-radius: 12px 12px 0 0;
        border: none;
    }
    .dashboard-section-title {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 24px;
        font-weight: 700;
        font-size: 1.35rem;
        box-shadow: 0 2px 6px rgba(0,0,0,.05);
    }
    .badge.bg-warning { color: #222; }
    .border-warning { border-color: #ffc107 !important; }
    .border-success { border-color: #198754 !important; }
    .card-combo-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px 12px 0 0;
        color: white;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        font-size: 1.12rem;
    }
    .report-meta-time {
        color: #ffefc3;
        font-size: 0.95em;
        margin-left: 1rem;
        letter-spacing: 0.5px;
    }
    .reported-msg-bubble {
        background: #f4f8fb;
        border-radius: 14px;
        padding: 1rem 1.2rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        color: #22313a;
        font-size: 1rem;
        margin-bottom: 0.2rem;
        word-break: break-word;
    }
    .report-details {
        background: #e2eaf8;
        border-radius: 10px;
        padding: 0.7rem 1rem;
        margin-bottom: 1rem;
        color: #22313a;
    }
    .report-user-section, .report-reporter-section {
        background: #171a26 !important;
        color: #d7fcff !important;
        border-radius: 10px !important;
        padding: 1rem;
    }
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border-radius: 6px;
        margin-top: 8px;
        padding: 9px 12px;
        font-size: 0.96em;
    }
    </style>
</head>
<body>

<?php
// Navbar block for consistent header and faculty-only "Reports" link
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
        <?php if (in_array($userRole, ['faculty','lecturer','teacher'])): ?>
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='reports_dashboard.php')echo' active';?>" href="reports_dashboard.php"><i class="fas fa-flag me-1"></i>Reports</a>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['first_name']); ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a class="dropdown-item" href="../php/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row">
        <aside class="col-lg-4 dashboard-sidebar p-0">
            <div class="dashboard-section-title">
                <i class="fas fa-flag text-warning"></i> Reports Overview
            </div>
            <div class="list-group list-group-flush">
                <span class="list-group-item">
                    <span class="badge bg-warning text-dark me-2">Pending: <?php echo $pendingCount; ?></span>
                    <span class="badge bg-success">Reviewed: <?php echo $reviewedCount; ?></span>
                </span>
                <span class="list-group-item">
                    <i class="fas fa-info-circle text-info me-2"></i>
                    Only visible to faculty
                </span>
            </div>
        </aside>
        <section class="col-lg-8 dashboard-main">
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
            <?php if (empty($reports)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No reports received yet</h5>
                    <p class="text-muted">Reports submitted by students will appear here for your review.</p>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($reports as $report): ?>
            <div class="card mb-3 <?php echo $report['status'] === 'pending' ? 'border-warning' : 'border-success'; ?>">
                <div class="card-combo-header">
                    <span>
                        <span class="badge <?php echo $report['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                            <?php echo ucfirst($report['status']); ?>
                        </span>
                        <span class="ms-2"><?php echo htmlspecialchars($report['report_reason']); ?></span>
                        <span class="report-meta-time">• <?php echo formatTimeAgo($report['reported_at']); ?></span>
                    </span>
                    <form method="POST" class="d-inline-block m-0 p-0">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                        <?php if ($report['status'] === 'pending'): ?>
                            <button type="submit" name="status" value="reviewed" class="btn btn-sm btn-success">
                                <i class="fas fa-check"></i> Mark Reviewed
                            </button>
                        <?php else: ?>
                            <button type="submit" name="status" value="pending" class="btn btn-sm btn-warning">
                                <i class="fas fa-undo"></i> Mark Pending
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="card-body bg-dark">
                    <div class="row">
                        <!-- Reported Message -->
                        <div class="col-md-6">
                            <h6 class="text-info mb-2"><i class="fas fa-message me-1"></i> Reported Message</h6>
                            <div class="reported-msg-bubble mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <small>
                                        <strong><?php echo htmlspecialchars($report['chat_type']); ?>:</strong> 
                                        <?php echo htmlspecialchars($report['chat_name']); ?>
                                    </small>
                                    <small class="text-muted">
                                        <?php echo formatTimeAgo($report['message_sent_at']); ?>
                                        <?php if ($report['message_was_anonymous']): ?>
                                            <i class="fas fa-user-secret ms-1" title="Sent anonymously"></i>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php if ($report['message_type'] === 'image'): ?>
                                    <img src="../<?php echo htmlspecialchars($report['file_path']); ?>" class="img-fluid mb-2" style="max-height: 150px;">
                                <?php elseif ($report['message_type'] === 'file'): ?>
                                    <p><i class="fas fa-file"></i>
                                        <a href="../<?php echo htmlspecialchars($report['file_path']); ?>" download>
                                            <?php echo htmlspecialchars($report['message_text']); ?>
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['message_text'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($report['report_text']): ?>
                                <h6 class="text-info"><i class="fas fa-info-circle me-1"></i> Additional Details</h6>
                                <div class="report-details">
                                    <span><?php echo nl2br(htmlspecialchars($report['report_text'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- User Details -->
                        <div class="col-md-6">
                            <h6 class="text-success mb-2"><i class="fas fa-user me-1"></i> Reporter Details</h6>
                            <div class="report-reporter-section mb-3">
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($report['reporter_first_name'] . ' ' . $report['reporter_last_name']); ?></p>
                                <p class="mb-1"><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($report['reporter_role'])); ?></p>
                                <?php if ($report['reporter_section']): ?>
                                    <p class="mb-0"><strong>Section:</strong> <?php echo htmlspecialchars($report['reporter_section']); ?></p>
                                <?php endif; ?>
                            </div>
                            <h6 class="text-danger mb-2"><i class="fas fa-user-times me-1"></i> Reported User Details</h6>
                            <div class="report-user-section">
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($report['reported_first_name'] . ' ' . $report['reported_last_name']); ?></p>
                                <p class="mb-1"><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($report['reported_role'])); ?></p>
                                <?php if ($report['reported_section']): ?>
                                    <p class="mb-0"><strong>Section:</strong> <?php echo htmlspecialchars($report['reported_section']); ?></p>
                                <?php endif; ?>
                                <?php if ($report['message_was_anonymous']): ?>
                                    <div class="alert alert-warning mt-2 mb-0 py-1">
                                        <small><i class="fas fa-exclamation-triangle"></i> This message was sent anonymously, but real identity is shown above</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
