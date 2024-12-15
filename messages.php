<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

// Debug: Log all session data
error_log("=== START OF MESSAGE PAGE EXECUTION ===");
error_log("Full SESSION data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session. Redirecting to login.");
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
error_log("Current user_id from session: " . $user_id);

// Initialize variables
$error_message = '';
$success_message = '';
$users = [];
$messages = [];
$teams = [];

try {
    // Test database connection
    try {
        $conn->query("SELECT 1");
        error_log("Database connection test successful");
    } catch (PDOException $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        throw new Exception("Database connection error");
    }

    // Get all active users except current user
    try {
        $users_sql = "SELECT id, first_name, last_name, role 
                      FROM users 
                      WHERE is_active = TRUE AND id != :user_id";
        $users_stmt = $conn->prepare($users_sql);
        $users_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $users_stmt->execute();
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($users) . " other active users");
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        throw new Exception("Failed to fetch users");
    }

    // Verify messages table exists
    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'messages'");
        if ($table_check->rowCount() === 0) {
            error_log("Messages table does not exist!");
            throw new Exception("Messages table not found");
        }
        error_log("Messages table exists");

        // Check table structure
        $structure = $conn->query("DESCRIBE messages");
        $columns = $structure->fetchAll(PDO::FETCH_COLUMN);
        error_log("Messages table columns: " . print_r($columns, true));
    } catch (PDOException $e) {
        error_log("Error checking messages table: " . $e->getMessage());
        throw new Exception("Failed to verify messages table");
    }

    // Get messages with detailed error logging
    try {
        error_log("Preparing to fetch messages for user_id: " . $user_id);
        
        $messages_sql = "SELECT m.*, 
                        CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                        CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                        s.role as sender_role,
                        r.role as receiver_role
                        FROM messages m
                        JOIN users s ON m.sender_id = s.id
                        JOIN users r ON m.receiver_id = r.id
                        WHERE m.sender_id = :user_id1 
                        OR m.receiver_id = :user_id2
                        ORDER BY m.created_at DESC";

        error_log("Messages SQL query: " . $messages_sql);
        
        $messages_stmt = $conn->prepare($messages_sql);
        if (!$messages_stmt) {
            error_log("Failed to prepare messages statement");
            throw new Exception("Failed to prepare messages query");
        }

        $messages_stmt->bindParam(':user_id1', $user_id, PDO::PARAM_INT);
        $messages_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
        
        error_log("About to execute messages query");
        if (!$messages_stmt->execute()) {
            $error = $messages_stmt->errorInfo();
            error_log("Query execution failed: " . print_r($error, true));
            throw new Exception("Failed to execute messages query");
        }

        $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Messages query successful. Found " . count($messages) . " messages");
        error_log("Raw messages data: " . print_r($messages, true));

    } catch (PDOException $e) {
        error_log("Error fetching messages: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw new Exception("Failed to fetch messages");
    }

    // If user is a coach, get their teams
    if ($_SESSION['role'] === 'coach') {
        try {
            $teams_sql = "SELECT t.*, s.name as sport_name
                         FROM teams t
                         JOIN sports s ON t.sport_id = s.id
                         WHERE t.coach_id = :coach_id";
            $teams_stmt = $conn->prepare($teams_sql);
            $teams_stmt->bindParam(':coach_id', $user_id, PDO::PARAM_INT);
            $teams_stmt->execute();
            $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found " . count($teams) . " teams for coach");
        } catch (PDOException $e) {
            error_log("Error fetching teams: " . $e->getMessage());
            throw new Exception("Failed to fetch teams");
        }
    }

} catch (Exception $e) {
    error_log("Main try-catch block error: " . $e->getMessage());
    $error_message = "An error occurred: " . $e->getMessage();
}

error_log("=== END OF PHP PROCESSING ===");

// Handle sending new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'send_message') {
            $receiver_id = $_POST['receiver_id'];
            $message_text = trim($_POST['message']);
            
            if (empty($message_text)) {
                throw new Exception("Message cannot be empty");
            }
            
            $insert_sql = "INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                          VALUES (:sender_id, :receiver_id, :message, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':message', $message_text, PDO::PARAM_STR);
            
            if ($insert_stmt->execute()) {
                $success_message = "Message sent successfully!";
                // Refresh messages list
                $messages_stmt->execute();
                $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                throw new Exception("Failed to send message");
            }
        } elseif ($_POST['action'] === 'send_team_message' && $_SESSION['role'] === 'coach') {
            $team_id = $_POST['team_id'];
            $message_text = trim($_POST['team_message']);
            
            if (empty($message_text)) {
                throw new Exception("Message cannot be empty");
            }
            
            // Get all team members
            $members_sql = "SELECT user_id FROM team_members WHERE team_id = :team_id";
            $members_stmt = $conn->prepare($members_sql);
            $members_stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
            $members_stmt->execute();
            $team_members = $members_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Start transaction for sending multiple messages
            $conn->beginTransaction();
            
            try {
                $insert_sql = "INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                              VALUES (:sender_id, :receiver_id, :message, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                
                foreach ($team_members as $member_id) {
                    $insert_stmt->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
                    $insert_stmt->bindParam(':receiver_id', $member_id, PDO::PARAM_INT);
                    $insert_stmt->bindParam(':message', $message_text, PDO::PARAM_STR);
                    $insert_stmt->execute();
                }
                
                $conn->commit();
                $success_message = "Team message sent successfully!";
                
                // Refresh messages list
                $messages_stmt->execute();
                $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        }
    } catch (Exception $e) {
        error_log("Error sending message: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Messages - SpyWalker</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/icons-fix.css" rel="stylesheet">
    <link href="assets/css/team-stats.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #2C1810;
            font-family: 'Press Start 2P', cursive;
        }

        /* Navbar styling to match the brown and gold theme */
        .navbar {
            background-color: #2C1810 !important;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #D4AF37;
        }

        .navbar-brand {
            color: #D4AF37 !important;
            font-family: 'Press Start 2P', cursive;
            font-size: 16px;
            text-decoration: none;
        }

        .navbar-nav .nav-link {
            color: #D4AF37 !important;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            padding: 0.5rem 1rem;
            text-transform: uppercase;
        }

        .navbar-nav .nav-link:hover {
            color: #FFD700 !important;
        }

        .navbar-nav .nav-link.active {
            color: #FFD700 !important;
        }

        .search-form {
            margin-left: auto;
        }

        .search-form input {
            background-color: transparent;
            border: 1px solid #D4AF37;
            color: #D4AF37;
        }

        .search-form input::placeholder {
            color: #D4AF37;
            opacity: 0.7;
        }

        .dropdown-toggle {
            color: #D4AF37 !important;
        }

        /* Rest of the retro styling for content */
        .content-container {
            font-family: 'Press Start 2P', cursive;
        }
        
        .message-container {
            background-color: #3C2A1E;
            border: 2px solid #D4AF37;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .container {
            background-color: #3C2419;
            border: 4px solid #D4AF37;
            border-radius: 0;
            padding: 20px;
            margin-top: 20px;
            position: relative;
            image-rendering: pixelated;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 4px;
            left: 4px;
            right: 4px;
            bottom: 4px;
            border: 2px solid #2C1810;
            pointer-events: none;
        }
        
        .messages-title {
            color: #D4AF37;
            text-transform: uppercase;
            margin-bottom: 20px;
            font-size: 24px;
            text-shadow: 3px 3px #2C1810;
            letter-spacing: 2px;
        }
        
        .btn-primary {
            background-color: #D4AF37;
            border: 4px solid #2C1810;
            color: #2C1810;
            font-size: 12px;
            padding: 10px 15px;
            border-radius: 0;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #C4A027;
            border-color: #2C1810;
            color: #2C1810;
            transform: translateY(2px);
        }
        
        .message-card {
            transition: transform 0.2s;
            display: block;
            margin-bottom: 15px;
        }
        
        .card {
            background-color: #2C1810;
            border: 4px solid #D4AF37;
            border-radius: 0;
            position: relative;
        }
        
        .card::after {
            content: '';
            position: absolute;
            top: 4px;
            left: 4px;
            right: 4px;
            bottom: 4px;
            border: 2px solid #3C2419;
            pointer-events: none;
        }
        
        .card:hover {
            border-color: #C4A027;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .card-title {
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .text-gold {
            color: #D4AF37 !important;
        }
        
        .badge {
            font-size: 8px;
            padding: 5px 8px;
            border-radius: 0;
            margin-left: 8px;
            image-rendering: pixelated;
        }
        
        .badge.bg-secondary {
            background-color: #3C2419 !important;
            color: #D4AF37;
            border: 2px solid #D4AF37;
        }
        
        .badge.bg-danger {
            background-color: #D4AF37 !important;
            color: #2C1810;
            border: 2px solid #2C1810;
        }
        
        .card-text {
            font-size: 10px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        small {
            font-size: 8px;
        }
        
        /* Modal Styles */
        .modal-content {
            background-color: #3C2A1E;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            font-family: 'Press Start 2P', cursive;
        }

        .modal-header {
            border-bottom: 1px solid #D4AF37;
            padding: 1rem;
        }

        .modal-title {
            font-size: 16px;
            color: #D4AF37;
            font-family: 'Press Start 2P', cursive;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-footer {
            border-top: 1px solid #D4AF37;
            padding: 1rem;
        }

        .form-label {
            color: #D4AF37;
            font-size: 12px;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            background-color: #2C1810;
            border: 1px solid #D4AF37;
            color: #D4AF37;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
        }

        .form-control:focus, .form-select:focus {
            background-color: #2C1810;
            border-color: #FFD700;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
            color: #D4AF37;
        }

        .btn-primary {
            background-color: #D4AF37;
            border-color: #D4AF37;
            color: #2C1810;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            text-transform: uppercase;
        }

        .btn-primary:hover {
            background-color: #FFD700;
            border-color: #FFD700;
            color: #2C1810;
        }

        .btn-secondary {
            background-color: #2C1810;
            border-color: #D4AF37;
            color: #D4AF37;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            text-transform: uppercase;
        }

        .btn-secondary:hover {
            background-color: #3C2A1E;
            border-color: #FFD700;
            color: #FFD700;
        }

        .btn-close {
            filter: invert(1) sepia(0) saturate(100%) hue-rotate(0deg);
        }
        
        /* Scrollbar Styles */
        ::-webkit-scrollbar {
            width: 12px;
        }
        
        ::-webkit-scrollbar-track {
            background: #2C1810;
            border: 2px solid #D4AF37;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #D4AF37;
            border: 2px solid #2C1810;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #C4A027;
        }
        
        /* Empty State */
        .text-center.text-gold {
            padding: 30px;
            border: 4px solid #D4AF37;
            margin: 20px 0;
            font-size: 12px;
            line-height: 1.6;
            background: repeating-linear-gradient(
                45deg,
                #2C1810,
                #2C1810 10px,
                #3C2419 10px,
                #3C2419 20px
            );
        }
        
        .new-message-btn,
        .team-message-btn {
            background-color: #D4AF37;
            border-color: #D4AF37;
            color: #2C1810;
            transition: all 0.3s ease;
        }
        
        .new-message-btn:hover,
        .team-message-btn:hover {
            background-color: #C4A030;
            border-color: #C4A030;
            color: #2C1810;
        }
        
        .team-message-btn {
            margin-left: 10px;
        }
        
        .notification-dot {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 10px;
            height: 10px;
            background-color: #FFD700;
            border-radius: 50%;
        }
        
        .has-unread {
            position: relative;
        }
        
        .has-unread .notification-dot {
            display: block;
        }
        
        .has-unread .nav-link {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="content-container">
        <div class="container py-4">
            <div class="row mb-4">
                <div class="col d-flex justify-content-between align-items-center">
                    <h1 class="messages-title">Messages</h1>
                    <div class="button-group">
                        <button type="button" class="btn btn-primary new-message-btn" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                            <i class="bi bi-envelope-plus"></i> New Message
                        </button>
                        <?php 
                        error_log("Checking team message button visibility");
                        error_log("Current role: " . $_SESSION['role']);
                        
                        if ($_SESSION['role'] === 'coach'): // Use lowercase to match database
                            error_log("Showing team message button");
                        ?>
                            <button type="button" class="btn btn-primary team-message-btn" data-bs-toggle="modal" data-bs-target="#teamMessageModal">
                                <i class="bi bi-people-fill"></i> Team Message
                            </button>
                        <?php else: 
                            error_log("Not showing team message button - user role is: " . $_SESSION['role']);
                        endif; ?>
                    </div>
                </div>
            </div>

            <div class="messages-list">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($messages)): ?>
                    <?php error_log("No messages found to display"); ?>
                    <div class="text-center text-gold">
                        <p>No messages yet. Start a conversation!</p>
                    </div>
                <?php else: ?>
                    <?php error_log("Starting to display " . count($messages) . " messages"); ?>
                    <?php foreach ($messages as $message): ?>
                        <?php 
                        error_log("Processing message ID " . $message['id']);
                        error_log("Message details: " . print_r($message, true));
                        
                        // Determine the other user's ID and details
                        $is_sender = ($message['sender_id'] == $user_id);
                        $chat_with_id = $is_sender ? $message['receiver_id'] : $message['sender_id'];
                        $chat_with_name = $is_sender ? $message['receiver_name'] : $message['sender_name'];
                        $chat_with_role = $is_sender ? $message['receiver_role'] : $message['sender_role'];
                        
                        error_log("Chat partner details - ID: $chat_with_id, Name: $chat_with_name, Role: $chat_with_role");
                        ?>
                        <a href="chat.php?user_id=<?php echo htmlspecialchars($chat_with_id); ?>" 
                           class="message-card text-decoration-none">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title text-gold mb-1">
                                                <?php echo htmlspecialchars($chat_with_name); ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($chat_with_role); ?></span>
                                            </h5>
                                            <p class="card-text text-light mb-1">
                                                <?php 
                                                $preview = htmlspecialchars(substr($message['message'], 0, 50));
                                                if (strlen($message['message']) > 50) {
                                                    $preview .= '...';
                                                }
                                                echo $preview;
                                                ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                            </small>
                                        </div>
                                        <?php if (!$message['is_read'] && $message['receiver_id'] == $user_id): ?>
                                            <span class="badge bg-primary">New</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1" aria-labelledby="newMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newMessageModalLabel">New Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="messageForm">
                        <div class="mb-3">
                            <label for="receiver_id" class="form-label">To:</label>
                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                <option value="">Select recipient...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message:</label>
                            <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sendMessage">Send</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Message Modal -->
    <?php if ($_SESSION['role'] === 'coach'): // Use lowercase to match database
        error_log("Rendering team message modal for coach");
    ?>
    <div class="modal fade" id="teamMessageModal" tabindex="-1" aria-labelledby="teamMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="teamMessageModalLabel">Send Team Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="teamMessageForm">
                        <?php
                        // Get coach's teams by checking coach_id in teams table
                        $team_query = "SELECT DISTINCT t.id, t.name 
                                     FROM teams t 
                                     WHERE t.coach_id = :coach_id";
                        $team_stmt = $conn->prepare($team_query);
                        $team_stmt->bindParam(':coach_id', $_SESSION['user_id'], PDO::PARAM_INT);
                        $team_stmt->execute();
                        $team_result = $team_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        error_log("Team query executed for user_id: " . $_SESSION['user_id']);
                        error_log("Number of teams found: " . count($team_result));
                        error_log("SQL Query: " . $team_query);
                        
                        if (count($team_result) == 0) {
                            error_log("No teams found for coach with ID: " . $_SESSION['user_id']);
                        }
                        ?>
                        <div class="mb-3">
                            <label for="team" class="form-label">Team</label>
                            <select class="form-select" id="team" name="team" required>
                                <?php foreach ($team_result as $team): ?>
                                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="teamMessage" class="form-label">Message</label>
                            <textarea class="form-control" id="teamMessage" name="message" rows="4" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php else:
        error_log("Not rendering team message modal - user is not a coach");
    endif; ?>

    <script>
        // Global error handler
        window.onerror = function(msg, url, lineNo, columnNo, error) {
            console.error('Global error:', {
                message: msg,
                url: url,
                lineNo: lineNo,
                columnNo: columnNo,
                error: error
            });
            return false;
        };

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            // Debug: Check if Bootstrap is loaded
            console.log('Bootstrap version:', typeof bootstrap !== 'undefined' ? 'Loaded' : 'Not loaded');
            
            // Debug: Check if modals exist
            const newMessageModal = document.getElementById('newMessageModal');
            const teamMessageModal = document.getElementById('teamMessageModal');
            console.log('New Message Modal exists:', newMessageModal !== null);
            console.log('Team Message Modal exists:', teamMessageModal !== null);
            
            // Debug: Check button elements
            const newMessageBtn = document.querySelector('.new-message-btn');
            const teamMessageBtn = document.querySelector('.team-message-btn');
            console.log('New Message Button exists:', newMessageBtn !== null);
            console.log('Team Message Button exists:', teamMessageBtn !== null);

            // Add click listeners for debugging
            if (newMessageBtn) {
                newMessageBtn.addEventListener('click', function(e) {
                    console.log('New message button clicked');
                    console.log('Button data-bs-toggle:', this.getAttribute('data-bs-toggle'));
                    console.log('Button data-bs-target:', this.getAttribute('data-bs-target'));
                });
            }

            if (teamMessageBtn) {
                teamMessageBtn.addEventListener('click', function(e) {
                    console.log('Team message button clicked');
                    console.log('Button data-bs-toggle:', this.getAttribute('data-bs-toggle'));
                    console.log('Button data-bs-target:', this.getAttribute('data-bs-target'));
                });
            }

            // Initialize Bootstrap modals manually
            if (newMessageModal) {
                try {
                    const modal = new bootstrap.Modal(newMessageModal);
                    console.log('New message modal initialized');
                    
                    // Add modal event listeners
                    newMessageModal.addEventListener('show.bs.modal', function () {
                        console.log('New message modal is about to show');
                    });
                    
                    newMessageModal.addEventListener('shown.bs.modal', function () {
                        console.log('New message modal is now visible');
                    });
                    
                    newMessageModal.addEventListener('hide.bs.modal', function () {
                        console.log('New message modal is about to hide');
                    });
                } catch (error) {
                    console.error('Error initializing new message modal:', error);
                }
            }

            if (teamMessageModal) {
                try {
                    const modal = new bootstrap.Modal(teamMessageModal);
                    console.log('Team message modal initialized');
                    
                    // Add modal event listeners
                    teamMessageModal.addEventListener('show.bs.modal', function () {
                        console.log('Team message modal is about to show');
                    });
                    
                    teamMessageModal.addEventListener('shown.bs.modal', function () {
                        console.log('Team message modal is now visible');
                    });
                    
                    teamMessageModal.addEventListener('hide.bs.modal', function () {
                        console.log('Team message modal is about to hide');
                    });
                } catch (error) {
                    console.error('Error initializing team message modal:', error);
                }
            }

            // Handle new message form submission
            document.getElementById('sendMessage').addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default form submission
                console.log('Send message clicked');
                const receiverId = document.getElementById('receiver_id').value;
                const message = document.getElementById('message').value;

                console.log('Message data:', { receiverId, message });

                if (!receiverId || !message) {
                    alert('Please fill in all fields');
                    return;
                }

                console.log('Sending message request...');
                fetch('api/send_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        receiver_id: receiverId,
                        message: message
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    console.log('Response received:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Message sent response:', data);
                    if (data.success) {
                        const modalInstance = bootstrap.Modal.getInstance(newMessageModal);
                        if (modalInstance) {
                            console.log('Closing modal...');
                            modalInstance.hide();
                            // Clear the form
                            document.getElementById('message').value = '';
                            // Reload the page after a short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            console.error('Could not get modal instance');
                            window.location.reload();
                        }
                    } else {
                        alert('Error sending message: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error sending message. Please try again.');
                });
            });

            // Handle team message form submission
            const teamMessageForm = document.getElementById('teamMessageForm');
            if (teamMessageForm) {
                teamMessageForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('Team message form submitted');
                    
                    const teamId = document.getElementById('team').value;
                    const message = document.getElementById('teamMessage').value;
                    
                    console.log('Team message data:', { teamId, message });

                    if (!teamId || !message) {
                        alert('Please fill in all fields');
                        return;
                    }

                    console.log('Sending team message request...');
                    fetch('api/send_team_message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            team_id: teamId,
                            message: message
                        })
                    })
                    .then(response => {
                        console.log('Response received:', response);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Team message sent response:', data);
                        if (data.success) {
                            const modalInstance = bootstrap.Modal.getInstance(teamMessageModal);
                            if (modalInstance) {
                                console.log('Closing team modal...');
                                modalInstance.hide();
                            } else {
                                console.error('Could not get team modal instance');
                            }
                            location.reload();
                        } else {
                            alert('Error sending team message: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error sending team message. Please try again.');
                    });
                });
            }
        });
        
        function checkUnreadMessages() {
            fetch('api/check_unread_messages.php')
                .then(response => response.json())
                .then(data => {
                    const messagesNavItem = document.getElementById('messages-nav-item');
                    if (data.unread_count > 0) {
                        messagesNavItem.classList.add('has-unread');
                    } else {
                        messagesNavItem.classList.remove('has-unread');
                    }
                })
                .catch(error => console.error('Error checking unread messages:', error));
        }

        // Check for unread messages every 30 seconds
        setInterval(checkUnreadMessages, 30000);
        // Check immediately when page loads
        checkUnreadMessages();
    </script>
</body>
</html>
