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
    if (isset($_POST['create_exam'])) {
        $exam_type = $_POST['exam_type'];
        $academic_year = $_POST['academic_year'];
        $registration_start = $_POST['registration_start'];
        $registration_end = $_POST['registration_end'];
        $exam_date = $_POST['exam_date'];
        $center_name = $_POST['center_name'];
        $center_code = $_POST['center_code'];
        
        $stmt = $pdo->prepare("
            INSERT INTO exam_registrations 
            (school_id, exam_type, academic_year, registration_period_start, 
             registration_period_end, exam_date, center_name, center_code, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$school_id, $exam_type, $academic_year, $registration_start, 
                       $registration_end, $exam_date, $center_name, $center_code, $user_id]);
        
        $_SESSION['success_message'] = "Session d'examen créée avec succès!";
        header("Location: exams.php");
        exit();
    }
    
    if (isset($_POST['register_students'])) {
        $exam_id = $_POST['exam_id'];
        $student_ids = $_POST['student_ids'] ?? [];
        
        foreach ($student_ids as $student_id) {
            // Générer un numéro d'inscription
            $registration_number = generateRegistrationNumber($exam_id, $student_id);
            
            $stmt = $pdo->prepare("
                INSERT INTO exam_candidates (exam_registration_id, student_id, registration_number)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE registration_number = ?
            ");
            $stmt->execute([$exam_id, $student_id, $registration_number, $registration_number]);
        }
        
        // Mettre à jour le nombre d'inscrits
        $stmt = $pdo->prepare("
            UPDATE exam_registrations 
            SET total_registered = (SELECT COUNT(*) FROM exam_candidates WHERE exam_registration_id = ?)
            WHERE id = ?
        ");
        $stmt->execute([$exam_id, $exam_id]);
        
        $_SESSION['success_message'] = count($student_ids) . " élève(s) inscrit(s) avec succès!";
        header("Location: exams.php?view=candidates&id=" . $exam_id);
        exit();
    }
}

// Récupérer les sessions d'examen
$stmt = $pdo->prepare("
    SELECT e.*, 
           COUNT(ec.id) as registered_count,
           COUNT(CASE WHEN ec.status = 'passed' THEN 1 END) as passed_count
    FROM exam_registrations e
    LEFT JOIN exam_candidates ec ON e.id = ec.exam_registration_id
    WHERE e.school_id = ?
    GROUP BY e.id
    ORDER BY e.academic_year DESC, e.exam_type
");
$stmt->execute([$school_id]);
$exam_sessions = $stmt->fetchAll();

// Fonction pour générer un numéro d'inscription
function generateRegistrationNumber($exam_id, $student_id) {
    global $pdo;
    
    // Récupérer le code de l'école
    $school_code = $pdo->prepare("
        SELECT exam_code FROM schools WHERE id = (
            SELECT school_id FROM exam_registrations WHERE id = ?
        )
    ")->execute([$exam_id])->fetchColumn();
    
    if (!$school_code) {
        $school_code = 'SCH' . str_pad($exam_id, 4, '0', STR_PAD_LEFT);
    }
    
    // Récupérer le type d'examen
    $exam_type = $pdo->prepare("SELECT exam_type FROM exam_registrations WHERE id = ?")
        ->execute([$exam_id])->fetchColumn();
    
    $type_codes = [
        'CEPE' => 'CP',
        'BEPC' => 'BP',
        'BAC' => 'BC',
        'BTS' => 'BT',
        'CAP' => 'CA'
    ];
    
    $exam_code = $type_codes[$exam_type] ?? 'EX';
    
    // Récupérer l'année
    $year = $pdo->prepare("SELECT LEFT(academic_year, 4) FROM exam_registrations WHERE id = ?")
        ->execute([$exam_id])->fetchColumn();
    
    // Numéro séquentiel
    $sequence = $pdo->prepare("
        SELECT COUNT(*) + 1 FROM exam_candidates 
        WHERE exam_registration_id = ?
    ")->execute([$exam_id])->fetchColumn();
    
    return $school_code . $exam_code . $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des examens - <?php echo $school['school_name']; ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .exam-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .exam-stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .exam-stat-card h3 {
            font-size: 2rem;
            margin: 0;
            color: #3498db;
        }
        
        .exam-session {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #3498db;
        }
        
        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .exam-type {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 500;
            color: white;
            font-size: 0.9rem;
        }
        
        .type-cepe { background: #2ecc71; }
        .type-bepc { background: #3498db; }
        .type-bac { background: #9b59b6; }
        .type-bts { background: #f39c12; }
        
        .exam-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-open { background: #2ecc71; color: white; }
        .status-closed { background: #e74c3c; color: white; }
        .status-completed { background: #95a5a6; color: white; }
        
        .exam-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .candidates-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
            padding: 15px;
            background: #f8f9fa;
            font-weight: bold;
            border-bottom: 1px solid #eee;
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        
        .table-row:last-child {
            border-bottom: none;
        }
        
        .candidate-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        .status-registered { background: #3498db; color: white; }
        .status-passed { background: #2ecc71; color: white; }
        .status-failed { background: #e74c3c; color: white; }
        .status-absent { background: #95a5a6; color: white; }
        
        .batch-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .select-all {
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="dashboard">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un examen..." id="searchInput">
            </div>
            <div class="user-info">
                <span><?php echo $school['school_name']; ?></span>
                <img src="../../../assets/images/default-school.png" alt="Logo">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">
                <i class="fas fa-graduation-cap"></i> Gestion des examens nationaux
                <button class="btn-primary" onclick="showNewExamModal()">
                    <i class="fas fa-plus"></i> Nouvelle session
                </button>
            </h1>
            
            <!-- Statistiques -->
            <div class="exam-stats">
                <?php
                $stats = [
                    'total' => count($exam_sessions),
                    'open' => count(array_filter($exam_sessions, fn($e) => $e['status'] == 'open')),
                    'registered' => array_sum(array_column($exam_sessions, 'registered_count')),
                    'passed' => array_sum(array_column($exam_sessions, 'passed_count'))
                ];
                ?>
                <div class="exam-stat-card">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Sessions d'examen</p>
                </div>
                <div class="exam-stat-card">
                    <h3><?php echo $stats['open']; ?></h3>
                    <p>Sessions ouvertes</p>
                </div>
                <div class="exam-stat-card">
                    <h3><?php echo $stats['registered']; ?></h3>
                    <p>Élèves inscrits</p>
                </div>
                <div class="exam-stat-card">
                    <h3><?php echo $stats['passed']; ?></h3>
                    <p>Élèves admis</p>
                </div>
            </div>
            
            <!-- Sessions d'examen -->
            <div class="form-section">
                <h3>Sessions d'examen</h3>
                
                <?php if (empty($exam_sessions)): ?>
                    <div class="exam-session">
                        <p style="text-align: center; color: #7f8c8d; padding: 30px;">
                            Aucune session d'examen créée. Créez votre première session.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach($exam_sessions as $exam): ?>
                        <div class="exam-session">
                            <div class="exam-header">
                                <div>
                                    <h4 style="margin: 0 0 10px;">
                                        <?php 
                                        $exam_names = [
                                            'CEPE' => 'Certificat d\'Études Primaires Élémentaires',
                                            'BEPC' => 'Brevet d\'Études du Premier Cycle',
                                            'BAC' => 'Baccalauréat',
                                            'BTS' => 'Brevet de Technicien Supérieur',
                                            'CAP' => 'Certificat d\'Aptitude Professionnelle'
                                        ];
                                        echo $exam_names[$exam['exam_type']] ?? $exam['exam_type'];
                                        ?>
                                    </h4>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span class="exam-type type-<?php echo strtolower($exam['exam_type']); ?>">
                                            <?php echo $exam['exam_type']; ?>
                                        </span>
                                        <span class="exam-status status-<?php echo $exam['status']; ?>">
                                            <?php 
                                            $status_labels = [
                                                'open' => 'Inscriptions ouvertes',
                                                'closed' => 'Inscriptions closes',
                                                'completed' => 'Terminé'
                                            ];
                                            echo $status_labels[$exam['status']] ?? $exam['status'];
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="exam-actions">
                                    <a href="?view=candidates&id=<?php echo $exam['id']; ?>" class="btn-small btn-primary">
                                        <i class="fas fa-users"></i> Candidats
                                    </a>
                                    <a href="export_exam.php?id=<?php echo $exam['id']; ?>" class="btn-small">
                                        <i class="fas fa-download"></i> Exporter
                                    </a>
                                    <button class="btn-small" onclick="editExam(<?php echo $exam['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="exam-info">
                                <div class="info-item">
                                    <div class="info-label">Année scolaire</div>
                                    <div><strong><?php echo $exam['academic_year']; ?></strong></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Période d'inscription</div>
                                    <div>
                                        <?php echo date('d/m/Y', strtotime($exam['registration_period_start'])); ?>
                                        au
                                        <?php echo date('d/m/Y', strtotime($exam['registration_period_end'])); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Date d'examen</div>
                                    <div>
                                        <?php echo $exam['exam_date'] ? date('d/m/Y', strtotime($exam['exam_date'])) : 'À définir'; ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Centre d'examen</div>
                                    <div><?php echo $exam['center_name'] ?: 'À définir'; ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Candidats</div>
                                    <div>
                                        <strong><?php echo $exam['registered_count']; ?></strong> inscrits
                                        <?php if ($exam['passed_count'] > 0): ?>
                                            <br><span style="color: #2ecc71;"><?php echo $exam['passed_count']; ?> admis</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Candidats pour une session spécifique -->
            <?php if (isset($_GET['view']) && $_GET['view'] == 'candidates' && isset($_GET['id'])): 
                $exam_id = $_GET['id'];
                
                // Récupérer les candidats
                $stmt = $pdo->prepare("
                    SELECT ec.*, s.first_name, s.last_name, s.matricule, s.birthdate, 
                           c.class_name, s.parent_phone
                    FROM exam_candidates ec
                    JOIN students s ON ec.student_id = s.id
                    LEFT JOIN classes c ON s.current_class_id = c.id
                    WHERE ec.exam_registration_id = ?
                    ORDER BY s.last_name, s.first_name
                ");
                $stmt->execute([$exam_id]);
                $candidates = $stmt->fetchAll();
                
                // Récupérer les élèves éligibles non encore inscrits
                $stmt = $pdo->prepare("
                    SELECT s.*, c.class_name 
                    FROM students s
                    LEFT JOIN classes c ON s.current_class_id = c.id
                    LEFT JOIN exam_candidates ec ON s.id = ec.student_id AND ec.exam_registration_id = ?
                    WHERE s.school_id = ? AND s.status = 'active' AND ec.id IS NULL
                    ORDER BY s.last_name, s.first_name
                ");
                $stmt->execute([$exam_id, $school_id]);
                $eligible_students = $stmt->fetchAll();
            ?>
                <div class="form-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>Gestion des candidats</h3>
                        <button class="btn-primary" onclick="showRegisterModal()">
                            <i class="fas fa-user-plus"></i> Inscrire des élèves
                        </button>
                    </div>
                    
                    <!-- Actions groupées -->
                    <div class="batch-actions">
                        <div class="select-all">
                            <label>
                                <input type="checkbox" id="selectAllCandidates">
                                Sélectionner tous les candidats visibles
                            </label>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button class="btn-small" onclick="bulkUpdateStatus('passed')">
                                <i class="fas fa-check"></i> Marquer comme admis
                            </button>
                            <button class="btn-small btn-danger" onclick="bulkUpdateStatus('failed')">
                                <i class="fas fa-times"></i> Marquer comme échoué
                            </button>
                            <button class="btn-small" onclick="bulkUpdateStatus('absent')">
                                <i class="fas fa-user-slash"></i> Marquer comme absent
                            </button>
                            <button class="btn-small" onclick="exportSelected()">
                                <i class="fas fa-file-excel"></i> Exporter sélection
                            </button>
                        </div>
                    </div>
                    
                    <!-- Liste des candidats -->
                    <div class="candidates-table">
                        <div class="table-header">
                            <div>Élève</div>
                            <div>Matricule</div>
                            <div>Classe</div>
                            <div>N° Inscription</div>
                            <div>Résultat</div>
                            <div>Actions</div>
                        </div>
                        
                        <?php if (empty($candidates)): ?>
                            <div class="table-row">
                                <div colspan="6" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                    Aucun candidat inscrit pour cette session.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach($candidates as $candidate): ?>
                                <div class="table-row">
                                    <div>
                                        <strong><?php echo $candidate['last_name'] . ' ' . $candidate['first_name']; ?></strong>
                                        <br>
                                        <small style="color: #7f8c8d;">
                                            Né(e) le <?php echo date('d/m/Y', strtotime($candidate['birthdate'])); ?>
                                        </small>
                                    </div>
                                    <div><?php echo $candidate['matricule']; ?></div>
                                    <div><?php echo $candidate['class_name']; ?></div>
                                    <div>
                                        <?php if ($candidate['registration_number']): ?>
                                            <code><?php echo $candidate['registration_number']; ?></code>
                                        <?php else: ?>
                                            <span style="color: #e74c3c;">À générer</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="candidate-status status-<?php echo $candidate['status']; ?>">
                                            <?php 
                                            $status_labels = [
                                                'registered' => 'Inscrit',
                                                'passed' => 'Admis',
                                                'failed' => 'Échoué',
                                                'absent' => 'Absent',
                                                'awaiting' => 'En attente'
                                            ];
                                            echo $status_labels[$candidate['status']] ?? $candidate['status'];
                                            ?>
                                        </span>
                                        <?php if ($candidate['score']): ?>
                                            <br>
                                            <small><?php echo $candidate['score']; ?>/20</small>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <button class="btn-small" onclick="editCandidate(<?php echo $candidate['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-small btn-danger" 
                                                onclick="deleteCandidate(<?php echo $candidate['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Modal d'inscription d'élèves -->
                <div class="modal" id="registerModal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Inscrire des élèves</h3>
                            <button class="modal-close" onclick="closeRegisterModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" id="registerForm">
                                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                
                                <div class="form-group">
                                    <label>Sélectionnez les élèves à inscrire</label>
                                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;">
                                        <?php if (empty($eligible_students)): ?>
                                            <p style="color: #7f8c8d; text-align: center; padding: 20px;">
                                                Tous les élèves sont déjà inscrits.
                                            </p>
                                        <?php else: ?>
                                            <?php foreach($eligible_students as $student): ?>
                                                <label style="display: block; padding: 8px; border-bottom: 1px solid #eee;">
                                                    <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>">
                                                    <?php echo $student['last_name'] . ' ' . $student['first_name']; ?>
                                                    <small style="color: #7f8c8d;">
                                                        (<?php echo $student['matricule']; ?> - <?php echo $student['class_name']; ?>)
                                                    </small>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <button type="button" class="btn-secondary" onclick="closeRegisterModal()">Annuler</button>
                                    <button type="submit" name="register_students" class="btn-primary">Inscrire les élèves</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal nouvelle session d'examen -->
    <div class="modal" id="newExamModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouvelle session d'examen</h3>
                <button class="modal-close" onclick="closeNewExamModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="examForm">
                    <div class="form-group">
                        <label>Type d'examen *</label>
                        <select name="exam_type" required>
                            <option value="">Sélectionnez...</option>
                            <option value="CEPE">CEPE (Certificat d'Études Primaires)</option>
                            <option value="BEPC">BEPC (Brevet d'Études)</option>
                            <option value="BAC">BAC (Baccalauréat)</option>
                            <option value="BTS">BTS</option>
                            <option value="CAP">CAP</option>
                            <option value="OTHER">Autre examen</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Année scolaire *</label>
                        <input type="text" name="academic_year" value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>" required>
                    </div>
                    
                    <div class="row" style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Début des inscriptions *</label>
                            <input type="date" name="registration_start" required>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label>Fin des inscriptions *</label>
                            <input type="date" name="registration_end" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Date d'examen (prévisionnelle)</label>
                        <input type="date" name="exam_date">
                    </div>
                    
                    <div class="form-group">
                        <label>Centre d'examen</label>
                        <input type="text" name="center_name" placeholder="Nom du centre">
                    </div>
                    
                    <div class="form-group">
                        <label>Code du centre</label>
                        <input type="text" name="center_code" placeholder="Code officiel du centre">
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn-secondary" onclick="closeNewExamModal()">Annuler</button>
                        <button type="submit" name="create_exam" class="btn-primary">Créer la session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showNewExamModal() {
            document.getElementById('newExamModal').style.display = 'flex';
            
            // Définir des dates par défaut
            const today = new Date();
            const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
            const nextMonth = new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000);
            
            document.querySelector('input[name="registration_start"]').valueAsDate = today;
            document.querySelector('input[name="registration_end"]').valueAsDate = nextWeek;
            document.querySelector('input[name="exam_date"]').valueAsDate = nextMonth;
        }
        
        function closeNewExamModal() {
            document.getElementById('newExamModal').style.display = 'none';
        }
        
        function showRegisterModal() {
            document.getElementById('registerModal').style.display = 'flex';
        }
        
        function closeRegisterModal() {
            document.getElementById('registerModal').style.display = 'none';
        }
        
        function editExam(examId) {
            // Récupérer les infos de l'examen et afficher le modal d'édition
            fetch(`get_exam.php?id=${examId}`)
                .then(response => response.json())
                .then(exam => {
                    document.getElementById('newExamModal').style.display = 'flex';
                    document.getElementById('modalHeader').textContent = 'Modifier la session';
                    
                    // Remplir le formulaire
                    document.querySelector('select[name="exam_type"]').value = exam.exam_type;
                    document.querySelector('input[name="academic_year"]').value = exam.academic_year;
                    document.querySelector('input[name="registration_start"]').value = exam.registration_period_start;
                    document.querySelector('input[name="registration_end"]').value = exam.registration_period_end;
                    document.querySelector('input[name="exam_date"]').value = exam.exam_date || '';
                    document.querySelector('input[name="center_name"]').value = exam.center_name || '';
                    document.querySelector('input[name="center_code"]').value = exam.center_code || '';
                    
                    // Changer le bouton de soumission
                    const submitBtn = document.querySelector('button[name="create_exam"]');
                    submitBtn.name = 'update_exam';
                    submitBtn.textContent = 'Modifier la session';
                    
                    // Ajouter un champ caché pour l'ID
                    let idInput = document.querySelector('input[name="exam_id"]');
                    if (!idInput) {
                        idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'exam_id';
                        document.getElementById('examForm').appendChild(idInput);
                    }
                    idInput.value = examId;
                });
        }
        
        // Sélection de masse
        document.getElementById('selectAllCandidates').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="student_ids[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        
        function bulkUpdateStatus(status) {
            const selectedIds = Array.from(document.querySelectorAll('input[name="student_ids[]"]:checked'))
                .map(cb => cb.value);
            
            if (selectedIds.length === 0) {
                alert('Veuillez sélectionner au moins un candidat.');
                return;
            }
            
            if (confirm(`Mettre à jour le statut de ${selectedIds.length} candidat(s) ?`)) {
                const formData = new FormData();
                formData.append('bulk_update', '1');
                formData.append('status', status);
                selectedIds.forEach(id => formData.append('candidate_ids[]', id));
                
                fetch('update_candidates.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    }
                });
            }
        }
        
        function exportSelected() {
            const selectedIds = Array.from(document.querySelectorAll('input[name="student_ids[]"]:checked'))
                .map(cb => cb.value);
            
            if (selectedIds.length === 0) {
                alert('Veuillez sélectionner au moins un candidat.');
                return;
            }
            
            const examId = <?php echo isset($_GET['id']) ? $_GET['id'] : 'null'; ?>;
            if (examId) {
                window.open(`export_candidates.php?exam_id=${examId}&ids=${selectedIds.join(',')}`);
            }
        }
        
        // Recherche en direct
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = this.value.toLowerCase();
            const examSessions = document.querySelectorAll('.exam-session');
            
            examSessions.forEach(session => {
                const text = session.textContent.toLowerCase();
                session.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>