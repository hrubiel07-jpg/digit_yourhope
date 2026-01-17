<?php
require_once __DIR__ . '/../../includes/config.php';
requireUserType('school');

$user_id = $_SESSION['user_id'];

// Récupérer l'école
$stmt = $pdo->prepare("SELECT s.* FROM schools s JOIN users u ON s.user_id = u.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

// Récupérer les paramètres
$recipient_type = $_POST['recipient_type'] ?? 'all_parents';
$class_id = $_POST['class_id'] ?? 0;
$parent_ids = isset($_POST['parent_ids']) ? explode(',', $_POST['parent_ids']) : [];

$recipients = [];

switch ($recipient_type) {
    case 'all_parents':
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.parent_phone as phone, 
                   s.parent_email as email,
                   CONCAT(s.parent_name, ' (', s.first_name, ' ', s.last_name, ')') as name
            FROM students s
            WHERE s.school_id = ? AND s.parent_phone IS NOT NULL AND s.parent_phone != ''
            ORDER BY s.parent_name
        ");
        $stmt->execute([$school_id]);
        $recipients = $stmt->fetchAll();
        break;
        
    case 'all_teachers':
        $stmt = $pdo->prepare("
            SELECT u.phone, u.email, u.full_name as name
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            WHERE t.school_id = ? AND u.phone IS NOT NULL AND u.phone != ''
            ORDER BY u.full_name
        ");
        $stmt->execute([$school_id]);
        $recipients = $stmt->fetchAll();
        break;
        
    case 'specific_class':
        if ($class_id) {
            $stmt = $pdo->prepare("
                SELECT s.parent_phone as phone, 
                       s.parent_email as email,
                       CONCAT(s.parent_name, ' (', s.first_name, ' ', s.last_name, ')') as name
                FROM students s
                WHERE s.school_id = ? AND s.current_class_id = ? 
                AND s.parent_phone IS NOT NULL AND s.parent_phone != ''
                ORDER BY s.parent_name
            ");
            $stmt->execute([$school_id, $class_id]);
            $recipients = $stmt->fetchAll();
        }
        break;
        
    case 'specific_parents':
        if (!empty($parent_ids)) {
            $placeholders = str_repeat('?,', count($parent_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT s.parent_phone as phone, 
                       s.parent_email as email,
                       CONCAT(s.parent_name, ' (', s.first_name, ' ', s.last_name, ')') as name
                FROM students s
                WHERE s.id IN ($placeholders)
                AND s.parent_phone IS NOT NULL AND s.parent_phone != ''
                ORDER BY s.parent_name
            ");
            $stmt->execute($parent_ids);
            $recipients = $stmt->fetchAll();
        }
        break;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'count' => count($recipients),
    'recipients' => $recipients
]);