<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès refusé - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>Digital YOURHOPE</span>
            </a>
            <div class="nav-links">
                <a href="index.php">Accueil</a>
                <a href="platform/schools.php">Écoles</a>
                <a href="platform/teachers.php">Enseignants</a>
                <a href="auth/login.php">Connexion</a>
                <a href="auth/register.php">Inscription</a>
            </div>
        </div>
    </nav>

    <section class="error-page">
        <div class="container">
            <div class="error-content">
                <i class="fas fa-lock fa-5x"></i>
                <h1>403 - Accès refusé</h1>
                <p>Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
                <div class="error-actions">
                    <a href="index.php" class="btn-primary">
                        <i class="fas fa-home"></i> Retour à l'accueil
                    </a>
                    <a href="auth/login.php" class="btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </a>
                </div>
            </div>
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
                    <a href="platform/schools.php">Écoles</a>
                    <a href="platform/teachers.php">Enseignants</a>
                    <a href="auth/login.php">Connexion</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Digital YOURHOPE. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <style>
        .error-page {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 0;
        }
        
        .error-content {
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .error-content i {
            color: #e74c3c;
            margin-bottom: 30px;
        }
        
        .error-content h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .error-content p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 40px;
        }
        
        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .error-actions {
                flex-direction: column;
            }
            
            .error-content h1 {
                font-size: 2rem;
            }
        }
    </style>
</body>
</html>