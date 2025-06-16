</main>
    
    <!-- Pied de page -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <!-- Section À propos -->
                <div class="footer-section">
                    <h3>À propos</h3>
                    <p><?php echo SITE_NAME; ?> connecte chauffeurs et passagers pour faciliter les déplacements à travers tout le Sénégal.</p>
                    <div class="footer-social">
                        <a href="#" class="social-link" title="Facebook">📘</a>
                        <a href="#" class="social-link" title="WhatsApp">📱</a>
                        <a href="#" class="social-link" title="Instagram">📸</a>
                    </div>
                </div>
                
                <!-- Section Liens rapides -->
                <div class="footer-section">
                    <h3>Liens rapides</h3>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>">Accueil</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/recherche.php">Rechercher un trajet</a></li>
                        <?php if (!isLoggedIn()): ?>
                            <li><a href="<?php echo SITE_URL; ?>/pages/inscription.php">Devenir chauffeur</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/pages/inscription.php">S'inscrire</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Section Support -->
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>/pages/aide.php">Centre d'aide</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/contact.php">Nous contacter</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/securite.php">Conseils sécurité</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/tarifs.php">Grille tarifaire</a></li>
                    </ul>
                </div>
                
                <!-- Section Contact -->
                <div class="footer-section">
                    <h3>Contact</h3>
                    <div class="contact-info">
                        <p>📞 <a href="tel:+221771234567">+221 77 123 45 67</a></p>
                        <p>📧 <a href="mailto:contact@covoiturage-senegal.com">contact@covoiturage-senegal.com</a></p>
                        <p>📍 Dakar, Sénégal</p>
                    </div>
                </div>
            </div>
            
            <!-- Ligne de séparation -->
            <hr class="footer-divider">
            
            <!-- Copyright et liens légaux -->
            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tous droits réservés.</p>
                    <p class="slogan">Connecter le Sénégal, un trajet à la fois 🇸🇳</p>
                </div>
                
                <div class="legal-links">
                    <a href="<?php echo SITE_URL; ?>/pages/conditions.php">Conditions d'utilisation</a>
                    <a href="<?php echo SITE_URL; ?>/pages/confidentialite.php">Politique de confidentialité</a>
                    <a href="<?php echo SITE_URL; ?>/pages/mentions-legales.php">Mentions légales</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bouton retour en haut -->
    <button class="back-to-top" id="backToTop" title="Retour en haut">
        ↑
    </button>
    
    <!-- Scripts JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <!-- Script pour la géolocalisation si nécessaire -->
    <?php if (isset($need_geolocation) && $need_geolocation): ?>
    <script>
        // Fonctionnalité de géolocalisation pour les points de rendez-vous
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        // Utiliser les coordonnées
                        console.log('Position:', lat, lng);
                    },
                    function(error) {
                        console.log('Erreur géolocalisation:', error.message);
                    }
                );
            }
        }
    </script>
    <?php endif; ?>
    
    <!-- Analytics (à remplacer par votre code) -->
    <script>
        // Google Analytics ou autre outil d'analyse
        // gtag('config', 'GA_MEASUREMENT_ID');
    </script>
    
    <!-- Service Worker pour le mode hors ligne -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?php echo SITE_URL; ?>/sw.js')
                .then(function(registration) {
                    console.log('Service Worker registered successfully');
                })
                .catch(function(error) {
                    console.log('Service Worker registration failed');
                });
        }
    </script>
</body>
</html>