<?php
/**
 * Fonctions utilitaires pour Digital YOURHOPE
 */

// Générer un token aléatoire
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Envoyer un email
function sendEmail($to, $subject, $message) {
    $headers = "From: noreply@digitalyourhope.com\r\n";
    $headers .= "Reply-To: contact@digitalyourhope.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Formater une date
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Calculer l'âge à partir d'une date de naissance
function calculateAge($birthdate) {
    $birth = new DateTime($birthdate);
    $now = new DateTime();
    $age = $now->diff($birth);
    return $age->y;
}

// Valider un numéro de téléphone
function validatePhone($phone) {
    return preg_match('/^(\+221|221)?[0-9]{9}$/', $phone);
}

// Uploader un fichier
function uploadFile($file, $uploadDir = '../uploads/') {
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $filename = uniqid() . '_' . basename($file['name']);
    $targetFile = $uploadDir . $filename;
    
    // Vérifier le type de fichier
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé'];
    }
    
    // Vérifier la taille (max 5MB)
    if ($file['size'] > 5000000) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Erreur lors de l\'upload'];
}

// Générer un mot de passe aléatoire
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Obtenir l'URL de base
function baseUrl($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    
    return $protocol . '://' . $host . $base . $path;
}

// Redirection sécurisée
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

// Vérifier les permissions
function hasPermission($userType, $requiredType) {
    if (is_array($requiredType)) {
        return in_array($userType, $requiredType);
    }
    return $userType === $requiredType;
}

// Journaliser les actions
function logAction($userId, $action, $details = '') {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR']]);
}
?>