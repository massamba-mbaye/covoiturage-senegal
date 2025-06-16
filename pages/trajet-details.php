<?php
// Page de détails d'un trajet
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Récupérer l'ID du trajet
$trajet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$trajet_id) {
    redirectWithError('../pages/recherche.php', 'Trajet introuvable.');
}

// Récupérer les détails du trajet
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u.nom as chauffeur_nom, u.prenom as chauffeur_prenom, 
               u.telephone as chauffeur_telephone, u.email as chauffeur_email,
               u.note_moyenne as chauffeur_note, u.nombre_evaluations,
               u.photo_profil, u.date_inscription as chauffeur_depuis,
               u.ville as chauffeur_ville
        FROM trajets t 
        JOIN users u ON t.chauffeur_id = u.id 
        WHERE t.id = ? AND t.statut IN ('actif', 'complet', 'termine')
    ");
    $stmt->execute([$trajet_id]);
    $trajet = $stmt->fetch();
    
    if (!$trajet) {
        redirectWithError('../pages/recherche.php', 'Trajet introuvable ou non disponible.');
    }
    
    // Récupérer les réservations confirmées pour ce trajet
    $stmt = $pdo->prepare("
        SELECT r.*, u.prenom, u.nom 
        FROM reservations r 
        JOIN users u ON r.passager_id = u.id 
        WHERE r.trajet_id = ? AND r.statut = 'confirmee'
        ORDER BY r.date_reservation
    ");
    $stmt->execute([$trajet_id]);
    $reservations = $stmt->fetchAll();
    
    // Récupérer les évaluations du chauffeur
    $stmt = $pdo->prepare("
        SELECT e.*, u.prenom, u.nom 
        FROM evaluations e 
        JOIN users u ON e.evaluateur_id = u.id 
        WHERE e.evalue_id = ? AND e.type_evaluation = 'passager_vers_chauffeur'
        ORDER BY e.date_evaluation DESC 
        LIMIT 5
    ");
    $stmt->execute([$trajet['chauffeur_id']]);
    $evaluations = $stmt->fetchAll();
    
} catch(PDOException $e) {
    redirectWithError('../pages/recherche.php', 'Erreur lors du chargement du trajet.');
}

$page_title = $trajet['ville_depart'] . ' → ' . $trajet['ville_destination'];
$page_description = "Trajet en covoiturage de " . $trajet['ville_depart'] . " vers " . $trajet['ville_destination'] . " le " . formatDateFr($trajet['date_trajet']);

// Vérifier si l'utilisateur peut réserver
$peut_reserver = false;
$message_reservation = '';
$user_is_chauffeur = false;

if (isLoggedIn()) {
    $user = getCurrentUser();
    
    // Vérifier si c'est le chauffeur du trajet
    if ($user['id'] == $trajet['chauffeur_id']) {
        $user_is_chauffeur = true;
        $message_reservation = 'Vous êtes le chauffeur de ce trajet.';
    }
    // Vérifier si l'utilisateur a déjà réservé ce trajet
    elseif (!$user_is_chauffeur) {
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE trajet_id = ? AND passager_id = ? AND statut IN ('en_attente', 'confirmee')");
        $stmt->execute([$trajet_id, $user['id']]);
        $existing_reservation = $stmt->fetch();
        
        if ($existing_reservation) {
            $message_reservation = 'Vous avez déjà réservé ce trajet.';
        } elseif ($trajet['places_disponibles'] <= 0) {
            $message_reservation = 'Ce trajet est complet.';
        } elseif ($trajet['statut'] !== 'actif') {
            $message_reservation = 'Ce trajet n\'est plus disponible à la réservation.';
        } elseif (strtotime($trajet['date_trajet'] . ' ' . $trajet['heure_depart']) <= time()) {
            $message_reservation = 'Ce trajet est déjà passé.';
        } else {
            $peut_reserver = true;
        }
    }
} else {
    $message_reservation = 'Connectez-vous pour réserver ce trajet.';
}

include '../includes/header.php';
?>

<div class="container">
    <div class="trajet-details-page">
        <!-- Breadcrumb -->
        <nav class="breadcrumb" data-animate="fade-in">
            <a href="../">Accueil</a>
            <span class="breadcrumb-separator">›</span>
            <a href="recherche.php">Recherche</a>
            <span class="breadcrumb-separator">›</span>
            <span class="breadcrumb-current">Détails du trajet</span>
        </nav>
        
        <!-- En-tête du trajet -->
        <div class="trajet-header" data-animate="fade-in">
            <div class="trajet-route">
                <div class="route-info">
                    <h1>
                        <span class="route-start"><?php echo htmlspecialchars($trajet['ville_depart']); ?></span>
                        <span class="route-arrow">→</span>
                        <span class="route-end"><?php echo htmlspecialchars($trajet['ville_destination']); ?></span>
                    </h1>
                    <div class="route-details">
                        <?php if (!empty($trajet['point_depart_precis'])): ?>
                            <span class="route-detail">📍 Départ : <?php echo htmlspecialchars($trajet['point_depart_precis']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($trajet['point_arrivee_precis'])): ?>
                            <span class="route-detail">🎯 Arrivée : <?php echo htmlspecialchars($trajet['point_arrivee_precis']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="trajet-status">
                    <span class="status-badge status-<?php echo $trajet['statut']; ?>">
                        <?php 
                        $status_labels = [
                            'actif' => '✅ Disponible',
                            'complet' => '👥 Complet',
                            'termine' => '🏁 Terminé'
                        ];
                        echo $status_labels[$trajet['statut']] ?? $trajet['statut'];
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="trajet-content">
            <!-- Informations principales -->
            <div class="main-content" data-animate="fade-in">
                <!-- Informations du voyage -->
                <div class="voyage-info">
                    <h2>📅 Informations du voyage</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-icon">📅</span>
                            <div class="info-content">
                                <span class="info-label">Date</span>
                                <span class="info-value"><?php echo formatDateFr($trajet['date_trajet']); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-icon">🕐</span>
                            <div class="info-content">
                                <span class="info-label">Heure de départ</span>
                                <span class="info-value"><?php echo date('H:i', strtotime($trajet['heure_depart'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-icon">💰</span>
                            <div class="info-content">
                                <span class="info-label">Prix par place</span>
                                <span class="info-value price"><?php echo formatPrice($trajet['prix_par_place']); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-icon">👥</span>
                            <div class="info-content">
                                <span class="info-label">Places disponibles</span>
                                <span class="info-value"><?php echo $trajet['places_disponibles']; ?> / <?php echo $trajet['places_totales']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Informations du véhicule -->
                <?php if (!empty($trajet['voiture_marque']) || !empty($trajet['voiture_modele'])): ?>
                <div class="vehicule-info">
                    <h2>🚗 Véhicule</h2>
                    <div class="vehicule-details">
                        <?php if (!empty($trajet['voiture_marque']) && !empty($trajet['voiture_modele'])): ?>
                            <span class="vehicule-item">
                                <span class="vehicule-icon">🚙</span>
                                <?php echo htmlspecialchars($trajet['voiture_marque'] . ' ' . $trajet['voiture_modele']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($trajet['voiture_couleur'])): ?>
                            <span class="vehicule-item">
                                <span class="vehicule-icon">🎨</span>
                                <?php echo htmlspecialchars($trajet['voiture_couleur']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($trajet['numero_plaque'])): ?>
                            <span class="vehicule-item">
                                <span class="vehicule-icon">🔢</span>
                                <?php echo htmlspecialchars($trajet['numero_plaque']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Description -->
                <?php if (!empty($trajet['description'])): ?>
                <div class="description-info">
                    <h2>📝 Description</h2>
                    <p class="description-text">
                        <?php echo nl2br(htmlspecialchars($trajet['description'])); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Passagers confirmés -->
                <?php if (!empty($reservations)): ?>
                <div class="passagers-info">
                    <h2>👥 Passagers confirmés (<?php echo count($reservations); ?>)</h2>
                    <div class="passagers-list">
                        <?php foreach ($reservations as $reservation): ?>
                            <div class="passager-item">
                                <div class="passager-avatar">
                                    <?php echo strtoupper(substr($reservation['prenom'], 0, 1)); ?>
                                </div>
                                <div class="passager-info">
                                    <span class="passager-name">
                                        <?php echo htmlspecialchars($reservation['prenom'] . ' ' . substr($reservation['nom'], 0, 1) . '.'); ?>
                                    </span>
                                    <span class="passager-places">
                                        <?php echo $reservation['nombre_places']; ?> place<?php echo $reservation['nombre_places'] > 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="sidebar" data-animate="fade-in">
                <!-- Carte de réservation -->
                <div class="reservation-card">
                    <h3>💺 Réservation</h3>
                    
                    <div class="price-display">
                        <span class="price-amount"><?php echo formatPrice($trajet['prix_par_place']); ?></span>
                        <span class="price-unit">par personne</span>
                    </div>
                    
                    <?php if ($peut_reserver): ?>
                        <form method="POST" action="reserver.php" class="reservation-form">
                            <input type="hidden" name="trajet_id" value="<?php echo $trajet['id']; ?>">
                            
                            <div class="form-group">
                                <label for="nombre_places">Nombre de places :</label>
                                <select id="nombre_places" name="nombre_places" class="form-control" required>
                                    <?php for($i = 1; $i <= min($trajet['places_disponibles'], 4); $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> place<?php echo $i > 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Message pour le chauffeur (optionnel) :</label>
                                <textarea id="message" name="message" class="form-control" rows="3" 
                                         placeholder="Présentez-vous ou posez vos questions..."></textarea>
                            </div>
                            
                            <div class="total-price">
                                <span>Total : <span id="prix-total"><?php echo formatPrice($trajet['prix_par_place']); ?></span></span>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-full">
                                📝 Réserver maintenant
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="reservation-message">
                            <p><?php echo htmlspecialchars($message_reservation); ?></p>
                            
                            <?php if (!isLoggedIn()): ?>
                                <a href="connexion.php?redirect=<?php echo urlencode('trajet-details.php?id=' . $trajet_id); ?>" 
                                   class="btn btn-primary btn-full">
                                    🔑 Se connecter pour réserver
                                </a>
                            <?php elseif (!$user_is_chauffeur && $trajet['statut'] === 'actif'): ?>
                                <a href="https://wa.me/221<?php echo $trajet['chauffeur_telephone']; ?>?text=Bonjour, je suis intéressé par votre trajet <?php echo urlencode($trajet['ville_depart'] . ' - ' . $trajet['ville_destination']); ?> du <?php echo urlencode(formatDateFr($trajet['date_trajet'])); ?>" 
                                   class="btn btn-outline btn-full" target="_blank">
                                    📱 Contacter via WhatsApp
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Informations du chauffeur -->
                <div class="chauffeur-card">
                    <h3>🚗 Votre chauffeur</h3>
                    
                    <div class="chauffeur-profile">
                        <div class="chauffeur-avatar">
                            <?php if (!empty($trajet['photo_profil'])): ?>
                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($trajet['photo_profil']); ?>" alt="Photo de profil">
                            <?php else: ?>
                                <span class="avatar-placeholder">
                                    <?php echo strtoupper(substr($trajet['chauffeur_prenom'], 0, 1) . substr($trajet['chauffeur_nom'], 0, 1)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="chauffeur-info">
                            <h4><?php echo htmlspecialchars($trajet['chauffeur_prenom'] . ' ' . substr($trajet['chauffeur_nom'], 0, 1) . '.'); ?></h4>
                            
                            <?php if ($trajet['chauffeur_note'] > 0): ?>
                                <div class="chauffeur-rating">
                                    <span class="rating-stars">
                                        <?php 
                                        $note = round($trajet['chauffeur_note']);
                                        for($i = 1; $i <= 5; $i++) {
                                            echo $i <= $note ? '⭐' : '☆';
                                        }
                                        ?>
                                    </span>
                                    <span class="rating-value"><?php echo number_format($trajet['chauffeur_note'], 1); ?></span>
                                    <span class="rating-count">(<?php echo $trajet['nombre_evaluations']; ?>)</span>
                                </div>
                            <?php else: ?>
                                <span class="no-rating">Nouveau chauffeur</span>
                            <?php endif; ?>
                            
                            <div class="chauffeur-details">
                                <?php if (!empty($trajet['chauffeur_ville'])): ?>
                                    <span class="detail-item">📍 <?php echo htmlspecialchars($trajet['chauffeur_ville']); ?></span>
                                <?php endif; ?>
                                <span class="detail-item">📅 Membre depuis <?php echo formatDateFr($trajet['chauffeur_depuis']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$user_is_chauffeur): ?>
                        <div class="contact-actions">
                            <a href="https://wa.me/221<?php echo $trajet['chauffeur_telephone']; ?>?text=Bonjour, je vous contacte concernant votre trajet <?php echo urlencode($trajet['ville_depart'] . ' - ' . $trajet['ville_destination']); ?> du <?php echo urlencode(formatDateFr($trajet['date_trajet'])); ?>" 
                               class="btn btn-outline btn-sm" target="_blank">
                                📱 WhatsApp
                            </a>
                            
                            <?php if (!empty($trajet['chauffeur_email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($trajet['chauffeur_email']); ?>?subject=Trajet <?php echo urlencode($trajet['ville_depart'] . ' - ' . $trajet['ville_destination']); ?>" 
                                   class="btn btn-outline btn-sm">
                                    📧 Email
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Évaluations -->
                <?php if (!empty($evaluations)): ?>
                <div class="evaluations-card">
                    <h3>⭐ Avis des passagers</h3>
                    
                    <div class="evaluations-list">
                        <?php foreach ($evaluations as $evaluation): ?>
                            <div class="evaluation-item">
                                <div class="evaluation-header">
                                    <span class="evaluateur-name">
                                        <?php echo htmlspecialchars($evaluation['prenom'] . ' ' . substr($evaluation['nom'], 0, 1) . '.'); ?>
                                    </span>
                                    <span class="evaluation-note">
                                        <?php 
                                        for($i = 1; $i <= 5; $i++) {
                                            echo $i <= $evaluation['note'] ? '⭐' : '☆';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <?php if (!empty($evaluation['commentaire'])): ?>
                                    <p class="evaluation-commentaire">
                                        "<?php echo htmlspecialchars($evaluation['commentaire']); ?>"
                                    </p>
                                <?php endif; ?>
                                <span class="evaluation-date">
                                    <?php echo formatDateFr($evaluation['date_evaluation']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Actions supplémentaires -->
                <div class="actions-card">
                    <button class="btn btn-outline btn-sm btn-full" onclick="shareTrajet(<?php echo $trajet['id']; ?>, '<?php echo htmlspecialchars($trajet['ville_depart'] . ' - ' . $trajet['ville_destination']); ?>')">
                        📤 Partager ce trajet
                    </button>
                    
                    <?php if (isLoggedIn() && !$user_is_chauffeur): ?>
                        <button class="btn btn-outline btn-sm btn-full" onclick="signaler(<?php echo $trajet['id']; ?>)">
                            🚨 Signaler un problème
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Trajets similaires -->
        <div class="similar-trajets" data-animate="fade-in">
            <h2>🔍 Autres trajets similaires</h2>
            <div class="similar-trajets-container">
                <!-- Les trajets similaires seraient chargés ici via AJAX -->
                <p class="loading-text">Chargement des trajets similaires...</p>
            </div>
        </div>
    </div>
</div>

<!-- Styles spécifiques -->
<style>
.trajet-details-page {
    padding: var(--spacing-lg) 0;
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

/* En-tête du trajet */
.trajet-header {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    margin-bottom: var(--spacing-xl);
}

.trajet-route {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.route-info h1 {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
    font-size: 2.5rem;
    color: var(--dark-gray);
}

.route-arrow {
    color: var(--primary-color);
    font-weight: bold;
}

.route-details {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.route-detail {
    color: var(--gray);
    font-size: 0.9rem;
}

/* Contenu principal */
.trajet-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
}

/* Informations principales */
.main-content > div {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: var(--spacing-lg);
}

.main-content h2 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
}

.info-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.info-icon {
    font-size: 1.5rem;
    opacity: 0.8;
}

.info-content {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 0.8rem;
    color: var(--gray);
}

.info-value {
    font-weight: var(--font-weight-semibold);
    color: var(--dark-gray);
}

.info-value.price {
    color: var(--primary-color);
    font-size: 1.1rem;
}

/* Véhicule */
.vehicule-details {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.vehicule-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
    font-size: 0.9rem;
}

.vehicule-icon {
    opacity: 0.8;
}

/* Description */
.description-text {
    line-height: 1.7;
    color: var(--gray);
}

/* Passagers */
.passagers-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.passager-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.passager-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-color);
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: var(--font-weight-bold);
}

.passager-name {
    font-weight: var(--font-weight-medium);
    color: var(--dark-gray);
}

.passager-places {
    font-size: 0.8rem;
    color: var(--gray);
}

/* Sidebar */
.sidebar > div {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: var(--spacing-lg);
}

.sidebar h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

/* Carte de réservation */
.price-display {
    text-align: center;
    margin-bottom: var(--spacing-lg);
    padding: var(--spacing-lg);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.price-amount {
    display: block;
    font-size: 2rem;
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
}

.price-unit {
    font-size: 0.9rem;
    color: var(--gray);
}

.total-price {
    text-align: center;
    font-size: 1.1rem;
    font-weight: var(--font-weight-semibold);
    color: var(--primary-color);
    margin: var(--spacing-lg) 0;
    padding: var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.reservation-message {
    text-align: center;
    padding: var(--spacing-lg);
    background: var(--light-gray);
    border-radius: var(--border-radius);
    color: var(--gray);
}

/* Profil chauffeur */
.chauffeur-profile {
    display: flex;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.chauffeur-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--primary-color);
    flex-shrink: 0;
}

.chauffeur-avatar img {
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
    font-weight: var(--font-weight-bold);
    font-size: 1.2rem;
}

.chauffeur-info h4 {
    margin-bottom: var(--spacing-sm);
    color: var(--dark-gray);
}

.chauffeur-rating {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    margin-bottom: var(--spacing-sm);
    font-size: 0.9rem;
}

.rating-stars {
    color: var(--secondary-color);
}

.rating-count {
    color: var(--gray);
}

.no-rating {
    color: var(--gray);
    font-size: 0.9rem;
    font-style: italic;
}

.chauffeur-details {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.detail-item {
    font-size: 0.8rem;
    color: var(--gray);
}

.contact-actions {
    display: flex;
    gap: var(--spacing-sm);
}

/* Évaluations */
.evaluations-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.evaluation-item {
    padding: var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
}

.evaluation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-sm);
}

.evaluateur-name {
    font-weight: var(--font-weight-medium);
    color: var(--dark-gray);
}

.evaluation-note {
    font-size: 0.9rem;
}

.evaluation-commentaire {
    font-style: italic;
    color: var(--gray);
    margin: var(--spacing-sm) 0;
    line-height: 1.5;
}

.evaluation-date {
    font-size: 0.8rem;
    color: var(--gray);
}

/* Status badges */
.status-badge {
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius);
    font-size: 0.875rem;
    font-weight: var(--font-weight-medium);
}

.status-actif {
    background: var(--success-color);
    color: var(--white);
}

.status-complet {
    background: var(--info-color);
    color: var(--white);
}

.status-termine {
    background: var(--gray);
    color: var(--white);
}

/* Trajets similaires */
.similar-trajets {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
}

.similar-trajets h2 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
}

.loading-text {
    text-align: center;
    color: var(--gray);
    font-style: italic;
}

/* Responsive */
@media (max-width: 767px) {
    .trajet-content {
        grid-template-columns: 1fr;
    }
    
    .route-info h1 {
        font-size: 1.8rem;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .trajet-route {
        flex-direction: column;
        gap: var(--spacing-md);
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .vehicule-details {
        flex-direction: column;
    }
    
    .chauffeur-profile {
        flex-direction: column;
        text-align: center;
    }
    
    .contact-actions {
        flex-direction: column;
    }
}
</style>

<script>
// Script spécifique aux détails de trajet
document.addEventListener('DOMContentLoaded', function() {
    initPriceCalculator();
    loadSimilarTrajets();
});

function initPriceCalculator() {
    const placesSelect = document.getElementById('nombre_places');
    const prixTotal = document.getElementById('prix-total');
    
    if (placesSelect && prixTotal) {
        const prixParPlace = <?php echo $trajet['prix_par_place']; ?>;
        
        placesSelect.addEventListener('change', function() {
            const nbPlaces = parseInt(this.value);
            const total = nbPlaces * prixParPlace;
            prixTotal.textContent = formatPrice(total);
        });
    }
}

function loadSimilarTrajets() {
    // Simulation du chargement de trajets similaires
    setTimeout(() => {
        const container = document.querySelector('.similar-trajets-container');
        if (container) {
            container.innerHTML = '<p style="text-align: center; color: var(--gray);">Aucun trajet similaire trouvé pour le moment.</p>';
        }
    }, 1500);
}

function signaler(trajetId) {
    if (confirm('Voulez-vous signaler un problème avec ce trajet ?')) {
        // Rediriger vers la page de signalement
        window.location.href = `signaler.php?trajet_id=${trajetId}`;
    }
}
</script>

<?php include '../includes/footer.php'; ?>