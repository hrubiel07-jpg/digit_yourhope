<?php
// Définir la racine du projet
define('ROOT_DIR', dirname(__DIR__));

// Puis dans tous les fichiers, utilisez :
require_once ROOT_DIR . '/includes/config.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'digital_yourhope');

// Configuration du site
define('SITE_NAME', 'Digital YOURHOPE');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/digital_yourhope/');
define('ADMIN_EMAIL', 'contact@digitalyourhope.com');

// Configuration des chemins
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('ASSETS_URL', SITE_URL . 'assets/');

// Connexion à la base de données avec UTF-8
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}

// Fonction pour vérifier l'authentification
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
}

function requireUserType($allowed_types) {
    requireLogin();
    
    if (!isset($_SESSION['user_type'])) {
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
    
    if (is_array($allowed_types)) {
        if (!in_array($_SESSION['user_type'], $allowed_types)) {
            header('Location: ' . SITE_URL . 'dashboard/' . $_SESSION['user_type'] . '/index.php');
            exit();
        }
    } else {
        if ($_SESSION['user_type'] !== $allowed_types) {
            header('Location: ' . SITE_URL . 'dashboard/' . $_SESSION['user_type'] . '/index.php');
            exit();
        }
    }
}

// Fonction pour obtenir les infos utilisateur
function getUserInfo($user_id = null) {
    global $pdo;
    
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    if (!$user_id) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Protection contre les injections XSS
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Fonction pour logger les erreurs
function logError($error) {
    $log_file = dirname(__DIR__) . '/logs/errors.log';
    
    // Créer le dossier logs s'il n'existe pas
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0777, true);
    }
    
    $message = date('Y-m-d H:i:s') . " - " . $error . PHP_EOL;
    file_put_contents($log_file, $message, FILE_APPEND);
}

// Fonction pour générer un token CSRF
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier le token CSRF
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fonction pour valider un email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Fonction pour valider un téléphone
function validatePhone($phone) {
    return preg_match('/^(\+221|221)?[0-9]{9}$/', $phone);
}

// Fonction pour rediriger avec un message
function redirectWithMessage($url, $type, $message) {
    $_SESSION[$type . '_message'] = $message;
    header('Location: ' . $url);
    exit();
}

// Fonction pour formater une date
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date == '0000-00-00') {
        return '';
    }
    return date($format, strtotime($date));
}

// Fonction pour obtenir l'URL actuelle
function currentUrl() {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// Gestion des erreurs
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logError("Erreur [$errno] $errstr dans $errfile à la ligne $errline");
    
    // En production, ne pas afficher les erreurs détaillées
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
        return true; // Ne pas exécuter le gestionnaire d'erreurs PHP interne
    }
    
    return false; // Exécuter le gestionnaire d'erreurs PHP interne
});

// Gestion des exceptions
set_exception_handler(function($exception) {
    logError("Exception: " . $exception->getMessage() . " dans " . $exception->getFile() . ":" . $exception->getLine());
    
    // En production, afficher un message générique
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
        http_response_code(500);
        die("Une erreur s'est produite. Veuillez réessayer plus tard.");
    }
    
    // En développement, afficher l'erreur détaillée
    die("Exception: " . $exception->getMessage() . " dans " . $exception->getFile() . ":" . $exception->getLine());
});

// Désactiver l'affichage des erreurs en production
if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Mettre en mémoire tampon la sortie
ob_start();
?>