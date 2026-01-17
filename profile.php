<?php
require_once '../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'parent') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les infos du parent
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name, u.email, u.phone, u.profile_image 
    FROM parents p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");
$stmt->execute([$user_id]);
$parent = $stmt->fetch();

// Récupérer les enfants
$stmt = $pdo->prepare("
    SELECT child_name, child_age, child_grade, id 
    FROM parent_children 
    WHERE parent_id = ? 
    ORDER BY child_name
");
$stmt->execute([$parent['id']]);
$children = $stmt->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $preferred_location = sanitize($_POST['preferred_location'] ?? '');
        $preferred_subjects = sanitize($_POST['preferred_subjects'] ?? '');
        
        // Validation
        $errors = [];
        if (empty($full_name)) $errors[] = "Le nom complet est obligatoire";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Mettre à jour la table users
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $user_id]);
                
                // Mettre à jour la table parents
                $stmt = $pdo->prepare("
                    UPDATE parents SET 
                        address = ?, 
                        preferred_location = ?, 
                        preferred_subjects = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$address, $preferred_location, $preferred_subjects, $user_id]);
                
                $pdo->commit();
                $success = "Profil mis à jour avec succès!";
                
                // Recharger les données
                $stmt = $pdo->prepare("SELECT p.*, u.full_name, u.email, u.phone FROM parents p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
                $stmt->execute([$user_id]);
                $parent = $stmt->fetch();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
        }
    }
    
    // Ajouter un enfant
    if (isset($_POST['add_child'])) {
        $child_name = sanitize($_POST['child_name'] ?? '');
        $child_age = intval($_POST['child_age'] ?? 0);
        $child_grade = sanitize($_POST['child_grade'] ?? '');
        
        if (empty($child_name) || $child_age <= 0) {
            $errors[] = "Nom et âge de l'enfant sont obligatoires";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO parent_children (parent_id, child_name, child_age, child_grade) 
                VALUES (?, ?, ?, ?)
            ");
            if ($stmt->execute([$parent['id'], $child_name, $child_age, $child_grade])) {
                $success = "Enfant ajouté avec succès!";
                $stmt = $pdo->prepare("SELECT child_name, child_age, child_grade, id FROM parent_children WHERE parent_id = ? ORDER BY child_name");
                $stmt->execute([$parent['id']]);
                $children = $stmt->fetchAll();
            } else {
                $errors[] = "Erreur lors de l'ajout de l'enfant";
            }
        }
    }
    
    // Supprimer un enfant
    if (isset($_GET['delete_child'])) {
        $child_id = intval($_GET['delete_child']);
        $stmt = $pdo->prepare("DELETE FROM parent_children WHERE id = ? AND parent_id = ?");
        if ($stmt->execute([$child_id, $parent['id']])) {
            $success = "Enfant supprimé avec succès!";
            $stmt = $pdo->prepare("SELECT child_name, child_age, child_grade, id FROM parent_children WHERE parent_id = ? ORDER BY child_name");
            $stmt->execute([$parent['id']]);
            $children = $stmt->fetchAll();
        }
    }
}

// Gestion de l'upload de photo
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024;
    
    if (in_array($_FILES['profile_image']['type'], $allowedTypes) && $_FILES['profile_image']['size'] <= $maxSize) {
        $uploadDir = '../../uploads/parents/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filename = 'parent_' . $parent['id'] . '_' . time() . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
            $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            if ($stmt->execute([$filename, $user_id])) {
                $parent['profile_image'] = $filename;
                $success = "Photo de profil mise à jour avec succès!";
            }
        }
    } else {
        $errors[] = "Format de fichier non supporté ou fichier trop volumineux (max 5MB)";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil Parent - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-friends"></i> <?php echo $parent['full_name']; ?></h3>
            <p>Espace Parent</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="profile.php" class="active">
                <i class="fas fa-user"></i> Mon Profil
            </a>
            <a href="children.php">
                <i class="fas fa-child"></i> Mes Enfants
            </a>
            <a href="appointments.php">
                <i class="fas fa-calendar-alt"></i> Mes Rendez-vous
            </a>
            <a href="favorites.php">
                <i class="fas fa-heart"></i> Favoris
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
                <input type="text" placeholder="Rechercher...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <?php if ($parent['profile_image']): ?>
                    <img src="../../uploads/parents/<?php echo $parent['profile_image']; ?>" alt="Avatar">
                <?php else: ?>
                    <img src="../../assets/images/default-avatar.png" alt="Avatar">
                <?php endif; ?>
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">Mon Profil</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errors)): ?>
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php foreach($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Informations personnelles</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Nom complet *</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($parent['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($parent['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Téléphone *</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($parent['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Photo de profil</label>
                            <div style="margin-top: 10px;">
                                <?php if ($parent['profile_image']): ?>
                                    <img src="../../uploads/parents/<?php echo $parent['profile_image']; ?>" 
                                         alt="Photo de profil" 
                                         style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100px; height: 100px; border-radius: 50%; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user fa-2x" style="color: #ccc;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="profile_image" accept="image/*" style="margin-top: 10px;">
                            <small style="color: #666;">Formats: JPG, PNG, GIF (max 5MB)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Adresse</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($parent['address']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="preferred_location">Localisation préférée</label>
                            <input type="text" id="preferred_location" name="preferred_location" 
                                   value="<?php echo htmlspecialchars($parent['preferred_location']); ?>"
                                   placeholder="Ex: Dakar, Plateau">
                        </div>
                        
                        <div class="form-group">
                            <label for="preferred_subjects">Matières recherchées</label>
                            <input type="text" id="preferred_subjects" name="preferred_subjects" 
                                   value="<?php echo htmlspecialchars($parent['preferred_subjects']); ?>"
                                   placeholder="Ex: Mathématiques, Physique (séparées par des virgules)">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </form>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-child"></i> Mes enfants</h3>
                
                <?php if (count($children) > 0): ?>
                <table class="data-table" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Âge</th>
                            <th>Niveau scolaire</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($children as $child): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($child['child_name']); ?></td>
                            <td><?php echo $child['child_age']; ?> ans</td>
                            <td><?php echo htmlspecialchars($child['child_grade']); ?></td>
                            <td>
                                <a href="profile.php?delete_child=<?php echo $child['id']; ?>" 
                                   class="btn-small btn-danger"
                                   onclick="return confirm('Supprimer cet enfant ?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="no-data">Aucun enfant enregistré.</p>
                <?php endif; ?>
                
                <h4 style="margin: 30px 0 15px;">Ajouter un enfant</h4>
                <form method="POST" class="child-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="child_name">Nom de l'enfant *</label>
                            <input type="text" id="child_name" name="child_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="child_age">Âge *</label>
                            <input type="number" id="child_age" name="child_age" min="3" max="25" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="child_grade">Niveau scolaire</label>
                            <input type="text" id="child_grade" name="child_grade" 
                                   placeholder="Ex: CM2, 3ème, Terminale">
                        </div>
                    </div>
                    
                    <button type="submit" name="add_child" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter l'enfant
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>