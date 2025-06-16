<?php
// Page de recherche de trajets
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Rechercher un trajet";
$page_description = "Trouvez le trajet qui vous convient parmi tous les covoiturages disponibles au Sénégal.";

// Récupérer les paramètres de recherche
$depart = isset($_GET['depart']) ? secure($_GET['depart']) : '';
$destination = isset($_GET['destination']) ? secure($_GET['destination']) : '';
$date = isset($_GET['date']) ? secure($_GET['date']) : '';
$nb_places = isset($_GET['nb_places']) ? (int)$_GET['nb_places'] : 1;

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$trajets_par_page = 10;
$offset = ($page - 1) * $trajets_par_page;

// Variables pour les résultats
$trajets = [];
$total_trajets = 0;
$search_performed = false;

// Effectuer la recherche si des paramètres sont fournis
if (!empty($depart) && !empty($destination)) {
    $search_performed = true;
    
    try {
        // Construire la requête de recherche
        $sql_count = "SELECT COUNT(*) as total FROM trajets t JOIN users u ON t.chauffeur_id = u.id 
                     WHERE t.ville_depart LIKE ? AND t.ville_destination LIKE ? 
                     AND t.statut = 'actif' AND t.places_disponibles >= ?";
        
        $sql = "SELECT t.*, u.nom as chauffeur_nom, u.prenom as chauffeur_prenom, 
                       u.note_moyenne, u.telephone as chauffeur_telephone, u.photo_profil
                FROM trajets t 
                JOIN users u ON t.chauffeur_id = u.id 
                WHERE t.ville_depart LIKE ? AND t.ville_destination LIKE ? 
                AND t.statut = 'actif' AND t.places_disponibles >= ?";
        
        $params = ["%$depart%", "%$destination%", $nb_places];
        
        // Ajouter le filtre de date si spécifié
        if (!empty($date)) {
            $sql_count .= " AND t.date_trajet = ?";
            $sql .= " AND t.date_trajet = ?";
            $params[] = $date;
        } else {
            $sql_count .= " AND t.date_trajet >= CURDATE()";
            $sql .= " AND t.date_trajet >= CURDATE()";
        }
        
        // Compter le total
        $stmt = $pdo->prepare($sql_count);
        $stmt->execute($params);
        $total_trajets = $stmt->fetch()['total'];
        
        // Récupérer les trajets avec pagination
        $sql .= " ORDER BY t.date_trajet, t.heure_depart LIMIT ? OFFSET ?";
        $params[] = $trajets_par_page;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trajets = $stmt->fetchAll();
        
    } catch(PDOException $e) {
        $error_message = "Erreur lors de la recherche. Veuillez réessayer.";
        if (DEBUG) {
            $error_message .= " " . $e->getMessage();
        }
    }
}

// Récupérer les villes pour l'autocomplétion
$villes = getVilles();

// Calcul de la pagination
$pagination = paginate($total_trajets, $trajets_par_page, $page);

include '../includes/header.php';
?>

<div class="container">
    <div class="recherche-page">
        <!-- En-tête de la page -->
        <div class="page-header" data-animate="fade-in">
            <h1>Rechercher un trajet</h1>
            <p class="page-subtitle">
                Trouvez le covoiturage parfait pour votre destination
            </p>
        </div>
        
        <!-- Formulaire de recherche -->
        <div class="search-form-container" data-animate="fade-in">
            <form method="GET" class="search-form advanced-search" data-validate>
                <div class="search-fields">
                    <div class="search-field">
                        <label for="depart">🏠 Ville de départ</label>
                        <input 
                            type="text" 
                            id="depart" 
                            name="depart" 
                            value="<?php echo htmlspecialchars($depart); ?>"
                            placeholder="Ex: Dakar"
                            data-autocomplete="cities"
                            required
                        >
                    </div>
                    
                    <div class="search-field">
                        <label for="destination">🎯 Ville de destination</label>
                        <input 
                            type="text" 
                            id="destination" 
                            name="destination" 
                            value="<?php echo htmlspecialchars($destination); ?>"
                            placeholder="Ex: Thiès"
                            data-autocomplete="cities"
                            required
                        >
                    </div>
                    
                    <div class="search-field">
                        <label for="date">📅 Date de voyage</label>
                        <input 
                            type="date" 
                            id="date" 
                            name="date" 
                            value="<?php echo htmlspecialchars($date); ?>"
                            min="<?php echo date('Y-m-d'); ?>"
                        >
                        <small class="field-help">Laisser vide pour voir tous les trajets disponibles</small>
                    </div>
                    
                    <div class="search-field">
                        <label for="nb_places">👥 Nombre de places</label>
                        <select id="nb_places" name="nb_places">
                            <?php for($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $nb_places == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> place<?php echo $i > 1 ? 's' : ''; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="search-field">
                        <button type="submit" class="btn btn-primary btn-lg search-btn">
                            🔍 Rechercher
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Résultats de recherche -->
        <?php if ($search_performed): ?>
            <div class="search-results" data-animate="fade-in">
                <!-- En-tête des résultats -->
                <div class="results-header">
                    <h2>
                        <?php if ($total_trajets > 0): ?>
                            <?php echo $total_trajets; ?> trajet<?php echo $total_trajets > 1 ? 's' : ''; ?> trouvé<?php echo $total_trajets > 1 ? 's' : ''; ?>
                        <?php else: ?>
                            Aucun trajet trouvé
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (!empty($depart) && !empty($destination)): ?>
                        <p class="search-summary">
                            De <strong><?php echo htmlspecialchars($depart); ?></strong> 
                            vers <strong><?php echo htmlspecialchars($destination); ?></strong>
                            <?php if (!empty($date)): ?>
                                le <strong><?php echo formatDateFr($date); ?></strong>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Liste des trajets -->
                <?php if (!empty($trajets)): ?>
                    <div class="trajets-list">
                        <?php foreach ($trajets as $trajet): ?>
                            <div class="trajet-card" data-animate="fade-in">
                                <!-- En-tête du trajet -->
                                <div class="trajet-header">
                                    <div class="trajet-route">
                                        <span class="route-start"><?php echo htmlspecialchars($trajet['ville_depart']); ?></span>
                                        <span class="route-arrow">→</span>
                                        <span class="route-end"><?php echo htmlspecialchars($trajet['ville_destination']); ?></span>
                                    </div>
                                    <div class="trajet-price">
                                        <?php echo formatPrice($trajet['prix_par_place']); ?>
                                    </div>
                                </div>
                                
                                <!-- Informations du trajet -->
                                <div class="trajet-info">
                                    <div class="trajet-datetime">
                                        <span class="datetime-icon">📅</span>
                                        <span><?php echo formatDateFr($trajet['date_trajet']); ?></span>
                                        <span class="datetime-icon">🕐</span>
                                        <span><?php echo date('H:i', strtotime($trajet['heure_depart'])); ?></span>
                                    </div>
                                    
                                    <div class="trajet-places">
                                        <span class="places-icon">👥</span>
                                        <span><?php echo $trajet['places_disponibles']; ?> place<?php echo $trajet['places_disponibles'] > 1 ? 's' : ''; ?> disponible<?php echo $trajet['places_disponibles'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                    
                                    <?php if (!empty($trajet['voiture_marque'])): ?>
                                        <div class="trajet-vehicle">
                                            <span class="vehicle-icon">🚗</span>
                                            <span><?php echo htmlspecialchars($trajet['voiture_marque'] . ' ' . $trajet['voiture_modele']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Description -->
                                <?php if (!empty($trajet['description'])): ?>
                                    <div class="trajet-description">
                                        <?php echo htmlspecialchars($trajet['description']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Informations du chauffeur -->
                                <div class="trajet-chauffeur">
                                    <div class="chauffeur-info">
                                        <div class="chauffeur-avatar">
                                            <?php if (!empty($trajet['photo_profil'])): ?>
                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($trajet['photo_profil']); ?>" alt="Photo de profil">
                                            <?php else: ?>
                                                <span class="avatar-placeholder">
                                                    <?php echo strtoupper(substr($trajet['chauffeur_prenom'], 0, 1)); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="chauffeur-details">
                                            <div class="chauffeur-name">
                                                <?php echo htmlspecialchars($trajet['chauffeur_prenom'] . ' ' . substr($trajet['chauffeur_nom'], 0, 1) . '.'); ?>
                                            </div>
                                            <?php if ($trajet['note_moyenne'] > 0): ?>
                                                <div class="chauffeur-rating">
                                                    <span class="rating-stars">
                                                        <?php 
                                                        $note = round($trajet['note_moyenne']);
                                                        for($i = 1; $i <= 5; $i++) {
                                                            echo $i <= $note ? '⭐' : '☆';
                                                        }
                                                        ?>
                                                    </span>
                                                    <span class="rating-value"><?php echo number_format($trajet['note_moyenne'], 1); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="trajet-actions">
                                        <?php if (isLoggedIn()): ?>
                                            <a href="trajet-details.php?id=<?php echo $trajet['id']; ?>" class="btn btn-outline btn-sm">
                                                👁️ Voir détails
                                            </a>
                                            <a href="reserver.php?trajet_id=<?php echo $trajet['id']; ?>" class="btn btn-primary btn-sm">
                                                📝 Réserver
                                            </a>
                                        <?php else: ?>
                                            <a href="https://wa.me/221<?php echo $trajet['chauffeur_telephone']; ?>?text=Bonjour, je suis intéressé par votre trajet <?php echo urlencode($trajet['ville_depart'] . ' - ' . $trajet['ville_destination']); ?> du <?php echo urlencode(formatDateFr($trajet['date_trajet'])); ?>" 
                                               class="btn btn-primary btn-sm" target="_blank">
                                                📱 Contacter via WhatsApp
                                            </a>
                                            <a href="connexion.php?redirect=<?php echo urlencode('trajet-details.php?id=' . $trajet['id']); ?>" class="btn btn-outline btn-sm">
                                                🔑 Se connecter pour réserver
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination">
                                <?php if ($pagination['has_previous']): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-link">
                                        ← Précédent
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="pagination-link current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="pagination-link">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['has_next']): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-link">
                                        Suivant →
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pagination-info">
                                Page <?php echo $page; ?> sur <?php echo $pagination['total_pages']; ?>
                                (<?php echo $total_trajets; ?> résultat<?php echo $total_trajets > 1 ? 's' : ''; ?>)
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Aucun résultat -->
                    <div class="no-results">
                        <div class="no-results-icon">😔</div>
                        <h3>Aucun trajet trouvé</h3>
                        <p>Nous n'avons trouvé aucun trajet correspondant à vos critères.</p>
                        
                        <div class="suggestions">
                            <h4>Suggestions :</h4>
                            <ul>
                                <li>Vérifiez l'orthographe des villes</li>
                                <li>Essayez avec une date différente ou sans date</li>
                                <li>Réduisez le nombre de places demandées</li>
                                <li>Cherchez des villes proches de votre destination</li>
                            </ul>
                        </div>
                        
                        <div class="alternative-actions">
                            <a href="." class="btn btn-outline">
                                🔍 Nouvelle recherche
                            </a>
                            <?php if (isChauffeur()): ?>
                                <a href="publier.php" class="btn btn-primary">
                                    ➕ Publier ce trajet
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        
        <?php else: ?>
            <!-- Page d'accueil de recherche -->
            <div class="search-welcome" data-animate="fade-in">
                <div class="welcome-content">
                    <h2>Trouvez votre trajet idéal</h2>
                    <p>Recherchez parmi des centaines de trajets disponibles partout au Sénégal</p>
                    
                    <!-- Trajets populaires -->
                    <div class="popular-searches">
                        <h3>Trajets populaires :</h3>
                        <div class="popular-links">
                            <a href="?depart=Dakar&destination=Thiès" class="popular-link">Dakar → Thiès</a>
                            <a href="?depart=Dakar&destination=Saint-Louis" class="popular-link">Dakar → Saint-Louis</a>
                            <a href="?depart=Thiès&destination=Dakar" class="popular-link">Thiès → Dakar</a>
                            <a href="?depart=Dakar&destination=Kaolack" class="popular-link">Dakar → Kaolack</a>
                            <a href="?depart=Dakar&destination=Ziguinchor" class="popular-link">Dakar → Ziguinchor</a>
                            <a href="?depart=Saint-Louis&destination=Dakar" class="popular-link">Saint-Louis → Dakar</a>
                        </div>
                    </div>
                    
                    <!-- Conseils de recherche -->
                    <div class="search-tips">
                        <h3>💡 Conseils de recherche :</h3>
                        <ul>
                            <li>Soyez flexible sur vos dates pour plus d'options</li>
                            <li>Réservez tôt pour les trajets populaires</li>
                            <li>Contactez directement les chauffeurs pour négocier</li>
                            <li>Vérifiez les évaluations avant de réserver</li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Styles spécifiques -->
<style>
.recherche-page {
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

/* Formulaire de recherche */
.search-form-container {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xxl);
}

.advanced-search .search-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
    align-items: end;
}

.search-field label {
    display: block;
    font-weight: var(--font-weight-medium);
    margin-bottom: var(--spacing-sm);
    color: var(--dark-gray);
}

.search-field input,
.search-field select {
    width: 100%;
    padding: var(--spacing-md);
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius);
    font-size: 1rem;
}

.search-field input:focus,
.search-field select:focus {
    outline: none;
    border-color: var(--primary-color);
}

.field-help {
    display: block;
    color: var(--gray);
    font-size: 0.8rem;
    margin-top: var(--spacing-xs);
}

.search-btn {
    height: 50px;
}

/* Résultats de recherche */
.results-header {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 2px solid var(--light-gray);
}

.results-header h2 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-sm);
}

.search-summary {
    color: var(--gray);
    font-size: 1.1rem;
}

/* Cartes de trajet */
.trajets-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}

.trajet-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid #e9ecef;
    padding: var(--spacing-lg);
    transition: var(--transition);
}

.trajet-card:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.trajet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}

.trajet-route {
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

.trajet-price {
    font-size: 1.75rem;
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
}

.trajet-info {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-md);
    color: var(--gray);
}

.trajet-info > div {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.trajet-description {
    background: var(--light-gray);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-md);
    font-style: italic;
    color: var(--gray);
}

/* Informations chauffeur */
.trajet-chauffeur {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: var(--spacing-md);
    border-top: 1px solid #e9ecef;
}

.chauffeur-info {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.chauffeur-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    background: var(--light-gray);
    display: flex;
    align-items: center;
    justify-content: center;
}

.chauffeur-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    font-size: 1.5rem;
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
}

.chauffeur-name {
    font-weight: var(--font-weight-semibold);
    color: var(--dark-gray);
}

.chauffeur-rating {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: 0.9rem;
}

.rating-stars {
    color: var(--secondary-color);
}

.rating-value {
    color: var(--gray);
}

/* Actions */
.trajet-actions {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

/* Pas de résultats */
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

.suggestions {
    text-align: left;
    max-width: 400px;
    margin: 0 auto var(--spacing-xl);
}

.suggestions h4 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
}

.suggestions ul {
    color: var(--gray);
    line-height: 1.6;
}

.alternative-actions {
    display: flex;
    justify-content: center;
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

/* Page d'accueil de recherche */
.search-welcome {
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
}

.welcome-content h2 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
}

.welcome-content p {
    color: var(--gray);
    font-size: 1.1rem;
    margin-bottom: var(--spacing-xxl);
}

.popular-searches {
    margin-bottom: var(--spacing-xxl);
}

.popular-searches h3 {
    color: var(--dark-gray);
    margin-bottom: var(--spacing-lg);
}

.popular-links {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: var(--spacing-md);
}

.popular-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--light-gray);
    border-radius: var(--border-radius);
    text-decoration: none;
    color: var(--primary-color);
    font-weight: var(--font-weight-medium);
    transition: var(--transition);
}

.popular-link:hover {
    background: var(--primary-color);
    color: var(--white);
    transform: translateY(-2px);
}

.search-tips {
    text-align: left;
    max-width: 500px;
    margin: 0 auto;
    background: var(--light-gray);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
}

.search-tips h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.search-tips ul {
    color: var(--gray);
    line-height: 1.6;
}

/* Pagination */
.pagination-container {
    margin-top: var(--spacing-xxl);
    text-align: center;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-md);
}

.pagination-link {
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid #e9ecef;
    border-radius: var(--border-radius);
    text-decoration: none;
    color: var(--primary-color);
    transition: var(--transition);
}

.pagination-link:hover {
    background: var(--light-gray);
}

.pagination-link.current {
    background: var(--primary-color);
    color: var(--white);
    border-color: var(--primary-color);
}

.pagination-info {
    color: var(--gray);
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 767px) {
    .search-form-container {
        padding: var(--spacing-lg);
    }
    
    .advanced-search .search-fields {
        grid-template-columns: 1fr;
    }
    
    .trajet-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-sm);
    }
    
    .trajet-route {
        font-size: 1.25rem;
    }
    
    .trajet-price {
        font-size: 1.5rem;
    }
    
    .trajet-info {
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .trajet-chauffeur {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-md);
    }
    
    .trajet-actions {
        width: 100%;
        justify-content: center;
    }
    
    .popular-links {
        flex-direction: column;
        align-items: center;
    }
    
    .popular-link {
        width: 200px;
        text-align: center;
    }
    
    .alternative-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<script>
// Script spécifique à la recherche
document.addEventListener('DOMContentLoaded', function() {
    // Focus sur le premier champ vide
    const searchInputs = document.querySelectorAll('.search-form input[type="text"]');
    searchInputs.forEach(input => {
        if (!input.value) {
            input.focus();
            return false;
        }
    });
    
    // Intervertir départ et destination
    addSwapButton();
});

function addSwapButton() {
    const departField = document.querySelector('.search-field:has(#depart)');
    const destinationField = document.querySelector('.search-field:has(#destination)');
    
    if (departField && destinationField) {
        const swapButton = document.createElement('button');
        swapButton.type = 'button';
        swapButton.className = 'swap-button';
        swapButton.innerHTML = '🔄';
        swapButton.title = 'Intervertir départ et destination';
        swapButton.onclick = swapDepartDestination;
        
        destinationField.appendChild(swapButton);
    }
}

function swapDepartDestination() {
    const departInput = document.getElementById('depart');
    const destinationInput = document.getElementById('destination');
    
    if (departInput && destinationInput) {
        const temp = departInput.value;
        departInput.value = destinationInput.value;
        destinationInput.value = temp;
    }
}
</script>

<style>
.swap-button {
    position: absolute;
    right: -15px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--white);
    border: 2px solid var(--primary-color);
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8rem;
    transition: var(--transition);
    z-index: 10;
}

.swap-button:hover {
    background: var(--primary-color);
    color: var(--white);
    transform: translateY(-50%) rotate(180deg);
}

.search-field {
    position: relative;
}
</style>

<?php include '../includes/footer.php'; ?>