=== C:\xampp\htdocs\digital_yourhope\fix_paths.php ===
<?php
/**
 * Script pour corriger les chemins incorrects dans tous les fichiers
 */

$base_dir = __DIR__;

// Liste des fichiers à corriger
$files_to_fix = [
    'dashboard/school/classes.php',
    'dashboard/school/schedules.php',
    'dashboard/school/payments.php',
    'dashboard/school/reports.php',
    'dashboard/school/configurations.php',
    'dashboard/school/profile.php',
    'dashboard/school/jobs.php',
    'dashboard/school/applications.php',
    'dashboard/teacher/profile.php',
    'dashboard/teacher/services.php',
    'dashboard/teacher/applications.php',
    'dashboard/teacher/appointments.php',
    'dashboard/teacher/reviews.php',
    'dashboard/parent/profile.php',
    'dashboard/parent/children.php',
    'dashboard/parent/appointments.php',
    'dashboard/parent/favorites.php',
    'dashboard/parent/reviews.php'
];

foreach ($files_to_fix as $file) {
    $file_path = $base_dir . '/' . $file;
    
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        
        // Corriger les chemins incorrects
        $content = str_replace(
            "require_once '../../../includes/config.php';",
            "require_once __DIR__ . '/../../includes/config.php';",
            $content
        );
        
        $content = str_replace(
            'require_once "../../../includes/config.php";',
            'require_once __DIR__ . "/../../includes/config.php";',
            $content
        );
        
        $content = str_replace(
            "require_once '../../includes/config.php';",
            "require_once __DIR__ . '/../../includes/config.php';",
            $content
        );
        
        // Sauvegarder
        file_put_contents($file_path, $content);
        echo "Corrigé : $file\n";
    } else {
        echo "Fichier non trouvé : $file\n";
    }
}

echo "\nCorrection terminée !\n";
?>