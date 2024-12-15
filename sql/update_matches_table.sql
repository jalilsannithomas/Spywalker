-- Add venue_id column to matches table if it doesn't exist
ALTER TABLE matches
ADD COLUMN IF NOT EXISTS venue_id INT,
ADD FOREIGN KEY (venue_id) REFERENCES venues(id);
