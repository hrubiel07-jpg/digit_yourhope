<?php
/**
 * Configuration spécifique aux écoles
 */

function getSchoolConfig($school_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM school_configurations WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $config = $stmt->fetch();
        
        if (!$config) {
            // Créer une configuration par défaut
            return getDefaultSchoolConfig();
        }
        
        // Assurer que toutes les clés existent
        $defaults = getDefaultSchoolConfig();
        foreach ($defaults as $key => $value) {
            if (!isset($config[$key])) {
                $config[$key] = $value;
            }
        }
        
        return $config;
    } catch (Exception $e) {
        error_log("Erreur dans getSchoolConfig: " . $e->getMessage());
        return getDefaultSchoolConfig();
    }
}

function getDefaultSchoolConfig() {
    return [
        'primary_color' => '#3498db',
        'secondary_color' => '#2ecc71',
        'accent_color' => '#e74c3c',
        'text_color' => '#2c3e50',
        'background_color' => '#f8f9fa',
        'currency' => 'FCFA',
        'currency_symbol' => 'FCFA',
        'tax_rate' => 0.00,
        'education_system' => 'Congolais',
        'grading_system' => 'Sur 20 points',
        'exam_system' => 'Trimestriel',
        'academic_calendar' => '[]'
    ];
}

function applySchoolTheme($school_id) {
    $config = getSchoolConfig($school_id);
    
    $css = "
    <style>
        :root {
            --primary-color: {$config['primary_color']};
            --secondary-color: {$config['secondary_color']};
            --accent-color: {$config['accent_color']};
        }
        
        .btn-primary {
            background-color: {$config['primary_color']};
            border-color: {$config['primary_color']};
        }
        
        .btn-primary:hover {
            background-color: " . darkenColor($config['primary_color'], 10) . ";
            border-color: " . darkenColor($config['primary_color'], 10) . ";
        }
        
        .btn-secondary {
            background-color: {$config['secondary_color']};
            border-color: {$config['secondary_color']};
        }
        
        .btn-secondary:hover {
            background-color: " . darkenColor($config['secondary_color'], 10) . ";
            border-color: " . darkenColor($config['secondary_color'], 10) . ";
        }
        
        .stat-card-extended .stat-icon-extended {
            background: {$config['primary_color']};
        }
        
        .school-header {
            background: linear-gradient(135deg, {$config['primary_color']}, {$config['secondary_color']});
        }
    </style>
    ";
    
    return $css;
}

function darkenColor($color, $percent) {
    $color = str_replace('#', '', $color);
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    $r = max(0, min(255, $r - ($r * $percent / 100)));
    $g = max(0, min(255, $g - ($g * $percent / 100)));
    $b = max(0, min(255, $b - ($b * $percent / 100)));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

function getSchoolStatistics($school_id) {
    global $pdo;
    
    $stats = getDefaultSchoolConfig();
    
    try {
        // Total élèves
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $stats['total_students'] = $stmt->fetchColumn();
        
        // Élèves actifs
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'active'");
        $stmt->execute([$school_id]);
        $stats['active_students'] = $stmt->fetchColumn();
        
        // Total enseignants
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT t.id) 
            FROM teachers t 
            JOIN teacher_applications ta ON t.id = ta.teacher_id 
            JOIN school_jobs sj ON ta.job_id = sj.id 
            WHERE sj.school_id = ? AND ta.status = 'accepted'
        ");
        $stmt->execute([$school_id]);
        $stats['total_teachers'] = $stmt->fetchColumn();
        
        // Total classes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ? AND is_active = 1");
        $stmt->execute([$school_id]);
        $stats['total_classes'] = $stmt->fetchColumn();
        
        // Total payé
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(paid_amount), 0) 
            FROM enrollments 
            WHERE class_id IN (SELECT id FROM classes WHERE school_id = ?)
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
        
    } catch (Exception $e) {
        error_log("Erreur dans getSchoolStatistics: " . $e->getMessage());
    }
    
    return $stats;
}
?>