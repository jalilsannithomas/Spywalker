<?php
$current_page = str_replace('/Spywalker/', '', $_SERVER['REQUEST_URI']);
$current_page = explode('?', $current_page)[0]; // Remove query parameters if any
$role = $_SESSION['role'] ?? '';

// Define role-specific paths with leading slash
$schedule_path = '/Spywalker/team_schedule.php';
$dashboard_path = $role === 'admin' ? '/Spywalker/admin/dashboard.php' : '/Spywalker/dashboard.php';
$stats_path = $role === 'admin' ? '/Spywalker/admin/manage_stats.php' : '/Spywalker/team_stats.php';
?>

<style>
    .navbar {
        background-color: #241409 !important;
        border-bottom: 4px solid #D4AF37;
        font-family: 'Press Start 2P', cursive;
        padding: 1rem;
        margin-bottom: 20px;
    }

    .navbar-brand {
        color: #D4AF37 !important;
        font-size: 20px;
        text-shadow: 2px 2px #000;
        letter-spacing: 2px;
        padding: 5px 10px;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .navbar-brand:hover {
        border-color: #D4AF37;
        transform: translateY(-2px);
    }

    .nav-link {
        color: #D4AF37 !important;
        font-size: 12px;
        padding: 8px 16px !important;
        margin: 0 4px;
        border: 2px solid transparent;
        transition: all 0.3s ease;
        position: relative;
    }

    .nav-link:hover, .nav-link.active {
        border-color: #D4AF37;
        transform: translateY(-2px);
        background-color: rgba(212, 175, 55, 0.1);
    }

    .navbar-toggler {
        border: 2px solid #D4AF37 !important;
        padding: 4px 8px;
    }

    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba(212, 175, 55, 1)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    .dropdown-menu {
        background-color: #241409;
        border: 2px solid #D4AF37;
        border-radius: 0;
        margin-top: 8px;
        box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.5);
        font-family: 'Press Start 2P', cursive;
        font-size: 10px;
    }

    .dropdown-item {
        color: #D4AF37 !important;
        padding: 10px 20px;
        transition: all 0.3s ease;
    }

    .dropdown-item:hover {
        background-color: rgba(212, 175, 55, 0.1);
        transform: translateX(4px);
    }

    .dropdown-divider {
        border-top: 2px solid #D4AF37;
        margin: 0.5rem 0;
    }

    /* Active link style */
    .nav-link.active {
        background-color: rgba(212, 175, 55, 0.2);
        border-color: #D4AF37;
    }

    /* Search icon alignment */
    .bi-search {
        font-size: 14px;
        margin-right: 8px;
    }
</style>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="/Spywalker/index.php">Spywalker</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == $dashboard_path ? 'active' : ''; ?>" href="<?php echo $dashboard_path; ?>">
                            <?php echo $current_page == $dashboard_path ? '[ DASHBOARD ]' : 'Dashboard'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == $schedule_path ? 'active' : ''; ?>" href="<?php echo $schedule_path; ?>">
                            <?php echo $current_page == $schedule_path ? '[ SCHEDULE ]' : 'Schedule'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/Spywalker/messages.php' ? 'active' : ''; ?>" href="/Spywalker/messages.php">
                            <?php echo $current_page == '/Spywalker/messages.php' ? '[ MESSAGES ]' : 'Messages'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == $stats_path ? 'active' : ''; ?>" href="<?php echo $stats_path; ?>">
                            <?php echo $current_page == $stats_path ? '[ STATS ]' : 'Stats'; ?>
                        </a>
                    </li>
                    <?php if ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/Spywalker/admin/manage_roster.php' ? 'active' : ''; ?>" href="/Spywalker/admin/manage_roster.php">
                            <?php echo $current_page == '/Spywalker/admin/manage_roster.php' ? '[ MANAGE ROSTER ]' : 'Manage Roster'; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($role === 'coach'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/Spywalker/coach/manage_roster.php' ? 'active' : ''; ?>" href="/Spywalker/coach/manage_roster.php">
                            <?php echo $current_page == '/Spywalker/coach/manage_roster.php' ? '[ MANAGE ROSTER ]' : 'Manage Roster'; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/Spywalker/admin/manage_teams.php' ? 'active' : ''; ?>" href="/Spywalker/admin/manage_teams.php">
                            <?php echo $current_page == '/Spywalker/admin/manage_teams.php' ? '[ MANAGE TEAMS ]' : 'Manage Teams'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/Spywalker/admin/manage_matches.php' ? 'active' : ''; ?>" href="/Spywalker/admin/manage_matches.php">
                            <?php echo $current_page == '/Spywalker/admin/manage_matches.php' ? '[ MANAGE MATCHES ]' : 'Manage Matches'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/Spywalker/admin/manage_users.php' ? 'active' : ''; ?>" href="/Spywalker/admin/manage_users.php">
                            <?php echo $current_page == '/Spywalker/admin/manage_users.php' ? '[ MANAGE USERS ]' : 'Manage Users'; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/Spywalker/leaderboards.php' ? 'active' : ''; ?>" href="/Spywalker/leaderboards.php">
                            <?php echo $current_page == '/Spywalker/leaderboards.php' ? '[ FANTASY LEAGUE ]' : 'Fantasy League'; ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item me-3">
                        <a class="nav-link <?php echo $current_page == '/Spywalker/search_users.php' ? 'active' : ''; ?>" href="/Spywalker/search_users.php">
                            <i class="bi bi-search"></i> <?php echo $current_page == '/Spywalker/search_users.php' ? '[ SEARCH ]' : 'Search'; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo isset($_SESSION['first_name']) ? htmlspecialchars(strtoupper($_SESSION['first_name'])) : 'ACCOUNT'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="/Spywalker/edit_profile.php">Edit Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/Spywalker/logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(function(dropdown) {
        new bootstrap.Dropdown(dropdown);
    });
});
</script>