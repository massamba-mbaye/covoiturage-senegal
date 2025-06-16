<?php
// Page de publication de trajet
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Publier un trajet";
$page_description = "Publiez votre trajet et trouvez des passagers pour partager les frais de voyage.";

// Vérifier que l'utilisateur est connecté et est chauffeur
if (!isLoggedIn()) {
    redirectWithError('../pages/connexion.php?redirect=' . urlencode('publier.php'), 
                     'Vous devez être connecté pour publier un trajet.');
}

if (!isChauffeur()) {
    redirectWithError('../pages/profil.php', 
                     'Seuls les chauffeurs peuvent publier des trajets. Modifiez votre profil pour devenir chauffeur.');
}

$errors = [];
$success = false;

// Traitement du formulaire
if ($_POST) {
    // Récupérer et nettoyer les données
    $ville_depart = secure($_POST['ville_depart']);
    $ville_destination = secure($_POST['ville_destination']);
    $date_trajet = secure($_POST['date_trajet']);
    $heure_depart = secure($_POST['heure_depart']);
    $prix_par_place = (float)str_replace(',', '.', $_POST['prix_par_place']);
    $places_totales = (int)$_POST['places_totales'];
    $voiture_marque = !empty($_POST['voiture_marque']) ? secure($_POST['voiture_marque']) : '';
    $voiture_modele = !empty($_POST['voiture_modele']) ? secure($_POST['voiture_modele']) : '';
    $voiture_couleur = !empty($_POST['voiture_couleur']) ? secure($_POST['voiture_couleur']) : '';
    $numero_plaque = !empty($_POST['numero_plaque']) ? secure($_POST['numero_plaque']) : '';
    $description = !empty($_POST['description']) ? secure($_POST['description']) : '';
    $point_depart_precis = !empty($_POST['point_depart_precis']) ? secure($_POST['point_depart_precis']) : '';
    $point_arrivee_precis = !empty($_POST['point_arrivee_precis']) ? secure($_POST['point_arrivee_precis']) : '';
    
    // Validation des données
    $validation_rules = [
        'ville_depart' => ['required' => true, 'min_length' => 2, 'max_length' => 100, 'label' => 'Ville de départ'],
        'ville_destination' => ['required' => true, 'min_length' => 2, 'max_length' => 100, 'label' => 'Ville de destination'],
        'date_trajet' => ['required' => true, 'future_date' => true, 'label' => 'Date du trajet'],
        'heure_depart' => ['required' => true, 'label' => 'Heure de départ'],
        'places_totales' => ['required' => true, 'label' => 'Nombre de places'],
    ];
    
    $form_errors = validateInput($_POST, $validation_rules);
    $errors = array_merge($errors, $form_errors);
    
    // Validations spécifiques
    if ($ville_depart === $ville_destination) {
        $errors['ville_destination'] = "La destination doit être différente du point de départ.";
    }
    
    if ($prix_par_place < PRIX_MINIMUM || $prix_par_place > PRIX_MAXIMUM) {
        $errors['prix_par_place'] = "Le prix doit être entre " . formatPrice(PRIX_MINIMUM) . " et " . formatPrice(PRIX_MAXIMUM) . ".";
    }
    
    if ($places_totales < 1 || $places_totales > MAX_PLACES_PAR_TRAJET) {
        $errors['places_totales'] = "Le nombre de places doit être entre 1 et " . MAX_PLACES_PAR_TRAJET . ".";
    }
    
    // Vérifier que la date/heure n'est pas trop proche
    $trajet_datetime = $date_trajet . ' ' . $heure_depart;
    $trajet_timestamp = strtotime($trajet_datetime);
    $min_timestamp = time() + (2 * 3600); // Minimum 2 heures à l'avance
    
    if ($trajet_timestamp < $min_timestamp) {
        $errors['heure_depart'] = "Le trajet doit être programmé au moins 2 heures à l'avance.";
    }
    
    // Insertion en base de données
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO trajets (
                    chauffeur_id, ville_depart, ville_destination, date_trajet, heure_depart,
                    prix_par_place, places_disponibles, places_totales, voiture_marque, voiture_modele,
                    voiture_couleur, numero_plaque, description, point_depart_precis, point_arrivee_precis,
                    date_creation
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $ville_depart,
                $ville_destination,
                $date_trajet,
                $heure_depart,
                $prix_par_place,
                $places_totales, // places_disponibles = places_totales au début
                $places_totales,
                $voiture_marque ?: null,
                $voiture_modele ?: null,
                $voiture_couleur ?: null,
                $numero_plaque ?: null,
                $description ?: null,
                $point_depart_precis ?: null,
                $point_arrivee_precis ?: null
            ]);
            
            $trajet_id = $pdo->lastInsertId();
            
            // Log de l'action
            logUserAction($_SESSION['user_id'], 'publication_trajet', "Trajet ID: $trajet_id");
            
            // Message de succès
            $message = "Votre trajet a été publié avec succès ! Les passagers pourront maintenant le voir et le réserver.";
            redirectWithSuccess('../pages/mes-trajets.php', $message);
            
        } catch(PDOException $e) {
            $errors['general'] = "Erreur lors de la publication du trajet. Veuillez réessayer.";
            if (DEBUG) {
                $errors['general'] .= " " . $e->getMessage();
            }
        }
    }
}

// Récupérer les villes pour l'autocomplétion
$villes = getVilles();

// Définir des valeurs par défaut
$default_date = date('Y-m-d', strtotime('+1 day'));
$default_time = '08:00';

include '../includes/header.php';
?>

<div class="container">
    <div class="publier-page">
        <!-- En-tête de la page -->
        <div class="page-header" data-animate="fade-in">
            <h1>Publier un trajet</h1>
            <p class="page-subtitle">
                Partagez votre voyage et trouvez des passagers pour réduire vos frais de transport
            </p>
        </div>
        
        <!-- Guide rapide -->
        <div class="quick-guide" data-animate="fade-in">
            <h3>📋 Comment bien publier votre trajet :</h3>
            <div class="guide-steps">
                <div class="guide-step">
                    <span class="step-number">1</span>
                    <span>Renseignez votre itinéraire et horaires</span>
                </div>
                <div class="guide-step">
                    <span class="step-number">2</span>
                    <span>Fixez un prix équitable pour vos passagers</span>
                </div>
                <div class="guide-step">
                    <span class="step-number">3</span>
                    <span>Ajoutez les détails de votre véhicule</span>
                </div>
                <div class="guide-step">
                    <span class="step-number">4</span>
                    <span>Décrivez votre trajet et vos conditions</span>
                </div>
            </div>
        </div>
        
        <!-- Formulaire de publication -->
        <div class="publication-form-container" data-animate="fade-in">
            <form method="POST" class="publication-form" data-validate>
                <!-- Erreur générale -->
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <span class="alert-icon">⚠️</span>
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Section Itinéraire -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="section-icon">🗺️</span>
                        Itinéraire
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ville_depart" class="form-label">Ville de départ *</label>
                            <input 
                                type="text" 
                                id="ville_depart" 
                                name="ville_depart" 
                                class="form-control <?php echo isset($errors['ville_depart']) ? 'error' : ''; ?>" 
                                value="<?php echo isset($_POST['ville_depart']) ? htmlspecialchars($_POST['ville_depart']) : ''; ?>"
                                placeholder="Ex: Dakar"
                                data-autocomplete="cities"
                                required
                            >
                            <?php if (isset($errors['ville_depart'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['ville_depart']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="ville_destination" class="form-label">Ville de destination *</label>
                            <input 
                                type="text" 
                                id="ville_destination" 
                                name="ville_destination" 
                                class="form-control <?php echo isset($errors['ville_destination']) ? 'error' : ''; ?>" 
                                value="<?php echo isset($_POST['ville_destination']) ? htmlspecialchars($_POST['ville_destination']) : ''; ?>"
                                placeholder="Ex: Thiès"
                                data-autocomplete="cities"
                                required
                            >
                            <?php if (isset($errors['ville_destination'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['ville_destination']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="point_depart_precis" class="form-label">Point de départ précis</label>
                            <input 
                                type="text" 
                                id="point_depart_precis" 
                                name="point_depart_precis" 
                                class="form-control" 
                                value="<?php echo isset($_POST['point_depart_precis']) ? htmlspecialchars($_POST['point_depart_precis']) : ''; ?>"
                                placeholder="Ex: Gare routière, Station Total..."
                            >
                            <small class="form-help">Indiquez où vous prendrez vos passagers</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="point_arrivee_precis" class="form-label">Point d'arrivée précis</label>
                            <input 
                                type="text" 
                                id="point_arrivee_precis" 
                                name="point_arrivee_precis" 
                                class="form-control" 
                                value="<?php echo isset($_POST['point_arrivee_precis']) ? htmlspecialchars($_POST['point_arrivee_precis']) : ''; ?>"
                                placeholder="Ex: Centre-ville, Gare..."
                            >
                            <small class="form-help">Indiquez où vous déposerez vos passagers</small>
                        </div>
                    </div>
                </div>
                
                <!-- Section Horaires et Places -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="section-icon">⏰</span>
                        Horaires et Places
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_trajet" class="form-label">Date du trajet *</label>
                            <input 
                                type="date" 
                                id="date_trajet" 
                                name="date_trajet" 
                                class="form-control <?php echo isset($errors['date_trajet']) ? 'error' : ''; ?>" 
                                value="<?php echo isset($_POST['date_trajet']) ? htmlspecialchars($_POST['date_trajet']) : $default_date; ?>"
                                min="<?php echo date('Y-m-d'); ?>"
                                required
                            >
                            <?php if (isset($errors['date_trajet'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['date_trajet']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="heure_depart" class="form-label">Heure de départ *</label>
                            <input 
                                type="time" 
                                id="heure_depart" 
                                name="heure_depart" 
                                class="form-control <?php echo isset($errors['heure_depart']) ? 'error' : ''; ?>" 
                                value="<?php echo isset($_POST['heure_depart']) ? htmlspecialchars($_POST['heure_depart']) : $default_time; ?>"
                                required
                            >
                            <?php if (isset($errors['heure_depart'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['heure_depart']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="places_totales" class="form-label">Nombre de places disponibles *</label>
                            <select 
                                id="places_totales" 
                                name="places_totales" 
                                class="form-control <?php echo isset($errors['places_totales']) ? 'error' : ''; ?>" 
                                required
                            >
                                <option value="">Sélectionner</option>
                                <?php for($i = 1; $i <= MAX_PLACES_PAR_TRAJET; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (isset($_POST['places_totales']) && $_POST['places_totales'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> place<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <small class="form-help">Nombre de passagers que vous pouvez prendre</small>
                            <?php if (isset($errors['places_totales'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['places_totales']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="prix_par_place" class="form-label">Prix par place (FCFA) *</label>
                            <input 
                                type="number" 
                                id="prix_par_place" 
                                name="prix_par_place" 
                                class="form-control <?php echo isset($errors['prix_par_place']) ? 'error' : ''; ?>" 
                                value="<?php echo isset($_POST['prix_par_place']) ? htmlspecialchars($_POST['prix_par_place']) : ''; ?>"
                                min="<?php echo PRIX_MINIMUM; ?>" 
                                max="<?php echo PRIX_MAXIMUM; ?>"
                                step="250"
                                placeholder="Ex: 2500"
                                required
                            >
                            <small class="form-help">
                                Entre <?php echo formatPrice(PRIX_MINIMUM); ?> et <?php echo formatPrice(PRIX_MAXIMUM); ?>
                            </small>
                            <?php if (isset($errors['prix_par_place'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($errors['prix_par_place']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Section Véhicule -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="section-icon">🚗</span>
                        Informations sur votre véhicule
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="voiture_marque" class="form-label">Marque du véhicule</label>
                            <input 
                                type="text" 
                                id="voiture_marque" 
                                name="voiture_marque" 
                                class="form-control" 
                                value="<?php echo isset($_POST['voiture_marque']) ? htmlspecialchars($_POST['voiture_marque']) : ''; ?>"
                                placeholder="Ex: Toyota, Peugeot, Nissan..."
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="voiture_modele" class="form-label">Modèle du véhicule</label>
                            <input 
                                type="text" 
                                id="voiture_modele" 
                                name="voiture_modele" 
                                class="form-control" 
                                value="<?php echo isset($_POST['voiture_modele']) ? htmlspecialchars($_POST['voiture_modele']) : ''; ?>"
                                placeholder="Ex: Corolla, 308, Almera..."
                            >
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="voiture_couleur" class="form-label">Couleur du véhicule</label>
                            <input 
                                type="text" 
                                id="voiture_couleur" 
                                name="voiture_couleur" 
                                class="form-control" 
                                value="<?php echo isset($_POST['voiture_couleur']) ? htmlspecialchars($_POST['voiture_couleur']) : ''; ?>"
                                placeholder="Ex: Blanc, Noir, Gris..."
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_plaque" class="form-label">Numéro de plaque</label>
                            <input 
                                type="text" 
                                id="numero_plaque" 
                                name="numero_plaque" 
                                class="form-control" 
                                value="<?php echo isset($_POST['numero_plaque']) ? htmlspecialchars($_POST['numero_plaque']) : ''; ?>"
                                placeholder="Ex: DK 1234 AB"
                            >
                            <small class="form-help">Optionnel, mais aide les passagers à vous identifier</small>
                        </div>
                    </div>
                </div>
                
                <!-- Section Description -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span class="section-icon">📝</span>
                        Description et conditions
                    </h3>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description du trajet</label>
                        <textarea 
                            id="description" 
                            name="description" 
                            class="form-control" 
                            rows="4"
                            placeholder="Décrivez votre trajet : ambiance, arrêts prévus, conditions particulières..."
                        ><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <small class="form-help">
                            Exemples : "Trajet direct sans arrêt", "Musique d'ambiance", "Climatisation", "Arrêt possible à Rufisque"...
                        </small>
                    </div>
                </div>
                
                <!-- Conseils de prix -->
                <div class="price-suggestion" data-animate="fade-in">
                    <h4>💡 Suggestion de prix</h4>
                    <p>Pour vous aider à fixer un prix équitable :</p>
                    <div class="price-examples">
                        <div class="price-example">
                            <strong>Dakar ↔ Thiès</strong> : 2000 - 3000 FCFA
                        </div>
                        <div class="price-example">
                            <strong>Dakar ↔ Saint-Louis</strong> : 4000 - 6000 FCFA
                        </div>
                        <div class="price-example">
                            <strong>Dakar ↔ Kaolack</strong> : 3500 - 5000 FCFA
                        </div>
                        <div class="price-example">
                            <strong>Dakar ↔ Ziguinchor</strong> : 8000 - 12000 FCFA
                        </div>
                    </div>
                    <p><em>Ces prix sont indicatifs et peuvent varier selon la qualité du service et la demande.</em></p>
                </div>
                
                <!-- Bouton de soumission -->
                <div class="form-submit">
                    <button type="submit" class="btn btn-primary btn-lg btn-full">
                        <span class="btn-icon">🚀</span>
                        Publier mon trajet
                    </button>
                    
                    <p class="submit-note">
                        En publiant ce trajet, vous vous engagez à respecter les conditions d'utilisation 
                        et à être ponctuel le jour du voyage.
                    </p>
                </div>
            </form>
        </div>
        
        <!-- Conseils pour réussir -->
        <div class="success-tips" data-animate="fade-in">
            <h3>✅ Conseils pour attirer plus de passagers :</h3>
            <div class="tips-grid">
                <div class="tip-item">
                    <span class="tip-icon">💰</span>
                    <div class="tip-content">
                        <h4>Prix compétitif</h4>
                        <p>Proposez un prix raisonnable par rapport aux autres moyens de transport</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <span class="tip-icon">📝</span>
                    <div class="tip-content">
                        <h4>Description détaillée</h4>
                        <p>Plus votre description est précise, plus les passagers seront rassurés</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <span class="tip-icon">⏰</span>
                    <div class="tip-content">
                        <h4>Horaires flexibles</h4>
                        <p>Mentionnez si vous pouvez ajuster légèrement vos horaires</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <span class="tip-icon">🤝</span>
                    <div class="tip-content">
                        <h4>Communication</h4>
                        <p>Répondez rapidement aux demandes des passagers intéressés</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles spécifiques -->
<style>
.publier-page {
    max-width: 800px;
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

/* Guide rapide */
.quick-guide {
    background: linear-gradient(135deg, var(--primary-color), #006b32);
    color: var(--white);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    margin-bottom: var(--spacing-xxl);
}

.quick-guide h3 {
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.guide-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
}

.guide-step {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--border-radius);
}

.step-number {
    width: 30px;
    height: 30px;
    background: var(--secondary-color);
    color: var(--dark-gray);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: var(--font-weight-bold);
    flex-shrink: 0;
}

/* Formulaire */
.publication-form-container {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    padding: var(--spacing-xxl);
    margin-bottom: var(--spacing-xxl);
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
    font-size: 1.25rem;
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

.form-label {
    display: block;
    font-weight: var(--font-weight-medium);
    margin-bottom: var(--spacing-sm);
    color: var(--dark-gray);
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

.form-help {
    display: block;
    color: var(--gray);
    font-size: 0.875rem;
    margin-top: var(--spacing-xs);
}

/* Suggestion de prix */
.price-suggestion {
    background: var(--light-gray);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    margin-bottom: var(--spacing-xl);
    border-left: 4px solid var(--secondary-color);
}

.price-suggestion h4 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
}

.price-examples {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-md);
    margin: var(--spacing-lg) 0;
}

.price-example {
    padding: var(--spacing-md);
    background: var(--white);
    border-radius: var(--border-radius);
    text-align: center;
    font-size: 0.9rem;
}

/* Soumission */
.form-submit {
    text-align: center;
    margin-top: var(--spacing-xxl);
}

.btn-full {
    width: 100%;
    max-width: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    margin: 0 auto var(--spacing-md);
}

.btn-icon {
    font-size: 1.2rem;
}

.submit-note {
    color: var(--gray);
    font-size: 0.9rem;
    line-height: 1.5;
    max-width: 500px;
    margin: 0 auto;
}

/* Conseils de succès */
.success-tips {
    background: var(--light-gray);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
}

.success-tips h3 {
    color: var(--primary-color);
    text-align: center;
    margin-bottom: var(--spacing-xl);
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-lg);
}

.tip-item {
    display: flex;
    gap: var(--spacing-md);
    padding: var(--spacing-lg);
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
}

.tip-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.tip-content h4 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-sm);
    font-size: 1rem;
}

.tip-content p {
    color: var(--gray);
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0;
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
    .publier-page {
        padding: var(--spacing-lg) 0;
    }
    
    .guide-steps {
        grid-template-columns: 1fr;
    }
    
    .publication-form-container {
        padding: var(--spacing-lg);
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: var(--spacing-md);
    }
    
    .price-examples {
        grid-template-columns: 1fr;
    }
    
    .tips-grid {
        grid-template-columns: 1fr;
    }
    
    .tip-item {
        flex-direction: column;
        text-align: center;
    }
    
    .tip-icon {
        align-self: center;
    }
}
</style>

<script>
// Script spécifique à la publication
document.addEventListener('DOMContentLoaded', function() {
    // Calculateur de prix suggéré
    initPriceCalculator();
    
    // Validation du formulaire améliorée
    enhanceFormValidation();
    
    // Auto-complétion intelligente
    initSmartAutocomplete();
});

function initPriceCalculator() {
    const departInput = document.getElementById('ville_depart');
    const destinationInput = document.getElementById('ville_destination');
    const priceInput = document.getElementById('prix_par_place');
    
    function suggestPrice() {
        const depart = departInput.value.toLowerCase();
        const destination = destinationInput.value.toLowerCase();
        
        // Base de données des prix suggérés
        const priceRanges = {
            'dakar-thiès': [2000, 3000],
            'dakar-saint-louis': [4000, 6000],
            'dakar-kaolack': [3500, 5000],
            'dakar-ziguinchor': [8000, 12000],
            'dakar-mbour': [1500, 2500],
            'dakar-saly': [2000, 3000],
            'thiès-saint-louis': [3000, 4500],
            'thiès-kaolack': [2500, 3500]
        };
        
        const key1 = `${depart}-${destination}`;
        const key2 = `${destination}-${depart}`;
        
        const range = priceRanges[key1] || priceRanges[key2];
        
        if (range && !priceInput.value) {
            const suggestedPrice = Math.round((range[0] + range[1]) / 2 / 250) * 250; // Arrondir au 250 le plus proche
            priceInput.placeholder = `Suggéré: ${suggestedPrice} FCFA`;
        }
    }
    
    if (departInput && destinationInput && priceInput) {
        departInput.addEventListener('blur', suggestPrice);
        destinationInput.addEventListener('blur', suggestPrice);
    }
}

function enhanceFormValidation() {
    // Vérification en temps réel de la date/heure
    const dateInput = document.getElementById('date_trajet');
    const timeInput = document.getElementById('heure_depart');
    
    function validateDateTime() {
        if (dateInput.value && timeInput.value) {
            const selectedDateTime = new Date(dateInput.value + 'T' + timeInput.value);
            const minDateTime = new Date(Date.now() + 2 * 60 * 60 * 1000); // +2 heures
            
            if (selectedDateTime < minDateTime) {
                showFieldError(timeInput, 'Le trajet doit être programmé au moins 2 heures à l\'avance');
                return false;
            } else {
                clearFieldError(timeInput);
                return true;
            }
        }
        return true;
    }
    
    if (dateInput && timeInput) {
        dateInput.addEventListener('change', validateDateTime);
        timeInput.addEventListener('change', validateDateTime);
    }
    
    // Vérification que départ ≠ destination
    const departInput = document.getElementById('ville_depart');
    const destinationInput = document.getElementById('ville_destination');
    
    function validateRoute() {
        if (departInput.value && destinationInput.value && 
            departInput.value.toLowerCase() === destinationInput.value.toLowerCase()) {
            showFieldError(destinationInput, 'La destination doit être différente du point de départ');
            return false;
        } else {
            clearFieldError(destinationInput);
            return true;
        }
    }
    
    if (departInput && destinationInput) {
        departInput.addEventListener('blur', validateRoute);
        destinationInput.addEventListener('blur', validateRoute);
    }
}

function initSmartAutocomplete() {
    // Auto-complétion avec villes du Sénégal
    const villes = [
        'Dakar', 'Thiès', 'Saint-Louis', 'Kaolack', 'Ziguinchor',
        'Diourbel', 'Louga', 'Tambacounda', 'Kolda', 'Fatick',
        'Mbour', 'Saly', 'Touba', 'Rufisque', 'Tivaouane',
        'Mbacké', 'Kédougou', 'Sédhiou', 'Kaffrine', 'Matam'
    ];
    
    const cityInputs = document.querySelectorAll('[data-autocomplete="cities"]');
    
    cityInputs.forEach(input => {
        input.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            
            if (query.length >= 2) {
                const suggestions = villes.filter(ville => 
                    ville.toLowerCase().includes(query)
                ).slice(0, 5);
                
                showAutocompleteSuggestions(input, suggestions);
            } else {
                hideAutocompleteSuggestions(input);
            }
        });
        
        input.addEventListener('blur', function() {
            setTimeout(() => hideAutocompleteSuggestions(input), 200);
        });
    });
}

// Animation du bouton de soumission
document.querySelector('.publication-form').addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    setButtonLoading(submitBtn, true);
});
</script>

<?php include '../includes/footer.php'; ?>