<?php
require_once __DIR__ . '/../config/config.php';
if (!validateSession()) redirectTo('../login.php');
$roomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = getCurrentUserId();


// ========== PHASE 2 MODIFICATION START - Get enhanced room and user info ==========
// Fetch room info with creator details
$stmt = $pdo->prepare('
    SELECT cr.room_name, cr.room_topic, cr.description, cr.created_at,
           u.first_name as creator_first, u.last_name as creator_last, u.role as creator_role,
           (SELECT COUNT(*) FROM room_participants WHERE room_id = cr.room_id) as total_participants,
           (SELECT COUNT(DISTINCT u2.section) FROM room_participants rp2 
            JOIN users u2 ON rp2.user_id = u2.user_id 
            WHERE rp2.room_id = cr.room_id AND u2.section IS NOT NULL) as section_diversity
    FROM chat_rooms cr
    LEFT JOIN users u ON cr.created_by = u.user_id
    WHERE cr.room_id = ? AND cr.is_active = 1
');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    $_SESSION['error_message'] = 'Chat room not found or inactive';
    redirectTo('rooms.php');
}

// Get current user info
$stmt = $pdo->prepare('SELECT first_name, last_name, section, year, role FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
// ========== PHASE 2 MODIFICATION END ==========


// Auto-join if not participant
$stmt = $pdo->prepare('SELECT 1 FROM room_participants WHERE room_id = ? AND user_id = ?');
$stmt->execute([$roomId, $userId]);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare('INSERT INTO room_participants (room_id, user_id) VALUES (?, ?)');
    $stmt->execute([$roomId, $userId]);
}


// Get faculty list for reporting
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM users WHERE role IN ('faculty','lecturer','teacher') AND status='active' ORDER BY first_name");
$stmt->execute();
$facultyList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?php echo htmlspecialchars($room['room_name']); ?> – <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/typing-indicator.css">
    <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .room-chat-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.09);
      margin-bottom: 32px;
    }
    .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      font-weight: 600;
      border-radius: 12px 12px 0 0;
      border: none;
    }
    .room-title-heading {
      color: white !important;
      font-weight: 700;
    }
    #chatWindow {
      background: #f4f8fb;
      min-height: 400px;
      max-height: 400px;
      overflow-y: auto;
      padding: 15px;
      border-radius: 10px;
      border: 1px solid #ddd;
    }
    .input-group .form-control, .form-check-input, .form-select {
      border-radius: 10px;
    }
    .form-check-label {
      color: #222 !important;
      font-weight: 500;
      font-size: 1rem;
    }
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
.public-room-banner {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
    border: 2px solid #667eea;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}
.participant-badge {
    background: white;
    border: 1px solid #667eea;
    color: #667eea;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
}
.diversity-badge {
    background: #28a745;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
}
/* ========== PHASE 2 ADDITION END ========== */


    </style>
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="container mt-4">
    <div class="card mb-3 room-chat-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="room-title-heading mb-2"><i class="fas fa-door-open me-2"></i><?php echo htmlspecialchars($room['room_name']); ?></h4>
                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($room['room_topic']); ?></span>
                <!-- ========== PHASE 2 ADDITION START ========== -->
                <?php if ($room['section_diversity'] > 1): ?>
                    <span class="badge bg-success ms-2">
                        <i class="fas fa-users-cog"></i> Multi-Section
                    </span>
                <?php endif; ?>
                <!-- ========== PHASE 2 ADDITION END ========== -->
                <div class="mt-2">
                    <small class="text-white-50"><?php echo htmlspecialchars($room['description']); ?></small>
                </div>
            </div>
            <a href="rooms.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back to Rooms</a>
        </div>
    </div>

    <!-- ========== PHASE 2 ADDITION START - Public Room Information Banner ========== -->
    <div class="public-room-banner">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-globe fa-3x text-primary"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">
                            <i class="fas fa-door-open text-primary"></i> 
                            Campus-Wide Public Room
                        </h5>
                        <p class="mb-0 small text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            This is a <strong>public discussion room</strong> accessible to all students and faculty across all sections.
                            <?php if ($room['creator_first']): ?>
                                Created by 
                                <strong><?php echo htmlspecialchars($room['creator_first'] . ' ' . $room['creator_last']); ?></strong>
                                (<?php echo ucfirst($room['creator_role']); ?>).
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-5 mt-3 mt-md-0">
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    <div class="participant-badge">
                        <i class="fas fa-users me-1"></i>
                        <?php echo $room['total_participants']; ?> Participant<?php echo $room['total_participants'] != 1 ? 's' : ''; ?>
                    </div>
                    <?php if ($room['section_diversity'] > 1): ?>
                        <div class="diversity-badge">
                            <i class="fas fa-graduation-cap me-1"></i>
                            <?php echo $room['section_diversity']; ?> Sections
                        </div>
                    <?php endif; ?>
                    <?php if ($currentUser['section']): ?>
                        <div class="badge bg-secondary px-3 py-2">
                            <i class="fas fa-user me-1"></i>
                            You: Sec <?php echo $currentUser['section']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- ========== PHASE 2 ADDITION END ========== -->

    <div class="room-chat-card card">
        <div class="card-body">
            <div id="chatWindow"></div>
            <div id="typingIndicator"></div>
            <div class="mb-2">
                <input type="file" id="fileInput" class="form-control">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="anonToggle">
                <label class="form-check-label" for="anonToggle">
                    <i class="fas fa-user-secret"></i> Send anonymously
                </label>
            </div>
            <form id="messageForm" class="input-group mb-4">
                <input type="text" id="messageText" class="form-control" placeholder="Type a message to the room…" maxlength="<?php echo MAX_MESSAGE_LENGTH; ?>" required>
                <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Send</button>
            </form>
        </div>
    </div>
</div>


<!-- Report Message Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-flag text-danger"></i> Report Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm">
                    <input type="hidden" id="reportMessageId">
                    <div class="mb-3">
                        <label for="facultySelect" class="form-label">Select Faculty to Report to:</label>
                        <select class="form-select" id="facultySelect" required>
                            <option value="">Choose a faculty member...</option>
                            <?php foreach($facultyList as $faculty): ?>
                                <option value="<?php echo $faculty['user_id']; ?>">
                                    <?php echo htmlspecialchars($faculty['first_name'].' '.$faculty['last_name']); ?>
                                </option>
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
const ROOM_ID = <?php echo $roomId; ?>;
const CURRENT_USER_ID = <?php echo $userId; ?>;
const CHAT_REFRESH_INTERVAL = <?php echo CHAT_REFRESH_INTERVAL; ?>;


window.currentChatType = 'room';
window.currentChatId = ROOM_ID;


function formatTimeAgo(ts) {
    const d = new Date(ts), diff = Date.now() - d;
    const m = Math.floor(diff/60000), h = Math.floor(m/60), dd = Math.floor(h/24);
    if(m < 1) return 'just now';
    if(m < 60) return m + 'm ago';
    if(h < 24) return h + 'h ago';
    if(dd < 7) return dd + 'd ago';
    return d.toLocaleDateString();
}


function loadMessages() {
    const win = $('#chatWindow');
    const scrollTopBefore = win.scrollTop();
    const scrollHeightBefore = win.prop('scrollHeight');
    const clientHeight = win.innerHeight();
    const scrollAtBottom = (scrollTopBefore + clientHeight + 10) >= scrollHeightBefore;


    $.getJSON('../php/chat/get_room_messages.php', { room_id: ROOM_ID }, data => {
        win.empty();
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
                    ${!mine ? '<strong>' + (msg.is_anonymous ? 'Anonymous' : msg.first_name+' '+msg.last_name) + ':</strong><br>' : ''}
                    ${content}
                    <span class="bubble-meta">${formatTimeAgo(msg.sent_at)} ${status}</span>
                  </div>
                  ${reportBtn}
                </div>`);
        });


        // Only scroll to bottom if user was already at/near bottom, else preserve scroll
        if(scrollAtBottom){
          win.scrollTop(win.prop('scrollHeight'));
        } else {
          win.scrollTop(scrollTopBefore);
        }


        win.find('[data-msg-id]').each(function(){
            const id = $(this).data('msg-id'), s = $(this).data('sender-id');
            if (s !== CURRENT_USER_ID) {
                $.post('../php/chat/mark_as_read.php', { message_id: id });
            }
        });
    });
}


$('#messageForm').submit(function(e){
    e.preventDefault();
    const text = $('#messageText').val().trim();
    const file = $('#fileInput')[0].files[0];
    if (!text && !file) return;
    const isAnon = $('#anonToggle').is(':checked') ? 1 : 0;
    const fd = new FormData();
    fd.append('room_id', ROOM_ID);
    fd.append('message_text', text);
    fd.append('is_anonymous', isAnon);
    if (file) fd.append('file', file);


    $('#messageText, #fileInput, button').prop('disabled', true);
    $.ajax({
        url: '../php/chat/send_room_message.php',
        method: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json'
    }).done(res => {
        if(res.success){
            $('#messageText, #fileInput').val('');
            $('#anonToggle').prop('checked',false);
            loadMessages();
        } else alert(res.error||'Send failed');
    }).always(()=>{
        $('#messageText, #fileInput, button').prop('disabled',false);
        $('#messageText').focus();
    });
});


$(document).on('click', '.report-btn', function() {
    const msgId = $(this).data('msg-id');
    $('#reportMessageId').val(msgId);
    $('#reportModal').modal('show');
});


$('#submitReport').click(function() {
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
    }, function(res) {
        if (res.success) {
            alert('Report submitted successfully');
            $('#reportModal').modal('hide');
            $('#reportForm')[0].reset();
        } else {
            alert('Failed to submit report: ' + (res.error || 'Unknown error'));
        }
    }, 'json');
});


loadMessages();
setInterval(loadMessages,CHAT_REFRESH_INTERVAL);


$('#messageText').keypress(e=>{
    if(e.which===13&&!e.shiftKey){
        e.preventDefault();
        $('#messageForm').submit();
    }
});
</script>
</body>
</html>