<?php
require_once '../includes/config.php';

// Redirection si déjà connecté
if (isLoggedIn()) {
    header('Location: ../dashboard/' . $_SESSION['user_type'] . '/index.php');
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$errors = [];
$success = false;

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = sanitize($_POST['user_type'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($full_name)) $errors[] = "Le nom complet est obligatoire";
    if (empty($email)) $errors[] = "L'email est obligatoire";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format d'email invalide";
    if (empty($phone)) $errors[] = "Le téléphone est obligatoire";
    if (strlen($password) < 6) $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas";
    if (!in_array($user_type, ['school', 'teacher', 'parent'])) $errors[] = "Type d'utilisateur invalide";
    
    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Cet email est déjà utilisé";
        }
    }
    
    // Si pas d'erreurs, créer l'utilisateur
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(32));
        
        try {
            $pdo->beginTransaction();
            
            // Insérer dans la table users
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password, user_type, full_name, phone, verification_token) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$email, $hashed_password, $user_type, $full_name, $phone, $verification_token]);
            $user_id = $pdo->lastInsertId();
            
            // Insérer dans la table spécifique selon le type
            switch ($user_type) {
                case 'school':
                    $stmt = $pdo->prepare("INSERT INTO schools (user_id, school_name, email, phone) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $full_name, $email, $phone]);
                    break;
                    
                case 'teacher':
                    $stmt = $pdo->prepare("
                        INSERT INTO teachers (user_id, qualification, specialization) 
                        VALUES (?, 'À définir', 'À définir')
                    ");
                    $stmt->execute([$user_id]);
                    break;
                    
                case 'parent':
                    $stmt = $pdo->prepare("INSERT INTO parents (user_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                    break;
            }
            
            $pdo->commit();
            
            // Envoyer un email de vérification (simulé)
            // sendVerificationEmail($email, $verification_token);
            
            $success = true;
            $success_message = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erreur lors de la création du compte : " . $e->getMessage();
        }
    }
}

// Définir le type d'utilisateur par défaut depuis l'URL
$user_type = $type ?: ($_POST['user_type'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .auth-container {
            max-width: 500px;
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
        
        .user-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .user-type-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .user-type-btn:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }
        
        .user-type-btn.active {
            border-color: #3498db;
            background: #3498db;
            color: white;
        }
        
        .user-type-btn i {
            display: block;
            font-size: 2rem;
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
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
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
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
        }
        
        .auth-links a:hover {
            text-decoration: underline;
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
            <h1><i class="fas fa-user-plus"></i> Créer un compte</h1>
            <p>Rejoignez notre communauté éducative</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p><?php echo $success_message; ?></p>
                <p style="margin-top: 10px;">
                    <a href="login.php" class="btn-primary" style="display: inline-block; padding: 10px 20px;">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </a>
                </p>
            </div>
        <?php else: ?>
        
        <!-- Sélecteur de type d'utilisateur -->
        <div class="user-type-selector">
            <button type="button" class="user-type-btn <?php echo $user_type == 'school' ? 'active' : ''; ?>" 
                    onclick="selectUserType('school')">
                <i class="fas fa-school"></i>
                <span>École</span>
            </button>
            <button type="button" class="user-type-btn <?php echo $user_type == 'teacher' ? 'active' : ''; ?>" 
                    onclick="selectUserType('teacher')">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Enseignant</span>
            </button>
            <button type="button" class="user-type-btn <?php echo $user_type == 'parent' ? 'active' : ''; ?>" 
                    onclick="selectUserType('parent')">
                <i class="fas fa-user-friends"></i>
                <span>Parent</span>
            </button>
        </div>
        
        <form method="POST" id="registerForm">
            <input type="hidden" name="user_type" id="user_type" value="<?php echo $user_type; ?>" required>
            
            <div class="form-group">
                <label for="full_name">Nom complet *</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                       required placeholder="Ex: Lycée Excellence">
            </div>
            
            <div class="form-group">
                <label for="email">Adresse email *</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                       required placeholder="exemple@email.com">
            </div>
            
            <div class="form-group">
                <label for="phone">Numéro de téléphone *</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                       required placeholder="Ex: +221 77 123 4567">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe *</label>
                <input type="password" id="password" name="password" required>
                <small style="color: #666; display: block; margin-top: 5px;">Minimum 6 caractères</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn-primary btn-auth">
                <i class="fas fa-user-plus"></i> Créer mon compte
            </button>
        </form>
        
        <div class="auth-links">
            <p>Vous avez déjà un compte ? <a href="login.php">Connectez-vous</a></p>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
        function selectUserType(type) {
            document.getElementById('user_type').value = type;
            
            // Mettre à jour l'interface
            document.querySelectorAll('.user-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Mettre à jour le placeholder du nom
            const nameInput = document.getElementById('full_name');
            switch(type) {
                case 'school':
                    nameInput.placeholder = 'Ex: Lycée Excellence';
                    break;
                case 'teacher':
                    nameInput.placeholder = 'Ex: Marie Diop';
                    break;
                case 'parent':
                    nameInput.placeholder = 'Ex: Papa Ndiaye';
                    break;
            }
        }
        
        // Sélectionner automatiquement le type depuis l'URL
        document.addEventListener('DOMContentLoaded', function() {
            const type = '<?php echo $type; ?>';
            if (type) {
                selectUserType(type);
            }
        });
    </script>
</body>
</html>