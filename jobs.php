<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer l'école
$stmt = $pdo->prepare("SELECT id, school_name FROM schools WHERE user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();

if (!$school) {
    header('Location: profile.php');
    exit();
}

$school_id = $school['id'];

// Ajouter une offre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_job'])) {
    $job_title = sanitize($_POST['job_title'] ?? '');
    $job_description = sanitize($_POST['job_description'] ?? '');
    $requirements = sanitize($_POST['requirements'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $level = sanitize($_POST['level'] ?? '');
    $job_type = sanitize($_POST['job_type'] ?? 'full_time');
    $salary_range = sanitize($_POST['salary_range'] ?? '');
    $application_deadline = sanitize($_POST['application_deadline'] ?? '');
    
    if (empty($job_title) || empty($job_description) || empty($application_deadline)) {
        $error = "Les champs obligatoires sont manquants";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO school_jobs (school_id, job_title, job_description, requirements, 
                                    subject, level, job_type, salary_range, application_deadline) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$school_id, $job_title, $job_description, $requirements,
                           $subject, $level, $job_type, $salary_range, $application_deadline])) {
            $success = "Offre publiée avec succès!";
        } else {
            $error = "Erreur lors de la publication de l'offre";
        }
    }
}

// Mettre à jour une offre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $job_id = intval($_POST['job_id']);
    $job_title = sanitize($_POST['job_title'] ?? '');
    $job_description = sanitize($_POST['job_description'] ?? '');
    $requirements = sanitize($_POST['requirements'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $level = sanitize($_POST['level'] ?? '');
    $job_type = sanitize($_POST['job_type'] ?? 'full_time');
    $salary_range = sanitize($_POST['salary_range'] ?? '');
    $application_deadline = sanitize($_POST['application_deadline'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $pdo->prepare("
        UPDATE school_jobs SET 
            job_title = ?, 
            job_description = ?, 
            requirements = ?, 
            subject = ?, 
            level = ?, 
            job_type = ?, 
            salary_range = ?, 
            application_deadline = ?, 
            is_active = ? 
        WHERE id = ? AND school_id = ?
    ");
    
    if ($stmt->execute([$job_title, $job_description, $requirements, $subject, $level,
                       $job_type, $salary_range, $application_deadline, $is_active, 
                       $job_id, $school_id])) {
        $success = "Offre mise à jour avec succès!";
    } else {
        $error = "Erreur lors de la mise à jour de l'offre";
    }
}

// Supprimer une offre
if (isset($_GET['delete'])) {
    $job_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM school_jobs WHERE id = ? AND school_id = ?");
    if ($stmt->execute([$job_id, $school_id])) {
        $success = "Offre supprimée avec succès!";
    }
}

// Activer/Désactiver une offre
if (isset($_GET['toggle'])) {
    $job_id = intval($_GET['toggle']);
    $stmt = $pdo->prepare("UPDATE school_jobs SET is_active = NOT is_active WHERE id = ? AND school_id = ?");
    if ($stmt->execute([$job_id, $school_id])) {
        $success = "Statut de l'offre modifié avec succès!";
    }
}

// Récupérer les offres
$query = "SELECT * FROM school_jobs WHERE school_id = ?";
$params = [$school_id];

if (isset($_GET['status']) && $_GET['status'] !== 'all') {
    if ($_GET['status'] == 'active') {
        $query .= " AND is_active = 1 AND application_deadline >= CURDATE()";
    } elseif ($_GET['status'] == 'expired') {
        $query .= " AND application_deadline < CURDATE()";
    } elseif ($_GET['status'] == 'inactive') {
        $query .= " AND is_active = 0";
    }
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Récupérer une offre pour modification
$job_to_edit = null;
if (isset($_GET['edit'])) {
    $job_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM school_jobs WHERE id = ? AND school_id = ?");
    $stmt->execute([$job_id, $school_id]);
    $job_to_edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offres d'emploi - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <!-- Inclure la sidebar UNE SEULE FOIS -->
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher une offre...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">Offres d'emploi</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-button active" onclick="openTab('jobList')">
                    <i class="fas fa-list"></i> Toutes les offres (<?php echo count($jobs); ?>)
                </button>
                <button class="tab-button" onclick="openTab('addJob')">
                    <i class="fas fa-plus-circle"></i> Publier une offre
                </button>
                <?php if ($job_to_edit): ?>
                    <button class="tab-button" onclick="openTab('editJob')">
                        <i class="fas fa-edit"></i> Modifier l'offre
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Liste des offres -->
            <div id="jobList" class="tab-content active">
                <!-- Filtres -->
                <div class="filter-section" style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 3px 10px rgba(0,0,0,0.05);">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <span style="font-weight: 500;">Filtrer par :</span>
                        <a href="jobs.php?status=all" class="btn-small <?php echo (!isset($_GET['status']) || $_GET['status'] == 'all') ? 'active' : ''; ?>">
                            Toutes
                        </a>
                        <a href="jobs.php?status=active" class="btn-small <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'active' : ''; ?>">
                            Actives
                        </a>
                        <a href="jobs.php?status=expired" class="btn-small <?php echo (isset($_GET['status']) && $_GET['status'] == 'expired') ? 'active' : ''; ?>">
                            Expirées
                        </a>
                        <a href="jobs.php?status=inactive" class="btn-small <?php echo (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'active' : ''; ?>">
                            Inactives
                        </a>
                    </div>
                </div>
                
                <!-- Liste -->
                <?php if (count($jobs) > 0): ?>
                    <div class="jobs-list" style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach($jobs as $job): 
                            $days_left = ceil((strtotime($job['application_deadline']) - time()) / (60 * 60 * 24));
                            $is_expired = $days_left < 0;
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_applications WHERE job_id = ?");
                            $stmt->execute([$job['id']]);
                            $applications_count = $stmt->fetchColumn();
                        ?>
                            <div class="job-card" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div>
                                        <h4 style="margin: 0 0 5px;"><?php echo htmlspecialchars($job['job_title']); ?></h4>
                                        <p style="color: #666; margin: 0;">
                                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($job['subject']); ?> • 
                                            <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($job['level']); ?>
                                        </p>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <span style="background: <?php echo $job['is_active'] ? '#2ecc71' : '#e74c3c'; ?>; 
                                                  color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.8rem;">
                                            <?php echo $job['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        
                                        <span style="background: #3498db; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.8rem;">
                                            <?php echo $applications_count; ?> candidatures
                                        </span>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
                                    <div style="color: #666;">
                                        <i class="fas fa-briefcase"></i> 
                                        <strong>Type:</strong> 
                                        <?php 
                                        $types = [
                                            'full_time' => 'Temps plein',
                                            'part_time' => 'Temps partiel',
                                            'contract' => 'Contrat'
                                        ];
                                        echo $types[$job['job_type']] ?? $job['job_type'];
                                        ?>
                                    </div>
                                    
                                    <div style="color: #666;">
                                        <i class="fas fa-clock"></i> 
                                        <strong>Date limite:</strong> <?php echo date('d/m/Y', strtotime($job['application_deadline'])); ?>
                                        <span style="color: <?php echo $is_expired ? '#e74c3c' : ($days_left <= 3 ? '#f39c12' : '#666'); ?>;">
                                            (<?php echo $is_expired ? 'Expirée' : $days_left . ' jour' . ($days_left > 1 ? 's' : ''); ?>)
                                        </span>
                                    </div>
                                    
                                    <?php if ($job['salary_range']): ?>
                                        <div style="color: #666;">
                                            <i class="fas fa-money-bill-wave"></i> 
                                            <strong>Salaire:</strong> <?php echo htmlspecialchars($job['salary_range']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <p style="color: #666; line-height: 1.5; margin-bottom: 15px;">
                                    <?php echo substr(htmlspecialchars($job['job_description']), 0, 200); ?>...
                                </p>
                                
                                <div style="display: flex; gap: 10px;">
                                    <a href="applications.php?job_id=<?php echo $job['id']; ?>" class="btn-small btn-primary">
                                        <i class="fas fa-file-alt"></i> Voir candidatures
                                    </a>
                                    <a href="jobs.php?edit=<?php echo $job['id']; ?>" class="btn-small">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="jobs.php?toggle=<?php echo $job['id']; ?>" class="btn-small btn-secondary">
                                        <i class="fas fa-power-off"></i> <?php echo $job['is_active'] ? 'Désactiver' : 'Activer'; ?>
                                    </a>
                                    <a href="jobs.php?delete=<?php echo $job['id']; ?>" 
                                       class="btn-small btn-danger"
                                       onclick="return confirm('Supprimer cette offre ?');">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-briefcase fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucune offre d'emploi</h3>
                        <p>Vous n'avez pas encore publié d'offre d'emploi.</p>
                        <a href="#addJob" class="btn-primary" onclick="openTab('addJob')">
                            <i class="fas fa-plus-circle"></i> Publier votre première offre
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Ajouter une offre -->
            <div id="addJob" class="tab-content">
                <div class="form-section">
                    <h3><i class="fas fa-plus-circle"></i> Publier une nouvelle offre d'emploi</h3>
                    
                    <form method="POST" id="jobForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="job_title">Titre du poste *</label>
                                <input type="text" id="job_title" name="job_title" required
                                       placeholder="Ex: Professeur de Mathématiques">
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Matière *</label>
                                <input type="text" id="subject" name="subject" required
                                       placeholder="Ex: Mathématiques, Physique, Français">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="level">Niveau *</label>
                                <select id="level" name="level" required>
                                    <option value="">Sélectionner un niveau</option>
                                    <option value="Primaire">Primaire</option>
                                    <option value="Collège">Collège</option>
                                    <option value="Lycée">Lycée</option>
                                    <option value="Universitaire">Universitaire</option>
                                    <option value="Tous niveaux">Tous niveaux</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="job_type">Type de contrat *</label>
                                <select id="job_type" name="job_type" required>
                                    <option value="full_time">Temps plein</option>
                                    <option value="part_time">Temps partiel</option>
                                    <option value="contract">Contrat</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="salary_range">Salaire proposé</label>
                                <input type="text" id="salary_range" name="salary_range"
                                       placeholder="Ex: 300 000 - 500 000 FCFA">
                            </div>
                            
                            <div class="form-group">
                                <label for="application_deadline">Date limite de candidature *</label>
                                <input type="date" id="application_deadline" name="application_deadline" required
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="job_description">Description du poste *</label>
                            <textarea id="job_description" name="job_description" rows="6" required
                                      placeholder="Décrivez en détail les missions, responsabilités..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="requirements">Qualifications requises</label>
                            <textarea id="requirements" name="requirements" rows="4"
                                      placeholder="Diplômes, expérience, compétences spécifiques..."></textarea>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="add_job" class="btn-primary">
                                <i class="fas fa-paper-plane"></i> Publier l'offre
                            </button>
                            <button type="button" class="btn-secondary" onclick="openTab('jobList')">
                                <i class="fas fa-times"></i> Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Modifier une offre -->
            <?php if ($job_to_edit): ?>
            <div id="editJob" class="tab-content">
                <div class="form-section">
                    <h3><i class="fas fa-edit"></i> Modifier l'offre</h3>
                    
                    <form method="POST">
                        <input type="hidden" name="job_id" value="<?php echo $job_to_edit['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_job_title">Titre du poste *</label>
                                <input type="text" id="edit_job_title" name="job_title" 
                                       value="<?php echo htmlspecialchars($job_to_edit['job_title']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_subject">Matière *</label>
                                <input type="text" id="edit_subject" name="subject" 
                                       value="<?php echo htmlspecialchars($job_to_edit['subject']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_level">Niveau *</label>
                                <select id="edit_level" name="level" required>
                                    <option value="Primaire" <?php echo $job_to_edit['level'] == 'Primaire' ? 'selected' : ''; ?>>Primaire</option>
                                    <option value="Collège" <?php echo $job_to_edit['level'] == 'Collège' ? 'selected' : ''; ?>>Collège</option>
                                    <option value="Lycée" <?php echo $job_to_edit['level'] == 'Lycée' ? 'selected' : ''; ?>>Lycée</option>
                                    <option value="Universitaire" <?php echo $job_to_edit['level'] == 'Universitaire' ? 'selected' : ''; ?>>Universitaire</option>
                                    <option value="Tous niveaux" <?php echo $job_to_edit['level'] == 'Tous niveaux' ? 'selected' : ''; ?>>Tous niveaux</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_job_type">Type de contrat *</label>
                                <select id="edit_job_type" name="job_type" required>
                                    <option value="full_time" <?php echo $job_to_edit['job_type'] == 'full_time' ? 'selected' : ''; ?>>Temps plein</option>
                                    <option value="part_time" <?php echo $job_to_edit['job_type'] == 'part_time' ? 'selected' : ''; ?>>Temps partiel</option>
                                    <option value="contract" <?php echo $job_to_edit['job_type'] == 'contract' ? 'selected' : ''; ?>>Contrat</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_salary_range">Salaire proposé</label>
                                <input type="text" id="edit_salary_range" name="salary_range"
                                       value="<?php echo htmlspecialchars($job_to_edit['salary_range']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_application_deadline">Date limite *</label>
                                <input type="date" id="edit_application_deadline" name="application_deadline" 
                                       value="<?php echo $job_to_edit['application_deadline']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_job_description">Description du poste *</label>
                            <textarea id="edit_job_description" name="job_description" rows="6" required><?php echo htmlspecialchars($job_to_edit['job_description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_requirements">Qualifications requises</label>
                            <textarea id="edit_requirements" name="requirements" rows="4"><?php echo htmlspecialchars($job_to_edit['requirements']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox">
                                <input type="checkbox" name="is_active" value="1" 
                                    <?php echo $job_to_edit['is_active'] ? 'checked' : ''; ?>>
                                <span>Offre active</span>
                            </label>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="update_job" class="btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                            <a href="jobs.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    function openTab(tabName) {
        const tabContents = document.getElementsByClassName('tab-content');
        for (let i = 0; i < tabContents.length; i++) {
            tabContents[i].classList.remove('active');
        }
        
        const tabButtons = document.getElementsByClassName('tab-button');
        for (let i = 0; i < tabButtons.length; i++) {
            tabButtons[i].classList.remove('active');
        }
        
        document.getElementById(tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }
    
    // Initialiser la date limite à J+30
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date();
        const nextMonth = new Date(today);
        nextMonth.setDate(today.getDate() + 30);
        
        const dateStr = nextMonth.toISOString().split('T')[0];
        document.getElementById('application_deadline').value = dateStr;
    });
    </script>
</body>
</html>