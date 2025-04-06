<?php
// Check for unread notifications
$unreadNotifications = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error checking notifications: " . $e->getMessage());
    }
}

// Check for pending approvals
$pendingApprovals = 0;
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin'])) {
    try {
        if ($_SESSION['role'] === 'Admin') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM claims WHERE status IN ('Submitted', 'Under Review')");
            $stmt->execute();
        } elseif ($_SESSION['role'] === 'FinanceOfficer') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM claims WHERE status = 'Submitted'");
            $stmt->execute();
        } else { // Manager
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM claims c
                JOIN departments d ON c.department_id = d.department_id
                WHERE c.status = 'Submitted' AND d.manager_id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
        }
        $pendingApprovals = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error checking approvals: " . $e->getMessage());
    }
}

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Main Navigation -->
<nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="dashboard.php" class="flex items-center">
                        <img class="h-8 w-auto" src="assets/images/uzima_logo.png" alt="Uzima">
                        <span class="ml-2 text-lg font-bold text-gray-900 dark:text-white">Uzima</span>
                    </a>
                </div>
                
                <!-- Navigation Links -->
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <a href="dashboard.php" 
                       class="<?= $currentPage === 'dashboard.php' ? 'border-blue-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-700 dark:hover:text-gray-200' ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i data-lucide="home" class="h-5 w-5 mr-1"></i>
                        Dashboard
                    </a>
                    
                    <?php if (in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin'])): ?>
                        <a href="reports.php" 
                           class="<?= $currentPage === 'reports.php' ? 'border-blue-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-700 dark:hover:text-gray-200' ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i data-lucide="bar-chart-2" class="h-5 w-5 mr-1"></i>
                            Reports
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin'])): ?>
                        <a href="approvals.php" 
                           class="<?= $currentPage === 'approvals.php' ? 'border-blue-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-700 dark:hover:text-gray-200' ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i data-lucide="check-circle" class="h-5 w-5 mr-1"></i>
                            Approvals
                            <?php if ($pendingApprovals > 0): ?>
                                <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                    <?= $pendingApprovals ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Navigation -->
            <div class="hidden sm:ml-6 sm:flex sm:items-center sm:space-x-4">
                <!-- New Claim Button (for employees) -->
                <?php if ($_SESSION['role'] === 'Employee'): ?>
                    <a href="submit_claim.php" 
                       class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-lucide="plus" class="h-4 w-4 mr-1"></i>
                        New Claim
                    </a>
                <?php endif; ?>
                
                <!-- Notifications -->
                <a href="notifications.php" class="relative p-1 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="sr-only">View notifications</span>
                    <i data-lucide="bell" class="h-6 w-6"></i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-gray-800"></span>
                    <?php endif; ?>
                </a>
                
                <!-- Messages -->
                <a href="messages.php" class="relative p-1 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="sr-only">View messages</span>
                    <i data-lucide="mail" class="h-6 w-6"></i>
                </a>
                
                <!-- Profile dropdown -->
                <div class="relative">
                    <div>
                        <button type="button" class="bg-white dark:bg-gray-800 rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">Open user menu</span>
                            <div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-700 dark:text-gray-300">
                                <?= substr($_SESSION['fullName'], 0, 1) ?>
                            </div>
                        </button>
                    </div>
                    
                    <!-- Dropdown menu -->
                    <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 focus:outline-none z-10" id="user-menu" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                        <div class="block px-4 py-2 text-xs text-gray-400 dark:text-gray-500">
                            Signed in as <strong><?= $_SESSION['email'] ?></strong>
                        </div>
                        <a href="dashboard.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" role="menuitem">
                            Dashboard
                        </a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" role="menuitem">
                            Profile
                        </a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" role="menuitem">
                            Settings
                        </a>
                        <div class="border-t border-gray-200 dark:border-gray-700"></div>
                        <a href="index.php?logout=1" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" role="menuitem">
                            Sign out
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Mobile menu button -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500" aria-controls="mobile-menu" aria-expanded="false" id="mobile-menu-button">
                    <span class="sr-only">Open main menu</span>
                    <i data-lucide="menu" class="h-6 w-6"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="hidden sm:hidden" id="mobile-menu">
        <div class="pt-2 pb-3 space-y-1">
            <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-700 dark:text-blue-300' : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-800 dark:hover:text-gray-100' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Dashboard
            </a>
            
            <?php if (in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin'])): ?>
                <a href="reports.php" class="<?= $currentPage === 'reports.php' ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-700 dark:text-blue-300' : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-800 dark:hover:text-gray-100' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Reports
                </a>
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin'])): ?>
                <a href="approvals.php" class="<?= $currentPage === 'approvals.php' ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-700 dark:text-blue-300' : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-800 dark:hover:text-gray-100' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Approvals
                    <?php if ($pendingApprovals > 0): ?>
                        <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                            <?= $pendingApprovals ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            
            <a href="notifications.php" class="<?= $currentPage === 'notifications.php' ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-700 dark:text-blue-300' : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-800 dark:hover:text-gray-100' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Notifications
                <?php if ($unreadNotifications > 0): ?>
                    <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                        <?= $unreadNotifications ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="messages.php" class="<?= $currentPage === 'messages.php' ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-700 dark:text-blue-300' : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-800 dark:hover:text-gray-100' ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Messages
            </a>
            
            <?php if ($_SESSION['role'] === 'Employee'): ?>
                <a href="submit_claim.php" class="border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-800 dark:hover:text-gray-100 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    New Claim
                </a>
            <?php endif; ?>
        </div>
        
        <div class="pt-4 pb-3 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center px-4">
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-700 dark:text-gray-300">
                        <?= substr($_SESSION['fullName'], 0, 1) ?>
                    </div>
                </div>
                <div class="ml-3">
                    <div class="text-base font-medium text-gray-800 dark:text-white"><?= $_SESSION['fullName'] ?></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= $_SESSION['email'] ?></div>
                </div>
            </div>
            <div class="mt-3 space-y-1">
                <a href="#" class="block px-4 py-2 text-base font-medium text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                    Profile
                </a>
                <a href="#" class="block px-4 py-2 text-base font-medium text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                    Settings
                </a>
                <a href="index.php?logout=1" class="block px-4 py-2 text-base font-medium text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                    Sign out
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    });
    
    // User dropdown toggle
    document.getElementById('user-menu-button').addEventListener('click', function() {
        const dropdown = document.getElementById('user-menu');
        dropdown.classList.toggle('hidden');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const userMenu = document.getElementById('user-menu');
        const userMenuButton = document.getElementById('user-menu-button');
        
        if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
            userMenu.classList.add('hidden');
        }
    });
</script>
