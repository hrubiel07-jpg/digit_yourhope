<?php
// CORRECTION : Chemin correct
require_once __DIR__ . '/../../includes/config.php';

// S'assurer que $pdo existe
if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
            DB_USER, 
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch(PDOException $e) {
        error_log("Erreur de connexion à la base de données: " . $e->getMessage());
        die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
    }
}

requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer l'école
$stmt = $pdo->prepare("SELECT id FROM schools WHERE user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

// Récupérer les étudiants
$action = $_GET['action'] ?? 'list';
$class_id = $_GET['class_id'] ?? null;
$search = $_GET['search'] ?? '';

// Requête pour récupérer les étudiants
$query = "SELECT s.*, c.class_name 
          FROM students s 
          LEFT JOIN classes c ON s.current_class_id = c.id 
          WHERE s.school_id = ?";
$params = [$school_id];

if ($class_id) {
    $query .= " AND s.current_class_id = ?";
    $params[] = $class_id;
}

if ($search) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.matricule LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY s.last_name, s.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Récupérer les classes pour le filtre
$classes = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = ? AND is_active = 1 ORDER BY class_name");
$classes->execute([$school_id]);
$classes = $classes->fetchAll();

// Charger la configuration de l'école
require_once __DIR__ . '/../../includes/school_config.php';
$school_config = getSchoolConfig($school_id);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Élèves - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/admin.css">
    <?php echo applySchoolTheme($school_id); ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <?php 
    // Inclure la sidebar
    $sidebar_path = __DIR__ . '/sidebar.php';
    if (file_exists($sidebar_path)) {
        include $sidebar_path;
    }
    ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher élève..." id="studentSearch">
            </div>
            <div class="user-info">
                <span>Gestion des Élèves</span>
            </div>
        </header>
        
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Liste des Élèves</h2>
                    <div class="actions">
                        <a href="?action=add" class="btn-primary">
                            <i class="fas fa-user-plus"></i> Ajouter un élève
                        </a>
                        <a href="export-students.php" class="btn-secondary">
                            <i class="fas fa-file-export"></i> Exporter
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Filtres -->
                    <div class="filters" style="margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap;">
                        <div class="filter-group">
                            <select id="classFilter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">Toutes les classes</option>
                                <?php foreach($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <input type="text" id="searchInput" placeholder="Nom, prénom ou matricule..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <button id="applyFilters" class="btn-primary" style="padding: 8px 20px;">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                    </div>
                    
                    <!-- Liste des élèves -->
                    <?php if ($action === 'list'): ?>
                        <?php if (count($students) > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom & Prénom</th>
                                        <th>Classe</th>
                                        <th>Téléphone</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($students as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['matricule']); ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="width: 30px; height: 30px; border-radius: 50%; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                                        <?php echo substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['class_name'] ?? 'Non affecté'); ?></td>
                                            <td><?php echo htmlspecialchars($student['phone'] ?? ''); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $student['status']; ?>">
                                                    <?php 
                                                    $status_labels = [
                                                        'active' => 'Actif',
                                                        'suspended' => 'Suspendu',
                                                        'graduated' => 'Diplômé',
                                                        'transferred' => 'Transféré',
                                                        'expelled' => 'Exclu'
                                                    ];
                                                    echo $status_labels[$student['status']] ?? $student['status'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons" style="display: flex; gap: 5px;">
                                                    <a href="?action=view&id=<?php echo $student['id']; ?>" class="btn-small" title="Voir">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="?action=edit&id=<?php echo $student['id']; ?>" class="btn-small btn-primary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?action=delete&id=<?php echo $student['id']; ?>" 
                                                       class="btn-small btn-danger" 
                                                       title="Supprimer"
                                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet élève ?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Pagination -->
                            <div style="margin-top: 20px; text-align: center;">
                                <p>Total : <?php echo count($students); ?> élève(s)</p>
                            </div>
                        <?php else: ?>
                            <div class="no-data" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                <i class="fas fa-user-graduate fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                                <h3>Aucun élève trouvé</h3>
                                <p><?php echo $search || $class_id ? 'Essayez de modifier vos critères de recherche.' : 'Commencez par ajouter votre premier élève.'; ?></p>
                                <?php if (!$search && !$class_id): ?>
                                    <a href="?action=add" class="btn-primary" style="margin-top: 20px;">
                                        <i class="fas fa-user-plus"></i> Ajouter un élève
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    
                    <?php elseif ($action === 'add'): ?>
                        <!-- Formulaire d'ajout d'élève -->
                        <div class="form-section">
                            <h3>Ajouter un nouvel élève</h3>
                            <form method="POST" action="process-student.php">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="matricule">Matricule *</label>
                                        <input type="text" id="matricule" name="matricule" required 
                                               class="form-control" placeholder="Ex: 2024-001">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="first_name">Prénom *</label>
                                        <input type="text" id="first_name" name="first_name" required 
                                               class="form-control" placeholder="Prénom de l'élève">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="last_name">Nom *</label>
                                        <input type="text" id="last_name" name="last_name" required 
                                               class="form-control" placeholder="Nom de l'élève">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="gender">Sexe *</label>
                                        <select id="gender" name="gender" required class="form-control">
                                            <option value="">Sélectionnez...</option>
                                            <option value="M">Masculin</option>
                                            <option value="F">Féminin</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="birth_date">Date de naissance</label>
                                        <input type="date" id="birth_date" name="birth_date" 
                                               class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="current_class_id">Classe</label>
                                        <select id="current_class_id" name="current_class_id" class="form-control">
                                            <option value="">Sélectionnez une classe</option>
                                            <?php foreach($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>">
                                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="phone">Téléphone</label>
                                        <input type="tel" id="phone" name="phone" 
                                               class="form-control" placeholder="+221 77 123 4567">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" 
                                               class="form-control" placeholder="élève@email.com">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="nationality">Nationalité</label>
                                        <input type="text" id="nationality" name="nationality" 
                                               class="form-control" value="Congolaise">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="address">Adresse</label>
                                    <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                                </div>
                                
                                <div class="form-actions" style="margin-top: 30px;">
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-save"></i> Enregistrer
                                    </button>
                                    <a href="?" class="btn-secondary">
                                        <i class="fas fa-times"></i> Annuler
                                    </a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Gestion des filtres
        document.getElementById('applyFilters').addEventListener('click', function() {
            const classId = document.getElementById('classFilter').value;
            const search = document.getElementById('searchInput').value;
            
            let url = 'students.php?';
            const params = [];
            
            if (classId) params.push(`class_id=${classId}`);
            if (search) params.push(`search=${encodeURIComponent(search)}`);
            
            window.location.href = url + params.join('&');
        });
        
        // Recherche par Enter
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('applyFilters').click();
            }
        });
        
        // Recherche globale
        document.getElementById('studentSearch').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    window.location.href = `students.php?search=${encodeURIComponent(query)}`;
                }
            }
        });
    </script>
</body>
</html>