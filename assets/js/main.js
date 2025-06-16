// JavaScript Principal - Covoiturage Sénégal

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚗 Covoiturage Sénégal - Application chargée');
    
    // Initialisation de l'application
    initApp();
});

/**
 * Initialisation principale de l'application
 */
function initApp() {
    initMobileMenu();
    initDropdowns();
    initFlashMessages();
    initBackToTop();
    initFormValidation();
    initSearchFeatures();
    initTooltips();
    initAutoComplete();
    
    // Animation d'entrée
    animateOnScroll();
}

/**
 * Gestion du menu mobile
 */
function initMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenuToggle.classList.toggle('active');
            mobileMenu.classList.toggle('active');
            
            // Empêcher le scroll quand le menu est ouvert
            document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
        });
        
        // Fermer le menu si on clique en dehors
        document.addEventListener('click', function(e) {
            if (!mobileMenu.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                mobileMenuToggle.classList.remove('active');
                mobileMenu.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Fermer le menu sur les liens
        const mobileMenuLinks = mobileMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenuToggle.classList.remove('active');
                mobileMenu.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
    }
}

/**
 * Gestion des dropdowns
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Fermer tous les autres dropdowns
                dropdowns.forEach(other => {
                    if (other !== dropdown) {
                        other.classList.remove('active');
                    }
                });
                
                // Toggle le dropdown actuel
                dropdown.classList.toggle('active');
            });
        }
    });
    
    // Fermer les dropdowns si on clique ailleurs
    document.addEventListener('click', function() {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    });
}

/**
 * Gestion des messages flash
 */
function initFlashMessages() {
    const flashMessage = document.getElementById('flashMessage');
    
    if (flashMessage) {
        // Auto-fermeture après 5 secondes
        setTimeout(() => {
            closeFlashMessage();
        }, 5000);
    }
}

/**
 * Fermer le message flash
 */
function closeFlashMessage() {
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        flashMessage.style.opacity = '0';
        flashMessage.style.transform = 'translateY(-100%)';
        setTimeout(() => {
            flashMessage.remove();
        }, 300);
    }
}

/**
 * Bouton retour en haut
 */
function initBackToTop() {
    const backToTop = document.getElementById('backToTop');
    
    if (backToTop) {
        // Afficher/masquer selon le scroll
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
        
        // Action du clic
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
}

/**
 * Validation des formulaires
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
        
        // Validation en temps réel
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(input);
            });
            
            input.addEventListener('input', function() {
                // Supprimer l'erreur lors de la saisie
                clearFieldError(input);
            });
        });
    });
}

/**
 * Valider un formulaire complet
 */
function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

/**
 * Valider un champ spécifique
 */
function validateField(field) {
    const value = field.value.trim();
    const fieldType = field.type;
    const isRequired = field.hasAttribute('required');
    
    // Nettoyer les erreurs précédentes
    clearFieldError(field);
    
    // Vérifier si requis
    if (isRequired && !value) {
        showFieldError(field, 'Ce champ est requis.');
        return false;
    }
    
    if (value) {
        // Validation spécifique selon le type
        switch (fieldType) {
            case 'email':
                if (!isValidEmail(value)) {
                    showFieldError(field, 'Veuillez saisir une adresse email valide.');
                    return false;
                }
                break;
                
            case 'tel':
                if (!isValidSenegalPhone(value)) {
                    showFieldError(field, 'Veuillez saisir un numéro de téléphone sénégalais valide.');
                    return false;
                }
                break;
                
            case 'password':
                if (value.length < 6) {
                    showFieldError(field, 'Le mot de passe doit contenir au moins 6 caractères.');
                    return false;
                }
                break;
                
            case 'date':
                if (field.hasAttribute('data-future') && new Date(value) <= new Date()) {
                    showFieldError(field, 'La date doit être dans le futur.');
                    return false;
                }
                break;
        }
        
        // Validation personnalisée
        if (field.hasAttribute('data-min-length')) {
            const minLength = parseInt(field.getAttribute('data-min-length'));
            if (value.length < minLength) {
                showFieldError(field, `Ce champ doit contenir au moins ${minLength} caractères.`);
                return false;
            }
        }
        
        if (field.hasAttribute('data-max-length')) {
            const maxLength = parseInt(field.getAttribute('data-max-length'));
            if (value.length > maxLength) {
                showFieldError(field, `Ce champ ne peut pas dépasser ${maxLength} caractères.`);
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Afficher une erreur sur un champ
 */
function showFieldError(field, message) {
    field.classList.add('error');
    
    let errorElement = field.parentNode.querySelector('.form-error');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'form-error';
        field.parentNode.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
}

/**
 * Supprimer l'erreur d'un champ
 */
function clearFieldError(field) {
    field.classList.remove('error');
    const errorElement = field.parentNode.querySelector('.form-error');
    if (errorElement) {
        errorElement.remove();
    }
}

/**
 * Valider une adresse email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Valider un numéro de téléphone sénégalais
 */
function isValidSenegalPhone(phone) {
    // Nettoyer le numéro
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    
    // Formats acceptés: 77/78/76/75/33/70 suivis de 7 chiffres
    const phoneRegex = /^(77|78|76|75|33|70)[0-9]{7}$/;
    return phoneRegex.test(cleanPhone);
}

/**
 * Formater un numéro de téléphone sénégalais
 */
function formatSenegalPhone(phone) {
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    if (cleanPhone.length === 9) {
        return cleanPhone.replace(/(\d{2})(\d{3})(\d{2})(\d{2})/, '$1 $2 $3 $4');
    }
    return phone;
}

/**
 * Fonctionnalités de recherche
 */
function initSearchFeatures() {
    // Formulaire de recherche rapide
    const searchForm = document.getElementById('quickSearchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performQuickSearch();
        });
    }
    
    // Recherche en temps réel
    const searchInputs = document.querySelectorAll('[data-live-search]');
    searchInputs.forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                performLiveSearch(input);
            }, 300);
        });
    });
}

/**
 * Recherche rapide
 */
function performQuickSearch() {
    const form = document.getElementById('quickSearchForm');
    const depart = form.querySelector('[name="depart"]').value;
    const destination = form.querySelector('[name="destination"]').value;
    const date = form.querySelector('[name="date"]').value;
    
    if (!depart || !destination) {
        showNotification('Veuillez saisir le départ et la destination.', 'warning');
        return;
    }
    
    // Rediriger vers la page de recherche avec les paramètres
    const params = new URLSearchParams({
        depart: depart,
        destination: destination,
        date: date || ''
    });
    
    window.location.href = `/pages/recherche.php?${params.toString()}`;
}

/**
 * Recherche en temps réel
 */
function performLiveSearch(input) {
    const query = input.value.trim();
    const target = input.getAttribute('data-target');
    
    if (query.length < 2) {
        hideSearchResults(target);
        return;
    }
    
    // Simulation d'une recherche AJAX
    // En production, remplacer par un vrai appel AJAX
    const mockResults = getMockSearchResults(query);
    showSearchResults(target, mockResults);
}

/**
 * Résultats de recherche fictifs (à remplacer par de vrais appels AJAX)
 */
function getMockSearchResults(query) {
    const villes = [
        'Dakar', 'Thiès', 'Saint-Louis', 'Kaolack', 'Ziguinchor',
        'Diourbel', 'Louga', 'Tambacounda', 'Kolda', 'Fatick',
        'Mbour', 'Saly', 'Touba', 'Rufisque', 'Tivaouane'
    ];
    
    return villes.filter(ville => 
        ville.toLowerCase().includes(query.toLowerCase())
    ).slice(0, 5);
}

/**
 * Afficher les résultats de recherche
 */
function showSearchResults(target, results) {
    let resultsContainer = document.getElementById(target);
    
    if (!resultsContainer) {
        resultsContainer = document.createElement('div');
        resultsContainer.id = target;
        resultsContainer.className = 'search-results';
        document.body.appendChild(resultsContainer);
    }
    
    resultsContainer.innerHTML = '';
    
    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        item.textContent = result;
        item.addEventListener('click', function() {
            selectSearchResult(result);
            hideSearchResults(target);
        });
        resultsContainer.appendChild(item);
    });
    
    resultsContainer.style.display = results.length > 0 ? 'block' : 'none';
}

/**
 * Masquer les résultats de recherche
 */
function hideSearchResults(target) {
    const resultsContainer = document.getElementById(target);
    if (resultsContainer) {
        resultsContainer.style.display = 'none';
    }
}

/**
 * Sélectionner un résultat de recherche
 */
function selectSearchResult(result) {
    const activeInput = document.activeElement;
    if (activeInput && activeInput.hasAttribute('data-live-search')) {
        activeInput.value = result;
        activeInput.dispatchEvent(new Event('change'));
    }
}

/**
 * Auto-complétion des villes
 */
function initAutoComplete() {
    const cityInputs = document.querySelectorAll('[data-autocomplete="cities"]');
    
    cityInputs.forEach(input => {
        input.addEventListener('input', function() {
            const query = this.value.trim();
            
            if (query.length >= 2) {
                // Simulation - remplacer par un vrai appel AJAX
                const suggestions = getMockSearchResults(query);
                showAutocompleteSuggestions(input, suggestions);
            } else {
                hideAutocompleteSuggestions(input);
            }
        });
        
        input.addEventListener('blur', function() {
            // Délai pour permettre le clic sur les suggestions
            setTimeout(() => {
                hideAutocompleteSuggestions(input);
            }, 200);
        });
    });
}

/**
 * Afficher les suggestions d'auto-complétion
 */
function showAutocompleteSuggestions(input, suggestions) {
    let suggestionsList = input.parentNode.querySelector('.autocomplete-suggestions');
    
    if (!suggestionsList) {
        suggestionsList = document.createElement('ul');
        suggestionsList.className = 'autocomplete-suggestions';
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(suggestionsList);
    }
    
    suggestionsList.innerHTML = '';
    
    suggestions.forEach(suggestion => {
        const li = document.createElement('li');
        li.textContent = suggestion;
        li.addEventListener('click', function() {
            input.value = suggestion;
            hideAutocompleteSuggestions(input);
            input.focus();
        });
        suggestionsList.appendChild(li);
    });
    
    suggestionsList.style.display = suggestions.length > 0 ? 'block' : 'none';
}

/**
 * Masquer les suggestions d'auto-complétion
 */
function hideAutocompleteSuggestions(input) {
    const suggestionsList = input.parentNode.querySelector('.autocomplete-suggestions');
    if (suggestionsList) {
        suggestionsList.style.display = 'none';
    }
}

/**
 * Tooltips simples
 */
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            showTooltip(this);
        });
        
        element.addEventListener('mouseleave', function() {
            hideTooltip();
        });
    });
}

/**
 * Afficher un tooltip
 */
function showTooltip(element) {
    const text = element.getAttribute('data-tooltip');
    
    let tooltip = document.getElementById('tooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'tooltip';
        tooltip.className = 'tooltip';
        document.body.appendChild(tooltip);
    }
    
    tooltip.textContent = text;
    tooltip.style.display = 'block';
    
    // Positionner le tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
}

/**
 * Masquer le tooltip
 */
function hideTooltip() {
    const tooltip = document.getElementById('tooltip');
    if (tooltip) {
        tooltip.style.display = 'none';
    }
}

/**
 * Notifications
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">×</button>
    `;
    
    document.body.appendChild(notification);
    
    // Animation d'entrée
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Auto-suppression
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

/**
 * Animation au scroll
 */
function animateOnScroll() {
    const animatedElements = document.querySelectorAll('[data-animate]');
    
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    const animation = element.getAttribute('data-animate');
                    element.classList.add(animation);
                    observer.unobserve(element);
                }
            });
        });
        
        animatedElements.forEach(element => {
            observer.observe(element);
        });
    } else {
        // Fallback pour les navigateurs plus anciens
        animatedElements.forEach(element => {
            const animation = element.getAttribute('data-animate');
            element.classList.add(animation);
        });
    }
}

/**
 * Utilitaires pour les prix
 */
function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR').format(price) + ' FCFA';
}

/**
 * Utilitaires pour les dates
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

/**
 * Géolocalisation
 */
function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('La géolocalisation n\'est pas supportée'));
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            position => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                });
            },
            error => {
                reject(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 600000
            }
        );
    });
}

/**
 * Partager un trajet
 */
function shareTrajet(trajetId, title) {
    const url = `${window.location.origin}/pages/trajet.php?id=${trajetId}`;
    const text = `Découvrez ce trajet : ${title}`;
    
    if (navigator.share) {
        navigator.share({
            title: title,
            text: text,
            url: url
        });
    } else {
        // Fallback : copier dans le presse-papier
        navigator.clipboard.writeText(url).then(() => {
            showNotification('Lien copié dans le presse-papier !', 'success');
        });
    }
}

/**
 * Confirmation avant suppression
 */
function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer cet élément ?') {
    return confirm(message);
}

/**
 * Loading state pour les boutons
 */
function setButtonLoading(button, loading = true) {
    if (loading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Chargement...';
        button.classList.add('loading');
    } else {
        button.disabled = false;
        button.textContent = button.dataset.originalText || button.textContent;
        button.classList.remove('loading');
    }
}

// Exposition des fonctions utiles dans le scope global
window.closeFlashMessage = closeFlashMessage;
window.showNotification = showNotification;
window.formatPrice = formatPrice;
window.formatDate = formatDate;
window.shareTrajet = shareTrajet;
window.confirmDelete = confirmDelete;
window.setButtonLoading = setButtonLoading;