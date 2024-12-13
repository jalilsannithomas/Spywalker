<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

try {
    // Get the player ID from the URL parameter
    $player_id = isset($_GET['player_id']) ? (int)$_GET['player_id'] : null;
    
    echo "<h3>Player Data Check</h3>";
    
    // If specific player ID provided, check that player
    if ($player_id) {
        $player_query = "SELECT p.*, t.name as team_name 
                        FROM players p 
                        LEFT JOIN teams t ON p.team_id = t.id 
                        WHERE p.id = ?";
        $stmt = $conn->prepare($player_query);
        $stmt->bind_param("i", $player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo "<pre>Player found:\n";
            print_r($row);
            echo "</pre>";
        } else {
            echo "No player found with ID: $player_id<br>";
        }
    }
    
    // Show all players from both teams in the match
    echo "<h4>All Players in Teams:</h4>";
    $all_players_query = "SELECT p.*, t.name as team_name 
                         FROM players p 
                         JOIN teams t ON p.team_id = t.id 
                         ORDER BY t.name, p.name";
    $result = $conn->query($all_players_query);
    
    if ($result) {
        $current_team = '';
        while ($row = $result->fetch_assoc()) {
            if ($current_team != $row['team_name']) {
                echo "<h4>{$row['team_name']}</h4>";
                $current_team = $row['team_name'];
            }
            echo "ID: {$row['id']} - {$row['name']} (Jersey #: {$row['jersey_number']})<br>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
