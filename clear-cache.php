<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vider le cache session
session_unset();
session_regenerate_id(true);

// Réinitialiser certaines variables de session
$_SESSION['last_cache_clear'] = time();

// Enregistrer l'action
logAction($_SESSION['user_id'], 'clear_cache', 'Cache système vidé');

echo json_encode([
    'success' => true,
    'message' => 'Cache vidé avec succès'
]);
?>