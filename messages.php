<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

$admin_id = $_SESSION['user_id'];

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_message') {
        $message = trim($_POST['message']);
        $recipient_id = (int)$_POST['recipient_id'];
        
        if (!empty($message)) {
            $query = "INSERT INTO messages (sender_id, recipient_id, message, message_type) 
                     VALUES (?, ?, ?, 'text')";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iis", $admin_id, $recipient_id, $message);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Message sent successfully!";
            } else {
                $error_message = "Error sending message: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Message cannot be empty.";
        }
    }
}

// Get all conversations for the admin
$conversations_query = "
SELECT DISTINCT 
    CASE 
        WHEN m.sender_id = ? THEN m.recipient_id 
        ELSE m.sender_id 
    END as other_user_id,
    u.username as other_username,
    u.role as other_role,
    u.email as other_email,
    (SELECT message FROM messages 
     WHERE (sender_id = ? AND recipient_id = other_user_id) 
        OR (sender_id = other_user_id AND recipient_id = ?)
     ORDER BY created_at DESC LIMIT 1) as last_message,
    (SELECT created_at FROM messages 
     WHERE (sender_id = ? AND recipient_id = other_user_id) 
        OR (sender_id = other_user_id AND recipient_id = ?)
     ORDER BY created_at DESC LIMIT 1) as last_message_time,
    (SELECT COUNT(*) FROM messages 
     WHERE sender_id = other_user_id AND recipient_id = ? AND is_read = 0) as unread_count
FROM messages m
JOIN users u ON (m.sender_id = u.id OR m.recipient_id = u.id)
WHERE (m.sender_id = ? OR m.recipient_id = ?) AND u.id != ?
ORDER BY last_message_time DESC";

$stmt = mysqli_prepare($conn, $conversations_query);
mysqli_stmt_bind_param($stmt, "iiiiiiiii", $admin_id, $admin_id, $admin_id, $admin_id, $admin_id, $admin_id, $admin_id, $admin_id, $admin_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$conversations = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get selected conversation
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$messages = [];

if ($selected_user_id > 0) {
    $messages_query = "
    SELECT m.*, u.username as sender_username, u.role as sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = ? AND m.recipient_id = ?) 
       OR (m.sender_id = ? AND m.recipient_id = ?)
    ORDER BY m.created_at ASC";
    
    $stmt = mysqli_prepare($conn, $messages_query);
    mysqli_stmt_bind_param($stmt, "iiii", $admin_id, $selected_user_id, $selected_user_id, $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Mark messages as read
    $update_query = "UPDATE messages SET is_read = 1 
                     WHERE sender_id = ? AND recipient_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ii", $selected_user_id, $admin_id);
    mysqli_stmt_execute($stmt);
}

// Get all users for new conversation
$users_query = "SELECT id, username, role, email FROM users WHERE role != 'admin' ORDER BY username";
$users_result = mysqli_query($conn, $users_query);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .chat-container {
            height: 600px;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .conversation-list {
            height: 100%;
            overflow-y: auto;
            border-right: 1px solid #dee2e6;
        }
        
        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        
        .conversation-item.active {
            background-color: #e3f2fd;
            border-left: 4px solid #007bff;
        }
        
        .chat-messages {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .messages-header {
            padding: 1rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .messages-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .message {
            margin-bottom: 1rem;
            max-width: 70%;
        }
        
        .message.sent {
            margin-left: auto;
        }
        
        .message.received {
            margin-right: auto;
        }
        
        .message-bubble {
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            position: relative;
        }
        
        .message.sent .message-bubble {
            background-color: #28a745;
            color: white;
        }
        
        .message.received .message-bubble {
            background-color: #f1f3f4;
            color: #333;
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }
        
        .message-input {
            padding: 1rem;
            border-top: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }
        
        .unread-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .admin-avatar {
            background-color: #28a745;
        }
        
        .user-info {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-comments"></i> Admin Messages
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newConversationModal">
                            <i class="fas fa-plus"></i> New Conversation
                        </button>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i> Conversations
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="conversation-list">
                                    <?php if (empty($conversations)): ?>
                                    <div class="text-center text-muted p-4">
                                        <i class="fas fa-comments fa-2x mb-3"></i>
                                        <p>No conversations yet.</p>
                                        <p>Users will appear here when they start conversations.</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($conversations as $conv): ?>
                                    <div class="conversation-item <?php echo ($selected_user_id == $conv['other_user_id']) ? 'active' : ''; ?>"
                                         onclick="loadConversation(<?php echo $conv['other_user_id']; ?>)">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3">
                                                <?php echo strtoupper(substr($conv['other_username'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($conv['other_username']); ?></h6>
                                                    <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="user-info">
                                                    <small><?php echo htmlspecialchars($conv['other_email']); ?></small>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?>
                                                    <?php if (strlen($conv['last_message']) > 50): ?>...<?php endif; ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('M j, g:i A', strtotime($conv['last_message_time'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="chat-container">
                                    <div class="chat-messages">
                                        <?php if ($selected_user_id > 0): ?>
                                        <div class="messages-header">
                                            <h6 class="mb-0">
                                                <i class="fas fa-user"></i> 
                                                <?php 
                                                $selected_user = null;
                                                foreach ($conversations as $conv) {
                                                    if ($conv['other_user_id'] == $selected_user_id) {
                                                        $selected_user = $conv;
                                                        break;
                                                    }
                                                }
                                                echo htmlspecialchars($selected_user['other_username'] ?? 'Unknown User');
                                                ?>
                                                <small class="text-muted ms-2">
                                                    <?php echo htmlspecialchars($selected_user['other_email'] ?? ''); ?>
                                                </small>
                                            </h6>
                                        </div>
                                        
                                        <div class="messages-body" id="messagesBody">
                                            <?php foreach ($messages as $message): ?>
                                            <div class="message <?php echo ($message['sender_id'] == $admin_id) ? 'sent' : 'received'; ?>">
                                                <div class="message-bubble">
                                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                </div>
                                                <div class="message-time">
                                                    <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                                                    <?php if ($message['sender_id'] == $admin_id): ?>
                                                    <i class="fas fa-check <?php echo $message['is_read'] ? 'text-success' : 'text-muted'; ?>"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <form class="message-input" method="POST" id="messageForm">
                                            <input type="hidden" name="action" value="send_message">
                                            <input type="hidden" name="recipient_id" value="<?php echo $selected_user_id; ?>">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="message" placeholder="Type your message..." required>
                                                <button class="btn btn-success" type="submit">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </div>
                                        </form>
                                        <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center h-100">
                                            <div class="text-center text-muted">
                                                <i class="fas fa-comments fa-3x mb-3"></i>
                                                <h5>Select a conversation</h5>
                                                <p>Choose a conversation from the list to respond to user messages.</p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- New Conversation Modal -->
    <div class="modal fade" id="newConversationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Start New Conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Select a user to start a conversation:</p>
                    <div class="list-group">
                        <?php foreach ($users as $user): ?>
                        <a href="?user_id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-3">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadConversation(userId) {
            window.location.href = 'messages.php?user_id=' + userId;
        }
        
        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const messagesBody = document.getElementById('messagesBody');
            if (messagesBody) {
                messagesBody.scrollTop = messagesBody.scrollHeight;
            }
        }
        
        // Scroll to bottom when page loads
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
        });
        
        // Auto-refresh messages every 10 seconds
        setInterval(function() {
            if (<?php echo $selected_user_id; ?> > 0) {
                location.reload();
            }
        }, 10000);
        
        // Handle message form submission
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            const messageInput = this.querySelector('input[name="message"]');
            if (messageInput.value.trim() === '') {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html> 