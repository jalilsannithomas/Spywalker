<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = htmlspecialchars($_POST['role']);
    $first_name = htmlspecialchars($_POST['first_name']);
    $last_name = htmlspecialchars($_POST['last_name']);
    
    try {
        // Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$email]);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            throw new Exception("Email already exists");
        }

        // Insert the user
        $sql = "INSERT INTO users (email, password, role, first_name, last_name) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email, $password, $role, $first_name, $last_name]);
        
        $user_id = $conn->lastInsertId();
        
        // Create profile based on role
        if ($role === 'athlete') {
            if (!isset($_POST['height_feet']) || !isset($_POST['height_inches']) || !isset($_POST['sport']) || !isset($_POST['position']) || !isset($_POST['school_year'])) {
                throw new Exception("Missing required athlete information");
            }
            
            $sport_id = (int)$_POST['sport'];
            $position_id = (int)$_POST['position'];
            $height_feet = (int)$_POST['height_feet'];
            $height_inches = (int)$_POST['height_inches'];
            $weight = isset($_POST['weight']) ? (int)$_POST['weight'] : null;
            $jersey_number = isset($_POST['jersey_number']) ? (int)$_POST['jersey_number'] : null;
            $years_exp = isset($_POST['years_of_experience']) ? (int)$_POST['years_of_experience'] : null;
            $school_year = $_POST['school_year'];
            
            $sql = "INSERT INTO athlete_profiles (user_id, sport_id, position_id, jersey_number, height_feet, height_inches, weight, years_of_experience, school_year) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $sport_id, $position_id, $jersey_number, $height_feet, $height_inches, $weight, $years_exp, $school_year]);
        }
        
        // Redirect to login page
        header("Location: login.php");
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get sports and positions for the form
$sports_query = "SELECT id, name FROM sports ORDER BY name";
$stmt = $conn->prepare($sports_query);
$stmt->execute();
$sports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$positions_query = "SELECT id, sport_id, name FROM positions ORDER BY sport_id, name";
$stmt = $conn->prepare($positions_query);
$stmt->execute();
$positions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($positions[$row['sport_id']])) {
        $positions[$row['sport_id']] = [];
    }
    $positions[$row['sport_id']][] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #2C1810;
            color: #D4AF37;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background-color: #3C2415;
            border: 4px solid #D4AF37;
            border-radius: 10px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        h1 {
            text-align: center;
            color: #D4AF37;
            font-size: 2em;
            margin-bottom: 10px;
            text-shadow: 2px 2px #000;
        }

        h2 {
            text-align: center;
            color: #D4AF37;
            font-size: 1em;
            margin-bottom: 30px;
            text-shadow: 1px 1px #000;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #D4AF37;
            text-transform: uppercase;
            font-size: 0.8em;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            background-color: #1a0f0a;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            font-family: 'Press Start 2P', cursive;
            font-size: 0.8em;
            border-radius: 5px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #FFD700;
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }

        button {
            width: 100%;
            padding: 15px;
            background-color: #D4AF37;
            border: none;
            color: #2C1810;
            font-family: 'Press Start 2P', cursive;
            font-size: 1em;
            cursor: pointer;
            margin-top: 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        button:hover {
            background-color: #FFD700;
            transform: scale(1.02);
        }

        .sign-in-text {
            text-align: center;
            margin-top: 20px;
            font-size: 0.7em;
        }

        .sign-in-link {
            color: #FFD700;
            text-decoration: none;
        }

        .sign-in-link:hover {
            color: #FFF;
            text-decoration: underline;
        }

        .alert {
            background-color: #8B0000;
            border: 2px solid #FF0000;
            color: #FFF;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.8em;
            text-align: center;
        }

        .alert-success {
            background-color: #32CD32;
            border: 2px solid #008000;
            color: #FFF;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.8em;
            text-align: center;
        }

        #athleteFields, #coachFields {
            border: 2px solid #D4AF37;
            padding: 20px;
            margin-top: 20px;
            border-radius: 5px;
            background-color: rgba(212, 175, 55, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SPYWALKER</h1>
        <h2>PLAYER REGISTRATION</h2>
        
        <?php if (isset($error)): ?>
        <div class="alert">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="registrationForm">
            <label>EMAIL</label>
            <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            
            <label>FIRST NAME</label>
            <input type="text" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
            
            <label>LAST NAME</label>
            <input type="text" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
            
            <label>PASSWORD</label>
            <input type="password" name="password" required>
            
            <label>ROLE</label>
            <select name="role" required>
                <option value="">Select Role</option>
                <option value="athlete" <?php echo (isset($_POST['role']) && $_POST['role'] === 'athlete') ? 'selected' : ''; ?>>Athlete</option>
                <option value="coach" <?php echo (isset($_POST['role']) && $_POST['role'] === 'coach') ? 'selected' : ''; ?>>Coach</option>
                <option value="fan" <?php echo (isset($_POST['role']) && $_POST['role'] === 'fan') ? 'selected' : ''; ?>>Fan</option>
            </select>
            
            <div id="sportField" style="display: none;">
                <label>SPORT</label>
                <select name="sport" class="form-select athlete-required" id="sportSelect" required>
                    <option value="">Select Sport</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo $sport['id']; ?>"><?php echo htmlspecialchars($sport['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="athleteFields" style="display: none;">
                <label>POSITION</label>
                <select name="position" class="form-select athlete-required" id="positionSelect" required>
                    <option value="">Select Sport First</option>
                </select>
                
                <label>HEIGHT</label>
                <div class="row">
                    <div class="col-6">
                        <select name="height_feet" class="form-control athlete-required">
                            <option value="">Feet</option>
                            <?php for($i = 4; $i <= 7; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> ft</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <select name="height_inches" class="form-control athlete-required">
                            <option value="">Inches</option>
                            <?php for($i = 0; $i <= 11; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> in</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <label>WEIGHT (lbs)</label>
                <input type="number" name="weight" class="form-control" min="100" max="400">
                
                <label>JERSEY NUMBER</label>
                <input type="number" name="jersey_number" class="form-control" min="0" max="99">
                
                <label>YEARS OF EXPERIENCE</label>
                <input type="number" name="years_of_experience" class="form-control" min="0" max="20">
                
                <label>SCHOOL YEAR</label>
                <select name="school_year" class="form-select athlete-required">
                    <option value="">Select Year</option>
                    <option value="Freshman">Freshman</option>
                    <option value="Sophomore">Sophomore</option>
                    <option value="Junior">Junior</option>
                    <option value="Senior">Senior</option>
                </select>
            </div>
            
            <div id="coachFields" style="display: none;">
                <label>SPECIALIZATION</label>
                <input type="text" name="specialization" class="form-control">
                
                <label>CERTIFICATION</label>
                <input type="text" name="certification" class="form-control">
                
                <label>EDUCATION</label>
                <input type="text" name="education" class="form-control">
            </div>

            <button type="submit">REGISTER</button>
        </form>
        
        <div class="sign-in-text">
            <p>Already on the team? <a href="login.php" class="sign-in-link">Sign In</a></p>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store positions data
        const positions = <?php echo json_encode($positions); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            
            const form = document.getElementById('registrationForm');
            const roleSelect = document.querySelector('select[name="role"]');
            const sportField = document.getElementById('sportField');
            const athleteFields = document.getElementById('athleteFields');
            const coachFields = document.getElementById('coachFields');
            const sportSelect = document.getElementById('sportSelect');
            const positionField = document.getElementById('positionField');
            const positionSelect = document.getElementById('positionSelect');
            
            // Remove required attributes
            document.querySelectorAll('.athlete-required').forEach(field => {
                field.required = false;
            });
            
            // Handle sport selection change
            sportSelect.addEventListener('change', function() {
                const selectedSport = this.value;
                const positionSelect = document.getElementById('positionSelect');
                
                // Clear and reset position dropdown
                positionSelect.innerHTML = '<option value="">Select Position</option>';
                
                if (selectedSport && positions[selectedSport]) {
                    positions[selectedSport].forEach(position => {
                        const option = document.createElement('option');
                        option.value = position.id;
                        option.textContent = position.name;
                        positionSelect.appendChild(option);
                    });
                }
            });
            
            roleSelect.addEventListener('change', function() {
                console.log('Role changed to:', this.value);
                const selectedRole = this.value;
                
                // Hide all fields first
                sportField.style.display = 'none';
                athleteFields.style.display = 'none';
                coachFields.style.display = 'none';
                
                // Remove required attributes
                document.querySelectorAll('.athlete-required').forEach(field => {
                    field.required = false;
                });
                
                // Show relevant fields based on role
                if (selectedRole === 'athlete') {
                    sportField.style.display = 'block';
                    athleteFields.style.display = 'block';
                    // Set required fields for athlete
                    document.querySelectorAll('.athlete-required').forEach(field => {
                        field.required = true;
                    });
                } else if (selectedRole === 'coach') {
                    sportField.style.display = 'block';
                    coachFields.style.display = 'block';
                }
            });
            
            // Update positions when sport is selected
            sportSelect.addEventListener('change', updatePositions);
            
            // Initialize fields based on current role
            if (roleSelect.value === 'athlete') {
                athleteFields.style.display = 'block';
                if (sportSelect.value) {
                    updatePositions();
                }
            }
        });

        // Show/hide athlete fields based on role selection
        roleSelect.addEventListener('change', function() {
            const athleteFields = document.getElementById('athleteFields');
            if (this.value === 'athlete') {
                athleteFields.style.display = 'block';
            } else {
                athleteFields.style.display = 'none';
            }
        });
    </script>
</body>
</html>
