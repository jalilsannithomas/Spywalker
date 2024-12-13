<?php
require_once(__DIR__ . '/../config/db.php');

// Start transaction
$conn->begin_transaction();

try {
    // First, get the IDs of sample teams
    $sample_teams_query = "SELECT id FROM teams WHERE name LIKE 'Sample Team%'";
    $result = $conn->query($sample_teams_query);
    $team_ids = [];
    
    while($row = $result->fetch_assoc()) {
        $team_ids[] = $row['id'];
    }
    
    if (!empty($team_ids)) {
        $team_ids_str = implode(',', $team_ids);
        
        // Delete team members
        $conn->query("DELETE FROM team_members WHERE team_id IN ($team_ids_str)");
        
        // Delete team schedules/matches if they exist
        $conn->query("DELETE FROM matches WHERE home_team_id IN ($team_ids_str) OR away_team_id IN ($team_ids_str)");
        
        // Delete the teams
        $conn->query("DELETE FROM teams WHERE id IN ($team_ids_str)");
        
        // Commit the transaction
        $conn->commit();
        
        echo "Successfully removed sample teams and related data.";
    } else {
        echo "No sample teams found.";
    }
    
} catch (Exception $e) {
    // If there's an error, roll back the transaction
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}

// Close the connection
$conn->close();
?>
