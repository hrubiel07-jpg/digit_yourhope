<?php
/**
 * Autoloader pour Digital YOURHOPE
 * À inclure dans tous les fichiers avec: require_once __DIR__ . '/../autoload.php';
 */

// Définir la racine absolue du projet
define('PROJECT_ROOT', dirname(__FILE__));

// Fonction pour charger la configuration
function loadConfig() {
    require_once PROJECT_ROOT . '/includes/config.php';
}

// Charger la configuration
loadConfig();

// Charger les utilitaires
require_once PROJECT_ROOT . '/includes/utils.php';

// La variable $pdo est maintenant disponible globalement depuis config.php

// Pour s'assurer que $pdo est globalement accessible
global $pdo;

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonction pour vérifier l'authentification
function requireAuth($allowed_types = null) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
    
    if ($allowed_types) {
        if (is_array($allowed_types)) {
            if (!in_array($_SESSION['user_type'], $allowed_types)) {
                header('Location: ' . SITE_URL . 'dashboard/' . $_SESSION['user_type'] . '/index.php');
                exit();
            }
        } else {
            if ($_SESSION['user_type'] !== $allowed_types) {
                header('Location: ' . SITE_URL . 'dashboard/' . $_SESSION['user_type'] . '/index.php');
                exit();
            }
        }
    }
}

// Fonction pour inclure les fichiers de manière sécurisée
function safeInclude($path) {
    $full_path = PROJECT_ROOT . '/' . ltrim($path, '/');
    if (file_exists($full_path)) {
        return require_once $full_path;
    }
    throw new Exception("Fichier non trouvé: $path");
}