<?php
// Fonctions utilitaires - Covoiturage Sénégal

/**
 * Sécuriser les données utilisateur
 */
function secure($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifier si l'utilisateur est un chauffeur
 */
function isChauffeur() {
    return isLoggedIn() && $_SESSION['user_type'] === 'chauffeur';
}

/**
 * Vérifier si l'utilisateur est un admin
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

/**
 * Obtenir les informations de l'utilisateur connecté
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Rediriger vers une page
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Rediriger avec un message d'erreur
 */
function redirectWithError($url, $message) {
    setFlashMessage($message, 'error');
    redirect($url);
}

/**
 * Rediriger avec un message de succès
 */
function redirectWithSuccess($url, $message) {
    setFlashMessage($message, 'success');
    redirect($url);
}

/**
 * Gérer les messages flash
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return false;
}

/**
 * Valider un numéro de téléphone sénégalais
 */
function validateSenegalPhone($phone) {
    // Nettoyer le numéro
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Formats acceptés: 77/78/76/75/33/70 suivis de 7 chiffres
    $pattern = '/^(77|78|76|75|33|70)[0-9]{7}$/';
    return preg_match($pattern, $phone);
}

/**
 * Formater un numéro de téléphone sénégalais
 */
function formatSenegalPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 9) {
        return substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5, 2) . ' ' . substr($phone, 7, 2);
    }
    return $phone;
}

/**
 * Valider une adresse email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hacher un mot de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Vérifier un mot de passe
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Générer un token aléatoire
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Formater un prix en FCFA
 */
function formatPrice($price) {
    return number_format($price, 0, ',', ' ') . ' FCFA';
}

/**
 * Formater une date en français
 */
function formatDateFr($date) {
    $mois = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
    ];
    
    $timestamp = is_string($date) ? strtotime($date) : $date;
    $jour = date('j', $timestamp);
    $moisNum = (int)date('n', $timestamp);
    $annee = date('Y', $timestamp);
    
    return $jour . ' ' . $mois[$moisNum] . ' ' . $annee;
}

/**
 * Calculer l'âge à partir d'une date de naissance
 */
function calculateAge($birthDate) {
    $today = new DateTime();
    $birth = new DateTime($birthDate);
    return $today->diff($birth)->y;
}

/**
 * Vérifier si une date est dans le futur
 */
function isFutureDate($date) {
    return strtotime($date) > time();
}

/**
 * Obtenir les villes du Sénégal
 */
function getVilles() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM villes WHERE actif = 1 ORDER BY nom");
    return $stmt->fetchAll();
}

/**
 * Rechercher des trajets
 */
function searchTrajets($depart, $destination, $date = null) {
    global $pdo;
    
    $sql = "SELECT t.*, u.nom as chauffeur_nom, u.prenom as chauffeur_prenom, 
                   u.note_moyenne, u.telephone as chauffeur_telephone
            FROM trajets t 
            JOIN users u ON t.chauffeur_id = u.id 
            WHERE t.ville_depart = ? AND t.ville_destination = ? 
                  AND t.statut = 'actif' AND t.places_disponibles > 0";
    
    $params = [$depart, $destination];
    
    if ($date) {
        $sql .= " AND t.date_trajet = ?";
        $params[] = $date;
    } else {
        $sql .= " AND t.date_trajet >= CURDATE()";
    }
    
    $sql .= " ORDER BY t.date_trajet, t.heure_depart";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Obtenir les trajets d'un chauffeur
 */
function getChauffeurTrajets($chauffeurId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, COUNT(r.id) as nb_reservations 
        FROM trajets t 
        LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut = 'confirmee'
        WHERE t.chauffeur_id = ? 
        GROUP BY t.id 
        ORDER BY t.date_trajet DESC, t.heure_depart DESC
    ");
    $stmt->execute([$chauffeurId]);
    return $stmt->fetchAll();
}

/**
 * Obtenir les réservations d'un passager
 */
function getPassagerReservations($passagerId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*, t.*, u.nom as chauffeur_nom, u.prenom as chauffeur_prenom, u.telephone as chauffeur_telephone
        FROM reservations r
        JOIN trajets t ON r.trajet_id = t.id
        JOIN users u ON t.chauffeur_id = u.id
        WHERE r.passager_id = ?
        ORDER BY t.date_trajet DESC, t.heure_depart DESC
    ");
    $stmt->execute([$passagerId]);
    return $stmt->fetchAll();
}

/**
 * Vérifier si l'utilisateur peut annuler une réservation
 */
function canCancelReservation($trajetDate, $trajetHeure) {
    $trajetDateTime = $trajetDate . ' ' . $trajetHeure;
    $trajetTimestamp = strtotime($trajetDateTime);
    $limitTimestamp = time() + (DELAI_ANNULATION_HEURES * 3600);
    
    return $trajetTimestamp > $limitTimestamp;
}

/**
 * Envoyer une notification par email (simulation)
 */
function sendEmailNotification($to, $subject, $message) {
    // En production, utilisez une vraie librairie d'email comme PHPMailer
    // Pour l'instant, on log juste
    error_log("Email to $to: $subject - $message");
    return true;
}

/**
 * Envoyer une notification SMS (simulation)
 */
function sendSMSNotification($phone, $message) {
    // En production, intégrez une API SMS sénégalaise
    error_log("SMS to $phone: $message");
    return true;
}

/**
 * Logger les actions utilisateur
 */
function logUserAction($userId, $action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, details, date_action) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $details]);
    } catch(PDOException $e) {
        error_log("Erreur log: " . $e->getMessage());
    }
}

/**
 * Nettoyer et valider les données d'entrée
 */
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = isset($data[$field]) ? trim($data[$field]) : '';
        
        // Requis
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = "Le champ " . ($rule['label'] ?? $field) . " est requis.";
            continue;
        }
        
        // Longueur minimale
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            $errors[$field] = "Le champ " . ($rule['label'] ?? $field) . " doit contenir au moins " . $rule['min_length'] . " caractères.";
        }
        
        // Longueur maximale
        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
            $errors[$field] = "Le champ " . ($rule['label'] ?? $field) . " ne peut pas dépasser " . $rule['max_length'] . " caractères.";
        }
        
        // Email
        if (isset($rule['email']) && $rule['email'] && !empty($value) && !validateEmail($value)) {
            $errors[$field] = "Veuillez saisir une adresse email valide.";
        }
        
        // Téléphone sénégalais
        if (isset($rule['senegal_phone']) && $rule['senegal_phone'] && !empty($value) && !validateSenegalPhone($value)) {
            $errors[$field] = "Veuillez saisir un numéro de téléphone sénégalais valide.";
        }
        
        // Date future
        if (isset($rule['future_date']) && $rule['future_date'] && !empty($value) && !isFutureDate($value)) {
            $errors[$field] = "La date doit être dans le futur.";
        }
    }
    
    return $errors;
}

/**
 * Pagination
 */
function paginate($totalItems, $itemsPerPage, $currentPage) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

// Fonctions pour le système de notifications
// À ajouter à la fin du fichier includes/functions.php

/**
 * Créer une nouvelle notification
 */
function createNotification($user_id, $type, $titre, $message, $data = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, titre, message, data, date_creation) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $data_json = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt->execute([$user_id, $type, $titre, $message, $data_json]);
        
        return $pdo->lastInsertId();
        
    } catch(PDOException $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir le nombre de notifications non lues
 */
function getUnreadNotificationsCount($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND lue = FALSE");
        $stmt->execute([$user_id]);
        return $stmt->fetch()['count'];
    } catch(PDOException $e) {
        return 0;
    }
}

/**
 * Obtenir les notifications d'un utilisateur
 */
function getUserNotifications($user_id, $limit = 20, $offset = 0) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY date_creation DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Marquer une notification comme lue
 */
function markNotificationAsRead($notification_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lue = TRUE, date_lecture = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    } catch(PDOException $e) {
        return false;
    }
}

/**
 * Marquer toutes les notifications comme lues
 */
function markAllNotificationsAsRead($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lue = TRUE, date_lecture = NOW() 
            WHERE user_id = ? AND lue = FALSE
        ");
        return $stmt->execute([$user_id]);
    } catch(PDOException $e) {
        return false;
    }
}

/**
 * Supprimer une notification
 */
function deleteNotification($notification_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    } catch(PDOException $e) {
        return false;
    }
}

/**
 * Notifications spécifiques pour les événements
 */

// Notification pour nouvelle réservation (chauffeur)
function notifyNewReservation($chauffeur_id, $trajet, $passager) {
    $titre = "🎯 Nouvelle réservation !";
    $message = "{$passager['prenom']} {$passager['nom']} vient de réserver votre trajet {$trajet['ville_depart']} → {$trajet['ville_destination']}";
    $data = [
        'trajet_id' => $trajet['id'],
        'passager_id' => $passager['id'],
        'passager_nom' => $passager['prenom'] . ' ' . $passager['nom']
    ];
    
    return createNotification($chauffeur_id, 'nouvelle_reservation', $titre, $message, $data);
}

// Notification pour réservation confirmée (passager)
function notifyReservationConfirmed($passager_id, $trajet, $chauffeur) {
    $titre = "✅ Réservation confirmée !";
    $message = "Votre réservation pour le trajet {$trajet['ville_depart']} → {$trajet['ville_destination']} a été confirmée par {$chauffeur['prenom']}";
    $data = [
        'trajet_id' => $trajet['id'],
        'chauffeur_id' => $chauffeur['id'],
        'chauffeur_nom' => $chauffeur['prenom'] . ' ' . $chauffeur['nom']
    ];
    
    return createNotification($passager_id, 'reservation_confirmee', $titre, $message, $data);
}

// Notification pour réservation annulée
function notifyReservationCancelled($user_id, $trajet, $cancelled_by, $type) {
    if ($type === 'by_passenger') {
        $titre = "❌ Réservation annulée";
        $message = "La réservation pour votre trajet {$trajet['ville_depart']} → {$trajet['ville_destination']} a été annulée par le passager";
    } else {
        $titre = "❌ Réservation annulée";
        $message = "Votre réservation pour le trajet {$trajet['ville_depart']} → {$trajet['ville_destination']} a été annulée";
    }
    
    $data = [
        'trajet_id' => $trajet['id'],
        'cancelled_by' => $cancelled_by
    ];
    
    return createNotification($user_id, 'reservation_annulee', $titre, $message, $data);
}

// Notification pour trajet annulé
function notifyTrajetCancelled($passager_id, $trajet, $chauffeur) {
    $titre = "🚫 Trajet annulé";
    $message = "Le trajet {$trajet['ville_depart']} → {$trajet['ville_destination']} du " . formatDateFr($trajet['date_trajet']) . " a été annulé par le chauffeur";
    $data = [
        'trajet_id' => $trajet['id'],
        'chauffeur_id' => $chauffeur['id']
    ];
    
    return createNotification($passager_id, 'trajet_annule', $titre, $message, $data);
}

// Notification pour nouvelle évaluation
function notifyNewEvaluation($user_id, $note, $commentaire, $evaluateur_nom) {
    $titre = "⭐ Nouvelle évaluation !";
    $message = "Vous avez reçu une évaluation " . str_repeat('⭐', $note) . " de {$evaluateur_nom}";
    if ($commentaire) {
        $message .= " : \"$commentaire\"";
    }
    
    $data = [
        'note' => $note,
        'commentaire' => $commentaire,
        'evaluateur' => $evaluateur_nom
    ];
    
    return createNotification($user_id, 'evaluation_recue', $titre, $message, $data);
}

// Notification de rappel de trajet
function notifyTrajetReminder($user_id, $trajet, $hours_before = 2) {
    $titre = "⏰ Rappel de trajet";
    $message = "N'oubliez pas : votre trajet {$trajet['ville_depart']} → {$trajet['ville_destination']} commence dans {$hours_before}h";
    $data = [
        'trajet_id' => $trajet['id'],
        'hours_before' => $hours_before
    ];
    
    return createNotification($user_id, 'rappel_trajet', $titre, $message, $data);
}

/**
 * Obtenir l'icône selon le type de notification
 */
function getNotificationIcon($type) {
    $icons = [
        'nouvelle_reservation' => '🎯',
        'reservation_confirmee' => '✅',
        'reservation_annulee' => '❌',
        'trajet_annule' => '🚫',
        'evaluation_recue' => '⭐',
        'message_recu' => '💬',
        'rappel_trajet' => '⏰'
    ];
    
    return $icons[$type] ?? '🔔';
}

/**
 * Nettoyer les anciennes notifications (à appeler périodiquement)
 */
function cleanOldNotifications($days = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE date_creation < DATE_SUB(NOW(), INTERVAL ? DAY)");
        return $stmt->execute([$days]);
    } catch(PDOException $e) {
        return false;
    }
}
?>