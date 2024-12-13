<?php
require_once '../config/db.php';

function calculateFantasyPoints($stats) {
    // Fantasy points calculation based on standard scoring system
    $points = [
        'points' => 1.0,      // 1 point per actual point scored
        'assists' => 2.0,     // 2 points per assist
        'rebounds' => 1.5,    // 1.5 points per rebound
        'steals' => 2.0,      // 2 points per steal
        'blocks' => 2.0,      // 2 points per block
    ];

    $fantasyPoints = 0;
    foreach ($points as $stat => $multiplier) {
        $fantasyPoints += $stats[$stat] * $multiplier;
    }

    return $fantasyPoints;
}

function updateFantasyPoints($conn, $match_id) {
    try {
        // Get match date to determine week and month
        $match_query = "SELECT match_date FROM matches WHERE id = ?";
        $stmt = $conn->prepare($match_query);
        $stmt->bind_param("i", $match_id);
        $stmt->execute();
        $match_result = $stmt->get_result();
        $match_data = $match_result->fetch_assoc();
        
        $match_date = new DateTime($match_data['match_date']);
        $week_number = $match_date->format("W");
        $month_number = $match_date->format("n");
        $season_year = $match_date->format("Y");

        // Get all player stats for the match
        $stats_query = "SELECT * FROM player_stats WHERE match_id = ?";
        $stmt = $conn->prepare($stats_query);
        $stmt->bind_param("i", $match_id);
        $stmt->execute();
        $stats_result = $stmt->get_result();

        while ($stats = $stats_result->fetch_assoc()) {
            $fantasy_points = calculateFantasyPoints($stats);
            
            // Insert or update fantasy points
            $upsert_query = "INSERT INTO fantasy_points 
                           (player_id, match_id, points_scored, week_number, month_number, season_year) 
                           VALUES (?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE 
                           points_scored = VALUES(points_scored),
                           updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $conn->prepare($upsert_query);
            $stmt->bind_param("iidiii", 
                $stats['player_id'], 
                $match_id, 
                $fantasy_points,
                $week_number,
                $month_number,
                $season_year
            );
            $stmt->execute();
        }

        // Update leaderboards
        updateLeaderboards($conn, $week_number, $month_number, $season_year);
        
        return true;
    } catch (Exception $e) {
        error_log("Error updating fantasy points: " . $e->getMessage());
        return false;
    }
}

function updateLeaderboards($conn, $week_number, $month_number, $season_year) {
    try {
        // Update weekly leaderboard
        $weekly_query = "INSERT INTO fantasy_leaderboards 
                        (player_id, total_points, week_number, month_number, season_year, leaderboard_type, ranking)
                        SELECT 
                            player_id,
                            SUM(points_scored) as total_points,
                            week_number,
                            month_number,
                            season_year,
                            'weekly' as leaderboard_type,
                            0 as ranking
                        FROM fantasy_points
                        WHERE week_number = ? AND season_year = ?
                        GROUP BY player_id
                        ON DUPLICATE KEY UPDATE
                        total_points = VALUES(total_points),
                        updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $conn->prepare($weekly_query);
        $stmt->bind_param("ii", $week_number, $season_year);
        $stmt->execute();

        // Update monthly leaderboard
        $monthly_query = "INSERT INTO fantasy_leaderboards 
                         (player_id, total_points, week_number, month_number, season_year, leaderboard_type, ranking)
                         SELECT 
                             player_id,
                             SUM(points_scored) as total_points,
                             week_number,
                             month_number,
                             season_year,
                             'monthly' as leaderboard_type,
                             0 as ranking
                         FROM fantasy_points
                         WHERE month_number = ? AND season_year = ?
                         GROUP BY player_id
                         ON DUPLICATE KEY UPDATE
                         total_points = VALUES(total_points),
                         updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $conn->prepare($monthly_query);
        $stmt->bind_param("ii", $month_number, $season_year);
        $stmt->execute();

        // Update rankings for weekly leaderboard
        $update_weekly_rankings = "UPDATE fantasy_leaderboards fl1,
                                 (SELECT id, 
                                         @rank := @rank + 1 as new_rank
                                  FROM fantasy_leaderboards, 
                                       (SELECT @rank := 0) r
                                  WHERE leaderboard_type = 'weekly'
                                  AND week_number = ?
                                  AND season_year = ?
                                  ORDER BY total_points DESC) fl2
                                 SET fl1.ranking = fl2.new_rank
                                 WHERE fl1.id = fl2.id";
        
        $stmt = $conn->prepare($update_weekly_rankings);
        $stmt->bind_param("ii", $week_number, $season_year);
        $stmt->execute();

        // Update rankings for monthly leaderboard
        $update_monthly_rankings = "UPDATE fantasy_leaderboards fl1,
                                  (SELECT id, 
                                          @rank := @rank + 1 as new_rank
                                   FROM fantasy_leaderboards, 
                                        (SELECT @rank := 0) r
                                   WHERE leaderboard_type = 'monthly'
                                   AND month_number = ?
                                   AND season_year = ?
                                   ORDER BY total_points DESC) fl2
                                  SET fl1.ranking = fl2.new_rank
                                  WHERE fl1.id = fl2.id";
        
        $stmt = $conn->prepare($update_monthly_rankings);
        $stmt->bind_param("ii", $month_number, $season_year);
        $stmt->execute();

        return true;
    } catch (Exception $e) {
        error_log("Error updating leaderboards: " . $e->getMessage());
        return false;
    }
}

// If this script is called directly to update fantasy points for a match
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_id'])) {
    $match_id = filter_var($_POST['match_id'], FILTER_VALIDATE_INT);
    if ($match_id) {
        $success = updateFantasyPoints($conn, $match_id);
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid match ID']);
    }
}
?>
