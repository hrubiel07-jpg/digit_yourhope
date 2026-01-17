<?php
require_once '../includes/config.php';

// Redirection si déjà connecté
if (isLoggedIn()) {
    header('Location: ../dashboard/' . $_SESSION['user_type'] . '/index.php');
    exit();
}

$errors = [];

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email)) $errors[] = "L'email est obligatoire";
    if (empty($password)) $errors[] = "Le mot de passe est obligatoire";
    
    if (empty($errors)) {
        // Rechercher l'utilisateur
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Vérifier le mot de passe
            if (password_verify($password, $user['password'])) {
                // Vérifier si le compte est actif
                if ($user['is_active']) {
                    // Créer la session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Mettre à jour la dernière connexion
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Rediriger vers le tableau de bord approprié
                    header('Location: ../dashboard/' . $user['user_type'] . '/index.php');
                    exit();
                } else {
                    $errors[] = "Votre compte a été désactivé. Contactez l'administrateur.";
                }
            } else {
                $errors[] = "Email ou mot de passe incorrect";
            }
        } else {
            $errors[] = "Email ou mot de passe incorrect";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .auth-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fde8e8;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        
        .btn-auth {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .auth-links a {
            color: #3498db;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .user-type-login {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .user-type-link {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            text-decoration: none;
            color: #3498db;
            transition: all 0.3s;
        }
        
        .user-type-link:hover {
            background: #3498db;
            color: white;
        }
        
        .user-type-link i {
            display: block;
            margin-bottom: 5px;
        }
    </style>
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
                <a href="../platform/schools.php">Écoles</a>
                <a href="../platform/teachers.php">Enseignants</a>
                <a href="../platform/search.php">Recherche</a>
                <a href="login.php" class="btn-login">Connexion</a>
                <a href="register.php" class="btn-primary">S'inscrire</a>
            </div>
        </div>
    </nav>

    <div class="auth-container">
        <div class="auth-header">
            <h1><i class="fas fa-sign-in-alt"></i> Connexion</h1>
            <p>Accédez à votre espace personnel</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Adresse email *</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                       required placeholder="exemple@email.com">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe *</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-primary btn-auth">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>
        
        <div class="auth-links">
            <p>Vous n'avez pas de compte ? <a href="register.php">Inscrivez-vous</a></p>
            <p><a href="forgot-password.php">Mot de passe oublié ?</a></p>
        </div>
        
        <div class="user-type-login">
            <a href="register.php?type=school" class="user-type-link">
                <i class="fas fa-school"></i>
                <span>École</span>
            </a>
            <a href="register.php?type=teacher" class="user-type-link">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Enseignant</span>
            </a>
            <a href="register.php?type=parent" class="user-type-link">
                <i class="fas fa-user-friends"></i>
                <span>Parent</span>
            </a>
        </div>
    </div>
</body>
</html>