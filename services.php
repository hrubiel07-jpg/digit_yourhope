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

// Ajouter un nouveau service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $service_type = sanitize($_POST['service_type']);
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    $duration = sanitize($_POST['duration']);
    
    $stmt = $pdo->prepare("
        INSERT INTO teacher_services (teacher_id, service_type, title, description, price, duration) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$teacher_id, $service_type, $title, $description, $price, $duration])) {
        $success = "Service ajouté avec succès!";
    } else {
        $error = "Erreur lors de l'ajout du service";
    }
}

// Mettre à jour un service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $service_id = intval($_POST['service_id']);
    $service_type = sanitize($_POST['service_type']);
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    $duration = sanitize($_POST['duration']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    $stmt = $pdo->prepare("
        UPDATE teacher_services SET 
            service_type = ?, 
            title = ?, 
            description = ?, 
            price = ?, 
            duration = ?, 
            is_available = ? 
        WHERE id = ? AND teacher_id = ?
    ");
    
    if ($stmt->execute([$service_type, $title, $description, $price, $duration, $is_available, $service_id, $teacher_id])) {
        $success = "Service mis à jour avec succès!";
    } else {
        $error = "Erreur lors de la mise à jour du service";
    }
}

// Supprimer un service
if (isset($_GET['delete'])) {
    $service_id = intval($_GET['delete']);
    
    $stmt = $pdo->prepare("DELETE FROM teacher_services WHERE id = ? AND teacher_id = ?");
    if ($stmt->execute([$service_id, $teacher_id])) {
        $success = "Service supprimé avec succès!";
    } else {
        $error = "Erreur lors de la suppression du service";
    }
}

// Récupérer tous les services de l'enseignant
$stmt = $pdo->prepare("SELECT * FROM teacher_services WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$teacher_id]);
$services = $stmt->fetchAll();

// Récupérer un service pour modification
$service_to_edit = null;
if (isset($_GET['edit'])) {
    $service_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM teacher_services WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$service_id, $teacher_id]);
    $service_to_edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Services - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-chalkboard-teacher"></i> Espace Enseignant</h3>
            <p>Gestion des services</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i> Mon Profil
            </a>
            <a href="services.php" class="active">
                <i class="fas fa-concierge-bell"></i> Mes Services
            </a>
            <a href="applications.php">
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
                <input type="text" placeholder="Rechercher un service...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">Mes Services</h1>
            
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
            
            <div class="form-section">
                <h3>
                    <i class="fas <?php echo $service_to_edit ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                    <?php echo $service_to_edit ? 'Modifier le service' : 'Ajouter un nouveau service'; ?>
                </h3>
                
                <form method="POST">
                    <?php if ($service_to_edit): ?>
                        <input type="hidden" name="service_id" value="<?php echo $service_to_edit['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="service_type">Type de service *</label>
                            <select id="service_type" name="service_type" required>
                                <option value="">Sélectionnez un type</option>
                                <option value="home_tutoring" <?php echo ($service_to_edit['service_type'] ?? '') == 'home_tutoring' ? 'selected' : ''; ?>>
                                    Encadrement à domicile
                                </option>
                                <option value="td_correction" <?php echo ($service_to_edit['service_type'] ?? '') == 'td_correction' ? 'selected' : ''; ?>>
                                    Correction de TD/Exercices
                                </option>
                                <option value="online_course" <?php echo ($service_to_edit['service_type'] ?? '') == 'online_course' ? 'selected' : ''; ?>>
                                    Cours en ligne
                                </option>
                                <option value="test_preparation" <?php echo ($service_to_edit['service_type'] ?? '') == 'test_preparation' ? 'selected' : ''; ?>>
                                    Préparation aux examens
                                </option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Titre du service *</label>
                            <input type="text" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($service_to_edit['title'] ?? ''); ?>" 
                                   placeholder="Ex: Cours de Mathématiques Terminale" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Prix (FCFA) *</label>
                            <input type="number" id="price" name="price" 
                                   value="<?php echo $service_to_edit['price'] ?? ''; ?>" 
                                   min="0" step="500" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Durée *</label>
                            <input type="text" id="duration" name="duration" 
                                   value="<?php echo htmlspecialchars($service_to_edit['duration'] ?? ''); ?>" 
                                   placeholder="Ex: 1h30, 2h, par session" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description détaillée *</label>
                        <textarea id="description" name="description" rows="4" required 
                                  placeholder="Décrivez votre service en détail..."><?php echo htmlspecialchars($service_to_edit['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <?php if ($service_to_edit): ?>
                        <div class="form-group">
                            <label class="checkbox">
                                <input type="checkbox" name="is_available" value="1" 
                                    <?php echo ($service_to_edit['is_available'] ?? 0) ? 'checked' : ''; ?>>
                                <span>Service disponible</span>
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-buttons">
                        <button type="submit" name="<?php echo $service_to_edit ? 'update_service' : 'add_service'; ?>" 
                                class="btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $service_to_edit ? 'Mettre à jour' : 'Ajouter le service'; ?>
                        </button>
                        
                        <?php if ($service_to_edit): ?>
                            <a href="services.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-list"></i> Mes services (<?php echo count($services); ?>)</h3>
                
                <?php if (count($services) > 0): ?>
                    <div class="services-grid">
                        <?php foreach($services as $service): ?>
                            <div class="service-card">
                                <div class="service-header">
                                    <h4><?php echo htmlspecialchars($service['title']); ?></h4>
                                    <span class="service-type">
                                        <?php 
                                        $types = [
                                            'home_tutoring' => 'À domicile',
                                            'td_correction' => 'Correction TD',
                                            'online_course' => 'En ligne',
                                            'test_preparation' => 'Préparation examens'
                                        ];
                                        echo $types[$service['service_type']] ?? $service['service_type'];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="service-details">
                                    <p><i class="fas fa-money-bill-wave"></i> <strong>Prix:</strong> <?php echo number_format($service['price'], 0, ',', ' '); ?> FCFA</p>
                                    <p><i class="fas fa-clock"></i> <strong>Durée:</strong> <?php echo htmlspecialchars($service['duration']); ?></p>
                                    <p><i class="fas fa-calendar-check"></i> <strong>Disponibilité:</strong> 
                                        <span class="<?php echo $service['is_available'] ? 'status-accepted' : 'status-rejected'; ?>" style="padding: 3px 8px; border-radius: 3px;">
                                            <?php echo $service['is_available'] ? 'Disponible' : 'Indisponible'; ?>
                                        </span>
                                    </p>
                                </div>
                                
                                <div class="service-description">
                                    <p><?php echo substr(htmlspecialchars($service['description']), 0, 150); ?>...</p>
                                </div>
                                
                                <div class="service-actions">
                                    <a href="services.php?edit=<?php echo $service['id']; ?>" class="btn-small">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="services.php?delete=<?php echo $service['id']; ?>" 
                                       class="btn-small" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce service?');"
                                       style="background: #e74c3c;">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-concierge-bell fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucun service ajouté</h3>
                        <p>Commencez par ajouter votre premier service pour être visible par les parents.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/dashboard.js"></script>
    <style>
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .service-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: box-shadow 0.3s;
        }
        
        .service-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .service-header h4 {
            margin: 0;
            color: #2c3e50;
            flex: 1;
        }
        
        .service-type {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .service-details {
            margin-bottom: 15px;
        }
        
        .service-details p {
            margin: 5px 0;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .service-details i {
            width: 20px;
            color: #7f8c8d;
        }
        
        .service-description {
            margin-bottom: 15px;
            color: #666;
            line-height: 1.5;
        }
        
        .service-actions {
            display: flex;
            gap: 10px;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</body>
</html>