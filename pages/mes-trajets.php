<?php
// Page de gestion des trajets
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Mes Trajets";
$page_description = "Gérez vos trajets et réservations sur Covoiturage Sénégal.";

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    redirectWithError('../pages/connexion.php', 'Vous devez être connecté pour accéder à vos trajets.');
}

$user = getCurrentUser();
$user_type = $user['type_utilisateur'];

// Traitement des actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $trajet_id = (int)($_POST['trajet_id'] ?? 0);
    
    if ($action === 'annuler_trajet' && $user_type === 'chauffeur') {
        // Annuler un trajet
        try {
            // Vérifier que le trajet appartient à l'utilisateur
            $stmt = $pdo->prepare("SELECT * FROM trajets WHERE id = ? AND chauffeur_id = ? AND statut = 'actif'");
            $stmt->execute([$trajet_id, $user['id']]);
            $trajet = $stmt->fetch();
            
            if ($trajet) {
                // Vérifier s'il y a des réservations confirmées
                $stmt = $pdo->prepare("SELECT COUNT(*) as nb_reservations FROM reservations WHERE trajet_id = ? AND statut = 'confirmee'");
                $stmt->execute([$trajet_id]);
                $nb_reservations = $stmt->fetch()['nb_reservations'];
                
                if ($nb_reservations > 0) {
                    setFlashMessage("Impossible d'annuler ce trajet car il y a des réservations confirmées. Contactez d'abord vos passagers.", 'error');
                } else {
                    // Annuler le trajet
                    $stmt = $pdo->prepare("UPDATE trajets SET statut = 'annule' WHERE id = ?");
                    $stmt->execute([$trajet_id]);
                    
                    // Annuler les réservations en attente
                    $stmt = $pdo->prepare("UPDATE reservations SET statut = 'annulee' WHERE trajet_id = ? AND statut = 'en_attente'");
                    $stmt->execute([$trajet_id]);
                    
                    logUserAction($user['id'], 'annulation_trajet', "Trajet ID: $trajet_id");
                    setFlashMessage("Trajet annulé avec succès.", 'success');
                }
            } else {
                setFlashMessage("Trajet introuvable ou déjà traité.", 'error');
            }
        } catch(PDOException $e) {
            setFlashMessage("Erreur lors de l'annulation du trajet.", 'error');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
    
    elseif ($action === 'annuler_reservation' && $user_type === 'passager') {
        $reservation_id = (int)($_POST['reservation_id'] ?? 0);
        
        try {
            // Vérifier que la réservation appartient à l'utilisateur
            $stmt = $pdo->prepare("
                SELECT r.*, t.date_trajet, t.heure_depart 
                FROM reservations r 
                JOIN trajets t ON r.trajet_id = t.id 
                WHERE r.id = ? AND r.passager_id = ? AND r.statut IN ('en_attente', 'confirmee')
            ");
            $stmt->execute([$reservation_id, $user['id']]);
            $reservation = $stmt->fetch();
            
            if ($reservation) {
                // Vérifier si l'annulation est encore possible
                if (canCancelReservation($reservation['date_trajet'], $reservation['heure_depart'])) {
                    // Annuler la réservation
                    $stmt = $pdo->prepare("UPDATE reservations SET statut = 'annulee' WHERE id = ?");
                    $stmt->execute([$reservation_id]);
                    
                    // Libérer les places
                    if ($reservation['statut'] === 'confirmee') {
                        $stmt = $pdo->prepare("UPDATE trajets SET places_disponibles = places_disponibles + ? WHERE id = ?");
                        $stmt->execute([$reservation['nombre_places'], $reservation['trajet_id']]);
                    }
                    
                    // Notifier le chauffeur
                    $stmt = $pdo->prepare("SELECT * FROM trajets WHERE id = ?");
                    $stmt->execute([$reservation['trajet_id']]);
                    $trajet_info = $stmt->fetch();
                    if ($trajet_info) {
                        notifyReservationCancelled($trajet_info['chauffeur_id'], $trajet_info, $user['prenom'], 'by_passenger');
                    }
                    
                    logUserAction($user['id'], 'annulation_reservation', "Réservation ID: $reservation_id");
                    setFlashMessage("Réservation annulée avec succès.", 'success');
                } else {
                    setFlashMessage("Impossible d'annuler cette réservation. Délai d'annulation dépassé (" . DELAI_ANNULATION_HEURES . "h avant le départ).", 'error');
                }
            } else {
                setFlashMessage("Réservation introuvable ou déjà traitée.", 'error');
            }
        } catch(PDOException $e) {
            setFlashMessage("Erreur lors de l'annulation de la réservation.", 'error');
        }
        
        redirect($_SERVER['PHP_SELF']);
    }
}

// Récupérer les trajets selon le type d'utilisateur
$trajets = [];
$reservations = [];

try {
    if ($user_type === 'chauffeur') {
        // Trajets du chauffeur avec le nombre de réservations
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   COUNT(CASE WHEN r.statut = 'confirmee' THEN 1 END) as nb_reservations_confirmees,
                   COUNT(CASE WHEN r.statut = 'en_attente' THEN 1 END) as nb_reservations_attente,
                   GROUP_CONCAT(
                       CASE WHEN r.statut = 'confirmee' 
                       THEN CONCAT(u.prenom, ' ', LEFT(u.nom, 1), '.') 
                       END SEPARATOR ', '
                   ) as passagers_confirmes
            FROM trajets t 
            LEFT JOIN reservations r ON t.id = r.trajet_id
            LEFT JOIN users u ON r.passager_id = u.id
            WHERE t.chauffeur_id = ?
            GROUP BY t.id
            ORDER BY t.date_trajet DESC, t.heure_depart DESC
        ");
        $stmt->execute([$user['id']]);
        $trajets = $stmt->fetchAll();
        
    } else {
        // Réservations du passager
        $stmt = $pdo->prepare("
            SELECT r.*, t.*, 
                   u.nom as chauffeur_nom, u.prenom as chauffeur_prenom, 
                   u.telephone as chauffeur_telephone, u.note_moyenne as chauffeur_note
            FROM reservations r
            JOIN trajets t ON r.trajet_id = t.id
            JOIN users u ON t.chauffeur_id = u.id
            WHERE r.passager_id = ?
            ORDER BY t.date_trajet DESC, t.heure_depart DESC
        ");
        $stmt->execute([$user['id']]);
        $reservations = $stmt->fetchAll();
    }
    
} catch(PDOException $e) {
    $error_message = "Erreur lors du chargement des trajets.";
    if (DEBUG) {
        $error_message .= " " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="mes-trajets-page">
        <!-- En-tête de la page -->
        <div class="page-header" data-animate="fade-in">
            <h1>
                <?php if ($user_type === 'chauffeur'): ?>
                    🚗 Mes Trajets
                <?php else: ?>
                    🎯 Mes Réservations
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <?php if ($user_type === 'chauffeur'): ?>
                    Gérez vos trajets publiés et suivez vos réservations
                <?php else: ?>
                    Suivez vos réservations et l'historique de vos voyages
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Actions rapides -->
        <div class="quick-actions" data-animate="fade-in">
            <?php if ($user_type === 'chauffeur'): ?>
                <a href="publier.php" class="btn btn-primary">
                    <span class="btn-icon">➕</span>
                    Publier un nouveau trajet
                </a>
            <?php endif; ?>
            
            <a href="recherche.php" class="btn btn-outline">
                <span class="btn-icon">🔍</span>
                Rechercher des trajets
            </a>
            
            <a href="profil.php" class="btn btn-outline">
                <span class="btn-icon">👤</span>
                Mon profil
            </a>
        </div>
        
        <!-- Statistiques rapides -->
        <div class="quick-stats" data-animate="fade-in">
            <?php if ($user_type === 'chauffeur'): ?>
                <?php 
                $stats = [
                    'total' => count($trajets),
                    'actifs' => count(array_filter($trajets, function($t) { return $t['statut'] === 'actif' && $t['date_trajet'] >= date('Y-m-d'); })),
                    'termines' => count(array_filter($trajets, function($t) { return $t['statut'] === 'termine'; })),
                    'annules' => count(array_filter($trajets, function($t) { return $t['statut'] === 'annule'; }))
                ];
                ?>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['total']; ?></span>
                    <span class="stat-label">Total trajets</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['actifs']; ?></span>
                    <span class="stat-label">Actifs</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['termines']; ?></span>
                    <span class="stat-label">Terminés</span>
                </div>
            <?php else: ?>
                <?php 
                $stats = [
                    'total' => count($reservations),
                    'confirmees' => count(array_filter($reservations, function($r) { return $r['statut'] === 'confirmee'; })),
                    'terminees' => count(array_filter($reservations, function($r) { return $r['statut'] === 'terminee'; })),
                    'attente' => count(array_filter($reservations, function($r) { return $r['statut'] === 'en_attente'; }))
                ];
                ?>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['total']; ?></span>
                    <span class="stat-label">Total voyages</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['confirmees']; ?></span>
                    <span class="stat-label">Confirmés</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['terminees']; ?></span>
                    <span class="stat-label">Terminés</span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Filtres -->
        <div class="filters-section" data-animate="fade-in">
            <div class="filters">
                <button class="filter-btn active" data-filter="all">Tous</button>
                <button class="filter-btn" data-filter="actif">Actifs</button>
                <button class="filter-btn" data-filter="termine">Terminés</button>
                <button class="filter-btn" data-filter="annule">Annulés</button>
            </div>
        </div>
        
        <!-- Liste des trajets/réservations -->
        <?php if ($user_type === 'chauffeur'): ?>
            <!-- Trajets du chauffeur -->
            <?php if (!empty($trajets)): ?>
                <div class="trajets-list" data-animate="fade-in">
                    <?php foreach ($trajets as $trajet): ?>
                        <div class="trajet-card" data-status="<?php echo $trajet['statut']; ?>" data-animate="fade-in">
                            <!-- En-tête du trajet -->
                            <div class="trajet-header">
                                <div class="trajet-route">
                                    <span class="route-start"><?php echo htmlspecialchars($trajet['ville_depart']); ?></span>
                                    <span class="route-arrow">→</span>
                                    <span class="route-end"><?php echo htmlspecialchars($trajet['ville_destination']); ?></span>
                                </div>
                                
                                <div class="trajet-status">
                                    <span class="status-badge status-<?php echo $trajet['statut']; ?>">
                                        <?php 
                                        $status_labels = [
                                            'actif' => '✅ Actif',
                                            'complet' => '👥 Complet',
                                            'termine' => '🏁 Terminé',
                                            'annule' => '❌ Annulé'
                                        ];
                                        echo $status_labels[$trajet['statut']] ?? $trajet['statut'];
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Informations du trajet -->
                            <div class="trajet-info">
                                <div class="info-row">
                                    <div class="info-item">
                                        <span class="info-icon">📅</span>
                                        <span><?php echo formatDateFr($trajet['date_trajet']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">🕐</span>
                                        <span><?php echo date('H:i', strtotime($trajet['heure_depart'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">💰</span>
                                        <span><?php echo formatPrice($trajet['prix_par_place']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-item">
                                        <span class="info-icon">👥</span>
                                        <span>
                                            <?php echo ($trajet['places_totales'] - $trajet['places_disponibles']); ?>/<?php echo $trajet['places_totales']; ?> places réservées
                                        </span>
                                    </div>
                                    <?php if ($trajet['nb_reservations_confirmees'] > 0): ?>
                                        <div class="info-item">
                                            <span class="info-icon">✅</span>
                                            <span><?php echo $trajet['nb_reservations_confirmees']; ?> confirmée<?php echo $trajet['nb_reservations_confirmees'] > 1 ? 's' : ''; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($trajet['nb_reservations_attente'] > 0): ?>
                                        <div class="info-item">
                                            <span class="info-icon">⏳</span>
                                            <span><?php echo $trajet['nb_reservations_attente']; ?> en attente</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Passagers confirmés -->
                            <?php if (!empty($trajet['passagers_confirmes'])): ?>
                                <div class="trajet-passagers">
                                    <strong>Passagers confirmés :</strong>
                                    <?php echo htmlspecialchars($trajet['passagers_confirmes']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Description -->
                            <?php if (!empty($trajet['description'])): ?>
                                <div class="trajet-description">
                                    <?php echo htmlspecialchars($trajet['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Actions -->
                            <div class="trajet-actions">
                                <a href="trajet-details.php?id=<?php echo $trajet['id']; ?>" class="btn btn-outline btn-sm">
                                    👁️ Voir détails
                                </a>
                                
                                <?php if ($trajet['statut'] === 'actif'): ?>
                                    <a href="modifier-trajet.php?id=<?php echo $trajet['id']; ?>" class="btn btn-outline btn-sm">
                                        ✏️ Modifier
                                    </a>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Êtes-vous sûr de vouloir annuler ce trajet ?')">
                                        <input type="hidden" name="action" value="annuler_trajet">
                                        <input type="hidden" name="trajet_id" value="<?php echo $trajet['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            ❌ Annuler
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline btn-sm" onclick="shareTrajet(<?php echo $trajet['id']; ?>, '<?php echo htmlspecialchars($trajet['ville_depart'] . ' - ' . $trajet['ville_destination']); ?>')">
                                    📤 Partager
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <!-- Aucun trajet -->
                <div class="no-results" data-animate="fade-in">
                    <div class="no-results-icon">🚗</div>
                    <h3>Aucun trajet publié</h3>
                    <p>Vous n'avez pas encore publié de trajet. Commencez dès maintenant !</p>
                    <a href="publier.php" class="btn btn-primary">
                        ➕ Publier mon premier trajet
                    </a>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Réservations du passager -->
            <?php if (!empty($reservations)): ?>
                <div class="reservations-list" data-animate="fade-in">
                    <?php foreach ($reservations as $reservation): ?>
                        <div class="reservation-card" data-status="<?php echo $reservation['statut']; ?>" data-animate="fade-in">
                            <!-- En-tête de la réservation -->
                            <div class="reservation-header">
                                <div class="reservation-route">
                                    <span class="route-start"><?php echo htmlspecialchars($reservation['ville_depart']); ?></span>
                                    <span class="route-arrow">→</span>
                                    <span class="route-end"><?php echo htmlspecialchars($reservation['ville_destination']); ?></span>
                                </div>
                                
                                <div class="reservation-status">
                                    <span class="status-badge status-<?php echo $reservation['statut']; ?>">
                                        <?php 
                                        $status_labels = [
                                            'en_attente' => '⏳ En attente',
                                            'confirmee' => '✅ Confirmée',
                                            'terminee' => '🏁 Terminée',
                                            'annulee' => '❌ Annulée'
                                        ];
                                        echo $status_labels[$reservation['statut']] ?? $reservation['statut'];
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Informations de la réservation -->
                            <div class="reservation-info">
                                <div class="info-row">
                                    <div class="info-item">
                                        <span class="info-icon">📅</span>
                                        <span><?php echo formatDateFr($reservation['date_trajet']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">🕐</span>
                                        <span><?php echo date('H:i', strtotime($reservation['heure_depart'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">👥</span>
                                        <span><?php echo $reservation['nombre_places']; ?> place<?php echo $reservation['nombre_places'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">💰</span>
                                        <span><?php echo formatPrice($reservation['prix_total']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informations du chauffeur -->
                            <div class="chauffeur-info">
                                <div class="chauffeur-details">
                                    <span class="chauffeur-name">
                                        🚗 <?php echo htmlspecialchars($reservation['chauffeur_prenom'] . ' ' . substr($reservation['chauffeur_nom'], 0, 1) . '.'); ?>
                                    </span>
                                    
                                    <?php if ($reservation['chauffeur_note'] > 0): ?>
                                        <span class="chauffeur-rating">
                                            <span class="rating-stars">
                                                <?php 
                                                $note = round($reservation['chauffeur_note']);
                                                for($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $note ? '⭐' : '☆';
                                                }
                                                ?>
                                            </span>
                                            <span class="rating-value"><?php echo number_format($reservation['chauffeur_note'], 1); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="chauffeur-contact">
                                    <a href="https://wa.me/221<?php echo $reservation['chauffeur_telephone']; ?>?text=Bonjour, je vous contacte concernant notre trajet <?php echo urlencode($reservation['ville_depart'] . ' - ' . $reservation['ville_destination']); ?> du <?php echo urlencode(formatDateFr($reservation['date_trajet'])); ?>" 
                                       class="btn btn-outline btn-sm" target="_blank">
                                        📱 WhatsApp
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Message du passager -->
                            <?php if (!empty($reservation['message_passager'])): ?>
                                <div class="reservation-message">
                                    <strong>Votre message :</strong>
                                    <?php echo htmlspecialchars($reservation['message_passager']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Actions -->
                            <div class="reservation-actions">
                                <a href="trajet-details.php?id=<?php echo $reservation['trajet_id']; ?>" class="btn btn-outline btn-sm">
                                    👁️ Voir le trajet
                                </a>
                                
                                <?php if (in_array($reservation['statut'], ['en_attente', 'confirmee'])): ?>
                                    <?php if (canCancelReservation($reservation['date_trajet'], $reservation['heure_depart'])): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Êtes-vous sûr de vouloir annuler cette réservation ?')">
                                            <input type="hidden" name="action" value="annuler_reservation">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                ❌ Annuler
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn btn-sm btn-disabled" title="Délai d'annulation dépassé">
                                            ❌ Annulation impossible
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($reservation['statut'] === 'terminee'): ?>
                                    <a href="evaluer.php?reservation_id=<?php echo $reservation['id']; ?>" class="btn btn-primary btn-sm">
                                        ⭐ Évaluer
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <!-- Aucune réservation -->
                <div class="no-results" data-animate="fade-in">
                    <div class="no-results-icon">🎯</div>
                    <h3>Aucune réservation</h3>
                    <p>Vous n'avez pas encore effectué de réservation. Trouvez votre premier trajet !</p>
                    <a href="recherche.php" class="btn btn-primary">
                        🔍 Rechercher un trajet
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Styles spécifiques -->
<style>
.mes-trajets-page {
    padding: var(--spacing-xl) 0;
}

.page-header {
    text-align: center;
    margin-bottom: var(--spacing-xl);
}

.page-header h1 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
}

.page-subtitle {
    color: var(--gray);
    font-size: 1.1rem;
}

/* Actions rapides */
.quick-actions {
    display: flex;
    justify-content: center;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-xl);
    flex-wrap: wrap;
}

/* Statistiques rapides */
.quick-stats {
    display: flex;
    justify-content: center;
    gap: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    padding: var(--spacing-lg);
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    min-width: 120px;
}

.stat-number {
    display: block;
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

/* Filtres */
.filters-section {
    margin-bottom: var(--spacing-xl);
}

.filters {
    display: flex;
    justify-content: center;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

.filter-btn {
    padding: var(--spacing-sm) var(--spacing-lg);
    border: 2px solid #e9ecef;
    background: var(--white);
    color: var(--gray);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
    font-weight: var(--font-weight-medium);
}

.filter-btn:hover,
.filter-btn.active {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: var(--white);
}

/* Cartes de trajet/réservation */
.trajets-list,
.reservations-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}

.trajet-card,
.reservation-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid #e9ecef;
    padding: var(--spacing-lg);
    transition: var(--transition);
}

.trajet-card:hover,
.reservation-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

/* En-tête */
.trajet-header,
.reservation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}

.trajet-route,
.reservation-route {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: 1.5rem;
    font-weight: var(--font-weight-semibold);
}

.route-arrow {
    color: var(--primary-color);
    font-weight: bold;
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

.status-confirmee {
    background: var(--success-color);
    color: var(--white);
}

.status-en_attente {
    background: var(--warning-color);
    color: var(--dark-gray);
}

.status-complet {
    background: var(--info-color);
    color: var(--white);
}

.status-termine,
.status-terminee {
    background: var(--gray);
    color: var(--white);
}

.status-annule,
.status-annulee {
    background: var(--danger-color);
    color: var(--white);
}

/* Informations */
.trajet-info,
.reservation-info {
    margin-bottom: var(--spacing-md);
}

.info-row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-sm);
}

.info-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    color: var(--gray);
    font-size: 0.9rem;
}

.info-icon {
    font-size: 1rem;
}

/* Passagers et chauffeur */
.trajet-passagers,
.chauffeur-info {
    background: var(--light-gray);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-md);
    font-size: 0.9rem;
}

.chauffeur-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chauffeur-details {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.chauffeur-name {
    font-weight: var(--font-weight-medium);
    color: var(--dark-gray);
}

.chauffeur-rating {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: 0.8rem;
}

.rating-stars {
    color: var(--secondary-color);
}

/* Description et message */
.trajet-description,
.reservation-message {
    background: var(--light-gray);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-md);
    font-style: italic;
    color: var(--gray);
    font-size: 0.9rem;
}

/* Actions */
.trajet-actions,
.reservation-actions {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
    padding-top: var(--spacing-md);
    border-top: 1px solid #e9ecef;
}

.btn-disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

/* Aucun résultat */
.no-results {
    text-align: center;
    padding: var(--spacing-xxl);
    background: var(--light-gray);
    border-radius: var(--border-radius-lg);
}

.no-results-icon {
    font-size: 4rem;
    margin-bottom: var(--spacing-lg);
}

.no-results h3 {
    color: var(--dark-gray);
    margin-bottom: var(--spacing-md);
}

.no-results p {
    color: var(--gray);
    margin-bottom: var(--spacing-xl);
}

/* Filtrage par statut */
.trajet-card[data-status]:not([data-status="all"]),
.reservation-card[data-status]:not([data-status="all"]) {
    display: block;
}

.trajet-card.hidden,
.reservation-card.hidden {
    display: none;
}

/* Responsive */
@media (max-width: 767px) {
    .quick-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .quick-actions .btn {
        width: 100%;
        max-width: 300px;
    }
    
    .quick-stats {
        gap: var(--spacing-md);
    }
    
    .trajet-header,
    .reservation-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-sm);
    }
    
    .trajet-route,
    .reservation-route {
        font-size: 1.25rem;
    }
    
    .info-row {
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .chauffeur-info {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-md);
    }
    
    .trajet-actions,
    .reservation-actions {
        justify-content: center;
    }
}
</style>

<script>
// Script spécifique à la page mes trajets
document.addEventListener('DOMContentLoaded', function() {
    initFilters();
});

function initFilters() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const trajets = document.querySelectorAll('.trajet-card, .reservation-card');
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            
            // Mise à jour des boutons
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Filtrage des éléments
            trajets.forEach(trajet => {
                if (filter === 'all' || trajet.getAttribute('data-status') === filter) {
                    trajet.classList.remove('hidden');
                } else {
                    trajet.classList.add('hidden');
                }
            });
            
            // Vérifier s'il y a des résultats
            const visibleTrajets = document.querySelectorAll('.trajet-card:not(.hidden), .reservation-card:not(.hidden)');
            
            // Créer ou supprimer le message "aucun résultat"
            let noResultsMsg = document.querySelector('.filter-no-results');
            
            if (visibleTrajets.length === 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'filter-no-results no-results';
                    noResultsMsg.innerHTML = `
                        <div class="no-results-icon">🔍</div>
                        <h3>Aucun résultat pour ce filtre</h3>
                        <p>Essayez un autre filtre ou consultez tous vos trajets.</p>
                    `;
                    
                    const container = document.querySelector('.trajets-list, .reservations-list');
                    if (container) {
                        container.appendChild(noResultsMsg);
                    }
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        });
    });
}

// Fonction de partage améliorée
function shareTrajet(trajetId, title) {
    const url = `${window.location.origin}/pages/trajet-details.php?id=${trajetId}`;
    const text = `Découvrez ce trajet en covoiturage : ${title}`;
    
    if (navigator.share) {
        navigator.share({
            title: `Covoiturage: ${title}`,
            text: text,
            url: url
        }).catch(console.error);
    } else {
        // Fallback : copier dans le presse-papier
        navigator.clipboard.writeText(url).then(() => {
            showNotification('Lien copié dans le presse-papier !', 'success');
        }).catch(() => {
            // Fallback du fallback : prompt
            prompt('Copiez ce lien pour partager le trajet:', url);
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>