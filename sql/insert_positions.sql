-- First, let's get the sport IDs
SET @basketball_id = (SELECT id FROM sports WHERE name = 'Basketball');
SET @football_id = (SELECT id FROM sports WHERE name = 'Football');
SET @soccer_id = (SELECT id FROM sports WHERE name = 'Soccer');
SET @baseball_id = (SELECT id FROM sports WHERE name = 'Baseball');
SET @volleyball_id = (SELECT id FROM sports WHERE name = 'Volleyball');

-- Insert positions for Basketball
INSERT INTO positions (sport_id, name) VALUES 
(@basketball_id, 'Point Guard'),
(@basketball_id, 'Shooting Guard'),
(@basketball_id, 'Small Forward'),
(@basketball_id, 'Power Forward'),
(@basketball_id, 'Center');

-- Insert positions for Football
INSERT INTO positions (sport_id, name) VALUES 
(@football_id, 'Quarterback'),
(@football_id, 'Running Back'),
(@football_id, 'Wide Receiver'),
(@football_id, 'Tight End'),
(@football_id, 'Offensive Line'),
(@football_id, 'Defensive Line'),
(@football_id, 'Linebacker'),
(@football_id, 'Cornerback'),
(@football_id, 'Safety');

-- Insert positions for Soccer
INSERT INTO positions (sport_id, name) VALUES 
(@soccer_id, 'Goalkeeper'),
(@soccer_id, 'Defender'),
(@soccer_id, 'Midfielder'),
(@soccer_id, 'Forward'),
(@soccer_id, 'Striker');

-- Insert positions for Baseball
INSERT INTO positions (sport_id, name) VALUES 
(@baseball_id, 'Pitcher'),
(@baseball_id, 'Catcher'),
(@baseball_id, 'First Baseman'),
(@baseball_id, 'Second Baseman'),
(@baseball_id, 'Third Baseman'),
(@baseball_id, 'Shortstop'),
(@baseball_id, 'Left Fielder'),
(@baseball_id, 'Center Fielder'),
(@baseball_id, 'Right Fielder');

-- Insert positions for Volleyball
INSERT INTO positions (sport_id, name) VALUES 
(@volleyball_id, 'Setter'),
(@volleyball_id, 'Outside Hitter'),
(@volleyball_id, 'Middle Blocker'),
(@volleyball_id, 'Opposite Hitter'),
(@volleyball_id, 'Libero');
