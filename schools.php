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
$city = $_GET['city'] ?? '';
$school_type = $_GET['school_type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Construction de la requête - CORRECTION : éviter les colonnes dupliquées
$query = "
    SELECT s.*, u.full_name as user_full_name, u.phone as user_phone,
           sc.logo as school_logo
    FROM schools s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN school_configurations sc ON s.id = sc.school_id
    WHERE u.is_active = 1
";

$params = [];
$where = [];

if ($search) {
    $where[] = "(s.school_name LIKE ? OR s.city LIKE ? OR s.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($city) {
    $where[] = "s.city = ?";
    $params[] = $city;
}

if ($school_type) {
    $where[] = "s.school_type = ?";
    $params[] = $school_type;
}

if (!empty($where)) {
    $query .= " AND " . implode(" AND ", $where);
}

// Compter le total
$countQuery = str_replace('s.*, u.full_name as user_full_name, u.phone as user_phone, sc.logo as school_logo', 'COUNT(*)', $query);
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$total_results = $stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

// Ajouter la pagination
$query .= " ORDER BY s.school_name LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$schools = $stmt->fetchAll();

// Récupérer les villes uniques pour le filtre
$cities = $pdo->query("SELECT DISTINCT city FROM schools WHERE city != '' ORDER BY city")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Écoles - <?php echo SITE_NAME; ?></title>
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
                <a href="schools.php" class="active">Écoles</a>
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

    <!-- En-tête de recherche -->
    <section class="search-header">
        <div class="container">
            <h1>Découvrez nos écoles partenaires</h1>
            <p>Trouvez l'établissement idéal pour vos enfants</p>
            
            <form method="GET" class="search-form">
                <div class="search-filters">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Nom de l'école, ville..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="city">
                            <option value="">Toutes les villes</option>
                            <?php foreach($cities as $city_item): ?>
                                <option value="<?php echo htmlspecialchars($city_item['city']); ?>" 
                                    <?php echo $city == $city_item['city'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city_item['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="school_type">
                            <option value="">Tous les types</option>
                            <option value="public" <?php echo $school_type == 'public' ? 'selected' : ''; ?>>Public</option>
                            <option value="private" <?php echo $school_type == 'private' ? 'selected' : ''; ?>>Privé</option>
                            <option value="international" <?php echo $school_type == 'international' ? 'selected' : ''; ?>>International</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                    
                    <?php if ($search || $city || $school_type): ?>
                        <a href="schools.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Réinitialiser
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <!-- Liste des écoles -->
    <section class="schools-list">
        <div class="container">
            <div class="results-info">
                <h2><?php echo $total_results; ?> école(s) trouvée(s)</h2>
            </div>
            
            <?php if (count($schools) > 0): ?>
                <div class="schools-grid">
                    <?php foreach($schools as $school): ?>
                        <div class="school-card">
                            <div class="school-header">
                                <div class="school-logo">
                                    <?php if (!empty($school['school_logo'])): ?>
                                        <img src="../uploads/schools/<?php echo htmlspecialchars($school['school_logo']); ?>" 
                                             alt="<?php echo htmlspecialchars($school['school_name']); ?>">
                                    <?php else: ?>
                                        <div class="logo-placeholder">
                                            <i class="fas fa-school"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="school-info">
                                    <h3><?php echo htmlspecialchars($school['school_name']); ?></h3>
                                    <p class="school-location">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?php echo htmlspecialchars($school['city'] ?? 'Non spécifié'); ?>
                                    </p>
                                    <p class="school-type">
                                        <?php 
                                        $types = [
                                            'public' => 'École publique',
                                            'private' => 'École privée',
                                            'international' => 'École internationale'
                                        ];
                                        echo $types[$school['school_type']] ?? 'École';
                                        ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="school-details">
                                <?php if (!empty($school['established_year'])): ?>
                                    <p><i class="fas fa-calendar-alt"></i> <strong>Fondée en:</strong> <?php echo $school['established_year']; ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($school['phone'])): ?>
                                    <p><i class="fas fa-phone"></i> <strong>Téléphone:</strong> <?php echo htmlspecialchars($school['phone']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($school['email'])): ?>
                                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($school['email']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($school['website'])): ?>
                                    <p><i class="fas fa-globe"></i> <strong>Site web:</strong> 
                                        <a href="<?php echo htmlspecialchars($school['website']); ?>" target="_blank">
                                            Visiter le site
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($school['description'])): ?>
                                <div class="school-description">
                                    <p><?php echo substr(htmlspecialchars($school['description']), 0, 200); ?>...</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="school-actions">
                                <a href="school-profile.php?id=<?php echo $school['id']; ?>" class="btn-primary">
                                    <i class="fas fa-eye"></i> Voir détails
                                </a>
                                
                                <?php if (isLoggedIn() && $_SESSION['user_type'] == 'parent'): ?>
                                    <a href="../dashboard/parent/favorites.php?school_id=<?php echo $school['id']; ?>" class="btn-secondary">
                                        <i class="fas fa-heart"></i> Ajouter aux favoris
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (isLoggedIn() && $_SESSION['user_type'] == 'teacher'): ?>
                                    <a href="../dashboard/teacher/applications.php?school_id=<?php echo $school['id']; ?>" class="btn-secondary">
                                        <i class="fas fa-briefcase"></i> Voir les offres
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
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
                <div class="no-results">
                    <i class="fas fa-school fa-3x"></i>
                    <h3>Aucune école trouvée</h3>
                    <p>Essayez de modifier vos critères de recherche</p>
                    <a href="schools.php" class="btn-primary">
                        <i class="fas fa-redo"></i> Voir toutes les écoles
                    </a>
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
                    <a href="search.php">Recherche avancée</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Digital YOURHOPE. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
    
    <script src="../assets/js/main.js"></script>
    <style>
        .schools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .school-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .school-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .school-header {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: flex-start;
        }
        
        .school-logo {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .logo-placeholder {
            width: 100%;
            height: 100%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        
        .school-info h3 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .school-location {
            color: #7f8c8d;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .school-type {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .school-details {
            display: grid;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .school-details p {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
        }
        
        .school-details i {
            width: 20px;
            color: #7f8c8d;
        }
        
        .school-description {
            margin-bottom: 20px;
            color: #666;
            line-height: 1.6;
        }
        
        .school-actions {
            display: flex;
            gap: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .page-link {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #555;
            transition: all 0.3s;
        }
        
        .page-link:hover,
        .page-link.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
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
            .schools-grid {
                grid-template-columns: 1fr;
            }
            
            .school-header {
                flex-direction: column;
                text-align: center;
            }
            
            .school-logo {
                margin: 0 auto;
            }
            
            .school-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>