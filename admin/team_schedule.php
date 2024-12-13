<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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

// Get all teams
$teams_sql = "SELECT id, name FROM teams ORDER BY name";
$teams_result = $conn->query($teams_sql);
$teams = $teams_result->fetch_all(MYSQLI_ASSOC);

// Get events for all teams
$events_sql = "SELECT te.*, t.name as team_name 
               FROM team_events te
               JOIN teams t ON te.team_id = t.id
               WHERE te.event_date BETWEEN ? AND ?
               ORDER BY te.event_date, te.event_time";

$stmt = $conn->prepare($events_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$events_result = $stmt->get_result();

$events = [];
while ($event = $events_result->fetch_assoc()) {
    $date = date('j', strtotime($event['event_date']));
    if (!isset($events[$date])) {
        $events[$date] = [];
    }
    $events[$date][] = $event;
}

// Get first day of the month (0 = Sunday, 6 = Saturday)
$first_day_of_month = date('w', strtotime($start_date));
$days_in_month = date('t', strtotime($start_date));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Team Schedule - SpyWalker Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .calendar {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        .calendar-day {
            aspect-ratio: 1;
            padding: 5px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            position: relative;
        }
        .calendar-day:not(.empty):hover {
            background: rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }
        .day-number {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.7);
        }
        .event-dot {
            width: 8px;
            height: 8px;
            background: #ffd700;
            border-radius: 50%;
            margin: 2px;
            display: inline-block;
        }
        .event-list {
            font-size: 0.8em;
            margin-top: 20px;
        }
        .event-item {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
            padding: 2px 4px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <?php require_once '../components/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="header-container text-center">
            <h1 class="vintage-title">Team Schedule</h1>
            <div class="calendar-navigation vintage-subtitle mb-4">
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-outline-light">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <span class="mx-3"><?php echo date('F Y', strtotime($start_date)); ?></span>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-outline-light">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>

        <div class="calendar">
            <div class="calendar-grid mb-2">
                <div class="text-center">Sun</div>
                <div class="text-center">Mon</div>
                <div class="text-center">Tue</div>
                <div class="text-center">Wed</div>
                <div class="text-center">Thu</div>
                <div class="text-center">Fri</div>
                <div class="text-center">Sat</div>
            </div>
            
            <div class="calendar-grid">
                <?php
                // Empty cells before first day of month
                for ($i = 0; $i < $first_day_of_month; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                
                // Calendar days
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $day_events = $events[$day] ?? [];
                    $event_count = count($day_events);
                    
                    echo '<div class="calendar-day" data-bs-toggle="modal" data-bs-target="#newEventModal" data-date="' . sprintf('%04d-%02d-%02d', $year, $month, $day) . '">';
                    echo '<div class="day-number">' . $day . '</div>';
                    
                    if ($event_count > 0) {
                        echo '<div class="event-list">';
                        foreach ($day_events as $event) {
                            $emoji = $event_emojis[$event['event_type']] ?? '📅';
                            echo '<div class="event-item" data-bs-toggle="modal" data-bs-target="#eventDetailsModal"
                                      data-event-id="' . $event['id'] . '"
                                      data-event-title="' . htmlspecialchars($event['title']) . '"
                                      data-event-time="' . date('g:i A', strtotime($event['start_time'])) . '"
                                      data-event-location="' . htmlspecialchars($event['location']) . '"
                                      data-event-team="' . htmlspecialchars($event['team_name']) . '">';
                            echo $emoji . ' ' . htmlspecialchars($event['title']);
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
                
                // Empty cells after last day of month
                $last_day_of_month = date('w', strtotime($end_date));
                for ($i = $last_day_of_month; $i < 6; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                ?>
            </div>
        </div>

        <div class="text-center mt-4">
            <button class="btn btn-primary vintage-button" data-bs-toggle="modal" data-bs-target="#newEventModal">
                Add New Event
            </button>
        </div>
    </div>

    <!-- New Event Modal -->
    <div class="modal fade" id="newEventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newEventForm">
                        <div class="mb-3">
                            <label for="eventTeam" class="form-label">Team</label>
                            <select class="form-select" id="eventTeam" name="team_id" required>
                                <option value="">Select a team...</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="eventTitle" class="form-label">Event Title</label>
                            <input type="text" class="form-control" id="eventTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventType" class="form-label">Event Type</label>
                            <select class="form-select" id="eventType" name="event_type" required>
                                <option value="match">Match</option>
                                <option value="training">Training</option>
                                <option value="tournament">Tournament</option>
                                <option value="meeting">Meeting</option>
                                <option value="social">Social Event</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="eventDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="eventDate" name="event_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventTime" class="form-label">Time</label>
                            <input type="time" class="form-control" id="eventTime" name="event_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventLocation" class="form-label">Location</label>
                            <input type="text" class="form-control" id="eventLocation" name="location" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="eventDescription" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveEvent()">Save Event</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Event Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h4 id="eventDetailTitle"></h4>
                    <p><strong>Team:</strong> <span id="eventDetailTeam"></span></p>
                    <p><strong>Time:</strong> <span id="eventDetailTime"></span></p>
                    <p><strong>Location:</strong> <span id="eventDetailLocation"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="deleteEvent(currentEventId)">Delete Event</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentEventId = null;

    document.addEventListener('DOMContentLoaded', function() {
        const calendarDays = document.querySelectorAll('.calendar-day:not(.empty)');
        
        calendarDays.forEach(day => {
            day.addEventListener('click', function(e) {
                if (e.target.classList.contains('event-item')) {
                    e.stopPropagation();
                    return;
                }
                const date = this.dataset.date;
                document.getElementById('eventDate').value = date;
            });
        });

        const newEventModal = document.getElementById('newEventModal');
        newEventModal.addEventListener('show.bs.modal', function (event) {
            if (event.relatedTarget.classList.contains('calendar-day')) {
                const date = event.relatedTarget.dataset.date;
                document.getElementById('eventDate').value = date;
            }
        });

        const eventDetailsModal = document.getElementById('eventDetailsModal');
        eventDetailsModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const title = button.dataset.eventTitle;
            const time = button.dataset.eventTime;
            const location = button.dataset.eventLocation;
            const team = button.dataset.eventTeam;
            currentEventId = button.dataset.eventId;

            document.getElementById('eventDetailTitle').textContent = title;
            document.getElementById('eventDetailTime').textContent = time;
            document.getElementById('eventDetailLocation').textContent = location;
            document.getElementById('eventDetailTeam').textContent = team;
        });
    });

    async function saveEvent() {
        const form = document.getElementById('newEventForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('../api/save_event.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const result = await response.json();
            if (result.success) {
                location.reload();
            } else {
                alert('Error saving event: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error saving event. Please try again.');
        }
    }

    async function deleteEvent(eventId) {
        if (!confirm('Are you sure you want to delete this event?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('event_id', eventId);
            
            const response = await fetch('../api/delete_event.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const result = await response.json();
            if (result.success) {
                location.reload();
            } else {
                alert('Error deleting event: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error deleting event. Please try again.');
        }
    }
    </script>
</body>
</html>
