<?php
// Déconnexion utilisateur
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (isLoggedIn()) {
    // Logger la déconnexion
    logUserAction($_SESSION['user_id'], 'deconnexion', 'Déconnexion volontaire');
    
    // Supprimer les cookies "se souvenir de moi" si ils existent
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Détruire la session
    session_destroy();
    
    // Rediriger avec message de confirmation
    session_start(); // Redémarrer pour le message flash
    setFlashMessage('Vous avez été déconnecté avec succès. À bientôt !', 'success');
}

// Rediriger vers la page d'accueil
redirect(SITE_URL);
?>