<?php
require 'config.php';
requireLogin();

// Ensure user has appropriate role
if (!in_array($_SESSION['role'], ['Manager', 'FinanceOfficer', 'Admin'])) {
    header("Location: dashboard.php");
    exit();
}

// Log user activity
logUserActivity($_SESSION['user_id'], 'page_view', 'Viewed approvals');

// Process claim approval/rejection if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['claimID'])) {
    $claimID = intval($_POST['claimID']);
    $action = $_POST['action'];
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid form submission. Please try again.";
    } else {
        try {
            // Check if user has authority to approve/reject
            $hasAuthority = false;
            if ($_SESSION['role'] === 'Admin') {
                $hasAuthority = true;
            } elseif ($_SESSION['role'] === 'FinanceOfficer') {
                $hasAuthority = true;
            } elseif ($_SESSION['role'] === 'Manager') {
                // Check if user is the department manager
                $stmt = $pdo->prepare("
                    SELECT 1 FROM claims c
                    JOIN departments d ON c.department_id = d.department_id
                    WHERE c.claimID = ? AND d.manager_id = ?
                ");
                $stmt->execute([$claimID, $_SESSION['user_id']]);
                $hasAuthority = $stmt->fetchColumn() ? true : false;
            }
            
            if (!$hasAuthority) {
                $errorMessage = "You don't have permission to approve or reject this claim.";
            } else {
                // Update claim status
                $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';
                
                $stmt = $pdo->prepare("UPDATE claims SET status = ?, last_updated = NOW() WHERE claimID = ?");
                $stmt->execute([$newStatus, $claimID]);
                
                // Record approval/rejection
                $stmt = $pdo->prepare("
                    INSERT INTO claim_approvals (claimID, approver_id, status, notes, approval_date)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$claimID, $_SESSION['user_id'], $newStatus, $notes]);
                
                // Get claim details for notification
                $stmt = $pdo->prepare("
                    SELECT c.userID, c.reference_number, u.fullName 
                    FROM claims c
                    JOIN users u ON c.userID = u.userID
                    WHERE c.claimID = ?
                ");
                $stmt->execute([$claimID]);
                $claimDetails = $stmt->fetch();
                
                // Create notification for the claim owner
                $notificationType = ($action === 'approve') ? 'approval' : 'rejection';
                $notificationTitle = ($action === 'approve') ? 'Claim Approved' : 'Claim Rejected';
                $notificationMessage = 'Your claim #' . $claimDetails['reference_number'] . ' has been ' . 
                                      strtolower($newStatus) . ' by ' . $_SESSION['fullName'] . 
                                      (!empty($notes) ? '. Notes: ' . $notes : '');
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (recipient_id, title, message, notification_type, reference_id, reference_type, created_at)
                    VALUES (?, ?, ?, ?, ?, 'claim', NOW())
                ");
                $stmt->execute([
                    $claimDetails['userID'], 
                    $notificationTitle, 
                    $notificationMessage, 
                    $notificationType, 
                    $claimID
                ]);
                
                $successMessage = "Claim has been successfully " . strtolower($newStatus) . ".";
            }
        } catch (PDOException $e) {
            error_log("Error processing approval/rejection: " . $e->getMessage());
            $errorMessage = "An error occurred while processing your request. Please try again.";
        }
    }
}

// Fetch claims awaiting approval
try {
    // Different queries based on user role
    if ($_SESSION['role'] === 'Admin') {
        $stmt = $pdo->prepare("
            SELECT 
                c.claimID,
                c.reference_number,
                c.submission_date,
                c.amount,
                c.currency,
                c.status,
                u.fullName as employee_name,
                u.email as employee_email,
                d.department_name,
                ec.category_name
            FROM claims c
            JOIN users u ON c.userID = u.userID
            JOIN departments d ON c.department_id = d.department_id
            JOIN expense_categories ec ON c.category_id = ec.category_id
            WHERE c.status IN ('Submitted', 'Under Review')
            ORDER BY c.submission_date ASC
        ");
        $stmt->execute();
    } elseif ($_SESSION['role'] === 'FinanceOfficer') {
        $stmt = $pdo->prepare("
            SELECT 
                c.claimID,
                c.reference_number,
                c.submission_date,
                c.amount,
                c.currency,
                c.status,
                u.fullName as employee_name,
                u.email as employee_email,
                d.department_name,
                ec.category_name
            FROM claims c
            JOIN users u ON c.userID = u.userID
            JOIN departments d ON c.department_id = d.department_id
            JOIN expense_categories ec ON c.category_id = ec.category_id
            WHERE c.status = 'Submitted'
            ORDER BY c.submission_date ASC
        ");
        $stmt->execute();
    } else { // Manager
        $stmt = $pdo->prepare("
            SELECT 
                c.claimID,
                c.reference_number,
                c.submission_date,
                c.amount,
                c.currency,
                c.status,
                u.fullName as employee_name,
                u.email as employee_email,
                d.department_name,
                ec.category_name
            FROM claims c
            JOIN users u ON c.userID = u.userID
            JOIN departments d ON c.department_id = d.department_id
            JOIN expense_categories ec ON c.category_id = ec.category_id
            WHERE c.status = 'Submitted'
            AND d.manager_id = ?
            ORDER BY c.submission_date ASC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    }
    
    $pendingClaims = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching pending approvals: " . $e->getMessage());
    $errorMessage = "An error occurred while fetching pending approvals. Please try again.";
}

// Include header
include 'includes/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pending Approvals</h1>
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

    <?php if (empty($pendingClaims)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
            <div class="flex justify-center">
                <i data-lucide="check-circle" class="h-12 w-12 text-green-500 dark:text-green-400"></i>
            </div>
            <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">No pending approvals</h3>
            <p class="mt-1 text-gray-500 dark:text-gray-400">There are no claims waiting for your approval at this time.</p>
        </div>
    <?php else: ?>
        <div class="mt-4 flex flex-col">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">Reference</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Employee</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Department</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Category</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Amount</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Date</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                <?php foreach ($pendingClaims as $claim): ?>
                                    <tr>
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-6">
                                            <?= htmlspecialchars($claim['reference_number']) ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($claim['employee_name']) ?>
                                            <div class="text-xs text-gray-400 dark:text-gray-500"><?= htmlspecialchars($claim['employee_email']) ?></div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($claim['department_name']) ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($claim['category_name']) ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($claim['currency']) ?> <?= number_format($claim['amount'], 2) ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?= date('M j, Y', strtotime($claim['submission_date'])) ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <span class="inline-flex rounded-full bg-yellow-100 dark:bg-yellow-900 px-2 text-xs font-semibold leading-5 text-yellow-800 dark:text-yellow-200">
                                                <?= htmlspecialchars($claim['status']) ?>
                                            </span>
                                        </td>
                                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <button 
                                                onclick="viewClaim(<?= $claim['claimID'] ?>)" 
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-4">
                                                <i data-lucide="eye" class="h-5 w-5"></i>
                                            </button>
                                            <button 
                                                onclick="showApprovalModal(<?= $claim['claimID'] ?>, '<?= htmlspecialchars($claim['reference_number']) ?>', 'approve')" 
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 mr-4">
                                                <i data-lucide="check-circle" class="h-5 w-5"></i>
                                            </button>
                                            <button 
                                                onclick="showApprovalModal(<?= $claim['claimID'] ?>, '<?= htmlspecialchars($claim['reference_number']) ?>', 'reject')" 
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                <i data-lucide="x-circle" class="h-5 w-5"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Approval/Rejection Modal -->
<div id="approvalModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
        <div class="px-4 py-5 sm:p-6">
            <h3 id="modalTitle" class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                Approve Claim
            </h3>
            
            <form id="approvalForm" action="approvals.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" id="claimIDInput" name="claimID" value="">
                <input type="hidden" id="actionInput" name="action" value="">
                
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Notes (optional)
                    </label>
                    <textarea id="notes" name="notes" rows="4" 
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                              placeholder="Add any notes or comments"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelButton" 
                            class="mr-3 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </button>
                    <button type="submit" id="confirmButton"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();
    
    // View claim details
    function viewClaim(claimId) {
        window.location.href = `submit_claim.php?view=${claimId}`;
    }
    
    // Show approval/rejection modal
    function showApprovalModal(claimId, reference, action) {
        const modal = document.getElementById('approvalModal');
        const form = document.getElementById('approvalForm');
        const claimIDInput = document.getElementById('claimIDInput');
        const actionInput = document.getElementById('actionInput');
        const modalTitle = document.getElementById('modalTitle');
        const confirmButton = document.getElementById('confirmButton');
        
        claimIDInput.value = claimId;
        actionInput.value = action;
        
        if (action === 'approve') {
            modalTitle.textContent = `Approve Claim #${reference}`;
            confirmButton.textContent = 'Confirm Approval';
            confirmButton.classList.remove('bg-red-600', 'hover:bg-red-700', 'focus:ring-red-500');
            confirmButton.classList.add('bg-green-600', 'hover:bg-green-700', 'focus:ring-green-500');
        } else {
            modalTitle.textContent = `Reject Claim #${reference}`;
            confirmButton.textContent = 'Confirm Rejection';
            confirmButton.classList.remove('bg-green-600', 'hover:bg-green-700', 'focus:ring-green-500');
            confirmButton.classList.add('bg-red-600', 'hover:bg-red-700', 'focus:ring-red-500');
        }
        
        modal.classList.remove('hidden');
    }
    
    // Close modal on cancel
    document.getElementById('cancelButton').addEventListener('click', function() {
        document.getElementById('approvalModal').classList.add('hidden');
    });
</script>

<?php include 'includes/footer.php'; ?> 