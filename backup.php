<?php
/**
 * Script de sauvegarde automatique pour Digital YOURHOPE
 */

require_once '../includes/config.php';

// Vérifier l'accès (seul l'admin ou via cron)
$is_cron = php_sapi_name() === 'cli';
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';

if (!$is_cron && !$is_admin) {
    die('Accès non autorisé');
}

// Configuration des sauvegardes
$backup_dir = __DIR__ . '/../backups/';
$max_backups = 30; // Garder les 30 dernières sauvegardes
$backup_prefix = 'backup_' . date('Y-m-d_H-i-s');

// Créer le dossier de sauvegarde s'il n'existe pas
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Fonction de sauvegarde de la base de données
function backupDatabase($backup_dir, $backup_prefix) {
    global $pdo;
    
    $backup_file = $backup_dir . $backup_prefix . '_database.sql.gz';
    
    // Récupérer toutes les tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $sql = "-- Backup de la base de données Digital YOURHOPE\n";
    $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Version: 1.0\n\n";
    
    foreach ($tables as $table) {
        // Structure de la table
        $sql .= "\n--\n-- Structure de la table `$table`\n--\n\n";
        $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= $create_table['Create Table'] . ";\n\n";
        
        // Données de la table
        $sql .= "--\n-- Données de la table `$table`\n--\n\n";
        
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $row_values = array_map(function($value) use ($pdo) {
                    if ($value === null) return 'NULL';
                    return $pdo->quote($value);
                }, array_values($row));
                
                $values[] = "(" . implode(', ', $row_values) . ")";
            }
            
            $sql .= implode(",\n", $values) . ";\n";
        }
    }
    
    // Compresser et sauvegarder
    file_put_contents($backup_file, gzencode($sql, 9));
    
    return $backup_file;
}

// Fonction de sauvegarde des fichiers
function backupFiles($backup_dir, $backup_prefix) {
    $files_to_backup = [
        '../includes/',
        '../dashboard/',
        '../auth/',
        '../platform/',
        '../assets/',
        '../uploads/',
        '../config/'
    ];
    
    $backup_file = $backup_dir . $backup_prefix . '_files.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($backup_file, ZipArchive::CREATE) !== TRUE) {
        throw new Exception("Impossible de créer le fichier ZIP");
    }
    
    foreach ($files_to_backup as $directory) {
        if (!file_exists($directory)) continue;
        
        $directory = realpath($directory);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) continue;
            
            $file_path = $file->getRealPath();
            $relative_path = substr($file_path, strlen(realpath(__DIR__ . '/..')) + 1);
            
            $zip->addFile($file_path, $relative_path);
        }
    }
    
    $zip->close();
    
    return $backup_file;
}

// Fonction de nettoyage des anciennes sauvegardes
function cleanupOldBackups($backup_dir, $max_backups) {
    $backups = glob($backup_dir . 'backup_*');
    
    // Trier par date (le plus récent en premier)
    usort($backups, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Supprimer les sauvegardes excédentaires
    $to_delete = array_slice($backups, $max_backups);
    
    foreach ($to_delete as $backup) {
        unlink($backup);
    }
    
    return count($to_delete);
}

try {
    // Démarrer la sauvegarde
    echo "Démarrage de la sauvegarde...\n";
    
    // Sauvegarder la base de données
    echo "Sauvegarde de la base de données...\n";
    $db_file = backupDatabase($backup_dir, $backup_prefix);
    $db_size = filesize($db_file);
    echo "✓ Base de données sauvegardée: " . round($db_size / 1024 / 1024, 2) . " MB\n";
    
    // Sauvegarder les fichiers
    echo "Sauvegarde des fichiers...\n";
    $files_file = backupFiles($backup_dir, $backup_prefix);
    $files_size = filesize($files_file);
    echo "✓ Fichiers sauvegardés: " . round($files_size / 1024 / 1024, 2) . " MB\n";
    
    // Nettoyer les anciennes sauvegardes
    echo "Nettoyage des anciennes sauvegardes...\n";
    $deleted_count = cleanupOldBackups($backup_dir, $max_backups);
    echo "✓ $deleted_count ancienne(s) sauvegarde(s) supprimée(s)\n";
    
    // Enregistrer le log
    $log = date('Y-m-d H:i:s') . " - Sauvegarde complétée\n";
    $log .= "  Base de données: " . basename($db_file) . " (" . round($db_size / 1024 / 1024, 2) . " MB)\n";
    $log .= "  Fichiers: " . basename($files_file) . " (" . round($files_size / 1024 / 1024, 2) . " MB)\n";
    
    file_put_contents($backup_dir . 'backup.log', $log . "\n", FILE_APPEND);
    
    // Envoyer une notification (optionnel)
    if (defined('ADMIN_EMAIL') && !$is_cron) {
        $subject = "Sauvegarde Digital YOURHOPE - " . date('d/m/Y');
        $message = "La sauvegarde a été effectuée avec succès.\n\n" . $log;
        sendEmail(ADMIN_EMAIL, $subject, $message);
    }
    
    echo "\n✅ Sauvegarde terminée avec succès!\n";
    
    // Si appelé depuis le navigateur, afficher un message
    if (!$is_cron) {
        echo "<script>alert('Sauvegarde terminée avec succès!'); window.location.href = '../admin/';</script>";
    }
    
} catch (Exception $e) {
    $error = date('Y-m-d H:i:s') . " - ERREUR: " . $e->getMessage() . "\n";
    file_put_contents($backup_dir . 'backup.log', $error . "\n", FILE_APPEND);
    
    if (defined('ADMIN_EMAIL') && !$is_cron) {
        $subject = "ERREUR Sauvegarde Digital YOURHOPE - " . date('d/m/Y');
        $message = "Une erreur est survenue lors de la sauvegarde:\n\n" . $e->getMessage();
        sendEmail(ADMIN_EMAIL, $subject, $message);
    }
    
    echo "\n❌ Erreur lors de la sauvegarde: " . $e->getMessage() . "\n";
    
    if (!$is_cron) {
        echo "<script>alert('Erreur lors de la sauvegarde: " . addslashes($e->getMessage()) . "');</script>";
    }
}