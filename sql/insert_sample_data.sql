-- Insert sports
INSERT INTO sports (name, description) VALUES 
('Basketball', 'Basketball is a team sport in which two teams compete to score points'),
('Football', 'American football is a team sport played between two teams'),
('Soccer', 'Soccer is a team sport played between two teams of eleven players'),
('Baseball', 'Baseball is a bat-and-ball sport played between two teams'),
('Volleyball', 'Volleyball is a team sport in which two teams of six players');

-- Insert positions for Basketball
INSERT INTO positions (sport_id, name) VALUES 
(1, 'Point Guard'),
(1, 'Shooting Guard'),
(1, 'Small Forward'),
(1, 'Power Forward'),
(1, 'Center');

-- Insert positions for Football
INSERT INTO positions (sport_id, name) VALUES 
(2, 'Quarterback'),
(2, 'Running Back'),
(2, 'Wide Receiver'),
(2, 'Tight End'),
(2, 'Offensive Line'),
(2, 'Defensive Line'),
(2, 'Linebacker'),
(2, 'Cornerback'),
(2, 'Safety');

-- Insert positions for Soccer
INSERT INTO positions (sport_id, name) VALUES 
(3, 'Goalkeeper'),
(3, 'Defender'),
(3, 'Midfielder'),
(3, 'Forward'),
(3, 'Striker');

-- Insert positions for Baseball
INSERT INTO positions (sport_id, name) VALUES 
(4, 'Pitcher'),
(4, 'Catcher'),
(4, 'First Baseman'),
(4, 'Second Baseman'),
(4, 'Third Baseman'),
(4, 'Shortstop'),
(4, 'Left Fielder'),
(4, 'Center Fielder'),
(4, 'Right Fielder');

-- Insert positions for Volleyball
INSERT INTO positions (sport_id, name) VALUES 
(5, 'Setter'),
(5, 'Outside Hitter'),
(5, 'Middle Blocker'),
(5, 'Opposite Hitter'),
(5, 'Libero');
