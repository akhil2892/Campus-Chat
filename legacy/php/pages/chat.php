<?php
require_once '../config/config.php';
if (!validateSession()) redirectTo('../login.php');
$currentUserId = getCurrentUserId();

// ========== PHASE 2 ADDITION START - Friends-only verification ==========
// Check if users are friends before allowing conversation
if (isset($_GET['start_chat'], $_GET['user_id'])) {
    $otherUserId = (int)$_GET['user_id'];
    if ($otherUserId != $currentUserId) {
        // Verify friendship before allowing chat
        if (!areFriends($currentUserId, $otherUserId)) {
            $_SESSION['error_message'] = 'You can only message friends. Please send a friend request first.';
            redirectTo('friends.php');
        }
        // ========== PHASE 2 ADDITION END ==========

        $user1 = min($currentUserId, $otherUserId);
        $user2 = max($currentUserId, $otherUserId);
        try {
            $stmt = $pdo->prepare("SELECT conversation_id FROM direct_conversations WHERE user1_id = ? AND user2_id = ?");
            $stmt->execute([$user1, $user2]);
            $existing = $stmt->fetch();
            if ($existing) {
                redirectTo('chat.php?conv=' . $existing['conversation_id']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO direct_conversations (user1_id, user2_id) VALUES (?, ?)");
                $stmt->execute([$user1, $user2]);
                $newConvId = $pdo->lastInsertId();
                redirectTo('chat.php?conv=' . $newConvId);
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Failed to start conversation';
        }
    }
}
$conversationId = isset($_GET['conv']) ? (int)$_GET['conv'] : 0;


// ========== PHASE 2 MODIFICATION START - Get only friends' conversations ==========
$stmt = $pdo->prepare("
    SELECT dc.conversation_id, u.user_id AS other_id, u.first_name, u.last_name, u.profile_picture, u.section, u.year,
           (SELECT message_text FROM messages m WHERE ((m.sender_id = dc.user1_id AND m.receiver_id = dc.user2_id) OR (m.sender_id = dc.user2_id AND m.receiver_id = dc.user1_id)) ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
           (SELECT sent_at FROM messages m WHERE ((m.sender_id = dc.user1_id AND m.receiver_id = dc.user2_id) OR (m.sender_id = dc.user2_id AND m.receiver_id = dc.user1_id)) ORDER BY m.sent_at DESC LIMIT 1) AS last_at
    FROM direct_conversations dc
    JOIN users u ON (u.user_id = IF(dc.user1_id = ?, dc.user2_id, dc.user1_id))
    WHERE (dc.user1_id = ? OR dc.user2_id = ?)
    ORDER BY last_at DESC
");
$stmt->execute([$currentUserId, $currentUserId, $currentUserId]);
$conversations = $stmt->fetchAll();
// ========== PHASE 2 MODIFICATION END ==========


// ========== PHASE 2 MODIFICATION START - Show only friends in available users ==========
// Get friends for new chat (only friends can be messaged)
$availableUsers = getUserFriends($currentUserId);

// Filter out friends already in conversations
$existingConvUserIds = array_column($conversations, 'other_id');
$availableUsers = array_filter($availableUsers, function($user) use ($existingConvUserIds) {
    return !in_array($user['user_id'], $existingConvUserIds);
});
// ========== PHASE 2 MODIFICATION END ==========


$otherUser = null;
if ($conversationId) {
    $stmt = $pdo->prepare("SELECT user1_id, user2_id FROM direct_conversations WHERE conversation_id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch();
    if ($conv && ($conv['user1_id'] == $currentUserId || $conv['user2_id'] == $currentUserId)) {
        $otherId = ($conv['user1_id'] == $currentUserId) ? $conv['user2_id'] : $conv['user1_id'];

        // ========== PHASE 2 ADDITION START - Verify friendship for existing conversation ==========
        if (!areFriends($currentUserId, $otherId)) {
            $_SESSION['error_message'] = 'You can only message friends.';
            redirectTo('friends.php');
        }
        // ========== PHASE 2 ADDITION END ==========

        $stmt = $pdo->prepare("SELECT first_name, last_name, profile_picture, section, year FROM users WHERE user_id = ?");
        $stmt->execute([$otherId]);
        $otherUser = $stmt->fetch();
    }
}
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM users WHERE role IN ('faculty','lecturer','teacher') AND status='active' ORDER BY first_name");
$stmt->execute();
$facultyList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Direct Messages – <?php echo SITE_NAME; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="../css/main.css" />
  <link rel="stylesheet" href="../css/typing-indicator.css" />
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .chat-sidebar {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      margin-bottom: 24px;
      min-height: 600px;
    }
    .chat-main {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
    .list-group-item.active {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
    }
    #chatWindow {
      background: #f4f8fb;
    }
    .input-group .form-control, .form-check-input, .form-select {
      border-radius: 10px;
    }
    .chat-bg-light {
      background: #f4f8fb !important;
    }
    /* Message bubbles */
    .bubble {
      display: inline-block;
      padding: 10px 14px;
      border-radius: 18px;
      margin-bottom: 2px;
      max-width: 75%;
      word-break: break-word;
      font-size: 1rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      position: relative;
    }
    .bubble-my {
      background: #377dff;
      color: #fff;
      text-align: right;
      float: right;
      border-bottom-right-radius: 6px;
      border: 1px solid #377dff;
    }
    .bubble-other {
      background: #fff;
      color: #22313a;
      text-align: left;
      float: left;
      border-bottom-left-radius: 6px;
      border: 1px solid #e3e6eb;
    }
    .bubble-meta {
      font-size: 0.90em;
      opacity: .93;
      margin-top: 2px;
      display: block;
      clear: both;
    }
    .bubble-my .bubble-meta {
      color: #f1f1f1 !important;
      text-align: right;
    }
    .bubble-other .bubble-meta {
      color: #6c757d !important;
      text-align: left;
    }
    .chat-message-wrap {
      clear: both;
      margin-bottom: 10px;
    }
    .form-check-label {
      color: #222 !important;
      font-weight: 500;
      font-size: 1rem;
    }
    /* Make modal labels/text always readable over modal backgrounds */
.modal-content .form-label,
.modal-content label,
.modal-content .form-check-label,
.modal-content .form-select,
.modal-content input,
.modal-content textarea,
.modal-content select,
.modal-content .modal-title {
    color: #222 !important;
    font-weight: 500 !important;
}


.modal-content .form-select,
.modal-content input,
.modal-content textarea {
    background: #fff !important;
    color: #222 !important;
}


.modal-content ::placeholder {
    color: #555 !important;
    opacity: 1 !important;
}

/* ========== PHASE 2 ADDITION START ========== */
.friends-only-banner {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(40, 167, 69, 0.05) 100%);
    border: 2px solid #28a745;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.user-avatar-sm {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.friend-badge {
    background: #28a745;
    color: white;
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 6px;
}
/* ========== PHASE 2 ADDITION END ========== */


  </style>
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="container-fluid mt-4">
  <!-- ========== PHASE 2 ADDITION START - Friends-Only Banner ========== -->
  <div class="friends-only-banner">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h6 class="mb-1">
          <i class="fas fa-user-friends text-success"></i> 
          <strong>Friends-Only Direct Messaging</strong>
        </h6>
        <p class="mb-0 small text-muted">
          <i class="fas fa-lock me-1"></i>
          You can only send direct messages to your friends for privacy and security.
          <a href="friends.php" class="text-success"><strong>Manage friends</strong></a>
        </p>
      </div>
      <div class="col-md-4 text-md-end mt-2 mt-md-0">
        <a href="friends.php" class="btn btn-success btn-sm">
          <i class="fas fa-user-plus"></i> Find Friends
        </a>
      </div>
    </div>
  </div>
  <!-- ========== PHASE 2 ADDITION END ========== -->

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <!-- Conversations sidebar -->
    <aside class="col-lg-4 chat-sidebar p-0">
      <div class="card h-100 d-flex flex-column">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-comments"></i> Conversations</h5>
          <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#newChatModal">
            <i class="fas fa-plus"></i>
          </button>
        </div>
        <div class="card-body p-0 flex-grow-1 overflow-auto">
          <?php if (empty($conversations)): ?>
            <div class="text-center p-4 text-muted">
              <i class="fas fa-comment-slash fa-2x mb-2"></i>
              <p>No conversations yet</p>
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newChatModal">
                Start a conversation
              </button>
            </div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($conversations as $conv): ?>
                <a href="chat.php?conv=<?php echo $conv['conversation_id']; ?>"
                  class="list-group-item list-group-item-action<?php echo ($conv['conversation_id'] == $conversationId) ? ' active' : ''; ?>">
                    <!-- ========== PHASE 2 ADDITION START - Avatar & Friend Badge ========== -->
                    <div class="d-flex align-items-center mb-2">
                      <img src="../<?php echo $conv['profile_picture'] ?: 'uploads/profiles/default-avatar.svg'; ?>" 
                           alt="Avatar" class="user-avatar-sm me-2">
                      <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                          <span>
                            <?= htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']) ?>
                            <span class="friend-badge"><i class="fas fa-user-friends"></i> Friend</span>
                          </span>
                          <small><?= $conv['last_at'] ? formatTimeAgo($conv['last_at']) : '' ?></small>
                        </div>
                        <?php if ($conv['section']): ?>
                          <small class="text-muted">Sec <?= $conv['section'] ?>, Y<?= $conv['year'] ?></small>
                        <?php endif; ?>
                      </div>
                    </div>
                    <!-- ========== PHASE 2 ADDITION END ========== -->
                    <p class="mb-1 text-truncate"><?= htmlspecialchars($conv['last_message'] ?? 'No messages yet') ?></p>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </aside>


    <!-- Chat main area -->
    <section class="col-lg-8 chat-main">
      <?php if ($conversationId && $otherUser): ?>
        <div>
          <div class="card-header mb-3">
            <!-- ========== PHASE 2 MODIFICATION START - Enhanced Chat Header ========== -->
            <div class="d-flex align-items-center">
              <img src="../<?php echo $otherUser['profile_picture'] ?: 'uploads/profiles/default-avatar.svg'; ?>" 
                   alt="Avatar" class="user-avatar-sm me-3">
              <div>
                <h5 class="mb-0">
                  <i class="fas fa-user"></i> 
                  <?= htmlspecialchars($otherUser['first_name'] . ' ' . $otherUser['last_name']) ?>
                  <span class="friend-badge"><i class="fas fa-user-friends"></i> Friend</span>
                </h5>
                <?php if ($otherUser['section']): ?>
                  <small class="text-white-50">
                    <i class="fas fa-graduation-cap me-1"></i>
                    Section <?= $otherUser['section'] ?>, Year <?= $otherUser['year'] ?>
                  </small>
                <?php endif; ?>
              </div>
            </div>
            <!-- ========== PHASE 2 MODIFICATION END ========== -->
          </div>
          <div id="chatWindow" class="border rounded p-3 mb-3 chat-bg-light" style="height:400px; overflow-y:auto;"></div>
          <div id="typingIndicator"></div>
          <div class="mb-3">
            <input type="file" id="fileInput" class="form-control" />
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="anonToggle" />
            <label class="form-check-label" for="anonToggle"><i class="fas fa-user-secret"></i> Send anonymously</label>
          </div>
          <form id="messageForm" class="input-group mb-4" autocomplete="off">
            <input type="text" id="messageText" name="messageText" class="form-control" placeholder="Type a message…" maxlength="<?= MAX_MESSAGE_LENGTH ?>" required autofocus />
            <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Send</button>
          </form>
        </div>
      <?php else: ?>
        <div class="d-flex justify-content-center align-items-center text-center" style="height: 100%;">
          <div>
            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Select a conversation to start chatting</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newChatModal"><i class="fas fa-plus"></i> Start New Chat</button>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>


<!-- Modal for new chat -->
<div class="modal fade" id="newChatModal" tabindex="-1" aria-labelledby="newChatModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newChatModalLabel"><i class="fas fa-plus-circle"></i> Start New Chat with Friend</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- ========== PHASE 2 MODIFICATION START - Friends-only message ========== -->
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-2"></i>
          <strong>Friends-Only Messaging:</strong> You can only start conversations with your friends.
        </div>
        <p>Select a friend to start chatting:</p>
        <!-- ========== PHASE 2 MODIFICATION END ========== -->
        <?php if (empty($availableUsers)): ?>
          <div class="text-center py-3">
            <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
            <p class="text-muted">No friends available to chat with.</p>
            <p class="text-muted small">You've either messaged all your friends or need to add more friends.</p>
            <a href="friends.php" class="btn btn-success">
              <i class="fas fa-user-plus"></i> Find Friends
            </a>
          </div>
        <?php else: ?>
          <div class="list-group">
            <?php foreach ($availableUsers as $user): ?>
              <!-- ========== PHASE 2 MODIFICATION START - Enhanced User Card ========== -->
              <a href="chat.php?start_chat=1&user_id=<?= $user['user_id'] ?>" class="list-group-item list-group-item-action">
                <div class="d-flex align-items-center">
                  <img src="../<?php echo $user['profile_picture'] ?: 'uploads/profiles/default-avatar.svg'; ?>" 
                       alt="Avatar" class="user-avatar-sm me-3">
                  <div>
                    <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    <span class="friend-badge"><i class="fas fa-user-friends"></i> Friend</span>
                    <?php if ($user['section']): ?>
                      <br><small class="text-muted">Section <?= $user['section'] ?>, Year <?= $user['year'] ?></small>
                    <?php endif; ?>
                  </div>
                </div>
              </a>
              <!-- ========== PHASE 2 MODIFICATION END ========== -->
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>


<!-- Modal for reporting messages -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reportModalLabel"><i class="fas fa-flag text-danger"></i> Report Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="reportForm">
          <input type="hidden" id="reportMessageId" />
          <div class="mb-3">
            <label for="facultySelect" class="form-label">Select Faculty to Report to:</label>
            <select class="form-select" id="facultySelect" required>
              <option value="">Choose a faculty member...</option>
              <?php foreach ($facultyList as $faculty): ?>
                <option value="<?= $faculty['user_id'] ?>"><?= htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="reportReason" class="form-label">Reason for Report:</label>
            <select class="form-select" id="reportReason" required>
              <option value="">Select a reason...</option>
              <option value="Inappropriate Content">Inappropriate Content</option>
              <option value="Harassment">Harassment</option>
              <option value="Spam">Spam</option>
              <option value="Offensive Language">Offensive Language</option>
              <option value="Bullying">Bullying</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="reportDetails" class="form-label">Additional Details (Optional):</label>
            <textarea class="form-control" id="reportDetails" rows="3" placeholder="Provide additional context..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="submitReport">Submit Report</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../js/typing-indicator.js"></script>
<script>
const CONV_ID = <?php echo $conversationId ?: 'null'; ?>;
const CURRENT_USER_ID = <?php echo $currentUserId; ?>;
const CHAT_INTERVAL = <?php echo CHAT_REFRESH_INTERVAL; ?>;


if (CONV_ID) {
  window.currentChatType = 'direct';
  window.currentChatId = CONV_ID;
}


function formatTimeAgo(ts) {
  const d = new Date(ts), diff = Date.now() - d;
  const m = Math.floor(diff / 60000), h = Math.floor(m / 60), dd = Math.floor(h / 24);
  if (m < 1) return 'just now';
  if (m < 60) return m + 'm ago';
  if (h < 24) return h + 'h ago';
  if (dd < 7) return dd + 'd ago';
  return d.toLocaleDateString();
}


function loadDirectMessages() {
  if (!CONV_ID) return;
  $.getJSON('../php/chat/get_direct_messages.php', { conv_id: CONV_ID }, data => {
    const win = $('#chatWindow').empty();
    const isNearBottom = (win[0].scrollHeight - win.scrollTop() - win.outerHeight()) < 80;
    data.forEach(msg => {
      const mine = msg.sender_id == CURRENT_USER_ID;
      const status = mine ? (msg.is_read ? '✓✓' : '✓') : '';
      let content = msg.message_text;
      if (msg.message_type === 'image') {
        content = `<img src="../${msg.file_path}" class="img-fluid mb-1">`;
      } else if (msg.message_type === 'file') {
        content = `<a href="../${msg.file_path}" download>${msg.message_text}</a>`;
      }
      const reportBtn = !mine ? `<button class="btn btn-sm btn-outline-danger ms-2 report-btn" data-msg-id="${msg.message_id}" title="Report Message"><i class="fas fa-flag"></i></button>` : '';
      win.append(`
        <div class="chat-message-wrap clearfix">
          <div class="bubble ${mine ? 'bubble-my' : 'bubble-other'} mb-0">
            ${!mine ? '<strong>' + (msg.is_anonymous ? 'Anonymous' : msg.first_name + ' ' + msg.last_name) + ':</strong><br>' : ''}
            ${content}
            <span class="bubble-meta">${formatTimeAgo(msg.sent_at)} ${status}</span>
          </div>
          ${reportBtn}
        </div>`);
    });
    if (isNearBottom) win.scrollTop(win[0].scrollHeight);
    win.find('[data-msg-id]').each(function () {
      const id = $(this).data('msg-id'), s = $(this).data('sender-id');
      if (s !== CURRENT_USER_ID) $.post('../php/chat/mark_as_read.php', { message_id: id });
    });
  });
}


$('#messageForm').submit(function (e) {
  e.preventDefault();
  const text = $('#messageText').val().trim();
  const file = $('#fileInput')[0].files[0];
  if (!text && !file) return;
  const isAnon = $('#anonToggle').is(':checked') ? 1 : 0;
  const fd = new FormData();
  fd.append('conv_id', CONV_ID);
  fd.append('message_text', text);
  fd.append('is_anonymous', isAnon);
  if (file) fd.append('file', file);


  $('#messageText,#fileInput,button').prop('disabled', true);
  $.ajax({
    url: '../php/chat/send_direct_message.php',
    method: 'POST',
    data: fd, contentType: false, processData: false, dataType: 'json'
  }).done(res => {
    if (res.success) {
      $('#messageText,#fileInput').val('');
      $('#anonToggle').prop('checked', false);
      loadDirectMessages();
    } else alert(res.error || 'Send failed');
  }).always(() => {
    $('#messageText,#fileInput,button').prop('disabled', false);
    $('#messageText').focus();
  });
});


$(document).on('click', '.report-btn', function () {
  const msgId = $(this).data('msg-id');
  $('#reportMessageId').val(msgId);
  $('#reportModal').modal('show');
});


$('#submitReport').click(function () {
  const msgId = $('#reportMessageId').val();
  const facultyId = $('#facultySelect').val();
  const reason = $('#reportReason').val();
  const details = $('#reportDetails').val();


  if (!facultyId || !reason) {
    alert('Please select a faculty member and reason for report');
    return;
  }


  $.post('../php/chat/report_message.php', {
    message_id: msgId,
    faculty_id: facultyId,
    reason: reason,
    details: details
  }, function (res) {
    if (res.success) {
      alert('Report submitted successfully');
      $('#reportModal').modal('hide');
      $('#reportForm')[0].reset();
    } else {
      alert('Failed to submit report: ' + (res.error || 'Unknown error'));
    }
  }, 'json');
});


if (CONV_ID) {
  loadDirectMessages();
  setInterval(loadDirectMessages, CHAT_INTERVAL);
  $('#messageText').focus();
  $('#messageText').keypress(function (e) {
    if (e.which === 13 && !e.shiftKey) {
      e.preventDefault();
      $('#messageForm').submit();
    }
  });
}
</script>
</body>
</html>