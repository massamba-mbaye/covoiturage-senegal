<?php
// Page d'inscription - Chauffeurs et Passagers
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Inscription";
$page_description = "Rejoignez Covoiturage Sénégal en tant que chauffeur ou passager. Inscription gratuite et sécurisée.";

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect(SITE_URL . '/pages/profil.php');
}

// Récupérer le type d'utilisateur depuis l'URL
$user_type = isset($_GET['type']) && in_array($_GET['type'], ['chauffeur', 'passager']) ? $_GET['type'] : 'passager';

$errors = [];
$success = false;

// Traitement du formulaire
if ($_POST) {
    // Récupérer et nettoyer les données
    $nom = secure($_POST['nom']);
    $prenom = secure($_POST['prenom']);
    $telephone = secure($_POST['telephone']);
    $email = !empty($_POST['email']) ? secure($_POST['email']) : '';
    $mot_de_passe = $_POST['mot_de_passe'];
    $confirmer_mot_de_passe = $_POST['confirmer_mot_de_passe'];
    $type_utilisateur = isset($_POST['type_utilisateur']) ? secure($_POST['type_utilisateur']) : $user_type;
    $date_naissance = !empty($_POST['date_naissance']) ? secure($_POST['date_naissance']) : '';
    $genre = !empty($_POST['genre']) ? secure($_POST['genre']) : '';
    $ville = !empty($_POST['ville']) ? secure($_POST['ville']) : '';
    $accepter_conditions = isset($_POST['accepter_conditions']);
    
    // Validation des données
    $validation_rules = [
        'nom' => ['required' => true, 'min_length' => 2, 'max_length' => 100, 'label' => 'Nom'],
        'prenom' => ['required' => true, 'min_length' => 2, 'max_length' => 100, 'label' => 'Prénom'],
        'telephone' => ['required' => true, 'senegal_phone' => true, 'label' => 'Téléphone'],
        'mot_de_passe' => ['required' => true, 'min_length' => 6, 'label' => 'Mot de passe'],
    ];
    
    if (!empty($email)) {
        $validation_rules['email'] = ['email' => true, 'label' => 'Email'];
    }
    
    $form_errors = validateInput($_POST, $validation_rules);
    $errors = array_merge($errors, $form_errors);
    
    // Vérifications supplémentaires
    if ($mot_de_passe !== $confirmer_mot_de_passe) {
        $errors['confirmer_mot_de_passe'] = "Les mots de passe ne correspondent pas.";
    }
    
    if (!$accepter_conditions) {
        $errors['accepter_conditions'] = "Vous devez accepter les conditions d'utilisation.";
    }
    
    if (!in_array($type_utilisateur, ['chauffeur', 'passager'])) {
        $errors['type_utilisateur'] = "Type d'utilisateur invalide.";
    }
    
    // Nettoyer le numéro de téléphone
    $telephone = preg_replace('/[^0-9]/', '', $telephone);
    
    // Vérifier si le téléphone ou l'email existent déjà
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE telephone = ? OR (email IS NOT NULL AND email = ?)");
            $stmt->execute([$telephone, $email]);
            if ($stmt->fetch()) {
                $errors['general'] = "Ce numéro de téléphone ou cette adresse email est déjà utilisé.";
            }
        } catch(PDOException $e) {
            $errors['general'] = "Erreur lors de la vérification des données.";
        }
    }
    
    // Insertion en base de données
    if (empty($errors)) {
        try {
            $mot_de_passe_hash = hashPassword($mot_de_passe);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (nom, prenom, telephone, email, mot_de_passe, type_utilisateur, date_naissance, genre, ville, date_inscription) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $nom,
                $prenom,
                $telephone,
                $email ?: null,
                $mot_de_passe_hash,
                $type_utilisateur,
                $date_naissance ?: null,
                $genre ?: null,
                $ville ?: null
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Connexion automatique
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_nom'] = $nom;
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_type'] = $type_utilisateur;
            $_SESSION['user_telephone'] = $telephone;
            
            // Log de l'inscription
            logUserAction($user_id, 'inscription', "Type: $type_utilisateur");
            
            // Message de bienvenue
            $message = "Bienvenue sur Covoiturage Sénégal ! Votre compte a été créé avec succès.";
            redirectWithSuccess(SITE_URL . '/pages/profil.php', $message);
            
        } catch(PDOException $e) {
            $errors['general'] = "Erreur lors de la création du compte. Veuillez réessayer.";
            if (DEBUG) {
                $errors['general'] .= " " . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="inscription-page">
        <!-- En-tête de la page -->
        <div class="page-header" data-animate="fade-in">
            <h1>Créer votre compte</h1>
            <p class="page-subtitle">
                Rejoignez la communauté Covoiturage Sénégal et voyagez malin !
            </p>
        </div>
        
        <!-- Sélecteur de type d'utilisateur -->
        <div class="user-type-selector" data-animate="fade-in">
            <div class="type-options">
                <a href="?type=passager" class="type-option <?php echo $user_type === 'passager' ? 'active' : ''; ?>">
                    <div class="type-icon">👤</div>
                    <h3>Passager</h3>
                    <p>Trouvez des trajets</p>
                </a>
                <a href="?type=chauffeur" class="type-option <?php echo $user_type === 'chauffeur' ? 'active' : ''; ?>">
                    <div class="type-icon">🚗</div>
                    <h3>Chauffeur</h3>
                    <p>Proposez des trajets</p>
                </a>
            </div>
        </div>
        
        <!-- Formulaire d'inscription -->
        <div class="inscription-form-container" data-animate="fade-in">
            <form method="POST" class="inscription-form" data-validate>
                <input type="hidden" name="type_utilisateur" value="<?php echo htmlspecialchars($user_type); ?>">
                
                <!-- Erreur générale -->
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Informations personnelles -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="section-icon">👤</span>
                        Informations personnelles
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom" class="form-label">Nom *</label>
                            <input 
                                type="text" 
                                id="nom" 
                                name="nom" 
                                class="form-control <?php echo isset($errors['nom']) ? 'error' : ''; ?>" 
                                value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>"
                                required
                                data-min-length="2"
                                data-max-length="100"
                            >
                            <?php if (isset($errors['nom'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['nom']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input 
                                type="text" 
                                id="prenom" 
                                name="prenom" 
                                class="form-control <?php echo isset($errors['prenom']) ? 'error' : ''; ?>" 
                                value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>"
                                required
                                data-min-length="2"
                                data-max-length="100"
                            >
                            <?php if (isset($errors['prenom'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['prenom']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_naissance" class="form-label">Date de naissance</label>
                            <input 
                                type="date" 
                                id="date_naissance" 
                                name="date_naissance" 
                                class="form-control" 
                                value="<?php echo isset($_POST['date_naissance']) ? htmlspecialchars($_POST['date_naissance']) : ''; ?>"
                                max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="genre" class="form-label">Genre</label>
                            <select id="genre" name="genre" class="form-control">
                                <option value="">Sélectionner</option>
                                <option value="homme" <?php echo (isset($_POST['genre']) && $_POST['genre'] === 'homme') ? 'selected' : ''; ?>>Homme</option>
                                <option value="femme" <?php echo (isset($_POST['genre']) && $_POST['genre'] === 'femme') ? 'selected' : ''; ?>>Femme</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ville" class="form-label">Ville</label>
                        <input 
                            type="text" 
                            id="ville" 
                            name="ville" 
                            class="form-control" 
                            value="<?php echo isset($_POST['ville']) ? htmlspecialchars($_POST['ville']) : ''; ?>"
                            data-autocomplete="cities"
                            placeholder="Ex: Dakar, Thiès, Saint-Louis..."
                        >
                    </div>
                </div>
                
                <!-- Contact -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="section-icon">📞</span>
                        Contact
                    </h3>
                    
                    <div class="form-group">
                        <label for="telephone" class="form-label">Numéro de téléphone *</label>
                        <input 
                            type="tel" 
                            id="telephone" 
                            name="telephone" 
                            class="form-control <?php echo isset($errors['telephone']) ? 'error' : ''; ?>" 
                            value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>"
                            required
                            placeholder="Ex: 77 123 45 67"
                        >
                        <div class="form-help">Format accepté : 77/78/76/75/33/70 suivi de 7 chiffres</div>
                        <?php if (isset($errors['telephone'])): ?>
                            <div class="form-error"><?php echo htmlspecialchars($errors['telephone']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Adresse email (optionnel)</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            placeholder="votre.email@exemple.com"
                        >
                        <div class="form-help">Recommandé pour récupérer votre mot de passe</div>
                        <?php if (isset($errors['email'])): ?>
                            <div class="form-error"><?php echo htmlspecialchars($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sécurité -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="section-icon">🔒</span>
                        Sécurité
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mot_de_passe" class="form-label">Mot de passe *</label>
                            <input 
                                type="password" 
                                id="mot_de_passe" 
                                name="mot_de_passe" 
                                class="form-control <?php echo isset($errors['mot_de_passe']) ? 'error' : ''; ?>" 
                                required
                                data-min-length="6"
                            >
                            <div class="form-help">Au moins 6 caractères</div>
                            <?php if (isset($errors['mot_de_passe'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['mot_de_passe']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmer_mot_de_passe" class="form-label">Confirmer le mot de passe *</label>
                            <input 
                                type="password" 
                                id="confirmer_mot_de_passe" 
                                name="confirmer_mot_de_passe" 
                                class="form-control <?php echo isset($errors['confirmer_mot_de_passe']) ? 'error' : ''; ?>" 
                                required
                            >
                            <?php if (isset($errors['confirmer_mot_de_passe'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['confirmer_mot_de_passe']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Conditions -->
                <div class="form-section">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input 
                                type="checkbox" 
                                name="accepter_conditions" 
                                required
                                <?php echo isset($_POST['accepter_conditions']) ? 'checked' : ''; ?>
                            >
                            <span class="checkbox-custom"></span>
                            J'accepte les <a href="../pages/conditions.php" target="_blank">conditions d'utilisation</a> 
                            et la <a href="../pages/confidentialite.php" target="_blank">politique de confidentialité</a> *
                        </label>
                        <?php if (isset($errors['accepter_conditions'])): ?>
                            <div class="form-error"><?php echo htmlspecialchars($errors['accepter_conditions']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bouton de soumission -->
                <div class="form-submit">
                    <button type="submit" class="btn btn-primary btn-lg btn-full">
                        <span class="btn-icon">✨</span>
                        Créer mon compte <?php echo $user_type; ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Lien de connexion -->
        <div class="login-link" data-animate="fade-in">
            <p>Vous avez déjà un compte ? <a href="connexion.php">Connectez-vous ici</a></p>
        </div>
        
        <!-- Avantages selon le type -->
        <div class="user-benefits" data-animate="fade-in">
            <?php if ($user_type === 'chauffeur'): ?>
                <h3>En tant que chauffeur, vous pourrez :</h3>
                <ul class="benefits-list">
                    <li>💰 Rentabiliser vos trajets en partageant les frais</li>
                    <li>🤝 Rencontrer de nouvelles personnes</li>
                    <li>📱 Gérer facilement vos trajets depuis votre téléphone</li>
                    <li>⭐ Construire votre réputation avec le système de notes</li>
                    <li>🛡️ Voyager en sécurité avec des passagers vérifiés</li>
                </ul>
            <?php else: ?>
                <h3>En tant que passager, vous pourrez :</h3>
                <ul class="benefits-list">
                    <li>💰 Voyager moins cher qu'en transport traditionnel</li>
                    <li>🚗 Accéder à plus de destinations</li>
                    <li>⏰ Choisir vos horaires de départ</li>
                    <li>👥 Voyager dans une ambiance conviviale</li>
                    <li>🔍 Trouver facilement des trajets près de chez vous</li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Styles spécifiques -->
<style>
.inscription-page {
    max-width: 600px;
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

/* Sélecteur de type */
.user-type-selector {
    margin-bottom: var(--spacing-xxl);
}

.type-options {
    display: flex;
    gap: var(--spacing-lg);
    justify-content: center;
}

.type-option {
    flex: 1;
    text-align: center;
    padding: var(--spacing-xl);
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius-lg);
    text-decoration: none;
    color: var(--dark-gray);
    transition: var(--transition);
    max-width: 200px;
}

.type-option:hover,
.type-option.active {
    border-color: var(--primary-color);
    background-color: rgba(0, 133, 62, 0.05);
    color: var(--primary-color);
    transform: translateY(-2px);
}

.type-icon {
    font-size: 3rem;
    margin-bottom: var(--spacing-md);
}

.type-option h3 {
    margin-bottom: var(--spacing-sm);
    font-size: 1.25rem;
}

.type-option p {
    font-size: 0.9rem;
    opacity: 0.7;
    margin: 0;
}

/* Formulaire */
.inscription-form-container {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    padding: var(--spacing-xxl);
    margin-bottom: var(--spacing-xl);
}

.form-section {
    margin-bottom: var(--spacing-xxl);
}

.form-section-title {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-sm);
    border-bottom: 2px solid var(--light-gray);
}

.section-icon {
    font-size: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-lg);
}

.form-group {
    margin-bottom: var(--spacing-lg);
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

/* Checkbox personnalisé */
.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-sm);
    cursor: pointer;
    line-height: 1.5;
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    flex-shrink: 0;
    margin-top: 2px;
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--white);
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom::before {
    content: "✓";
    font-size: 0.8rem;
    font-weight: bold;
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

.form-submit {
    margin-top: var(--spacing-xl);
}

/* Lien de connexion */
.login-link {
    text-align: center;
    margin-bottom: var(--spacing-xl);
}

.login-link a {
    color: var(--primary-color);
    font-weight: var(--font-weight-medium);
}

/* Avantages */
.user-benefits {
    background: var(--light-gray);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
}

.user-benefits h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.benefits-list {
    list-style: none;
    padding: 0;
}

.benefits-list li {
    padding: var(--spacing-sm) 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

/* Alerte */
.alert {
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive */
@media (max-width: 767px) {
    .inscription-page {
        padding: var(--spacing-lg) 0;
    }
    
    .type-options {
        flex-direction: column;
        align-items: center;
    }
    
    .type-option {
        max-width: none;
        width: 100%;
    }
    
    .inscription-form-container {
        padding: var(--spacing-lg);
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: var(--spacing-md);
    }
}
</style>

<script>
// Script spécifique à l'inscription
document.addEventListener('DOMContentLoaded', function() {
    // Formater le numéro de téléphone en temps réel
    const phoneInput = document.getElementById('telephone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
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
        });
    }
    
    // Vérification des mots de passe en temps réel
    const passwordInput = document.getElementById('mot_de_passe');
    const confirmPasswordInput = document.getElementById('confirmer_mot_de_passe');
    
    if (passwordInput && confirmPasswordInput) {
        function checkPasswordMatch() {
            if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Les mots de passe ne correspondent pas');
                showFieldError(confirmPasswordInput, 'Les mots de passe ne correspondent pas');
            } else {
                confirmPasswordInput.setCustomValidity('');
                clearFieldError(confirmPasswordInput);
            }
        }
        
        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
});
</script>

<?php include '../includes/footer.php'; ?>