<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Debug session information
error_log("Session data: " . print_r($_SESSION, true));
error_log("Current user role: " . ($_SESSION['role'] ?? 'not set'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session - redirecting to login");
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
error_log("User ID: $user_id, Role: $role");

// Event type emojis mapping
$event_emojis = [
    'match' => '⚽',
    'training' => '🏃',
    'tournament' => '🏆',
    'meeting' => '👥',
    'social' => '🎉',
    'other' => '📅'
];

// Get current month and year from URL, default to current date
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle month/year transitions
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));

// Calculate previous and next month/year
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get team events
if ($role === 'athlete') {
    error_log("Fetching events for athlete (User ID: $user_id) from $start_date to $end_date");
    
    // Athletes see events for their teams and personal events
    $sql = "SELECT te.id, te.team_id, te.event_date, te.title, te.event_time, 
                   te.location, te.event_type, te.description, 
                   t.name as team_name, 'team' as event_source
            FROM team_events te
            INNER JOIN teams t ON t.id = te.team_id
            INNER JOIN team_players tp ON tp.team_id = t.id
            INNER JOIN athlete_profiles ap ON ap.id = tp.athlete_id
            WHERE ap.user_id = ? AND te.event_date BETWEEN ? AND ?
            UNION
            SELECT e.id, NULL as team_id, e.event_date, e.event_text as title, NULL as event_time, 
                   NULL as location, 'personal' as event_type, NULL as description, 
                   NULL as team_name, 'personal' as event_source
            FROM events e 
            WHERE e.user_id = ? AND e.event_date BETWEEN ? AND ?
            ORDER BY event_date, event_time";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $user_id, $start_date, $end_date, $user_id, $start_date, $end_date);
} elseif ($role === 'coach') {
    // Coaches see events for teams they coach
    $sql = "SELECT te.*, t.name as team_name, 'team' as event_source
            FROM team_events te
            INNER JOIN teams t ON t.id = te.team_id
            WHERE t.coach_id = ? 
            AND te.event_date BETWEEN ? AND ?
            ORDER BY te.event_date, te.event_time";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
} else {
    // Fans see events for teams of athletes they follow
    $check_athletes_sql = "SELECT COUNT(*) as athlete_count FROM fan_followed_athletes WHERE fan_id = ?";
    $check_stmt = $conn->prepare($check_athletes_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $athlete_count = $check_stmt->get_result()->fetch_assoc()['athlete_count'];
    
    if ($athlete_count === 0) {
        $events = [];
    } else {
        $sql = "SELECT DISTINCT te.*, t.name as team_name, 'team' as event_source,
                CONCAT(ap.first_name, ' ', ap.last_name) as athlete_name
                FROM team_events te
                INNER JOIN teams t ON t.id = te.team_id
                INNER JOIN team_players tp ON tp.team_id = t.id
                INNER JOIN athlete_profiles ap ON ap.id = tp.athlete_id
                INNER JOIN fan_followed_athletes ffa ON ffa.athlete_id = ap.id
                WHERE ffa.fan_id = ? AND te.event_date BETWEEN ? AND ?
                ORDER BY te.event_date, te.event_time";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    }
}

$events = [];
if (isset($stmt)) {
    error_log("Executing events query");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = $row['event_date'];
        if (!isset($events[$date])) {
            $events[$date] = [];
        }
        error_log("Found event for date $date: " . print_r($row, true));
        $events[$date][] = $row;
    }
}

// Calendar generation logic
$first_day = date('N', strtotime($start_date)) - 1;
$days_in_month = date('t', strtotime($start_date));
$weeks = ceil(($first_day + $days_in_month) / 7);
$current_day = 1;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Team Schedule - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/team-stats.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #2C1810;
            font-family: 'Press Start 2P', monospace;
        }

        .calendar {
            background: #241409;
            border: 4px solid #D4AF37;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            padding: 20px;
            margin-top: 20px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: #D4AF37;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 2px solid #D4AF37;
            background-color: #3C2A20;
            border-radius: 4px;
            padding: 8px;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .calendar-day:hover {
            background-color: #4A3828;
            transform: translateY(-2px);
        }

        .day-number {
            font-size: 0.8rem;
            color: #D4AF37;
            margin-bottom: 4px;
        }

        .marker-text {
            font-family: 'Press Start 2P', monospace;
            color: #D4AF37;
            font-size: 0.7em;
            line-height: 1.3;
            word-break: break-word;
            margin-top: 4px;
            position: relative;
            cursor: pointer;
            padding-right: 20px;
        }
        
        .marker-text:hover {
            color: #FFD700;
        }

        .header-container {
            margin-bottom: 30px;
        }

        .header-container h1 {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            text-transform: uppercase;
            text-shadow: 2px 2px #000;
            margin-bottom: 20px;
        }

        .modal-content {
            background-color: #241409;
            border: 4px solid #D4AF37;
        }

        .modal-header {
            border-bottom: 2px solid #D4AF37;
            color: #D4AF37;
        }

        .modal-title {
            font-family: 'Press Start 2P', monospace;
            font-size: 1rem;
        }

        .modal-body {
            color: #D4AF37;
        }

        .btn-primary {
            background-color: #D4AF37;
            border-color: #D4AF37;
            color: #241409;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8rem;
            padding: 8px 16px;
        }

        .btn-primary:hover {
            background-color: #FFD700;
            border-color: #FFD700;
            transform: translateY(-2px);
        }

        .form-control {
            background-color: #3C2A20;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8rem;
        }

        .form-control:focus {
            background-color: #4A3828;
            border-color: #FFD700;
            color: #D4AF37;
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
        }

        .month-nav {
            color: #D4AF37;
            text-decoration: none;
            font-size: 1.2rem;
            padding: 5px 10px;
        }

        .month-nav:hover {
            color: #FFD700;
            text-decoration: none;
        }
        
        .current-month {
            font-family: 'Press Start 2P', monospace;
            font-size: 1.2rem;
            color: #D4AF37;
        }

        .weekday-header {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.7rem;
            text-align: center;
            padding: 10px 0;
            border-bottom: 2px solid #D4AF37;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="header-container text-center">
            <h1 class="page-title">TEAM SCHEDULE</h1>
        </div>
        <?php if ($role === 'coach' || $role === 'admin'): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newEventModal">
            <i class="bi bi-plus-circle-fill"></i> Create Event
        </button>
        <?php endif; ?>
        
        <div class="calendar">
            <div class="calendar-header">
                <h2 class="month-year"><?php echo date('F Y', strtotime($start_date)); ?></h2>
                <div class="calendar-nav">
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="nav-btn month-nav">&lt;</a>
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="nav-btn month-nav">&gt;</a>
                </div>
            </div>
            
            <?php if ($role === 'fan' && empty($events) && !isset($sql)): ?>
                <div class="alert alert-info text-center my-4">
                    <i class="bi bi-info-circle me-2"></i>
                    You haven't followed any athletes yet. 
                    <a href="follow_athletes.php" class="alert-link">Click here to follow athletes</a> and see their schedules!
                </div>
            <?php endif; ?>
            
            <div class="calendar-grid mb-3">
                <div class="weekday-header">Sun</div>
                <div class="weekday-header">Mon</div>
                <div class="weekday-header">Tue</div>
                <div class="weekday-header">Wed</div>
                <div class="weekday-header">Thu</div>
                <div class="weekday-header">Fri</div>
                <div class="weekday-header">Sat</div>
                
                <?php
                // Add empty cells for days before the first day of the month
                for ($i = 0; $i < $first_day; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                
                // Add cells for each day of the month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $is_today = $date === date('Y-m-d');
                    $has_events = isset($events[$date]);
                    
                    $classes = 'calendar-day';
                    if ($is_today) $classes .= ' today';
                    if ($has_events) $classes .= ' has-events';
                    
                    echo '<div class="' . $classes . '" data-date="' . $date . '">';
                    echo '<div class="day-number">' . $day . '</div>';
                    
                    if ($has_events) {
                        foreach ($events[$date] as $event) {
                            $display_text = htmlspecialchars($event['title']);
                            $marker_class = ($event['event_source'] === 'personal') ? 'marker-text red-marker' : 'marker-text';
                            echo '<div class="' . $marker_class . '" data-event-id="' . $event['id'] . '">' . $display_text . '</div>';
                            error_log("Displaying event: " . print_r($event, true));
                        }
                    }
                    
                    if ($role === 'athlete') {
                        echo '<div class="event-input-container" style="display: none;">';
                        echo '<div class="event-input-guide">Type your event and press Enter</div>';
                        echo '<textarea class="quick-event-input"></textarea>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarDays = document.querySelectorAll('.calendar-day:not(.empty)');
        
        async function deleteEvent(eventId) {
            try {
                const formData = new FormData();
                formData.append('event_id', eventId);
                
                const response = await fetch('handlers/delete_event.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    // Remove the event marker from the UI
                    const eventElement = document.querySelector(`[data-event-id="${eventId}"]`);
                    if (eventElement) {
                        eventElement.remove();
                    }
                } else {
                    alert(result.error || 'Failed to delete event');
                }
            } catch (error) {
                console.error('Error deleting event:', error);
                alert('Failed to delete event. Please try again.');
            }
        }
        
        calendarDays.forEach(day => {
            day.addEventListener('click', function(e) {
                // First remove any existing active input containers
                document.querySelectorAll('.event-input-container').forEach(container => {
                    container.style.display = 'none';
                });

                // Check if clicking on a marker text (existing event)
                if (e.target.classList.contains('marker-text')) {
                    if (confirm('Are you sure you want to delete this event?')) {
                        deleteEvent(e.target.dataset.eventId);
                    }
                    return;
                }

                // Show input container for this day
                const inputContainer = this.querySelector('.event-input-container');
                if (inputContainer) {
                    inputContainer.style.display = 'block';
                    const textarea = inputContainer.querySelector('.quick-event-input');
                    textarea.value = ''; // Clear any existing input
                    textarea.focus();
                }
            });

            // Handle quick event input
            const textarea = day.querySelector('.quick-event-input');
            if (textarea) {
                textarea.addEventListener('keypress', async function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const text = this.value.trim();
                        if (!text) return;

                        const date = day.dataset.date;
                        try {
                            const response = await fetch('handlers/save_event.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    personal: true,
                                    date: date,
                                    text: text
                                })
                            });
                            
                            const result = await response.json();
                            if (result.success) {
                                // Add the new event marker
                                const markerText = document.createElement('div');
                                markerText.className = 'marker-text red-marker';
                                markerText.dataset.eventId = result.eventId;
                                markerText.textContent = text;
                                day.appendChild(markerText);
                                
                                // Hide the input container
                                this.closest('.event-input-container').style.display = 'none';
                            } else {
                                console.error('Server error:', result);
                                alert(result.message || 'Failed to save event');
                            }
                        } catch (error) {
                            console.error('Network error:', error);
                            alert('Failed to save event: Network error');
                        }
                    }
                });
            }
        });
        
        // Initialize Bootstrap components
        document.addEventListener('DOMContentLoaded', function() {
            // Enable all modals
            var modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                new bootstrap.Modal(modal);
            });
        });
    });
    </script>
    
    <!-- New Event Modal -->
    <div class="modal fade" id="newEventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="newEventForm">
                    <div class="modal-body">
                        <?php if ($role === 'coach'): ?>
                        <div class="mb-3">
                            <label for="team_id" class="form-label">Team</label>
                            <select class="form-select" id="team_id" name="team_id" required>
                                <?php
                                $teams_sql = "SELECT id, name FROM teams WHERE coach_id = ?";
                                $teams_stmt = $conn->prepare($teams_sql);
                                $teams_stmt->bind_param("i", $user_id);
                                $teams_stmt->execute();
                                $teams_result = $teams_stmt->get_result();
                                while ($team = $teams_result->fetch_assoc()) {
                                    echo "<option value='" . $team['id'] . "'>" . htmlspecialchars($team['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="event_title" class="form-label">Event Title</label>
                            <input type="text" class="form-control" id="event_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="event_type" class="form-label">Event Type</label>
                            <select class="form-select" id="event_type" name="event_type" required>
                                <option value="match">⚽ Match</option>
                                <option value="training">🏃 Training</option>
                                <option value="tournament">🏆 Tournament</option>
                                <option value="meeting">👥 Meeting</option>
                                <option value="social">🎉 Social Event</option>
                                <option value="other">📅 Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="event_date" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="event_time" class="form-label">Time</label>
                            <input type="time" class="form-control" id="event_time" name="time" required>
                        </div>
                        <div class="mb-3">
                            <label for="event_location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="event_location" name="location" required>
                        </div>
                        <div class="mb-3">
                            <label for="event_description" class="form-label">Description</label>
                            <textarea class="form-control" id="event_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form submission handler
        const newEventForm = document.getElementById('newEventForm');
        if (newEventForm) {
            newEventForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const eventData = {
                    team_id: formData.get('team_id'),
                    title: formData.get('title'),
                    event_type: formData.get('event_type'),
                    event_date: formData.get('date'),
                    event_time: formData.get('time'),
                    location: formData.get('location'),
                    description: formData.get('description') || ''
                };

                try {
                    const response = await fetch('handlers/add_event.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(eventData)
                    });

                    const result = await response.json();
                    if (result.success) {
                        // Refresh the page to show the new event
                        window.location.reload();
                    } else {
                        alert(result.message || 'Failed to add event');
                    }
                } catch (error) {
                    console.error('Error adding event:', error);
                    alert('Failed to add event. Please try again.');
                }
            });
        }
    });
    </script>
    
    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventDetailsTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="eventDetailsType" class="mb-3"></div>
                    <p><strong>Time:</strong> <span id="eventDetailsTime"></span></p>
                    <p><strong>Location:</strong> <span id="eventDetailsLocation"></span></p>
                    <p><strong>Description:</strong> <span id="eventDetailsDescription"></span></p>
                    <p class="text-muted"><small>Created by: <span id="eventDetailsCreator"></span></small></p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('newEventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('handlers/add_event.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to add event. Please try again.');
        });
    });

    // Event Details Modal
    const eventDetailsModal = document.getElementById('eventDetailsModal');
    eventDetailsModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const title = button.dataset.eventTitle;
        const time = button.dataset.eventTime;
        const location = button.dataset.eventLocation;
        const description = button.dataset.eventDescription;
        const creator = button.dataset.eventCreator;
        const type = button.dataset.eventType;
        
        // Get emoji for event type
        const eventEmojis = {
            'match': '⚽',
            'training': '🏃',
            'tournament': '🏆',
            'meeting': '👥',
            'social': '🎉',
            'other': '📅'
        };
        const emoji = eventEmojis[type] || eventEmojis['other'];
        
        document.getElementById('eventDetailsTitle').textContent = title;
        document.getElementById('eventDetailsType').innerHTML = `<span class="badge bg-secondary">${emoji} ${type.charAt(0).toUpperCase() + type.slice(1)}</span>`;
        document.getElementById('eventDetailsTime').textContent = time;
        document.getElementById('eventDetailsLocation').textContent = location;
        document.getElementById('eventDetailsDescription').textContent = description;
        document.getElementById('eventDetailsCreator').textContent = creator;
    });
    </script>
</body>
</html>
