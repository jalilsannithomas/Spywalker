<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get events for the current user
$events_query = "SELECT event_date, event_text FROM events WHERE user_id = ?";
$stmt = $conn->prepare($events_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$events = array();
while ($row = $result->fetch_assoc()) {
    $events[$row['event_date']] = $row['event_text'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/schedule.css" rel="stylesheet">
    <style>
        :root {
            --dark-brown: #2C1810;
            --medium-brown: #3C2A20;
            --light-brown: #4E3829;
            --gold: #FFD700;
            --vintage-bg: #2C1810;
        }

        body {
            background-color: var(--vintage-bg);
            color: #fff;
            font-family: 'Press Start 2P', cursive;
        }

        .schedule-container {
            max-width: 1000px;
            margin: 30px auto;
            background-color: var(--medium-brown);
            border: 2px solid var(--gold);
            border-radius: 10px;
            padding: 20px;
        }

        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .schedule-title {
            color: var(--gold);
            font-size: 24px;
            margin: 0;
        }

        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-button {
            background-color: var(--dark-brown);
            color: var(--gold);
            border: 1px solid var(--gold);
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .nav-button:hover {
            background-color: var(--light-brown);
        }

        .current-month {
            color: var(--gold);
            font-size: 20px;
            margin: 0 20px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .day-header {
            width: calc(100% / 7);
            text-align: center;
            color: var(--gold);
        }

        .calendar-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .calendar-day {
            width: calc(100% / 7);
            background-color: var(--dark-brown);
            border: 1px solid var(--light-brown);
            padding: 10px;
            text-align: center;
            vertical-align: top;
            height: 100px;
            border-radius: 5px;
            cursor: pointer;
            position: relative;
            transition: background-color 0.3s;
        }

        .calendar-day.today {
            background-color: var(--light-brown);
        }

        .calendar-day.other-month {
            background-color: var(--medium-brown);
        }

        .day-number {
            position: absolute;
            top: 5px;
            left: 5px;
            font-size: 14px;
            color: #fff;
        }

        .event {
            margin-top: 25px;
            color: #ff4444;
            font-size: 14px;
            line-height: 1.2;
            word-wrap: break-word;
            padding: 0 5px;
            cursor: pointer;
        }

        .event-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: var(--medium-brown);
            border: 2px solid var(--gold);
            border-radius: 10px;
            padding: 20px;
            display: none;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            color: var(--gold);
            font-size: 20px;
            margin: 0;
        }

        .modal-body {
            color: #fff;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="schedule-container">
        <div class="schedule-header">
            <h1 class="schedule-title">MY SCHEDULE</h1>
            <div class="month-navigation">
                <button class="nav-button" onclick="prevMonth()">PREVIOUS</button>
                <div class="current-month" id="currentMonth">DECEMBER 2024</div>
                <button class="nav-button" onclick="nextMonth()">NEXT</button>
            </div>
        </div>

        <div class="calendar-header">
            <div class="day-header">SUN</div>
            <div class="day-header">MON</div>
            <div class="day-header">TUE</div>
            <div class="day-header">WED</div>
            <div class="day-header">THU</div>
            <div class="day-header">FRI</div>
            <div class="day-header">SAT</div>
        </div>

        <div class="calendar-grid">
            <?php
            $currentDate = new DateTime();
            $firstDay = new DateTime($currentDate->format('Y-m-1'));
            $lastDay = new DateTime($currentDate->format('Y-m-t'));
            $startingDay = $firstDay->format('w');
            $monthLength = $lastDay->format('d');
            
            $day = 1;
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 7; $j++) {
                    if ($i === 0 && $j < $startingDay) {
                        // Empty cells before the first day
                        echo '<div class="calendar-day"></div>';
                    } elseif ($day > $monthLength) {
                        // Empty cells after the last day
                        echo '<div class="calendar-day other-month"></div>';
                    } else {
                        // Add date number
                        $dateStr = $currentDate->format('Y-m-') . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $isToday = $dateStr === date('Y-m-d');
                        $isOtherMonth = false;
                        
                        echo '<div class="calendar-day ' . ($isToday ? 'today' : '') . ' ' . ($isOtherMonth ? 'other-month' : '') . '">';
                        echo '<div class="day-number">' . $day . '</div>';
                        
                        // Add existing event or make cell clickable
                        if (isset($events[$dateStr])) {
                            echo '<div class="event">' . $events[$dateStr] . '</div>';
                        }
                        
                        echo '</div>';
                        $day++;
                    }
                }
                if ($day > $monthLength) break;
            }
            ?>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="event-modal" id="eventModal" style="display: none;">
        <div class="modal-header">
            <h2 class="modal-title">Event Details</h2>
        </div>
        <div class="modal-body">
            <!-- Event details will be populated by JavaScript -->
        </div>
        <div class="modal-footer">
            <button class="nav-button" onclick="closeEventModal()">CLOSE</button>
        </div>
    </div>

    <script>
        let currentDate = new Date();
        
        function prevMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar();
        }

        function generateCalendar() {
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            const startingDay = firstDay.getDay();
            const monthLength = lastDay.getDate();
            
            // Update month display
            const monthNames = ["January", "February", "March", "April", "May", "June",
                              "July", "August", "September", "October", "November", "December"];
            document.getElementById('currentMonth').textContent = 
                `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
            
            let calendar = document.querySelector('.calendar-grid');
            calendar.innerHTML = '';
            
            let day = 1;
            for (let i = 0; i < 6; i++) {
                for (let j = 0; j < 7; j++) {
                    if (i === 0 && j < startingDay) {
                        // Empty cells before the first day
                        calendar.innerHTML += '<div class="calendar-day"></div>';
                    } elseif (day > monthLength) {
                        // Empty cells after the last day
                        calendar.innerHTML += '<div class="calendar-day other-month"></div>';
                    } else {
                        // Add date number
                        let dateStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                        
                        // Format date string for comparison
                        let isToday = dateStr === new Date().toISOString().split('T')[0];
                        let isOtherMonth = false;
                        
                        calendar.innerHTML += `
                            <div class="calendar-day ${isToday ? 'today' : ''} ${isOtherMonth ? 'other-month' : ''}">
                                <div class="day-number">${day}</div>
                                ${events[dateStr] ? `<div class="event">${events[dateStr]}</div>` : ''}
                            </div>
                        `;
                        
                        day++;
                    }
                }
                if (day > monthLength) break;
            }
        }

        function showEventDetails(eventData) {
            const event = JSON.parse(eventData);
            const modal = document.getElementById('eventModal');
            const modalBody = modal.querySelector('.modal-body');
            
            modalBody.innerHTML = `
                <p><strong>Time:</strong> ${event.time}</p>
                <p><strong>Title:</strong> ${event.title}</p>
                <p><strong>Location:</strong> ${event.location || 'TBD'}</p>
                <p><strong>Description:</strong> ${event.description || 'No description available'}</p>
            `;
            
            modal.style.display = 'block';
        }

        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
        }

        // Initialize calendar
        generateCalendar();
    </script>
</body>
</html>
