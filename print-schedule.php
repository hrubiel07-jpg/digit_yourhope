<?php
require_once __DIR__ . '/../../includes/config.php';
require_once '../../../includes/school_config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];
$class_id = $_GET['class'] ?? 0;
$academic_year = $_GET['year'] ?? date('Y') . '-' . (date('Y') + 1);

// Récupérer l'ID de l'école
$stmt = $pdo->prepare("SELECT id FROM schools WHERE user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

// Récupérer les informations de la classe
$stmt = $pdo->prepare("
    SELECT c.*, l.level_name, t.full_name as teacher_name,
           s.school_name, s.address, s.city, s.phone, s.email
    FROM classes c 
    JOIN school_levels l ON c.level_id = l.id 
    JOIN schools s ON c.school_id = s.id 
    LEFT JOIN teachers te ON c.teacher_id = te.id 
    LEFT JOIN users t ON te.user_id = t.id 
    WHERE c.id = ? AND c.school_id = ?
");
$stmt->execute([$class_id, $school_id]);
$class_info = $stmt->fetch();

if (!$class_info) {
    die('Classe non trouvée');
}

// Récupérer l'emploi du temps
$stmt = $pdo->prepare("
    SELECT s.*, sj.subject_name, sj.category, t.full_name as teacher_name,
           TIME_FORMAT(s.start_time, '%H:%i') as start_formatted,
           TIME_FORMAT(s.end_time, '%H:%i') as end_formatted
    FROM schedules sch 
    JOIN subjects sj ON sch.subject_id = sj.id 
    LEFT JOIN teachers te ON sch.teacher_id = te.id 
    LEFT JOIN users t ON te.user_id = t.id 
    WHERE sch.class_id = ? 
    AND sch.academic_year = ?
    AND sch.school_id = ?
    ORDER BY 
        FIELD(sch.day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'),
        sch.start_time
");
$stmt->execute([$class_id, $academic_year, $school_id]);
$schedules = $stmt->fetchAll();

// Organiser par jour
$schedule_by_day = [
    'Lundi' => [], 'Mardi' => [], 'Mercredi' => [],
    'Jeudi' => [], 'Vendredi' => [], 'Samedi' => []
];

foreach ($schedules as $course) {
    $schedule_by_day[$course['day_of_week']][] = $course;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emploi du temps - <?php echo htmlspecialchars($class_info['class_name']); ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        .schedule-container {
            width: 297mm;
            min-height: 210mm;
            margin: 0 auto;
            padding: 5mm;
            box-sizing: border-box;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 5mm;
            margin-bottom: 5mm;
        }
        
        .school-name {
            font-size: 18pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 2mm;
        }
        
        .schedule-title {
            font-size: 16pt;
            color: #666;
            margin-bottom: 2mm;
        }
        
        .class-info {
            font-size: 12pt;
            color: #666;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5mm;
        }
        
        .schedule-table th {
            background: #333;
            color: white;
            padding: 3mm;
            text-align: center;
            font-weight: bold;
            border: 1px solid #333;
            font-size: 10pt;
        }
        
        .schedule-table td {
            padding: 2mm;
            border: 1px solid #ccc;
            vertical-align: top;
            font-size: 9pt;
        }
        
        .time-header {
            background: #555;
            color: white;
            font-weight: bold;
            width: 40mm;
        }
        
        .day-header {
            background: #666;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        
        .time-slot {
            background: #f8f8f8;
            padding: 2mm;
            text-align: center;
            font-weight: bold;
            color: #333;
        }
        
        .course-cell {
            min-height: 15mm;
            position: relative;
        }
        
        .course-item {
            background: white;
            border: 1px solid #ccc;
            border-left: 4px solid #3498db;
            border-radius: 2mm;
            padding: 2mm;
            margin: 1mm;
            overflow: hidden;
        }
        
        .course-subject {
            font-weight: bold;
            color: #333;
            font-size: 8pt;
            margin-bottom: 1mm;
        }
        
        .course-details {
            font-size: 7pt;
            color: #666;
            line-height: 1.2;
        }
        
        .course-time {
            font-weight: bold;
            color: #333;
        }
        
        .empty-cell {
            background: #f8f8f8;
            text-align: center;
            color: #ccc;
            font-style: italic;
            font-size: 8pt;
            padding: 5mm 2mm;
        }
        
        .footer {
            text-align: center;
            margin-top: 10mm;
            padding-top: 5mm;
            border-top: 1px solid #ccc;
            font-size: 8pt;
            color: #666;
        }
        
        .legend {
            display: flex;
            gap: 5mm;
            margin: 3mm 0;
            padding: 2mm;
            background: #f8f8f8;
            border-radius: 1mm;
            font-size: 8pt;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 1mm;
        }
        
        .legend-color {
            width: 3mm;
            height: 3mm;
            border-radius: 0.5mm;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .schedule-container {
                width: 100%;
                min-height: 100vh;
                padding: 5mm;
                box-shadow: none;
                border: none;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .category-lettres { border-left-color: #e74c3c; }
        .category-sciences { border-left-color: #3498db; }
        .category-techniques { border-left-color: #2ecc71; }
        .category-arts { border-left-color: #f39c12; }
        .category-sport { border-left-color: #9b59b6; }
    </style>
</head>
<body>
    <div class="schedule-container">
        <div class="header">
            <div class="school-name"><?php echo htmlspecialchars($class_info['school_name']); ?></div>
            <div class="schedule-title">EMPLOI DU TEMPS</div>
            <div class="class-info">
                <?php echo htmlspecialchars($class_info['class_name'] . ' - ' . $class_info['level_name']); ?> | 
                Année académique: <?php echo htmlspecialchars($academic_year); ?> | 
                Professeur principal: <?php echo htmlspecialchars($class_info['teacher_name'] ?? 'Non affecté'); ?>
            </div>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background: #e74c3c;"></div>
                <span>Lettres</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #3498db;"></div>
                <span>Sciences</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #2ecc71;"></div>
                <span>Techniques</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #f39c12;"></div>
                <span>Arts</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #9b59b6;"></div>
                <span>Sport</span>
            </div>
        </div>
        
        <table class="schedule-table">
            <thead>
                <tr>
                    <th class="time-header">Heures</th>
                    <?php foreach(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'] as $day): ?>
                        <th class="day-header"><?php echo $day; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Créneaux horaires de 8h à 18h
                $start_hour = 8;
                $end_hour = 18;
                
                for ($hour = $start_hour; $hour < $end_hour; $hour++):
                    for ($minute = 0; $minute < 60; $minute += 60):
                        $time_str = sprintf('%02d:%02d', $hour, $minute);
                        $next_time = sprintf('%02d:%02d', $hour + 1, $minute);
                ?>
                    <tr>
                        <td class="time-slot">
                            <?php echo $time_str; ?><br>
                            <?php echo $next_time; ?>
                        </td>
                        
                        <?php foreach(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'] as $day): ?>
                            <td class="course-cell">
                                <?php 
                                // Trouver les cours à cette heure
                                $current_courses = [];
                                foreach ($schedule_by_day[$day] as $course) {
                                    $course_start = strtotime($course['start_time']);
                                    $course_end = strtotime($course['end_time']);
                                    $cell_start = strtotime($time_str . ':00');
                                    $cell_end = strtotime($next_time . ':00');
                                    
                                    if ($course_start < $cell_end && $course_end > $cell_start) {
                                        $current_courses[] = $course;
                                    }
                                }
                                
                                if (!empty($current_courses)):
                                    foreach ($current_courses as $course):
                                        // Déterminer la catégorie pour la couleur
                                        $category_class = 'category-' . strtolower($course['category']);
                                ?>
                                    <div class="course-item <?php echo $category_class; ?>">
                                        <div class="course-subject">
                                            <?php echo htmlspecialchars($course['subject_name']); ?>
                                        </div>
                                        <div class="course-details">
                                            <div class="course-time">
                                                <?php echo $course['start_formatted']; ?>-<?php echo $course['end_formatted']; ?>
                                            </div>
                                            <?php if ($course['teacher_name']): ?>
                                                <div><?php echo htmlspecialchars($course['teacher_name']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($course['room']): ?>
                                                <div>Salle: <?php echo htmlspecialchars($course['room']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                    <div class="empty-cell">-</div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php 
                    endfor;
                endfor; 
                ?>
            </tbody>
        </table>
        
        <div class="footer">
            Emploi du temps généré le <?php echo date('d/m/Y H:i'); ?> | 
            <?php echo htmlspecialchars($class_info['school_name']); ?> | 
            <?php echo htmlspecialchars($class_info['address'] ?? ''); ?> - <?php echo htmlspecialchars($class_info['city'] ?? ''); ?> | 
            Tel: <?php echo htmlspecialchars($class_info['phone'] ?? ''); ?>
        </div>
    </div>
    
    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #333; color: white; border: none; border-radius: 5px; cursor: pointer;">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #ccc; color: #333; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            <i class="fas fa-times"></i> Fermer
        </button>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
        
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>