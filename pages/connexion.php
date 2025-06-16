<?php
// Page de connexion
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Connexion";
$page_description = "Connectez-vous à votre compte Covoiturage Sénégal pour accéder à vos trajets et réservations.";

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect(SITE_URL . '/pages/profil.php');
}

$errors = [];
$login_identifier = '';

// Traitement du formulaire
if ($_POST) {
    $login_identifier = secure($_POST['login_identifier']); // Téléphone ou email
    $mot_de_passe = $_POST['mot_de_passe'];
    $se_souvenir = isset($_POST['se_souvenir']);
    
    // Validation des données
    if (empty($login_identifier)) {
        $errors['login_identifier'] = "Veuillez saisir votre numéro de téléphone ou email.";
    }
    
    if (empty($mot_de_passe)) {
        $errors['mot_de_passe'] = "Veuillez saisir votre mot de passe.";
    }
    
    // Tentative de connexion
    if (empty($errors)) {
        try {
            // Nettoyer le numéro de téléphone si c'est un numéro
            $clean_phone = preg_replace('/[^0-9]/', '', $login_identifier);
            
            // Chercher l'utilisateur par téléphone ou email
            $stmt = $pdo->prepare("
                SELECT id, nom, prenom, telephone, email, mot_de_passe, type_utilisateur, statut, derniere_connexion
                FROM users 
                WHERE (telephone = ? OR telephone = ? OR email = ?) AND statut = 'actif'
            ");
            $stmt->execute([$login_identifier, $clean_phone, $login_identifier]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($mot_de_passe, $user['mot_de_passe'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_prenom'] = $user['prenom'];
                $_SESSION['user_type'] = $user['type_utilisateur'];
                $_SESSION['user_telephone'] = $user['telephone'];
                $_SESSION['user_email'] = $user['email'];
                
                // Mettre à jour la dernière connexion
                $stmt = $pdo->prepare("UPDATE users SET derniere_connexion = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Log de la connexion
                logUserAction($user['id'], 'connexion', 'Connexion réussie');
                
                // Cookie "Se souvenir de moi" (optionnel)
                if ($se_souvenir) {
                    $token = generateToken();
                    // En production, stocker ce token en base pour la validation
                    setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/'); // 30 jours
                }
                
                // Redirection
                $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : SITE_URL . '/pages/profil.php';
                redirectWithSuccess($redirect_url, "Bienvenue, " . $user['prenom'] . " !");
                
            } else {
                $errors['general'] = "Identifiants incorrects. Vérifiez votre numéro/email et mot de passe.";
            }
            
        } catch(PDOException $e) {
            $errors['general'] = "Erreur de connexion. Veuillez réessayer.";
            if (DEBUG) {
                $errors['general'] .= " " . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="connexion-page">
        <!-- En-tête de la page -->
        <div class="page-header" data-animate="fade-in">
            <h1>Connexion</h1>
            <p class="page-subtitle">
                Connectez-vous pour accéder à votre compte Covoiturage Sénégal
            </p>
        </div>
        
        <div class="connexion-container">
            <!-- Formulaire de connexion -->
            <div class="connexion-form-container" data-animate="fade-in">
                <form method="POST" class="connexion-form" data-validate>
                    <!-- Erreur générale -->
                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger">
                            <span class="alert-icon">⚠️</span>
                            <?php echo htmlspecialchars($errors['general']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Champ identifiant -->
                    <div class="form-group">
                        <label for="login_identifier" class="form-label">
                            <span class="label-icon">📱</span>
                            Téléphone ou Email
                        </label>
                        <input 
                            type="text" 
                            id="login_identifier" 
                            name="login_identifier" 
                            class="form-control <?php echo isset($errors['login_identifier']) ? 'error' : ''; ?>" 
                            value="<?php echo htmlspecialchars($login_identifier); ?>"
                            placeholder="77 123 45 67 ou email@exemple.com"
                            required
                            autofocus
                        >
                        <?php if (isset($errors['login_identifier'])): ?>
                            <div class="form-error"><?php echo htmlspecialchars($errors['login_identifier']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Champ mot de passe -->
                    <div class="form-group">
                        <label for="mot_de_passe" class="form-label">
                            <span class="label-icon">🔒</span>
                            Mot de passe
                        </label>
                        <div class="password-input-group">
                            <input 
                                type="password" 
                                id="mot_de_passe" 
                                name="mot_de_passe" 
                                class="form-control <?php echo isset($errors['mot_de_passe']) ? 'error' : ''; ?>" 
                                placeholder="Votre mot de passe"
                                required
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <span id="password-toggle-icon">👁️</span>
                            </button>
                        </div>
                        <?php if (isset($errors['mot_de_passe'])): ?>
                            <div class="form-error"><?php echo htmlspecialchars($errors['mot_de_passe']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Options -->
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="se_souvenir" <?php echo isset($_POST['se_souvenir']) ? 'checked' : ''; ?>>
                            <span class="checkbox-custom"></span>
                            Se souvenir de moi
                        </label>
                        
                        <a href="mot-de-passe-oublie.php" class="forgot-password-link">
                            Mot de passe oublié ?
                        </a>
                    </div>
                    
                    <!-- Bouton de connexion -->
                    <div class="form-submit">
                        <button type="submit" class="btn btn-primary btn-lg btn-full">
                            <span class="btn-icon">🔑</span>
                            Se connecter
                        </button>
                    </div>
                </form>
                
                <!-- Liens alternatifs -->
                <div class="alternative-links">
                    <div class="or-separator">
                        <span>ou</span>
                    </div>
                    
                    <a href="inscription.php" class="btn btn-outline btn-lg btn-full">
                        <span class="btn-icon">✨</span>
                        Créer un compte
                    </a>
                </div>
            </div>
            
            <!-- Informations supplémentaires -->
            <div class="info-section" data-animate="fade-in">
                <div class="info-card">
                    <h3>
                        <span class="info-icon">🚗</span>
                        Première fois ici ?
                    </h3>
                    <p>
                        Rejoignez des milliers de Sénégalais qui voyagent ensemble et économisent 
                        sur leurs déplacements quotidiens.
                    </p>
                    <ul class="info-list">
                        <li>✅ Inscription gratuite</li>
                        <li>✅ Vérification des membres</li>
                        <li>✅ Paiements sécurisés</li>
                        <li>✅ Support client dédié</li>
                    </ul>
                </div>
                
                <div class="info-card">
                    <h3>
                        <span class="info-icon">📱</span>
                        Connexion sécurisée
                    </h3>
                    <p>
                        Vos données sont protégées par un cryptage SSL et nos serveurs 
                        sont hébergés en sécurité.
                    </p>
                    <div class="security-badges">
                        <span class="security-badge">🔒 SSL</span>
                        <span class="security-badge">🛡️ Sécurisé</span>
                        <span class="security-badge">🇸🇳 Sénégal</span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3>
                        <span class="info-icon">💡</span>
                        Astuce
                    </h3>
                    <p>
                        Vous pouvez vous connecter avec votre numéro de téléphone 
                        (format: 77 123 45 67) ou votre adresse email.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Section aide -->
        <div class="help-section" data-animate="fade-in">
            <h3>Besoin d'aide ?</h3>
            <div class="help-links">
                <a href="aide.php" class="help-link">
                    <span class="help-icon">❓</span>
                    Centre d'aide
                </a>
                <a href="contact.php" class="help-link">
                    <span class="help-icon">📞</span>
                    Nous contacter
                </a>
                <a href="https://wa.me/221771234567" class="help-link" target="_blank">
                    <span class="help-icon">📱</span>
                    WhatsApp Support
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Styles spécifiques -->
<style>
.connexion-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: var(--spacing-xl) 0;
}

.page-header {
    text-align: center;
    margin-bottom: var(--spacing-xxl);
}

.page-header h1 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
}

.page-subtitle {
    color: var(--gray);
    font-size: 1.1rem;
}

.connexion-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-xxl);
    align-items: start;
}

/* Formulaire de connexion */
.connexion-form-container {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    padding: var(--spacing-xxl);
}

.form-group {
    margin-bottom: var(--spacing-lg);
}

.form-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-sm);
    font-weight: var(--font-weight-medium);
    color: var(--dark-gray);
}

.label-icon {
    font-size: 1.2rem;
}

.form-control {
    width: 100%;
    padding: var(--spacing-md);
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius);
    font-size: 1rem;
    transition: var(--transition);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 133, 62, 0.1);
}

.form-control.error {
    border-color: var(--danger-color);
}

/* Groupe mot de passe */
.password-input-group {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: var(--spacing-md);
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    padding: var(--spacing-xs);
}

/* Options du formulaire */
.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xl);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    cursor: pointer;
    font-size: 0.9rem;
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 18px;
    height: 18px;
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--white);
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom::before {
    content: "✓";
    font-size: 0.7rem;
    font-weight: bold;
}

.forgot-password-link {
    color: var(--primary-color);
    font-size: 0.9rem;
    text-decoration: none;
}

.forgot-password-link:hover {
    text-decoration: underline;
}

/* Boutons */
.btn-full {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
}

.btn-icon {
    font-size: 1.2rem;
}

/* Liens alternatifs */
.alternative-links {
    margin-top: var(--spacing-xl);
}

.or-separator {
    text-align: center;
    margin: var(--spacing-lg) 0;
    position: relative;
}

.or-separator::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background-color: #e9ecef;
}

.or-separator span {
    background-color: var(--white);
    padding: 0 var(--spacing-md);
    color: var(--gray);
    font-size: 0.9rem;
}

/* Section d'informations */
.info-section {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}

.info-card {
    background: var(--light-gray);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius-lg);
    border-left: 4px solid var(--primary-color);
}

.info-card h3 {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
    font-size: 1.1rem;
}

.info-icon {
    font-size: 1.3rem;
}

.info-card p {
    color: var(--gray);
    line-height: 1.6;
    margin-bottom: var(--spacing-md);
}

.info-list {
    list-style: none;
    padding: 0;
}

.info-list li {
    padding: var(--spacing-xs) 0;
    color: var(--gray);
    font-size: 0.9rem;
}

.security-badges {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

.security-badge {
    background-color: var(--primary-color);
    color: var(--white);
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius-sm);
    font-size: 0.8rem;
    font-weight: var(--font-weight-medium);
}

/* Section aide */
.help-section {
    margin-top: var(--spacing-xxl);
    text-align: center;
}

.help-section h3 {
    color: var(--dark-gray);
    margin-bottom: var(--spacing-lg);
}

.help-links {
    display: flex;
    justify-content: center;
    gap: var(--spacing-lg);
    flex-wrap: wrap;
}

.help-link {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md) var(--spacing-lg);
    border: 1px solid #e9ecef;
    border-radius: var(--border-radius);
    text-decoration: none;
    color: var(--gray);
    transition: var(--transition);
}

.help-link:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-2px);
}

.help-icon {
    font-size: 1.1rem;
}

/* Alerte */
.alert {
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-icon {
    font-size: 1.2rem;
}

/* Responsive */
@media (max-width: 767px) {
    .connexion-page {
        padding: var(--spacing-lg) 0;
    }
    
    .connexion-container {
        grid-template-columns: 1fr;
        gap: var(--spacing-xl);
    }
    
    .connexion-form-container {
        padding: var(--spacing-lg);
    }
    
    .form-options {
        flex-direction: column;
        gap: var(--spacing-md);
        align-items: flex-start;
    }
    
    .help-links {
        flex-direction: column;
        align-items: center;
    }
    
    .help-link {
        width: 100%;
        max-width: 250px;
        justify-content: center;
    }
}
</style>

<script>
// Script spécifique à la connexion
document.addEventListener('DOMContentLoaded', function() {
    // Focus automatique sur le premier champ
    const firstInput = document.getElementById('login_identifier');
    if (firstInput) {
        firstInput.focus();
    }
    
    // Formater le numéro de téléphone si saisi
    const loginInput = document.getElementById('login_identifier');
    if (loginInput) {
        loginInput.addEventListener('input', function() {
            // Détecter si c'est un numéro (commence par un chiffre)
            if (/^\d/.test(this.value)) {
                let value = this.value.replace(/[^0-9]/g, '');
                if (value.length <= 9) {
                    // Format: XX XXX XX XX
                    if (value.length > 2) {
                        value = value.substring(0, 2) + ' ' + value.substring(2);
                    }
                    if (value.length > 6) {
                        value = value.substring(0, 6) + ' ' + value.substring(6);
                    }
                    if (value.length > 9) {
                        value = value.substring(0, 9) + ' ' + value.substring(9);
                    }
                    this.value = value;
                }
            }
        });
    }
    
    // Soumission du formulaire avec animation
    const form = document.querySelector('.connexion-form');
    if (form) {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            setButtonLoading(submitBtn, true);
        });
    }
});

/**
 * Basculer la visibilité du mot de passe
 */
function togglePassword() {
    const passwordInput = document.getElementById('mot_de_passe');
    const toggleIcon = document.getElementById('password-toggle-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.textContent = '🙈';
    } else {
        passwordInput.type = 'password';
        toggleIcon.textContent = '👁️';
    }
}
</script>

<?php include '../includes/footer.php'; ?>