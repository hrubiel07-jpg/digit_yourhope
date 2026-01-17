=== C:\xampp\htdocs\digital_yourhope\includes\school_functions.php ===
<?php
/**
 * Fonctions spécifiques aux écoles
 */

function getSchoolStatistics($school_id) {
    global $pdo;
    
    $stats = [];
    
    // Total élèves actifs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'active'");
    $stmt->execute([$school_id]);
    $stats['active_students'] = $stmt->fetchColumn();
    
    // Total enseignants (acceptés dans des offres)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ta.teacher_id) 
        FROM teacher_applications ta 
        JOIN school_jobs sj ON ta.job_id = sj.id 
        WHERE sj.school_id = ? AND ta.status = 'accepted'
    ");
    $stmt->execute([$school_id]);
    $stats['total_teachers'] = $stmt->fetchColumn();
    
    // Total classes actives
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ? AND is_active = 1");
    $stmt->execute([$school_id]);
    $stats['total_classes'] = $stmt->fetchColumn();
    
    // Total payé
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(paid_amount), 0) 
        FROM enrollments 
        WHERE class_id IN (SELECT id FROM classes WHERE school_id = ?) 
        AND payment_status = 'paid'
    ");
    $stmt->execute([$school_id]);
    $stats['total_paid'] = $stmt->fetchColumn();
    
    // Total en attente
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(balance), 0) 
        FROM enrollments 
        WHERE class_id IN (SELECT id FROM classes WHERE school_id = ?) 
        AND payment_status = 'pending'
    ");
    $stmt->execute([$school_id]);
    $stats['total_pending'] = $stmt->fetchColumn();
    
    return $stats;
}

function getPendingPayments($school_id, $limit = 5) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            s.id as student_id,
            s.first_name, 
            s.last_name, 
            s.matricule,
            c.class_name,
            e.tuition_fee,
            e.paid_amount,
            e.balance,
            e.payment_status,
            DATE_ADD(e.enrollment_date, INTERVAL 30 DAY) as due_date
        FROM enrollments e
        JOIN students s ON e.student_id = s.id 
        LEFT JOIN classes c ON s.current_class_id = c.id
        WHERE s.school_id = ? 
        AND e.payment_status = 'pending'
        ORDER BY due_date ASC 
        LIMIT ?
    ");
    $stmt->execute([$school_id, $limit]);
    return $stmt->fetchAll();
}
?>