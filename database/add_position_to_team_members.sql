ALTER TABLE team_members
ADD COLUMN position_id INT,
ADD COLUMN jersey_number VARCHAR(10),
ADD FOREIGN KEY (position_id) REFERENCES positions(id);
