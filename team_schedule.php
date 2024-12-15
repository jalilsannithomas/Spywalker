<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Initialize debug info
$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session' => $_SESSION,
    'events_found' => 0,
    'steps' => []
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$events = []; // Initialize events array
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'athlete';

try {
    // Get user's team_id
    $team_id = $_SESSION['team_id'] ?? null;
    $debug_info['steps'][] = "Team ID from session: " . ($team_id ?? 'null');
    
    // Get events for the team
    if ($team_id) {
        // First, check the team_events table
        $check_query = "SELECT COUNT(*) as count FROM team_events WHERE team_id = :team_id";
        $stmt = $conn->prepare($check_query);
        $stmt->execute([':team_id' => $team_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug_info['steps'][] = "Found " . $result['count'] . " entries in team_events table";

        // Now get the actual events
        $query = "SELECT e.*, te.team_id 
                 FROM events e 
                 JOIN team_events te ON e.id = te.event_id 
                 WHERE te.team_id = :team_id 
                 ORDER BY e.start_time ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute([':team_id' => $team_id]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $debug_info['events_found'] = count($events);
        $debug_info['steps'][] = "Found " . count($events) . " events for team";
        $debug_info['events'] = $events; // Add the actual events to debug info
        
        // Check each event's data
        foreach ($events as $event) {
            $debug_info['steps'][] = "Event ID: " . $event['id'] . 
                                   ", Title: " . $event['title'] . 
                                   ", Start: " . $event['start_time'] . 
                                   ", Team ID: " . $event['team_id'];
        }
    } else {
        $debug_info['steps'][] = "No team_id found in session";
    }
} catch (PDOException $e) {
    $debug_info['error'] = $e->getMessage();
    $debug_info['steps'][] = "Database error: " . $e->getMessage();
}

// Output debug info in HTML comment
echo "<!--\nDEBUG INFO:\n" . print_r($debug_info, true) . "\n-->\n";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Team Schedule</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" rel="stylesheet" />
    <link href="assets/css/team-stats.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #2C1810;
            font-family: 'Press Start 2P', monospace;
        }
        
        .fc-event {
            border: none !important;
            padding: 2px 5px !important;
        }
        
        .fc-day-grid-event {
            background-color: #ff0000 !important;
            color: white !important;
        }
        
        .fc-day:hover {
            background: rgba(212, 175, 55, 0.1) !important;
            cursor: pointer;
        }
        
        .fc-widget-header {
            background-color: #3C2A20 !important;
            color: #D4AF37 !important;
        }
        
        .fc-widget-content {
            background-color: #241409 !important;
            border-color: #D4AF37 !important;
        }
        
        .fc-today {
            background-color: rgba(212, 175, 55, 0.2) !important;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="header-container text-center mb-4">
            <h1 class="text-golden">Team Schedule</h1>
        </div>
        <div id="calendar"></div>
    </div>

    <script>
    $(document).ready(function() {
        // Initialize calendar with saved events
        var savedEvents = <?php echo json_encode($events); ?>;
        console.log('Loaded saved events:', savedEvents);
        
        $('#calendar').fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            editable: true,
            eventLimit: true,
            events: savedEvents.map(function(event) {
                return {
                    title: event.title,
                    start: event.start_time,
                    end: event.end_time,
                    color: 'red',  // Set event color to red
                    textColor: 'white'  // Set text color to white for better contrast
                };
            }),
            eventClick: function(event) {
                // Handle event click
                console.log('Event clicked:', event);
            },
            dayClick: function(date) {
                var title = prompt('Enter event title:');
                if (title) {
                    // Save event to database
                    $.ajax({
                        url: 'handlers/save_event.php',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            date: date.format(),
                            text: title
                        }),
                        success: function(response) {
                            console.log('Save response:', response);
                            if (response.success) {
                                // Add event to calendar
                                $('#calendar').fullCalendar('renderEvent', {
                                    title: title,
                                    start: date,
                                    color: 'red',  // Set event color to red
                                    textColor: 'white'  // Set text color to white for better contrast
                                }, true);
                            } else {
                                alert('Error saving event: ' + response.message);
                                console.error('Debug info:', response.debug_info);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Ajax error:', error);
                            alert('Error saving event. Please try again.');
                        }
                    });
                }
            }
        });
    });
    </script>
</body>
</html>