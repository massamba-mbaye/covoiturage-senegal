<?php
// Inclure la configuration si pas déjà fait
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';
}

// Définir le titre de la page si pas défini
$page_title = isset($page_title) ? $page_title : '';
$page_description = isset($page_description) ? $page_description : 'Plateforme de covoiturage pour tous vos trajets au Sénégal. Trouvez ou proposez un trajet rapidement et en sécurité.';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="covoiturage, sénégal, transport, voyage, dakar, thiès, saint-louis">
    <meta name="author" content="Covoiturage Sénégal">
    
    <!-- Titre de la page -->
    <title><?php echo $page_title ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    
    <!-- Fonts Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Meta pour les réseaux sociaux -->
    <meta property="og:title" content="<?php echo $page_title ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo SITE_NAME; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.jpg">
</head>
<body>
    <header class="main-header">
        <nav class="navbar">
            <div class="container">
                <!-- Logo -->
                <div class="navbar-brand">
                    <a href="<?php echo SITE_URL; ?>" class="logo">
                        <span class="logo-icon">🚗</span>
                        <span class="logo-text"><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                
                <!-- Menu principal -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>" class="nav-link">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/pages/recherche.php" class="nav-link">
                            <span class="nav-icon">🔍</span>
                            Rechercher
                        </a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isChauffeur()): ?>
                            <li class="nav-item">
                                <a href="<?php echo SITE_URL; ?>/pages/publier.php" class="nav-link btn-primary">
                                    <span class="nav-icon">➕</span>
                                    Publier un trajet
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" id="userDropdown">
                                <span class="nav-icon">👤</span>
                                <?php echo htmlspecialchars($_SESSION['user_prenom']); ?>
                                <span class="dropdown-arrow">▼</span>
                            </a>
                            <ul class="dropdown-menu" id="userDropdownMenu">
                                <li><a href="<?php echo SITE_URL; ?>/pages/profil.php" class="dropdown-link">Mon Profil</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/pages/mes-trajets.php" class="dropdown-link">Mes Trajets</a></li>
                                <?php if (isAdmin()): ?>
                                    <li><a href="<?php echo SITE_URL; ?>/admin/" class="dropdown-link">Administration</a></li>
                                <?php endif; ?>
                                <li class="dropdown-divider"></li>
                                <li><a href="<?php echo SITE_URL; ?>/logout.php" class="dropdown-link logout">Déconnexion</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/pages/connexion.php" class="nav-link">Connexion</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/pages/inscription.php" class="nav-link btn-primary">Inscription</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Bouton menu mobile -->
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </nav>
        
        <!-- Menu mobile -->
        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-content">
                <a href="<?php echo SITE_URL; ?>" class="mobile-menu-link">🏠 Accueil</a>
                <a href="<?php echo SITE_URL; ?>/pages/recherche.php" class="mobile-menu-link">🔍 Rechercher</a>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isChauffeur()): ?>
                        <a href="<?php echo SITE_URL; ?>/pages/publier.php" class="mobile-menu-link">➕ Publier un trajet</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/pages/profil.php" class="mobile-menu-link">👤 Mon Profil</a>
                    <a href="<?php echo SITE_URL; ?>/pages/mes-trajets.php" class="mobile-menu-link">🚗 Mes Trajets</a>
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/" class="mobile-menu-link">⚙️ Administration</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/logout.php" class="mobile-menu-link logout">🚪 Déconnexion</a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/pages/connexion.php" class="mobile-menu-link">🔑 Connexion</a>
                    <a href="<?php echo SITE_URL; ?>/pages/inscription.php" class="mobile-menu-link">📝 Inscription</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Messages flash -->
    <?php
    $flash = getFlashMessage();
    if ($flash):
    ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>" id="flashMessage">
        <div class="container">
            <span class="flash-content"><?php echo htmlspecialchars($flash['message']); ?></span>
            <button class="flash-close" onclick="closeFlashMessage()">×</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contenu principal -->
    <main class="main-content">