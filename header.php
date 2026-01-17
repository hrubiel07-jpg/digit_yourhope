<?php
// Vérifier si config.php a été inclus
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

// Sécurité : vérifier si le fichier est appelé directement
if (basename($_SERVER['PHP_SELF']) == 'header.php') {
    die('Accès direct non autorisé');
}

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']);
$user_type = $_SESSION['user_type'] ?? null;
$full_name = $_SESSION['full_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php if (isset($additional_css)): ?>
        <link rel="stylesheet" href="<?php echo $additional_css; ?>">
    <?php endif; ?>
</head>
<body class="<?php echo isset($body_class) ? $body_class : ''; ?>">
    <!-- Navigation principale -->
    <?php if (!isset($hide_navigation) || !$hide_navigation): ?>
    <nav class="navbar">
        <div class="container">
            <a href="<?php echo SITE_URL; ?>index.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>Digital YOURHOPE</span>
            </a>
            
            <button class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-links">
                <a href="<?php echo SITE_URL; ?>index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>
                    Accueil
                </a>
                <a href="<?php echo SITE_URL; ?>platform/schools.php" <?php echo strpos($_SERVER['PHP_SELF'], 'schools.php') !== false ? 'class="active"' : ''; ?>>
                    Écoles
                </a>
                <a href="<?php echo SITE_URL; ?>platform/teachers.php" <?php echo strpos($_SERVER['PHP_SELF'], 'teachers.php') !== false ? 'class="active"' : ''; ?>>
                    Enseignants
                </a>
                <a href="<?php echo SITE_URL; ?>platform/search.php" <?php echo strpos($_SERVER['PHP_SELF'], 'search.php') !== false ? 'class="active"' : ''; ?>>
                    Recherche avancée
                </a>
                
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo SITE_URL; ?>dashboard/<?php echo $user_type; ?>/index.php" 
                       class="btn-login">
                        <i class="fas fa-tachometer-alt"></i> Tableau de bord
                    </a>
                    <a href="<?php echo SITE_URL; ?>auth/logout.php" class="btn-primary">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>auth/login.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Connexion
                    </a>
                    <a href="<?php echo SITE_URL; ?>auth/register.php" class="btn-primary">
                        <i class="fas fa-user-plus"></i> S'inscrire
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Conteneur principal -->
    <div class="main-container">