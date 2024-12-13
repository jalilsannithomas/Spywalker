/Spywalker
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── images/
├── config/
│   └── database.php
├── controllers/
│   └── UserController.php
├── models/
│   └── User.php
├── views/
│   ├── components/
│   │   ├── header.php
│   │   └── footer.php
│   └── pages/
│       └── home.php
├── includes/
│   └── functions.php
├── .htaccess
└── index.php




Users:

- user_id (PK)
- username
- email (unique)
- password (hashed)
- role_id (FK to roles)
- created_at
- updated_at
- status (active/inactive)

Roles:

- role_id (PK)
- name
- created_at
- updated_at

athlete_profiles:

- athlete_profile_id (PK)
- user_id (FK to users)
- first_name
- last_name
- date_of_birth
- height
- weight
- created_at
- updated_at

coach_profiles:
- coach_id (PK)
- user_id (FK to users)
- specialization
- experience_years
- certification
- bio

sports:
- sport_id (PK)
- name
- description
- season_start
- season_end

teams:
- team_id (PK)
- name
- sport_id (FK to sports)
- coach_id (FK to coach_profiles)
- created_at
- updated_at

team_members:
- member_id (PK)
- team_id (FK to teams)
- athlete_id (FK to athlete_profiles)
- join_date
- status

matches:
- match_id (PK)
- sport_id (FK to sports)
- home_team_id (FK to teams)
- away_team_id (FK to teams)
- match_date
- status
- final_score
- highlights

match_stats:
- stat_id (PK)
- match_id (FK to matches)
- athlete_id (FK to athlete_profiles)
- points
- assists
- rebounds
- other_stats_json
- created_at

fantasy_teams:
- fantasy_team_id (PK)
- user_id (FK to users)
- team_name
- points
- created_at

roster:
- roster_id (PK)
- fantasy_team_id (FK to fantasy_teams)
- athlete_id (FK to athlete_profiles)
- active_status
- points

