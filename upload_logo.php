<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['logo'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Requête invalide']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer l'ID de l'école
$stmt = $pdo->prepare("SELECT id FROM schools WHERE user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();

if (!$school) {
    echo json_encode(['success' => false, 'error' => 'École non trouvée']);
    exit();
}

$school_id = $school['id'];

// Vérifier le fichier
$logo = $_FILES['logo'];
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$max_size = 2 * 1024 * 1024; // 2MB

if (!in_array($logo['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé']);
    exit();
}

if ($logo['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 2MB)']);
    exit();
}

// Créer le dossier uploads/schools s'il n'existe pas
$upload_dir = dirname(__DIR__, 3) . '/uploads/schools/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Générer un nom de fichier unique
$file_ext = pathinfo($logo['name'], PATHINFO_EXTENSION);
$filename = 'school_' . $school_id . '_' . time() . '.' . $file_ext;
$filepath = $upload_dir . $filename;

// Déplacer le fichier
if (move_uploaded_file($logo['tmp_name'], $filepath)) {
    // Mettre à jour la base de données
    try {
        // Supprimer l'ancien logo s'il existe
        $stmt = $pdo->prepare("SELECT logo FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $old_logo = $stmt->fetchColumn();
        
        if ($old_logo && file_exists($upload_dir . $old_logo)) {
            unlink($upload_dir . $old_logo);
        }
        
        // Mettre à jour avec le nouveau logo
        $stmt = $pdo->prepare("UPDATE schools SET logo = ? WHERE id = ?");
        $stmt->execute(['schools/' . $filename, $school_id]);
        
        // Mettre à jour le chemin dans les configurations
        $stmt = $pdo->prepare("UPDATE school_configurations SET school_logo = ? WHERE school_id = ?");
        $stmt->execute(['schools/' . $filename, $school_id]);
        
        echo json_encode(['success' => true, 'filename' => $filename]);
    } catch (Exception $e) {
        // Supprimer le fichier en cas d'erreur
        unlink($filepath);
        echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors du téléchargement']);
}
?>