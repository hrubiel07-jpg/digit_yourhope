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
$class_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Récupérer les niveaux de l'école
$stmt = $pdo->prepare("SELECT * FROM school_levels WHERE school_id = ? OR school_id IS NULL ORDER BY order_num");
$stmt->execute([$school_id]);
$levels = $stmt->fetchAll();

// Récupérer les enseignants de l'école
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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'school_id' => $school_id,
        'level_id' => $_POST['level_id'],
        'class_name' => sanitize($_POST['class_name']),
        'class_code' => sanitize($_POST['class_code']),
        'capacity' => $_POST['capacity'] ? intval($_POST['capacity']) : null,
        'room_number' => sanitize($_POST['room_number']),
        'teacher_id' => $_POST['teacher_id'] ? intval($_POST['teacher_id']) : null,
        'academic_year' => sanitize($_POST['academic_year']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Validation
    if (empty($data['class_name']) || empty($data['level_id'])) {
        $error = "Le nom de la classe et le niveau sont obligatoires";
    } else {
        try {
            if ($action === 'edit' && $class_id > 0) {
                // Mettre à jour
                $sql = "UPDATE classes SET ";
                $params = [];
                $updates = [];
                
                foreach ($data as $key => $value) {
                    if ($key !== 'school_id') {
                        $updates[] = "$key = ?";
                        $params[] = $value;
                    }
                }
                
                $sql .= implode(', ', $updates) . " WHERE id = ? AND school_id = ?";
                $params[] = $class_id;
                $params[] = $school_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "Classe mise à jour avec succès";
            } else {
                // Ajouter une nouvelle classe
                $columns = implode(', ', array_keys($data));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));
                
                $sql = "INSERT INTO classes ($columns) VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
                
                $message = "Classe créée avec succès";
            }
            
        } catch (Exception $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}

// Récupérer les données de la classe pour l'édition
$class = null;
if ($action === 'edit' && $class_id > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, l.level_name, l.cycle 
        FROM classes c 
        JOIN school_levels l ON c.level_id = l.id 
        WHERE c.id = ? AND c.school_id = ?
    ");
    $stmt->execute([$class_id, $school_id]);
    $class = $stmt->fetch();
    
    if (!$class) {
        header('Location: classes.php');
        exit();
    }
}

// Récupérer la liste des classes
if ($action === 'list') {
    $level_filter = $_GET['level'] ?? '';
    $cycle_filter = $_GET['cycle'] ?? '';
    
    $sql = "SELECT c.*, l.level_name, l.cycle, 
                   COUNT(DISTINCT s.id) as student_count,
                   t.full_name as teacher_name
            FROM classes c 
            JOIN school_levels l ON c.level_id = l.id 
            LEFT JOIN students s ON c.id = s.current_class_id AND s.status = 'active'
            LEFT JOIN teachers te ON c.teacher_id = te.id 
            LEFT JOIN users t ON te.user_id = t.id 
            WHERE c.school_id = ?";
    $params = [$school_id];
    
    if (!empty($level_filter)) {
        $sql .= " AND c.level_id = ?";
        $params[] = $level_filter;
    }
    
    if (!empty($cycle_filter)) {
        $sql .= " AND l.cycle = ?";
        $params[] = $cycle_filter;
    }
    
    $sql .= " GROUP BY c.id ORDER BY l.order_num, c.class_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes_list = $stmt->fetchAll();
    
    // Statistiques par cycle
    $stmt = $pdo->prepare("
        SELECT l.cycle, COUNT(DISTINCT c.id) as class_count, COUNT(DISTINCT s.id) as student_count
        FROM classes c 
        JOIN school_levels l ON c.level_id = l.id 
        LEFT JOIN students s ON c.id = s.current_class_id AND s.status = 'active'
        WHERE c.school_id = ? AND c.is_active = TRUE
        GROUP BY l.cycle
    ");
    $stmt->execute([$school_id]);
    $cycle_stats = $stmt->fetchAll();
}

// Récupérer la configuration pour le thème
$school_config = getSchoolConfig($school_id);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Classes - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <?php echo applySchoolTheme($school_id); ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .classes-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .classes-header h1 {
            color: white;
            margin-bottom: 10px;
        }
        
        .classes-header p {
            opacity: 0.9;
        }
        
        .cycle-stats {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .cycle-stat {
            flex: 1;
            min-width: 150px;
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .cycle-stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .cycle-stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .class-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            transition: all 0.3s;
            position: relative;
        }
        
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        
        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .class-title h3 {
            margin: 0 0 5px;
            color: var(--primary-color);
        }
        
        .class-cycle {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            background: #f0f9ff;
            color: var(--primary-color);
        }
        
        .class-stats {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .class-info {
            margin: 15px 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .class-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .btn-class {
            flex: 1;
            padding: 8px 12px;
            text-align: center;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-class:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-students {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        
        .btn-schedule {
            background: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #e1bee7;
        }
        
        .btn-edit {
            background: #fff3e0;
            color: #f57c00;
            border: 1px solid #ffe0b2;
        }
        
        .inactive-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #f8d7da;
            color: #721c24;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .no-classes {
            text-align: center;
            padding: 40px;
            color: #666;
            background: #f8f9fa;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .capacity-meter {
            height: 5px;
            background: #eee;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .capacity-fill {
            height: 100%;
            background: var(--primary-color);
            border-radius: 3px;
            transition: width 0.3s;
        }
    </style>
</head>
<body class="dashboard">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher une classe..." id="globalSearch">
            </div>
            <div class="user-info">
                <span>Gestion des Classes</span>
                <img src="../../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <div class="classes-header">
                <h1><i class="fas fa-chalkboard"></i> Gestion des Classes</h1>
                <p>Organisez les classes de votre établissement par niveau et cycle</p>
                
                <div class="cycle-stats">
                    <?php foreach($cycle_stats as $stat): ?>
                        <div class="cycle-stat">
                            <div class="cycle-stat-value"><?php echo $stat['class_count']; ?></div>
                            <div class="cycle-stat-label">Classes <?php echo $stat['cycle']; ?></div>
                            <div class="cycle-stat-value" style="font-size: 1.2rem;"><?php echo $stat['student_count']; ?> élèves</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if ($message || $error): ?>
                <div class="alert <?php echo $error ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo $error ? $error : $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="page-actions">
                <div class="search-filters">
                    <?php if ($action === 'list'): ?>
                        <form method="GET" class="filter-form" style="display: flex; gap: 10px; align-items: flex-end;">
                            <div class="filter-group">
                                <label>Cycle</label>
                                <select name="cycle">
                                    <option value="">Tous les cycles</option>
                                    <option value="Primaire" <?php echo ($_GET['cycle'] ?? '') == 'Primaire' ? 'selected' : ''; ?>>Primaire</option>
                                    <option value="Secondaire I" <?php echo ($_GET['cycle'] ?? '') == 'Secondaire I' ? 'selected' : ''; ?>>Secondaire I</option>
                                    <option value="Secondaire II" <?php echo ($_GET['cycle'] ?? '') == 'Secondaire II' ? 'selected' : ''; ?>>Secondaire II</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Niveau</label>
                                <select name="level">
                                    <option value="">Tous les niveaux</option>
                                    <?php foreach($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>" 
                                            <?php echo ($_GET['level'] ?? '') == $level['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['level_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <a href="classes.php" class="btn-secondary">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </a>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($action === 'list'): ?>
                        <a href="classes.php?action=add" class="btn-primary">
                            <i class="fas fa-plus-circle"></i> Nouvelle Classe
                        </a>
                    <?php else: ?>
                        <a href="classes.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour à la liste
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($action === 'list'): ?>
                
                <?php if ($classes_list && count($classes_list) > 0): ?>
                    <div class="classes-grid">
                        <?php foreach($classes_list as $class_item): 
                            $capacity_percent = $class_item['capacity'] > 0 ? 
                                min(100, ($class_item['student_count'] / $class_item['capacity']) * 100) : 0;
                        ?>
                            <div class="class-card">
                                <?php if (!$class_item['is_active']): ?>
                                    <span class="inactive-badge">Inactive</span>
                                <?php endif; ?>
                                
                                <div class="class-header">
                                    <div class="class-title">
                                        <h3><?php echo htmlspecialchars($class_item['class_name']); ?></h3>
                                        <span class="class-cycle"><?php echo $class_item['cycle']; ?></span>
                                    </div>
                                    <div>
                                        <span style="font-size: 0.9rem; color: #666;"><?php echo $class_item['level_name']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="class-stats">
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo $class_item['student_count']; ?></span>
                                        <span class="stat-label">Élèves</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo $class_item['capacity'] ?: '∞'; ?></span>
                                        <span class="stat-label">Capacité</span>
                                    </div>
                                </div>
                                
                                <?php if ($class_item['capacity'] > 0): ?>
                                    <div class="capacity-meter">
                                        <div class="capacity-fill" style="width: <?php echo $capacity_percent; ?>%;"></div>
                                    </div>
                                    <small style="color: #666; display: block; text-align: center;">
                                        <?php echo number_format($capacity_percent, 0); ?>% rempli
                                    </small>
                                <?php endif; ?>
                                
                                <div class="class-info">
                                    <div class="info-row">
                                        <span>Salle:</span>
                                        <span><?php echo htmlspecialchars($class_item['room_number'] ?: 'Non spécifié'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span>Professeur principal:</span>
                                        <span><?php echo htmlspecialchars($class_item['teacher_name'] ?: 'Non affecté'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span>Année académique:</span>
                                        <span><?php echo htmlspecialchars($class_item['academic_year'] ?: date('Y') . '-' . (date('Y') + 1)); ?></span>
                                    </div>
                                </div>
                                
                                <div class="class-actions">
                                    <a href="students.php?class=<?php echo $class_item['id']; ?>" class="btn-class btn-students">
                                        <i class="fas fa-users"></i> Élèves
                                    </a>
                                    <a href="schedules.php?class=<?php echo $class_item['id']; ?>" class="btn-class btn-schedule">
                                        <i class="fas fa-calendar-alt"></i> Emploi du temps
                                    </a>
                                    <a href="classes.php?action=edit&id=<?php echo $class_item['id']; ?>" class="btn-class btn-edit">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    <div class="no-classes">
                        <i class="fas fa-chalkboard fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucune classe créée</h3>
                        <p>Commencez par créer les classes de votre établissement</p>
                        <a href="classes.php?action=add" class="btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus-circle"></i> Créer la première classe
                        </a>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Formulaire d'ajout/modification -->
                <div class="form-section">
                    <form method="POST" id="classForm">
                        <h3 style="margin-bottom: 20px;">
                            <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                            <?php echo $action === 'add' ? 'Créer une nouvelle classe' : 'Modifier la classe'; ?>
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="level_id">Niveau *</label>
                                <select id="level_id" name="level_id" required class="form-control">
                                    <option value="">Sélectionner un niveau</option>
                                    <?php foreach($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>" 
                                            <?php echo ($class['level_id'] ?? '') == $level['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['level_name'] . ' (' . $level['cycle'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="class_name">Nom de la classe *</label>
                                <input type="text" id="class_name" name="class_name" 
                                       value="<?php echo htmlspecialchars($class['class_name'] ?? ''); ?>" 
                                       required class="form-control" 
                                       placeholder="Ex: 6ème A, Terminale C, etc.">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="class_code">Code de la classe</label>
                                <input type="text" id="class_code" name="class_code" 
                                       value="<?php echo htmlspecialchars($class['class_code'] ?? ''); ?>" 
                                       class="form-control" 
                                       placeholder="Ex: 6A, TleC">
                            </div>
                            
                            <div class="form-group">
                                <label for="capacity">Capacité maximale</label>
                                <input type="number" id="capacity" name="capacity" 
                                       value="<?php echo htmlspecialchars($class['capacity'] ?? ''); ?>" 
                                       class="form-control" min="1" max="100">
                                <small style="color: #666;">Laisser vide pour illimité</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="room_number">Salle de classe</label>
                                <input type="text" id="room_number" name="room_number" 
                                       value="<?php echo htmlspecialchars($class['room_number'] ?? ''); ?>" 
                                       class="form-control" 
                                       placeholder="Ex: Salle 101, Bâtiment A">
                            </div>
                            
                            <div class="form-group">
                                <label for="teacher_id">Professeur principal</label>
                                <select id="teacher_id" name="teacher_id" class="form-control">
                                    <option value="">Non affecté</option>
                                    <?php foreach($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" 
                                            <?php echo ($class['teacher_id'] ?? '') == $teacher['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['specialization'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="academic_year">Année académique</label>
                                <select id="academic_year" name="academic_year" class="form-control">
                                    <?php
                                    $current_year = date('Y');
                                    for ($i = -2; $i <= 2; $i++):
                                        $year = $current_year + $i;
                                        $value = $year . '-' . ($year + 1);
                                    ?>
                                        <option value="<?php echo $value; ?>" 
                                            <?php echo ($class['academic_year'] ?? ($current_year . '-' . ($current_year + 1))) == $value ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="is_active" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" id="is_active" name="is_active" 
                                           <?php echo ($class['is_active'] ?? 1) ? 'checked' : ''; ?> 
                                           style="width: auto;">
                                    <span>Classe active</span>
                                </label>
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    Une classe inactive n'apparaîtra pas dans les listes déroulantes
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> 
                                <?php echo $action === 'add' ? 'Créer la classe' : 'Mettre à jour'; ?>
                            </button>
                            <a href="classes.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../../../assets/js/dashboard.js"></script>
    <script>
        // Génération automatique du code de classe
        const classNameInput = document.getElementById('class_name');
        const classCodeInput = document.getElementById('class_code');
        
        function generateClassCode() {
            if (classNameInput.value && !classCodeInput.value) {
                // Extraire les chiffres et premières lettres
                const name = classNameInput.value;
                let code = '';
                
                // Extraire les chiffres
                const numbers = name.match(/\d+/g);
                if (numbers) {
                    code += numbers.join('');
                }
                
                // Extraire les premières lettres des mots
                const words = name.split(' ');
                words.forEach(word => {
                    if (word.match(/[A-Za-z]/) && !word.match(/\d/)) {
                        code += word.charAt(0).toUpperCase();
                    }
                });
                
                if (code) {
                    classCodeInput.value = code;
                }
            }
        }
        
        classNameInput.addEventListener('blur', generateClassCode);
        
        // Validation du formulaire
        document.getElementById('classForm').addEventListener('submit', function(e) {
            const levelId = document.getElementById('level_id').value;
            const className = document.getElementById('class_name').value.trim();
            
            if (!levelId) {
                e.preventDefault();
                alert('Veuillez sélectionner un niveau');
                return false;
            }
            
            if (!className) {
                e.preventDefault();
                alert('Veuillez saisir le nom de la classe');
                return false;
            }
            
            return true;
        });
        
        // Afficher les informations du cycle sélectionné
        const levelSelect = document.getElementById('level_id');
        const levelInfo = document.createElement('div');
        levelInfo.style.marginTop = '5px';
        levelInfo.style.color = '#666';
        levelInfo.style.fontSize = '0.9rem';
        levelSelect.parentNode.appendChild(levelInfo);
        
        const levelsData = <?php echo json_encode($levels); ?>;
        
        function updateLevelInfo() {
            const selectedLevelId = levelSelect.value;
            const level = levelsData.find(l => l.id == selectedLevelId);
            
            if (level) {
                levelInfo.textContent = `Cycle: ${level.cycle}, Ordre: ${level.order_num}`;
            } else {
                levelInfo.textContent = '';
            }
        }
        
        levelSelect.addEventListener('change', updateLevelInfo);
        
        // Initialiser l'info du niveau si déjà sélectionné
        if (levelSelect.value) {
            updateLevelInfo();
        }
    </script>
</body>
</html>