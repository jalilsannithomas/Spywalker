-- Add sport_id column to users table
ALTER TABLE users ADD COLUMN sport_id INT NULL;
ALTER TABLE users ADD CONSTRAINT fk_user_sport FOREIGN KEY (sport_id) REFERENCES sports(id);
