<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'students';

// Récupérer l'ID de l'école
$stmt = $pdo->prepare("SELECT id FROM schools WHERE user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

// Générer le fichier CSV selon le type
$filename = "export_$type_" . date('Y-m-d_H-i-s') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

switch ($type) {
    case 'students':
        // Exporter les élèves
        $stmt = $pdo->prepare("
            SELECT s.*, c.class_name 
            FROM students s 
            LEFT JOIN classes c ON s.current_class_id = c.id 
            WHERE s.school_id = ? 
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$school_id]);
        $students = $stmt->fetchAll();
        
        // En-têtes CSV
        fputcsv($output, [
            'Matricule', 'Nom', 'Prénom', 'Sexe', 'Date de naissance', 
            'Lieu de naissance', 'Classe', 'Statut', 'Date d\'inscription'
        ]);
        
        // Données
        foreach ($students as $student) {
            fputcsv($output, [
                $student['matricule'],
                $student['last_name'],
                $student['first_name'],
                $student['gender'],
                $student['birth_date'],
                $student['birth_place'],
                $student['class_name'],
                $student['status'],
                $student['enrollment_date']
            ]);
        }
        break;
        
    case 'teachers':
        // Exporter les enseignants
        $stmt = $pdo->prepare("
            SELECT t.*, u.full_name, u.email, u.phone 
            FROM teachers t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.school_id = ? 
            ORDER BY u.full_name
        ");
        $stmt->execute([$school_id]);
        $teachers = $stmt->fetchAll();
        
        fputcsv($output, [
            'Nom complet', 'Qualification', 'Spécialisation', 
            'Années d\'expérience', 'Taux horaire', 'Email', 'Téléphone'
        ]);
        
        foreach ($teachers as $teacher) {
            fputcsv($output, [
                $teacher['full_name'],
                $teacher['qualification'],
                $teacher['specialization'],
                $teacher['experience_years'],
                $teacher['hourly_rate'],
                $teacher['email'],
                $teacher['phone']
            ]);
        }
        break;
        
    case 'payments':
        // Exporter les paiements
        $stmt = $pdo->prepare("
            SELECT p.*, s.first_name, s.last_name, s.matricule, f.fee_name 
            FROM payments p 
            JOIN students s ON p.student_id = s.id 
            JOIN school_fees f ON p.fee_id = f.id 
            WHERE s.school_id = ? 
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([$school_id]);
        $payments = $stmt->fetchAll();
        
        fputcsv($output, [
            'Élève', 'Matricule', 'Frais', 'Montant', 'Méthode', 
            'Date paiement', 'Statut', 'N° reçu'
        ]);
        
        foreach ($payments as $payment) {
            fputcsv($output, [
                $payment['first_name'] . ' ' . $payment['last_name'],
                $payment['matricule'],
                $payment['fee_name'],
                $payment['amount'],
                $payment['payment_method'],
                $payment['payment_date'],
                $payment['payment_status'],
                $payment['receipt_number']
            ]);
        }
        break;
        
    default:
        // Exporter tout
        fputcsv($output, ['Type d\'export non supporté']);
        break;
}

fclose($output);
exit();
?>