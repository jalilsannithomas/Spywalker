<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name']);
                
                $sql = "INSERT INTO sports (name) VALUES (?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $name);
                
                if ($stmt->execute()) {
                    $success_message = "Sport added successfully!";
                } else {
                    $error_message = "Error adding sport: " . $conn->error;
                }
                break;
                
            case 'edit':
                $sport_id = intval($_POST['sport_id']);
                $name = trim($_POST['name']);
                
                $sql = "UPDATE sports SET name = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $name, $sport_id);
                
                if ($stmt->execute()) {
                    $success_message = "Sport updated successfully!";
                } else {
                    $error_message = "Error updating sport: " . $conn->error;
                }
                break;
                
            case 'delete':
                $sport_id = intval($_POST['sport_id']);
                
                $sql = "DELETE FROM sports WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $sport_id);
                
                if ($stmt->execute()) {
                    $success_message = "Sport deleted successfully!";
                } else {
                    $error_message = "Error deleting sport: " . $conn->error;
                }
                break;
        }
    }
}

// Get all sports
$sql = "SELECT * FROM sports ORDER BY name";
$result = $conn->query($sql);
if (!$result) {
    die("Error fetching sports: " . $conn->error);
}
$sports = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Sports - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php require_once '../components/navbar.php'; ?>

    <div class="container py-4">
        <h1 class="vintage-title text-center mb-4">Manage Sports</h1>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="text-end mb-3">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSportModal">Add New Sport</button>
        </div>

        <div class="admin-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sport Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sports as $sport): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sport['name']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $sport['id']; ?>">Edit</button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this sport?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="sport_id" value="<?php echo $sport['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $sport['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Sport</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="sport_id" value="<?php echo $sport['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Sport Name</label>
                                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($sport['name']); ?>" required>
                                                </div>

                                                <div class="text-end">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Sport Modal -->
    <div class="modal fade" id="addSportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Sport</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Sport Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Sport</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
