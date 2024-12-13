Users (1) ----< (Many) Athlete_Profiles
     (1) ----< (Many) Coach_Profiles
     (1) ----< (Many) Fantasy_Teams

Roles (1) ----< (Many) Users

Sports (1) ----< (Many) Teams
       (1) ----< (Many) Matches

Teams (1) ----< (Many) Team_Members
      (1) ----< (Many) Matches (as home_team)
      (1) ----< (Many) Matches (as away_team)

Coach_Profiles (1) ----< (Many) Teams

Athlete_Profiles (1) ----< (Many) Team_Members
                (1) ----< (Many) Player_Stats
                (1) ----< (Many) Fantasy_Roster

Matches (1) ----< (Many) Player_Stats

Fantasy_Teams (1) ----< (Many) Fantasy_Roster