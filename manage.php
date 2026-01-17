<?php
require_once '../includes/config.php';

// Vérifier si l'utilisateur est admin
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Traitement des actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'activate_user':
            $user_id = $_GET['id'];
            $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$user_id]);
            $_SESSION['success'] = "Utilisateur activé avec succès!";
            break;
            
        case 'deactivate_user':
            $user_id = $_GET['id'];
            $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$user_id]);
            $_SESSION['success'] = "Utilisateur désactivé avec succès!";
            break;
            
        case 'verify_user':
            $user_id = $_GET['id'];
            $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$user_id]);
            $_SESSION['success'] = "Utilisateur vérifié avec succès!";
            break;
    }
    
    header('Location: manage.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filtres
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Construire la requête
$query = "SELECT u.* FROM users u WHERE 1=1";
$params = [];

if ($type !== 'all') {
    $query .= " AND u.user_type = ?";
    $params[] = $type;
}

if ($status !== 'all') {
    if ($status === 'active') {
        $query .= " AND u.is_active = 1";
    } elseif ($status === 'inactive') {
        $query .= " AND u.is_active = 0";
    } elseif ($status === 'verified') {
        $query .= " AND u.is_verified = 1";
    } elseif ($status === 'unverified') {
        $query .= " AND u.is_verified = 0";
    }
}

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Compter le total
$countQuery = str_replace('SELECT u.*', 'SELECT COUNT(*) as total', $query);
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Récupérer les données
$query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Récupérer les détails supplémentaires
foreach ($users as &$user) {
    switch ($user['user_type']) {
        case 'school':
            $stmt = $pdo->prepare("SELECT * FROM schools WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $user['details'] = $stmt->fetch();
            break;
            
        case 'teacher':
            $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $user['details'] = $stmt->fetch();
            break;
            
        case 'parent':
            $stmt = $pdo->prepare("SELECT * FROM parents WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $user['details'] = $stmt->fetch();
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            margin: 10px 0;
            color: #2c3e50;
        }
        
        .stat-card p {
            color: #7f8c8d;
            margin: 0;
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .filter-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .filter-select, .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #eaeaea;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .filter-select:focus, .filter-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .users-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: #f8f9fa;
            border-bottom: 2px solid #eaeaea;
        }
        
        .table-title {
            color: #2c3e50;
            margin: 0;
        }
        
        .table-count {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th {
            background: #f8f9fa;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #eaeaea;
        }
        
        .users-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f1f1;
            color: #666;
        }
        
        .users-table tr:hover {
            background: #f8f9fa;
        }
        
        .user-type {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .type-school { background: #e3f2fd; color: #1976d2; }
        .type-teacher { background: #f3e5f5; color: #7b1fa2; }
        .type-parent { background: #e8f5e9; color: #388e3c; }
        
        .user-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-verified { background: #cce5ff; color: #004085; }
        .status-unverified { background: #fff3cd; color: #856404; }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-view { background: #3498db; color: white; }
        .btn-edit { background: #f39c12; color: white; }
        .btn-activate { background: #2ecc71; color: white; }
        .btn-deactivate { background: #e74c3c; color: white; }
        .btn-verify { background: #9b59b6; color: white; }
        .btn-delete { background: #34495e; color: white; }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .page-link {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #3498db;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover, .page-link.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .no-data {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
        }
        
        .no-data i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="admin">
    <div class="admin-container">
        <div class="admin-header">
            <div class="container">
                <h1 style="color: white; margin-bottom: 10px;">
                    <i class="fas fa-shield-alt"></i> Administration
                </h1>
                <p style="color: rgba(255,255,255,0.9);">Gestion complète de la plateforme</p>
            </div>
        </div>
        
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <?php
            $total_schools = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'school'")->fetchColumn();
            $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'teacher'")->fetchColumn();
            $total_parents = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'parent'")->fetchColumn();
            $total_users = $total_schools + $total_teachers + $total_parents;
            ?>
            
            <div class="admin-stats">
                <div class="stat-card">
                    <i class="fas fa-users" style="color: #3498db;"></i>
                    <h3><?php echo $total_users; ?></h3>
                    <p>Utilisateurs totaux</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-school" style="color: #2ecc71;"></i>
                    <h3><?php echo $total_schools; ?></h3>
                    <p>Écoles</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-chalkboard-teacher" style="color: #9b59b6;"></i>
                    <h3><?php echo $total_teachers; ?></h3>
                    <p>Enseignants</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-user-friends" style="color: #f39c12;"></i>
                    <h3><?php echo $total_parents; ?></h3>
                    <p>Parents</p>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="filter-section">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50;">
                    <i class="fas fa-filter"></i> Filtres de recherche
                </h3>
                
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Type d'utilisateur</label>
                            <select name="type" class="filter-select">
                                <option value="all" <?php echo $type == 'all' ? 'selected' : ''; ?>>Tous les types</option>
                                <option value="school" <?php echo $type == 'school' ? 'selected' : ''; ?>>Écoles</option>
                                <option value="teacher" <?php echo $type == 'teacher' ? 'selected' : ''; ?>>Enseignants</option>
                                <option value="parent" <?php echo $type == 'parent' ? 'selected' : ''; ?>>Parents</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Statut</label>
                            <select name="status" class="filter-select">
                                <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Actifs</option>
                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
                                <option value="verified" <?php echo $status == 'verified' ? 'selected' : ''; ?>>Vérifiés</option>
                                <option value="unverified" <?php echo $status == 'unverified' ? 'selected' : ''; ?>>Non vérifiés</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Recherche</label>
                            <input type="text" name="search" class="filter-input" 
                                   placeholder="Nom, email ou téléphone..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="btn-primary" style="padding: 12px 30px;">
                            <i class="fas fa-search"></i> Appliquer les filtres
                        </button>
                        <a href="manage.php" class="btn-secondary" style="padding: 12px 30px;">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Tableau des utilisateurs -->
            <div class="users-table-container">
                <div class="table-header">
                    <h3 class="table-title">Gestion des utilisateurs</h3>
                    <span class="table-count"><?php echo $total; ?> résultat(s) trouvé(s)</span>
                </div>
                
                <?php if (count($users) > 0): ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Utilisateur</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #f8f9fa; 
                                                    display: flex; align-items: center; justify-content: center;">
                                            <?php if ($user['profile_image']): ?>
                                                <img src="../uploads/<?php echo $user['profile_image']; ?>" 
                                                     alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%;">
                                            <?php else: ?>
                                                <i class="fas fa-user" style="color: #ccc;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                            <div style="color: #7f8c8d; font-size: 0.9rem;"><?php echo $user['email']; ?></div>
                                            <div style="color: #95a5a6; font-size: 0.8rem;"><?php echo $user['phone']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="user-type type-<?php echo $user['user_type']; ?>">
                                        <?php 
                                        $types = [
                                            'school' => 'École',
                                            'teacher' => 'Enseignant',
                                            'parent' => 'Parent'
                                        ];
                                        echo $types[$user['user_type']];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 5px;">
                                        <span class="user-status status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                        <span class="user-status status-<?php echo $user['is_verified'] ? 'verified' : 'unverified'; ?>">
                                            <?php echo $user['is_verified'] ? 'Vérifié' : 'Non vérifié'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                    <div style="color: #95a5a6; font-size: 0.8rem;">
                                        <?php echo date('H:i', strtotime($user['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['user_type'] == 'school' && isset($user['details'])): ?>
                                            <a href="../platform/school-profile.php?id=<?php echo $user['details']['id']; ?>" 
                                               target="_blank" class="btn-action btn-view" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../dashboard/school/profile.php" 
                                               target="_blank" class="btn-action btn-edit" title="Éditer">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php elseif ($user['user_type'] == 'teacher' && isset($user['details'])): ?>
                                            <a href="../platform/teacher-profile.php?id=<?php echo $user['details']['id']; ?>" 
                                               target="_blank" class="btn-action btn-view" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../dashboard/teacher/profile.php" 
                                               target="_blank" class="btn-action btn-edit" title="Éditer">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['is_active']): ?>
                                            <a href="manage.php?action=deactivate_user&id=<?php echo $user['id']; ?>" 
                                               class="btn-action btn-deactivate" title="Désactiver">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="manage.php?action=activate_user&id=<?php echo $user['id']; ?>" 
                                               class="btn-action btn-activate" title="Activer">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!$user['is_verified']): ?>
                                            <a href="manage.php?action=verify_user&id=<?php echo $user['id']; ?>" 
                                               class="btn-action btn-verify" title="Vérifier">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="delete.php?type=<?php echo $user['user_type']; ?>&id=<?php echo isset($user['details']['id']) ? $user['details']['id'] : $user['id']; ?>" 
                                           class="btn-action btn-delete" title="Supprimer">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="manage.php?page=<?php echo $i; ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-users-slash"></i>
                        <h3>Aucun utilisateur trouvé</h3>
                        <p>Aucun utilisateur ne correspond à vos critères de recherche.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    // Confirmation pour les actions sensibles
    document.addEventListener('DOMContentLoaded', function() {
        const deleteLinks = document.querySelectorAll('.btn-delete');
        const deactivateLinks = document.querySelectorAll('.btn-deactivate');
        
        deleteLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')) {
                    e.preventDefault();
                }
            });
        });
        
        deactivateLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir désactiver cet utilisateur ?')) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
</body>
</html>