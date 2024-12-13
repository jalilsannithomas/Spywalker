-- Make sure sports exist
INSERT IGNORE INTO sports (id, name, description) VALUES 
(1, 'Basketball', 'Basketball is a team sport in which two teams compete to score points');

-- Make sure coach exists
INSERT IGNORE INTO users (id, username, email, password, role, first_name, last_name) VALUES 
(6, 'coach_byron', 'byron@example.com', '$2y$10$YourHashedPasswordHere', 'coach', 'Byron', 'Steele');

-- Create the team and assign the coach
INSERT IGNORE INTO teams (name, sport_id, coach_id) VALUES 
('Berekuso Warriors', 1, 6);

-- Update any existing team to ensure coach assignment
UPDATE teams 
SET coach_id = 6 
WHERE name = 'Berekuso Warriors' AND (coach_id IS NULL OR coach_id != 6);
