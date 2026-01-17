<?php
require_once '../includes/config.php';

// Vérifier si l'utilisateur est admin
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $entity_type = $_POST['entity_type'];
    $entity_id = $_POST['entity_id'];
    
    try {
        switch ($entity_type) {
            case 'school':
                // Supprimer l'école et ses données associées
                $stmt = $pdo->prepare("SELECT user_id FROM schools WHERE id = ?");
                $stmt->execute([$entity_id]);
                $school = $stmt->fetch();
                
                if ($school) {
                    // Supprimer d'abord les données associées
                    $pdo->prepare("DELETE FROM school_configurations WHERE school_id = ?")->execute([$entity_id]);
                    $pdo->prepare("DELETE FROM school_jobs WHERE school_id = ?")->execute([$entity_id]);
                    
                    // Supprimer l'école
                    $pdo->prepare("DELETE FROM schools WHERE id = ?")->execute([$entity_id]);
                    
                    // Supprimer l'utilisateur
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$school['user_id']]);
                    
                    $_SESSION['success'] = "École supprimée avec succès!";
                }
                break;
                
            case 'teacher':
                // Supprimer l'enseignant
                $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
                $stmt->execute([$entity_id]);
                $teacher = $stmt->fetch();
                
                if ($teacher) {
                    // Supprimer les données associées
                    $pdo->prepare("DELETE FROM teacher_services WHERE teacher_id = ?")->execute([$entity_id]);
                    $pdo->prepare("DELETE FROM teacher_applications WHERE teacher_id = ?")->execute([$entity_id]);
                    $pdo->prepare("DELETE FROM reviews WHERE teacher_id = ?")->execute([$entity_id]);
                    $pdo->prepare("DELETE FROM appointments WHERE teacher_id = ?")->execute([$entity_id]);
                    
                    // Supprimer l'enseignant
                    $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$entity_id]);
                    
                    // Supprimer l'utilisateur
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$teacher['user_id']]);
                    
                    $_SESSION['success'] = "Enseignant supprimé avec succès!";
                }
                break;
                
            case 'parent':
                // Supprimer le parent
                $stmt = $pdo->prepare("SELECT user_id FROM parents WHERE id = ?");
                $stmt->execute([$entity_id]);
                $parent = $stmt->fetch();
                
                if ($parent) {
                    // Supprimer les données associées
                    $pdo->prepare("DELETE FROM appointments WHERE parent_id = ?")->execute([$entity_id]);
                    $pdo->prepare("DELETE FROM favorites WHERE parent_id = ?")->execute([$entity_id]);
                    $pdo->prepare("DELETE FROM view_history WHERE parent_id = ?")->execute([$entity_id]);
                    
                    // Supprimer le parent
                    $pdo->prepare("DELETE FROM parents WHERE id = ?")->execute([$entity_id]);
                    
                    // Supprimer l'utilisateur
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$parent['user_id']]);
                    
                    $_SESSION['success'] = "Parent supprimé avec succès!";
                }
                break;
        }
        
        header('Location: manage.php');
        exit();
        
    } catch (Exception $e) {
        $error = "Erreur lors de la suppression: " . $e->getMessage();
    }
}

// Récupérer les informations de l'entité à supprimer
if (isset($_GET['type']) && isset($_GET['id'])) {
    $entity_type = $_GET['type'];
    $entity_id = $_GET['id'];
    
    switch ($entity_type) {
        case 'school':
            $stmt = $pdo->prepare("SELECT s.*, u.email FROM schools s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
            $stmt->execute([$entity_id]);
            $entity = $stmt->fetch();
            $entity_name = $entity['school_name'] ?? '';
            break;
            
        case 'teacher':
            $stmt = $pdo->prepare("SELECT t.*, u.full_name, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
            $stmt->execute([$entity_id]);
            $entity = $stmt->fetch();
            $entity_name = $entity['full_name'] ?? '';
            break;
            
        case 'parent':
            $stmt = $pdo->prepare("SELECT p.*, u.full_name, u.email FROM parents p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
            $stmt->execute([$entity_id]);
            $entity = $stmt->fetch();
            $entity_name = $entity['full_name'] ?? '';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppression - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .delete-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        
        .delete-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .delete-container h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .delete-container p {
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .entity-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }
        
        .entity-info h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .entity-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }
        
        .warning-box h5 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .warning-box ul {
            margin: 10px 0;
            padding-left: 20px;
            color: #856404;
        }
        
        .warning-box li {
            margin-bottom: 5px;
        }
        
        .btn-confirm-delete {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            margin-right: 15px;
        }
        
        .btn-confirm-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }
        
        .btn-cancel {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #dee2e6;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
    </style>
</head>
<body class="admin">
    <div class="delete-container">
        <div class="delete-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h2>Confirmer la suppression</h2>
        <p>Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est irréversible.</p>
        
        <?php if (isset($entity)): ?>
        <div class="entity-info">
            <h4>Informations à supprimer :</h4>
            <p><strong>Type :</strong> 
                <?php echo $entity_type == 'school' ? 'École' : ($entity_type == 'teacher' ? 'Enseignant' : 'Parent'); ?>
            </p>
            <p><strong>Nom :</strong> <?php echo htmlspecialchars($entity_name); ?></p>
            <p><strong>Email :</strong> <?php echo htmlspecialchars($entity['email']); ?></p>
            <p><strong>ID :</strong> #<?php echo $entity_id; ?></p>
        </div>
        <?php endif; ?>
        
        <div class="warning-box">
            <h5><i class="fas fa-exclamation-circle"></i> Attention !</h5>
            <p>Cette action supprimera :</p>
            <ul>
                <?php if ($entity_type == 'school'): ?>
                    <li>L'école et tous ses employés</li>
                    <li>Toutes les offres d'emploi associées</li>
                    <li>Toutes les configurations personnalisées</li>
                    <li>Toutes les candidatures liées</li>
                <?php elseif ($entity_type == 'teacher'): ?>
                    <li>Le profil de l'enseignant</li>
                    <li>Tous les services proposés</li>
                    <li>Toutes les candidatures</li>
                    <li>Tous les rendez-vous</li>
                    <li>Tous les avis et évaluations</li>
                <?php elseif ($entity_type == 'parent'): ?>
                    <li>Le profil du parent</li>
                    <li>Tous les rendez-vous</li>
                    <li>Toutes les favoris</li>
                    <li>Tout l'historique de navigation</li>
                <?php endif; ?>
            </ul>
            <p><strong>Cette action ne peut pas être annulée.</strong></p>
        </div>
        
        <div class="btn-group">
            <form method="POST">
                <input type="hidden" name="entity_type" value="<?php echo $entity_type; ?>">
                <input type="hidden" name="entity_id" value="<?php echo $entity_id; ?>">
                <button type="submit" name="confirm_delete" class="btn-confirm-delete">
                    <i class="fas fa-trash-alt"></i> Confirmer la suppression
                </button>
            </form>
            
            <a href="manage.php" class="btn-cancel">
                <i class="fas fa-times"></i> Annuler
            </a>
        </div>
    </div>
</body>
</html>