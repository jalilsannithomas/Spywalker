<?php
session_start();
?>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<link href="/Spywalker/assets/css/navbar.css" rel="stylesheet">

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="/Spywalker/index.php">SpyWalker</a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       href="/Spywalker/dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>" 
                       href="/Spywalker/schedule.php">Schedule</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" 
                       href="/Spywalker/messages.php">Messages</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : ''; ?>" 
                       href="/Spywalker/stats.php">Stats</a>
                </li>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Admin
                        </a>
                        <ul class="dropdown-menu admin-dropdown">
                            <li><a class="dropdown-item" href="/Spywalker/admin/manage_roster.php">Manage Roster</a></li>
                            <li><a class="dropdown-item" href="/Spywalker/admin/manage_teams.php">Manage Teams</a></li>
                            <li><a class="dropdown-item" href="/Spywalker/admin/manage_matches.php">Manage Matches</a></li>
                            <li><a class="dropdown-item" href="/Spywalker/admin/manage_users.php">Manage Users</a></li>
                            <li><a class="dropdown-item" href="/Spywalker/admin/fantasy_league.php">Fantasy League</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            
            <form class="d-flex" role="search">
                <input class="search-box" type="search" placeholder="Search..." aria-label="Search">
            </form>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                    <ul class="dropdown-menu admin-dropdown">
                        <li><a class="dropdown-item" href="/Spywalker/profile.php">Profile</a></li>
                        <li><a class="dropdown-item" href="/Spywalker/settings.php">Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/Spywalker/logout.php">Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a class="nav-link" href="/Spywalker/login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
