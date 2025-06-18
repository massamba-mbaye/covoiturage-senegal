<?php
// Configuration de la base de données et constantes - Covoiturage Sénégal

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'covoiturage_senegal');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // Mettez votre mot de passe MySQL ici

// Constantes de l'application
define('SITE_NAME', 'Covoiturage Sénégal');
define('SITE_URL', 'http://localhost:8888/covoiturage-senegal');
define('UPLOAD_PATH', __DIR__ . '/../assets/images/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/images/uploads/');

// Configuration de l'application
define('MAX_PLACES_PAR_TRAJET', 8);
define('DELAI_ANNULATION_HEURES', 24);
define('PRIX_MINIMUM', 500); // en FCFA
define('PRIX_MAXIMUM', 50000); // en FCFA

// Timezone du Sénégal
date_default_timezone_set('Africa/Dakar');

// Démarrage de session si pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Connexion à la base de données avec PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Fonction pour gérer les erreurs de base de données
function handleDatabaseError($e) {
    error_log("Erreur DB: " . $e->getMessage());
    if (defined('DEBUG') && DEBUG) {
        die("Erreur de base de données : " . $e->getMessage());
    } else {
        die("Une erreur technique est survenue. Veuillez réessayer plus tard.");
    }
}

// Configuration du mode debug (à désactiver en production)
define('DEBUG', true);

// Gestion des erreurs PHP
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>