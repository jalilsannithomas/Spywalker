-- Insert sports
INSERT INTO sports (name, description) VALUES 
('Basketball', 'A team sport played with a ball and hoop'),
('Soccer', 'A team sport played with a ball and goals'),
('Volleyball', 'A team sport played with a ball over a net');

-- Insert positions for Basketball
INSERT INTO positions (sport_id, name) VALUES
((SELECT id FROM sports WHERE name = 'Basketball'), 'Point Guard'),
((SELECT id FROM sports WHERE name = 'Basketball'), 'Shooting Guard'),
((SELECT id FROM sports WHERE name = 'Basketball'), 'Small Forward'),
((SELECT id FROM sports WHERE name = 'Basketball'), 'Power Forward'),
((SELECT id FROM sports WHERE name = 'Basketball'), 'Center');

-- Insert positions for Soccer
INSERT INTO positions (sport_id, name) VALUES
((SELECT id FROM sports WHERE name = 'Soccer'), 'Goalkeeper'),
((SELECT id FROM sports WHERE name = 'Soccer'), 'Defender'),
((SELECT id FROM sports WHERE name = 'Soccer'), 'Midfielder'),
((SELECT id FROM sports WHERE name = 'Soccer'), 'Forward'),
((SELECT id FROM sports WHERE name = 'Soccer'), 'Striker');

-- Insert positions for Volleyball
INSERT INTO positions (sport_id, name) VALUES
((SELECT id FROM sports WHERE name = 'Volleyball'), 'Setter'),
((SELECT id FROM sports WHERE name = 'Volleyball'), 'Outside Hitter'),
((SELECT id FROM sports WHERE name = 'Volleyball'), 'Middle Blocker'),
((SELECT id FROM sports WHERE name = 'Volleyball'), 'Opposite Hitter'),
((SELECT id FROM sports WHERE name = 'Volleyball'), 'Libero');
