<?php
// Panel d'administration - Covoiturage Sénégal
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Administration";
$page_description = "Panel d'administration pour gérer la plateforme Covoiturage Sénégal.";

// Vérifier que l'utilisateur est connecté et est admin
if (!isLoggedIn()) {
    redirectWithError('../pages/connexion.php', 'Vous devez être connecté pour accéder à l\'administration.');
}

// Pour le moment, on considère que tous les utilisateurs connectés peuvent accéder à l'admin
// En production, il faudrait un champ 'role' dans la table users
$user = getCurrentUser();

// Récupérer les statistiques générales
try {
    // Utilisateurs
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE statut = 'actif'");
    $total_users = $stmt->fetch()['total_users'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_chauffeurs FROM users WHERE type_utilisateur = 'chauffeur' AND statut = 'actif'");
    $total_chauffeurs = $stmt->fetch()['total_chauffeurs'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_passagers FROM users WHERE type_utilisateur = 'passager' AND statut = 'actif'");
    $total_passagers = $stmt->fetch()['total_passagers'];
    
    // Trajets
    $stmt = $pdo->query("SELECT COUNT(*) as total_trajets FROM trajets");
    $total_trajets = $stmt->fetch()['total_trajets'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as trajets_actifs FROM trajets WHERE statut = 'actif' AND date_trajet >= CURDATE()");
    $trajets_actifs = $stmt->fetch()['trajets_actifs'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as trajets_termines FROM trajets WHERE statut = 'termine'");
    $trajets_termines = $stmt->fetch()['trajets_termines'];
    
    // Réservations
    $stmt = $pdo->query("SELECT COUNT(*) as total_reservations FROM reservations");
    $total_reservations = $stmt->fetch()['total_reservations'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as reservations_confirmees FROM reservations WHERE statut = 'confirmee'");
    $reservations_confirmees = $stmt->fetch()['reservations_confirmees'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as reservations_terminees FROM reservations WHERE statut = 'terminee'");
    $reservations_terminees = $stmt->fetch()['reservations_terminees'];
    
    // Revenus (simulation)
    $stmt = $pdo->query("SELECT SUM(prix_total) as revenus_total FROM reservations WHERE statut = 'terminee'");
    $revenus_total = $stmt->fetch()['revenus_total'] ?: 0;
    
    // Inscriptions récentes (7 derniers jours)
    $stmt = $pdo->query("SELECT COUNT(*) as nouvelles_inscriptions FROM users WHERE date_inscription >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $nouvelles_inscriptions = $stmt->fetch()['nouvelles_inscriptions'];
    
    // Trajets populaires
    $stmt = $pdo->query("
        SELECT ville_depart, ville_destination, COUNT(*) as nb_trajets 
        FROM trajets 
        WHERE statut IN ('actif', 'termine') 
        GROUP BY ville_depart, ville_destination 
        ORDER BY nb_trajets DESC 
        LIMIT 10
    ");
    $trajets_populaires = $stmt->fetchAll();
    
    // Utilisateurs récents
    $stmt = $pdo->query("
        SELECT nom, prenom, type_utilisateur, date_inscription, ville
        FROM users 
        WHERE statut = 'actif'
        ORDER BY date_inscription DESC 
        LIMIT 10
    ");
    $utilisateurs_recents = $stmt->fetchAll();
    
    // Trajets récents
    $stmt = $pdo->query("
        SELECT t.*, u.nom, u.prenom 
        FROM trajets t 
        JOIN users u ON t.chauffeur_id = u.id 
        ORDER BY t.date_creation DESC 
        LIMIT 10
    ");
    $trajets_recents = $stmt->fetchAll();
    
    // Statistiques par mois (6 derniers mois)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(date_inscription, '%Y-%m') as mois,
            COUNT(*) as nb_inscriptions
        FROM users 
        WHERE date_inscription >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_inscription, '%Y-%m')
        ORDER BY mois DESC
    ");
    $stats_mensuelles = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error_message = "Erreur lors du chargement des statistiques.";
    if (DEBUG) {
        $error_message .= " " . $e->getMessage();
    }
    
    // Valeurs par défaut en cas d'erreur
    $total_users = $total_chauffeurs = $total_passagers = 0;
    $total_trajets = $trajets_actifs = $trajets_termines = 0;
    $total_reservations = $reservations_confirmees = $reservations_terminees = 0;
    $revenus_total = $nouvelles_inscriptions = 0;
    $trajets_populaires = $utilisateurs_recents = $trajets_recents = $stats_mensuelles = [];
}

include '../includes/header.php';
?>

<div class="container">
    <div class="admin-page">
        <!-- En-tête d'administration -->
        <div class="admin-header" data-animate="fade-in">
            <h1>🛡️ Panel d'Administration</h1>
            <p class="admin-subtitle">
                Tableau de bord pour gérer la plateforme Covoiturage Sénégal
            </p>
            <div class="admin-user">
                Connecté en tant que : <strong><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></strong>
            </div>
        </div>
        
        <!-- Statistiques principales -->
        <div class="stats-grid" data-animate="fade-in">
            <!-- Utilisateurs -->
            <div class="stat-card users">
                <div class="stat-header">
                    <span class="stat-icon">👥</span>
                    <h3>Utilisateurs</h3>
                </div>
                <div class="stat-content">
                    <div class="stat-main">
                        <span class="stat-number"><?php echo number_format($total_users); ?></span>
                        <span class="stat-label">Total actifs</span>
                    </div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span class="detail-number"><?php echo number_format($total_chauffeurs); ?></span>
                            <span class="detail-label">Chauffeurs</span>
                        </div>
                        <div class="stat-detail">
                            <span class="detail-number"><?php echo number_format($total_passagers); ?></span>
                            <span class="detail-label">Passagers</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Trajets -->
            <div class="stat-card trajets">
                <div class="stat-header">
                    <span class="stat-icon">🚗</span>
                    <h3>Trajets</h3>
                </div>
                <div class="stat-content">
                    <div class="stat-main">
                        <span class="stat-number"><?php echo number_format($total_trajets); ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span class="detail-number"><?php echo number_format($trajets_actifs); ?></span>
                            <span class="detail-label">Actifs</span>
                        </div>
                        <div class="stat-detail">
                            <span class="detail-number"><?php echo number_format($trajets_termines); ?></span>
                            <span class="detail-label">Terminés</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Réservations -->
            <div class="stat-card reservations">
                <div class="stat-header">
                    <span class="stat-icon">📝</span>
                    <h3>Réservations</h3>
                </div>
                <div class="stat-content">
                    <div class="stat-main">
                        <span class="stat-number"><?php echo number_format($total_reservations); ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span class="detail-number"><?php echo number_format($reservations_confirmees); ?></span>
                            <span class="detail-label">Confirmées</span>
                        </div>
                        <div class="stat-detail">
                            <span class="detail-number"><?php echo number_format($reservations_terminees); ?></span>
                            <span class="detail-label">Terminées</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Revenus -->
            <div class="stat-card revenus">
                <div class="stat-header">
                    <span class="stat-icon">💰</span>
                    <h3>Activité</h3>
                </div>
                <div class="stat-content">
                    <div class="stat-main">
                        <span class="stat-number"><?php echo formatPrice($revenus_total); ?></span>
                        <span class="stat-label">Volume échangé</span>
                    </div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span class="detail-number"><?php echo number_format($nouvelles_inscriptions); ?></span>
                            <span class="detail-label">Inscriptions (7j)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="quick-actions" data-animate="fade-in">
            <h2>Actions rapides</h2>
            <div class="actions-grid">
                <a href="gerer-utilisateurs.php" class="action-card">
                    <span class="action-icon">👥</span>
                    <h4>Gérer les utilisateurs</h4>
                    <p>Voir, modifier et gérer tous les utilisateurs</p>
                </a>
                
                <a href="gerer-trajets.php" class="action-card">
                    <span class="action-icon">🚗</span>
                    <h4>Gérer les trajets</h4>
                    <p>Modérer et superviser les trajets publiés</p>
                </a>
                
                <a href="signalements.php" class="action-card">
                    <span class="action-icon">🚨</span>
                    <h4>Signalements</h4>
                    <p>Traiter les signalements et conflits</p>
                </a>
                
                <a href="statistiques.php" class="action-card">
                    <span class="action-icon">📊</span>
                    <h4>Statistiques détaillées</h4>
                    <p>Analyses et rapports complets</p>
                </a>
                
                <a href="parametres.php" class="action-card">
                    <span class="action-icon">⚙️</span>
                    <h4>Paramètres</h4>
                    <p>Configuration de la plateforme</p>
                </a>
                
                <a href="logs.php" class="action-card">
                    <span class="action-icon">📋</span>
                    <h4>Journaux d'activité</h4>
                    <p>Historique des actions utilisateurs</p>
                </a>
            </div>
        </div>
        
        <!-- Tableaux de données -->
        <div class="data-section" data-animate="fade-in">
            <div class="data-grid">
                <!-- Trajets populaires -->
                <div class="data-card">
                    <h3>🔥 Trajets populaires</h3>
                    <div class="data-content">
                        <?php if (!empty($trajets_populaires)): ?>
                            <?php foreach ($trajets_populaires as $index => $trajet): ?>
                                <div class="data-row">
                                    <span class="row-rank">#<?php echo $index + 1; ?></span>
                                    <span class="row-content">
                                        <?php echo htmlspecialchars($trajet['ville_depart']); ?> → 
                                        <?php echo htmlspecialchars($trajet['ville_destination']); ?>
                                    </span>
                                    <span class="row-value"><?php echo $trajet['nb_trajets']; ?> trajets</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-data">Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Utilisateurs récents -->
                <div class="data-card">
                    <h3>👤 Utilisateurs récents</h3>
                    <div class="data-content">
                        <?php if (!empty($utilisateurs_recents)): ?>
                            <?php foreach ($utilisateurs_recents as $utilisateur): ?>
                                <div class="data-row">
                                    <span class="row-content">
                                        <strong><?php echo htmlspecialchars($utilisateur['prenom'] . ' ' . substr($utilisateur['nom'], 0, 1) . '.'); ?></strong>
                                        <small class="user-type <?php echo $utilisateur['type_utilisateur']; ?>">
                                            <?php echo $utilisateur['type_utilisateur'] === 'chauffeur' ? '🚗' : '👤'; ?> 
                                            <?php echo ucfirst($utilisateur['type_utilisateur']); ?>
                                        </small>
                                    </span>
                                    <span class="row-meta">
                                        <?php echo $utilisateur['ville'] ? htmlspecialchars($utilisateur['ville']) : 'Non spécifié'; ?>
                                        <small><?php echo formatDateFr($utilisateur['date_inscription']); ?></small>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-data">Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Trajets récents -->
                <div class="data-card">
                    <h3>🆕 Trajets récents</h3>
                    <div class="data-content">
                        <?php if (!empty($trajets_recents)): ?>
                            <?php foreach ($trajets_recents as $trajet): ?>
                                <div class="data-row">
                                    <span class="row-content">
                                        <strong><?php echo htmlspecialchars($trajet['ville_depart']); ?> → <?php echo htmlspecialchars($trajet['ville_destination']); ?></strong>
                                        <small>par <?php echo htmlspecialchars($trajet['prenom'] . ' ' . substr($trajet['nom'], 0, 1) . '.'); ?></small>
                                    </span>
                                    <span class="row-meta">
                                        <span class="status-badge status-<?php echo $trajet['statut']; ?>">
                                            <?php echo ucfirst($trajet['statut']); ?>
                                        </span>
                                        <small><?php echo formatDateFr($trajet['date_trajet']); ?></small>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-data">Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Graphique des inscriptions -->
        <?php if (!empty($stats_mensuelles)): ?>
        <div class="chart-section" data-animate="fade-in">
            <h3>📈 Évolution des inscriptions (6 derniers mois)</h3>
            <div class="chart-container">
                <canvas id="inscriptionsChart" width="400" height="200"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Alertes et notifications -->
        <div class="alerts-section" data-animate="fade-in">
            <h3>🔔 Alertes système</h3>
            <div class="alerts-content">
                <div class="alert alert-info">
                    <span class="alert-icon">ℹ️</span>
                    <div class="alert-content">
                        <strong>Système opérationnel</strong>
                        <p>Tous les services fonctionnent normalement.</p>
                    </div>
                </div>
                
                <?php if ($nouvelles_inscriptions > 10): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">🎉</span>
                    <div class="alert-content">
                        <strong>Forte activité</strong>
                        <p><?php echo $nouvelles_inscriptions; ?> nouvelles inscriptions cette semaine !</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($trajets_actifs < 5): ?>
                <div class="alert alert-warning">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-content">
                        <strong>Peu de trajets actifs</strong>
                        <p>Seulement <?php echo $trajets_actifs; ?> trajets actifs actuellement.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Styles spécifiques à l'administration -->
<style>
.admin-page {
    padding: var(--spacing-xl) 0;
}

.admin-header {
    text-align: center;
    margin-bottom: var(--spacing-xxl);
    background: linear-gradient(135deg, var(--primary-color), #006b32);
    color: var(--white);
    padding: var(--spacing-xxl);
    border-radius: var(--border-radius-lg);
}

.admin-header h1 {
    margin-bottom: var(--spacing-md);
}

.admin-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: var(--spacing-lg);
}

.admin-user {
    background: rgba(255, 255, 255, 0.1);
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--border-radius);
    display: inline-block;
}

/* Grille de statistiques */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xxl);
}

.stat-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    padding: var(--spacing-xl);
    border-left: 5px solid var(--primary-color);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card.users {
    border-left-color: var(--info-color);
}

.stat-card.trajets {
    border-left-color: var(--primary-color);
}

.stat-card.reservations {
    border-left-color: var(--warning-color);
}

.stat-card.revenus {
    border-left-color: var(--success-color);
}

.stat-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.stat-icon {
    font-size: 2rem;
    opacity: 0.8;
}

.stat-header h3 {
    color: var(--dark-gray);
    margin: 0;
}

.stat-main {
    text-align: center;
    margin-bottom: var(--spacing-lg);
}

.stat-number {
    display: block;
    font-size: 2.5rem;
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
    line-height: 1;
}

.stat-label {
    color: var(--gray);
    font-size: 0.9rem;
    margin-top: var(--spacing-xs);
}

.stat-details {
    display: flex;
    justify-content: space-around;
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--light-gray);
}

.stat-detail {
    text-align: center;
}

.detail-number {
    display: block;
    font-size: 1.25rem;
    font-weight: var(--font-weight-semibold);
    color: var(--dark-gray);
}

.detail-label {
    font-size: 0.8rem;
    color: var(--gray);
}

/* Actions rapides */
.quick-actions {
    margin-bottom: var(--spacing-xxl);
}

.quick-actions h2 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-lg);
}

.action-card {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    text-align: center;
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
    border: 2px solid transparent;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-color);
    color: inherit;
}

.action-icon {
    font-size: 2.5rem;
    display: block;
    margin-bottom: var(--spacing-md);
}

.action-card h4 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-sm);
}

.action-card p {
    color: var(--gray);
    font-size: 0.9rem;
    margin: 0;
}

/* Section de données */
.data-section {
    margin-bottom: var(--spacing-xxl);
}

.data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: var(--spacing-lg);
}

.data-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.data-card h3 {
    background: var(--light-gray);
    padding: var(--spacing-lg);
    margin: 0;
    color: var(--primary-color);
    border-bottom: 1px solid #e9ecef;
}

.data-content {
    padding: var(--spacing-lg);
    max-height: 400px;
    overflow-y: auto;
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md) 0;
    border-bottom: 1px solid var(--light-gray);
}

.data-row:last-child {
    border-bottom: none;
}

.row-rank {
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
    margin-right: var(--spacing-md);
}

.row-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.row-content small {
    color: var(--gray);
    font-size: 0.8rem;
}

.row-value,
.row-meta {
    font-size: 0.9rem;
    color: var(--gray);
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.user-type {
    padding: 2px 6px;
    border-radius: var(--border-radius-sm);
    font-size: 0.7rem;
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

.no-data {
    text-align: center;
    color: var(--gray);
    font-style: italic;
    padding: var(--spacing-xl);
}

/* Status badges */
.status-badge {
    padding: 2px 8px;
    border-radius: var(--border-radius-sm);
    font-size: 0.7rem;
    font-weight: var(--font-weight-medium);
}

.status-actif {
    background: var(--success-color);
    color: var(--white);
}

.status-termine {
    background: var(--gray);
    color: var(--white);
}

.status-annule {
    background: var(--danger-color);
    color: var(--white);
}

/* Graphique */
.chart-section {
    margin-bottom: var(--spacing-xxl);
}

.chart-section h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.chart-container {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
}

/* Alertes */
.alerts-section {
    margin-bottom: var(--spacing-xl);
}

.alerts-section h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.alerts-content {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-md);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    border-left: 4px solid;
}

.alert-info {
    background: #e3f2fd;
    border-left-color: var(--info-color);
    color: #0277bd;
}

.alert-success {
    background: #e8f5e8;
    border-left-color: var(--success-color);
    color: #2e7d32;
}

.alert-warning {
    background: #fff8e1;
    border-left-color: var(--warning-color);
    color: #f57c00;
}

.alert-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.alert-content h4 {
    margin: 0 0 var(--spacing-xs);
}

.alert-content p {
    margin: 0;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 767px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .data-grid {
        grid-template-columns: 1fr;
    }
    
    .data-row {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-sm);
    }
    
    .row-value,
    .row-meta {
        text-align: left;
    }
}
</style>

<!-- Script pour le graphique -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
// Script spécifique à l'administration
document.addEventListener('DOMContentLoaded', function() {
    // Animation des compteurs
    animateCounters();
    
    // Initialiser le graphique
    initChart();
    
    // Actualisation automatique toutes les 5 minutes
    setInterval(function() {
        window.location.reload();
    }, 5 * 60 * 1000);
});

function animateCounters() {
    const counters = document.querySelectorAll('.stat-number, .detail-number');
    
    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
        const duration = 2000;
        const increment = target / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            
            // Préserver le formatage original
            if (counter.textContent.includes(' FCFA')) {
                counter.textContent = Math.floor(current).toLocaleString() + ' FCFA';
            } else {
                counter.textContent = Math.floor(current).toLocaleString();
            }
        }, 16);
    });
}

function initChart() {
    const chartCanvas = document.getElementById('inscriptionsChart');
    if (!chartCanvas) return;
    
    // Données des inscriptions (à partir de PHP)
    const statsData = <?php echo json_encode($stats_mensuelles); ?>;
    
    const labels = statsData.map(stat => {
        const [year, month] = stat.mois.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
    }).reverse();
    
    const data = statsData.map(stat => stat.nb_inscriptions).reverse();
    
    new Chart(chartCanvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nouvelles inscriptions',
                data: data,
                borderColor: 'rgb(0, 133, 62)',
                backgroundColor: 'rgba(0, 133, 62, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Fonction pour exporter les données
function exportData(type) {
    // Simulation d'export - à implémenter avec un vrai script
    showNotification(`Export ${type} en cours...`, 'info');
    
    setTimeout(() => {
        showNotification(`Export ${type} terminé !`, 'success');
    }, 2000);
}

// Fonction pour rafraîchir les données
function refreshData() {
    showNotification('Actualisation des données...', 'info');
    window.location.reload();
}
</script>

<?php include '../includes/footer.php'; ?>