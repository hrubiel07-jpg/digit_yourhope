<?php
require_once '../includes/config.php';

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

// Récupérer les filtres de recherche
$search = $_GET['search'] ?? '';
$subject = $_GET['subject'] ?? '';
$location = $_GET['location'] ?? '';
$availability = $_GET['availability'] ?? '';

// Construction de la requête - CORRECTION : vérifier les colonnes existantes
$query = "
    SELECT t.*, u.full_name, u.email, u.phone, u.profile_image,
           u.city as teacher_city
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.is_active = 1
";

$params = [];

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR t.specialization LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($subject) {
    $query .= " AND t.specialization = ?";
    $params[] = $subject;
}

if ($location) {
    $query .= " AND u.city = ?";
    $params[] = $location;
}

$query .= " ORDER BY t.rating DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// Récupérer les spécialisations uniques pour le filtre
$subjects = $pdo->query("SELECT DISTINCT specialization FROM teachers WHERE specialization != '' AND specialization IS NOT NULL")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche d'Enseignants - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <a href="teachers.php" class="active">Enseignants</a>
                <?php if (isLoggedIn()): ?>
                    <a href="../dashboard/<?php echo $_SESSION['user_type']; ?>/">Tableau de bord</a>
                    <a href="../auth/logout.php">Déconnexion</a>
                <?php else: ?>
                    <a href="../auth/login.php">Connexion</a>
                    <a href="../auth/register.php">Inscription</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- En-tête de recherche -->
    <section class="search-header">
        <div class="container">
            <h1>Trouvez le professeur idéal</h1>
            <p>Recherchez parmi nos enseignants qualifiés et expérimentés</p>
            
            <form method="GET" class="search-form">
                <div class="search-filters">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Nom, matière, spécialisation..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="subject">
                            <option value="">Toutes les matières</option>
                            <?php foreach($subjects as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub['specialization']); ?>" 
                                    <?php echo $subject == $sub['specialization'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sub['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="availability">
                            <option value="">Disponibilité</option>
                            <option value="full_time" <?php echo $availability == 'full_time' ? 'selected' : ''; ?>>Temps plein</option>
                            <option value="part_time" <?php echo $availability == 'part_time' ? 'selected' : ''; ?>>Temps partiel</option>
                            <option value="weekends" <?php echo $availability == 'weekends' ? 'selected' : ''; ?>>Weekends</option>
                            <option value="flexible" <?php echo $availability == 'flexible' ? 'selected' : ''; ?>>Flexible</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                    
                    <?php if ($search || $subject || $availability): ?>
                        <a href="teachers.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Réinitialiser
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <!-- Liste des enseignants -->
    <section class="teachers-list">
        <div class="container">
            <div class="results-info">
                <h2><?php echo count($teachers); ?> enseignant(s) trouvé(s)</h2>
            </div>
            
            <?php if (count($teachers) > 0): ?>
                <div class="teachers-grid">
                    <?php foreach($teachers as $teacher): ?>
                        <div class="teacher-card">
                            <div class="teacher-header">
                                <div class="teacher-avatar">
                                    <?php if (!empty($teacher['profile_image'])): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($teacher['profile_image']); ?>" alt="<?php echo htmlspecialchars($teacher['full_name']); ?>">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="teacher-info">
                                    <h3><?php echo htmlspecialchars($teacher['full_name']); ?></h3>
                                    <p class="specialization"><?php echo htmlspecialchars($teacher['specialization'] ?? 'Non spécifié'); ?></p>
                                    <div class="teacher-rating">
                                        <?php 
                                        $rating = $teacher['rating'] ?? 0;
                                        for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= floor($rating) ? 'filled' : ''; ?>"></i>
                                        <?php endfor; ?>
                                        <span>(<?php echo $teacher['total_reviews'] ?? 0; ?> avis)</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="teacher-details">
                                <p><i class="fas fa-graduation-cap"></i> <strong>Qualification:</strong> <?php echo htmlspecialchars($teacher['qualification'] ?? 'Non spécifié'); ?></p>
                                <p><i class="fas fa-clock"></i> <strong>Expérience:</strong> <?php echo $teacher['experience_years'] ?? 0; ?> ans</p>
                                <p><i class="fas fa-money-bill-wave"></i> <strong>Taux horaire:</strong> <?php echo number_format($teacher['hourly_rate'] ?? 0, 0, ',', ' '); ?> FCFA</p>
                                <p><i class="fas fa-map-marker-alt"></i> <strong>Localisation:</strong> <?php echo htmlspecialchars($teacher['teacher_city'] ?? 'Non spécifié'); ?></p>
                            </div>
                            
                            <div class="teacher-bio">
                                <p><?php 
                                $bio = $teacher['bio'] ?? 'Aucune biographie disponible';
                                echo substr(htmlspecialchars($bio), 0, 150); 
                                if (strlen($bio) > 150) echo '...';
                                ?></p>
                            </div>
                            
                            <div class="teacher-actions">
                                <a href="teacher-profile.php?id=<?php echo $teacher['id']; ?>" class="btn-primary">
                                    <i class="fas fa-eye"></i> Voir profil
                                </a>
                                <?php if (isLoggedIn() && $_SESSION['user_type'] == 'parent'): ?>
                                    <a href="../dashboard/parent/appointments.php?teacher_id=<?php echo $teacher['id']; ?>" class="btn-secondary">
                                        <i class="fas fa-calendar-plus"></i> Prendre RDV
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search fa-3x"></i>
                    <h3>Aucun enseignant trouvé</h3>
                    <p>Essayez de modifier vos critères de recherche</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

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
                    <a href="../auth/login.php">Connexion</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Digital YOURHOPE. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
    
    <script src="../assets/js/main.js"></script>
    <style>
        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .teacher-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .teacher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .teacher-header {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: flex-start;
        }
        
        .teacher-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .teacher-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        
        .teacher-info h3 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .specialization {
            color: #3498db;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .teacher-rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .teacher-rating .fas.fa-star.filled {
            color: #f39c12;
        }
        
        .teacher-rating span {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .teacher-details {
            display: grid;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .teacher-details p {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            font-size: 0.95rem;
        }
        
        .teacher-details i {
            width: 20px;
            color: #7f8c8d;
        }
        
        .teacher-bio {
            margin-bottom: 20px;
            color: #666;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .teacher-actions {
            display: flex;
            gap: 10px;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 40px 0;
        }
        
        .no-results i {
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .no-results h3 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .no-results p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .teachers-grid {
                grid-template-columns: 1fr;
            }
            
            .teacher-header {
                flex-direction: column;
                text-align: center;
            }
            
            .teacher-avatar {
                margin: 0 auto;
            }
            
            .teacher-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>