<?php
require_once '../includes/config.php';

// Récupérer les paramètres de recherche
$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all'; // all, school, teacher
$location = $_GET['location'] ?? '';
$subject = $_GET['subject'] ?? '';
$availability = $_GET['availability'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Initialiser les résultats
$results = [];
$total_results = 0;
$school_count = 0;
$teacher_count = 0;

// Recherche d'écoles
if ($type === 'school' || $type === 'all') {
    $school_sql = "SELECT COUNT(*) FROM schools s 
                   JOIN users u ON s.user_id = u.id 
                   WHERE u.is_active = 1 AND u.is_verified = 1";
    $school_params = [];
    
    if ($query) {
        $school_sql .= " AND (s.school_name LIKE ? OR s.city LIKE ? OR s.description LIKE ?)";
        $search_term = "%$query%";
        $school_params[] = $search_term;
        $school_params[] = $search_term;
        $school_params[] = $search_term;
    }
    
    if ($location) {
        $school_sql .= " AND (s.city = ? OR s.country = ?)";
        $school_params[] = $location;
        $school_params[] = $location;
    }
    
    $stmt = $pdo->prepare($school_sql);
    $stmt->execute($school_params);
    $school_count = $stmt->fetchColumn();
    
    if ($type === 'school') {
        $total_results = $school_count;
    }
}

// Recherche d'enseignants
if ($type === 'teacher' || $type === 'all') {
    $teacher_sql = "SELECT COUNT(*) FROM teachers t 
                    JOIN users u ON t.user_id = u.id 
                    WHERE u.is_active = 1 AND u.is_verified = 1";
    $teacher_params = [];
    
    if ($query) {
        $teacher_sql .= " AND (u.full_name LIKE ? OR t.specialization LIKE ? OR t.subjects LIKE ?)";
        $search_term = "%$query%";
        $teacher_params[] = $search_term;
        $teacher_params[] = $search_term;
        $teacher_params[] = $search_term;
    }
    
    if ($subject) {
        $teacher_sql .= " AND t.specialization LIKE ?";
        $teacher_params[] = "%$subject%";
    }
    
    if ($availability) {
        $teacher_sql .= " AND t.availability = ?";
        $teacher_params[] = $availability;
    }
    
    $stmt = $pdo->prepare($teacher_sql);
    $stmt->execute($teacher_params);
    $teacher_count = $stmt->fetchColumn();
    
    if ($type === 'teacher') {
        $total_results = $teacher_count;
    }
}

if ($type === 'all') {
    $total_results = $school_count + $teacher_count;
}

// Récupérer les résultats paginés
if ($type === 'school' || $type === 'all') {
    $school_query = str_replace('COUNT(*)', 's.*, u.full_name as user_full_name', $school_sql);
    $school_query .= " ORDER BY s.school_name LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($school_query);
    $stmt->execute($school_params);
    $school_results = $stmt->fetchAll();
    
    foreach ($school_results as $school) {
        $school['type'] = 'school';
        $results[] = $school;
    }
}

if ($type === 'teacher' || $type === 'all') {
    $teacher_query = str_replace('COUNT(*)', 't.*, u.full_name, u.email, u.phone, u.profile_image', $teacher_sql);
    $teacher_query .= " ORDER BY t.rating DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($teacher_query);
    $stmt->execute($teacher_params);
    $teacher_results = $stmt->fetchAll();
    
    foreach ($teacher_results as $teacher) {
        $teacher['type'] = 'teacher';
        $results[] = $teacher;
    }
}

// Récupérer les sujets uniques pour les filtres
$subjects = $pdo->query("SELECT DISTINCT specialization FROM teachers WHERE specialization != '' AND specialization IS NOT NULL ORDER BY specialization")->fetchAll();
$locations = $pdo->query("SELECT DISTINCT city FROM schools WHERE city != '' AND city IS NOT NULL ORDER BY city")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche avancée - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
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
                <a href="search.php" class="active">Recherche avancée</a>
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

    <section class="search-header">
        <div class="container">
            <h1>Recherche avancée</h1>
            <p>Trouvez exactement ce que vous cherchez</p>
            
            <form method="GET" class="search-form">
                <div class="search-filters" style="grid-template-columns: 2fr 1fr 1fr 1fr auto;">
                    <div class="filter-group">
                        <input type="text" name="q" placeholder="Que recherchez-vous?" 
                               value="<?php echo htmlspecialchars($query); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="type">
                            <option value="all" <?php echo $type == 'all' ? 'selected' : ''; ?>>Tout</option>
                            <option value="school" <?php echo $type == 'school' ? 'selected' : ''; ?>>Écoles</option>
                            <option value="teacher" <?php echo $type == 'teacher' ? 'selected' : ''; ?>>Enseignants</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="subject">
                            <option value="">Matière</option>
                            <?php foreach($subjects as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub['specialization']); ?>" 
                                    <?php echo $subject == $sub['specialization'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sub['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="location">
                            <option value="">Localisation</option>
                            <?php foreach($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc['city']); ?>" 
                                    <?php echo $location == $loc['city'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="search-results">
        <div class="container">
            <div class="results-info">
                <h2><?php echo $total_results; ?> résultat(s) trouvé(s)</h2>
                <div class="results-type">
                    <span>Type: </span>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'all'])); ?>" 
                       class="<?php echo $type == 'all' ? 'active' : ''; ?>">Tout</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'school'])); ?>" 
                       class="<?php echo $type == 'school' ? 'active' : ''; ?>">Écoles (<?php echo $school_count; ?>)</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'teacher'])); ?>" 
                       class="<?php echo $type == 'teacher' ? 'active' : ''; ?>">Enseignants (<?php echo $teacher_count; ?>)</a>
                </div>
            </div>
            
            <?php if (count($results) > 0): ?>
                <div class="results-list">
                    <?php foreach($results as $result): ?>
                        <?php if ($result['type'] == 'school'): ?>
                            <div class="result-card">
                                <div class="result-icon">
                                    <i class="fas fa-school"></i>
                                </div>
                                <div class="result-content">
                                    <h3>
                                        <a href="school-profile.php?id=<?php echo $result['id']; ?>">
                                            <?php echo htmlspecialchars($result['school_name']); ?>
                                        </a>
                                    </h3>
                                    <p class="result-meta">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?php echo htmlspecialchars($result['city'] ?? 'Non spécifié'); ?>
                                        <?php if ($result['country']): ?>
                                            , <?php echo htmlspecialchars($result['country']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($result['description']): ?>
                                        <p class="result-description">
                                            <?php echo substr(htmlspecialchars($result['description']), 0, 200); ?>...
                                        </p>
                                    <?php endif; ?>
                                    <div class="result-tags">
                                        <span class="tag"><?php echo ucfirst($result['school_type'] ?? 'privé'); ?></span>
                                        <?php if ($result['established_year']): ?>
                                            <span class="tag">Depuis <?php echo $result['established_year']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-actions">
                                    <a href="school-profile.php?id=<?php echo $result['id']; ?>" class="btn-primary">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="result-card">
                                <div class="result-icon">
                                    <?php if ($result['profile_image']): ?>
                                        <img src="../uploads/<?php echo $result['profile_image']; ?>" 
                                             alt="<?php echo htmlspecialchars($result['full_name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-user-graduate"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="result-content">
                                    <h3>
                                        <a href="teacher-profile.php?id=<?php echo $result['id']; ?>">
                                            <?php echo htmlspecialchars($result['full_name']); ?>
                                        </a>
                                    </h3>
                                    <p class="result-meta">
                                        <i class="fas fa-graduation-cap"></i> 
                                        <?php echo htmlspecialchars($result['specialization']); ?>
                                        <?php if ($result['experience_years']): ?>
                                            • <i class="fas fa-clock"></i> 
                                            <?php echo $result['experience_years']; ?> ans d'expérience
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($result['bio']): ?>
                                        <p class="result-description">
                                            <?php echo substr(htmlspecialchars($result['bio']), 0, 200); ?>...
                                        </p>
                                    <?php endif; ?>
                                    <div class="result-tags">
                                        <span class="tag"><?php echo ucfirst(str_replace('_', ' ', $result['availability'] ?? 'flexible')); ?></span>
                                        <?php if ($result['hourly_rate']): ?>
                                            <span class="tag"><?php echo number_format($result['hourly_rate'], 0, ',', ' '); ?> FCFA/h</span>
                                        <?php endif; ?>
                                        <div class="rating">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= floor($result['rating'] ?? 0) ? 'filled' : ''; ?>"></i>
                                            <?php endfor; ?>
                                            <span>(<?php echo $result['total_reviews'] ?? 0; ?> avis)</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="result-actions">
                                    <a href="teacher-profile.php?id=<?php echo $result['id']; ?>" class="btn-primary">
                                        <i class="fas fa-eye"></i> Voir profil
                                    </a>
                                    <?php if (isLoggedIn() && $_SESSION['user_type'] == 'parent'): ?>
                                        <a href="../dashboard/parent/appointments.php?teacher_id=<?php echo $result['id']; ?>" 
                                           class="btn-secondary">
                                            <i class="fas fa-calendar-plus"></i> Prendre RDV
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_results > $limit): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $total_pages = ceil($total_results / $limit);
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
                    <i class="fas fa-search fa-3x"></i>
                    <h3>Aucun résultat trouvé</h3>
                    <p>Essayez de modifier vos critères de recherche</p>
                    <a href="search.php" class="btn-primary">
                        <i class="fas fa-redo"></i> Réinitialiser la recherche
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

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
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .results-type {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .results-type a {
            padding: 8px 15px;
            border: 2px solid #3498db;
            border-radius: 20px;
            text-decoration: none;
            color: #3498db;
            transition: all 0.3s;
        }
        
        .results-type a:hover,
        .results-type a.active {
            background: #3498db;
            color: white;
        }
        
        .results-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .result-card {
            display: flex;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            gap: 20px;
            align-items: flex-start;
        }
        
        .result-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .result-icon img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .result-content {
            flex: 1;
        }
        
        .result-content h3 {
            margin-bottom: 10px;
        }
        
        .result-content h3 a {
            color: #2c3e50;
            text-decoration: none;
        }
        
        .result-content h3 a:hover {
            color: #3498db;
        }
        
        .result-meta {
            color: #7f8c8d;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .result-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .result-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .tag {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            color: #555;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #f39c12;
        }
        
        .rating .filled {
            color: #f39c12;
        }
        
        .result-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
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
            .result-card {
                flex-direction: column;
            }
            
            .results-info {
                flex-direction: column;
                align-items: stretch;
            }
            
            .results-type {
                flex-wrap: wrap;
            }
            
            .result-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</body>
</html>