<?php
/**
 * Service de notifications pour Covoiturage Sénégal
 * 
 * @author Votre nom
 * @version 1.0
 */

class NotificationService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Envoyer une notification (utilise votre structure existante)
     */
    public function envoyerNotification($userId, $type, $data = [], $canaux = ['sms', 'in_app']) {
        try {
            // 1. Générer le titre et message depuis les templates
            $contenu = $this->genererContenu($type, $data);
            
            // 2. Insérer dans votre table notifications existante
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, titre, message, data, date_creation) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId, 
                $type, 
                $contenu['titre'], 
                $contenu['message'], 
                json_encode($data)
            ]);
            
            $notificationId = $this->pdo->lastInsertId();
            
            // 3. Envoyer via les canaux demandés
            $this->envoyerViaCanaux($userId, $notificationId, $type, $contenu, $canaux);
            
            return $notificationId;
            
        } catch(Exception $e) {
            error_log("Erreur notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Nouvelle réservation - Notifier le chauffeur
     */
    public function nouvelleReservation($chauffeurId, $reservationData) {
        $data = [
            'passager_nom' => $reservationData['passager_prenom'] . ' ' . substr($reservationData['passager_nom'], 0, 1) . '.',
            'trajet_route' => $reservationData['ville_depart'] . ' → ' . $reservationData['ville_destination'],
            'date' => formatDateFr($reservationData['date_trajet']),
            'heure' => date('H:i', strtotime($reservationData['heure_depart'])),
            'nb_places' => $reservationData['nombre_places'],
            'trajet_id' => $reservationData['trajet_id'],
            'reservation_id' => $reservationData['reservation_id']
        ];
        
        return $this->envoyerNotification(
            $chauffeurId, 
            'nouvelle_reservation', 
            $data,
            ['sms', 'in_app']
        );
    }
    
    /**
     * Réservation confirmée - Notifier le passager
     */
    public function reservationConfirmee($passagerId, $reservationData) {
        $data = [
            'trajet_route' => $reservationData['ville_depart'] . ' → ' . $reservationData['ville_destination'],
            'date' => formatDateFr($reservationData['date_trajet']),
            'heure' => date('H:i', strtotime($reservationData['heure_depart'])),
            'chauffeur_nom' => $reservationData['chauffeur_prenom'],
            'point_rdv' => $reservationData['point_depart_precis'] ?: $reservationData['ville_depart'],
            'prix_total' => formatPrice($reservationData['prix_total'])
        ];
        
        return $this->envoyerNotification(
            $passagerId, 
            'reservation_confirmee', 
            $data,
            ['sms', 'email', 'in_app']
        );
    }
    
    /**
     * Rappels automatiques (à exécuter via cron)
     */
    public function envoyerRappelsAutomatiques() {
        echo "🔔 Envoi des rappels automatiques...\n";
        
        $this->rappels24Heures();
        $this->rappels2Heures();
        
        echo "✅ Rappels envoyés avec succès !\n";
    }
    
    /**
     * Rappels 24h avant le trajet
     */
    private function rappels24Heures() {
        // Rappels pour les chauffeurs
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.prenom, u.nom, u.id as chauffeur_id
            FROM trajets t
            JOIN users u ON t.chauffeur_id = u.id
            WHERE DATE(t.date_trajet) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            AND t.statut = 'actif'
            AND NOT EXISTS (
                SELECT 1 FROM notifications n 
                WHERE n.user_id = t.chauffeur_id 
                AND n.type = 'rappel_trajet'
                AND JSON_EXTRACT(n.data, '$.trajet_id') = t.id
                AND DATE(n.date_creation) = CURDATE()
            )
        ");
        
        $stmt->execute();
        $trajets = $stmt->fetchAll();
        
        foreach ($trajets as $trajet) {
            $data = [
                'trajet_route' => $trajet['ville_depart'] . ' → ' . $trajet['ville_destination'],
                'date' => formatDateFr($trajet['date_trajet']),
                'heure' => date('H:i', strtotime($trajet['heure_depart'])),
                'message_specifique' => 'Vos passagers comptent sur vous !',
                'trajet_id' => $trajet['id']
            ];
            
            $this->envoyerNotification($trajet['chauffeur_id'], 'rappel_trajet', $data, ['sms']);
            echo "📱 Rappel envoyé au chauffeur {$trajet['prenom']}\n";
        }
        
        // Rappels pour les passagers
        $stmt = $this->pdo->prepare("
            SELECT r.*, t.*, u_chauffeur.prenom as chauffeur_prenom, r.passager_id
            FROM reservations r
            JOIN trajets t ON r.trajet_id = t.id
            JOIN users u_chauffeur ON t.chauffeur_id = u_chauffeur.id
            WHERE DATE(t.date_trajet) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            AND r.statut = 'confirmee'
            AND NOT EXISTS (
                SELECT 1 FROM notifications n 
                WHERE n.user_id = r.passager_id 
                AND n.type = 'rappel_trajet'
                AND JSON_EXTRACT(n.data, '$.reservation_id') = r.id
                AND DATE(n.date_creation) = CURDATE()
            )
        ");
        
        $stmt->execute();
        $reservations = $stmt->fetchAll();
        
        foreach ($reservations as $reservation) {
            $data = [
                'trajet_route' => $reservation['ville_depart'] . ' → ' . $reservation['ville_destination'],
                'date' => formatDateFr($reservation['date_trajet']),
                'heure' => date('H:i', strtotime($reservation['heure_depart'])),
                'chauffeur_nom' => $reservation['chauffeur_prenom'],
                'message_specifique' => 'Préparez-vous pour demain !',
                'reservation_id' => $reservation['id']
            ];
            
            $this->envoyerNotification($reservation['passager_id'], 'rappel_trajet', $data, ['sms']);
            echo "📱 Rappel envoyé au passager\n";
        }
    }
    
    /**
     * Rappels 2h avant (plus urgent)
     */
    private function rappels2Heures() {
        $stmt = $this->pdo->prepare("
            SELECT t.*, r.passager_id, r.id as reservation_id, u.prenom as chauffeur_prenom
            FROM trajets t
            LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut = 'confirmee'
            JOIN users u ON t.chauffeur_id = u.id
            WHERE DATE(t.date_trajet) = CURDATE()
            AND TIME(t.heure_depart) BETWEEN TIME(DATE_ADD(NOW(), INTERVAL 2 HOUR)) 
                                         AND TIME(DATE_ADD(NOW(), INTERVAL 3 HOUR))
            AND t.statut = 'actif'
        ");
        
        $stmt->execute();
        $trajets = $stmt->fetchAll();
        
        foreach ($trajets as $trajet) {
            $data = [
                'trajet_route' => $trajet['ville_depart'] . ' → ' . $trajet['ville_destination'],
                'heure' => date('H:i', strtotime($trajet['heure_depart'])),
                'point_rdv' => $trajet['point_depart_precis'] ?: $trajet['ville_depart']
            ];
            
            if ($trajet['passager_id']) {
                // Message pour passager
                $data['message'] = "🚨 Votre trajet commence dans 2h ! RDV {$data['point_rdv']} à {$data['heure']}";
                $this->envoyerNotification($trajet['passager_id'], 'rappel_trajet', $data, ['sms']);
            }
        }
    }
    
    /**
     * Obtenir notifications non lues pour un utilisateur
     */
    public function obtenirNotificationsNonLues($userId, $limite = 20) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND lue = FALSE 
            ORDER BY date_creation DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limite]);
        return $stmt->fetchAll();
    }
    
    /**
     * Marquer les notifications comme lues
     */
    public function marquerCommeLues($userId) {
        try {
            $stmt = $this->pdo->prepare("CALL MarquerNotificationsLues(?)");
            $stmt->execute([$userId]);
            return true;
        } catch(Exception $e) {
            error_log("Erreur marquage notifications lues: " . $e->getMessage());
            return false;
        }
    }
    
    // Méthodes privées pour la génération de contenu
    private function genererContenu($type, $data) {
        $templates = [
            'nouvelle_reservation' => [
                'titre' => '🎯 Nouvelle réservation !',
                'message' => '{passager_nom} souhaite réserver {nb_places} place(s) pour votre trajet {trajet_route} le {date}'
            ],
            'reservation_confirmee' => [
                'titre' => '✅ Réservation confirmée !',
                'message' => 'Votre réservation pour le trajet {trajet_route} le {date} à {heure} est confirmée'
            ],
            'rappel_trajet' => [
                'titre' => '⏰ Rappel de trajet',
                'message' => 'Votre trajet {trajet_route} a lieu demain à {heure}. {message_specifique}'
            ]
        ];
        
        $template = $templates[$type] ?? [
            'titre' => 'Nouvelle notification',
            'message' => 'Vous avez une nouvelle notification'
        ];
        
        // Remplacer les variables
        $titre = $this->remplacerVariables($template['titre'], $data);
        $message = $this->remplacerVariables($template['message'], $data);
        
        return [
            'titre' => $titre,
            'message' => $message
        ];
    }
    
    private function remplacerVariables($texte, $data) {
        foreach ($data as $cle => $valeur) {
            $texte = str_replace('{' . $cle . '}', $valeur, $texte);
        }
        return $texte;
    }
    
    private function envoyerViaCanaux($userId, $notificationId, $type, $contenu, $canaux) {
        $user = $this->obtenirUtilisateur($userId);
        
        foreach ($canaux as $canal) {
            $succes = $this->envoyerViaCanal($user, $contenu, $canal);
            // Logger si besoin
        }
    }
    
    private function envoyerViaCanal($user, $contenu, $canal) {
        switch ($canal) {
            case 'sms':
                return $this->envoyerSMS($user['telephone'], $contenu['message']);
            case 'email':
                if ($user['email']) {
                    return $this->envoyerEmail($user['email'], $contenu['titre'], $contenu['message']);
                }
                return false;
            case 'in_app':
                return true; // Déjà stocké en BDD
            default:
                return false;
        }
    }
    
    private function envoyerSMS($telephone, $message) {
        // IMPORTANT: Remplacer par votre vraie API SMS
        error_log("SMS vers $telephone: $message");
        
        // Exemple avec une API SMS sénégalaise
        /*
        $apiUrl = 'https://api.orange.sn/smsmessaging/v1/outbound/tel%3A%2B221xxxxxxx/requests';
        $apiKey = 'votre_api_key_orange';
        
        $data = [
            'outboundSMSMessageRequest' => [
                'address' => ['tel:+221' . $telephone],
                'senderAddress' => 'tel:+221xxxxxxx',
                'outboundSMSTextMessage' => ['message' => $message]
            ]
        ];
        
        $options = [
            'http' => [
                'header' => [
                    'Content-type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ],
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];
        
        $response = file_get_contents($apiUrl, false, stream_context_create($options));
        return $response !== false;
        */
        
        return true; // Simulation pour le développement
    }
    
    private function envoyerEmail($email, $titre, $message) {
        // Utiliser votre système d'email existant ou PHPMailer
        error_log("Email vers $email: $titre - $message");
        return true;
    }
    
    private function obtenirUtilisateur($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}
?>