<?php
/**
 * Configuration SMTP pour Digital YOURHOPE
 */

// Configuration SMTP Gmail (exemple)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'votre-email@gmail.com');
define('SMTP_PASS', 'votre-mot-de-passe');
define('SMTP_SECURE', 'tls');

// Configuration SMTP alternative (OVH, Orange, etc.)
/*
define('SMTP_HOST', 'ssl0.ovh.net');
define('SMTP_PORT', 465);
define('SMTP_USER', 'contact@digitalyourhope.com');
define('SMTP_PASS', 'votre-mot-de-passe');
define('SMTP_SECURE', 'ssl');
*/

// En-têtes par défaut
define('EMAIL_FROM', 'noreply@digitalyourhope.com');
define('EMAIL_FROM_NAME', SITE_NAME);
define('EMAIL_REPLY_TO', 'contact@digitalyourhope.com');

// Limites d'envoi
define('EMAIL_RATE_LIMIT', 100); // Nombre maximum d'emails par heure
define('EMAIL_BATCH_SIZE', 50); // Nombre d'emails par lot

// Configuration des templates
define('EMAIL_TEMPLATE_PATH', __DIR__ . '/../templates/emails/');
define('EMAIL_LOGO_URL', SITE_URL . 'assets/images/logo.png');

// Configuration des notifications
define('NOTIFY_ADMIN_ON_ERROR', true);
define('ADMIN_EMAIL', 'admin@digitalyourhope.com');

// Journalisation
define('EMAIL_LOG_PATH', __DIR__ . '/../logs/email.log');
define('LOG_EMAILS', true);

// Configuration des retry
define('EMAIL_MAX_RETRIES', 3);
define('EMAIL_RETRY_DELAY', 60); // secondes

/**
 * Vérifier la configuration SMTP
 */
function checkSMTPConfig() {
    if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
        throw new Exception('Configuration SMTP incomplète');
    }
    
    // Tester la connexion SMTP
    $test_connection = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
    
    if (!$test_connection) {
        error_log("Impossible de se connecter à " . SMTP_HOST . ":" . SMTP_PORT . " - $errstr ($errno)");
        return false;
    }
    
    fclose($test_connection);
    return true;
}

/**
 * Enregistrer une entrée dans le journal des emails
 */
function logEmail($type, $recipient, $subject, $status, $error = null) {
    if (!LOG_EMAILS) return;
    
    $log_file = EMAIL_LOG_PATH;
    
    // Créer le dossier logs s'il n'existe pas
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type | $recipient | $subject | $status";
    
    if ($error) {
        $log_entry .= " | ERROR: $error";
    }
    
    $log_entry .= PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Obtenir les statistiques d'envoi d'emails
 */
function getEmailStats($period = 'today') {
    global $pdo;
    
    $query = "SELECT COUNT(*) as total, 
                     COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                     COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
              FROM broadcast_messages 
              WHERE message_type = 'email'";
    
    switch ($period) {
        case 'today':
            $query .= " AND DATE(sent_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    return $stmt->fetch();
}

/**
 * Nettoyer les anciens logs d'emails
 */
function cleanupEmailLogs($days = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        DELETE FROM broadcast_messages 
        WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$days]);
    
    return $stmt->rowCount();
}