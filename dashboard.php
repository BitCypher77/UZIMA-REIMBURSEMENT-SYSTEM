<?php
require 'config.php';
requireLogin();

// Log user activity
logUserActivity($_SESSION['user_id'], 'page_view', 'Viewed dashboard');

// Fetch claim statistics
try {
    // Total Claims
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total FROM claims WHERE userID = ? OR (? IN (SELECT manager_id FROM departments WHERE department_id = claims.department_id) AND status != 'Draft') OR ? IN ('Admin', 'FinanceOfficer')");
    $stmtTotal->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['role']]);
    $totalClaims = $stmtTotal->fetch()['total'];

    // Pending Claims
    $stmtPending = $pdo->prepare("SELECT COUNT(*) AS pending FROM claims WHERE status IN ('Submitted', 'Under Review') AND (userID = ? OR (? IN (SELECT manager_id FROM departments WHERE department_id = claims.department_id)) OR ? IN ('Admin', 'FinanceOfficer'))");
    $stmtPending->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['role']]);
    $pendingClaims = $stmtPending->fetch()['pending'];

    // Approved Amount
    $stmtApproved = $pdo->prepare("SELECT SUM(amount) AS approved FROM claims WHERE status = 'Approved' AND (userID = ? OR ? IN ('Admin', 'FinanceOfficer'))");
    $stmtApproved->execute([$_SESSION['user_id'], $_SESSION['role']]);
    $approvedAmount = $stmtApproved->fetch()['approved'] ?? 0;

    // Approval Rate
    $stmtApprovalRate = $pdo->prepare("SELECT 
        ROUND((COUNT(CASE WHEN status = 'Approved' THEN 1 END) / 
        COUNT(CASE WHEN status IN ('Approved', 'Rejected', 'Paid') THEN 1 END)) * 100, 1) AS approval_rate 
        FROM claims 
        WHERE userID = ? AND status IN ('Approved', 'Rejected', 'Paid')");
    $stmtApprovalRate->execute([$_SESSION['user_id']]);
    $approvalRateResult = $stmtApprovalRate->fetch();
    $approvalRate = $approvalRateResult['approval_rate'] ?? 0;

    // Monthly expense trend (last 6 months)
    $stmtMonthly = $pdo->prepare("SELECT 
        DATE_FORMAT(incurred_date, '%Y-%m') AS month,
        SUM(amount) AS total_amount
        FROM claims
        WHERE userID = ? OR ? IN ('Admin', 'FinanceOfficer')
        GROUP BY DATE_FORMAT(incurred_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6");
    $stmtMonthly->execute([$_SESSION['user_id'], $_SESSION['role']]);
    $monthlyTrend = $stmtMonthly->fetchAll();
    $monthlyTrend = array_reverse($monthlyTrend);

    // Expense Categories
    $stmtCategories = $pdo->prepare("SELECT 
        ec.category_name,
        SUM(c.amount) AS total_amount
        FROM claims c
        JOIN expense_categories ec ON c.category_id = ec.category_id
        WHERE c.userID = ? OR ? IN ('Admin', 'FinanceOfficer')
        GROUP BY ec.category_id
        ORDER BY total_amount DESC
        LIMIT 5");
    $stmtCategories->execute([$_SESSION['user_id'], $_SESSION['role']]);
    $categoryExpenses = $stmtCategories->fetchAll();

    // Fetch pending approvals for managers and finance officers
    $pendingApprovals = [];
    if (in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin'])) {
        $stmtApprovals = $pdo->prepare("SELECT 
            c.claimID, 
            c.reference_number,
            c.amount,
            c.submission_date,
            u.fullName as employee_name,
            d.department_name,
            ec.category_name
            FROM claims c
            JOIN users u ON c.userID = u.userID
            JOIN departments d ON c.department_id = d.department_id
            JOIN expense_categories ec ON c.category_id = ec.category_id
            WHERE c.status = 'Submitted'
            AND (
                (? = 'FinanceOfficer' AND c.status = 'Submitted')
                OR
                (? = 'Manager' AND ? IN (SELECT manager_id FROM departments WHERE department_id = c.department_id))
                OR
                ? = 'Admin'
            )
            ORDER BY c.submission_date ASC
            LIMIT 5");
        $stmtApprovals->execute([$_SESSION['role'], $_SESSION['role'], $_SESSION['user_id'], $_SESSION['role']]);
        $pendingApprovals = $stmtApprovals->fetchAll();
    }

    // Fetch recent claims
    $stmtClaims = $pdo->prepare("SELECT 
        c.claimID,
        c.reference_number,
        c.amount,
        c.currency,
        c.status,
        c.submission_date,
        c.incurred_date,
        ec.category_name
        FROM claims c
        JOIN expense_categories ec ON c.category_id = ec.category_id
        WHERE c.userID = ? OR (? IN (SELECT manager_id FROM departments WHERE department_id = c.department_id) AND c.status != 'Draft') OR ? IN ('Admin', 'FinanceOfficer')
        ORDER BY c.submission_date DESC
        LIMIT 10");
    $stmtClaims->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['role']]);
    $recentClaims = $stmtClaims->fetchAll();

    // Fetch unread notifications
    $stmtNotifications = $pdo->prepare("SELECT * FROM notifications WHERE recipient_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmtNotifications->execute([$_SESSION['user_id']]);
    $notifications = $stmtNotifications->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    $error = "We encountered an error loading your dashboard data. Please try refreshing the page.";
}

// Include header with enhanced meta tags
include 'includes/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-lg p-6 mb-8 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold">Welcome back, <?= htmlspecialchars($_SESSION['fullName']) ?>!</h1>
                <p class="mt-2 text-blue-100">
                    <?php if ($_SESSION['role'] === 'Employee'): ?>
                        Track your expenses and submit new claims easily.
                    <?php elseif ($_SESSION['role'] === 'Manager'): ?>
                        Manage your team's expenses and approval workflows.
                    <?php elseif ($_SESSION['role'] === 'FinanceOfficer'): ?>
                        Review and process expense claims across the organization.
                    <?php else: ?>
                        Monitor and manage the entire expense management system.
                    <?php endif; ?>
                </p>
            </div>
            <div class="hidden md:block">
                <?php if ($_SESSION['role'] === 'Employee'): ?>
                    <a href="submit_claim.php" class="bg-white text-blue-600 hover:bg-blue-50 px-5 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i>
                        New Claim
                    </a>
                <?php elseif (in_array($_SESSION['role'], ['FinanceOfficer', 'Admin'])): ?>
                    <a href="reports.php" class="bg-white text-blue-600 hover:bg-blue-50 px-5 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                        View Reports
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Claims Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Claims</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                        <?= number_format($totalClaims) ?>
                    </p>
                </div>
                <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                    <i data-lucide="file-text" class="w-7 h-7 text-blue-600 dark:text-blue-300"></i>
                </div>
            </div>
            <?php if ($_SESSION['role'] === 'Employee'): ?>
                <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    <span class="inline-flex items-center">
                        <i data-lucide="trending-up" class="w-4 h-4 mr-1 text-green-500"></i>
                        <?= number_format($approvalRate, 1) ?>% approval rate
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Claims Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Claims</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                        <?= number_format($pendingClaims) ?>
                    </p>
                </div>
                <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-full">
                    <i data-lucide="clock" class="w-7 h-7 text-yellow-600 dark:text-yellow-300"></i>
                </div>
            </div>
            <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                <a href="#recent-claims" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                    View details
                    <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                </a>
            </div>
        </div>

        <!-- Approved Amount Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved Amount</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                        $<?= number_format($approvedAmount, 2) ?>
                    </p>
                </div>
                <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                    <i data-lucide="check-circle" class="w-7 h-7 text-green-600 dark:text-green-300"></i>
                </div>
            </div>
            <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                <span class="inline-flex items-center">
                    <i data-lucide="check" class="w-4 h-4 mr-1 text-green-500"></i>
                    Ready for reimbursement
                </span>
            </div>
        </div>

        <!-- Quick Links Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Quick Actions</p>
            <div class="mt-4 space-y-3">
                <?php if ($_SESSION['role'] === 'Employee'): ?>
                    <a href="submit_claim.php" class="flex items-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                        <i data-lucide="plus-circle" class="w-4 h-4 mr-2 text-gray-500"></i>
                        New Claim
                    </a>
                <?php endif; ?>
                <a href="view_claims.php" class="flex items-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                    <i data-lucide="list" class="w-4 h-4 mr-2 text-gray-500"></i>
                    My Claims
                </a>
                <?php if (in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin'])): ?>
                    <a href="approvals.php" class="flex items-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                        <i data-lucide="check-square" class="w-4 h-4 mr-2 text-gray-500"></i>
                        Pending Approvals
                    </a>
                <?php endif; ?>
                <?php if (in_array($_SESSION['role'], ['FinanceOfficer', 'Admin'])): ?>
                    <a href="reports.php" class="flex items-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                        <i data-lucide="bar-chart-2" class="w-4 h-4 mr-2 text-gray-500"></i>
                        Reports
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Charts & Tables Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Monthly Expenses Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-200 dark:border-gray-700 lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Monthly Expense Trend</h3>
            <div class="h-64">
                <canvas id="monthlyExpensesChart"></canvas>
            </div>
        </div>

        <!-- Top Expense Categories -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Top Expense Categories</h3>
            <div class="h-64">
                <canvas id="categoriesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Pending Approvals & Notifications Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <?php if (in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin']) && !empty($pendingApprovals)): ?>
        <!-- Pending Approvals -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Pending Approvals</h3>
                <a href="approvals.php" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View All</a>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($pendingApprovals as $approval): ?>
                <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium text-gray-800 dark:text-white"><?= htmlspecialchars($approval['reference_number']) ?></p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                <?= htmlspecialchars($approval['employee_name']) ?> • 
                                <?= htmlspecialchars($approval['department_name']) ?> • 
                                <?= htmlspecialchars($approval['category_name']) ?>
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Submitted <?= date('M d, Y', strtotime($approval['submission_date'])) ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-800 dark:text-white">$<?= number_format($approval['amount'], 2) ?></p>
                            <a href="process_claim.php?id=<?= $approval['claimID'] ?>" class="text-sm text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-block">Review</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notifications -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Recent Notifications</h3>
                <a href="notifications.php" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View All</a>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if (empty($notifications)): ?>
                <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    <i data-lucide="bell-off" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-3"></i>
                    <p>No unread notifications</p>
                </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 pt-0.5">
                                <?php if ($notification['notification_type'] === 'claim_approved'): ?>
                                <div class="bg-green-100 dark:bg-green-900 p-2 rounded-full">
                                    <i data-lucide="check" class="h-5 w-5 text-green-600 dark:text-green-300"></i>
                                </div>
                                <?php elseif ($notification['notification_type'] === 'claim_rejected'): ?>
                                <div class="bg-red-100 dark:bg-red-900 p-2 rounded-full">
                                    <i data-lucide="x" class="h-5 w-5 text-red-600 dark:text-red-300"></i>
                                </div>
                                <?php else: ?>
                                <div class="bg-blue-100 dark:bg-blue-900 p-2 rounded-full">
                                    <i data-lucide="bell" class="h-5 w-5 text-blue-600 dark:text-blue-300"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($notification['title']) ?></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($notification['message']) ?></p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= date('M d, g:i A', strtotime($notification['created_at'])) ?></p>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                                <a href="notifications.php?mark_read=<?= $notification['notification_id'] ?>" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    Mark as read
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Claims -->
    <div id="recent-claims" class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Recent Claims</h3>
            <a href="view_claims.php" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reference</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($recentClaims as $claim): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <?= htmlspecialchars($claim['reference_number']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <?= date('M d, Y', strtotime($claim['incurred_date'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <?= htmlspecialchars($claim['category_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <?= $claim['currency'] ?> <?= number_format($claim['amount'], 2) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= getStatusClass($claim['status']) ?>">
                                <?= $claim['status'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <a href="view_claim.php?id=<?= $claim['claimID'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">View</a>
                            <?php if ($claim['status'] === 'Draft' && $_SESSION['user_id'] == $claim['userID']): ?>
                                | <a href="edit_claim.php?id=<?= $claim['claimID'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">Edit</a>
                            <?php endif; ?>
                            <?php if (in_array($_SESSION['role'], ['FinanceOfficer', 'Admin']) && $claim['status'] === 'Submitted'): ?>
                                | <a href="process_claim.php?id=<?= $claim['claimID'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">Process</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Set Chart.js defaults for consistent styling
Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
Chart.defaults.color = document.documentElement.classList.contains('dark') 
    ? 'rgba(255, 255, 255, 0.8)' 
    : 'rgba(55, 65, 81, 0.8)';
Chart.defaults.borderColor = document.documentElement.classList.contains('dark') 
    ? 'rgba(255, 255, 255, 0.1)' 
    : 'rgba(0, 0, 0, 0.1)';

// Monthly Expenses Chart
const monthlyCtx = document.getElementById('monthlyExpensesChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: [<?= implode(', ', array_map(function($item) { 
            $date = new DateTime($item['month'] . '-01');
            return "'" . $date->format('M Y') . "'"; 
        }, $monthlyTrend)) ?>],
        datasets: [{
            label: 'Monthly Expenses',
            data: [<?= implode(', ', array_map(function($item) { 
                return $item['total_amount']; 
            }, $monthlyTrend)) ?>],
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderColor: 'rgba(59, 130, 246, 0.8)',
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: 'rgba(59, 130, 246, 1)',
            pointBorderColor: 'rgba(255, 255, 255, 1)',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '$' + context.raw.toLocaleString();
                    }
                }
            }
        }
    }
});

// Categories Chart
const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
new Chart(categoriesCtx, {
    type: 'doughnut',
    data: {
        labels: [<?= implode(', ', array_map(function($item) { 
            return "'" . addslashes($item['category_name']) . "'"; 
        }, $categoryExpenses)) ?>],
        datasets: [{
            data: [<?= implode(', ', array_map(function($item) { 
                return $item['total_amount']; 
            }, $categoryExpenses)) ?>],
            backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(139, 92, 246, 0.8)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': $' + context.raw.toLocaleString();
                    }
                }
            }
        },
        cutout: '65%'
    }
});
</script>

<?php
// Helper function to get badge classes based on status
function getStatusClass($status) {
    switch ($status) {
        case 'Approved':
            return 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100';
        case 'Paid':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100';
        case 'Rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100';
        case 'Submitted':
        case 'Under Review':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100';
        case 'Draft':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
        case 'Cancelled':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
}

include 'includes/footer.php';
?>