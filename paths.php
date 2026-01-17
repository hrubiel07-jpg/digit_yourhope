<?php
/**
 * Configuration des chemins pour Digital YOURHOPE
 */

// Définir les constantes de chemins
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('DASHBOARD_PATH', ROOT_PATH . '/dashboard');
define('PLATFORM_PATH', ROOT_PATH . '/platform');
define('AUTH_PATH', ROOT_PATH . '/auth');
define('PAYMENT_PATH', ROOT_PATH . '/payment');
define('MESSAGING_PATH', ROOT_PATH . '/messaging');

// Fonction pour inclure facilement les fichiers
function requireConfig() {
    require_once INCLUDES_PATH . '/config.php';
}

function requireFunctions() {
    require_once INCLUDES_PATH . '/functions.php';
}

function requireSchoolConfig() {
    require_once INCLUDES_PATH . '/school_config.php';
}

// Définir les chemins pour chaque type d'utilisateur
function getDashboardPath($user_type) {
    return DASHBOARD_PATH . '/' . $user_type;
}

// Chemins absolus pour les URLs
function asset($path) {
    return SITE_URL . 'assets/' . ltrim($path, '/');
}

function upload($path) {
    return SITE_URL . 'uploads/' . ltrim($path, '/');
}

function url($path) {
    return SITE_URL . ltrim($path, '/');
}