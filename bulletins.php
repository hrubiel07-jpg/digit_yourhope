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
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action == 'new' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        // Créer un nouveau bulletin
        $student_id = $_POST['student_id'];
        $term = $_POST['term'];
        $school_year = $_POST['school_year'];
        $class_id = $_POST['class_id'];
        
        // Vérifier si le bulletin existe déjà
        $stmt = $pdo->prepare("
            SELECT id FROM bulletins 
            WHERE student_id = ? AND term = ? AND school_year = ?
        ");
        $stmt->execute([$student_id, $term, $school_year]);
        
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO bulletins (student_id, school_year, term, class_id, academic_year, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $school_year, $term, $class_id, date('Y'), $user_id]);
            $bulletin_id = $pdo->lastInsertId();
            
            $_SESSION['success_message'] = "Bulletin créé avec succès!";
            header("Location: edit-bulletin.php?id=$bulletin_id");
            exit();
        } else {
            $_SESSION['error_message'] = "Un bulletin existe déjà pour cet élève et ce trimestre.";
            header("Location: bulletins.php");
            exit();
        }
    }
    
    if ($action == 'delete' && isset($_GET['id'])) {
        $bulletin_id = $_GET['id'];
        
        // Vérifier que le bulletin appartient à l'école
        $stmt = $pdo->prepare("
            DELETE b FROM bulletins b 
            JOIN students s ON b.student_id = s.id 
            WHERE b.id = ? AND s.school_id = ?
        ");
        $stmt->execute([$bulletin_id, $school_id]);
        
        $_SESSION['success_message'] = "Bulletin supprimé avec succès!";
        header("Location: bulletins.php");
        exit();
    }
    
    if ($action == 'publish' && isset($_GET['id'])) {
        $bulletin_id = $_GET['id'];
        
        $stmt = $pdo->prepare("
            UPDATE bulletins b
            JOIN students s ON b.student_id = s.id 
            SET b.status = 'published', b.published_at = NOW()
            WHERE b.id = ? AND s.school_id = ?
        ");
        $stmt->execute([$bulletin_id, $school_id]);
        
        $_SESSION['success_message'] = "Bulletin publié avec succès!";
        header("Location: bulletins.php");
        exit();
    }
}

// Récupérer les bulletins
$year = $_GET['year'] ?? date('Y');
$term = $_GET['term'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$status = $_GET['status'] ?? '';

$query = "
    SELECT b.*, s.first_name, s.last_name, s.matricule, c.class_name, 
           COUNT(bg.id) as grade_count,
           u.full_name as created_by_name
    FROM bulletins b
    JOIN students s ON b.student_id = s.id
    LEFT JOIN classes c ON b.class_id = c.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN bulletin_grades bg ON b.id = bg.bulletin_id
    WHERE s.school_id = ?
";

$params = [$school_id];
$conditions = [];

if ($year) {
    $conditions[] = "b.school_year = ?";
    $params[] = $year;
}

if ($term) {
    $conditions[] = "b.term = ?";
    $params[] = $term;
}

if ($class_id) {
    $conditions[] = "b.class_id = ?";
    $params[] = $class_id;
}

if ($status) {
    $conditions[] = "b.status = ?";
    $params[] = $status;
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " GROUP BY b.id ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bulletins = $stmt->fetchAll();

// Récupérer les classes et années pour les filtres
$classes = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name")->execute([$school_id])->fetchAll();
$years = $pdo->query("SELECT DISTINCT school_year FROM bulletins ORDER BY school_year DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Bulletins - <?php echo $school['school_name']; ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bulletin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
            color: #3498db;
        }
        
        .bulletin-filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .bulletin-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .bulletin-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-draft { background: #f39c12; color: white; }
        .status-published { background: #2ecc71; color: white; }
        .status-archived { background: #95a5a6; color: white; }
        
        .bulletin-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #3498db;
        }
        
        .bulletin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .bulletin-info h4 {
            margin: 0 0 5px;
        }
        
        .bulletin-average {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .bulletin-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 2fr 2fr;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .bulletin-row.header {
            font-weight: bold;
            background: #f8f9fa;
            border-radius: 5px 5px 0 0;
        }
        
        .bulletin-row:last-child {
            border-bottom: none;
        }
        
        .quick-actions {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body class="dashboard">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un bulletin..." id="searchInput">
            </div>
            <div class="user-info">
                <span><?php echo $school['school_name']; ?></span>
                <img src="../../../assets/images/default-school.png" alt="Logo">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">
                <i class="fas fa-file-alt"></i> Gestion des Bulletins
                <button class="btn-primary" onclick="showNewBulletinModal()">
                    <i class="fas fa-plus"></i> Nouveau bulletin
                </button>
            </h1>
            
            <!-- Statistiques -->
            <div class="bulletin-stats">
                <?php
                // Calculer les statistiques
                $stats = [
                    'total' => $pdo->prepare("SELECT COUNT(*) FROM bulletins b JOIN students s ON b.student_id = s.id WHERE s.school_id = ?")->execute([$school_id])->fetchColumn(),
                    'published' => $pdo->prepare("SELECT COUNT(*) FROM bulletins b JOIN students s ON b.student_id = s.id WHERE s.school_id = ? AND b.status = 'published'")->execute([$school_id])->fetchColumn(),
                    'draft' => $pdo->prepare("SELECT COUNT(*) FROM bulletins b JOIN students s ON b.student_id = s.id WHERE s.school_id = ? AND b.status = 'draft'")->execute([$school_id])->fetchColumn(),
                    'this_year' => $pdo->prepare("SELECT COUNT(*) FROM bulletins b JOIN students s ON b.student_id = s.id WHERE s.school_id = ? AND b.school_year = ?")->execute([$school_id, date('Y')])->fetchColumn()
                ];
                ?>
                <div class="stat-card">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Bulletins au total</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['published']; ?></h3>
                    <p>Bulletins publiés</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['draft']; ?></h3>
                    <p>Brouillons</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['this_year']; ?></h3>
                    <p>Cette année</p>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="bulletin-filters">
                <form method="GET" class="filter-row">
                    <div class="filter-group">
                        <label>Année scolaire</label>
                        <select name="year" onchange="this.form.submit()">
                            <option value="">Toutes les années</option>
                            <?php foreach($years as $y): ?>
                                <option value="<?php echo $y['school_year']; ?>" <?php echo $year == $y['school_year'] ? 'selected' : ''; ?>>
                                    <?php echo $y['school_year']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Trimestre</label>
                        <select name="term" onchange="this.form.submit()">
                            <option value="">Tous les trimestres</option>
                            <option value="trimestre1" <?php echo $term == 'trimestre1' ? 'selected' : ''; ?>>Trimestre 1</option>
                            <option value="trimestre2" <?php echo $term == 'trimestre2' ? 'selected' : ''; ?>>Trimestre 2</option>
                            <option value="trimestre3" <?php echo $term == 'trimestre3' ? 'selected' : ''; ?>>Trimestre 3</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Classe</label>
                        <select name="class_id" onchange="this.form.submit()">
                            <option value="">Toutes les classes</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo $c['class_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Statut</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">Tous les statuts</option>
                            <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                            <option value="published" <?php echo $status == 'published' ? 'selected' : ''; ?>>Publié</option>
                            <option value="archived" <?php echo $status == 'archived' ? 'selected' : ''; ?>>Archivé</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Liste des bulletins -->
            <div class="form-section">
                <div class="bulletin-row header">
                    <div>Élève</div>
                    <div>Année</div>
                    <div>Trimestre</div>
                    <div>Classe</div>
                    <div>Statut</div>
                    <div>Actions</div>
                </div>
                
                <?php if (empty($bulletins)): ?>
                    <div class="bulletin-card">
                        <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                            Aucun bulletin trouvé. Créez votre premier bulletin.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach($bulletins as $bulletin): ?>
                        <div class="bulletin-card">
                            <div class="bulletin-header">
                                <div class="bulletin-info">
                                    <h4>
                                        <?php echo htmlspecialchars($bulletin['first_name'] . ' ' . $bulletin['last_name']); ?>
                                        <small style="color: #7f8c8d;">(<?php echo $bulletin['matricule']; ?>)</small>
                                    </h4>
                                    <p style="margin: 5px 0 0; color: #666;">
                                        Créé le <?php echo date('d/m/Y', strtotime($bulletin['created_at'])); ?>
                                        <?php if ($bulletin['created_by_name']): ?>
                                            par <?php echo $bulletin['created_by_name']; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if ($bulletin['average']): ?>
                                    <div class="bulletin-average">
                                        <?php echo number_format($bulletin['average'], 2); ?>/20
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 20px;">
                                    <div>
                                        <small style="color: #7f8c8d;">Année scolaire</small>
                                        <div><?php echo $bulletin['school_year']; ?></div>
                                    </div>
                                    <div>
                                        <small style="color: #7f8c8d;">Trimestre</small>
                                        <div>
                                            <?php 
                                            $term_labels = [
                                                'trimestre1' => '1er Trimestre',
                                                'trimestre2' => '2ème Trimestre',
                                                'trimestre3' => '3ème Trimestre',
                                                'semestre1' => '1er Semestre',
                                                'semestre2' => '2ème Semestre',
                                                'annuel' => 'Annuel'
                                            ];
                                            echo $term_labels[$bulletin['term']] ?? $bulletin['term'];
                                            ?>
                                        </div>
                                    </div>
                                    <div>
                                        <small style="color: #7f8c8d;">Classe</small>
                                        <div><?php echo $bulletin['class_name']; ?></div>
                                    </div>
                                    <div>
                                        <small style="color: #7f8c8d;">Statut</small>
                                        <div>
                                            <span class="bulletin-status status-<?php echo $bulletin['status']; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'draft' => 'Brouillon',
                                                    'published' => 'Publié',
                                                    'archived' => 'Archivé'
                                                ];
                                                echo $status_labels[$bulletin['status']] ?? $bulletin['status'];
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="quick-actions">
                                    <a href="edit-bulletin.php?id=<?php echo $bulletin['id']; ?>" class="btn-small">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="print-bulletin.php?id=<?php echo $bulletin['id']; ?>" target="_blank" class="btn-small btn-primary">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $bulletin['id']; ?>" 
                                       onclick="return confirm('Supprimer ce bulletin ?')" 
                                       class="btn-small btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php if ($bulletin['status'] == 'draft'): ?>
                                        <a href="?action=publish&id=<?php echo $bulletin['id']; ?>" 
                                           onclick="return confirm('Publier ce bulletin ?')" 
                                           class="btn-small btn-success">
                                            <i class="fas fa-paper-plane"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Nouveau Bulletin -->
    <div class="modal" id="newBulletinModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Créer un nouveau bulletin</h3>
                <button class="modal-close" onclick="closeNewBulletinModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="?action=new">
                    <div class="form-group">
                        <label>Élève *</label>
                        <select name="student_id" required onchange="updateClassInfo(this)">
                            <option value="">Sélectionner un élève</option>
                            <?php
                            $students = $pdo->prepare("
                                SELECT s.id, s.first_name, s.last_name, s.matricule, c.class_name, c.id as class_id
                                FROM students s
                                LEFT JOIN classes c ON s.current_class_id = c.id
                                WHERE s.school_id = ? AND s.status = 'active'
                                ORDER BY s.last_name, s.first_name
                            ")->execute([$school_id])->fetchAll();
                            
                            foreach($students as $student):
                            ?>
                                <option value="<?php echo $student['id']; ?>" 
                                        data-class="<?php echo $student['class_id']; ?>"
                                        data-classname="<?php echo htmlspecialchars($student['class_name'] ?? 'Non affecté'); ?>">
                                    <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                    (<?php echo $student['matricule']; ?>)
                                    - <?php echo $student['class_name'] ?? 'Non affecté'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Classe</label>
                        <input type="text" id="classDisplay" readonly>
                        <input type="hidden" name="class_id" id="classIdInput">
                    </div>
                    
                    <div class="form-group">
                        <label>Année scolaire *</label>
                        <input type="text" name="school_year" value="<?php echo date('Y'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Trimestre *</label>
                        <select name="term" required>
                            <option value="trimestre1">1er Trimestre</option>
                            <option value="trimestre2">2ème Trimestre</option>
                            <option value="trimestre3">3ème Trimestre</option>
                            <option value="semestre1">1er Semestre</option>
                            <option value="semestre2">2ème Semestre</option>
                            <option value="annuel">Annuel</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> Créer le bulletin
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showNewBulletinModal() {
            document.getElementById('newBulletinModal').style.display = 'flex';
        }
        
        function closeNewBulletinModal() {
            document.getElementById('newBulletinModal').style.display = 'none';
        }
        
        function updateClassInfo(select) {
            const selectedOption = select.options[select.selectedIndex];
            const classId = selectedOption.getAttribute('data-class');
            const className = selectedOption.getAttribute('data-classname');
            
            document.getElementById('classDisplay').value = className;
            document.getElementById('classIdInput').value = classId;
        }
        
        // Recherche en direct
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = this.value.toLowerCase();
            const bulletinCards = document.querySelectorAll('.bulletin-card');
            
            bulletinCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>