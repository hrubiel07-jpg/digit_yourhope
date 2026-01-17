<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
    exit();
}

$school_id = $_GET['school_id'] ?? 0;

// Vérifier que l'école appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT s.id FROM schools s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND u.id = ?");
$stmt->execute([$school_id, $_SESSION['user_id']]);
$school = $stmt->fetch();

if (!$school) {
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
    exit();
}

try {
    // Récupérer le chemin du logo
    $stmt = $pdo->prepare("SELECT logo FROM schools WHERE id = ?");
    $stmt->execute([$school_id]);
    $logo = $stmt->fetchColumn();
    
    // Supprimer le fichier physique
    if ($logo && file_exists(dirname(__DIR__, 3) . '/uploads/' . $logo)) {
        unlink(dirname(__DIR__, 3) . '/uploads/' . $logo);
    }
    
    // Mettre à jour la base de données
    $stmt = $pdo->prepare("UPDATE schools SET logo = NULL WHERE id = ?");
    $stmt->execute([$school_id]);
    
    $stmt = $pdo->prepare("UPDATE school_configurations SET school_logo = NULL WHERE school_id = ?");
    $stmt->execute([$school_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>