<?php
require_once '../includes/config.php';

$school_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$school_id) {
    header('Location: schools.php');
    exit();
}

// Récupérer les informations de l'école
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email, u.phone, u.profile_image, 
           sc.theme_color, sc.welcome_message, 
           sc.social_facebook, sc.social_twitter, sc.social_linkedin 
    FROM schools s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN school_configurations sc ON s.id = sc.school_id 
    WHERE s.id = ? AND u.is_active = 1 AND u.is_verified = 1
");
$stmt->execute([$school_id]);
$school = $stmt->fetch();

if (!$school) {
    header('Location: schools.php');
    exit();
}

// Récupérer les offres d'emploi actives
$stmt = $pdo->prepare("
    SELECT * FROM school_jobs 
    WHERE school_id = ? AND is_active = 1 AND application_deadline >= CURDATE() 
    ORDER BY created_at DESC
");
$stmt->execute([$school_id]);
$jobs = $stmt->fetchAll();

// Récupérer les enseignants associés (qui ont postulé ou travaillé)
$stmt = $pdo->prepare("
    SELECT DISTINCT t.*, u.full_name, u.profile_image 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    JOIN teacher_applications ta ON t.id = ta.teacher_id 
    JOIN school_jobs sj ON ta.job_id = sj.id 
    WHERE sj.school_id = ? AND ta.status = 'accepted' 
    AND u.is_active = 1 
    LIMIT 6
");
$stmt->execute([$school_id]);
$teachers = $stmt->fetchAll();

// Récupérer les avis sur l'école
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name as reviewer_name 
    FROM reviews r 
    JOIN users u ON r.reviewer_id = u.id 
    WHERE r.reviewer_type = 'parent' 
    AND r.teacher_id IN (
        SELECT t.id FROM teachers t 
        JOIN teacher_applications ta ON t.id = ta.teacher_id 
        JOIN school_jobs sj ON ta.job_id = sj.id 
        WHERE sj.school_id = ? AND ta.status = 'accepted'
    )
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->execute([$school_id]);
$reviews = $stmt->fetchAll();

// Calculer la note moyenne
$avg_rating = 0;
if (count($reviews) > 0) {
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
    }
    $avg_rating = $total_rating / count($reviews);
}

// Incrémenter le compteur de vues
$stmt = $pdo->prepare("UPDATE schools SET views = COALESCE(views, 0) + 1 WHERE id = ?");
$stmt->execute([$school_id]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($school['school_name']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .school-header {
            background: linear-gradient(135deg, <?php echo $school['theme_color'] ?: '#3498db'; ?> 0%, #2980b9 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .school-header-content {
            display: flex;
            align-items: center;
            gap: 40px;
        }
        
        .school-logo {
            width: 150px;
            height: 150px;
            border-radius: 15px;
            overflow: hidden;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 15px;
        }
        
        .school-info h1 {
            color: white;
            margin-bottom: 10px;
        }
        
        .school-meta {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .school-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .school-meta-item i {
            width: 20px;
        }
        
        .school-rating {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .school-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .school-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .school-main {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .school-sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .sidebar-card h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .jobs-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .job-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .job-item:hover {
            border-color: #3498db;
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.1);
        }
        
        .job-item h4 {
            margin-bottom: 5px;
        }
        
        .job-details {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            flex-wrap: wrap;
        }
        
        .job-detail {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .teacher-card {
            text-align: center;
        }
        
        .teacher-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 10px;
            overflow: hidden;
        }
        
        .teacher-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .review-card {
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .review-rating {
            color: #f39c12;
        }
        
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .contact-item i {
            width: 20px;
            color: #3498db;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: #3498db;
            color: white;
        }
        
        @media (max-width: 992px) {
            .school-content {
                grid-template-columns: 1fr;
            }
            
            .school-header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .school-meta {
                justify-content: center;
            }
            
            .school-actions {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .school-header {
                padding: 40px 0;
            }
            
            .school-logo {
                width: 100px;
                height: 100px;
            }
            
            .school-actions {
                flex-direction: column;
            }
            
            .school-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>Digital YOURHOPE</span>
            </a>
            <div class="nav-links">
                <a href="../index.php">Accueil</a>
                <a href="schools.php">Écoles</a>
                <a href="teachers.php">Enseignants</a>
                <a href="search.php">Recherche avancée</a>
                <?php if (isLoggedIn()): ?>
                    <a href="../dashboard/<?php echo $_SESSION['user_type']; ?>/index.php">Tableau de bord</a>
                    <a href="../auth/logout.php">Déconnexion</a>
                <?php else: ?>
                    <a href="../auth/login.php">Connexion</a>
                    <a href="../auth/register.php">Inscription</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- En-tête de l'école -->
    <section class="school-header">
        <div class="container">
            <div class="school-header-content">
                <div class="school-logo">
                    <?php if ($school['logo']): ?>
                        <img src="../uploads/schools/<?php echo $school['logo']; ?>" 
                             alt="<?php echo htmlspecialchars($school['school_name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-school fa-3x" style="color: #3498db;"></i>
                    <?php endif; ?>
                </div>
                
                <div class="school-info">
                    <h1><?php echo htmlspecialchars($school['school_name']); ?></h1>
                    
                    <div class="school-meta">
                        <div class="school-meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($school['city'] . ', ' . $school['country']); ?></span>
                        </div>
                        
                        <div class="school-meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span>
                                <?php 
                                $types = [
                                    'public' => 'École publique',
                                    'private' => 'École privée',
                                    'international' => 'École internationale'
                                ];
                                echo $types[$school['school_type']] ?? 'École';
                                ?>
                            </span>
                        </div>
                        
                        <?php if ($school['established_year']): ?>
                            <div class="school-meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Fondée en <?php echo $school['established_year']; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($avg_rating > 0): ?>
                        <div class="school-rating">
                            <div class="stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= floor($avg_rating) ? 'filled' : ($i == ceil($avg_rating) && fmod($avg_rating, 1) >= 0.5 ? 'half' : ''); ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span><?php echo number_format($avg_rating, 1); ?> (<?php echo count($reviews); ?> avis)</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="school-actions">
                        <?php if (isLoggedIn() && $_SESSION['user_type'] == 'parent'): ?>
                            <a href="../dashboard/parent/favorites.php?action=add&school_id=<?php echo $school_id; ?>" 
                               class="btn-primary">
                                <i class="fas fa-heart"></i> Ajouter aux favoris
                            </a>
                        <?php endif; ?>
                        
                        <?php if (isLoggedIn() && $_SESSION['user_type'] == 'teacher'): ?>
                            <a href="../dashboard/teacher/applications.php?school_id=<?php echo $school_id; ?>" 
                               class="btn-primary">
                                <i class="fas fa-briefcase"></i> Voir les offres d'emploi
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($school['website']): ?>
                            <a href="<?php echo htmlspecialchars($school['website']); ?>" 
                               target="_blank" class="btn-secondary">
                                <i class="fas fa-globe"></i> Visiter le site web
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contenu principal -->
    <div class="container">
        <div class="school-content">
            <!-- Section principale -->
            <div class="school-main">
                <!-- Description -->
                <section>
                    <h2>Description</h2>
                    <div style="line-height: 1.8; margin-top: 20px;">
                        <?php if ($school['description']): ?>
                            <?php echo nl2br(htmlspecialchars($school['description'])); ?>
                        <?php else: ?>
                            <p style="color: #95a5a6; font-style: italic;">
                                Cette école n'a pas encore ajouté de description.
                            </p>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Installations -->
                <?php if ($school['facilities']): ?>
                <section style="margin-top: 40px;">
                    <h2>Installations</h2>
                    <div style="margin-top: 20px;">
                        <?php 
                        $facilities = explode(',', $school['facilities']);
                        echo '<ul style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; margin-top: 15px;">';
                        foreach ($facilities as $facility) {
                            echo '<li style="display: flex; align-items: center; gap: 10px;"><i class="fas fa-check" style="color: #2ecc71;"></i> ' . htmlspecialchars(trim($facility)) . '</li>';
                        }
                        echo '</ul>';
                        ?>
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- Enseignants -->
                <?php if (count($teachers) > 0): ?>
                <section style="margin-top: 40px;">
                    <h2>Enseignants</h2>
                    <div class="teachers-grid" style="margin-top: 20px;">
                        <?php foreach($teachers as $teacher): ?>
                            <div class="teacher-card">
                                <div class="teacher-avatar">
                                    <?php if ($teacher['profile_image']): ?>
                                        <img src="../uploads/<?php echo $teacher['profile_image']; ?>" 
                                             alt="<?php echo htmlspecialchars($teacher['full_name']); ?>">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user-graduate" style="color: #ccc; font-size: 1.5rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h4><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
                                <p style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($teacher['specialization']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- Avis -->
                <?php if (count($reviews) > 0): ?>
                <section style="margin-top: 40px;">
                    <h2>Avis des parents</h2>
                    <div class="reviews-list" style="margin-top: 20px;">
                        <?php foreach($reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <h4><?php echo htmlspecialchars($review['reviewer_name']); ?></h4>
                                    </div>
                                    <div class="review-rating">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                <p style="color: #95a5a6; font-size: 0.9rem; margin-top: 10px;">
                                    <?php echo date('d/m/Y', strtotime($review['created_at'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="school-sidebar">
                <!-- Coordonnées -->
                <div class="sidebar-card">
                    <h3><i class="fas fa-address-card"></i> Coordonnées</h3>
                    <div class="contact-info">
                        <?php if ($school['address']): ?>
                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($school['address']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($school['phone']): ?>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($school['phone']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($school['email']): ?>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($school['email']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($school['accreditation']): ?>
                            <div class="contact-item">
                                <i class="fas fa-award"></i>
                                <span><?php echo htmlspecialchars($school['accreditation']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Réseaux sociaux -->
                    <?php if ($school['social_facebook'] || $school['social_twitter'] || $school['social_linkedin']): ?>
                        <div style="margin-top: 20px;">
                            <h4 style="margin-bottom: 10px; font-size: 1rem;">Suivez-nous</h4>
                            <div class="social-links">
                                <?php if ($school['social_facebook']): ?>
                                    <a href="<?php echo htmlspecialchars($school['social_facebook']); ?>" target="_blank">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($school['social_twitter']): ?>
                                    <a href="<?php echo htmlspecialchars($school['social_twitter']); ?>" target="_blank">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($school['social_linkedin']): ?>
                                    <a href="<?php echo htmlspecialchars($school['social_linkedin']); ?>" target="_blank">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Offres d'emploi -->
                <?php if (count($jobs) > 0): ?>
                <div class="sidebar-card">
                    <h3><i class="fas fa-briefcase"></i> Offres d'emploi</h3>
                    <div class="jobs-list">
                        <?php foreach($jobs as $job): 
                            $days_left = ceil((strtotime($job['application_deadline']) - time()) / (60 * 60 * 24));
                        ?>
                            <div class="job-item">
                                <h4><?php echo htmlspecialchars($job['job_title']); ?></h4>
                                <div class="job-details">
                                    <div class="job-detail">
                                        <i class="fas fa-book"></i>
                                        <span><?php echo htmlspecialchars($job['subject']); ?></span>
                                    </div>
                                    <div class="job-detail">
                                        <i class="fas fa-clock"></i>
                                        <span>
                                            <?php 
                                            $types = [
                                                'full_time' => 'Temps plein',
                                                'part_time' => 'Temps partiel',
                                                'contract' => 'Contrat'
                                            ];
                                            echo $types[$job['job_type']] ?? $job['job_type'];
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <p style="color: #666; font-size: 0.9rem; margin: 10px 0;">
                                    <?php echo substr(htmlspecialchars($job['job_description']), 0, 100); ?>...
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="<?php echo $days_left <= 3 ? 'text-danger' : 'text-muted'; ?>" 
                                          style="font-size: 0.85rem;">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('d/m/Y', strtotime($job['application_deadline'])); ?>
                                        (<?php echo $days_left; ?>j)
                                    </span>
                                    
                                    <?php if (isLoggedIn() && $_SESSION['user_type'] == 'teacher'): ?>
                                        <a href="../dashboard/teacher/applications.php?apply=<?php echo $job['id']; ?>" 
                                           class="btn-primary btn-small">
                                            Postuler
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Digital YOURHOPE</h3>
                    <p>Votre partenaire éducatif numérique.</p>
                </div>
                <div class="footer-section">
                    <h4>Liens rapides</h4>
                    <a href="schools.php">Écoles</a>
                    <a href="teachers.php">Enseignants</a>
                    <a href="search.php">Recherche avancée</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Digital YOURHOPE. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>