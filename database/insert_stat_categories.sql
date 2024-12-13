-- Insert default stat categories for different sports

-- Basketball Stats
INSERT INTO stat_categories (sport_id, name, description, data_type, abbreviation, points_multiplier) VALUES
(1, 'Points', 'Points scored', 'integer', 'PTS', 1.0),
(1, 'Rebounds', 'Total rebounds', 'integer', 'REB', 1.2),
(1, 'Assists', 'Assists made', 'integer', 'AST', 1.5),
(1, 'Steals', 'Steals', 'integer', 'STL', 2.0),
(1, 'Blocks', 'Shots blocked', 'integer', 'BLK', 2.0),
(1, 'Turnovers', 'Turnovers committed', 'integer', 'TO', -1.0),
(1, 'Field Goal Percentage', 'Field goal percentage', 'percentage', 'FG%', 0),
(1, 'Three Point Percentage', 'Three point percentage', 'percentage', '3P%', 0),
(1, 'Free Throw Percentage', 'Free throw percentage', 'percentage', 'FT%', 0),
(1, 'Minutes Played', 'Minutes played', 'time', 'MIN', 0);

-- Football Stats
INSERT INTO stat_categories (sport_id, name, description, data_type, abbreviation, points_multiplier) VALUES
(2, 'Touchdowns', 'Touchdowns scored', 'integer', 'TD', 6.0),
(2, 'Passing Yards', 'Passing yards gained', 'integer', 'PYD', 0.04),
(2, 'Rushing Yards', 'Rushing yards gained', 'integer', 'RYD', 0.1),
(2, 'Receptions', 'Passes caught', 'integer', 'REC', 1.0),
(2, 'Field Goals', 'Field goals made', 'integer', 'FG', 3.0),
(2, 'Interceptions Thrown', 'Interceptions thrown', 'integer', 'INT', -2.0),
(2, 'Fumbles Lost', 'Fumbles lost', 'integer', 'FUM', -2.0),
(2, 'Sacks', 'Times sacked', 'integer', 'SACK', -1.0);

-- Baseball Stats
INSERT INTO stat_categories (sport_id, name, description, data_type, abbreviation, points_multiplier) VALUES
(3, 'Hits', 'Number of hits', 'integer', 'H', 1.0),
(3, 'Home Runs', 'Home runs hit', 'integer', 'HR', 4.0),
(3, 'Runs Batted In', 'Runs batted in', 'integer', 'RBI', 1.0),
(3, 'Runs Scored', 'Runs scored', 'integer', 'R', 1.0),
(3, 'Stolen Bases', 'Bases stolen', 'integer', 'SB', 2.0),
(3, 'Batting Average', 'Batting average', 'percentage', 'AVG', 0),
(3, 'Earned Run Average', 'Earned run average', 'decimal', 'ERA', 0),
(3, 'Strikeouts', 'Batters struck out', 'integer', 'K', 1.0);

-- Soccer Stats
INSERT INTO stat_categories (sport_id, name, description, data_type, abbreviation, points_multiplier) VALUES
(4, 'Goals', 'Goals scored', 'integer', 'G', 4.0),
(4, 'Assists', 'Assists made', 'integer', 'A', 3.0),
(4, 'Clean Sheets', 'Games without conceding a goal', 'integer', 'CS', 4.0),
(4, 'Yellow Cards', 'Yellow cards received', 'integer', 'YC', -1.0),
(4, 'Red Cards', 'Red cards received', 'integer', 'RC', -3.0),
(4, 'Saves', 'Saves made by goalkeeper', 'integer', 'SV', 0.5),
(4, 'Minutes Played', 'Minutes played', 'time', 'MIN', 0),
(4, 'Pass Completion Rate', 'Percentage of successful passes', 'percentage', 'PASS%', 0);

-- Volleyball Stats
INSERT INTO stat_categories (sport_id, name, description, data_type, abbreviation, points_multiplier) VALUES
(5, 'Kills', 'Successful attacks', 'integer', 'K', 1.0),
(5, 'Blocks', 'Successful blocks', 'integer', 'B', 1.5),
(5, 'Aces', 'Service aces', 'integer', 'A', 2.0),
(5, 'Digs', 'Successful defensive plays', 'integer', 'D', 1.0),
(5, 'Assists', 'Setting assists', 'integer', 'AST', 0.5),
(5, 'Hitting Percentage', 'Attack success rate', 'percentage', 'HIT%', 0);

-- Tennis Stats
INSERT INTO stat_categories (sport_id, name, description, data_type, abbreviation, points_multiplier) VALUES
(6, 'Aces', 'Service aces', 'integer', 'ACE', 2.0),
(6, 'Double Faults', 'Double faults', 'integer', 'DF', -1.0),
(6, 'First Serve Percentage', 'First serve success rate', 'percentage', '1ST%', 0),
(6, 'Break Points Saved', 'Break points saved', 'integer', 'BPS', 1.0),
(6, 'Winners', 'Winning shots', 'integer', 'W', 1.0),
(6, 'Unforced Errors', 'Unforced errors', 'integer', 'UE', -1.0);

-- Hockey Stats
INSERT INTO stat_categories (sport_id, name, description, data_type, abbreviation, points_multiplier) VALUES
(7, 'Goals', 'Goals scored', 'integer', 'G', 3.0),
(7, 'Assists', 'Assists made', 'integer', 'A', 2.0),
(7, 'Plus/Minus', 'Plus/minus rating', 'integer', '+/-', 1.0),
(7, 'Penalty Minutes', 'Time spent in penalty box', 'time', 'PIM', -0.5),
(7, 'Shots on Goal', 'Shots on goal', 'integer', 'SOG', 0.5),
(7, 'Save Percentage', 'Goalie save percentage', 'percentage', 'SV%', 0),
(7, 'Power Play Goals', 'Power play goals', 'integer', 'PPG', 2.0),
(7, 'Short Handed Goals', 'Short handed goals', 'integer', 'SHG', 4.0);
