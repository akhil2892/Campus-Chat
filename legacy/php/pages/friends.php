<?php
// ===============================================
// FRIENDS PAGE
// Path: pages/friends.php
// ===============================================

require_once '../config/config.php';
require_once '../config/database.php';

if (!validateSession()) {
    redirectTo('../login.php');
}

$current_user_id = getCurrentUserId();
$user_role = getCurrentUserRole();

// Get friends and friend requests
$friends = getUserFriends($current_user_id);
$friend_requests = getPendingFriendRequests($current_user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - Campus Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .friend-card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .friend-card:hover {
            transform: translateY(-2px);
        }
        .avatar-sm {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .search-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <!-- Search Users -->
        <div class="search-section">
            <h3><i class="fas fa-search"></i> Find Friends</h3>
            <div class="input-group mt-3">
                <input type="text" class="form-control" id="userSearch" placeholder="Search by name...">
                <button class="btn btn-light" type="button" id="searchBtn">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </div>

        <!-- Search Results -->
        <div id="searchResults" class="mb-4" style="display: none;">
            <h4>Search Results</h4>
            <div id="searchResultsContent"></div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <!-- Friend Requests -->
                <?php if (!empty($friend_requests)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-user-plus"></i> Friend Requests (<?php echo count($friend_requests); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($friend_requests as $request): ?>
                                <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                    <img src="../<?php echo $request['profile_picture'] ?: 'uploads/profiles/default-avatar.png'; ?>" 
                                         alt="Avatar" class="avatar-sm me-3">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></h6>
                                        <small class="text-muted">Section <?php echo $request['section']; ?>, Year <?php echo $request['year']; ?></small>
                                        <?php if ($request['message']): ?>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($request['message']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <button class="btn btn-success btn-sm me-1" onclick="handleFriendRequest(<?php echo $request['request_id']; ?>, 'accept')">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="handleFriendRequest(<?php echo $request['request_id']; ?>, 'reject')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <!-- My Friends -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-users"></i> My Friends (<?php echo count($friends); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($friends)): ?>
                            <p class="text-muted text-center">No friends yet. Search and add some friends!</p>
                        <?php else: ?>
                            <?php foreach ($friends as $friend): ?>
                                <div class="d-flex align-items-center mb-3 p-2 border rounded friend-card">
                                    <img src="../<?php echo $friend['profile_picture'] ?: 'uploads/profiles/default-avatar.png'; ?>" 
                                         alt="Avatar" class="avatar-sm me-3">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($friend['first_name'] . ' ' . $friend['last_name']); ?></h6>
                                        <small class="text-muted">Section <?php echo $friend['section']; ?>, Year <?php echo $friend['year']; ?></small>
                                        <div class="text-muted" style="font-size: 0.8rem;">Friends since <?php echo date('M Y', strtotime($friend['friends_since'])); ?></div>
                                    </div>
                                    <div>
                                        <a href="chat.php?user=<?php echo $friend['user_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-comment"></i> Chat
                                        </a>
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
    <script>
        // Search functionality
        document.getElementById('searchBtn').addEventListener('click', searchUsers);
        document.getElementById('userSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchUsers();
            }
        });

        function searchUsers() {
            const query = document.getElementById('userSearch').value.trim();
            if (query.length < 2) {
                alert('Please enter at least 2 characters');
                return;
            }

            fetch(`../php/friends/search_users.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displaySearchResults(data.users);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function displaySearchResults(users) {
            const resultsDiv = document.getElementById('searchResults');
            const contentDiv = document.getElementById('searchResultsContent');

            if (users.length === 0) {
                contentDiv.innerHTML = '<p class="text-muted">No users found</p>';
            } else {
                let html = '<div class="row">';
                users.forEach(user => {
                    const buttonHtml = getActionButton(user);
                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="card friend-card">
                                <div class="card-body d-flex align-items-center">
                                    <img src="../${user.profile_picture || 'uploads/profiles/default-avatar.png'}" 
                                         alt="Avatar" class="avatar-sm me-3">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">${user.first_name} ${user.last_name}</h6>
                                        <small class="text-muted">Section ${user.section}, Year ${user.year}</small>
                                    </div>
                                    <div>
                                        ${buttonHtml}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                contentDiv.innerHTML = html;
            }

            resultsDiv.style.display = 'block';
        }

        function getActionButton(user) {
            switch (user.friendship_status) {
                case 'friends':
                    return `<a href="chat.php?user=${user.user_id}" class="btn btn-primary btn-sm"><i class="fas fa-comment"></i> Chat</a>`;
                case 'request_sent':
                    return '<span class="badge bg-warning">Request Sent</span>';
                case 'request_received':
                    return '<span class="badge bg-info">Request Received</span>';
                default:
                    return `<button class="btn btn-success btn-sm" onclick="sendFriendRequest(${user.user_id})"><i class="fas fa-user-plus"></i> Add Friend</button>`;
            }
        }

        function sendFriendRequest(userId) {
            fetch('../php/friends/send_friend_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    receiver_id: userId,
                    message: ''
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Friend request sent!');
                    searchUsers(); // Refresh search results
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function handleFriendRequest(requestId, action) {
            fetch('../php/friends/accept_friend_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload(); // Refresh page
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>