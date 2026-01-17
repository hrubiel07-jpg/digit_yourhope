<?php
require_once __DIR__ . '/../../includes/config.php';
requireUserType('school');

$user_id = $_SESSION['user_id'];

// Récupérer l'école
$stmt = $pdo->prepare("SELECT s.* FROM schools s JOIN users u ON s.user_id = u.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_event'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $event_type = $_POST['event_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?: $start_date;
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $location = $_POST['location'];
        $color = $_POST['color'];
        $visibility = $_POST['visibility'];
        
        $stmt = $pdo->prepare("
            INSERT INTO school_calendar 
            (school_id, title, description, event_type, start_date, end_date, 
             start_time, end_time, location, color, visibility, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$school_id, $title, $description, $event_type, $start_date, $end_date,
                       $start_time, $end_time, $location, $color, $visibility, $user_id]);
        
        $_SESSION['success_message'] = "Événement ajouté avec succès!";
        header("Location: calendar.php");
        exit();
    }
    
    if (isset($_POST['update_event'])) {
        $event_id = $_POST['event_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $event_type = $_POST['event_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?: $start_date;
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $location = $_POST['location'];
        $color = $_POST['color'];
        $visibility = $_POST['visibility'];
        
        $stmt = $pdo->prepare("
            UPDATE school_calendar 
            SET title = ?, description = ?, event_type = ?, start_date = ?, end_date = ?,
                start_time = ?, end_time = ?, location = ?, color = ?, visibility = ?
            WHERE id = ? AND school_id = ?
        ");
        $stmt->execute([$title, $description, $event_type, $start_date, $end_date,
                       $start_time, $end_time, $location, $color, $visibility, $event_id, $school_id]);
        
        $_SESSION['success_message'] = "Événement modifié avec succès!";
        header("Location: calendar.php");
        exit();
    }
}

// Supprimer un événement
if (isset($_GET['delete'])) {
    $event_id = $_GET['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM school_calendar WHERE id = ? AND school_id = ?");
    $stmt->execute([$event_id, $school_id]);
    
    $_SESSION['success_message'] = "Événement supprimé avec succès!";
    header("Location: calendar.php");
    exit();
}

// Récupérer les événements
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Premier et dernier jour du mois
$first_day = date('Y-m-01', strtotime("$year-$month-01"));
$last_day = date('Y-m-t', strtotime("$year-$month-01"));

$stmt = $pdo->prepare("
    SELECT * FROM school_calendar 
    WHERE school_id = ? 
    AND (
        (start_date BETWEEN ? AND ?) 
        OR (end_date BETWEEN ? AND ?)
        OR (start_date <= ? AND end_date >= ?)
    )
    ORDER BY start_date, start_time
");
$stmt->execute([$school_id, $first_day, $last_day, $first_day, $last_day, $first_day, $last_day]);
$events = $stmt->fetchAll();

// Organiser les événements par jour
$events_by_day = [];
foreach ($events as $event) {
    $current_date = $event['start_date'];
    while ($current_date <= $event['end_date']) {
        $events_by_day[$current_date][] = $event;
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier scolaire - <?php echo $school['school_name']; ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <style>
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .calendar-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .calendar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .calendar-view-buttons {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
        }
        
        .calendar-view-btn {
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .calendar-view-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .events-sidebar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .event-item {
            padding: 10px;
            border-left: 4px solid #3498db;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .event-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-right: 5px;
            color: white;
        }
        
        .type-academic { background: #3498db; }
        .type-holiday { background: #e74c3c; }
        .type-exam { background: #9b59b6; }
        .type-meeting { background: #f39c12; }
        .type-cultural { background: #2ecc71; }
        
        .event-time {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        #calendar {
            min-height: 600px;
        }
        
        .fc-event {
            cursor: pointer;
        }
        
        .fc-day-today {
            background-color: #f8f9fa !important;
        }
        
        .event-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        
        .event-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .event-modal-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="dashboard">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un événement...">
            </div>
            <div class="user-info">
                <span><?php echo $school['school_name']; ?></span>
                <img src="../../../assets/images/default-school.png" alt="Logo">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">
                <i class="fas fa-calendar-alt"></i> Calendrier scolaire
                <button class="btn-primary" onclick="showEventModal()">
                    <i class="fas fa-plus"></i> Nouvel événement
                </button>
            </h1>
            
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button class="btn-small" onclick="changeMonth(-1)">
                        <i class="fas fa-chevron-left"></i> Mois précédent
                    </button>
                    <h2 class="calendar-title" id="calendarTitle">
                        <?php echo strftime('%B %Y', strtotime("$year-$month-01")); ?>
                    </h2>
                    <button class="btn-small" onclick="changeMonth(1)">
                        Mois suivant <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div>
                    <button class="btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <button class="btn-primary" onclick="exportCalendar()">
                        <i class="fas fa-download"></i> Exporter
                    </button>
                </div>
            </div>
            
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
            
            <div class="row" style="display: flex; gap: 20px;">
                <div class="events-sidebar" style="flex: 1;">
                    <h3>Événements à venir</h3>
                    <?php
                    // Récupérer les événements à venir (30 prochains jours)
                    $upcoming_events = $pdo->prepare("
                        SELECT * FROM school_calendar 
                        WHERE school_id = ? AND start_date >= CURDATE()
                        ORDER BY start_date, start_time 
                        LIMIT 10
                    ")->execute([$school_id])->fetchAll();
                    
                    if (empty($upcoming_events)): ?>
                        <p style="color: #7f8c8d; text-align: center; padding: 20px;">
                            Aucun événement à venir.
                        </p>
                    <?php else: ?>
                        <?php foreach($upcoming_events as $event): ?>
                            <div class="event-item" style="border-left-color: <?php echo $event['color']; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <strong><?php echo $event['title']; ?></strong>
                                        <span class="event-type type-<?php echo $event['event_type']; ?>">
                                            <?php echo $event['event_type']; ?>
                                        </span>
                                    </div>
                                    <div class="event-actions">
                                        <button class="btn-small" onclick="editEvent(<?php echo $event['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-small btn-danger" 
                                                onclick="deleteEvent(<?php echo $event['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="event-time">
                                    <?php echo date('d/m/Y', strtotime($event['start_date'])); ?>
                                    <?php if ($event['start_time']): ?>
                                        à <?php echo date('H:i', strtotime($event['start_time'])); ?>
                                    <?php endif; ?>
                                    <?php if ($event['location']): ?>
                                        - <?php echo $event['location']; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($event['description']): ?>
                                    <p style="margin-top: 8px; font-size: 0.9rem;">
                                        <?php echo substr($event['description'], 0, 100); ?>...
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="events-sidebar" style="flex: 1;">
                    <h3>Légende</h3>
                    <div style="display: grid; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 15px; height: 15px; background: #3498db; border-radius: 3px;"></div>
                            <span>Académique</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 15px; height: 15px; background: #e74c3c; border-radius: 3px;"></div>
                            <span>Vacances</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 15px; height: 15px; background: #9b59b6; border-radius: 3px;"></div>
                            <span>Examens</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 15px; height: 15px; background: #f39c12; border-radius: 3px;"></div>
                            <span>Réunions</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 15px; height: 15px; background: #2ecc71; border-radius: 3px;"></div>
                            <span>Culturel/Sport</span>
                        </div>
                    </div>
                    
                    <h3 style="margin-top: 30px;">Statistiques</h3>
                    <?php
                    $stats = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            COUNT(CASE WHEN event_type = 'academic' THEN 1 END) as academic,
                            COUNT(CASE WHEN event_type = 'holiday' THEN 1 END) as holiday,
                            COUNT(CASE WHEN event_type = 'exam' THEN 1 END) as exam
                        FROM school_calendar 
                        WHERE school_id = ? AND start_date >= CURDATE()
                    ")->execute([$school_id])->fetch();
                    ?>
                    <div style="display: grid; gap: 10px; margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Total événements à venir:</span>
                            <strong><?php echo $stats['total']; ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Événements académiques:</span>
                            <strong><?php echo $stats['academic']; ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Jours de vacances:</span>
                            <strong><?php echo $stats['holiday']; ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Examens:</span>
                            <strong><?php echo $stats['exam']; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal d'événement -->
    <div class="event-modal" id="eventModal">
        <div class="event-modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="modalTitle">Nouvel événement</h3>
                <button class="modal-close" onclick="closeEventModal()">&times;</button>
            </div>
            
            <form method="POST" id="eventForm">
                <input type="hidden" name="event_id" id="eventId">
                
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" id="eventTitle" required>
                </div>
                
                <div class="form-group">
                    <label>Type d'événement *</label>
                    <select name="event_type" id="eventType" required onchange="updateEventColor(this)">
                        <option value="academic">Académique</option>
                        <option value="holiday">Vacances</option>
                        <option value="exam">Examen</option>
                        <option value="meeting">Réunion</option>
                        <option value="cultural">Culturel/Sport</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" name="color" id="eventColor" value="#3498db">
                </div>
                
                <div class="row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date de début *</label>
                        <input type="date" name="start_date" id="startDate" required>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label>Date de fin</label>
                        <input type="date" name="end_date" id="endDate">
                    </div>
                </div>
                
                <div class="row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Heure de début</label>
                        <input type="time" name="start_time" id="startTime">
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label>Heure de fin</label>
                        <input type="time" name="end_time" id="endTime">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Lieu</label>
                    <input type="text" name="location" id="eventLocation">
                </div>
                
                <div class="form-group">
                    <label>Visibilité</label>
                    <select name="visibility" id="eventVisibility">
                        <option value="public">Public</option>
                        <option value="teachers">Enseignants seulement</option>
                        <option value="students">Élèves seulement</option>
                        <option value="parents">Parents seulement</option>
                        <option value="admin">Administration seulement</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="eventDescription" rows="4"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeEventModal()">Annuler</button>
                    <button type="submit" name="add_event" id="submitBtn" class="btn-primary">Ajouter l'événement</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fr.js"></script>
    <script>
        // Initialiser FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'fr',
                firstDay: 1, // Lundi
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                },
                buttonText: {
                    today: 'Aujourd\'hui',
                    month: 'Mois',
                    week: 'Semaine',
                    day: 'Jour',
                    list: 'Liste'
                },
                events: <?php echo json_encode(array_map(function($event) {
                    return [
                        'id' => $event['id'],
                        'title' => $event['title'],
                        'start' => $event['start_date'] . ($event['start_time'] ? 'T' . $event['start_time'] : ''),
                        'end' => $event['end_date'] ? ($event['end_date'] . ($event['end_time'] ? 'T' . $event['end_time'] : '')) : null,
                        'color' => $event['color'],
                        'extendedProps' => [
                            'description' => $event['description'],
                            'location' => $event['location'],
                            'type' => $event['event_type'],
                            'visibility' => $event['visibility']
                        ]
                    ];
                }, $events)); ?>,
                eventClick: function(info) {
                    showEventDetails(info.event);
                },
                dateClick: function(info) {
                    showEventModal();
                    document.getElementById('startDate').value = info.dateStr;
                    document.getElementById('endDate').value = info.dateStr;
                }
            });
            
            calendar.render();
            window.calendar = calendar;
        });
        
        // Gestion du modal d'événement
        function showEventModal() {
            document.getElementById('eventModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Nouvel événement';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            document.getElementById('submitBtn').name = 'add_event';
            document.getElementById('submitBtn').textContent = 'Ajouter l\'événement';
        }
        
        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
        }
        
        function editEvent(eventId) {
            fetch(`get_event.php?id=${eventId}`)
                .then(response => response.json())
                .then(event => {
                    document.getElementById('modalTitle').textContent = 'Modifier l\'événement';
                    document.getElementById('eventId').value = event.id;
                    document.getElementById('eventTitle').value = event.title;
                    document.getElementById('eventType').value = event.event_type;
                    document.getElementById('eventColor').value = event.color;
                    document.getElementById('startDate').value = event.start_date;
                    document.getElementById('endDate').value = event.end_date || event.start_date;
                    document.getElementById('startTime').value = event.start_time || '';
                    document.getElementById('endTime').value = event.end_time || '';
                    document.getElementById('eventLocation').value = event.location || '';
                    document.getElementById('eventVisibility').value = event.visibility;
                    document.getElementById('eventDescription').value = event.description || '';
                    
                    document.getElementById('submitBtn').name = 'update_event';
                    document.getElementById('submitBtn').textContent = 'Modifier l\'événement';
                    
                    document.getElementById('eventModal').style.display = 'flex';
                });
        }
        
        function deleteEvent(eventId) {
            if (confirm('Supprimer cet événement ?')) {
                window.location.href = `?delete=${eventId}`;
            }
        }
        
        function showEventDetails(event) {
            const modal = document.createElement('div');
            modal.className = 'event-modal';
            modal.style.display = 'flex';
            
            const props = event.extendedProps;
            
            modal.innerHTML = `
                <div class="event-modal-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>${event.title}</h3>
                        <button class="modal-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <div style="width: 15px; height: 15px; background: ${event.backgroundColor}; border-radius: 3px;"></div>
                            <span style="text-transform: capitalize;">${props.type}</span>
                        </div>
                        
                        <div style="display: grid; gap: 10px;">
                            <div>
                                <strong>Date:</strong> ${event.start.toLocaleDateString('fr-FR')}
                                ${event.start.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'}) !== '00:00' ? 'à ' + event.start.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'}) : ''}
                            </div>
                            
                            ${props.location ? `<div><strong>Lieu:</strong> ${props.location}</div>` : ''}
                            ${props.visibility ? `<div><strong>Visibilité:</strong> ${props.visibility}</div>` : ''}
                            ${props.description ? `<div><strong>Description:</strong><br>${props.description}</div>` : ''}
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button class="btn-secondary" onclick="this.parentElement.parentElement.parentElement.remove()">Fermer</button>
                        <button class="btn-primary" onclick="editEvent(${event.id}); this.parentElement.parentElement.parentElement.remove()">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        function updateEventColor(select) {
            const colors = {
                'academic': '#3498db',
                'holiday': '#e74c3c',
                'exam': '#9b59b6',
                'meeting': '#f39c12',
                'cultural': '#2ecc71',
                'other': '#95a5a6'
            };
            
            document.getElementById('eventColor').value = colors[select.value] || '#3498db';
        }
        
        // Navigation par mois
        function changeMonth(direction) {
            const currentDate = new Date(<?php echo $year; ?>, <?php echo $month - 1; ?>, 1);
            currentDate.setMonth(currentDate.getMonth() + direction);
            
            const year = currentDate.getFullYear();
            const month = String(currentDate.getMonth() + 1).padStart(2, '0');
            
            window.location.href = `?year=${year}&month=${month}`;
        }
        
        function exportCalendar() {
            // Exporter en ICS (format de calendrier)
            const icsContent = generateICS();
            downloadFile('calendrier-scolaire.ics', icsContent);
        }
        
        function generateICS() {
            let ics = `BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Digital YOURHOPE//Calendrier Scolaire//FR
CALSCALE:GREGORIAN
METHOD:PUBLISH
`;
            
            <?php foreach($events as $event): ?>
            ics += `BEGIN:VEVENT
UID:<?php echo $event['id']; ?>@digitalyourhope.com
DTSTAMP:<?php echo date('Ymd\THis\Z'); ?>
DTSTART:<?php echo date('Ymd\THis', strtotime($event['start_date'] . ' ' . ($event['start_time'] ?: '00:00:00'))); ?>
DTEND:<?php echo date('Ymd\THis', strtotime(($event['end_date'] ?: $event['start_date']) . ' ' . ($event['end_time'] ?: '23:59:59'))); ?>
SUMMARY:<?php echo str_replace(["\r", "\n"], ['', ' '], $event['title']); ?>
DESCRIPTION:<?php echo str_replace(["\r", "\n"], ['', ' '], $event['description'] ?: ''); ?>
LOCATION:<?php echo $event['location'] ?: ''; ?>
END:VEVENT
`;
            <?php endforeach; ?>
            
            ics += `END:VCALENDAR`;
            return ics;
        }
        
        function downloadFile(filename, content) {
            const element = document.createElement('a');
            element.setAttribute('href', 'data:text/calendar;charset=utf-8,' + encodeURIComponent(content));
            element.setAttribute('download', filename);
            element.style.display = 'none';
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        }
    </script>
</body>
</html>