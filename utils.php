<?php
/**
 * Fonctions utilitaires pour éviter les erreurs courantes
 */

// Fonction pour vérifier si une clé existe dans un tableau
function safeGet($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

// Fonction pour obtenir une valeur avec vérification
function getValue($value, $default = '') {
    if (is_array($value)) {
        return $value;
    }
    return !empty($value) ? $value : $default;
}

// Fonction pour formater une date en toute sécurité
function safeDateFormat($date, $format = 'd/m/Y', $default = '') {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return $default;
    }
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return $default;
    }
}

// Fonction pour obtenir le logo d'une école
function getSchoolLogo($school_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT logo FROM school_configurations WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $result = $stmt->fetch();
        
        return safeGet($result, 'logo');
    } catch (Exception $e) {
        return null;
    }
}

// Fonction pour obtenir l'image de profil d'un utilisateur
function getUserProfileImage($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        return safeGet($result, 'profile_image');
    } catch (Exception $e) {
        return null;
    }
}

// Fonction pour initialiser les statistiques par défaut
function getDefaultStats() {
    return [
        'total_students' => 0,
        'active_students' => 0,
        'total_teachers' => 0,
        'total_classes' => 0,
        'total_paid' => 0,
        'total_pending' => 0
    ];
}

// Fonction pour vérifier et initialiser la connexion PDO
function ensurePDOConnection() {
    global $pdo;
    
    if (!isset($pdo)) {
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
        }
        
        if (!isset($pdo)) {
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
        }
    }
    
    return $pdo;
}

// Fonction pour exécuter une requête en toute sécurité
function safeQuery($query, $params = []) {
    global $pdo;
    
    ensurePDOConnection();
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (Exception $e) {
        error_log("Erreur de requête SQL: " . $e->getMessage() . " - Query: " . $query);
        return false;
    }
}

// Fonction pour valider et nettoyer les données d'entrée
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}
?>