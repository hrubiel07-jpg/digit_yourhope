<?php
require_once __DIR__ . '/../../includes/config.php';
requireUserType('school');

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'] ?? 0;

// Récupérer les templates de la base de données
$stmt = $pdo->prepare("
    SELECT * FROM whatsapp_templates 
    WHERE school_id = ? OR school_id = 0
    ORDER BY template_name
");
$stmt->execute([$school_id]);
$templates = $stmt->fetchAll();

// Templates par défaut
$default_templates = [
    [
        'id' => 'bulletin',
        'name' => 'Bulletin scolaire disponible',
        'message' => 'Cher parent, le bulletin scolaire de {student_name} pour {term} {academic_year} est disponible. Moyenne: {average}/20, Rang: {rank}/{total}. Connectez-vous sur votre espace parent pour le consulter.'
    ],
    [
        'id' => 'payment_reminder',
        'name' => 'Rappel de paiement',
        'message' => 'Cher parent, le paiement des frais de {fee_name} d\'un montant de {amount} XOF est attendu avant le {due_date}. Veuillez effectuer le paiement dès que possible.'
    ],
    [
        'id' => 'meeting',
        'name' => 'Réunion parents-professeurs',
        'message' => 'Cher parent, une réunion parents-professeurs est prévue le {date} à {time} à {location}. Votre présence est importante.'
    ],
    [
        'id' => 'exam',
        'name' => 'Information examen',
        'message' => 'Cher parent, l\'examen {exam_type} aura lieu le {exam_date} au centre {center_name}. Veillez à ce que votre enfant soit présent à {report_time}.'
    ]
];

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'templates' => array_merge($default_templates, $templates)
]);