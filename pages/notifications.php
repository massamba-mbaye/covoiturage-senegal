<?php
// Page des notifications
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Mes Notifications";
$page_description = "Consultez toutes vos notifications Covoiturage Sénégal.";

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    redirectWithError('../pages/connexion.php', 'Vous devez être connecté pour voir vos notifications.');
}

$user = getCurrentUser();
$errors = [];
$success_message = '';

// Traitement des actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_as_read') {
        $notification_id = (int)($_POST['notification_id'] ?? 0);
        if (markNotificationAsRead($notification_id, $user['id'])) {
            $success_message = "Notification marquée comme lue.";
        } else {
            $errors['general'] = "Erreur lors du marquage de la notification.";
        }
    }
    
    elseif ($action === 'mark_all_read') {
        if (markAllNotificationsAsRead($user['id'])) {
            $success_message = "Toutes les notifications ont été marquées comme lues.";
        } else {
            $errors['general'] = "Erreur lors du marquage des notifications.";
        }
    }
    
    elseif ($action === 'delete_notification') {
        $notification_id = (int)($_POST['notification_id'] ?? 0);
        if (deleteNotification($notification_id, $user['id'])) {
            $success_message = "Notification supprimée.";
        } else {
            $errors['general'] = "Erreur lors de la suppression.";
        }
    }
    
    // Rediriger pour éviter la re-soumission
    if (!empty($success_message)) {
        setFlashMessage($success_message, 'success');
    } elseif (!empty($errors['general'])) {
        setFlashMessage($errors['general'], 'error');
    }
    redirect($_SERVER['PHP_SELF']);
}

// Paramètres de pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtre par type
$filter_type = isset($_GET['type']) ? secure($_GET['type']) : 'all';

// Récupérer les notifications
$notifications = getUserNotifications($user['id'], $limit, $offset);

// Filtrer par type si nécessaire
if ($filter_type !== 'all') {
    $notifications = array_filter($notifications, function($notif) use ($filter_type) {
        if ($filter_type === 'unread') {
            return !$notif['lue'];
        } elseif ($filter_type === 'read') {
            return $notif['lue'];
        } else {
            return $notif['type'] === $filter_type;
        }
    });
}

// Compter les notifications
$total_notifications = count(getUserNotifications($user['id'], 1000)); // Estimation
$unread_count = getUnreadNotificationsCount($user['id']);

include '../includes/header.php';
?>

<div class="container">
    <div class="notifications-page">
        <!-- En-tête -->
        <div class="notifications-header" data-animate="fade-in">
            <h1>🔔 Mes Notifications</h1>
            
            <div class="notifications-actions">
                <?php if ($unread_count > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-outline btn-sm">
                            ✅ Tout marquer comme lu
                        </button>
                    </form>
                <?php endif; ?>
                
                <button class="btn btn-outline btn-sm" onclick="refreshNotifications()">
                    🔄 Actualiser
                </button>
            </div>
        </div>
        
        <!-- Statistiques rapides -->
        <div class="notifications-stats" data-animate="fade-in">
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_notifications; ?></span>
                <span class="stat-label">Total</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $unread_count; ?></span>
                <span class="stat-label">Non lues</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_notifications - $unread_count; ?></span>
                <span class="stat-label">Lues</span>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="notifications-filters" data-animate="fade-in">
            <a href="?type=all" class="filter-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                Toutes
            </a>
            <a href="?type=unread" class="filter-btn <?php echo $filter_type === 'unread' ? 'active' : ''; ?>">
                Non lues (<?php echo $unread_count; ?>)
            </a>
            <a href="?type=nouvelle_reservation" class="filter-btn <?php echo $filter_type === 'nouvelle_reservation' ? 'active' : ''; ?>">
                🎯 Réservations
            </a>
            <a href="?type=evaluation_recue" class="filter-btn <?php echo $filter_type === 'evaluation_recue' ? 'active' : ''; ?>">
                ⭐ Évaluations
            </a>
            <a href="?type=rappel_trajet" class="filter-btn <?php echo $filter_type === 'rappel_trajet' ? 'active' : ''; ?>">
                ⏰ Rappels
            </a>
        </div>
        
        <!-- Liste des notifications -->
        <?php if (!empty($notifications)): ?>
            <div class="notifications-list" data-animate="fade-in">
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $notification_data = $notification['data'] ? json_decode($notification['data'], true) : [];
                    $is_unread = !$notification['lue'];
                    $time_ago = time() - strtotime($notification['date_creation']);
                    
                    // Formater le temps écoulé
                    if ($time_ago < 60) {
                        $time_display = "À l'instant";
                    } elseif ($time_ago < 3600) {
                        $time_display = floor($time_ago / 60) . " min";
                    } elseif ($time_ago < 86400) {
                        $time_display = floor($time_ago / 3600) . "h";
                    } elseif ($time_ago < 604800) {
                        $time_display = floor($time_ago / 86400) . "j";
                    } else {
                        $time_display = formatDateFr($notification['date_creation']);
                    }
                    ?>
                    
                    <div class="notification-item notification-<?php echo $notification['type']; ?> <?php echo $is_unread ? 'unread' : ''; ?>" 
                         data-notification-id="<?php echo $notification['id']; ?>">
                        
                        <div class="notification-header">
                            <div class="notification-title">
                                <span class="notification-icon"><?php echo getNotificationIcon($notification['type']); ?></span>
                                <span><?php echo htmlspecialchars($notification['titre']); ?></span>
                            </div>
                            <span class="notification-date"><?php echo $time_display; ?></span>
                        </div>
                        
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        
                        <!-- Données supplémentaires -->
                        <?php if (!empty($notification_data)): ?>
                            <div class="notification-meta">
                                <?php if (isset($notification_data['trajet_id'])): ?>
                                    <a href="trajet-details.php?id=<?php echo $notification_data['trajet_id']; ?>" class="btn btn-outline btn-sm">
                                        👁️ Voir le trajet
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="notification-actions">
                            <?php if ($is_unread): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_as_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">
                                        ✅ Marquer comme lue
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette notification ?')">
                                <input type="hidden" name="action" value="delete_notification">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    🗑️ Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_notifications > $limit): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $filter_type; ?>" class="pagination-link">
                                ← Précédent
                            </a>
                        <?php endif; ?>
                        
                        <span class="pagination-current">Page <?php echo $page; ?></span>
                        
                        <?php if (count($notifications) >= $limit): ?>
                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $filter_type; ?>" class="pagination-link">
                                Suivant →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Aucune notification -->
            <div class="no-notifications" data-animate="fade-in">
                <div class="no-notifications-icon">🔔</div>
                <h3>
                    <?php if ($filter_type === 'unread'): ?>
                        Aucune notification non lue
                    <?php elseif ($filter_type !== 'all'): ?>
                        Aucune notification de ce type
                    <?php else: ?>
                        Aucune notification
                    <?php endif; ?>
                </h3>
                <p>
                    <?php if ($filter_type === 'all'): ?>
                        Vous recevrez des notifications pour les réservations, messages et autres événements importants.
                    <?php else: ?>
                        Essayez un autre filtre ou consultez toutes vos notifications.
                    <?php endif; ?>
                </p>
                
                <?php if ($filter_type !== 'all'): ?>
                    <a href="notifications.php" class="btn btn-primary" style="margin-top: var(--spacing-lg);">
                        Voir toutes les notifications
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Actions globales -->
        <div class="global-actions" data-animate="fade-in">
            <h3>⚙️ Paramètres des notifications</h3>
            <p>Gérez vos préférences de notification depuis votre <a href="profil.php">profil</a>.</p>
        </div>
    </div>
</div>

<!-- Styles spécifiques (déjà ajoutés au CSS) -->
<style>
.notifications-stats {
    display: flex;
    justify-content: center;
    gap: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    flex-wrap: wrap;
}

.notifications-stats .stat-item {
    text-align: center;
    padding: var(--spacing-lg);
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    min-width: 100px;
}

.notifications-stats .stat-number {
    display: block;
    font-size: 1.8rem;
    font-weight: var(--font-weight-bold);
    color: var(--primary-color);
}

.notifications-stats .stat-label {
    font-size: 0.9rem;
    color: var(--gray);
}

.global-actions {
    margin-top: var(--spacing-xxl);
    padding: var(--spacing-xl);
    background: var(--light-gray);
    border-radius: var(--border-radius-lg);
    text-align: center;
}

.global-actions h3 {
    color: var(--primary-color);
    margin-bottom: var(--spacing-md);
}

.pagination-container {
    margin-top: var(--spacing-xl);
    text-align: center;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: var(--spacing-md);
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

.pagination-current {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--primary-color);
    color: var(--white);
    border-radius: var(--border-radius);
    font-weight: var(--font-weight-medium);
}
</style>

<script>
// Script spécifique aux notifications
document.addEventListener('DOMContentLoaded', function() {
    initNotifications();
    
    // Auto-refresh toutes les 30 secondes
    setInterval(function() {
        updateNotificationBadge();
    }, 30000);
});

function initNotifications() {
    // Marquer automatiquement comme lue quand on clique sur une notification
    const notificationItems = document.querySelectorAll('.notification-item.unread');
    
    notificationItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Éviter le déclenchement si on clique sur un bouton
            if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
                return;
            }
            
            const notificationId = this.getAttribute('data-notification-id');
            markNotificationAsRead(notificationId);
        });
    });
}

function markNotificationAsRead(notificationId) {
    // Créer un formulaire invisible pour marquer comme lu
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'mark_as_read';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'notification_id';
    idInput.value = notificationId;
    
    form.appendChild(actionInput);
    form.appendChild(idInput);
    document.body.appendChild(form);
    
    form.submit();
}

function refreshNotifications() {
    window.location.reload();
}

function updateNotificationBadge() {
    // Récupérer le nouveau nombre de notifications via AJAX (à implémenter)
    // Pour l'instant, on recharge la page
    // fetch('/api/notifications-count.php')...
}
</script>

<?php include '../includes/footer.php'; ?>