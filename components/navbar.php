<?php
$config_path = dirname($_SERVER['SCRIPT_FILENAME']);
if (strpos($config_path, '/admin') !== false) {
    require_once dirname($config_path) . '/config/config.php';
} else {
    require_once __DIR__ . '/../config/config.php';
}

$current_page = str_replace($base_url . '/', '', $_SERVER['REQUEST_URI']);
$current_page = explode('?', $current_page)[0]; // Remove query parameters if any
$role = $_SESSION['role'] ?? '';

// Define role-specific paths
$schedule_path = get_url('/team_schedule.php');
$dashboard_path = $role === 'admin' ? get_url('/admin/dashboard.php') : get_url('/dashboard.php');

// Debug
error_log("HTTP Host: " . $_SERVER['HTTP_HOST']);
error_log("Current Role: " . $role);

$stats_path = match($role) {
    'admin' => get_url('/admin/manage_stats.php'),
    'athlete' => get_url('/player_stats.php'),
    default => get_url('/team_stats.php')
};

// Debug the final path
error_log("Generated Stats Path: " . $stats_path);

// Debug line
error_log("Role: " . $role . ", Stats Path: " . $stats_path);

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
        transform: scale(1.05);
    }

    .nav-link {
        color: #D4AF37 !important;
        margin: 0 5px;
        padding: 5px 10px !important;
        border: 2px solid transparent;
        transition: all 0.3s ease;
        font-size: 0.8em;
    }

    .nav-link:hover, .nav-link.active {
        border-color: #D4AF37;
        transform: translateY(-2px);
    }

    .navbar-toggler {
        border-color: #D4AF37 !important;
    }

    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%23D4AF37' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    .dropdown-menu {
        background-color: #241409;
        border: 2px solid #D4AF37;
    }

    .dropdown-item {
        color: #D4AF37 !important;
        font-family: 'Press Start 2P', cursive;
        font-size: 0.8em;
        padding: 10px 20px;
        transition: all 0.3s ease;
    }

    .dropdown-item:hover {
        background-color: #D4AF37;
        color: #241409 !important;
    }

    .navbar-nav .dropdown-menu {
        position: absolute;
    }

    @media (max-width: 991px) {
        .navbar-nav .dropdown-menu {
            position: static;
        }
    }
</style>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo get_url('/index.php'); ?>">Spywalker</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/dashboard.php' ? 'active' : ''; ?>" href="<?php echo $dashboard_path; ?>">
                            <i class="bi bi-speedometer2"></i> <?php echo $current_page == 'dashboard.php' ? '[ DASHBOARD ]' : 'Dashboard'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/team_schedule.php' ? 'active' : ''; ?>" href="<?php echo $schedule_path; ?>">
                            <i class="bi bi-calendar"></i> <?php echo $current_page == 'team_schedule.php' ? '[ SCHEDULE ]' : 'Schedule'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/messages.php' ? 'active' : ''; ?>" href="<?php echo get_url('/messages.php'); ?>">
                            <i class="bi bi-chat"></i> <?php echo $current_page == 'messages.php' ? '[ MESSAGES ]' : 'Messages'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == '/team_stats.php' || $current_page == 'admin/manage_stats.php' || $current_page == 'player_stats.php') ? 'active' : ''; ?>" href="<?php echo $stats_path; ?>">
                            <i class="bi bi-graph-up"></i> <?php echo ($current_page == 'team_stats.php' || $current_page == 'admin/manage_stats.php' || $current_page == 'player_stats.php') ? '[ STATS ]' : 'Stats'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/leaderboards.php' ? 'active' : ''; ?>" href="<?php echo get_url('/leaderboards.php'); ?>">
                            <i class="bi bi-trophy"></i> <?php echo $current_page == 'leaderboards.php' ? '[ FANTASY LEAGUE ]' : 'Fantasy League'; ?>
                        </a>
                    </li>
                    <?php if ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/manage_roster.php' ? 'active' : ''; ?>" href="<?php echo get_url('/admin/manage_roster.php'); ?>">
                            <i class="bi bi-people"></i> <?php echo $current_page == 'manage_roster.php' ? '[ MANAGE ROSTER ]' : 'Manage Roster'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/manage_teams.php' ? 'active' : ''; ?>" href="<?php echo get_url('/admin/manage_teams.php'); ?>">
                            <i class="bi bi-people"></i> <?php echo $current_page == 'manage_teams.php' ? '[ MANAGE TEAMS ]' : 'Manage Teams'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/manage_matches.php' ? 'active' : ''; ?>" href="<?php echo get_url('/admin/manage_matches.php'); ?>">
                            <i class="bi bi-calendar"></i> <?php echo $current_page == 'manage_matches.php' ? '[ MANAGE MATCHES ]' : 'Manage Matches'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == '/manage_users.php' ? 'active' : ''; ?>" href="<?php echo get_url('/admin/manage_users.php'); ?>">
                            <i class="bi bi-people"></i> <?php echo $current_page == 'manage_users.php' ? '[ MANAGE USERS ]' : 'Manage Users'; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item me-3">
                        <a class="nav-link <?php echo $current_page == 'search_users.php' ? 'active' : ''; ?>" href="<?php echo get_url('/search_users.php'); ?>">
                            <i class="bi bi-search"></i> <?php echo $current_page == 'search_users.php' ? '[ SEARCH ]' : 'Search'; ?>
                        </a>
                    </li>
                    <li class="nav-item me-3">
                        <a class="nav-link <?php echo $current_page == 'edit_profile.php' ? 'active' : ''; ?>" href="<?php echo get_url('/edit_profile.php'); ?>">
                            <i class="bi bi-person-circle"></i> <?php echo $current_page == 'edit_profile.php' ? '[ EDIT PROFILE ]' : 'Edit Profile'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo get_url('/logout.php'); ?>">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'login.php' ? 'active' : ''; ?>" href="<?php echo get_url('/login.php'); ?>">Login</a>
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
        dropdown.addEventListener('click', function(e) {
            e.preventDefault();
            var menu = this.nextElementSibling;
            menu.classList.toggle('show');
        });
    });
});
</script>