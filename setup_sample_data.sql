-- Insert sample athletes
INSERT INTO users (username, email, password, role, first_name, last_name) VALUES 
('jalil95', 'jalil@example.com', '$2y$10$YourHashedPasswordHere', 'athlete', 'Jalil', 'Sanni-Thomas'),
('mike87', 'mike@example.com', '$2y$10$YourHashedPasswordHere', 'athlete', 'Michael', 'Jordan'),
('kobe24', 'kobe@example.com', '$2y$10$YourHashedPasswordHere', 'athlete', 'Kobe', 'Bryant'),
('lebron23', 'lebron@example.com', '$2y$10$YourHashedPasswordHere', 'athlete', 'LeBron', 'James');

-- Insert sample coach
INSERT INTO users (username, email, password, role, first_name, last_name) VALUES 
('coach_byron', 'byron@example.com', '$2y$10$YourHashedPasswordHere', 'coach', 'Byron', 'Steele');

-- Update team with coach
UPDATE teams SET coach_id = (SELECT id FROM users WHERE username = 'coach_byron') WHERE name = 'Berekuso Warriors';

-- Add players to team
INSERT INTO team_members (team_id, user_id, jersey_number, position) 
SELECT 
    (SELECT id FROM teams WHERE name = 'Berekuso Warriors'),
    id,
    CASE 
        WHEN username = 'jalil95' THEN 95
        WHEN username = 'mike87' THEN 23
        WHEN username = 'kobe24' THEN 24
        WHEN username = 'lebron23' THEN 6
    END,
    CASE 
        WHEN username = 'jalil95' THEN 'Forward'
        WHEN username = 'mike87' THEN 'Shooting Guard'
        WHEN username = 'kobe24' THEN 'Shooting Guard'
        WHEN username = 'lebron23' THEN 'Small Forward'
    END
FROM users 
WHERE role = 'athlete';
