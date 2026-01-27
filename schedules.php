<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/school_config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer l'ID de l'école
$stmt = $pdo->prepare("SELECT id FROM schools WHERE user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

$action = $_GET['action'] ?? 'list';
$schedule_id = $_GET['id'] ?? 0;
$class_id = $_GET['class'] ?? 0;
$message = '';
$error = '';

// Récupérer les classes
$stmt = $pdo->prepare("
    SELECT c.*, l.level_name 
    FROM classes c 
    JOIN school_levels l ON c.level_id = l.id 
    WHERE c.school_id = ? AND c.is_active = TRUE 
    ORDER BY l.order_num, c.class_name
");
$stmt->execute([$school_id]);
$classes = $stmt->fetchAll();

// Récupérer les matières
$stmt = $pdo->prepare("
    SELECT * FROM subjects 
    WHERE school_id = ? OR school_id IS NULL 
    ORDER BY category, subject_name
");
$stmt->execute([$school_id]);
$subjects = $stmt->fetchAll();

// Récupérer les enseignants
$stmt = $pdo->prepare("
    SELECT t.*, u.full_name, u.email 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id IN (
        SELECT ta.teacher_id FROM teacher_applications ta 
        JOIN school_jobs sj ON ta.job_id = sj.id 
        WHERE sj.school_id = ? AND ta.status = 'accepted'
    )
    ORDER BY u.full_name
");
$stmt->execute([$school_id]);
$teachers = $stmt->fetchAll();

// Traitement du formulaire d'emploi du temps
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_schedule'])) {
        // Génération automatique d'emploi du temps
        $class_id = $_POST['class_id'];
        $academic_year = $_POST['academic_year'];
        
        if (empty($class_id) || empty($academic_year)) {
            $error = "Veuillez sélectionner une classe et une année académique";
        } else {
            // Logique de génération automatique
            $message = "Génération automatique de l'emploi du temps (à implémenter)";
        }
    } else {
        // Ajout/Modification d'un cours
        $data = [
            'school_id' => $school_id,
            'class_id' => $_POST['class_id'],
            'subject_id' => $_POST['subject_id'],
            'teacher_id' => $_POST['teacher_id'] ? intval($_POST['teacher_id']) : null,
            'day_of_week' => $_POST['day_of_week'],
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'room' => sanitize($_POST['room']),
            'academic_year' => $_POST['academic_year']
        ];
        
        // Validation
        if (empty($data['class_id']) || empty($data['subject_id']) || empty($data['day_of_week'])) {
            $error = "La classe, la matière et le jour sont obligatoires";
        } else if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            $error = "L'heure de fin doit être après l'heure de début";
        } else {
            try {
                // Vérifier les conflits d'horaires
                $stmt = $pdo->prepare("
                    SELECT s.*, sj.subject_name, c.class_name 
                    FROM schedules s 
                    JOIN subjects sj ON s.subject_id = sj.id 
                    JOIN classes c ON s.class_id = c.id 
                    WHERE s.school_id = ? 
                    AND s.class_id = ? 
                    AND s.day_of_week = ? 
                    AND s.academic_year = ?
                    AND (
                        (s.start_time < ? AND s.end_time > ?) OR
                        (s.start_time < ? AND s.end_time > ?) OR
                        (s.start_time >= ? AND s.end_time <= ?)
                    )
                    " . ($schedule_id > 0 ? "AND s.id != ?" : "")
                );
                
                $params = [
                    $school_id,
                    $data['class_id'],
                    $data['day_of_week'],
                    $data['academic_year'],
                    $data['end_time'], $data['start_time'],
                    $data['start_time'], $data['end_time'],
                    $data['start_time'], $data['end_time']
                ];
                
                if ($schedule_id > 0) {
                    $params[] = $schedule_id;
                }
                
                $stmt->execute($params);
                
                if ($stmt->rowCount() > 0) {
                    $conflict = $stmt->fetch();
                    $error = "Conflit d'horaire avec: " . $conflict['subject_name'] . 
                            " (" . $conflict['class_name'] . ") " . 
                            substr($conflict['start_time'], 0, 5) . "-" . substr($conflict['end_time'], 0, 5);
                } else {
                    if ($action === 'edit' && $schedule_id > 0) {
                        // Mettre à jour
                        $sql = "UPDATE schedules SET ";
                        $params = [];
                        $updates = [];
                        
                        foreach ($data as $key => $value) {
                            if ($key !== 'school_id') {
                                $updates[] = "$key = ?";
                                $params[] = $value;
                            }
                        }
                        
                        $sql .= implode(', ', $updates) . " WHERE id = ? AND school_id = ?";
                        $params[] = $schedule_id;
                        $params[] = $school_id;
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $message = "Cours mis à jour avec succès";
                    } else {
                        // Ajouter
                        $columns = implode(', ', array_keys($data));
                        $placeholders = implode(', ', array_fill(0, count($data), '?'));
                        
                        $sql = "INSERT INTO schedules ($columns) VALUES ($placeholders)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_values($data));
                        $message = "Cours ajouté avec succès";
                    }
                }
            } catch (Exception $e) {
                $error = "Erreur: " . $e->getMessage();
            }
        }
    }
}

// Récupérer les données d'un cours pour l'édition
$schedule = null;
if ($action === 'edit' && $schedule_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, l.level_name, sj.subject_name, t.full_name as teacher_name
        FROM schedules s 
        JOIN classes c ON s.class_id = c.id 
        JOIN school_levels l ON c.level_id = l.id 
        JOIN subjects sj ON s.subject_id = sj.id 
        LEFT JOIN teachers te ON s.teacher_id = te.id 
        LEFT JOIN users t ON te.user_id = t.id 
        WHERE s.id = ? AND s.school_id = ?
    ");
    $stmt->execute([$schedule_id, $school_id]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        header('Location: schedules.php');
        exit();
    }
    
    $class_id = $schedule['class_id'];
}

// Récupérer l'emploi du temps d'une classe
if ($action === 'list' || ($action === 'view' && $class_id > 0)) {
    $academic_year = $_GET['year'] ?? date('Y') . '-' . (date('Y') + 1);
    
    // Récupérer les cours de la classe
    $stmt = $pdo->prepare("
        SELECT s.*, sj.subject_name, sj.category, t.full_name as teacher_name,
               TIME_FORMAT(s.start_time, '%H:%i') as start_formatted,
               TIME_FORMAT(s.end_time, '%H:%i') as end_formatted
        FROM schedules s 
        JOIN subjects sj ON s.subject_id = sj.id 
        LEFT JOIN teachers te ON s.teacher_id = te.id 
        LEFT JOIN users t ON te.user_id = t.id 
        WHERE s.class_id = ? 
        AND s.academic_year = ?
        AND s.school_id = ?
        ORDER BY 
            FIELD(s.day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'),
            s.start_time
    ");
    $stmt->execute([$class_id, $academic_year, $school_id]);
    $class_schedules = $stmt->fetchAll();
    
    // Organiser par jour
    $schedule_by_day = [
        'Lundi' => [], 'Mardi' => [], 'Mercredi' => [],
        'Jeudi' => [], 'Vendredi' => [], 'Samedi' => []
    ];
    
    foreach ($class_schedules as $course) {
        $schedule_by_day[$course['day_of_week']][] = $course;
    }
    
    // Récupérer les informations de la classe
    if ($class_id > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, l.level_name, t.full_name as teacher_name
            FROM classes c 
            JOIN school_levels l ON c.level_id = l.id 
            LEFT JOIN teachers te ON c.teacher_id = te.id 
            LEFT JOIN users t ON te.user_id = t.id 
            WHERE c.id = ? AND c.school_id = ?
        ");
        $stmt->execute([$class_id, $school_id]);
        $selected_class = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emplois du Temps - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <?php echo applySchoolTheme($school_id); ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .schedule-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .schedule-header h1 {
            color: white;
            margin-bottom: 10px;
        }
        
        .class-selector {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .selector-group {
            flex: 1;
            min-width: 250px;
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: 100px repeat(6, 1fr);
            gap: 1px;
            background: #e0e0e0;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 30px;
        }
        
        .time-header {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }
        
        .day-header {
            background: var(--secondary-color);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }
        
        .time-slot {
            background: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
        
        .course-cell {
            background: white;
            padding: 5px;
            border-bottom: 1px solid #e0e0e0;
            min-height: 60px;
            position: relative;
        }
        
        .course-item {
            background: white;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 10px;
            margin: 2px;
            position: absolute;
            left: 2px;
            right: 2px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .course-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 10;
        }
        
        .course-subject {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        
        .course-teacher {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 3px;
        }
        
        .course-time {
            font-size: 0.8rem;
            color: #888;
        }
        
        .course-room {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255,255,255,0.9);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .empty-cell {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 0.9rem;
            min-height: 60px;
        }
        
        .schedule-legend {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .legend-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .day-schedule {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .day-title {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .course-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .course-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            background: white;
            transition: all 0.3s;
        }
        
        .course-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .course-info {
            flex: 2;
        }
        
        .course-subject {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .course-details {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .course-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-course {
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-edit-course {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        
        .btn-delete-course {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        
        .no-schedule {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .time-slots {
            display: grid;
            grid-template-rows: repeat(10, 60px);
            margin-right: 10px;
        }
        
        .hour-marker {
            border-bottom: 1px solid #eee;
            padding: 5px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: white;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .compact-view {
            display: none;
        }
        
        @media (max-width: 1200px) {
            .schedule-grid {
                display: none;
            }
            
            .compact-view {
                display: block;
            }
        }
    </style>
</head>
<body class="dashboard">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un cours..." id="globalSearch">
            </div>
            <div class="user-info">
                <span>Emplois du Temps</span>
                <img src="../../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <div class="schedule-header">
                <h1><i class="fas fa-calendar-alt"></i> Gestion des Emplois du Temps</h1>
                <p>Organisez les horaires de cours pour chaque classe</p>
                
                <div class="class-selector">
                    <div class="selector-group">
                        <label for="class_filter" style="color: white; display: block; margin-bottom: 8px;">
                            <i class="fas fa-chalkboard"></i> Sélectionner une classe
                        </label>
                        <select id="class_filter" class="form-control" onchange="if(this.value) window.location.href='schedules.php?class=' + this.value">
                            <option value="">-- Choisir une classe --</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['level_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($class_id > 0): ?>
                    <div class="selector-group">
                        <label for="year_filter" style="color: white; display: block; margin-bottom: 8px;">
                            <i class="fas fa-calendar"></i> Année académique
                        </label>
                        <select id="year_filter" class="form-control" onchange="if(this.value) window.location.href='schedules.php?class=<?php echo $class_id; ?>&year=' + this.value">
                            <?php
                            $current_year = date('Y');
                            for ($i = -1; $i <= 1; $i++):
                                $year = $current_year + $i;
                                $value = $year . '-' . ($year + 1);
                            ?>
                                <option value="<?php echo $value; ?>" 
                                    <?php echo ($_GET['year'] ?? ($current_year . '-' . ($current_year + 1))) == $value ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn-primary" onclick="openAddCourseModal()">
                            <i class="fas fa-plus-circle"></i> Ajouter un cours
                        </button>
                        <button type="button" class="btn-secondary" onclick="openGenerateModal()">
                            <i class="fas fa-cogs"></i> Générer automatiquement
                        </button>
                        <a href="print-schedule.php?class=<?php echo $class_id; ?>&year=<?php echo urlencode($academic_year); ?>" 
                           target="_blank" class="btn-secondary">
                            <i class="fas fa-print"></i> Imprimer
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($message || $error): ?>
                <div class="alert <?php echo $error ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo $error ? $error : $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($class_id > 0 && $selected_class): ?>
                <!-- Vue détaillée de l'emploi du temps -->
                <div class="form-section" style="margin-bottom: 30px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="margin: 0;">
                                <i class="fas fa-chalkboard"></i> 
                                <?php echo htmlspecialchars($selected_class['class_name'] . ' - ' . $selected_class['level_name']); ?>
                            </h3>
                            <p style="color: #666; margin: 5px 0 0;">
                                Professeur principal: <?php echo htmlspecialchars($selected_class['teacher_name'] ?? 'Non affecté'); ?> | 
                                Salle: <?php echo htmlspecialchars($selected_class['room_number'] ?? 'Non spécifiée'); ?> | 
                                Année: <?php echo htmlspecialchars($academic_year); ?>
                            </p>
                        </div>
                        <div style="color: #666; font-size: 0.9rem;">
                            <?php echo count($class_schedules); ?> cours programmés
                        </div>
                    </div>
                    
                    <!-- Légende -->
                    <div class="schedule-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #e3f2fd;"></div>
                            <span class="legend-label">Lettres</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #f3e5f5;"></div>
                            <span class="legend-label">Sciences</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #e8f5e8;"></div>
                            <span class="legend-label">Techniques</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #fff3e0;"></div>
                            <span class="legend-label">Arts</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #e0f7fa;"></div>
                            <span class="legend-label">Sport</span>
                        </div>
                    </div>
                    
                    <!-- Grille horaire (vue desktop) -->
                    <div class="schedule-grid">
                        <!-- En-tête des heures -->
                        <div class="time-header">Heures</div>
                        <?php foreach(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'] as $day): ?>
                            <div class="day-header"><?php echo $day; ?></div>
                        <?php endforeach; ?>
                        
                        <!-- Créneaux horaires -->
                        <?php 
                        $start_hour = 8; // 8h
                        $end_hour = 18; // 18h
                        
                        for ($hour = $start_hour; $hour < $end_hour; $hour++):
                            for ($minute = 0; $minute < 60; $minute += 60): // Heures pleines seulement
                                $time_str = sprintf('%02d:%02d', $hour, $minute);
                                $next_time = sprintf('%02d:%02d', $hour + ($minute + 60 >= 60 ? 1 : 0), ($minute + 60) % 60);
                        ?>
                            <div class="time-slot">
                                <?php echo $time_str; ?>
                            </div>
                            
                            <?php foreach(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'] as $day): ?>
                                <div class="course-cell" id="cell-<?php echo $day . '-' . $hour . '-' . $minute; ?>">
                                    <?php 
                                    // Trouver le cours à cette heure
                                    $current_course = null;
                                    foreach ($schedule_by_day[$day] as $course) {
                                        $course_start = strtotime($course['start_time']);
                                        $course_end = strtotime($course['end_time']);
                                        $cell_start = strtotime($time_str);
                                        $cell_end = strtotime($next_time);
                                        
                                        if ($course_start < $cell_end && $course_end > $cell_start) {
                                            $current_course = $course;
                                            break;
                                        }
                                    }
                                    
                                    if ($current_course):
                                        // Calculer la hauteur et position
                                        $duration = (strtotime($current_course['end_time']) - strtotime($current_course['start_time'])) / 3600;
                                        $top = (strtotime($current_course['start_time']) - strtotime('08:00:00')) / 3600 * 60;
                                        $height = $duration * 60;
                                        
                                        // Couleur selon la catégorie
                                        $colors = [
                                            'Lettres' => '#e3f2fd',
                                            'Sciences' => '#f3e5f5',
                                            'Techniques' => '#e8f5e8',
                                            'Arts' => '#fff3e0',
                                            'Sport' => '#e0f7fa'
                                        ];
                                        $color = $colors[$current_course['category']] ?? '#f5f5f5';
                                    ?>
                                        <div class="course-item" 
                                             style="background: <?php echo $color; ?>; border-color: <?php echo adjustColor($color, -30); ?>;"
                                             onclick="openEditModal(<?php echo $current_course['id']; ?>)">
                                            <div class="course-subject"><?php echo htmlspecialchars($current_course['subject_name']); ?></div>
                                            <?php if ($current_course['teacher_name']): ?>
                                                <div class="course-teacher"><?php echo htmlspecialchars($current_course['teacher_name']); ?></div>
                                            <?php endif; ?>
                                            <div class="course-time">
                                                <?php echo $current_course['start_formatted']; ?>-<?php echo $current_course['end_formatted']; ?>
                                            </div>
                                            <?php if ($current_course['room']): ?>
                                                <div class="course-room"><?php echo htmlspecialchars($current_course['room']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php 
                            endfor;
                        endfor; 
                        ?>
                    </div>
                    
                    <!-- Vue compacte (mobile) -->
                    <div class="compact-view">
                        <?php foreach(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'] as $day): ?>
                            <?php if (!empty($schedule_by_day[$day])): ?>
                                <div class="day-schedule">
                                    <div class="day-title"><?php echo $day; ?></div>
                                    <div class="course-list">
                                        <?php foreach($schedule_by_day[$day] as $course): 
                                            $colors = [
                                                'Lettres' => '#e3f2fd',
                                                'Sciences' => '#f3e5f5',
                                                'Techniques' => '#e8f5e8',
                                                'Arts' => '#fff3e0',
                                                'Sport' => '#e0f7fa'
                                            ];
                                            $color = $colors[$course['category']] ?? '#f5f5f5';
                                        ?>
                                            <div class="course-card" style="border-left: 4px solid <?php echo adjustColor($color, -30); ?>;">
                                                <div class="course-info">
                                                    <div class="course-subject">
                                                        <?php echo htmlspecialchars($course['subject_name']); ?>
                                                    </div>
                                                    <div class="course-details">
                                                        <span>
                                                            <i class="far fa-clock"></i> 
                                                            <?php echo $course['start_formatted']; ?>-<?php echo $course['end_formatted']; ?>
                                                        </span>
                                                        <?php if ($course['teacher_name']): ?>
                                                            <span>
                                                                <i class="fas fa-user-graduate"></i> 
                                                                <?php echo htmlspecialchars($course['teacher_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($course['room']): ?>
                                                            <span>
                                                                <i class="fas fa-door-open"></i> 
                                                                <?php echo htmlspecialchars($course['room']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="course-actions">
                                                    <a href="schedules.php?action=edit&id=<?php echo $course['id']; ?>&class=<?php echo $class_id; ?>" 
                                                       class="btn-course btn-edit-course">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete.php?type=schedule&id=<?php echo $course['id']; ?>&class=<?php echo $class_id; ?>" 
                                                       class="btn-course btn-delete-course"
                                                       onclick="return confirm('Supprimer ce cours ?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($class_schedules)): ?>
                        <div class="no-schedule">
                            <i class="fas fa-calendar-times fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                            <h3>Aucun cours programmé</h3>
                            <p>Commencez par ajouter des cours à l'emploi du temps</p>
                            <div style="margin-top: 20px;">
                                <button type="button" class="btn-primary" onclick="openAddCourseModal()">
                                    <i class="fas fa-plus-circle"></i> Ajouter un premier cours
                                </button>
                                <button type="button" class="btn-secondary" onclick="openGenerateModal()">
                                    <i class="fas fa-cogs"></i> Générer automatiquement
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Sélection de classe -->
                <div class="form-section">
                    <div style="text-align: center; padding: 50px 20px;">
                        <i class="fas fa-calendar-alt fa-4x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Sélectionnez une classe</h3>
                        <p style="color: #666; max-width: 500px; margin: 0 auto 30px;">
                            Choisissez une classe pour afficher et gérer son emploi du temps
                        </p>
                        
                        <div style="max-width: 400px; margin: 0 auto;">
                            <select id="initial_class_select" class="form-control" onchange="if(this.value) window.location.href='schedules.php?class=' + this.value" style="margin-bottom: 20px;">
                                <option value="">-- Choisir une classe --</option>
                                <?php foreach($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['level_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div style="color: #666; font-size: 0.9rem; margin-top: 20px;">
                                <i class="fas fa-info-circle"></i> 
                                <?php echo count($classes); ?> classe(s) disponible(s)
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal d'ajout de cours -->
    <div id="addCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Ajouter un cours</h3>
                <button type="button" class="close-modal" onclick="closeAddCourseModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="courseForm">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_subject_id">Matière *</label>
                            <select id="modal_subject_id" name="subject_id" required class="form-control">
                                <option value="">Sélectionner une matière</option>
                                <?php foreach($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['category'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="modal_teacher_id">Enseignant</label>
                            <select id="modal_teacher_id" name="teacher_id" class="form-control">
                                <option value="">Sélectionner un enseignant</option>
                                <?php foreach($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['specialization'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_day_of_week">Jour *</label>
                            <select id="modal_day_of_week" name="day_of_week" required class="form-control">
                                <option value="">Sélectionner un jour</option>
                                <option value="Lundi">Lundi</option>
                                <option value="Mardi">Mardi</option>
                                <option value="Mercredi">Mercredi</option>
                                <option value="Jeudi">Jeudi</option>
                                <option value="Vendredi">Vendredi</option>
                                <option value="Samedi">Samedi</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="modal_academic_year">Année académique *</label>
                            <select id="modal_academic_year" name="academic_year" required class="form-control">
                                <?php
                                $current_year = date('Y');
                                for ($i = -1; $i <= 1; $i++):
                                    $year = $current_year + $i;
                                    $value = $year . '-' . ($year + 1);
                                ?>
                                    <option value="<?php echo $value; ?>" 
                                        <?php echo $academic_year == $value ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_start_time">Heure de début *</label>
                            <input type="time" id="modal_start_time" name="start_time" 
                                   required class="form-control" value="08:00">
                        </div>
                        
                        <div class="form-group">
                            <label for="modal_end_time">Heure de fin *</label>
                            <input type="time" id="modal_end_time" name="end_time" 
                                   required class="form-control" value="09:00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_room">Salle</label>
                            <input type="text" id="modal_room" name="room" 
                                   class="form-control" placeholder="Ex: Salle 101">
                        </div>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Ajouter le cours
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeAddCourseModal()">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de génération automatique -->
    <div id="generateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-cogs"></i> Générer l'emploi du temps</h3>
                <button type="button" class="close-modal" onclick="closeGenerateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="generateForm">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Cette fonctionnalité génère automatiquement un emploi du temps optimal
                        en respectant les contraintes d'horaires et de salles.
                    </div>
                    
                    <div class="form-group">
                        <label for="gen_academic_year">Année académique *</label>
                        <select id="gen_academic_year" name="academic_year" required class="form-control">
                            <?php
                            $current_year = date('Y');
                            for ($i = -1; $i <= 1; $i++):
                                $year = $current_year + $i;
                                $value = $year . '-' . ($year + 1);
                            ?>
                                <option value="<?php echo $value; ?>" 
                                    <?php echo $academic_year == $value ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="gen_start_hour">Heure de début des cours</label>
                        <input type="time" id="gen_start_hour" name="start_hour" 
                               class="form-control" value="08:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="gen_end_hour">Heure de fin des cours</label>
                        <input type="time" id="gen_end_hour" name="end_hour" 
                               class="form-control" value="17:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="gen_duration">Durée des cours (minutes)</label>
                        <select id="gen_duration" name="duration" class="form-control">
                            <option value="45">45 minutes</option>
                            <option value="60" selected>60 minutes</option>
                            <option value="90">90 minutes</option>
                            <option value="120">120 minutes</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="gen_break">Pause entre les cours (minutes)</label>
                        <select id="gen_break" name="break" class="form-control">
                            <option value="5">5 minutes</option>
                            <option value="10" selected>10 minutes</option>
                            <option value="15">15 minutes</option>
                            <option value="20">20 minutes</option>
                        </select>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" name="generate_schedule" class="btn-primary">
                            <i class="fas fa-cogs"></i> Générer automatiquement
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeGenerateModal()">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../../../assets/js/dashboard.js"></script>
    <script>
        // Gestion des modals
        function openAddCourseModal() {
            document.getElementById('addCourseModal').style.display = 'block';
        }
        
        function closeAddCourseModal() {
            document.getElementById('addCourseModal').style.display = 'none';
        }
        
        function openGenerateModal() {
            document.getElementById('generateModal').style.display = 'block';
        }
        
        function closeGenerateModal() {
            document.getElementById('generateModal').style.display = 'none';
        }
        
        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            const modals = ['addCourseModal', 'generateModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Validation du formulaire de cours
        document.getElementById('courseForm').addEventListener('submit', function(e) {
            const subject = document.getElementById('modal_subject_id').value;
            const day = document.getElementById('modal_day_of_week').value;
            const startTime = document.getElementById('modal_start_time').value;
            const endTime = document.getElementById('modal_end_time').value;
            const year = document.getElementById('modal_academic_year').value;
            
            if (!subject) {
                e.preventDefault();
                alert('Veuillez sélectionner une matière');
                return false;
            }
            
            if (!day) {
                e.preventDefault();
                alert('Veuillez sélectionner un jour');
                return false;
            }
            
            if (!startTime || !endTime) {
                e.preventDefault();
                alert('Veuillez sélectionner les heures de début et fin');
                return false;
            }
            
            if (startTime >= endTime) {
                e.preventDefault();
                alert('L\'heure de fin doit être après l\'heure de début');
                return false;
            }
            
            if (!year) {
                e.preventDefault();
                alert('Veuillez sélectionner l\'année académique');
                return false;
            }
            
            return true;
        });
        
        // Ouvrir le modal d'édition
        function openEditModal(courseId) {
            window.location.href = `schedules.php?action=edit&id=${courseId}&class=<?php echo $class_id; ?>`;
        }
        
        // Ajuster automatiquement l'heure de fin
        document.getElementById('modal_start_time').addEventListener('change', function() {
            const startTime = this.value;
            const endInput = document.getElementById('modal_end_time');
            
            if (startTime && !endInput.value) {
                // Ajouter 1 heure par défaut
                const startDate = new Date(`2000-01-01T${startTime}`);
                startDate.setHours(startDate.getHours() + 1);
                const endTime = startDate.toTimeString().substr(0, 5);
                endInput.value = endTime;
            }
        });
        
        // Fonction pour ajuster les couleurs (déjà définie ailleurs)
        function adjustColor(hex, percent) {
            // Cette fonction devrait être définie dans votre school_config.php
            // Pour l'instant, on utilise une version simplifiée
            return hex; // À remplacer par la vraie fonction
        }
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Si on vient d'ajouter un cours, scroll vers la grille
            if (window.location.hash === '#added') {
                const grid = document.querySelector('.schedule-grid');
                if (grid) {
                    grid.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    </script>
</body>
</html>