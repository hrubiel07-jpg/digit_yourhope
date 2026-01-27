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
    if (empty($date) || $date == '0000-00-00') {
        return '';
    }
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

// Uploader un fichier - FONCTION MANQUANTE
function uploadFile($file, $uploadDir = '../uploads/') {
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Vérifier si le fichier a été uploadé
    if (!isset($file['name']) || empty($file['name'])) {
        return ['success' => false, 'error' => 'Aucun fichier sélectionné'];
    }
    
    // Générer un nom de fichier unique
    $filename = uniqid() . '_' . basename($file['name']);
    $targetFile = $uploadDir . $filename;
    
    // Vérifier le type de fichier
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']) ?? $file['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé. Seuls JPG, PNG et GIF sont acceptés.'];
    }
    
    // Vérifier la taille (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)'];
    }
    
    // Vérifier les dimensions pour les images
    if (strpos($fileType, 'image/') === 0) {
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            return ['success' => false, 'error' => 'Fichier image invalide'];
        }
        
        // Dimensions maximales recommandées
        $maxWidth = 2000;
        $maxHeight = 2000;
        
        if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
            return ['success' => false, 'error' => 'Image trop grande (max 2000x2000 pixels)'];
        }
    }
    
    // Déplacer le fichier uploadé
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        // Changer les permissions
        chmod($targetFile, 0644);
        
        return [
            'success' => true, 
            'filename' => $filename,
            'full_path' => $targetFile,
            'size' => $file['size'],
            'type' => $fileType
        ];
    }
    
    return ['success' => false, 'error' => 'Erreur lors de l\'upload du fichier'];
}

// Fonction pour uploader un logo d'école
function uploadSchoolLogo($file, $school_id) {
    $uploadDir = '../uploads/schools/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Ajouter l'ID de l'école au nom du fichier
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'school_' . $school_id . '_' . time() . '.' . $extension;
    $targetFile = $uploadDir . $filename;
    
    // Utiliser la fonction uploadFile avec le chemin spécifique
    $result = uploadFile($file, $uploadDir);
    
    // Si upload réussi, renommer avec l'ID de l'école
    if ($result['success']) {
        $oldPath = $uploadDir . $result['filename'];
        $newPath = $targetFile;
        
        if (rename($oldPath, $newPath)) {
            $result['filename'] = $filename;
            $result['full_path'] = $newPath;
        }
    }
    
    return $result;
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
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        error_log("Erreur logAction: " . $e->getMessage());
    }
}

// Fonction pour supprimer un fichier
function deleteFile($filename, $directory = '../uploads/') {
    $filePath = $directory . $filename;
    
    if (file_exists($filePath) && is_file($filePath)) {
        if (unlink($filePath)) {
            return ['success' => true, 'message' => 'Fichier supprimé avec succès'];
        } else {
            return ['success' => false, 'error' => 'Impossible de supprimer le fichier'];
        }
    }
    
    return ['success' => false, 'error' => 'Fichier non trouvé'];
}

// Fonction pour formater un montant
function formatAmount($amount, $currency = 'FCFA') {
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

// Fonction pour valider un email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Fonction pour nettoyer une chaîne
function cleanString($string) {
    $string = trim($string);
    $string = stripslashes($string);
    $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    return $string;
}
?>