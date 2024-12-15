<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

error_log("=== START OF CHAT PAGE EXECUTION ===");
error_log("Full SESSION data: " . print_r($_SESSION, true));
error_log("GET parameters: " . print_r($_GET, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session. Redirecting to login.");
    header("Location: login.php");
    exit();
}

// Get chat participant info
$user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

error_log("Current user_id: $user_id, Other user_id: $other_user_id");

if (!$other_user_id) {
    error_log("No other_user_id provided. Redirecting to messages.");
    header("Location: messages.php");
    exit();
}

// Get other user's info
try {
    $user_sql = "SELECT first_name, last_name, role FROM users WHERE id = :user_id";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bindParam(':user_id', $other_user_id, PDO::PARAM_INT);
    $user_stmt->execute();
    $other_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$other_user) {
        error_log("No user found with ID: " . $other_user_id);
        header("Location: messages.php");
        exit();
    }
} catch (PDOException $e) {
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
            position: relative;
        }
        .chat-header {
            padding: 10px;
            border-bottom: 4px solid #D4AF37;
            background-color: #3C2419;
            display: flex;
            align-items: center;
            height: 60px;
            z-index: 2;
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
                #382015 2px,
                #382015 4px
            );
            min-height: 200px;
        }
        .message-bubble {
            max-width: 80%;
            margin: 10px 0;
            padding: 10px;
            border: 2px solid #D4AF37;
            background-color: #3C2419;
            color: #D4AF37;
            font-size: 12px;
            word-wrap: break-word;
            position: relative;
        }
        .message-sent {
            align-self: flex-end;
            margin-left: auto;
            background-color: #4A2D1F;
        }
        .message-received {
            align-self: flex-start;
            margin-right: auto;
        }
        .message-text {
            margin-bottom: 5px;
        }
        .message-time {
            font-size: 8px;
            color: #8B7355;
            text-align: right;
        }
        .chat-input {
            padding: 15px;
            border-top: 4px solid #D4AF37;
            background-color: #3C2419;
            z-index: 2;
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
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            padding: 10px;
            resize: none;
            height: 40px;
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
            border: none;
            color: #2C1810;
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            padding: 10px 20px;
            cursor: pointer;
        }
        .chat-input button:hover {
            background-color: #FFD700;
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
        let isFirstLoad = true;
        let isLoading = false;
        let lastMessageId = -1;

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

        // Function to safely encode HTML
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Function to add a message to the chat
        function addMessage(message) {
            console.log('Adding message:', message);
            const messageDiv = document.createElement('div');
            const isSent = message.sender_id == <?php echo $user_id; ?>;
            messageDiv.className = `message-bubble ${isSent ? 'message-sent' : 'message-received'}`;
            
            const messageText = message.message || message.message_text;
            
            messageDiv.innerHTML = `
                <div class="message-text">${escapeHtml(messageText)}</div>
                <div class="message-time">${formatDate(message.created_at)}</div>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Function to clear messages
        function clearMessages() {
            while (chatMessages.firstChild) {
                chatMessages.removeChild(chatMessages.firstChild);
            }
        }

        // Function to load messages
        async function loadMessages() {
            if (isLoading) return;
            isLoading = true;
            
            try {
                const params = new URLSearchParams({
                    other_user_id: otherUserId,
                    last_message_id: lastMessageId
                });
                
                if (isFirstLoad) {
                    params.append('first_load', '1');
                }
                
                const response = await fetch(`ajax/get_chat_messages.php?${params.toString()}`);
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                console.log('Received messages:', data);
                
                if (data.success && data.messages) {
                    if (isFirstLoad) {
                        clearMessages();
                        isFirstLoad = false;
                    }
                    
                    data.messages.forEach(message => {
                        if (!document.querySelector(`[data-message-id="${message.id}"]`)) {
                            if (message.id > lastMessageId) {
                                lastMessageId = message.id;
                            }
                            addMessage(message);
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            } finally {
                isLoading = false;
            }
        }

        // Handle message form submission
        messageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const messageInput = document.getElementById('message');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            try {
                const formData = new URLSearchParams();
                formData.append('receiver_id', otherUserId);
                formData.append('message', message);
                
                const response = await fetch('ajax/send_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formData.toString()
                });
                
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                console.log('Send message response:', data);
                
                if (data.success) {
                    messageInput.value = '';
                    // Add the message immediately
                    addMessage({
                        id: data.message_id,
                        sender_id: <?php echo $user_id; ?>,
                        receiver_id: otherUserId,
                        message_text: message,
                        created_at: new Date().toISOString()
                    });
                    lastMessageId = data.message_id;
                } else {
                    console.error('Failed to send message:', data.message);
                }
            } catch (error) {
                console.error('Error sending message:', error);
            }
        });

        // Load messages initially and then every few seconds
        loadMessages();
        setInterval(loadMessages, 3000);
    </script>
</body>
</html>
