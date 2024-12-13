<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        switch ($_POST['action']) {
            case 'activate':
                $sql = "UPDATE users SET is_active = 1 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $success_message = "User activated successfully!";
                }
                break;
                
            case 'deactivate':
                $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $success_message = "User deactivated successfully!";
                }
                break;
                
            case 'delete':
                $sql = "DELETE FROM users WHERE id = ? AND role != 'admin'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $success_message = "User deleted successfully!";
                }
                break;
                
            case 'edit':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = trim($_POST['password']);
                $role = $_POST['role'];
                
                // Check if username already exists for other users
                $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $username, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error_message = "Username already exists!";
                } else {
                    // Update user details
                    if (!empty($password)) {
                        // If password is provided, update it along with other details
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $user_id);
                    } else {
                        // If no password provided, update other details only
                        $sql = "UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssi", $username, $email, $role, $user_id);
                    }
                    
                    if ($stmt->execute()) {
                        $success_message = "User updated successfully!";
                    } else {
                        $error_message = "Error updating user: " . $conn->error;
                    }
                }
                break;
        }
    }
}

// Get all users
$sql = "SELECT u.*, 
        CASE 
            WHEN u.role = 'athlete' THEN CONCAT(ap.first_name, ' ', ap.last_name)
            WHEN u.role = 'coach' THEN CONCAT(cp.first_name, ' ', cp.last_name)
            ELSE NULL
        END as full_name,
        u.password as current_password
        FROM users u
        LEFT JOIN athlete_profiles ap ON u.id = ap.user_id AND u.role = 'athlete'
        LEFT JOIN coach_profiles cp ON u.id = cp.user_id AND u.role = 'coach'
        ORDER BY u.role, u.username";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/manage-users.css" rel="stylesheet">
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
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="status-badge active">Active</span>
                            <?php else: ?>
                                <span class="status-badge inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <?php if ($user['is_active']): ?>
                                        <button type="submit" name="action" value="deactivate" class="action-btn deactivate" onclick="return confirm('Are you sure you want to deactivate this user?')">
                                            ❌
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate" class="action-btn activate">
                                            ✔️
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="action-btn edit" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $user['id']; ?>">
                                        ✏️
                                    </button>
                                    <button type="submit" name="action" value="delete" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                        ❌
                                    </button>
                                </form>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content pixel-modal">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit User</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    
                                                    <div class="form-group">
                                                        <label class="form-label">Username</label>
                                                        <input type="text" class="pixel-input" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" class="pixel-input" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="form-label">Current Password Hash</label>
                                                        <input type="text" class="pixel-input" value="<?php echo htmlspecialchars($user['current_password']); ?>" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="form-label">New Password (leave blank to keep current)</label>
                                                        <input type="password" class="pixel-input" name="password">
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="form-label">Role</label>
                                                        <select class="pixel-input" name="role" required>
                                                            <option value="athlete" <?php echo $user['role'] === 'athlete' ? 'selected' : ''; ?>>Athlete</option>
                                                            <option value="coach" <?php echo $user['role'] === 'coach' ? 'selected' : ''; ?>>Coach</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="text-end">
                                                        <button type="button" class="action-btn" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="action-btn view">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
