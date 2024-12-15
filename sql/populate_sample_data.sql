-- Add sample data without affecting existing records

-- Set up variables to store user IDs
SET @coach1_id = NULL;
SET @coach2_id = NULL;
SET @coach3_id = NULL;
SET @coach4_id = NULL;

-- Insert coaches and store their IDs
INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Richard', 'Martinez', 'coach.martinez@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coach', 1);
SET @coach1_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Jennifer', 'Williams', 'coach.williams@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coach', 1);
SET @coach2_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Peter', 'Thompson', 'coach.thompson@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coach', 1);
SET @coach3_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Maria', 'Rodriguez', 'coach.rodriguez@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coach', 1);
SET @coach4_id = LAST_INSERT_ID();

-- Insert coach profiles using the stored IDs
INSERT INTO coach_profiles (user_id, sport_id, years_of_experience, created_at, updated_at) VALUES
(@coach1_id, 1, 10, NOW(), NOW()),  -- Football coach
(@coach2_id, 2, 8, NOW(), NOW()),   -- Basketball coach
(@coach3_id, 3, 12, NOW(), NOW()),  -- Baseball coach
(@coach4_id, 4, 15, NOW(), NOW());  -- Soccer coach

-- Set up variables for athlete IDs
SET @athlete1_id = NULL;
SET @athlete2_id = NULL;
SET @athlete3_id = NULL;
SET @athlete4_id = NULL;
SET @athlete5_id = NULL;
SET @athlete6_id = NULL;
SET @athlete7_id = NULL;
SET @athlete8_id = NULL;
SET @athlete9_id = NULL;
SET @athlete10_id = NULL;
SET @athlete11_id = NULL;
SET @athlete12_id = NULL;

-- Insert athletes and store their IDs
-- Football Athletes
INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Tom', 'Wilson', 'athlete.wilson@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete1_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('James', 'Anderson', 'athlete.anderson@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete2_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('David', 'Martinez', 'athlete.martinez@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete3_id = LAST_INSERT_ID();

-- Basketball Athletes
INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Marcus', 'Johnson', 'athlete.johnson@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete4_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Kevin', 'Thompson', 'athlete.thompson@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete5_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Chris', 'Lee', 'athlete.lee@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete6_id = LAST_INSERT_ID();

-- Baseball Athletes
INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Mike', 'Rodriguez', 'athlete.rodriguez@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete7_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Ryan', 'Garcia', 'athlete.garcia@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete8_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Daniel', 'White', 'athlete.white@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete9_id = LAST_INSERT_ID();

-- Soccer Athletes
INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Luis', 'Hernandez', 'athlete.hernandez@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete10_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Carlos', 'Silva', 'athlete.silva@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete11_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Juan', 'Torres', 'athlete.torres@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete', 1);
SET @athlete12_id = LAST_INSERT_ID();

-- Insert athlete performance metrics
INSERT INTO athlete_performance_metrics (athlete_id, base_rating, achievement_points, games_played) VALUES
-- Football Athletes
(@athlete1_id, 0.85, 1200, 45),
(@athlete2_id, 0.78, 950, 38),
(@athlete3_id, 0.82, 1100, 42),

-- Basketball Athletes
(@athlete4_id, 0.88, 1500, 65),
(@athlete5_id, 0.92, 1800, 72),
(@athlete6_id, 0.86, 1400, 68),

-- Baseball Athletes
(@athlete7_id, 0.79, 1000, 85),
(@athlete8_id, 0.83, 1250, 92),
(@athlete9_id, 0.81, 1150, 88),

-- Soccer Athletes
(@athlete10_id, 0.87, 1600, 52),
(@athlete11_id, 0.84, 1350, 48),
(@athlete12_id, 0.89, 1700, 55);

-- Set up variables for team IDs
SET @team1_id = NULL;
SET @team2_id = NULL;
SET @team3_id = NULL;
SET @team4_id = NULL;

-- Insert teams and store their IDs
INSERT INTO teams (name, sport_id, coach_id, founded_date, description) VALUES
('Eagles', 1, @coach1_id, '2020-01-01', 'Professional football team known for their aggressive offense');
SET @team1_id = LAST_INSERT_ID();

INSERT INTO teams (name, sport_id, coach_id, founded_date, description) VALUES
('Warriors', 2, @coach2_id, '2020-02-01', 'Championship basketball team with strong defensive strategy');
SET @team2_id = LAST_INSERT_ID();

INSERT INTO teams (name, sport_id, coach_id, founded_date, description) VALUES
('Sharks', 3, @coach3_id, '2020-03-01', 'Rising baseball team with promising young talent');
SET @team3_id = LAST_INSERT_ID();

INSERT INTO teams (name, sport_id, coach_id, founded_date, description) VALUES
('United', 4, @coach4_id, '2020-04-01', 'Elite soccer club with international players');
SET @team4_id = LAST_INSERT_ID();

-- Insert team athletes
INSERT INTO team_athletes (team_id, athlete_id, jersey_number, position_id) VALUES
-- Football Team
(@team1_id, @athlete1_id, 12, 1),  -- Quarterback
(@team1_id, @athlete2_id, 84, 2),  -- Wide Receiver
(@team1_id, @athlete3_id, 21, 3),  -- Running Back

-- Basketball Team
(@team2_id, @athlete4_id, 23, 4),  -- Point Guard
(@team2_id, @athlete5_id, 34, 5),  -- Center
(@team2_id, @athlete6_id, 11, 6),  -- Small Forward

-- Baseball Team
(@team3_id, @athlete7_id, 45, 7),  -- Pitcher
(@team3_id, @athlete8_id, 2, 8),   -- Catcher
(@team3_id, @athlete9_id, 25, 9),  -- First Base

-- Soccer Team
(@team4_id, @athlete10_id, 10, 10), -- Forward
(@team4_id, @athlete11_id, 8, 11),  -- Midfielder
(@team4_id, @athlete12_id, 4, 12);  -- Defender

-- Set up variables for fan IDs
SET @fan1_id = NULL;
SET @fan2_id = NULL;
SET @fan3_id = NULL;
SET @fan4_id = NULL;

-- Insert fans and store their IDs
INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Emily', 'Clark', 'fan.clark@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fan', 1);
SET @fan1_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Robert', 'Taylor', 'fan.taylor@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fan', 1);
SET @fan2_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('Lisa', 'Moore', 'fan.moore@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fan', 1);
SET @fan3_id = LAST_INSERT_ID();

INSERT INTO users (first_name, last_name, email, password, role, is_active) VALUES
('William', 'Miller', 'fan.miller@spywalker.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fan', 1);
SET @fan4_id = LAST_INSERT_ID();

-- Insert fan profiles
INSERT INTO fan_profiles (user_id, favorite_team_id, membership_level) VALUES
(@fan1_id, @team1_id, 'gold'),
(@fan2_id, @team2_id, 'silver'),
(@fan3_id, @team3_id, 'bronze'),
(@fan4_id, @team4_id, 'gold');

-- Add fan follows
INSERT INTO fan_follows (fan_id, athlete_id) VALUES
(@fan1_id, @athlete1_id),  -- Emily follows Tom Wilson
(@fan1_id, @athlete2_id),  -- Emily follows James Anderson
(@fan2_id, @athlete4_id),  -- Robert follows Marcus Johnson
(@fan2_id, @athlete5_id),  -- Robert follows Kevin Thompson
(@fan3_id, @athlete7_id),  -- Lisa follows Mike Rodriguez
(@fan4_id, @athlete10_id); -- William follows Luis Hernandez
