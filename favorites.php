<?php
require_once '../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'parent') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer le parent
$stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ?");
$stmt->execute([$user_id]);
$parent = $stmt->fetch();

// Ajouter aux favoris
if (isset($_GET['add'])) {
    $type = $_GET['type']; // teacher ou school
    $item_id = intval($_GET['id']);
    
    // Vérifier si déjà en favoris
    $stmt = $pdo->prepare("
        SELECT id FROM favorites 
        WHERE parent_id = ? AND {$type}_id = ?
    ");
    $stmt->execute([$parent['id'], $item_id]);
    
    if ($stmt->rowCount() == 0) {
        $field = $type . '_id';
        $stmt = $pdo->prepare("
            INSERT INTO favorites (parent_id, $field) 
            VALUES (?, ?)
        ");
        if ($stmt->execute([$parent['id'], $item_id])) {
            $success = "Ajouté aux favoris avec succès!";
        }
    } else {
        $info = "Déjà dans vos favoris";
    }
}

// Retirer des favoris
if (isset($_GET['remove'])) {
    $type = $_GET['type'];
    $item_id = intval($_GET['id']);
    
    $field = $type . '_id';
    $stmt = $pdo->prepare("
        DELETE FROM favorites 
        WHERE parent_id = ? AND $field = ?
    ");
    
    if ($stmt->execute([$parent['id'], $item_id])) {
        $success = "Retiré des favoris avec succès!";
    }
}

// Récupérer les favoris
$tabs = [
    'teachers' => ['name' => 'Enseignants', 'icon' => 'fa-chalkboard-teacher'],
    'schools' => ['name' => 'Écoles', 'icon' => 'fa-school']
];

$current_tab = $_GET['tab'] ?? 'teachers';

// Enseignants favoris
$favorite_teachers = [];
if ($current_tab == 'teachers') {
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name, u.profile_image, 
               ts.title as service_title, ts.price as service_price 
        FROM favorites f 
        JOIN teachers t ON f.teacher_id = t.id 
        JOIN users u ON t.user_id = u.id 
        LEFT JOIN teacher_services ts ON t.id = ts.teacher_id AND ts.is_available = 1 
        WHERE f.parent_id = ? AND f.teacher_id IS NOT NULL 
        GROUP BY t.id 
        ORDER BY u.full_name
    ");
    $stmt->execute([$parent['id']]);
    $favorite_teachers = $stmt->fetchAll();
}

// Écoles favorites
$favorite_schools = [];
if ($current_tab == 'schools') {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name 
        FROM favorites f 
        JOIN schools s ON f.school_id = s.id 
        JOIN users u ON s.user_id = u.id 
        WHERE f.parent_id = ? AND f.school_id IS NOT NULL 
        ORDER BY s.school_name
    ");
    $stmt->execute([$parent['id']]);
    $favorite_schools = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Favoris - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-heart"></i> Mes Favoris</h3>
            <p>Vos préférés</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i> Mon Profil
            </a>
            <a href="children.php">
                <i class="fas fa-child"></i> Mes Enfants
            </a>
            <a href="appointments.php">
                <i class="fas fa-calendar-alt"></i> Mes Rendez-vous
            </a>
            <a href="favorites.php" class="active">
                <i class="fas fa-heart"></i> Favoris
            </a>
            <a href="reviews.php">
                <i class="fas fa-star"></i> Mes Avis
            </a>
            <a href="../messages.php">
                <i class="fas fa-envelope"></i> Messages
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
                <input type="text" placeholder="Rechercher dans les favoris...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">Mes Favoris</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($info)): ?>
                <div class="alert alert-warning" style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $info; ?>
                </div>
            <?php endif; ?>
            
            <!-- Onglets -->
            <div class="tabs">
                <?php foreach($tabs as $tab_key => $tab): ?>
                    <button class="tab-button <?php echo $current_tab == $tab_key ? 'active' : ''; ?>" 
                            onclick="switchTab('<?php echo $tab_key; ?>')">
                        <i class="fas <?php echo $tab['icon']; ?>"></i> 
                        <?php echo $tab['name']; ?>
                        (<?php echo $tab_key == 'teachers' ? count($favorite_teachers) : count($favorite_schools); ?>)
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Contenu des onglets -->
            <div class="tab-content active">
                <?php if ($current_tab == 'teachers'): ?>
                    <?php if (count($favorite_teachers) > 0): ?>
                        <div class="teachers-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                            <?php foreach($favorite_teachers as $teacher): ?>
                                <div class="teacher-card" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); position: relative;">
                                    <!-- Bouton retirer des favoris -->
                                    <a href="favorites.php?tab=teachers&remove=1&type=teacher&id=<?php echo $teacher['id']; ?>" 
                                       class="favorite-btn" 
                                       style="position: absolute; top: 15px; right: 15px; color: #e74c3c; text-decoration: none; font-size: 1.2rem;"
                                       onclick="return confirm('Retirer cet enseignant des favoris ?');">
                                        <i class="fas fa-heart-broken"></i>
                                    </a>
                                    
                                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                        <div class="teacher-avatar" style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; flex-shrink: 0;">
                                            <?php if ($teacher['profile_image']): ?>
                                                <img src="../../uploads/<?php echo $teacher['profile_image']; ?>" 
                                                     alt="<?php echo $teacher['full_name']; ?>" 
                                                     style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 100%; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-user-graduate fa-2x" style="color: #ccc;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0 0 5px;"><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
                                            <p style="color: #666; margin: 0 0 5px;"><?php echo htmlspecialchars($teacher['specialization']); ?></p>
                                            <div style="color: #f39c12;">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= floor($teacher['rating']) ? 'filled' : ''; ?>"></i>
                                                <?php endfor; ?>
                                                <span style="color: #95a5a6; font-size: 0.9rem;">(<?php echo $teacher['total_reviews']; ?> avis)</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-bottom: 15px;">
                                        <p style="margin: 5px 0; color: #666;">
                                            <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($teacher['qualification']); ?>
                                        </p>
                                        <p style="margin: 5px 0; color: #666;">
                                            <i class="fas fa-clock"></i> <?php echo $teacher['experience_years']; ?> ans d'expérience
                                        </p>
                                        <p style="margin: 5px 0; color: #666;">
                                            <i class="fas fa-money-bill-wave"></i> <?php echo number_format($teacher['hourly_rate'], 0, ',', ' '); ?> FCFA/h
                                        </p>
                                    </div>
                                    
                                    <?php if ($teacher['service_title']): ?>
                                        <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                                            <p style="margin: 0; font-weight: 500;"><?php echo htmlspecialchars($teacher['service_title']); ?></p>
                                            <p style="margin: 5px 0 0; color: #666;"><?php echo number_format($teacher['service_price'], 0, ',', ' '); ?> FCFA</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <a href="../platform/teacher-profile.php?id=<?php echo $teacher['id']; ?>" class="btn-small">
                                            <i class="fas fa-eye"></i> Voir profil
                                        </a>
                                        <a href="appointments.php?teacher_id=<?php echo $teacher['id']; ?>" class="btn-small btn-primary">
                                            <i class="fas fa-calendar-plus"></i> Prendre RDV
                                        </a>
                                        <a href="../messages.php?contact=<?php echo $teacher['user_id']; ?>" class="btn-small btn-secondary">
                                            <i class="fas fa-envelope"></i> Contacter
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                            <i class="fas fa-heart fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                            <h3>Aucun enseignant favori</h3>
                            <p>Ajoutez des enseignants à vos favoris pour les retrouver facilement.</p>
                            <a href="../platform/teachers.php" class="btn-primary">
                                <i class="fas fa-search"></i> Chercher des enseignants
                            </a>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <?php if (count($favorite_schools) > 0): ?>
                        <div class="schools-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                            <?php foreach($favorite_schools as $school): ?>
                                <div class="school-card" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); position: relative;">
                                    <!-- Bouton retirer des favoris -->
                                    <a href="favorites.php?tab=schools&remove=1&type=school&id=<?php echo $school['id']; ?>" 
                                       class="favorite-btn" 
                                       style="position: absolute; top: 15px; right: 15px; color: #e74c3c; text-decoration: none; font-size: 1.2rem;"
                                       onclick="return confirm('Retirer cette école des favoris ?');">
                                        <i class="fas fa-heart-broken"></i>
                                    </a>
                                    
                                    <div style="margin-bottom: 15px;">
                                        <h4 style="margin: 0 0 10px;"><?php echo htmlspecialchars($school['school_name']); ?></h4>
                                        <p style="color: #666; margin: 0 0 10px;">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($school['city'] . ', ' . $school['country']); ?>
                                        </p>
                                        <p style="margin: 0;">
                                            <span style="background: #3498db; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.8rem;">
                                                <?php 
                                                $types = [
                                                    'public' => 'École publique',
                                                    'private' => 'École privée',
                                                    'international' => 'École internationale'
                                                ];
                                                echo $types[$school['school_type']] ?? 'École';
                                                ?>
                                            </span>
                                        </p>
                                    </div>
                                    
                                    <div style="margin-bottom: 15px;">
                                        <?php if ($school['established_year']): ?>
                                            <p style="margin: 5px 0; color: #666;">
                                                <i class="fas fa-calendar-alt"></i> Fondée en <?php echo $school['established_year']; ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($school['phone']): ?>
                                            <p style="margin: 5px 0; color: #666;">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($school['phone']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($school['email']): ?>
                                            <p style="margin: 5px 0; color: #666;">
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($school['email']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($school['description']): ?>
                                        <div style="margin-bottom: 15px;">
                                            <p style="color: #666; line-height: 1.5; font-size: 0.9rem;">
                                                <?php echo substr(htmlspecialchars($school['description']), 0, 150); ?>...
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <a href="../platform/school-profile.php?id=<?php echo $school['id']; ?>" class="btn-small">
                                            <i class="fas fa-eye"></i> Voir détails
                                        </a>
                                        <?php if ($school['website']): ?>
                                            <a href="<?php echo htmlspecialchars($school['website']); ?>" target="_blank" class="btn-small btn-secondary">
                                                <i class="fas fa-globe"></i> Site web
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                            <i class="fas fa-school fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                            <h3>Aucune école favorite</h3>
                            <p>Ajoutez des écoles à vos favoris pour les retrouver facilement.</p>
                            <a href="../platform/schools.php" class="btn-primary">
                                <i class="fas fa-search"></i> Chercher des écoles
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    function switchTab(tabName) {
        window.location.href = `favorites.php?tab=${tabName}`;
    }
    
    // Recherche en temps réel
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('.teacher-card, .school-card');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    </script>
</body>
</html>