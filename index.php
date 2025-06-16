<?php
// Page d'accueil - Covoiturage Sénégal
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = "Accueil";
$page_description = "Plateforme de covoiturage n°1 au Sénégal. Trouvez ou proposez un trajet rapidement et en sécurité partout au Sénégal.";

// Récupérer quelques statistiques
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE statut = 'actif'");
    $total_users = $stmt->fetch()['total_users'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_trajets FROM trajets WHERE statut = 'actif' AND date_trajet >= CURDATE()");
    $total_trajets = $stmt->fetch()['total_trajets'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_reservations FROM reservations WHERE statut = 'confirmee'");
    $total_reservations = $stmt->fetch()['total_reservations'];
} catch(PDOException $e) {
    $total_users = 0;
    $total_trajets = 0;
    $total_reservations = 0;
}

// Récupérer les trajets populaires
try {
    $stmt = $pdo->query("
        SELECT ville_depart, ville_destination, COUNT(*) as nb_trajets 
        FROM trajets 
        WHERE statut = 'actif' AND date_trajet >= CURDATE() 
        GROUP BY ville_depart, ville_destination 
        ORDER BY nb_trajets DESC 
        LIMIT 6
    ");
    $trajets_populaires = $stmt->fetchAll();
} catch(PDOException $e) {
    $trajets_populaires = [];
}

// Récupérer les villes pour la recherche
$villes = getVilles();

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-background">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text" data-animate="fade-in">
                    <h1>Voyagez ensemble à travers le Sénégal</h1>
                    <p class="hero-subtitle">
                        Trouvez ou proposez un trajet en covoiturage pour tous vos déplacements. 
                        Économique, convivial et écologique ! 🇸🇳
                    </p>
                </div>
                
                <!-- Formulaire de recherche principal -->
                <div class="hero-search" data-animate="fade-in">
                    <form id="quickSearchForm" class="search-form" data-validate>
                        <div class="search-fields">
                            <div class="search-field">
                                <label for="depart">🏠 Départ</label>
                                <input 
                                    type="text" 
                                    id="depart" 
                                    name="depart" 
                                    placeholder="Ville de départ"
                                    data-autocomplete="cities"
                                    required
                                >
                            </div>
                            
                            <div class="search-field">
                                <label for="destination">🎯 Destination</label>
                                <input 
                                    type="text" 
                                    id="destination" 
                                    name="destination" 
                                    placeholder="Ville d'arrivée"
                                    data-autocomplete="cities"
                                    required
                                >
                            </div>
                            
                            <div class="search-field">
                                <label for="date">📅 Date</label>
                                <input 
                                    type="date" 
                                    id="date" 
                                    name="date"
                                    min="<?php echo date('Y-m-d'); ?>"
                                    data-future="true"
                                >
                            </div>
                            
                            <div class="search-field">
                                <button type="submit" class="btn btn-primary btn-lg search-btn">
                                    🔍 Rechercher
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Statistiques -->
                <div class="hero-stats" data-animate="fade-in">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($total_users); ?></div>
                        <div class="stat-label">Utilisateurs actifs</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($total_trajets); ?></div>
                        <div class="stat-label">Trajets disponibles</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($total_reservations); ?></div>
                        <div class="stat-label">Voyages réalisés</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section Trajets Populaires -->
<?php if (!empty($trajets_populaires)): ?>
<section class="popular-routes-section">
    <div class="container">
        <h2 class="section-title" data-animate="fade-in">🔥 Trajets populaires</h2>
        <p class="section-subtitle" data-animate="fade-in">
            Les destinations les plus demandées par notre communauté
        </p>
        
        <div class="popular-routes-grid" data-animate="fade-in">
            <?php foreach ($trajets_populaires as $trajet): ?>
            <a href="pages/recherche.php?depart=<?php echo urlencode($trajet['ville_depart']); ?>&destination=<?php echo urlencode($trajet['ville_destination']); ?>" 
               class="route-card">
                <div class="route-info">
                    <div class="route-cities">
                        <?php echo htmlspecialchars($trajet['ville_depart']); ?>
                        <span class="route-arrow">→</span>
                        <?php echo htmlspecialchars($trajet['ville_destination']); ?>
                    </div>
                    <div class="route-count">
                        <?php echo $trajet['nb_trajets']; ?> trajet<?php echo $trajet['nb_trajets'] > 1 ? 's' : ''; ?> disponible<?php echo $trajet['nb_trajets'] > 1 ? 's' : ''; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section Comment ça marche -->
<section class="how-it-works-section">
    <div class="container">
        <h2 class="section-title" data-animate="fade-in">Comment ça marche ?</h2>
        
        <div class="steps-grid">
            <div class="step-card" data-animate="fade-in">
                <div class="step-icon">🔍</div>
                <h3>1. Recherchez</h3>
                <p>Saisissez votre ville de départ et de destination pour trouver les trajets disponibles.</p>
            </div>
            
            <div class="step-card" data-animate="fade-in">
                <div class="step-icon">📱</div>
                <h3>2. Réservez</h3>
                <p>Contactez le chauffeur via WhatsApp ou réservez directement en ligne.</p>
            </div>
            
            <div class="step-card" data-animate="fade-in">
                <div class="step-icon">🚗</div>
                <h3>3. Voyagez</h3>
                <p>Rejoignez votre chauffeur au point de rendez-vous et profitez du voyage !</p>
            </div>
        </div>
    </div>
</section>

<!-- Section Avantages -->
<section class="advantages-section">
    <div class="container">
        <div class="advantages-content">
            <div class="advantages-text" data-animate="fade-in">
                <h2>Pourquoi choisir le covoiturage ?</h2>
                <div class="advantage-list">
                    <div class="advantage-item">
                        <span class="advantage-icon">💰</span>
                        <div>
                            <h4>Économique</h4>
                            <p>Divisez les frais de carburant et de péage. Voyagez moins cher qu'en transport traditionnel.</p>
                        </div>
                    </div>
                    
                    <div class="advantage-item">
                        <span class="advantage-icon">🌍</span>
                        <div>
                            <h4>Écologique</h4>
                            <p>Réduisez votre empreinte carbone en partageant votre véhicule avec d'autres voyageurs.</p>
                        </div>
                    </div>
                    
                    <div class="advantage-item">
                        <span class="advantage-icon">👥</span>
                        <div>
                            <h4>Convivial</h4>
                            <p>Rencontrez de nouvelles personnes et créez des liens lors de vos déplacements.</p>
                        </div>
                    </div>
                    
                    <div class="advantage-item">
                        <span class="advantage-icon">🔒</span>
                        <div>
                            <h4>Sécurisé</h4>
                            <p>Tous les membres sont vérifiés. Système d'évaluation et de signalement intégré.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="advantages-image" data-animate="fade-in">
                <div class="image-placeholder">
                    🚗 📱 👥
                    <p>Image représentant le covoiturage au Sénégal</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section CTA (Call to Action) -->
<?php if (!isLoggedIn()): ?>
<section class="cta-section">
    <div class="container">
        <div class="cta-content" data-animate="fade-in">
            <h2>Prêt à commencer ?</h2>
            <p>Rejoignez des milliers de Sénégalais qui utilisent déjà notre plateforme pour leurs déplacements quotidiens.</p>
            
            <div class="cta-buttons">
                <a href="pages/inscription.php?type=passager" class="btn btn-primary btn-lg">
                    👤 Devenir passager
                </a>
                <a href="pages/inscription.php?type=chauffeur" class="btn btn-secondary btn-lg">
                    🚗 Devenir chauffeur
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section Témoignages -->
<section class="testimonials-section">
    <div class="container">
        <h2 class="section-title" data-animate="fade-in">Ce que disent nos utilisateurs</h2>
        
        <div class="testimonials-grid">
            <div class="testimonial-card" data-animate="fade-in">
                <div class="testimonial-content">
                    <p>"Grâce à cette plateforme, je fais le trajet Dakar-Thiès chaque semaine pour moins cher. Les chauffeurs sont sympathiques et ponctuels !"</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">👩</div>
                    <div class="author-info">
                        <div class="author-name">Aminata Fall</div>
                        <div class="author-location">Dakar</div>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card" data-animate="fade-in">
                <div class="testimonial-content">
                    <p>"En tant que chauffeur, cette application m'aide à rentabiliser mes trajets vers Saint-Louis. Je rencontre toujours des personnes intéressantes !"</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">👨</div>
                    <div class="author-info">
                        <div class="author-name">Moussa Diop</div>
                        <div class="author-location">Louga</div>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card" data-animate="fade-in">
                <div class="testimonial-content">
                    <p>"Interface simple et intuitive. J'ai trouvé un trajet pour Ziguinchor en quelques minutes seulement. Je recommande vivement !"</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">👩</div>
                    <div class="author-info">
                        <div class="author-name">Fatou Sarr</div>
                        <div class="author-location">Kaolack</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section FAQ -->
<section class="faq-section">
    <div class="container">
        <h2 class="section-title" data-animate="fade-in">Questions fréquentes</h2>
        
        <div class="faq-list" data-animate="fade-in">
            <details class="faq-item">
                <summary>Comment réserver un trajet ?</summary>
                <p>Recherchez votre trajet, contactez le chauffeur via WhatsApp ou réservez directement en ligne. Une fois confirmé, vous recevrez tous les détails du voyage.</p>
            </details>
            
            <details class="faq-item">
                <summary>Comment devenir chauffeur ?</summary>
                <p>Inscrivez-vous en tant que chauffeur, ajoutez vos informations de véhicule et votre permis de conduire. Après vérification, vous pourrez publier vos trajets.</p>
            </details>
            
            <details class="faq-item">
                <summary>Que faire en cas de problème ?</summary>
                <p>Utilisez notre système de signalement intégré ou contactez notre support client. Nous prenons tous les signalements au sérieux.</p>
            </details>
            
            <details class="faq-item">
                <summary>Les trajets sont-ils sécurisés ?</summary>
                <p>Oui, tous les chauffeurs sont vérifiés et nous avons un système d'évaluation mutuelle. Vous pouvez consulter les avis avant de réserver.</p>
            </details>
        </div>
    </div>
</section>

<!-- Styles spécifiques à la page d'accueil -->
<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, var(--primary-color) 0%, #006b32 100%);
    color: var(--white);
    padding: var(--spacing-xxl) 0;
    min-height: 80vh;
    display: flex;
    align-items: center;
}

.hero-content {
    text-align: center;
}

.hero-text h1 {
    font-size: 3rem;
    font-weight: var(--font-weight-bold);
    margin-bottom: var(--spacing-lg);
    line-height: 1.2;
}

.hero-subtitle {
    font-size: 1.25rem;
    margin-bottom: var(--spacing-xxl);
    opacity: 0.9;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

/* Formulaire de recherche */
.hero-search {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    box-shadow: var(--shadow-lg);
    margin-bottom: var(--spacing-xxl);
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.search-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
    align-items: end;
}

.search-field label {
    display: block;
    color: var(--dark-gray);
    font-weight: var(--font-weight-medium);
    margin-bottom: var(--spacing-sm);
}

.search-field input {
    width: 100%;
    padding: var(--spacing-md);
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius);
    font-size: 1rem;
}

.search-btn {
    width: 100%;
    height: 50px;
}

/* Statistiques */
.hero-stats {
    display: flex;
    justify-content: center;
    gap: var(--spacing-xxl);
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: var(--font-weight-bold);
    color: var(--secondary-color);
}

.stat-label {
    opacity: 0.9;
    margin-top: var(--spacing-xs);
}

/* Trajets populaires */
.popular-routes-section {
    padding: var(--spacing-xxl) 0;
    background-color: var(--light-gray);
}

.section-title {
    text-align: center;
    font-size: 2.5rem;
    margin-bottom: var(--spacing-md);
    color: var(--dark-gray);
}

.section-subtitle {
    text-align: center;
    color: var(--gray);
    margin-bottom: var(--spacing-xxl);
}

.popular-routes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--spacing-lg);
}

.route-card {
    background: var(--white);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    text-decoration: none;
    color: inherit;
}

.route-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-5px);
    color: inherit;
}

.route-cities {
    font-size: 1.25rem;
    font-weight: var(--font-weight-semibold);
    margin-bottom: var(--spacing-sm);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.route-arrow {
    color: var(--primary-color);
    font-weight: bold;
}

.route-count {
    color: var(--gray);
    font-size: 0.875rem;
}

/* Comment ça marche */
.how-it-works-section {
    padding: var(--spacing-xxl) 0;
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--spacing-xl);
    margin-top: var(--spacing-xxl);
}

.step-card {
    text-align: center;
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    transition: var(--transition);
}

.step-card:hover {
    transform: translateY(-5px);
}

.step-icon {
    font-size: 4rem;
    margin-bottom: var(--spacing-lg);
}

.step-card h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
}

/* Avantages */
.advantages-section {
    padding: var(--spacing-xxl) 0;
    background-color: var(--light-gray);
}

.advantages-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-xxl);
    align-items: center;
}

.advantage-list {
    margin-top: var(--spacing-xl);
}

.advantage-item {
    display: flex;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-xl);
}

.advantage-icon {
    font-size: 2rem;
    margin-top: var(--spacing-xs);
}

.advantage-item h4 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-sm);
}

.advantages-image {
    display: flex;
    justify-content: center;
    align-items: center;
}

.image-placeholder {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xxl);
    text-align: center;
    font-size: 3rem;
    box-shadow: var(--shadow-md);
}

.image-placeholder p {
    font-size: 1rem;
    color: var(--gray);
    margin-top: var(--spacing-md);
}

/* CTA Section */
.cta-section {
    padding: var(--spacing-xxl) 0;
    background: linear-gradient(135deg, var(--primary-color) 0%, #006b32 100%);
    color: var(--white);
    text-align: center;
}

.cta-content h2 {
    font-size: 2.5rem;
    margin-bottom: var(--spacing-md);
}

.cta-content p {
    font-size: 1.25rem;
    margin-bottom: var(--spacing-xl);
    opacity: 0.9;
}

.cta-buttons {
    display: flex;
    justify-content: center;
    gap: var(--spacing-lg);
    flex-wrap: wrap;
}

/* Témoignages */
.testimonials-section {
    padding: var(--spacing-xxl) 0;
}

.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: var(--spacing-xl);
    margin-top: var(--spacing-xxl);
}

.testimonial-card {
    background: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    border-left: 4px solid var(--primary-color);
}

.testimonial-content {
    margin-bottom: var(--spacing-lg);
}

.testimonial-content p {
    font-style: italic;
    line-height: 1.7;
}

.testimonial-author {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.author-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--light-gray);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.author-name {
    font-weight: var(--font-weight-semibold);
    color: var(--dark-gray);
}

.author-location {
    color: var(--gray);
    font-size: 0.875rem;
}

/* FAQ */
.faq-section {
    padding: var(--spacing-xxl) 0;
    background-color: var(--light-gray);
}

.faq-list {
    max-width: 800px;
    margin: 0 auto;
    margin-top: var(--spacing-xxl);
}

.faq-item {
    background: var(--white);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-md);
    box-shadow: var(--shadow-sm);
}

.faq-item summary {
    padding: var(--spacing-lg);
    cursor: pointer;
    font-weight: var(--font-weight-medium);
    color: var(--dark-gray);
}

.faq-item p {
    padding: 0 var(--spacing-lg) var(--spacing-lg);
    color: var(--gray);
    line-height: 1.7;
}

/* Responsive */
@media (max-width: 767px) {
    .hero-text h1 {
        font-size: 2rem;
    }
    
    .hero-subtitle {
        font-size: 1rem;
    }
    
    .search-fields {
        grid-template-columns: 1fr;
    }
    
    .hero-stats {
        gap: var(--spacing-lg);
    }
    
    .advantages-content {
        grid-template-columns: 1fr;
    }
    
    .cta-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .cta-buttons .btn {
        width: 100%;
        max-width: 300px;
    }
}
</style>

<script>
// Script spécifique à la page d'accueil
document.addEventListener('DOMContentLoaded', function() {
    // Définir la date minimale pour le champ date
    const dateInput = document.getElementById('date');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
    }
    
    // Animation des compteurs
    animateCounters();
});

function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    
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
            counter.textContent = Math.floor(current).toLocaleString();
        }, 16);
    });
}
</script>

<?php include 'includes/footer.php'; ?>