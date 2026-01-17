<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Récupérer les données
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email invalide']);
    exit();
}

// Récupérer le nom de l'école
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT s.school_name FROM schools s WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();

$subject = "Test d'email - " . $school['school_name'];
$message = "Bonjour,\n\nCeci est un email de test envoyé depuis le système de gestion scolaire.\n\nDate: " . date('d/m/Y H:i') . "\n\nCordialement,\nL'équipe de " . $school['school_name'];

$headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
$headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (mail($email, $subject, $message, $headers)) {
    echo json_encode(['success' => true, 'message' => 'Email de test envoyé avec succès']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email']);
}
?>