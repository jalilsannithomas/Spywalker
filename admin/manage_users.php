<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

try {
    // Get all users with their details
    $sql = "SELECT u.*, 
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            COALESCE(u.is_active, 1) as is_active
            FROM users u
            ORDER BY u.role, u.email";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $error_message = "Error loading users. Please try again later.";
    $users = [];
}

// Handle user actions (activate/deactivate/delete/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    try {
        switch($action) {
            case 'deactivate':
                $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = :user_id AND role != 'admin'");
                $stmt->execute(['user_id' => $user_id]);
                if ($stmt->rowCount() > 0) {
                    $success_message = "User deactivated successfully";
                }
                break;
                
            case 'activate':
                $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
                if ($stmt->rowCount() > 0) {
                    $success_message = "User activated successfully";
                }
                break;
                
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id AND role != 'admin'");
                $stmt->execute(['user_id' => $user_id]);
                if ($stmt->rowCount() > 0) {
                    $success_message = "User deleted successfully";
                }
                break;
                
            case 'edit':
                // Validate required fields
                if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email']) || empty($_POST['role'])) {
                    throw new Exception("All fields are required except password");
                }
                
                // Start transaction
                $conn->beginTransaction();
                
                // Build update SQL based on whether password is being changed
                $sql = "UPDATE users SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        role = :role";
                
                $params = [
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'email' => $_POST['email'],
                    'role' => $_POST['role'],
                    'user_id' => $user_id
                ];
                
                // Add password to update if provided
                if (!empty($_POST['password'])) {
                    $sql .= ", password = :password";
                    $params['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                
                $sql .= " WHERE id = :user_id AND role != 'admin'";
                
                // Execute update
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
                if ($stmt->rowCount() > 0) {
                    $conn->commit();
                    $success_message = "User updated successfully";
                } else {
                    throw new Exception("No changes were made or user not found");
                }
                break;
        }
        
        // Refresh user list after any action
        $stmt = $conn->prepare("SELECT u.*, 
                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                COALESCE(u.is_active, 1) as is_active
                FROM users u
                ORDER BY u.role, u.email");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        if ($action === 'edit' && isset($conn)) {
            $conn->rollBack();
        }
        error_log("Error performing action $action: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - SpyWalker</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #241409;
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
        }

        .container {
            padding: 20px;
        }

        .page-title {
            color: #D4AF37;
            text-align: center;
            margin-bottom: 30px;
        }

        .users-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .users-table th {
            color: #D4AF37;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #D4AF37;
        }

        .users-table td {
            padding: 12px;
            background-color: #362111;
            color: #D4AF37;
        }

        .users-table tr td:first-child {
            border-top-left-radius: 6px;
            border-bottom-left-radius: 6px;
        }

        .users-table tr td:last-child {
            border-top-right-radius: 6px;
            border-bottom-right-radius: 6px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .status-badge.active {
            background-color: #28a745;
            color: white;
        }
        
        .status-badge.inactive {
            background-color: #dc3545;
            color: white;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .role-badge.admin {
            background-color: #007bff;
            color: white;
        }
        
        .role-badge.athlete {
            background-color: #17a2b8;
            color: white;
        }
        
        .role-badge.coach {
            background-color: #6c757d;
            color: white;
        }
        
        .role-badge.fan {
            background-color: #ffc107;
            color: black;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin: 0 2px;
        }
        
        .modal-content {
            background-color: #241409;
            color: #D4AF37;
            border: 2px solid #D4AF37;
        }

        .modal-header {
            border-bottom: 1px solid #D4AF37;
        }

        .modal-footer {
            border-top: 1px solid #D4AF37;
        }

        .form-control {
            background-color: #362111;
            border: 1px solid #D4AF37;
            color: #D4AF37;
        }

        .form-control:focus {
            background-color: #362111;
            border-color: #FFD700;
            color: #D4AF37;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }

        .form-label {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8em;
            margin-bottom: 10px;
        }

        .btn-close {
            color: #D4AF37;
            filter: invert(1) sepia(1) saturate(10) hue-rotate(10deg);
        }

        .form-text {
            color: #8B7355;
        }

        select.form-control option {
            background-color: #362111;
            color: #D4AF37;
        }

        .modal-title {
            font-family: 'Press Start 2P', monospace;
            font-size: 1.2em;
        }

        .btn-primary {
            background-color: #D4AF37;
            border-color: #D4AF37;
            color: #241409;
        }

        .btn-primary:hover {
            background-color: #FFD700;
            border-color: #FFD700;
            color: #241409;
        }

        .btn-secondary {
            background-color: #8B7355;
            border-color: #8B7355;
            color: #241409;
        }

        .btn-secondary:hover {
            background-color: #A0522D;
            border-color: #A0522D;
            color: #241409;
        }

        .alert {
            margin-top: 20px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .alert-success {
            background-color: #28a745;
            color: white;
            border: none;
        }

        .alert-danger {
            background-color: #dc3545;
            color: white;
            border: none;
        }
    </style>
</head>
<body>
    <?php require_once '../components/navbar.php'; ?>
    
    <div class="container">
        <h1 class="page-title">Manage Users</h1>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <table class="users-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <div class="btn-group" role="group">
                                    <?php if ($user['is_active']): ?>
                                        <button type="button" class="btn btn-warning btn-sm deactivate-user" data-user-id="<?php echo $user['id']; ?>">
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-success btn-sm activate-user" data-user-id="<?php echo $user['id']; ?>">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-danger btn-sm delete-user" data-user-id="<?php echo $user['id']; ?>">
                                        Delete
                                    </button>
                                    
                                    <button type="button" class="btn btn-primary btn-sm edit-user" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $user['id']; ?>">
                                        Edit
                                    </button>
                                </div>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel<?php echo $user['id']; ?>">Edit User</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" class="edit-user-form">
                                                <div class="modal-body">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="edit">
                                                    
                                                    <div class="mb-3">
                                                        <label for="firstName<?php echo $user['id']; ?>" class="form-label">First Name</label>
                                                        <input type="text" class="form-control" id="firstName<?php echo $user['id']; ?>" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="lastName<?php echo $user['id']; ?>" class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" id="lastName<?php echo $user['id']; ?>" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="email<?php echo $user['id']; ?>" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="email<?php echo $user['id']; ?>" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="role<?php echo $user['id']; ?>" class="form-label">Role</label>
                                                        <select class="form-control" id="role<?php echo $user['id']; ?>" name="role" required>
                                                            <option value="athlete" <?php echo $user['role'] === 'athlete' ? 'selected' : ''; ?>>Athlete</option>
                                                            <option value="coach" <?php echo $user['role'] === 'coach' ? 'selected' : ''; ?>>Coach</option>
                                                            <option value="fan" <?php echo $user['role'] === 'fan' ? 'selected' : ''; ?>>Fan</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="password<?php echo $user['id']; ?>" class="form-label">New Password</label>
                                                        <input type="password" class="form-control" id="password<?php echo $user['id']; ?>" name="password">
                                                        <small class="form-text text-muted">Leave blank to keep current password</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all modals
            var modals = [].slice.call(document.querySelectorAll('.modal'));
            var modalInstances = modals.map(function(modal) {
                return new bootstrap.Modal(modal);
            });

            // Handle activate/deactivate buttons
            document.querySelectorAll('.activate-user, .deactivate-user').forEach(function(button) {
                button.addEventListener('click', function() {
                    var userId = this.getAttribute('data-user-id');
                    var action = this.classList.contains('activate-user') ? 'activate' : 'deactivate';
                    var confirmMessage = action === 'activate' ? 'Are you sure you want to activate this user?' : 'Are you sure you want to deactivate this user?';
                    
                    if (confirm(confirmMessage)) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="user_id" value="${userId}">
                            <input type="hidden" name="action" value="${action}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });

            // Handle delete buttons
            document.querySelectorAll('.delete-user').forEach(function(button) {
                button.addEventListener('click', function() {
                    var userId = this.getAttribute('data-user-id');
                    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="user_id" value="${userId}">
                            <input type="hidden" name="action" value="delete">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });

            // Debug modal functionality
            document.querySelectorAll('.edit-user').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    console.log('Edit button clicked');
                    var modalId = this.getAttribute('data-bs-target');
                    console.log('Modal ID:', modalId);
                    var modalElement = document.querySelector(modalId);
                    console.log('Modal element:', modalElement);
                    
                    if (modalElement) {
                        var modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    } else {
                        console.error('Modal element not found:', modalId);
                    }
                });
            });
        });
    </script>
</body>
</html>
