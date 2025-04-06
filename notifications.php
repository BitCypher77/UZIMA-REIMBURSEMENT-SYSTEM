<?php
require 'config.php';
requireLogin();

// Log user activity
logUserActivity($_SESSION['user_id'], 'page_view', 'Viewed notifications');

// Mark notifications as read if requested
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $successMessage = "All notifications marked as read";
    } catch (PDOException $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
        $errorMessage = "Could not mark notifications as read. Please try again.";
    }
    
    // Redirect to remove the GET parameter
    header("Location: notifications.php");
    exit;
}

// Mark a single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND recipient_id = ?");
        $stmt->execute([$_GET['mark_read'], $_SESSION['user_id']]);
        $successMessage = "Notification marked as read";
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        $errorMessage = "Could not mark notification as read. Please try again.";
    }
    
    // Redirect to remove the GET parameter
    header("Location: notifications.php");
    exit;
}

// Fetch all notifications for the user
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE recipient_id = ? 
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
    
    // Count unread notifications
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (PDOException $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    $errorMessage = "Could not load notifications. Please try again.";
}

// Include header
include 'includes/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notifications</h1>
        
        <?php if (!empty($notifications)): ?>
            <a href="notifications.php?mark_all_read=1" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Mark All as Read
            </a>
        <?php endif; ?>
    </div>

    <?php if (isset($errorMessage)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <?php if (isset($successMessage)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= $successMessage ?>
        </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
            <div class="flex justify-center">
                <i data-lucide="bell-off" class="h-12 w-12 text-gray-400 dark:text-gray-600"></i>
            </div>
            <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">No notifications</h3>
            <p class="mt-1 text-gray-500 dark:text-gray-400">You don't have any notifications at the moment.</p>
        </div>
    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($notifications as $notification): ?>
                    <li class="<?= $notification['is_read'] ? 'bg-white dark:bg-gray-800' : 'bg-blue-50 dark:bg-blue-900/20' ?>">
                        <a href="<?= $notification['is_read'] ? '#' : 'notifications.php?mark_read=' . $notification['notification_id'] ?>" class="block hover:bg-gray-50 dark:hover:bg-gray-700">
                            <div class="px-4 py-4 sm:px-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <?php if ($notification['notification_type'] === 'claim_status'): ?>
                                            <i data-lucide="file-check" class="h-5 w-5 text-blue-500 dark:text-blue-400 mr-3"></i>
                                        <?php elseif ($notification['notification_type'] === 'approval'): ?>
                                            <i data-lucide="check-circle" class="h-5 w-5 text-green-500 dark:text-green-400 mr-3"></i>
                                        <?php elseif ($notification['notification_type'] === 'rejection'): ?>
                                            <i data-lucide="x-circle" class="h-5 w-5 text-red-500 dark:text-red-400 mr-3"></i>
                                        <?php elseif ($notification['notification_type'] === 'comment'): ?>
                                            <i data-lucide="message-circle" class="h-5 w-5 text-purple-500 dark:text-purple-400 mr-3"></i>
                                        <?php else: ?>
                                            <i data-lucide="bell" class="h-5 w-5 text-gray-500 dark:text-gray-400 mr-3"></i>
                                        <?php endif; ?>
                                        
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($notification['title']) ?>
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 text-sm text-gray-500 dark:text-gray-400">
                                        <?= time_elapsed_string($notification['created_at']) ?>
                                    </div>
                                </div>
                                <div class="mt-2 sm:flex sm:justify-between">
                                    <div class="sm:flex">
                                        <p class="text-sm text-gray-600 dark:text-gray-300">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();
</script>

<?php
// Helper function to display relative time
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Manually calculate weeks from days
    $weeks = floor($diff->d / 7);
    $diff->d -= $weeks * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($k === 'w') {
            if ($weeks) {
                $v = $weeks . ' ' . $v . ($weeks > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        } else {
            $key = $k;
            switch($k) {
                case 'y': $value = $diff->y; break;
                case 'm': $value = $diff->m; break;
                case 'd': $value = $diff->d; break;
                case 'h': $value = $diff->h; break;
                case 'i': $value = $diff->i; break;
                case 's': $value = $diff->s; break;
                default: $value = 0; break;
            }
            
            if ($value) {
                $v = $value . ' ' . $v . ($value > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

include 'includes/footer.php';
?> 