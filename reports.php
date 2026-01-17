<?php
require_once __DIR__ . '/../../includes/config.php';
require_once '../../../includes/school_config.php';
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
$report_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Récupérer la configuration de l'école
$school_config = getSchoolConfig($school_id);
$grading_system = json_decode($school_config['grading_system'] ?? '[]', true);

// Récupérer les classes
$stmt = $pdo->prepare("
    SELECT c.*, l.level_name, l.cycle 
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

// Traitement de la génération de bulletins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_reports'])) {
    $class_id = $_POST['class_id'];
    $academic_year = $_POST['academic_year'];
    $term = $_POST['term'];
    
    // Validation
    if (empty($class_id) || empty($academic_year) || empty($term)) {
        $error = "Veuillez sélectionner la classe, l'année académique et le trimestre";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Récupérer tous les étudiants de la classe
            $stmt = $pdo->prepare("
                SELECT s.* FROM students s 
                WHERE s.current_class_id = ? 
                AND s.status = 'active'
                AND s.school_id = ?
            ");
            $stmt->execute([$class_id, $school_id]);
            $students = $stmt->fetchAll();
            
            $generated_count = 0;
            
            foreach ($students as $student) {
                // Vérifier si un bulletin existe déjà
                $stmt = $pdo->prepare("
                    SELECT id FROM report_cards 
                    WHERE student_id = ? 
                    AND class_id = ? 
                    AND academic_year = ? 
                    AND term = ?
                ");
                $stmt->execute([$student['id'], $class_id, $academic_year, $term]);
                
                if ($stmt->rowCount() === 0) {
                    // Calculer la moyenne de l'étudiant
                    $average = calculateStudentAverage($student['id'], $term, $academic_year);
                    
                    // Calculer le classement
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) + 1 as rank 
                        FROM students s2 
                        WHERE s2.current_class_id = ? 
                        AND s2.status = 'active'
                        AND s2.id IN (
                            SELECT student_id FROM grades 
                            WHERE term = ? AND academic_year = ?
                            GROUP BY student_id
                        )
                        AND (
                            SELECT AVG(score) FROM grades 
                            WHERE student_id = s2.id AND term = ? AND academic_year = ?
                        ) > ?
                    ");
                    $stmt->execute([$class_id, $term, $academic_year, $term, $academic_year, $average]);
                    $rank_result = $stmt->fetch();
                    $class_rank = $rank_result['rank'] ?? 1;
                    
                    // Récupérer les absences
                    $start_date = ($term == 'Trimestre 1') ? $academic_year . '-09-01' : 
                                 (($term == 'Trimestre 2') ? $academic_year . '-12-01' : 
                                 $academic_year . '-03-01');
                    $end_date = ($term == 'Trimestre 1') ? $academic_year . '-11-30' : 
                                (($term == 'Trimestre 2') ? $academic_year . '-02-28' : 
                                $academic_year . '-06-30');
                    
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as total_absences,
                               SUM(CASE WHEN justified = TRUE THEN 1 ELSE 0 END) as justified_absences
                        FROM absences
                        WHERE student_id = ? 
                        AND date BETWEEN ? AND ?
                    ");
                    $stmt->execute([$student['id'], $start_date, $end_date]);
                    $attendance = $stmt->fetch();
                    
                    // Insérer le bulletin
                    $stmt = $pdo->prepare("
                        INSERT INTO report_cards 
                        (student_id, class_id, academic_year, term, average_score, 
                         class_rank, total_students, attendance_days, absence_days, 
                         generated_by, is_published)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 90, ?, ?, FALSE)
                    ");
                    $stmt->execute([
                        $student['id'],
                        $class_id,
                        $academic_year,
                        $term,
                        $average,
                        $class_rank,
                        count($students),
                        $attendance['total_absences'] ?? 0,
                        $user_id
                    ]);
                    
                    $generated_count++;
                }
            }
            
            $pdo->commit();
            $message = "$generated_count bulletin(s) généré(s) avec succès";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la génération: " . $e->getMessage();
        }
    }
}

// Traitement de la publication des bulletins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_reports'])) {
    $report_ids = $_POST['report_ids'] ?? [];
    
    if (empty($report_ids)) {
        $error = "Aucun bulletin sélectionné";
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($report_ids), '?'));
            $stmt = $pdo->prepare("
                UPDATE report_cards 
                SET is_published = TRUE, published_at = NOW() 
                WHERE id IN ($placeholders) 
                AND student_id IN (SELECT id FROM students WHERE school_id = ?)
            ");
            $params = array_merge($report_ids, [$school_id]);
            $stmt->execute($params);
            
            $message = count($report_ids) . " bulletin(s) publié(s) avec succès";
            
        } catch (Exception $e) {
            $error = "Erreur lors de la publication: " . $e->getMessage();
        }
    }
}

// Récupérer la liste des bulletins
if ($action === 'list') {
    $class_filter = $_GET['class'] ?? '';
    $term_filter = $_GET['term'] ?? '';
    $year_filter = $_GET['year'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT rc.*, s.first_name, s.last_name, s.matricule, 
                   c.class_name, l.level_name, u.full_name as generated_by_name
            FROM report_cards rc 
            JOIN students s ON rc.student_id = s.id 
            JOIN classes c ON rc.class_id = c.id 
            JOIN school_levels l ON c.level_id = l.id 
            LEFT JOIN users u ON rc.generated_by = u.id 
            WHERE s.school_id = ?";
    $params = [$school_id];
    
    if (!empty($class_filter)) {
        $sql .= " AND rc.class_id = ?";
        $params[] = $class_filter;
    }
    
    if (!empty($term_filter)) {
        $sql .= " AND rc.term = ?";
        $params[] = $term_filter;
    }
    
    if (!empty($year_filter)) {
        $sql .= " AND rc.academic_year = ?";
        $params[] = $year_filter;
    }
    
    if (!empty($status_filter)) {
        if ($status_filter === 'published') {
            $sql .= " AND rc.is_published = TRUE";
        } else {
            $sql .= " AND rc.is_published = FALSE";
        }
    }
    
    // Total pour la pagination
    $count_sql = str_replace("SELECT rc.*, s.first_name, s.last_name, s.matricule, c.class_name, l.level_name, u.full_name as generated_by_name", 
                            "SELECT COUNT(*) as total", $sql);
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_reports = $stmt->fetchColumn();
    $total_pages = ceil($total_reports / $limit);
    
    // Données paginées
    $sql .= " ORDER BY rc.academic_year DESC, rc.term, rc.class_rank LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reports,
            SUM(CASE WHEN is_published = TRUE THEN 1 ELSE 0 END) as published_reports,
            AVG(average_score) as avg_score,
            MIN(average_score) as min_score,
            MAX(average_score) as max_score
        FROM report_cards rc 
        JOIN students s ON rc.student_id = s.id 
        WHERE s.school_id = ?
    ");
    $stmt->execute([$school_id]);
    $report_stats = $stmt->fetch();
}

// Récupérer les données d'un bulletin spécifique
if ($action === 'view' && $report_id > 0) {
    $stmt = $pdo->prepare("
        SELECT rc.*, s.first_name, s.last_name, s.matricule, s.birth_date,
               c.class_name, l.level_name, l.cycle,
               t.full_name as teacher_name,
               u.full_name as generated_by_name
        FROM report_cards rc 
        JOIN students s ON rc.student_id = s.id 
        JOIN classes c ON rc.class_id = c.id 
        JOIN school_levels l ON c.level_id = l.id 
        LEFT JOIN teachers t ON c.teacher_id = t.id 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN users ug ON rc.generated_by = ug.id 
        WHERE rc.id = ? AND s.school_id = ?
    ");
    $stmt->execute([$report_id, $school_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        header('Location: reports.php');
        exit();
    }
    
    // Récupérer les notes détaillées
    $stmt = $pdo->prepare("
        SELECT g.*, sj.subject_name, sj.coefficient, sj.max_score,
               t.full_name as teacher_name
        FROM grades g 
        JOIN subjects sj ON g.subject_id = sj.id 
        LEFT JOIN teachers t ON g.teacher_id = t.id 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE g.student_id = ? 
        AND g.term = ? 
        AND g.academic_year = ?
        ORDER BY sj.category, sj.subject_name
    ");
    $stmt->execute([$report['student_id'], $report['term'], $report['academic_year']]);
    $grades = $stmt->fetchAll();
    
    // Calculer les moyennes par matière
    $subject_averages = [];
    foreach ($grades as $grade) {
        $subject_id = $grade['subject_id'];
        if (!isset($subject_averages[$subject_id])) {
            $subject_averages[$subject_id] = [
                'subject_name' => $grade['subject_name'],
                'coefficient' => $grade['coefficient'],
                'scores' => [],
                'average' => 0
            ];
        }
        $subject_averages[$subject_id]['scores'][] = $grade['score'];
    }
    
    foreach ($subject_averages as &$subject) {
        if (!empty($subject['scores'])) {
            $subject['average'] = array_sum($subject['scores']) / count($subject['scores']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletins Scolaires - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <?php echo applySchoolTheme($school_id); ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .reports-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            text-align: center;
        }
        
        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 1.2rem;
        }
        
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .summary-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .grade-indicator {
            display: inline-block;
            width: 60px;
            height: 60px;
            line-height: 60px;
            border-radius: 50%;
            text-align: center;
            font-weight: bold;
            font-size: 1.2rem;
            color: white;
            margin: 0 10px;
        }
        
        .grade-excellent { background: #27ae60; }
        .grade-very-good { background: #2ecc71; }
        .grade-good { background: #f39c12; }
        .grade-fair { background: #e67e22; }
        .grade-pass { background: #d35400; }
        .grade-fail { background: #c0392b; }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .student-info h4 {
            margin: 0 0 5px;
            color: var(--primary-color);
        }
        
        .student-details {
            font-size: 0.9rem;
            color: #666;
        }
        
        .report-grade {
            text-align: center;
        }
        
        .report-details {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .detail-label {
            color: #666;
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .report-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-report {
            flex: 1;
            padding: 8px 12px;
            text-align: center;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-view { background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb; }
        .btn-edit { background: #fff3e0; color: #f57c00; border: 1px solid #ffe0b2; }
        .btn-print { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .btn-report:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .published-badge {
            background: #d4edda;
            color: #155724;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .draft-badge {
            background: #fff3cd;
            color: #856404;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .no-reports {
            text-align: center;
            padding: 40px;
            color: #666;
            background: #f8f9fa;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .bulletin-preview {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 40px;
            margin-top: 20px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .bulletin-header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .bulletin-school {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .bulletin-title {
            font-size: 1.5rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .bulletin-period {
            color: #666;
            font-size: 1.1rem;
        }
        
        .student-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .student-data {
            flex: 2;
        }
        
        .student-photo {
            flex: 1;
            text-align: center;
        }
        
        .photo-placeholder {
            width: 120px;
            height: 160px;
            background: #eee;
            border: 1px solid #ddd;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.9rem;
        }
        
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .grades-table th {
            background: #f1f3f4;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-weight: 600;
            color: #333;
        }
        
        .grades-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
        }
        
        .grades-table tr:hover {
            background: #f8f9fa;
        }
        
        .subject-category {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .coefficient {
            text-align: center;
            font-weight: 600;
            color: #666;
        }
        
        .grade-score {
            text-align: center;
            font-weight: 600;
        }
        
        .summary-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .summary-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .summary-item-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .summary-item-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .comments-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .comment-box {
            margin-bottom: 20px;
        }
        
        .comment-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: block;
        }
        
        .comment-content {
            min-height: 80px;
            padding: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #ddd;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin: 40px 0 10px;
        }
        
        .signature-label {
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body class="dashboard">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher bulletin..." id="globalSearch">
            </div>
            <div class="user-info">
                <span>Bulletins Scolaires</span>
                <img src="../../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <div class="page-actions">
                <h1 class="page-title">
                    <i class="fas fa-file-alt"></i>
                    <?php echo $action === 'list' ? 'Bulletins Scolaires' : 'Détails du Bulletin'; ?>
                </h1>
                
                <div class="search-filters">
                    <?php if ($action === 'list'): ?>
                        <form method="GET" class="filter-form" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                            <div class="filter-group">
                                <select name="class">
                                    <option value="">Toutes les classes</option>
                                    <?php foreach($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" 
                                            <?php echo ($_GET['class'] ?? '') == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['level_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <select name="term">
                                    <option value="">Tous les trimestres</option>
                                    <option value="Trimestre 1" <?php echo ($_GET['term'] ?? '') == 'Trimestre 1' ? 'selected' : ''; ?>>Trimestre 1</option>
                                    <option value="Trimestre 2" <?php echo ($_GET['term'] ?? '') == 'Trimestre 2' ? 'selected' : ''; ?>>Trimestre 2</option>
                                    <option value="Trimestre 3" <?php echo ($_GET['term'] ?? '') == 'Trimestre 3' ? 'selected' : ''; ?>>Trimestre 3</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <select name="year">
                                    <option value="">Toutes les années</option>
                                    <?php
                                    $current_year = date('Y');
                                    for ($i = -2; $i <= 0; $i++):
                                        $year = $current_year + $i;
                                        $value = $year . '-' . ($year + 1);
                                    ?>
                                        <option value="<?php echo $value; ?>" 
                                            <?php echo ($_GET['year'] ?? '') == $value ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <select name="status">
                                    <option value="">Tous les statuts</option>
                                    <option value="published" <?php echo ($_GET['status'] ?? '') == 'published' ? 'selected' : ''; ?>>Publiés</option>
                                    <option value="draft" <?php echo ($_GET['status'] ?? '') == 'draft' ? 'selected' : ''; ?>>Brouillons</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <a href="reports.php" class="btn-secondary">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </a>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($action === 'list'): ?>
                        <button type="button" class="btn-primary" onclick="openGenerateModal()">
                            <i class="fas fa-plus-circle"></i> Générer des bulletins
                        </button>
                    <?php else: ?>
                        <a href="reports.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour à la liste
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($message || $error): ?>
                <div class="alert <?php echo $error ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo $error ? $error : $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                
                <!-- Résumé statistique -->
                <div class="reports-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: #2ecc71;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="summary-value"><?php echo $report_stats['total_reports'] ?? 0; ?></div>
                        <div class="summary-label">Bulletins générés</div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon" style="background: #3498db;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="summary-value"><?php echo $report_stats['published_reports'] ?? 0; ?></div>
                        <div class="summary-label">Bulletins publiés</div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon" style="background: #f39c12;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="summary-value"><?php echo number_format($report_stats['avg_score'] ?? 0, 2); ?>/20</div>
                        <div class="summary-label">Moyenne générale</div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon" style="background: #9b59b6;">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="summary-value"><?php echo number_format($report_stats['max_score'] ?? 0, 2); ?>/20</div>
                        <div class="summary-label">Meilleure note</div>
                    </div>
                </div>
                
                <!-- Liste des bulletins -->
                <?php if ($reports && count($reports) > 0): ?>
                    <form method="POST" id="bulletinForm">
                        <div class="form-section">
                            <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h3 style="margin: 0;">Bulletins générés</h3>
                                <div>
                                    <button type="button" class="btn-secondary" onclick="selectAllReports()">
                                        <i class="fas fa-check-square"></i> Tout sélectionner
                                    </button>
                                    <button type="submit" name="publish_reports" class="btn-primary">
                                        <i class="fas fa-paper-plane"></i> Publier les sélectionnés
                                    </button>
                                </div>
                            </div>
                            
                            <div class="reports-grid">
                                <?php foreach($reports as $report_item): 
                                    // Déterminer la mention
                                    $mention = getMention($report_item['average_score']);
                                    $grade_class = 'grade-' . str_replace(' ', '-', strtolower($mention));
                                ?>
                                    <div class="report-card">
                                        <div class="report-header">
                                            <div class="student-info">
                                                <h4><?php echo htmlspecialchars($report_item['first_name'] . ' ' . $report_item['last_name']); ?></h4>
                                                <div class="student-details">
                                                    <?php echo htmlspecialchars($report_item['matricule']); ?> • 
                                                    <?php echo htmlspecialchars($report_item['class_name']); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <?php if ($report_item['is_published']): ?>
                                                    <span class="published-badge">Publié</span>
                                                <?php else: ?>
                                                    <span class="draft-badge">Brouillon</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="report-details">
                                            <div class="detail-row">
                                                <span class="detail-label">Trimestre:</span>
                                                <span class="detail-value"><?php echo $report_item['term']; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Année académique:</span>
                                                <span class="detail-value"><?php echo $report_item['academic_year']; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Moyenne:</span>
                                                <span class="detail-value"><?php echo number_format($report_item['average_score'], 2); ?>/20</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Classement:</span>
                                                <span class="detail-value"><?php echo $report_item['class_rank']; ?> sur <?php echo $report_item['total_students']; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Mention:</span>
                                                <span class="detail-value"><?php echo $mention; ?></span>
                                            </div>
                                        </div>
                                        
                                        <div style="text-align: center; margin: 15px 0;">
                                            <div class="grade-indicator <?php echo $grade_class; ?>">
                                                <?php echo number_format($report_item['average_score'], 1); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="report-actions">
                                            <a href="reports.php?action=view&id=<?php echo $report_item['id']; ?>" 
                                               class="btn-report btn-view">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                            <a href="print-bulletin.php?id=<?php echo $report_item['id']; ?>" 
                                               target="_blank" class="btn-report btn-print">
                                                <i class="fas fa-print"></i> Imprimer
                                            </a>
                                            <?php if (!$report_item['is_published']): ?>
                                                <label style="display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                                    <input type="checkbox" name="report_ids[]" 
                                                           value="<?php echo $report_item['id']; ?>" 
                                                           style="margin-right: 5px;">
                                                    <span style="font-size: 0.8rem; color: #666;">Sélectionner</span>
                                                </label>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </form>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="page-link">
                                    <i class="fas fa-chevron-left"></i> Précédent
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="page-link">
                                    Suivant <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-reports">
                        <i class="fas fa-file-alt fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucun bulletin généré</h3>
                        <p>Générez des bulletins pour vos étudiants</p>
                        <button type="button" class="btn-primary" style="margin-top: 15px;" onclick="openGenerateModal()">
                            <i class="fas fa-plus-circle"></i> Générer des bulletins
                        </button>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($action === 'view'): ?>
                <!-- Affichage détaillé du bulletin -->
                <div class="bulletin-preview" id="bulletinPreview">
                    <div class="bulletin-header">
                        <div class="bulletin-school"><?php echo htmlspecialchars($school['school_name']); ?></div>
                        <div class="bulletin-title">BULLETIN SCOLAIRE</div>
                        <div class="bulletin-period">
                            <?php echo $report['term']; ?> - Année académique <?php echo $report['academic_year']; ?>
                        </div>
                    </div>
                    
                    <div class="student-header">
                        <div class="student-data">
                            <h3 style="margin: 0 0 15px; color: #333;">
                                <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                            </h3>
                            <div style="margin-bottom: 10px;">
                                <strong>Matricule:</strong> <?php echo htmlspecialchars($report['matricule']); ?>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <strong>Classe:</strong> <?php echo htmlspecialchars($report['class_name'] . ' - ' . $report['level_name']); ?>
                            </div>
                            <div>
                                <strong>Professeur principal:</strong> 
                                <?php echo htmlspecialchars($report['teacher_name'] ?? 'Non affecté'); ?>
                            </div>
                        </div>
                        
                        <div class="student-photo">
                            <div class="photo-placeholder">
                                Photo de l'élève
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tableau des notes -->
                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th>Matières</th>
                                <th>Coefficient</th>
                                <th>Devoir 1</th>
                                <th>Devoir 2</th>
                                <th>Composition</th>
                                <th>Moyenne</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_category = '';
                            $total_coefficient = 0;
                            $weighted_sum = 0;
                            
                            foreach ($subject_averages as $subject_id => $subject): 
                                // Afficher la catégorie si elle change
                                $category = $subject['category'] ?? 'Autre';
                                if ($category !== $current_category):
                                    $current_category = $category;
                            ?>
                                <tr class="subject-category">
                                    <td colspan="6" style="font-weight: 600; padding: 12px;">
                                        <?php echo strtoupper($current_category); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td class="coefficient"><?php echo $subject['coefficient']; ?></td>
                                <td class="grade-score">
                                    <?php 
                                    $devoir1 = isset($subject['scores'][0]) ? number_format($subject['scores'][0], 1) : '-';
                                    echo $devoir1;
                                    ?>
                                </td>
                                <td class="grade-score">
                                    <?php 
                                    $devoir2 = isset($subject['scores'][1]) ? number_format($subject['scores'][1], 1) : '-';
                                    echo $devoir2;
                                    ?>
                                </td>
                                <td class="grade-score">
                                    <?php 
                                    $composition = isset($subject['scores'][2]) ? number_format($subject['scores'][2], 1) : '-';
                                    echo $composition;
                                    ?>
                                </td>
                                <td class="grade-score" style="color: var(--primary-color);">
                                    <?php echo number_format($subject['average'], 2); ?>
                                </td>
                            </tr>
                            
                            <?php 
                                $total_coefficient += $subject['coefficient'];
                                $weighted_sum += $subject['average'] * $subject['coefficient'];
                            ?>
                            <?php endforeach; ?>
                            
                            <!-- Total et moyenne -->
                            <tr style="background: #f1f3f4; font-weight: 600;">
                                <td colspan="5" style="text-align: right; padding: 15px;">TOTAL / MOYENNE GÉNÉRALE</td>
                                <td style="text-align: center; color: var(--primary-color); font-size: 1.1rem;">
                                    <?php 
                                    $overall_average = $total_coefficient > 0 ? $weighted_sum / $total_coefficient : 0;
                                    echo number_format($overall_average, 2);
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Résumé statistique -->
                    <div class="summary-section">
                        <div class="summary-item">
                            <div class="summary-item-label">Moyenne Générale</div>
                            <div class="summary-item-value"><?php echo number_format($report['average_score'], 2); ?>/20</div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="summary-item-label">Classement</div>
                            <div class="summary-item-value">
                                <?php echo $report['class_rank']; ?><sup>ème</sup> / <?php echo $report['total_students']; ?>
                            </div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="summary-item-label">Mention</div>
                            <div class="summary-item-value" style="color: <?php 
                                $average = $report['average_score'];
                                if ($average >= 16) echo '#27ae60';
                                elseif ($average >= 14) echo '#2ecc71';
                                elseif ($average >= 12) echo '#f39c12';
                                elseif ($average >= 10) echo '#e67e22';
                                elseif ($average >= 8) echo '#d35400';
                                else echo '#c0392b';
                            ?>;">
                                <?php echo getMention($report['average_score']); ?>
                            </div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="summary-item-label">Absences</div>
                            <div class="summary-item-value">
                                <?php echo $report['absence_days']; ?> jours
                            </div>
                        </div>
                    </div>
                    
                    <!-- Commentaires -->
                    <div class="comments-section">
                        <div class="comment-box">
                            <span class="comment-label">Appréciation du Professeur Principal:</span>
                            <div class="comment-content">
                                <?php echo nl2br(htmlspecialchars($report['teacher_comment'] ?? 'Aucun commentaire')); ?>
                            </div>
                        </div>
                        
                        <div class="comment-box">
                            <span class="comment-label">Observation du Chef d'Établissement:</span>
                            <div class="comment-content">
                                <?php echo nl2br(htmlspecialchars($report['principal_comment'] ?? 'Aucun commentaire')); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Signatures -->
                    <div class="signatures">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-label">Le Professeur Principal</div>
                        </div>
                        
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-label">Le Chef d'Établissement</div>
                        </div>
                        
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-label">Les Parents</div>
                        </div>
                    </div>
                    
                    <div class="receipt-footer" style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9rem;">
                        Bulletin généré le <?php echo date('d/m/Y H:i', strtotime($report['generated_at'])); ?> 
                        par <?php echo htmlspecialchars($report['generated_by_name']); ?>
                        <?php if ($report['is_published']): ?>
                            • Publié le <?php echo date('d/m/Y H:i', strtotime($report['published_at'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 30px; text-align: center;">
                    <button onclick="window.print()" class="btn-primary">
                        <i class="fas fa-print"></i> Imprimer le bulletin
                    </button>
                    <?php if (!$report['is_published']): ?>
                        <a href="publish-bulletin.php?id=<?php echo $report_id; ?>" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Publier le bulletin
                        </a>
                    <?php endif; ?>
                    <a href="reports.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal pour générer des bulletins -->
    <div id="generateModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Générer des bulletins</h3>
                <button type="button" class="close" onclick="closeGenerateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="generateForm">
                    <div class="form-group">
                        <label for="modal_class_id">Classe *</label>
                        <select id="modal_class_id" name="class_id" required class="form-control">
                            <option value="">Sélectionner une classe</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['level_name']); ?>
                                </option>
                            <?php endforeach; ?>
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
                                    <?php echo $i == 0 ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_term">Trimestre *</label>
                        <select id="modal_term" name="term" required class="form-control">
                            <option value="">Sélectionner un trimestre</option>
                            <option value="Trimestre 1">Trimestre 1</option>
                            <option value="Trimestre 2">Trimestre 2</option>
                            <option value="Trimestre 3">Trimestre 3</option>
                        </select>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" name="generate_reports" class="btn-primary">
                            <i class="fas fa-cogs"></i> Générer les bulletins
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
        // Modal pour générer des bulletins
        function openGenerateModal() {
            document.getElementById('generateModal').style.display = 'block';
        }
        
        function closeGenerateModal() {
            document.getElementById('generateModal').style.display = 'none';
        }
        
        // Fermer la modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('generateModal');
            if (event.target === modal) {
                closeGenerateModal();
            }
        }
        
        // Sélectionner tous les bulletins
        function selectAllReports() {
            const checkboxes = document.querySelectorAll('input[name="report_ids[]"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
        }
        
        // Style de la modal
        const modalStyle = document.createElement('style');
        modalStyle.textContent = `
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                overflow: auto;
            }
            
            .modal-content {
                background-color: white;
                margin: 10% auto;
                padding: 0;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                animation: modalSlideIn 0.3s;
            }
            
            @keyframes modalSlideIn {
                from { transform: translateY(-100px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
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
            
            .close {
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 5px;
            }
            
            .close:hover {
                opacity: 0.8;
            }
            
            .modal-body {
                padding: 30px;
            }
        `;
        document.head.appendChild(modalStyle);
        
        // Impression du bulletin
        if (window.location.search.includes('action=view')) {
            window.addEventListener('load', function() {
                // Ajouter un bouton d'impression
                const printBtn = document.createElement('button');
                printBtn.innerHTML = '<i class="fas fa-print"></i> Imprimer le bulletin';
                printBtn.className = 'btn-primary';
                printBtn.style.marginRight = '10px';
                printBtn.onclick = function() {
                    window.print();
                };
                
                const actions = document.querySelector('.form-actions');
                if (actions) {
                    actions.prepend(printBtn);
                }
                
                // Styles pour l'impression
                const printStyles = `
                    @media print {
                        body * {
                            visibility: hidden;
                        }
                        
                        #bulletinPreview, #bulletinPreview * {
                            visibility: visible;
                        }
                        
                        #bulletinPreview {
                            position: absolute;
                            left: 0;
                            top: 0;
                            width: 100%;
                            box-shadow: none;
                            border: none;
                        }
                        
                        .form-actions, .top-bar, .sidebar {
                            display: none !important;
                        }
                    }
                `;
                
                const styleSheet = document.createElement('style');
                styleSheet.type = 'text/css';
                styleSheet.innerText = printStyles;
                document.head.appendChild(styleSheet);
            });
        }
    </script>
</body>
</html>