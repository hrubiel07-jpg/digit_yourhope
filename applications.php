<?php
require_once '../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'teacher') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer l'ID de l'enseignant
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'];

// Postuler à une offre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
    $job_id = intval($_POST['job_id']);
    $application_letter = sanitize($_POST['application_letter']);
    
    // Vérifier si déjà postulé
    $stmt = $pdo->prepare("SELECT id FROM teacher_applications WHERE teacher_id = ? AND job_id = ?");
    $stmt->execute([$teacher_id, $job_id]);
    
    if ($stmt->rowCount() > 0) {
        $error = "Vous avez déjà postulé à cette offre";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO teacher_applications (teacher_id, job_id, application_letter) 
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$teacher_id, $job_id, $application_letter])) {
            $success = "Candidature envoyée avec succès!";
        } else {
            $error = "Erreur lors de l'envoi de la candidature";
        }
    }
}

// Annuler une candidature
if (isset($_GET['cancel'])) {
    $application_id = intval($_GET['cancel']);
    
    $stmt = $pdo->prepare("DELETE FROM teacher_applications WHERE id = ? AND teacher_id = ?");
    if ($stmt->execute([$application_id, $teacher_id])) {
        $success = "Candidature annulée avec succès!";
    } else {
        $error = "Erreur lors de l'annulation de la candidature";
    }
}

// Récupérer les candidatures de l'enseignant
$stmt = $pdo->prepare("
    SELECT ta.*, sj.job_title, s.school_name, s.city, sj.application_deadline 
    FROM teacher_applications ta 
    JOIN school_jobs sj ON ta.job_id = sj.id 
    JOIN schools s ON sj.school_id = s.id 
    WHERE ta.teacher_id = ? 
    ORDER BY ta.applied_at DESC
");
$stmt->execute([$teacher_id]);
$applications = $stmt->fetchAll();

// Récupérer les offres disponibles
$stmt = $pdo->prepare("
    SELECT sj.*, s.school_name, s.city, s.country 
    FROM school_jobs sj 
    JOIN schools s ON sj.school_id = s.id 
    WHERE sj.is_active = 1 
    AND sj.application_deadline >= CURDATE() 
    AND sj.id NOT IN (
        SELECT job_id FROM teacher_applications WHERE teacher_id = ?
    )
    ORDER BY sj.created_at DESC
");
$stmt->execute([$teacher_id]);
$available_jobs = $stmt->fetchAll();

// Récupérer une offre spécifique pour candidature
$job_to_apply = null;
if (isset($_GET['apply'])) {
    $job_id = intval($_GET['apply']);
    $stmt = $pdo->prepare("
        SELECT sj.*, s.school_name, s.city, s.country 
        FROM school_jobs sj 
        JOIN schools s ON sj.school_id = s.id 
        WHERE sj.id = ? AND sj.is_active = 1 AND sj.application_deadline >= CURDATE()
    ");
    $stmt->execute([$job_id]);
    $job_to_apply = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Candidatures - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-chalkboard-teacher"></i> Mes Candidatures</h3>
            <p>Postulez aux offres d'emploi</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i> Mon Profil
            </a>
            <a href="services.php">
                <i class="fas fa-concierge-bell"></i> Mes Services
            </a>
            <a href="applications.php" class="active">
                <i class="fas fa-briefcase"></i> Mes Candidatures
            </a>
            <a href="appointments.php">
                <i class="fas fa-calendar-alt"></i> Rendez-vous
            </a>
            <a href="../../auth/logout.php" class="logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </nav>
    </div>
    
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
            <h1 class="page-title">Mes Candidatures</h1>
            
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
                <button class="tab-button active" onclick="openTab('myApplications')">
                    <i class="fas fa-file-alt"></i> Mes candidatures (<?php echo count($applications); ?>)
                </button>
                <button class="tab-button" onclick="openTab('availableJobs')">
                    <i class="fas fa-briefcase"></i> Offres disponibles (<?php echo count($available_jobs); ?>)
                </button>
                <?php if ($job_to_apply): ?>
                    <button class="tab-button" onclick="openTab('applyJob')">
                        <i class="fas fa-edit"></i> Postuler
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Mes candidatures -->
            <div id="myApplications" class="tab-content active">
                <?php if (count($applications) > 0): ?>
                    <div class="applications-grid">
                        <?php foreach($applications as $application): 
                            $is_expired = strtotime($application['application_deadline']) < time();
                            ?>
                            <div class="application-card">
                                <div class="application-header">
                                    <h4><?php echo htmlspecialchars($application['job_title']); ?></h4>
                                    <span class="application-school">
                                        <?php echo htmlspecialchars($application['school_name']); ?>
                                    </span>
                                </div>
                                
                                <div class="application-details">
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($application['city']); ?></p>
                                    <p><i class="fas fa-calendar"></i> Postulé le: <?php echo date('d/m/Y', strtotime($application['applied_at'])); ?></p>
                                    <p><i class="fas fa-clock"></i> Date limite: <?php echo date('d/m/Y', strtotime($application['application_deadline'])); ?></p>
                                    
                                    <?php if ($is_expired): ?>
                                        <p><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> Offre expirée</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="application-status">
                                    <span class="status-badge status-<?php echo $application['status']; ?>">
                                        <?php 
                                        $status_labels = [
                                            'pending' => 'En attente',
                                            'reviewed' => 'En cours',
                                            'accepted' => 'Acceptée',
                                            'rejected' => 'Refusée'
                                        ];
                                        echo $status_labels[$application['status']] ?? $application['status'];
                                        ?>
                                    </span>
                                    
                                    <?php if ($application['status'] == 'pending' && !$is_expired): ?>
                                        <a href="applications.php?cancel=<?php echo $application['id']; ?>" 
                                           class="btn-small" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir annuler cette candidature?');"
                                           style="background: #95a5a6;">
                                            <i class="fas fa-times"></i> Annuler
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($application['notes']): ?>
                                    <div class="application-notes">
                                        <p><strong>Notes de l'école:</strong> <?php echo htmlspecialchars($application['notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-file-alt fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucune candidature</h3>
                        <p>Vous n'avez pas encore postulé à des offres d'emploi.</p>
                        <a href="#availableJobs" class="btn-primary" onclick="openTab('availableJobs')">
                            <i class="fas fa-search"></i> Voir les offres disponibles
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Offres disponibles -->
            <div id="availableJobs" class="tab-content">
                <?php if (count($available_jobs) > 0): ?>
                    <div class="jobs-grid">
                        <?php foreach($available_jobs as $job): 
                            $days_left = ceil((strtotime($job['application_deadline']) - time()) / (60 * 60 * 24));
                            ?>
                            <div class="job-card">
                                <div class="job-header">
                                    <h4><?php echo htmlspecialchars($job['job_title']); ?></h4>
                                    <span class="job-school">
                                        <?php echo htmlspecialchars($job['school_name']); ?>
                                    </span>
                                </div>
                                
                                <div class="job-details">
                                    <p><i class="fas fa-book"></i> <strong>Matière:</strong> <?php echo htmlspecialchars($job['subject']); ?></p>
                                    <p><i class="fas fa-graduation-cap"></i> <strong>Niveau:</strong> <?php echo htmlspecialchars($job['level']); ?></p>
                                    <p><i class="fas fa-briefcase"></i> <strong>Type:</strong> 
                                        <?php 
                                        $types = [
                                            'full_time' => 'Temps plein',
                                            'part_time' => 'Temps partiel',
                                            'contract' => 'Contrat'
                                        ];
                                        echo $types[$job['job_type']] ?? $job['job_type'];
                                        ?>
                                    </p>
                                    <p><i class="fas fa-map-marker-alt"></i> <strong>Localisation:</strong> 
                                        <?php echo htmlspecialchars($job['city'] . ', ' . $job['country']); ?>
                                    </p>
                                    <p><i class="fas fa-clock"></i> <strong>Date limite:</strong> 
                                        <?php echo date('d/m/Y', strtotime($job['application_deadline'])); ?>
                                        <span class="<?php echo $days_left <= 3 ? 'text-danger' : 'text-muted'; ?>">
                                            (<?php echo $days_left; ?> jour<?php echo $days_left > 1 ? 's' : ''; ?>)
                                        </span>
                                    </p>
                                    
                                    <?php if ($job['salary_range']): ?>
                                        <p><i class="fas fa-money-bill-wave"></i> <strong>Salaire:</strong> <?php echo htmlspecialchars($job['salary_range']); ?> FCFA</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="job-description">
                                    <p><?php echo substr(htmlspecialchars($job['job_description']), 0, 150); ?>...</p>
                                </div>
                                
                                <div class="job-actions">
                                    <a href="applications.php?apply=<?php echo $job['id']; ?>" class="btn-primary">
                                        <i class="fas fa-paper-plane"></i> Postuler
                                    </a>
                                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn-secondary">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-briefcase fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucune offre disponible</h3>
                        <p>Aucune offre d'emploi ne correspond à vos critères pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Formulaire de candidature -->
            <?php if ($job_to_apply): ?>
            <div id="applyJob" class="tab-content">
                <div class="form-section">
                    <h3><i class="fas fa-paper-plane"></i> Postuler à l'offre</h3>
                    
                    <div class="job-preview" style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h4><?php echo htmlspecialchars($job_to_apply['job_title']); ?></h4>
                        <p><strong>École:</strong> <?php echo htmlspecialchars($job_to_apply['school_name']); ?></p>
                        <p><strong>Matière:</strong> <?php echo htmlspecialchars($job_to_apply['subject']); ?> - <?php echo htmlspecialchars($job_to_apply['level']); ?></p>
                        <p><strong>Date limite:</strong> <?php echo date('d/m/Y', strtotime($job_to_apply['application_deadline'])); ?></p>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="job_id" value="<?php echo $job_to_apply['id']; ?>">
                        
                        <div class="form-group">
                            <label for="application_letter">Lettre de motivation *</label>
                            <textarea id="application_letter" name="application_letter" rows="8" required 
                                      placeholder="Présentez-vous et expliquez pourquoi vous êtes le candidat idéal pour ce poste..."></textarea>
                            <small style="color: #666;">Conseil: Personnalisez votre lettre pour chaque offre</small>
                        </div>
                        
                        <div class="form-group">
                            <label>CV et documents</label>
                            <div style="padding: 15px; background: white; border: 1px solid #ddd; border-radius: 5px;">
                                <?php
                                $stmt = $pdo->prepare("SELECT cv_path, certificates FROM teachers WHERE id = ?");
                                $stmt->execute([$teacher_id]);
                                $teacher_docs = $stmt->fetch();
                                ?>
                                
                                <?php if ($teacher_docs['cv_path']): ?>
                                    <p><i class="fas fa-file-pdf"></i> <strong>CV:</strong> 
                                        <a href="../../uploads/cv/<?php echo $teacher_docs['cv_path']; ?>" target="_blank">
                                            Télécharger mon CV
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p style="color: #e74c3c;"><i class="fas fa-exclamation-circle"></i> 
                                        Aucun CV uploadé. Veuillez ajouter votre CV dans votre profil.
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($teacher_docs['certificates']): ?>
                                    <p><i class="fas fa-certificate"></i> <strong>Certificats:</strong> Disponibles</p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$teacher_docs['cv_path']): ?>
                                <p style="color: #e74c3c; margin-top: 10px;">
                                    <a href="profile.php">Ajoutez votre CV dans votre profil avant de postuler</a>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="apply_job" class="btn-primary" 
                                    <?php echo empty($teacher_docs['cv_path']) ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane"></i> Envoyer ma candidature
                            </button>
                            
                            <a href="applications.php" class="btn-secondary">
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
        // Masquer tous les contenus de tab
        const tabContents = document.getElementsByClassName('tab-content');
        for (let i = 0; i < tabContents.length; i++) {
            tabContents[i].classList.remove('active');
        }
        
        // Désactiver tous les boutons de tab
        const tabButtons = document.getElementsByClassName('tab-button');
        for (let i = 0; i < tabButtons.length; i++) {
            tabButtons[i].classList.remove('active');
        }
        
        // Afficher le contenu de la tab sélectionnée
        document.getElementById(tabName).classList.add('active');
        
        // Activer le bouton de la tab sélectionnée
        event.currentTarget.classList.add('active');
    }
    </script>
    
    <style>
        .tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 30px;
        }
        
        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .tab-button:hover {
            color: #3498db;
        }
        
        .tab-button.active {
            color: #3498db;
            border-bottom-color: #3498db;
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .applications-grid,
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .application-card,
        .job-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .application-card:hover,
        .job-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .application-header,
        .job-header {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .application-header h4,
        .job-header h4 {
            margin: 0 0 5px;
            color: #2c3e50;
        }
        
        .application-school,
        .job-school {
            color: #3498db;
            font-size: 0.9rem;
        }
        
        .application-details,
        .job-details {
            margin-bottom: 15px;
        }
        
        .application-details p,
        .job-details p {
            margin: 5px 0;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .application-details i,
        .job-details i {
            width: 20px;
            color: #7f8c8d;
        }
        
        .application-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .application-notes {
            margin-top: 15px;
            padding: 10px;
            background: #fff3cd;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
        
        .job-description {
            margin: 15px 0;
            color: #666;
            line-height: 1.5;
        }
        
        .job-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .text-danger {
            color: #e74c3c;
        }
        
        .text-muted {
            color: #95a5a6;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</body>
</html>
