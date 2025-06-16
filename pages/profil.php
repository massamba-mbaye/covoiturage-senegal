<?php
// Page de profil utilisateur
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Mon Profil";
$page_description = "Gérez vos informations personnelles et paramètres de compte.";

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    redirectWithError('../pages/connexion.php', 'Vous devez être connecté pour accéder à votre profil.');
}

// Récupérer les informations de l'utilisateur
$user = getCurrentUser();
if (!$user) {
    redirectWithError('../pages/connexion.php', 'Session expirée. Veuillez vous reconnecter.');
}

$errors = [];
$success_message = '';

// Traitement du formulaire de mise à jour
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Mise à jour des informations de profil
        $nom = secure($_POST['nom']);
        $prenom = secure($_POST['prenom']);
        $email = !empty($_POST['email']) ? secure($_POST['email']) : null;
        $date_naissance = !empty($_POST['date_naissance']) ? secure($_POST['date_naissance']) : null;
        $genre = !empty($_POST['genre']) ? secure($_POST['genre']) : null;
        $ville = !empty($_POST['ville']) ? secure($_POST['ville']) : null;
        $type_utilisateur = secure($_POST['type_utilisateur']);
        
        // Validation
        $validation_rules = [
            'nom' => ['required' => true, 'min_length' => 2, 'max_length' => 100, 'label' => 'Nom'],
            'prenom' => ['required' => true, 'min_length' => 2, 'max_length' => 100, 'label' => 'Prénom'],
        ];
        
        if (!empty($email)) {
            $validation_rules['email'] = ['email' => true, 'label' => 'Email'];
        }
        
        $form_errors = validateInput($_POST, $validation_rules);
        $errors = array_merge($errors, $form_errors);
        
        if (!in_array($type_utilisateur, ['chauffeur', 'passager'])) {
            $errors['type_utilisateur'] = "Type d'utilisateur invalide.";
        }
        
        // Vérifier si l'email existe déjà (si modifié)
        if (empty($errors) && $email && $email !== $user['email']) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user['id']]);
                if ($stmt->fetch()) {
                    $errors['email'] = "Cette adresse email est déjà utilisée.";
                }
            } catch(PDOException $e) {
                $errors['general'] = "Erreur lors de la vérification de l'email.";
            }
        }
        
        // Mise à jour en base
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET nom = ?, prenom = ?, email = ?, date_naissance = ?, genre = ?, ville = ?, type_utilisateur = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $nom, $prenom, $email, $date_naissance, $genre, $ville, $type_utilisateur, $user['id']
                ]);
                
                // Mettre à jour la session
                $_SESSION['user_nom'] = $nom;
                $_SESSION['user_prenom'] = $prenom;
                $_SESSION['user_type'] = $type_utilisateur;
                $_SESSION['user_email'] = $email;
                
                // Log de l'action
                logUserAction($user['id'], 'modification_profil', 'Profil mis à jour');
                
                $success_message = "Votre profil a été mis à jour avec succès !";
                
                // Recharger les données
                $user = getCurrentUser();
                
            } catch(PDOException $e) {
                $errors['general'] = "Erreur lors de la mise à jour. Veuillez réessayer.";
                if (DEBUG) {
                    $errors['general'] .= " " . $e->getMessage();
                }
            }
        }
    }
    
    elseif ($action === 'change_password') {
        // Changement de mot de passe
        $mot_de_passe_actuel = $_POST['mot_de_passe_actuel'];
        $nouveau_mot_de_passe = $_POST['nouveau_mot_de_passe'];
        $confirmer_mot_de_passe = $_POST['confirmer_mot_de_passe'];
        
        // Validation
        if (empty($mot_de_passe_actuel)) {
            $errors['mot_de_passe_actuel'] = "Veuillez saisir votre mot de passe actuel.";
        }
        
        if (empty($nouveau_mot_de_passe) || strlen($nouveau_mot_de_passe) < 6) {
            $errors['nouveau_mot_de_passe'] = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        }
        
        if ($nouveau_mot_de_passe !== $confirmer_mot_de_passe) {
            $errors['confirmer_mot_de_passe'] = "Les mots de passe ne correspondent pas.";
        }
        
        // Vérifier le mot de passe actuel
        if (empty($errors) && !verifyPassword($mot_de_passe_actuel, $user['mot_de_passe'])) {
            $errors['mot_de_passe_actuel'] = "Mot de passe actuel incorrect.";
        }
        
        // Mise à jour
        if (empty($errors)) {
            try {
                $nouveau_hash = hashPassword($nouveau_mot_de_passe);
                $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$nouveau_hash, $user['id']]);
                
                logUserAction($user['id'], 'changement_mot_de_passe', 'Mot de passe modifié');
                
                $success_message = "Votre mot de passe a été modifié avec succès !";
                
            } catch(PDOException $e) {
                $errors['general'] = "Erreur lors du changement de mot de passe.";
            }
        }
    }
}

// Récupérer les statistiques de l'utilisateur
try {
    if ($user['type_utilisateur'] === 'chauffeur') {
        // Statistiques chauffeur
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb_trajets FROM trajets WHERE chauffeur_id = ?");
        $stmt->execute([$user['id']]);
        $nb_trajets = $stmt->fetch()['nb_trajets'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT r.passager_id) as nb_passagers 
            FROM reservations r 
            JOIN trajets t ON r.trajet_id = t.id 
            WHERE t.chauffeur_id = ? AND r.statut = 'confirmee'
        ");
        $stmt->execute([$user['id']]);
        $nb_passagers = $stmt->fetch()['nb_passagers'];
        
        $stmt = $pdo->prepare("
            SELECT SUM(r.prix_total) as revenus_total 
            FROM reservations r 
            JOIN trajets t ON r.trajet_id = t.id 
            WHERE t.chauffeur_id = ? AND r.statut = 'terminee'
        ");
        $stmt->execute([$user['id']]);
        $revenus_total = $stmt->fetch()['revenus_total'] ?: 0;
        
    } else {
        // Statistiques passager
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb_voyages FROM reservations WHERE passager_id = ? AND statut = 'terminee'");
        $stmt->execute([$user['id']]);
        $nb_voyages = $stmt->fetch()['nb_voyages'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT t.chauffeur_id) as nb_chauffeurs 
            FROM reservations r 
            JOIN trajets t ON r.trajet_id = t.id 
            WHERE r.passager_id = ? AND r.statut = 'terminee'
        ");
        $stmt->execute([$user['id']]);
        $nb_chauffeurs = $stmt->fetch()['nb_chauffeurs'];
        
        $stmt = $pdo->prepare("SELECT SUM(prix_total) as depenses_total FROM reservations WHERE passager_id = ? AND statut = 'terminee'");
        $stmt->execute([$user['id']]);
        $depenses_total = $stmt->fetch()['depenses_total'] ?: 0;
    }
    
} catch(PDOException $e) {
    // En cas d'erreur, initialiser avec des valeurs par défaut
    $nb_trajets = $nb_voyages = $nb_passagers = $nb_chauffeurs = 0;
    $revenus_total = $depenses_total = 0;
}

include '../includes/header.php';
?>

<div class="container">
    <div class="profil-page">
        <!-- En-tête du profil -->
        <div class="profile-header" data-animate="fade-in">
            <div class="profile-avatar">
                <?php if (!empty($user['photo_profil'])): ?>
                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($user['photo_profil']); ?>" alt="Photo de profil">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h1>
                <div class="profile-meta">
                    <span class="user-type <?php echo $user['type_utilisateur']; ?>">
                        <?php echo $user['type_utilisateur'] === 'chauffeur' ? '🚗 Chauffeur' : '👤 Passager'; ?>
                    </span>
                    
                    <?php if ($user['note_moyenne'] > 0): ?>
                        <span class="user-rating">
                            <span class="rating-stars">
                                <?php 
                                $note = round($user['note_moyenne']);
                                for($i = 1; $i <= 5; $i++) {
                                    echo $i <= $note ? '⭐' : '☆';
                                }
                                ?>
                            </span>
                            <span class="rating-value"><?php echo number_format($user['note_moyenne'], 1); ?></span>
                            <span class="rating-count">(<?php echo $user['nombre_evaluations']; ?> évaluation<?php echo $user['nombre_evaluations'] > 1 ? 's' : ''; ?>)</span>
                        </span>
                    <?php endif; ?>
                    
                    <span class="join-date">
                        Membre depuis <?php echo formatDateFr($user['date_inscription']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Messages de succès/erreur -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" data-animate="fade-in">
                <span class="alert-icon">✅</span>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger" data-animate="fade-in">
                <span class="alert-icon">⚠️</span>
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="profile-stats" data-animate="fade-in">
            <?php if ($user['type_utilisateur'] === 'chauffeur'): ?>
                <div class="stat-card">
                    <div class="stat-icon">🚗</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $nb_trajets; ?></div>
                        <div class="stat-label">Trajet<?php echo $nb_trajets > 1 ? 's' : ''; ?> publié<?php echo $nb_trajets > 1 ? 's' : ''; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $nb_passagers; ?></div>
                        <div class="stat-label">Passager<?php echo $nb_passagers > 1 ? 's' : ''; ?> transporté<?php echo $nb_passagers > 1 ? 's' : ''; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo formatPrice($revenus_total); ?></div>
                        <div class="stat-label">Revenus générés</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $nb_voyages; ?></div>
                        <div class="stat-label">Voyage<?php echo $nb_voyages > 1 ? 's' : ''; ?> effectué<?php echo $nb_voyages > 1 ? 's' : ''; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🚗</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $nb_chauffeurs; ?></div>
                        <div class="stat-label">Chauffeur<?php echo $nb_chauffeurs > 1 ? 's' : ''; ?> rencontré<?php echo $nb_chauffeurs > 1 ? 's' : ''; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💸</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo formatPrice($depenses_total); ?></div>
                        <div class="stat-label">Total économisé</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Onglets -->
        <div class="profile-tabs" data-animate="fade-in">
            <div class="tab-navigation">
                <button class="tab-btn active" data-tab="profile">
                    <span class="tab-icon">👤</span>
                    Informations personnelles
                </button>
                <button class="tab-btn" data-tab="security">
                    <span class="tab-icon">🔒</span>
                    Sécurité
                </button>
                <button class="tab-btn" data-tab="preferences">
                    <span class="tab-icon">⚙️</span>
                    Préférences
                </button>
            </div>
            
            <!-- Onglet Informations personnelles -->
            <div class="tab-content active" id="tab-profile">
                <form method="POST" class="profile-form" data-validate>
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-section">
                        <h3>Informations de base</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nom" class="form-label">Nom *</label>
                                <input 
                                    type="text" 
                                    id="nom" 
                                    name="nom" 
                                    class="form-control <?php echo isset($errors['nom']) ? 'error' : ''; ?>" 
                                    value="<?php echo htmlspecialchars($user['nom']); ?>"
                                    required
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
                                    value="<?php echo htmlspecialchars($user['prenom']); ?>"
                                    required
                                >
                                <?php if (isset($errors['prenom'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($errors['prenom']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input 
                                    type="tel" 
                                    id="telephone" 
                                    name="telephone" 
                                    class="form-control" 
                                    value="<?php echo formatSenegalPhone($user['telephone']); ?>"
                                    disabled
                                >
                                <small class="form-help">Le numéro de téléphone ne peut pas être modifié</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                                    value="<?php echo htmlspecialchars($user['email'] ?: ''); ?>"
                                    placeholder="votre.email@exemple.com"
                                >
                                <?php if (isset($errors['email'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($errors['email']); ?></div>
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
                                    value="<?php echo htmlspecialchars($user['date_naissance'] ?: ''); ?>"
                                    max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="genre" class="form-label">Genre</label>
                                <select id="genre" name="genre" class="form-control">
                                    <option value="">Sélectionner</option>
                                    <option value="homme" <?php echo $user['genre'] === 'homme' ? 'selected' : ''; ?>>Homme</option>
                                    <option value="femme" <?php echo $user['genre'] === 'femme' ? 'selected' : ''; ?>>Femme</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="ville" class="form-label">Ville de résidence</label>
                            <input 
                                type="text" 
                                id="ville" 
                                name="ville" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($user['ville'] ?: ''); ?>"
                                data-autocomplete="cities"
                                placeholder="Ex: Dakar, Thiès, Saint-Louis..."
                            >
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Type de compte</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Je suis :</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input 
                                        type="radio" 
                                        name="type_utilisateur" 
                                        value="passager" 
                                        <?php echo $user['type_utilisateur'] === 'passager' ? 'checked' : ''; ?>
                                    >
                                    <span class="radio-custom"></span>
                                    <span class="radio-content">
                                        <strong>👤 Passager</strong>
                                        <small>Je cherche des trajets</small>
                                    </span>
                                </label>
                                
                                <label class="radio-label">
                                    <input 
                                        type="radio" 
                                        name="type_utilisateur" 
                                        value="chauffeur" 
                                        <?php echo $user['type_utilisateur'] === 'chauffeur' ? 'checked' : ''; ?>
                                    >
                                    <span class="radio-custom"></span>
                                    <span class="radio-content">
                                        <strong>🚗 Chauffeur</strong>
                                        <small>Je propose des trajets</small>
                                    </span>
                                </label>
                            </div>
                            <?php if (isset($errors['type_utilisateur'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['type_utilisateur']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-submit">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-icon">💾</span>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Onglet Sécurité -->
            <div class="tab-content" id="tab-security">
                <form method="POST" class="security-form" data-validate>
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-section">
                        <h3>Changer le mot de passe</h3>
                        
                        <div class="form-group">
                            <label for="mot_de_passe_actuel" class="form-label">Mot de passe actuel *</label>
                            <input 
                                type="password" 
                                id="mot_de_passe_actuel" 
                                name="mot_de_passe_actuel" 
                                class="form-control <?php echo isset($errors['mot_de_passe_actuel']) ? 'error' : ''; ?>" 
                                required
                            >
                            <?php if (isset($errors['mot_de_passe_actuel'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['mot_de_passe_actuel']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nouveau_mot_de_passe" class="form-label">Nouveau mot de passe *</label>
                                <input 
                                    type="password" 
                                    id="nouveau_mot_de_passe" 
                                    name="nouveau_mot_de_passe" 
                                    class="form-control <?php echo isset($errors['nouveau_mot_de_passe']) ? 'error' : ''; ?>" 
                                    required
                                    data-min-length="6"
                                >
                                <small class="form-help">Au moins 6 caractères</small>
                                <?php if (isset($errors['nouveau_mot_de_passe'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($errors['nouveau_mot_de_passe']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmer_mot_de_passe" class="form-label">Confirmer le nouveau mot de passe *</label>
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
                    
                    <div class="form-submit">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-icon">🔒</span>
                            Changer le mot de passe
                        </button>
                    </div>
                </form>
                
                <div class="security-info">
                    <h3>Informations de sécurité</h3>
                    <div class="security-items">
                        <div class="security-item">
                            <span class="security-icon">📅</span>
                            <div class="security-content">
                                <strong>Dernière connexion</strong>
                                <span><?php echo $user['derniere_connexion'] ? formatDateFr($user['derniere_connexion']) : 'Jamais'; ?></span>
                            </div>
                        </div>
                        
                        <div class="security-item">
                            <span class="security-icon">📱</span>
                            <div class="security-content">
                                <strong>Téléphone vérifié</strong>
                                <span class="<?php echo $user['telephone_verifie'] ? 'verified' : 'not-verified'; ?>">
                                    <?php echo $user['telephone_verifie'] ? '✅ Vérifié' : '❌ Non vérifié'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="security-item">
                            <span class="security-icon">📧</span>
                            <div class="security-content">
                                <strong>Email vérifié</strong>
                                <span class="<?php echo $user['email_verifie'] ? 'verified' : 'not-verified'; ?>">
                                    <?php echo $user['email_verifie'] ? '✅ Vérifié' : '❌ Non vérifié'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Préférences -->
            <div class="tab-content" id="tab-preferences">
                <div class="preferences-section">
                    <h3>Préférences de notification</h3>
                    <p class="text-muted">Choisissez comment vous souhaitez être notifié.</p>
                    
                    <div class="preference-items">
                        <div class="preference-item">
                            <div class="preference-content">
                                <strong>Nouvelles réservations</strong>
                                <small>Recevoir une notification quand quelqu'un réserve votre trajet</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="preference-item">
                            <div class="preference-content">
                                <strong>Rappels de trajet</strong>
                                <small>Recevoir un rappel 2 heures avant le départ</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="preference-item">
                            <div class="preference-content">
                                <strong>Nouveaux trajets</strong>
                                <small>Être notifié des nouveaux trajets sur vos itinéraires favoris</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="preference-item">
                            <div class="preference-content">
                                <strong>Messages promotionnels</strong>
                                <small>Recevoir des offres spéciales et des conseils</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="preferences-section">
                    <h3>Gestion du compte</h3>
                    
                    <div class="danger-zone">
                        <h4>Zone de danger</h4>
                        <p>Ces actions sont irréversibles. Procédez avec prudence.</p>
                        
                        <div class="danger-actions">
                            <button class="btn btn-outline btn-danger" onclick="confirmAccountDeactivation()">
                                Désactiver mon compte
                            </button>
                            
                            <button class="btn btn-danger" onclick="confirmAccountDeletion()">
                                Supprimer définitivement mon compte
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles spécifiques -->
<style>
.profil-page {
    max-width: 900px;
    margin: 0 auto;
    padding: var(--spacing-xl) 0;
}

/* En-tête du profil */
.profile-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-xl);
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    margin-bottom: var(--spacing-xl);
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 4px solid var(--primary-color);
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: var(--primary-color);
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: var(--font-weight-bold);
}

.profile-info h1 {
    color: var(--dark-gray);
    margin-bottom: var(--spacing-md);
}

.profile-meta {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.user-type {
    display: inline-flex;
    align-items: center;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    font-weight: var(--font-weight-medium);
}

.user-type.chauffeur {
    background: var(--primary-color);
    color: var(--white);
}

.user-type.passager {
    background: var(--info-color);
    color: var(--white);
}

.user-rating {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: 0.9rem;
}

.rating-stars {
    color: var(--secondary-color);
}

.rating-count {
    color: var(--gray);
}

.join-date {
    color: var(--gray);
    font-size: 0.9rem;
}

/* Statistiques */
.profile-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

.stat-card {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    border-left: 4px solid var(--primary-color);
    display: flex;
    align-items: center;
    gap: var(--spacing-lg);
}

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.stat-number {
    font-size: 2rem;
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
    line-height: 1;
}

.stat-label {
    color: var(--gray);
    font-size: 0.9rem;
    margin-top: var(--spacing-xs);
}

/* Onglets */
.profile-tabs {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
}

.tab-navigation {
    display: flex;
    background: var(--light-gray);
    border-bottom: 1px solid #e9ecef;
}

.tab-btn {
    flex: 1;
    padding: var(--spacing-lg);
    border: none;
    background: none;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    color: var(--gray);
    font-weight: var(--font-weight-medium);
}

.tab-btn:hover {
    background: var(--white);
    color: var(--primary-color);
}

.tab-btn.active {
    background: var(--white);
    color: var(--primary-color);
    border-bottom: 3px solid var(--primary-color);
}

.tab-icon {
    font-size: 1.2rem;
}

.tab-content {
    display: none;
    padding: var(--spacing-xl);
}

.tab-content.active {
    display: block;
}

/* Formulaires */
.form-section {
    margin-bottom: var(--spacing-xxl);
}

.form-section h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-sm);
    border-bottom: 2px solid var(--light-gray);
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

.form-control:disabled {
    background: var(--light-gray);
    color: var(--gray);
}

/* Radio buttons */
.radio-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.radio-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
}

.radio-label:hover {
    border-color: var(--primary-color);
    background: rgba(0, 133, 62, 0.05);
}

.radio-label input[type="radio"] {
    display: none;
}

.radio-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.radio-label input[type="radio"]:checked + .radio-custom {
    border-color: var(--primary-color);
    background: var(--primary-color);
}

.radio-label input[type="radio"]:checked + .radio-custom::before {
    content: '';
    width: 8px;
    height: 8px;
    background: var(--white);
    border-radius: 50%;
}

.radio-label input[type="radio"]:checked ~ .radio-content {
    color: var(--primary-color);
}

.radio-content {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.radio-content small {
    color: var(--gray);
}

/* Sécurité */
.security-info {
    margin-top: var(--spacing-xxl);
    padding-top: var(--spacing-xl);
    border-top: 2px solid var(--light-gray);
}

.security-items {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.security-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.security-icon {
    font-size: 1.5rem;
}

.security-content {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.verified {
    color: var(--success-color);
}

.not-verified {
    color: var(--danger-color);
}

/* Préférences */
.preferences-section {
    margin-bottom: var(--spacing-xxl);
}

.preference-items {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.preference-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-lg);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.preference-content {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.preference-content small {
    color: var(--gray);
}

/* Switch */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 25px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 25px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 19px;
    width: 19px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--primary-color);
}

input:checked + .slider:before {
    transform: translateX(25px);
}

/* Zone de danger */
.danger-zone {
    padding: var(--spacing-xl);
    border: 2px solid var(--danger-color);
    border-radius: var(--border-radius);
    background: #fff5f5;
}

.danger-zone h4 {
    color: var(--danger-color);
    margin-bottom: var(--spacing-md);
}

.danger-actions {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-lg);
}

/* Alertes */
.alert {
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive */
@media (max-width: 767px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
        gap: var(--spacing-lg);
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
    }
    
    .tab-navigation {
        flex-direction: column;
    }
    
    .tab-btn {
        text-align: left;
        justify-content: flex-start;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: var(--spacing-md);
    }
    
    .danger-actions {
        flex-direction: column;
    }
    
    .preference-item {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-md);
    }
}
</style>

<script>
// Script spécifique au profil
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initPasswordConfirmation();
});

function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Réinitialiser tous les onglets
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Activer l'onglet sélectionné
            this.classList.add('active');
            document.getElementById(`tab-${targetTab}`).classList.add('active');
        });
    });
}

function initPasswordConfirmation() {
    const newPasswordInput = document.getElementById('nouveau_mot_de_passe');
    const confirmPasswordInput = document.getElementById('confirmer_mot_de_passe');
    
    if (newPasswordInput && confirmPasswordInput) {
        function checkPasswordMatch() {
            if (confirmPasswordInput.value && newPasswordInput.value !== confirmPasswordInput.value) {
                showFieldError(confirmPasswordInput, 'Les mots de passe ne correspondent pas');
            } else {
                clearFieldError(confirmPasswordInput);
            }
        }
        
        newPasswordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
}

function confirmAccountDeactivation() {
    if (confirm('Êtes-vous sûr de vouloir désactiver votre compte ? Vous pourrez le réactiver en vous reconnectant.')) {
        // Implémenter la désactivation
        showNotification('Fonctionnalité en cours de développement', 'info');
    }
}

function confirmAccountDeletion() {
    if (confirm('ATTENTION : Cette action est irréversible ! Voulez-vous vraiment supprimer définitivement votre compte et toutes vos données ?')) {
        if (confirm('Dernière confirmation : Tapez "SUPPRIMER" pour confirmer')) {
            // Implémenter la suppression
            showNotification('Fonctionnalité en cours de développement', 'info');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>