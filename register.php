<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Debug logging
error_log("Register.php accessed");
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("POST request received");
    error_log("POST data: " . print_r($_POST, true));
}

// Get sports and positions
$sports_query = "SELECT id, name FROM sports ORDER BY name";
$sports_result = mysqli_query($conn, $sports_query);
$sports = [];
while ($row = mysqli_fetch_assoc($sports_result)) {
    $sports[$row['id']] = $row;
}

// Get positions for all sports
$positions_query = "SELECT id, sport_id, name FROM positions ORDER BY sport_id, name";
$positions_result = mysqli_query($conn, $positions_query);
$positions = [];
while ($row = mysqli_fetch_assoc($positions_result)) {
    if (!isset($positions[$row['sport_id']])) {
        $positions[$row['sport_id']] = [];
    }
    $positions[$row['sport_id']][] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    error_log("Registration - Raw password: " . $_POST['password']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    error_log("Registration - Hashed password: " . $password);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Check if username already exists
        $check_sql = "SELECT id FROM users WHERE username = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $username);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($result) > 0) {
            throw new Exception("Username already exists");
        }
        
        // Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($result) > 0) {
            throw new Exception("Email already exists");
        }

        // Insert into users table
        $sql = "INSERT INTO users (username, email, password, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception("Database error: " . mysqli_error($conn));
        }
        
        $first_name = isset($_POST['first_name']) ? mysqli_real_escape_string($conn, $_POST['first_name']) : null;
        $last_name = isset($_POST['last_name']) ? mysqli_real_escape_string($conn, $_POST['last_name']) : null;
        mysqli_stmt_bind_param($stmt, "ssssss", $username, $email, $password, $role, $first_name, $last_name);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error creating user account: " . mysqli_stmt_error($stmt));
        }
        
        $user_id = mysqli_insert_id($conn);
        error_log("User created with ID: " . $user_id);
        
        // Create profile based on role
        if ($role === 'athlete') {
            error_log("Creating athlete profile for user_id: " . $user_id);
            error_log("POST data: " . print_r($_POST, true));
            
            if (!isset($_POST['height_feet']) || !isset($_POST['height_inches']) || !isset($_POST['sport']) || !isset($_POST['position']) || !isset($_POST['school_year']) || !isset($_POST['first_name']) || !isset($_POST['last_name'])) {
                error_log("Missing required athlete information");
                throw new Exception("Missing required athlete information");
            }
            
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $height_feet = (int)$_POST['height_feet'];
            $height_inches = (int)$_POST['height_inches'];
            $height = ($height_feet * 12) + $height_inches;
            $weight = isset($_POST['weight']) ? (int)$_POST['weight'] : null;
            $sport_id = (int)$_POST['sport'];
            $position_id = (int)$_POST['position'];
            $jersey_number = isset($_POST['jersey_number']) ? (int)$_POST['jersey_number'] : null;
            $years_experience = isset($_POST['years_experience']) ? (int)$_POST['years_experience'] : 0;
            $school_year = mysqli_real_escape_string($conn, $_POST['school_year']);
            
            error_log("Athlete data prepared");
            
            // Debug: Log all variables
            error_log("Debug values:");
            error_log("user_id: " . $user_id);
            error_log("first_name: " . $first_name);
            error_log("last_name: " . $last_name);
            error_log("height: " . $height);
            error_log("weight: " . ($weight === null ? 'NULL' : $weight));
            error_log("sport_id: " . $sport_id);
            error_log("position_id: " . $position_id);
            error_log("jersey_number: " . ($jersey_number === null ? 'NULL' : $jersey_number));
            error_log("years_experience: " . $years_experience);
            error_log("school_year: " . $school_year);
            
            $sql = "INSERT INTO athlete_profiles 
                    (user_id, first_name, last_name, height, weight, sport_id, position_id, jersey_number, years_experience, school_year) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                error_log("Failed to prepare athlete profile insert: " . mysqli_error($conn));
                throw new Exception("Database error: " . mysqli_error($conn));
            }
            
            if (!mysqli_stmt_bind_param($stmt, "issiiiiiis", 
                $user_id, $first_name, $last_name, $height, $weight, 
                $sport_id, $position_id, $jersey_number, $years_experience, $school_year
            )) {
                error_log("Failed to bind parameters: " . mysqli_stmt_error($stmt));
                throw new Exception("Error binding parameters: " . mysqli_stmt_error($stmt));
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                error_log("Failed to execute athlete profile insert: " . mysqli_stmt_error($stmt));
                throw new Exception("Error creating athlete profile: " . mysqli_stmt_error($stmt));
            }
            
            // Commit the transaction
            mysqli_commit($conn);
            error_log("Successfully created athlete profile");
            
            // Handle AJAX requests differently
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode(['success' => true, 'redirect' => 'login.php']);
                exit();
            }
            
            // Set success message
            $_SESSION['success_message'] = "Registration successful! Please log in.";
            header("Location: login.php");
            exit();
        } elseif ($role === 'coach') {
            error_log("Creating coach profile for user_id: " . $user_id);
            error_log("POST data: " . print_r($_POST, true));
            
            if (!isset($_POST['sport']) || !isset($_POST['first_name']) || !isset($_POST['last_name'])) {
                error_log("Missing required coach information");
                throw new Exception("Missing required coach information");
            }
            
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $sport_id = (int)$_POST['sport'];
            $specialization = isset($_POST['specialization']) ? mysqli_real_escape_string($conn, $_POST['specialization']) : null;
            $certification = isset($_POST['certification']) ? mysqli_real_escape_string($conn, $_POST['certification']) : null;
            $education = isset($_POST['education']) ? mysqli_real_escape_string($conn, $_POST['education']) : null;
            
            $sql = "INSERT INTO coach_profiles (user_id, first_name, last_name, sport_id, specialization, certification, education) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                throw new Exception("Database error: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "ississs", $user_id, $first_name, $last_name, $sport_id, $specialization, $certification, $education);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error creating coach profile: " . mysqli_stmt_error($stmt));
            }
            
            error_log("Coach profile created successfully");
        } elseif ($role === 'fan') {
            // For fans, we only need basic information which is already in the users table
            error_log("Creating fan profile for user_id: " . $user_id);
        } else {
            throw new Exception("Invalid role selected");
        }
        
        // Commit transaction if everything was successful
        mysqli_commit($conn);
        error_log("Registration successful for user_id: " . $user_id);
        
        // Handle AJAX requests differently
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => true, 'redirect' => 'login.php']);
            exit();
        }
        
        // Set success message
        $_SESSION['success_message'] = "Registration successful! Please log in.";
        header("Location: login.php");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Registration failed: " . $e->getMessage());
        
        // Handle AJAX requests differently
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
        
        $error = $e->getMessage();
    }
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

        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="registrationForm" onsubmit="return validateForm(event);">
            <label>USERNAME</label>
            <input type="text" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            
            <label>EMAIL</label>
            <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            
            <label>PASSWORD</label>
            <input type="password" name="password" required>
            
            <label>ROLE</label>
            <select name="role" required>
                <option value="">Select Role</option>
                <option value="athlete">Athlete</option>
                <option value="coach">Coach</option>
                <option value="fan">Fan</option>
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
            
            <div id="profileFields" style="display: none;">
                <label>FIRST NAME</label>
                <input type="text" name="first_name" class="form-control athlete-required" required>
                
                <label>LAST NAME</label>
                <input type="text" name="last_name" class="form-control athlete-required" required>
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
                <input type="number" name="years_experience" class="form-control" min="0" max="20">
                
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
        
        function validateForm(event) {
            console.log('validateForm called');
            event.preventDefault();
            
            const form = document.getElementById('registrationForm');
            const formData = new FormData(form);
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const jsonData = JSON.parse(data);
                    if (jsonData.success) {
                        window.location.href = jsonData.redirect;
                    } else {
                        alert(jsonData.error || 'Registration failed. Please try again.');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e, data);
                    // If we can't parse the JSON, redirect to login if registration was successful
                    if (data.includes('login.php')) {
                        window.location.href = 'login.php';
                    } else {
                        alert('An error occurred during registration. Please check your input and try again.');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while connecting to the server. Please try again.');
            });
            
            return false;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            
            const form = document.getElementById('registrationForm');
            const roleSelect = document.querySelector('select[name="role"]');
            const sportField = document.getElementById('sportField');
            const profileFields = document.getElementById('profileFields');
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
                profileFields.style.display = 'none';
                athleteFields.style.display = 'none';
                coachFields.style.display = 'none';
                
                // Remove required attributes
                document.querySelectorAll('.athlete-required').forEach(field => {
                    field.required = false;
                });
                
                // Show relevant fields based on role
                if (selectedRole === 'athlete') {
                    sportField.style.display = 'block';
                    profileFields.style.display = 'block';
                    athleteFields.style.display = 'block';
                    // Set required fields for athlete
                    document.querySelectorAll('.athlete-required').forEach(field => {
                        field.required = true;
                    });
                } else if (selectedRole === 'coach') {
                    sportField.style.display = 'block';
                    profileFields.style.display = 'block';
                    coachFields.style.display = 'block';
                }
            });
            
            // Update positions when sport is selected
            sportSelect.addEventListener('change', updatePositions);
            
            // Initialize fields based on current role
            if (roleSelect.value === 'athlete') {
                document.getElementById('profileFields').style.display = 'block';
                athleteFields.style.display = 'block';
                if (sportSelect.value) {
                    updatePositions();
                }
            }
        });

        // Show/hide athlete fields based on role selection
        roleSelect.addEventListener('change', function() {
            const profileFields = document.getElementById('profileFields');
            if (this.value === 'athlete') {
                profileFields.style.display = 'block';
                athleteFields.style.display = 'block';
            } else {
                profileFields.style.display = 'none';
                athleteFields.style.display = 'none';
            }
        });
    </script>
</body>
</html>
