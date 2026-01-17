<?php
session_start();
session_destroy();

// Redirection vers la page d'accueil
header('Location: ../index.php');
exit();
?>