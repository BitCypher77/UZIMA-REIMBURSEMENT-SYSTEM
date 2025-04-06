<?php
require 'config.php';
require 'includes/auth.php';
requireRole(['Admin', 'FinanceOfficer']);

// Report generation logic

include 'includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white">Financial Reports</h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-blue-50 dark:bg-gray-700 p-6 rounded-xl">
            <h3 class="text-lg font-semibold mb-4">Claim Statistics</h3>
            <canvas id="claimsChart" class="w-full h-64"></canvas>
        </div>
        
        <div class="bg-white dark:bg-gray-700 p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-4">Generate Report</h3>
            <form method="GET" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Date pickers and filters -->
                
                <button type="submit" name="export" value="excel"
                        class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg
                               flex items-center justify-center gap-2">
                    <i data-lucide="file-excel"></i> Export to Excel
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>