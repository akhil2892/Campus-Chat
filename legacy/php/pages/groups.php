<?php
require_once '../config/config.php';

if (!validateSession()) {
    redirectTo('../login.php');
}

$currentUserId = getCurrentUserId();

// Handle group join request
if (isset($_GET['action']) && $_GET['action'] == 'join' && isset($_GET['group_id'])) {
    $groupId = (int)$_GET['group_id'];

    try {
        $stmt = $pdo->prepare("SELECT member_id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $currentUserId]);

        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
            $stmt->execute([$groupId, $currentUserId]);
            $_SESSION['success_message'] = 'Successfully rejoined the group!';
        }
    } catch (PDOException $e) {
        logError("Group join error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to join group';
    }

    redirectTo('groups.php');
}

// Handle group leave request
if (isset($_GET['action']) && $_GET['action'] == 'leave' && isset($_GET['group_id'])) {
    $groupId = (int)$_GET['group_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $currentUserId]);
        $_SESSION['success_message'] = 'Successfully left the group';
    } catch (PDOException $e) {
        logError("Group leave error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to leave group';
    }

    redirectTo('groups.php');
}

// Handle group delete request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['group_id'])) {
    $groupId = (int)$_GET['group_id'];

    try {
        $stmt = $pdo->prepare("SELECT created_by FROM groups WHERE group_id = ? AND is_section_group = 0");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();

        if ($group && $group['created_by'] == $currentUserId) {
            $stmt = $pdo->prepare("UPDATE groups SET is_active = 0 WHERE group_id = ?");
            $stmt->execute([$groupId]);
            $_SESSION['success_message'] = 'Group deleted successfully';
        } else {
            $_SESSION['error_message'] = 'Only the group creator can delete the group';
        }
    } catch (PDOException $e) {
        logError("Group delete error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to delete group';
    }

    redirectTo('groups.php');
}

// Get user's groups
try {
    // Get user info
    $userInfo = $pdo->prepare("SELECT section, year, role FROM users WHERE user_id = ?");
    $userInfo->execute([$currentUserId]);
    $user = $userInfo->fetch();

    // Get all user's groups with creator info
    $stmt = $pdo->prepare("
        SELECT g.*, gm.role as member_role, gm.joined_at,
               u.first_name as creator_first, u.last_name as creator_last,
               (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.group_id) as member_count,
               (SELECT COUNT(*) FROM messages m WHERE m.group_id = g.group_id AND m.sent_at > gm.joined_at) as message_count
        FROM groups g
        JOIN group_members gm ON g.group_id = gm.group_id
        LEFT JOIN users u ON g.created_by = u.user_id
        WHERE gm.user_id = ? AND g.is_active = 1
        ORDER BY g.is_section_group DESC, gm.joined_at DESC
    ");
    $stmt->execute([$currentUserId]);
    $allGroups = $stmt->fetchAll();

    // FIXED: Separate section groups from personal groups
    $sectionGroups = [];
    $personalGroups = [];
    foreach ($allGroups as $group) {
        if ($group['is_section_group'] == 1) {
            // It's a section group - add it regardless of section matching
            $sectionGroups[] = $group;
        } else {
            // It's a personal group
            $personalGroups[] = $group;
        }
    }

    // Get groups user can rejoin
    $stmt = $pdo->prepare("
        SELECT g.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id) as member_count
        FROM groups g
        LEFT JOIN users u ON g.created_by = u.user_id
        WHERE g.is_active = 1 
        AND g.is_section_group = 0
        AND g.group_id IN (
            SELECT DISTINCT m.group_id 
            FROM messages m 
            WHERE m.sender_id = ? OR m.group_id IN (
                SELECT group_id FROM group_members WHERE user_id = ?
            )
        )
        AND g.group_id NOT IN (
            SELECT group_id FROM group_members WHERE user_id = ?
        )
        ORDER BY g.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$currentUserId, $currentUserId, $currentUserId]);
    $rejoinableGroups = $stmt->fetchAll();

} catch (PDOException $e) {
    logError("Groups page error: " . $e->getMessage());
    $sectionGroups = [];
    $personalGroups = [];
    $rejoinableGroups = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Groups - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #212529;
            margin: 0;
        }

        .dashboard-container {
            padding: 24px;
        }
        .dashboard-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .dashboard-card h5 {
            font-weight: 600;
            margin-bottom: 20px;
        }
        .hover-card {
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: #fff;
        }
        .hover-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .btn-main {
            padding: 10px 20px;
            border-radius: 8px;
        }
        .badge {
            font-size: 0.85rem;
            user-select: none;
        }
        .section-group-indicator {
            border-left: 4px solid #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8edff 100%);
        }
        .personal-group-indicator {
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container container-fluid">
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-users text-primary me-2"></i> Groups</h2>
                <button class="btn btn-primary btn-main" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                    <i class="fas fa-plus"></i> Create Group
                </button>
            </div>
        </div>

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
            <div class="col-lg-9">
                <!-- Section Groups -->
                <?php if (!empty($sectionGroups)): ?>
                <div class="dashboard-card mb-4">
                    <h5><i class="fas fa-graduation-cap me-2 text-primary"></i> Section Groups (<?php echo count($sectionGroups); ?>)</h5>
                    <div class="row">
                        <?php foreach ($sectionGroups as $group): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card hover-card section-group-indicator">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="card-title mb-0">
                                                <i class="fas fa-users text-primary me-2"></i>
                                                <?php echo htmlspecialchars($group['group_name']); ?>
                                            </h6>
                                            <span class="badge bg-primary">Section</span>
                                        </div>
                                        <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($group['description'] ?: 'Section group for your class'); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-users me-1"></i> <?php echo $group['member_count']; ?> members
                                                <?php if ($group['message_count'] > 0): ?>
                                                <br /><i class="fas fa-comment text-info"></i> <?php echo $group['message_count']; ?> new
                                                <?php endif; ?>
                                            </small>
                                            <a href="group_chat.php?id=<?php echo $group['group_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-comment"></i> Chat
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Personal Groups -->
                <div class="dashboard-card">
                    <h5><i class="fas fa-user-friends me-2 text-success"></i> My Groups (<?php echo count($personalGroups); ?>)</h5>
                    <?php if (empty($personalGroups)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-user-friends fa-2x mb-3"></i>
                            <h6>No Personal Groups Yet</h6>
                            <p class="small">Create a group with your friends</p>
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                                <i class="fas fa-plus"></i> Create Group
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($personalGroups as $group): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card hover-card personal-group-indicator">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0">
                                                    <i class="fas fa-users text-success me-2"></i>
                                                    <?php echo htmlspecialchars($group['group_name']); ?>
                                                </h6>
                                                <span class="badge bg-success"><i class="fas fa-lock"></i> Private</span>
                                            </div>
                                            <?php if ($group['created_by'] == $currentUserId): ?>
                                            <small class="text-muted d-block mb-2">
                                                <i class="fas fa-crown text-warning"></i> You created this group
                                            </small>
                                            <?php else: ?>
                                            <small class="text-muted d-block mb-2">
                                                Created by <?php echo htmlspecialchars($group['creator_first'] . ' ' . $group['creator_last']); ?>
                                            </small>
                                            <?php endif; ?>
                                            <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($group['description'] ?: 'No description'); ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-users me-1"></i> <?php echo $group['member_count']; ?> members
                                                    <?php if ($group['message_count'] > 0): ?>
                                                    <br /><i class="fas fa-comment text-info"></i> <?php echo $group['message_count']; ?> new
                                                    <?php endif; ?>
                                                </small>
                                                <div>
                                                    <a href="group_chat.php?id=<?php echo $group['group_id']; ?>" class="btn btn-sm btn-success me-1">
                                                        <i class="fas fa-comment"></i>
                                                    </a>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if ($group['created_by'] == $currentUserId): ?>
                                                            <li><a class="dropdown-item text-danger" href="groups.php?action=delete&group_id=<?php echo $group['group_id']; ?>" onclick="return confirm('Delete this group? This cannot be undone!')">
                                                                <i class="fas fa-trash"></i> Delete Group
                                                            </a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <?php endif; ?>
                                                            <li><a class="dropdown-item text-warning" href="groups.php?action=leave&group_id=<?php echo $group['group_id']; ?>" onclick="return confirm('Leave this group?')">
                                                                <i class="fas fa-sign-out-alt"></i> Leave Group
                                                            </a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rejoinable Groups Sidebar -->
            <div class="col-lg-3">
                <div class="dashboard-card sticky-top" style="top: 20px;">
                    <h6><i class="fas fa-undo me-2 text-warning"></i>Groups to Join</h6>
                    <?php if (empty($rejoinableGroups)): ?>
                        <p class="text-muted small text-center py-3">No groups to rejoin</p>
                    <?php else: ?>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php foreach ($rejoinableGroups as $group): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong class="d-block small"><?php echo htmlspecialchars($group['group_name']); ?></strong>
                                    <small class="text-muted d-block">
                                        <?php echo $group['member_count']; ?> members
                                    </small>
                                    <a href="groups.php?action=join&group_id=<?php echo $group['group_id']; ?>" class="btn btn-sm btn-outline-primary mt-1 w-100">
                                        <i class="fas fa-plus"></i> Rejoin
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createGroupForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Create New Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-lock me-2"></i>
                            <strong>Private Groups:</strong> Your group will only be visible to you and the friends you add.
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="groupName" required>
                            <label>Group Name</label>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="groupDescription" style="height: 100px;"></textarea>
                            <label>Description (optional)</label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user-friends me-2"></i><strong>Select Friends</strong>
                            </label>
                            <div id="friendCheckboxes" class="border rounded p-3" style="max-height: 250px; overflow-y: auto;">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary"></div>
                                    <small class="d-block mt-2 text-muted">Loading friends...</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#createGroupModal').on('shown.bs.modal', function() {
                loadFriends();
            });

            $('#createGroupForm').on('submit', function(e) {
                e.preventDefault();
                createGroup();
            });
        });

        function loadFriends() {
            $.get('../php/friends/get_friends.php', function(response) {
                if(response.success && response.friends && response.friends.length > 0) {
                    let html = '';
                    response.friends.forEach(friend => {
                        html += `
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" value="${friend.user_id}" id="friend_${friend.user_id}">
                                <label class="form-check-label" for="friend_${friend.user_id}">
                                    <strong>${friend.first_name} ${friend.last_name}</strong>
                                    ${friend.section ? `<small class="text-muted ms-2">(Sec ${friend.section}, Y${friend.year})</small>` : ''}
                                </label>
                            </div>
                        `;
                    });
                    $('#friendCheckboxes').html(html);
                } else {
                    $('#friendCheckboxes').html(
                        '<div class="text-center py-3">' +
                        '<i class="fas fa-user-friends fa-2x text-muted mb-2"></i>' +
                        '<p class="text-muted mb-2">No friends yet</p>' +
                        '<a href="friends.php" class="btn btn-sm btn-primary" target="_blank">' +
                        '<i class="fas fa-user-plus me-1"></i>Add Friends' +
                        '</a></div>'
                    );
                }
            }, 'json');
        }

        function createGroup() {
            const groupName = $('#groupName').val().trim();
            const description = $('#groupDescription').val().trim();

            if(!groupName) {
                alert('Please enter a group name');
                return;
            }

            const selectedFriends = [];
            $('#friendCheckboxes input:checked').each(function() {
                selectedFriends.push(parseInt($(this).val()));
            });

            if(selectedFriends.length === 0) {
                if(!confirm('No friends selected. Create empty group?')) return;
            }

            const submitBtn = $('#createGroupForm button[type="submit"]');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Creating...');

            $.ajax({
                url: '../php/groups/create_group.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    group_name: groupName,
                    description: description,
                    group_type: 'general',
                    member_ids: selectedFriends
                }),
                success: function(response) {
                    if(response.success) {
                        alert('Group created!');
                        location.reload();
                    } else {
                        alert(response.message || 'Failed');
                        submitBtn.prop('disabled', false).html('<i class="fas fa-check me-2"></i>Create Group');
                    }
                },
                error: function() {
                    alert('Error. Try again.');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-check me-2"></i>Create Group');
                }
            });
        }
    </script>
</body>
</html>