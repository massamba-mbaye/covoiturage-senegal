<?php
// Page de réservation
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Réserver un trajet";
$page_description = "Finalisez votre réservation de covoiturage.";

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    redirectWithError('../pages/connexion.php', 'Vous devez être connecté pour réserver un trajet.');
}

$user = getCurrentUser();
$errors = [];
$success = false;

// Récupérer l'ID du trajet
$trajet_id = isset($_POST['trajet_id']) ? (int)$_POST['trajet_id'] : (isset($_GET['trajet_id']) ? (int)$_GET['trajet_id'] : 0);

if (!$trajet_id) {
    redirectWithError('../pages/recherche.php', 'Trajet introuvable.');
}

// Récupérer les détails du trajet
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u.nom as chauffeur_nom, u.prenom as chauffeur_prenom, 
               u.telephone as chauffeur_telephone
        FROM trajets t 
        JOIN users u ON t.chauffeur_id = u.id 
        WHERE t.id = ? AND t.statut = 'actif'
    ");
    $stmt->execute([$trajet_id]);
    $trajet = $stmt->fetch();
    
    if (!$trajet) {
        redirectWithError('../pages/recherche.php', 'Trajet introuvable ou non disponible.');
    }
    
    // Vérifications de sécurité
    if ($trajet['chauffeur_id'] == $user['id']) {
        redirectWithError('../pages/trajet-details.php?id=' . $trajet_id, 'Vous ne pouvez pas réserver votre propre trajet.');
    }
    
    if ($trajet['places_disponibles'] <= 0) {
        redirectWithError('../pages/trajet-details.php?id=' . $trajet_id, 'Ce trajet est complet.');
    }
    
    if (strtotime($trajet['date_trajet'] . ' ' . $trajet['heure_depart']) <= time()) {
        redirectWithError('../pages/trajet-details.php?id=' . $trajet_id, 'Ce trajet est déjà passé.');
    }
    
    // Vérifier si l'utilisateur a déjà réservé ce trajet
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE trajet_id = ? AND passager_id = ? AND statut IN ('en_attente', 'confirmee')");
    $stmt->execute([$trajet_id, $user['id']]);
    $existing_reservation = $stmt->fetch();
    
    if ($existing_reservation) {
        redirectWithError('../pages/mes-trajets.php', 'Vous avez déjà réservé ce trajet.');
    }
    
} catch(PDOException $e) {
    redirectWithError('../pages/recherche.php', 'Erreur lors du chargement du trajet.');
}

// Traitement du formulaire de réservation
if ($_POST && isset($_POST['confirmer_reservation'])) {
    $nombre_places = (int)$_POST['nombre_places'];
    $message_passager = isset($_POST['message_passager']) ? secure($_POST['message_passager']) : '';
    $mode_paiement = isset($_POST['mode_paiement']) ? secure($_POST['mode_paiement']) : 'especes';
    $accepter_conditions = isset($_POST['accepter_conditions']);
    
    // Validation
    if ($nombre_places < 1 || $nombre_places > min($trajet['places_disponibles'], 4)) {
        $errors['nombre_places'] = "Nombre de places invalide.";
    }
    
    if (!in_array($mode_paiement, ['especes', 'orange_money', 'wave', 'virement'])) {
        $errors['mode_paiement'] = "Mode de paiement invalide.";
    }
    
    if (!$accepter_conditions) {
        $errors['accepter_conditions'] = "Vous devez accepter les conditions de réservation.";
    }
    
    // Vérifier à nouveau la disponibilité (race condition)
    $stmt = $pdo->prepare("SELECT places_disponibles FROM trajets WHERE id = ? AND statut = 'actif'");
    $stmt->execute([$trajet_id]);
    $current_trajet = $stmt->fetch();
    
    if (!$current_trajet || $current_trajet['places_disponibles'] < $nombre_places) {
        $errors['general'] = "Plus assez de places disponibles. Veuillez actualiser la page.";
    }
    
    // Créer la réservation
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $prix_total = $nombre_places * $trajet['prix_par_place'];
            
            // Insérer la réservation
            $stmt = $pdo->prepare("
                INSERT INTO reservations (
                    trajet_id, passager_id, nombre_places, prix_total, 
                    message_passager, mode_paiement, date_reservation
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $trajet_id,
                $user['id'],
                $nombre_places,
                $prix_total,
                $message_passager ?: null,
                $mode_paiement
            ]);
            
            $reservation_id = $pdo->lastInsertId();
            
            // Mettre à jour les places disponibles
            $stmt = $pdo->prepare("UPDATE trajets SET places_disponibles = places_disponibles - ? WHERE id = ?");
            $stmt->execute([$nombre_places, $trajet_id]);
            
            $pdo->commit();
            
            // Log de l'action
            logUserAction($user['id'], 'nouvelle_reservation', "Réservation ID: $reservation_id, Trajet ID: $trajet_id");
            
            // Notifications (simulation)
            sendSMSNotification(
                $trajet['chauffeur_telephone'], 
                "Nouvelle réservation pour votre trajet {$trajet['ville_depart']} - {$trajet['ville_destination']} le " . formatDateFr($trajet['date_trajet'])
            );
            
            $success = true;
            
        } catch(PDOException $e) {
            $pdo->rollback();
            $errors['general'] = "Erreur lors de la réservation. Veuillez réessayer.";
            if (DEBUG) {
                $errors['general'] .= " " . $e->getMessage();
            }
        }
    }
}

// Valeurs par défaut pour le formulaire
$nombre_places_default = isset($_POST['nombre_places']) ? (int)$_POST['nombre_places'] : 1;
$message_default = isset($_POST['message_passager']) ? $_POST['message_passager'] : '';
$mode_paiement_default = isset($_POST['mode_paiement']) ? $_POST['mode_paiement'] : 'especes';

include '../includes/header.php';
?>

<div class="container">
    <div class="reservation-page">
        <!-- Breadcrumb -->
        <nav class="breadcrumb" data-animate="fade-in">
            <a href="../">Accueil</a>
            <span class="breadcrumb-separator">›</span>
            <a href="recherche.php">Recherche</a>
            <span class="breadcrumb-separator">›</span>
            <a href="trajet-details.php?id=<?php echo $trajet_id; ?>">Détails du trajet</a>
            <span class="breadcrumb-separator">›</span>
            <span class="breadcrumb-current">Réservation</span>
        </nav>
        
        <?php if ($success): ?>
            <!-- Confirmation de réservation -->
            <div class="success-container" data-animate="fade-in">
                <div class="success-icon">✅</div>
                <h1>Réservation confirmée !</h1>
                <p class="success-message">
                    Votre réservation a été enregistrée avec succès. Le chauffeur va examiner votre demande 
                    et vous contacter rapidement.
                </p>
                
                <div class="reservation-summary">
                    <h3>📋 Récapitulatif de votre réservation</h3>
                    <div class="summary-details">
                        <div class="summary-item">
                            <span class="summary-label">Trajet :</span>
                            <span class="summary-value">
                                <?php echo htmlspecialchars($trajet['ville_depart']); ?> → 
                                <?php echo htmlspecialchars($trajet['ville_destination']); ?>
                            </span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Date :</span>
                            <span class="summary-value"><?php echo formatDateFr($trajet['date_trajet']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Heure :</span>
                            <span class="summary-value"><?php echo date('H:i', strtotime($trajet['heure_depart'])); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Places réservées :</span>
                            <span class="summary-value"><?php echo $nombre_places_default; ?></span>
                        </div>
                        <div class="summary-item total">
                            <span class="summary-label">Total :</span>
                            <span class="summary-value"><?php echo formatPrice($nombre_places_default * $trajet['prix_par_place']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="next-steps">
                    <h3>📱 Prochaines étapes</h3>
                    <div class="steps-list">
                        <div class="step-item">
                            <span class="step-icon">1️⃣</span>
                            <span class="step-text">Le chauffeur va examiner votre demande</span>
                        </div>
                        <div class="step-item">
                            <span class="step-icon">2️⃣</span>
                            <span class="step-text">Vous recevrez une confirmation par SMS/WhatsApp</span>
                        </div>
                        <div class="step-item">
                            <span class="step-icon">3️⃣</span>
                            <span class="step-text">Contactez le chauffeur pour finaliser les détails</span>
                        </div>
                    </div>
                </div>
                
                <div class="success-actions">
                    <a href="mes-trajets.php" class="btn btn-primary">
                        📋 Voir mes réservations
                    </a>
                    
                    <a href="https://wa.me/221<?php echo $trajet['chauffeur_telephone']; ?>?text=Bonjour, je viens de réserver votre trajet <?php echo urlencode($trajet['ville_depart'] . ' - ' . $trajet['ville_destination']); ?> du <?php echo urlencode(formatDateFr($trajet['date_trajet'])); ?>. Pouvez-vous confirmer ma réservation ?" 
                       class="btn btn-outline" target="_blank">
                        📱 Contacter le chauffeur
                    </a>
                    
                    <a href="recherche.php" class="btn btn-outline">
                        🔍 Chercher d'autres trajets
                    </a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Formulaire de réservation -->
            <div class="reservation-container">
                <!-- En-tête -->
                <div class="reservation-header" data-animate="fade-in">
                    <h1>💺 Finaliser votre réservation</h1>
                    <p class="reservation-subtitle">
                        Vérifiez les détails et confirmez votre réservation
                    </p>
                </div>
                
                <!-- Résumé du trajet -->
                <div class="trajet-summary" data-animate="fade-in">
                    <h2>🚗 Détails du trajet</h2>
                    <div class="summary-content">
                        <div class="trajet-route">
                            <span class="route-start"><?php echo htmlspecialchars($trajet['ville_depart']); ?></span>
                            <span class="route-arrow">→</span>
                            <span class="route-end"><?php echo htmlspecialchars($trajet['ville_destination']); ?></span>
                        </div>
                        
                        <div class="trajet-details">
                            <div class="detail-item">
                                <span class="detail-icon">📅</span>
                                <span><?php echo formatDateFr($trajet['date_trajet']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-icon">🕐</span>
                                <span><?php echo date('H:i', strtotime($trajet['heure_depart'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-icon">👥</span>
                                <span><?php echo $trajet['places_disponibles']; ?> places disponibles</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-icon">💰</span>
                                <span><?php echo formatPrice($trajet['prix_par_place']); ?> par place</span>
                            </div>
                        </div>
                        
                        <div class="chauffeur-info">
                            <span class="chauffeur-label">Chauffeur :</span>
                            <span class="chauffeur-name">
                                <?php echo htmlspecialchars($trajet['chauffeur_prenom'] . ' ' . substr($trajet['chauffeur_nom'], 0, 1) . '.'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Formulaire -->
                <div class="reservation-form-container" data-animate="fade-in">
                    <form method="POST" class="reservation-form" data-validate>
                        <input type="hidden" name="trajet_id" value="<?php echo $trajet_id; ?>">
                        
                        <!-- Erreur générale -->
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <span class="alert-icon">⚠️</span>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Nombre de places -->
                        <div class="form-section">
                            <h3>👥 Nombre de places</h3>
                            <div class="places-selector">
                                <?php for($i = 1; $i <= min($trajet['places_disponibles'], 4); $i++): ?>
                                    <label class="place-option">
                                        <input 
                                            type="radio" 
                                            name="nombre_places" 
                                            value="<?php echo $i; ?>" 
                                            <?php echo $i == $nombre_places_default ? 'checked' : ''; ?>
                                            required
                                        >
                                        <span class="place-display">
                                            <span class="place-number"><?php echo $i; ?></span>
                                            <span class="place-label">place<?php echo $i > 1 ? 's' : ''; ?></span>
                                            <span class="place-price"><?php echo formatPrice($i * $trajet['prix_par_place']); ?></span>
                                        </span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <?php if (isset($errors['nombre_places'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['nombre_places']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Message pour le chauffeur -->
                        <div class="form-section">
                            <h3>💬 Message pour le chauffeur (optionnel)</h3>
                            <div class="form-group">
                                <textarea 
                                    name="message_passager" 
                                    class="form-control" 
                                    rows="4"
                                    placeholder="Présentez-vous, posez vos questions ou mentionnez des détails importants..."
                                ><?php echo htmlspecialchars($message_default); ?></textarea>
                                <small class="form-help">
                                    Exemples : "Bonjour, je suis étudiant à Thiès", "J'ai une valise", "Pouvez-vous passer par la gare ?"
                                </small>
                            </div>
                        </div>
                        
                        <!-- Mode de paiement -->
                        <div class="form-section">
                            <h3>💳 Mode de paiement</h3>
                            <div class="payment-options">
                                <label class="payment-option">
                                    <input 
                                        type="radio" 
                                        name="mode_paiement" 
                                        value="especes" 
                                        <?php echo $mode_paiement_default == 'especes' ? 'checked' : ''; ?>
                                    >
                                    <span class="payment-display">
                                        <span class="payment-icon">💵</span>
                                        <span class="payment-info">
                                            <strong>Espèces</strong>
                                            <small>Paiement direct au chauffeur</small>
                                        </span>
                                    </span>
                                </label>
                                
                                <label class="payment-option">
                                    <input 
                                        type="radio" 
                                        name="mode_paiement" 
                                        value="orange_money" 
                                        <?php echo $mode_paiement_default == 'orange_money' ? 'checked' : ''; ?>
                                    >
                                    <span class="payment-display">
                                        <span class="payment-icon">📱</span>
                                        <span class="payment-info">
                                            <strong>Orange Money</strong>
                                            <small>Transfert mobile sécurisé</small>
                                        </span>
                                    </span>
                                </label>
                                
                                <label class="payment-option">
                                    <input 
                                        type="radio" 
                                        name="mode_paiement" 
                                        value="wave" 
                                        <?php echo $mode_paiement_default == 'wave' ? 'checked' : ''; ?>
                                    >
                                    <span class="payment-display">
                                        <span class="payment-icon">🌊</span>
                                        <span class="payment-info">
                                            <strong>Wave</strong>
                                            <small>Paiement mobile Wave</small>
                                        </span>
                                    </span>
                                </label>
                                
                                <label class="payment-option">
                                    <input 
                                        type="radio" 
                                        name="mode_paiement" 
                                        value="virement" 
                                        <?php echo $mode_paiement_default == 'virement' ? 'checked' : ''; ?>
                                    >
                                    <span class="payment-display">
                                        <span class="payment-icon">🏦</span>
                                        <span class="payment-info">
                                            <strong>Virement bancaire</strong>
                                            <small>Transfert via banque</small>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <?php if (isset($errors['mode_paiement'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['mode_paiement']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Récapitulatif -->
                        <div class="form-section">
                            <div class="reservation-recap">
                                <h3>📋 Récapitulatif</h3>
                                <div class="recap-content">
                                    <div class="recap-row">
                                        <span>Prix par place :</span>
                                        <span><?php echo formatPrice($trajet['prix_par_place']); ?></span>
                                    </div>
                                    <div class="recap-row">
                                        <span>Nombre de places :</span>
                                        <span id="recap-places">1</span>
                                    </div>
                                    <div class="recap-row total">
                                        <span><strong>Total à payer :</strong></span>
                                        <span id="recap-total"><strong><?php echo formatPrice($trajet['prix_par_place']); ?></strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Conditions -->
                        <div class="form-section">
                            <div class="conditions-section">
                                <label class="checkbox-label">
                                    <input 
                                        type="checkbox" 
                                        name="accepter_conditions" 
                                        required
                                    >
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">
                                        J'accepte les <a href="../pages/conditions-reservation.php" target="_blank">conditions de réservation</a> 
                                        et je comprends que le paiement se fait directement avec le chauffeur
                                    </span>
                                </label>
                                <?php if (isset($errors['accepter_conditions'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($errors['accepter_conditions']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Boutons d'action -->
                        <div class="form-actions">
                            <a href="trajet-details.php?id=<?php echo $trajet_id; ?>" class="btn btn-outline">
                                ← Retour aux détails
                            </a>
                            
                            <button type="submit" name="confirmer_reservation" class="btn btn-primary btn-lg">
                                ✅ Confirmer ma réservation
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Informations importantes -->
                <div class="important-info" data-animate="fade-in">
                    <h3>⚠️ Informations importantes</h3>
                    <ul class="info-list">
                        <li>Votre réservation sera envoyée au chauffeur pour validation</li>
                        <li>Le paiement se fait directement avec le chauffeur selon le mode choisi</li>
                        <li>Vous pouvez annuler votre réservation jusqu'à <?php echo DELAI_ANNULATION_HEURES; ?>h avant le départ</li>
                        <li>Présentez-vous à l'heure et au lieu convenus</li>
                        <li>En cas de problème, contactez notre support</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Styles spécifiques -->
<style>
.reservation-page {
    padding: var(--spacing-lg) 0;
    max-width: 800px;
    margin: 0 auto;
}

/* Breadcrumb */
.breadcrumb {
    margin-bottom: var(--spacing-lg);
    font-size: 0.9rem;
}

.breadcrumb a {
    color: var(--primary-color);
    text-decoration: none;
}

.breadcrumb-separator {
    margin: 0 var(--spacing-sm);
    color: var(--gray);
}

.breadcrumb-current {
    color: var(--gray);
}

/* Page de succès */
.success-container {
    text-align: center;
    background: var(--white);
    padding: var(--spacing-xxl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
}

.success-icon {
    font-size: 5rem;
    margin-bottom: var(--spacing-lg);
}

.success-container h1 {
    color: var(--success-color);
    margin-bottom: var(--spacing-lg);
}

.success-message {
    color: var(--gray);
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: var(--spacing-xxl);
}

.reservation-summary,
.next-steps {
    background: var(--light-gray);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-xl);
    text-align: left;
}

.reservation-summary h3,
.next-steps h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.summary-details {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-sm) 0;
    border-bottom: 1px solid #e9ecef;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item.total {
    font-size: 1.1rem;
    color: var(--primary-color);
    border-top: 2px solid var(--primary-color);
    padding-top: var(--spacing-md);
    margin-top: var(--spacing-md);
}

.steps-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.step-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.step-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.success-actions {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
    align-items: center;
}

.success-actions .btn {
    min-width: 250px;
}

/* En-tête de réservation */
.reservation-header {
    text-align: center;
    margin-bottom: var(--spacing-xl);
}

.reservation-header h1 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
}

.reservation-subtitle {
    color: var(--gray);
    font-size: 1.1rem;
}

/* Résumé du trajet */
.trajet-summary {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: var(--spacing-xl);
}

.trajet-summary h2 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
}

.trajet-route {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-md);
    font-size: 1.8rem;
    font-weight: var(--font-weight-semibold);
    margin-bottom: var(--spacing-lg);
}

.route-arrow {
    color: var(--primary-color);
}

.trajet-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.detail-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.detail-icon {
    font-size: 1.2rem;
    opacity: 0.8;
}

.chauffeur-info {
    text-align: center;
    padding: var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.chauffeur-label {
    color: var(--gray);
    margin-right: var(--spacing-sm);
}

.chauffeur-name {
    font-weight: var(--font-weight-semibold);
    color: var(--primary-color);
}

/* Formulaire */
.reservation-form-container {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: var(--spacing-xl);
}

.form-section {
    margin-bottom: var(--spacing-xxl);
}

.form-section h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

/* Sélecteur de places */
.places-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--spacing-md);
}

.place-option {
    cursor: pointer;
}

.place-option input[type="radio"] {
    display: none;
}

.place-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--spacing-lg);
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius);
    transition: var(--transition);
    background: var(--white);
}

.place-option input[type="radio"]:checked + .place-display {
    border-color: var(--primary-color);
    background: rgba(0, 133, 62, 0.05);
}

.place-display:hover {
    border-color: var(--primary-color);
}

.place-number {
    font-size: 2rem;
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
}

.place-label {
    font-size: 0.9rem;
    color: var(--gray);
    margin-bottom: var(--spacing-sm);
}

.place-price {
    font-weight: var(--font-weight-semibold);
    color: var(--dark-gray);
}

/* Options de paiement */
.payment-options {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.payment-option {
    cursor: pointer;
}

.payment-option input[type="radio"] {
    display: none;
}

.payment-display {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-lg);
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius);
    transition: var(--transition);
    background: var(--white);
}

.payment-option input[type="radio"]:checked + .payment-display {
    border-color: var(--primary-color);
    background: rgba(0, 133, 62, 0.05);
}

.payment-display:hover {
    border-color: var(--primary-color);
}

.payment-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.payment-info {
    display: flex;
    flex-direction: column;
}

.payment-info strong {
    color: var(--dark-gray);
}

.payment-info small {
    color: var(--gray);
}

/* Récapitulatif */
.reservation-recap {
    background: var(--light-gray);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
}

.reservation-recap h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
    text-align: center;
}

.recap-content {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.recap-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-sm) 0;
}

.recap-row.total {
    border-top: 2px solid var(--primary-color);
    padding-top: var(--spacing-md);
    margin-top: var(--spacing-md);
    color: var(--primary-color);
    font-size: 1.1rem;
}

/* Conditions */
.conditions-section {
    background: var(--light-gray);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
}

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

.checkbox-text a {
    color: var(--primary-color);
    text-decoration: underline;
}

/* Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--spacing-md);
    margin-top: var(--spacing-xxl);
}

/* Informations importantes */
.important-info {
    background: #fff3cd;
    border: 1px solid #ffeb9c;
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--warning-color);
}

.important-info h3 {
    color: #856404;
    margin-bottom: var(--spacing-md);
}

.info-list {
    color: #856404;
    line-height: 1.6;
}

.info-list li {
    margin-bottom: var(--spacing-sm);
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

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive */
@media (max-width: 767px) {
    .trajet-route {
        font-size: 1.5rem;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .trajet-details {
        grid-template-columns: 1fr;
    }
    
    .places-selector {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .success-actions .btn {
        width: 100%;
        min-width: auto;
    }
}
</style>

<script>
// Script spécifique à la réservation
document.addEventListener('DOMContentLoaded', function() {
    initPriceCalculator();
    initFormValidation();
});

function initPriceCalculator() {
    const placesInputs = document.querySelectorAll('input[name="nombre_places"]');
    const recapPlaces = document.getElementById('recap-places');
    const recapTotal = document.getElementById('recap-total');
    const prixParPlace = <?php echo $trajet['prix_par_place']; ?>;
    
    placesInputs.forEach(input => {
        input.addEventListener('change', function() {
            const nbPlaces = parseInt(this.value);
            const total = nbPlaces * prixParPlace;
            
            if (recapPlaces) recapPlaces.textContent = nbPlaces;
            if (recapTotal) recapTotal.innerHTML = `<strong>${formatPrice(total)}</strong>`;
        });
    });
}

function initFormValidation() {
    const form = document.querySelector('.reservation-form');
    if (form) {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            setButtonLoading(submitBtn, true);
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>