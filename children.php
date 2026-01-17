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

// Récupérer les enfants
$stmt = $pdo->prepare("
    SELECT pc.*, 
           (SELECT COUNT(*) FROM appointments WHERE parent_id = ? AND child_id = pc.id) as total_appointments,
           (SELECT COUNT(*) FROM appointments WHERE parent_id = ? AND child_id = pc.id AND status = 'completed') as completed_appointments
    FROM parent_children pc 
    WHERE pc.parent_id = ? 
    ORDER BY pc.child_name
");
$stmt->execute([$parent['id'], $parent['id'], $parent['id']]);
$children = $stmt->fetchAll();

// Ajouter un enfant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_child'])) {
    $child_name = sanitize($_POST['child_name'] ?? '');
    $child_age = intval($_POST['child_age'] ?? 0);
    $child_grade = sanitize($_POST['child_grade'] ?? '');
    $child_school = sanitize($_POST['child_school'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (empty($child_name) || $child_age <= 0) {
        $error = "Nom et âge de l'enfant sont obligatoires";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO parent_children (parent_id, child_name, child_age, child_grade, child_school, notes) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if ($stmt->execute([$parent['id'], $child_name, $child_age, $child_grade, $child_school, $notes])) {
            $success = "Enfant ajouté avec succès!";
            // Recharger la liste
            $stmt = $pdo->prepare("SELECT pc.* FROM parent_children pc WHERE pc.parent_id = ? ORDER BY pc.child_name");
            $stmt->execute([$parent['id']]);
            $children = $stmt->fetchAll();
        } else {
            $error = "Erreur lors de l'ajout de l'enfant";
        }
    }
}

// Mettre à jour un enfant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_child'])) {
    $child_id = intval($_POST['child_id']);
    $child_name = sanitize($_POST['child_name'] ?? '');
    $child_age = intval($_POST['child_age'] ?? 0);
    $child_grade = sanitize($_POST['child_grade'] ?? '');
    $child_school = sanitize($_POST['child_school'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    $stmt = $pdo->prepare("
        UPDATE parent_children SET 
            child_name = ?, 
            child_age = ?, 
            child_grade = ?, 
            child_school = ?, 
            notes = ? 
        WHERE id = ? AND parent_id = ?
    ");
    
    if ($stmt->execute([$child_name, $child_age, $child_grade, $child_school, $notes, $child_id, $parent['id']])) {
        $success = "Enfant mis à jour avec succès!";
        // Recharger la liste
        $stmt = $pdo->prepare("SELECT pc.* FROM parent_children pc WHERE pc.parent_id = ? ORDER BY pc.child_name");
        $stmt->execute([$parent['id']]);
        $children = $stmt->fetchAll();
    } else {
        $error = "Erreur lors de la mise à jour de l'enfant";
    }
}

// Supprimer un enfant
if (isset($_GET['delete'])) {
    $child_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM parent_children WHERE id = ? AND parent_id = ?");
    if ($stmt->execute([$child_id, $parent['id']])) {
        $success = "Enfant supprimé avec succès!";
        // Recharger la liste
        $stmt = $pdo->prepare("SELECT pc.* FROM parent_children pc WHERE pc.parent_id = ? ORDER BY pc.child_name");
        $stmt->execute([$parent['id']]);
        $children = $stmt->fetchAll();
    }
}

// Récupérer un enfant pour modification
$child_to_edit = null;
if (isset($_GET['edit'])) {
    $child_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM parent_children WHERE id = ? AND parent_id = ?");
    $stmt->execute([$child_id, $parent['id']]);
    $child_to_edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Enfants - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-child"></i> Mes Enfants</h3>
            <p>Gestion des profils enfants</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i> Mon Profil
            </a>
            <a href="children.php" class="active">
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
                <input type="text" placeholder="Rechercher un enfant...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">Mes Enfants</h1>
            
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
                    <i class="fas <?php echo $child_to_edit ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                    <?php echo $child_to_edit ? 'Modifier l\'enfant' : 'Ajouter un enfant'; ?>
                </h3>
                
                <form method="POST">
                    <?php if ($child_to_edit): ?>
                        <input type="hidden" name="child_id" value="<?php echo $child_to_edit['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="child_name">Nom de l'enfant *</label>
                            <input type="text" id="child_name" name="child_name" 
                                   value="<?php echo htmlspecialchars($child_to_edit['child_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="child_age">Âge *</label>
                            <input type="number" id="child_age" name="child_age" 
                                   value="<?php echo $child_to_edit['child_age'] ?? ''; ?>" 
                                   min="3" max="25" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="child_grade">Niveau scolaire</label>
                            <select id="child_grade" name="child_grade">
                                <option value="">Sélectionner un niveau</option>
                                <option value="Maternelle" <?php echo ($child_to_edit['child_grade'] ?? '') == 'Maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                                <option value="CP" <?php echo ($child_to_edit['child_grade'] ?? '') == 'CP' ? 'selected' : ''; ?>>CP</option>
                                <option value="CE1" <?php echo ($child_to_edit['child_grade'] ?? '') == 'CE1' ? 'selected' : ''; ?>>CE1</option>
                                <option value="CE2" <?php echo ($child_to_edit['child_grade'] ?? '') == 'CE2' ? 'selected' : ''; ?>>CE2</option>
                                <option value="CM1" <?php echo ($child_to_edit['child_grade'] ?? '') == 'CM1' ? 'selected' : ''; ?>>CM1</option>
                                <option value="CM2" <?php echo ($child_to_edit['child_grade'] ?? '') == 'CM2' ? 'selected' : ''; ?>>CM2</option>
                                <option value="6ème" <?php echo ($child_to_edit['child_grade'] ?? '') == '6ème' ? 'selected' : ''; ?>>6ème</option>
                                <option value="5ème" <?php echo ($child_to_edit['child_grade'] ?? '') == '5ème' ? 'selected' : ''; ?>>5ème</option>
                                <option value="4ème" <?php echo ($child_to_edit['child_grade'] ?? '') == '4ème' ? 'selected' : ''; ?>>4ème</option>
                                <option value="3ème" <?php echo ($child_to_edit['child_grade'] ?? '') == '3ème' ? 'selected' : ''; ?>>3ème</option>
                                <option value="2nde" <?php echo ($child_to_edit['child_grade'] ?? '') == '2nde' ? 'selected' : ''; ?>>2nde</option>
                                <option value="1ère" <?php echo ($child_to_edit['child_grade'] ?? '') == '1ère' ? 'selected' : ''; ?>>1ère</option>
                                <option value="Terminale" <?php echo ($child_to_edit['child_grade'] ?? '') == 'Terminale' ? 'selected' : ''; ?>>Terminale</option>
                                <option value="Université" <?php echo ($child_to_edit['child_grade'] ?? '') == 'Université' ? 'selected' : ''; ?>>Université</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="child_school">École/Établissement</label>
                            <input type="text" id="child_school" name="child_school" 
                                   value="<?php echo htmlspecialchars($child_to_edit['child_school'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes supplémentaires</label>
                        <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($child_to_edit['notes'] ?? ''); ?></textarea>
                        <small style="color: #666;">Difficultés particulières, centres d'intérêt, etc.</small>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="<?php echo $child_to_edit ? 'update_child' : 'add_child'; ?>" 
                                class="btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $child_to_edit ? 'Mettre à jour' : 'Ajouter l\'enfant'; ?>
                        </button>
                        
                        <?php if ($child_to_edit): ?>
                            <a href="children.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-list"></i> Mes enfants (<?php echo count($children); ?>)</h3>
                
                <?php if (count($children) > 0): ?>
                    <div class="children-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                        <?php foreach($children as $child): ?>
                            <div class="child-card" style="background: white; border-radius: 10px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <h4 style="margin: 0;"><?php echo htmlspecialchars($child['child_name']); ?></h4>
                                    <div style="display: flex; gap: 10px;">
                                        <a href="children.php?edit=<?php echo $child['id']; ?>" class="btn-small">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="children.php?delete=<?php echo $child['id']; ?>" 
                                           class="btn-small btn-danger"
                                           onclick="return confirm('Supprimer cet enfant ?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <p style="margin: 5px 0;"><i class="fas fa-birthday-cake" style="color: #3498db; width: 20px;"></i> <strong>Âge:</strong> <?php echo $child['child_age']; ?> ans</p>
                                    <p style="margin: 5px 0;"><i class="fas fa-graduation-cap" style="color: #3498db; width: 20px;"></i> <strong>Niveau:</strong> <?php echo htmlspecialchars($child['child_grade']); ?></p>
                                    
                                    <?php if ($child['child_school']): ?>
                                        <p style="margin: 5px 0;"><i class="fas fa-school" style="color: #3498db; width: 20px;"></i> <strong>École:</strong> <?php echo htmlspecialchars($child['child_school']); ?></p>
                                    <?php endif; ?>
                                    
                                    <p style="margin: 5px 0;">
                                        <i class="fas fa-calendar-check" style="color: #3498db; width: 20px;"></i> 
                                        <strong>RDV:</strong> <?php echo $child['completed_appointments']; ?>/<?php echo $child['total_appointments']; ?> terminés
                                    </p>
                                </div>
                                
                                <?php if ($child['notes']): ?>
                                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                        <p style="margin: 0; color: #666; font-size: 0.9rem;"><strong>Notes:</strong> <?php echo htmlspecialchars($child['notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <a href="appointments.php?child_id=<?php echo $child['id']; ?>" class="btn-small">
                                        <i class="fas fa-calendar-plus"></i> Prendre RDV
                                    </a>
                                    <a href="reviews.php?child_id=<?php echo $child['id']; ?>" class="btn-small btn-secondary">
                                        <i class="fas fa-star"></i> Voir les avis
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-child fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucun enfant enregistré</h3>
                        <p>Ajoutez vos enfants pour mieux gérer leurs accompagnements scolaires.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>