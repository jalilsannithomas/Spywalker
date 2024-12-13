-- Insert athlete profile for Jalil
INSERT INTO athlete_profiles (
    user_id, 
    first_name, 
    last_name, 
    height, 
    weight, 
    sport_id, 
    position_id, 
    jersey_number, 
    years_experience, 
    school_year
) VALUES (
    5,  -- user_id (from the users table)
    'Jalil',  -- first_name
    'Sanni-Thomas',  -- last_name
    72,  -- height (6 feet = 72 inches)
    185,  -- weight
    1,  -- sport_id (1 = Basketball)
    1,  -- position_id (1 = Point Guard)
    5,  -- jersey_number
    4,  -- years_experience
    'Senior'  -- school_year
);
