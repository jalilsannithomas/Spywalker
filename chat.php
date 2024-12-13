<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get chat participant info
$user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$other_user_id) {
    header("Location: messages.php");
    exit();
}

// Get other user's info
try {
    $user_sql = "SELECT first_name, last_name, role FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $other_user_id);
    $user_stmt->execute();
    $other_user = $user_stmt->get_result()->fetch_assoc();

    if (!$other_user) {
        header("Location: messages.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Error fetching user info: " . $e->getMessage());
    header("Location: messages.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .chat-container {
            background-color: #2C1810;
            border: 4px solid #D4AF37;
            border-radius: 0;
            height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
            font-family: 'Press Start 2P', monospace;
            image-rendering: pixelated;
        }
        .chat-header {
            padding: 10px;
            border-bottom: 4px solid #D4AF37;
            background-color: #3C2419;
            display: flex;
            align-items: center;
            height: 60px;
        }
        .chat-header h2 {
            font-size: 16px;
            margin: 0;
            font-family: 'Press Start 2P', monospace;
            color: #D4AF37;
        }
        .chat-header .status {
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 0;
            margin-right: 10px;
            box-shadow: 0 0 0 2px #2C1810;
        }
        .chat-messages {
            flex-grow: 1;
            overflow-y: auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
            background: repeating-linear-gradient(
                0deg,
                #2C1810,
                #2C1810 2px,
                #3C2419 2px,
                #3C2419 4px
            );
        }
        .message-bubble {
            max-width: 80%;
            margin-bottom: 15px;
            padding: 10px;
            border: 2px solid #D4AF37;
            position: relative;
            font-size: 12px;
            line-height: 1.5;
            font-family: 'Press Start 2P', monospace;
        }
        .message-sent {
            align-self: flex-end;
            background-color: #D4AF37;
            color: #2C1810;
            margin-left: 20%;
            border-radius: 0;
        }
        .message-received {
            align-self: flex-start;
            background-color: #3C2419;
            color: #D4AF37;
            margin-right: 20%;
            border-radius: 0;
        }
        .message-time {
            font-size: 8px;
            opacity: 0.7;
            margin-top: 5px;
            font-family: 'Press Start 2P', monospace;
        }
        .chat-input {
            padding: 10px;
            border-top: 4px solid #D4AF37;
            background-color: #3C2419;
        }
        .chat-input form {
            display: flex;
            gap: 10px;
        }
        .chat-input textarea {
            flex-grow: 1;
            background-color: #2C1810;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            resize: none;
            height: 40px;
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            padding: 8px;
            border-radius: 0;
        }
        .chat-input textarea:focus {
            background-color: #2C1810;
            border-color: #D4AF37;
            color: #D4AF37;
            box-shadow: none;
            outline: none;
        }
        .chat-input button {
            background-color: #D4AF37;
            border: 2px solid #D4AF37;
            color: #2C1810;
            border-radius: 0;
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            padding: 8px 16px;
        }
        .chat-input button:hover {
            background-color: #B38F28;
            border-color: #B38F28;
        }
        .back-button {
            background: none;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            padding: 5px 10px;
            font-family: 'Press Start 2P', monospace;
            font-size: 10px;
            margin-left: auto;
            text-decoration: none;
            display: inline-block;
        }
        .back-button:hover {
            background-color: #D4AF37;
            color: #2C1810;
            text-decoration: none;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 12px;
        }
        ::-webkit-scrollbar-track {
            background: #2C1810;
            border-left: 2px solid #D4AF37;
        }
        ::-webkit-scrollbar-thumb {
            background: #D4AF37;
            border: 2px solid #2C1810;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="chat-container">
            <div class="chat-header">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="d-flex align-items-center">
                        <div class="status"></div>
                        <div>
                            <h2 class="vintage-title mb-0">
                                <?php echo htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']); ?>
                            </h2>
                            <small style="font-size: 8px; color: #D4AF37;"><?php echo htmlspecialchars($other_user['role']); ?></small>
                        </div>
                    </div>
                    <a href="messages.php" class="back-button">
                        < BACK
                    </a>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <!-- Messages will be loaded here -->
            </div>
            
            <div class="chat-input">
                <form id="messageForm">
                    <textarea class="form-control" id="message" name="message" placeholder="Type your message..." required></textarea>
                    <button type="submit" class="btn">SEND</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const chatMessages = document.getElementById('chatMessages');
        const messageForm = document.getElementById('messageForm');
        const otherUserId = <?php echo $other_user_id; ?>;
        let lastMessageId = 0;

        // Function to format date
        function formatDate(date) {
            return new Date(date).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        // Function to add a message to the chat
        function addMessage(message, isNew = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message-bubble ${message.sender_id == <?php echo $user_id; ?> ? 'message-sent' : 'message-received'}`;
            messageDiv.innerHTML = `
                ${message.message_text}
                <div class="message-time">${formatDate(message.created_at)}</div>
            `;
            
            if (isNew) {
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            } else {
                chatMessages.insertBefore(messageDiv, chatMessages.firstChild);
            }
            
            if (message.id > lastMessageId) {
                lastMessageId = message.id;
            }
        }

        // Function to load messages
        function loadMessages() {
            fetch(`ajax/get_chat_messages.php?other_user_id=${otherUserId}&last_message_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(message => addMessage(message, true));
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Load initial messages
        loadMessages();

        // Poll for new messages every 3 seconds
        setInterval(loadMessages, 3000);

        // Handle message submission
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageInput = document.getElementById('message');
            const message = messageInput.value.trim();
            
            if (!message) return;

            fetch('ajax/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    receiver_id: otherUserId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    loadMessages();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send message. Please try again.');
            });
        });
    </script>
</body>
</html>
