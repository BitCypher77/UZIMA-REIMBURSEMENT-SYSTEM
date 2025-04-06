<?php
require 'config.php';
requireLogin();

// Get categories
try {
    $stmt = $pdo->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY category_name");
    $categories = $stmt->fetchAll();
    
    // Get policies
    $stmt = $pdo->query("SELECT * FROM policies WHERE is_active = 1 ORDER BY policy_name");
    $policies = $stmt->fetchAll();
    
    // Get projects for billable expenses
    $stmt = $pdo->query("SELECT p.*, c.client_name FROM projects p JOIN clients c ON p.client_id = c.client_id WHERE p.is_active = 1 ORDER BY p.project_name");
    $projects = $stmt->fetchAll();
    
    // Get currencies
    $stmt = $pdo->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY is_base_currency DESC, currency_code");
    $currencies = $stmt->fetchAll();
    
    // Get department
    $stmt = $pdo->prepare("SELECT d.* FROM departments d JOIN users u ON d.department_id = u.department_id WHERE u.userID = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $department = $stmt->fetch();
    
    // Check if draft exists
    $stmt = $pdo->prepare("SELECT * FROM claims WHERE userID = ? AND status = 'Draft' ORDER BY last_updated DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $draftClaim = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Error loading claim form data: " . $e->getMessage());
    $error = "Error loading form data. Please try again later.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Invalid request. Please try again.");
        }
        
        // Validate input
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        if (!$amount || $amount <= 0) {
            throw new Exception("Please enter a valid amount.");
        }
        
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        if (!$category_id) {
            throw new Exception("Please select an expense category.");
        }
        
        $description = trim($_POST['description']);
        if (empty($description)) {
            throw new Exception("Please enter a description.");
        }
        
        $purpose = trim($_POST['purpose']);
        $incurred_date = $_POST['incurred_date'];
        $currency = $_POST['currency'];
        $policy_id = filter_input(INPUT_POST, 'policy_id', FILTER_VALIDATE_INT);
        $is_billable = isset($_POST['is_billable']) ? 1 : 0;
        $project_id = $is_billable ? filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT) : null;
        
        // Handle receipt upload
        $receipt_path = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
            // Validate file size
            if ($_FILES['receipt']['size'] > MAX_FILE_SIZE) {
                throw new Exception("Receipt file is too large. Maximum size is " . (MAX_FILE_SIZE / 1024 / 1024) . "MB.");
            }
            
            // Validate file type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            $file_extension = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_extensions));
            }
            
            // Generate unique filename
            $unique_filename = uniqid('receipt_') . '_' . time() . '.' . $file_extension;
            $upload_dir = 'uploads/receipts/' . date('Y/m/');
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $upload_path = $upload_dir . $unique_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload receipt. Please try again.");
            }
            
            $receipt_path = $upload_path;
        }
        
        // Generate reference number
        $reference_number = generateReferenceNumber('CLM');
        
        // Determine the status (Draft or Submitted)
        $status = isset($_POST['save_draft']) ? 'Draft' : 'Submitted';
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert claim
        $stmt = $pdo->prepare("INSERT INTO claims (
            reference_number, userID, department_id, category_id, amount, currency, 
            description, purpose, incurred_date, receipt_path, status, policy_id,
            billable_to_client, project_id, submission_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $reference_number,
            $_SESSION['user_id'],
            $_SESSION['department_id'],
            $category_id,
            $amount,
            $currency,
            $description,
            $purpose,
            $incurred_date,
            $receipt_path,
            $status,
            $policy_id,
            $is_billable,
            $project_id
        ]);
        
        $claimID = $pdo->lastInsertId();
        
        // Process additional documents if any
        if (isset($_FILES['additional_docs']) && is_array($_FILES['additional_docs']['name'])) {
            $additional_docs_paths = [];
            
            for ($i = 0; $i < count($_FILES['additional_docs']['name']); $i++) {
                if ($_FILES['additional_docs']['error'][$i] == 0) {
                    // Similar validation as above
                    if ($_FILES['additional_docs']['size'][$i] > MAX_FILE_SIZE) {
                        continue; // Skip oversized files
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['additional_docs']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
                        continue; // Skip invalid file types
                    }
                    
                    $unique_filename = uniqid('doc_') . '_' . time() . '_' . $i . '.' . $file_extension;
                    $upload_dir = 'uploads/documents/' . date('Y/m/');
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $upload_path = $upload_dir . $unique_filename;
                    
                    if (move_uploaded_file($_FILES['additional_docs']['tmp_name'][$i], $upload_path)) {
                        $additional_docs_paths[] = $upload_path;
                    }
                }
            }
            
            // Update claim with additional documents
            if (!empty($additional_docs_paths)) {
                $stmt = $pdo->prepare("UPDATE claims SET additional_documents = ? WHERE claimID = ?");
                $stmt->execute([json_encode($additional_docs_paths), $claimID]);
            }
        }
        
        // If submitted (not draft), initiate the approval workflow
        if ($status === 'Submitted') {
            // Find applicable workflow
            $stmt = $pdo->prepare("
                SELECT workflow_id FROM approval_workflows 
                WHERE (department_id IS NULL OR department_id = ?) 
                AND (category_id IS NULL OR category_id = ?)
                AND ? BETWEEN min_amount AND COALESCE(max_amount, 999999999)
                AND is_active = 1
                ORDER BY 
                    CASE 
                        WHEN department_id IS NOT NULL AND category_id IS NOT NULL THEN 1
                        WHEN department_id IS NOT NULL THEN 2
                        WHEN category_id IS NOT NULL THEN 3
                        ELSE 4
                    END
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['department_id'], $category_id, $amount]);
            $workflow = $stmt->fetch();
            
            if ($workflow) {
                // Get the first step in the workflow
                $stmt = $pdo->prepare("
                    SELECT * FROM approval_steps 
                    WHERE workflow_id = ? 
                    ORDER BY step_order ASC 
                    LIMIT 1
                ");
                $stmt->execute([$workflow['workflow_id']]);
                $firstStep = $stmt->fetch();
                
                if ($firstStep) {
                    // Find the appropriate approver
                    $approver_id = $firstStep['specific_approver_id'];
                    
                    if (!$approver_id) {
                        // If no specific approver, find by role
                        if ($firstStep['approver_role'] === 'Manager') {
                            // Get department manager
                            $stmt = $pdo->prepare("
                                SELECT manager_id FROM departments WHERE department_id = ?
                            ");
                            $stmt->execute([$_SESSION['department_id']]);
                            $manager = $stmt->fetch();
                            $approver_id = $manager ? $manager['manager_id'] : null;
                        } else {
                            // Find any user with the required role
                            $stmt = $pdo->prepare("
                                SELECT userID FROM users 
                                WHERE role = ? AND is_active = 1
                                LIMIT 1
                            ");
                            $stmt->execute([$firstStep['approver_role']]);
                            $approver = $stmt->fetch();
                            $approver_id = $approver ? $approver['userID'] : null;
                        }
                    }
                    
                    // Create the approval record
                    if ($approver_id) {
                        $stmt = $pdo->prepare("
                            INSERT INTO claim_approvals (claimID, step_id, approver_id, status)
                            VALUES (?, ?, ?, 'Pending')
                        ");
                        $stmt->execute([$claimID, $firstStep['step_id'], $approver_id]);
                        
                        // Send notification to approver
                        createNotification(
                            $approver_id, 
                            "New Claim for Approval", 
                            "A new claim ({$reference_number}) requires your approval.", 
                            "claim_approval", 
                            $claimID, 
                            "claim"
                        );
                    }
                }
            }
            
            // Log the claim submission
            logClaimAudit(
                $claimID,
                'claim_submitted',
                'Claim submitted for approval',
                'Draft',
                'Submitted'
            );
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to success page
        if ($status === 'Draft') {
            header("Location: view_claim.php?id={$claimID}&success=1&draft=1");
        } else {
            header("Location: view_claim.php?id={$claimID}&success=1");
        }
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $error = $e->getMessage();
        
        // If AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit();
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Submit Expense Claim</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/30">
                    <div class="flex items-center text-red-800 dark:text-red-300">
                        <i data-lucide="alert-circle" class="h-5 w-5 mr-2 text-red-600 dark:text-red-400"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($draftClaim)): ?>
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/30">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-blue-800 dark:text-blue-300">
                            <i data-lucide="info" class="h-5 w-5 mr-2 text-blue-600 dark:text-blue-400"></i>
                            <span>You have a draft claim. Would you like to continue editing it?</span>
                        </div>
                        <a href="edit_claim.php?id=<?= $draftClaim['claimID'] ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Edit Draft
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <form action="submit_claim.php" method="post" enctype="multipart/form-data" id="claim-form" class="px-6 py-4 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Amount and Currency -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Amount <span class="text-red-600">*</span>
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input type="number" name="amount" id="amount" step="0.01" min="0.01" required
                                   class="block w-full pl-8 pr-20 py-3 placeholder-gray-400 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="0.00">
                            <div class="absolute inset-y-0 right-0 flex items-center">
                                <select name="currency" id="currency" 
                                        class="h-full py-0 pl-2 pr-7 border-transparent bg-transparent text-gray-500 dark:text-gray-300 focus:ring-blue-500 focus:border-blue-500 rounded-r-md">
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?= $currency['currency_code'] ?>" <?= $currency['is_base_currency'] ? 'selected' : '' ?>>
                                            <?= $currency['currency_code'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Expense Category -->
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Expense Category <span class="text-red-600">*</span>
                        </label>
                        <select name="category_id" id="category_id" required
                                class="mt-1 block w-full py-3 px-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>"
                                        data-receipt-required="<?= $category['receipt_required'] ? 'true' : 'false' ?>"
                                        data-max-amount="<?= $category['max_amount'] ?>">
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 hidden" id="max-amount-info">
                            Maximum claimable amount: <span id="max-amount-value"></span>
                        </p>
                    </div>
                </div>
                
                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Description <span class="text-red-600">*</span>
                    </label>
                    <textarea name="description" id="description" rows="3" required
                              class="mt-1 block w-full py-3 px-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                              placeholder="Detailed description of the expense"></textarea>
                </div>
                
                <!-- Purpose -->
                <div>
                    <label for="purpose" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Business Purpose
                    </label>
                    <input type="text" name="purpose" id="purpose"
                           class="mt-1 block w-full py-3 px-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                           placeholder="Business purpose of the expense">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Date -->
                    <div>
                        <label for="incurred_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Date Incurred <span class="text-red-600">*</span>
                        </label>
                        <input type="date" name="incurred_date" id="incurred_date" required
                               max="<?= date('Y-m-d') ?>"
                               class="mt-1 block w-full py-3 px-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <!-- Policy -->
                    <div>
                        <label for="policy_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Applicable Policy
                        </label>
                        <select name="policy_id" id="policy_id"
                                class="mt-1 block w-full py-3 px-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select a policy (optional)</option>
                            <?php foreach ($policies as $policy): ?>
                                <option value="<?= $policy['policy_id'] ?>">
                                    <?= htmlspecialchars($policy['policy_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Receipt Upload -->
                <div>
                    <label for="receipt" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Receipt <span class="text-red-600 receipt-required">*</span>
                    </label>
                    <div class="mt-1 flex items-center">
                        <label class="relative cursor-pointer bg-white dark:bg-gray-700 rounded-lg font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500 focus-within:outline-none">
                            <span class="sr-only">Upload a receipt</span>
                            <input id="receipt" name="receipt" type="file" class="sr-only" accept=".jpg,.jpeg,.png,.pdf">
                            <div class="flex items-center justify-center w-full p-6 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-gray-400 dark:hover:border-gray-500">
                                <div class="space-y-1 text-center">
                                    <i data-lucide="upload" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"></i>
                                    <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                        <span class="relative bg-white dark:bg-gray-700 rounded-md font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500 focus-within:outline-none">
                                            Upload Receipt
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        JPG, PNG, PDF up to 10MB
                                    </p>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div id="receipt-preview" class="mt-2 hidden">
                        <div class="flex items-center justify-between p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                            <div class="flex items-center">
                                <i data-lucide="file" class="h-5 w-5 mr-2 text-blue-600 dark:text-blue-400"></i>
                                <span id="receipt-name" class="text-sm text-gray-700 dark:text-gray-300"></span>
                            </div>
                            <button type="button" id="remove-receipt" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200">
                                <i data-lucide="x" class="h-5 w-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Documents -->
                <div>
                    <label for="additional_docs" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Additional Documents (Optional)
                    </label>
                    <div class="mt-1 flex items-center">
                        <label class="relative cursor-pointer bg-white dark:bg-gray-700 rounded-lg font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500 focus-within:outline-none">
                            <span class="sr-only">Upload additional documents</span>
                            <input id="additional_docs" name="additional_docs[]" type="file" class="sr-only" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
                            <div class="flex items-center justify-center w-full p-6 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-gray-400 dark:hover:border-gray-500">
                                <div class="space-y-1 text-center">
                                    <i data-lucide="file-plus" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"></i>
                                    <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                        <span class="relative bg-white dark:bg-gray-700 rounded-md font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500 focus-within:outline-none">
                                            Add Supporting Documents
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        JPG, PNG, PDF, DOC, XLS up to 10MB
                                    </p>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div id="additional-docs-preview" class="mt-2 space-y-2"></div>
                </div>
                
                <!-- Billable to Client -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="is_billable" name="is_billable" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-800">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_billable" class="font-medium text-gray-700 dark:text-gray-300">Billable to Client</label>
                            <p class="text-gray-500 dark:text-gray-400">Check this if the expense should be billed to a client or project</p>
                        </div>
                    </div>
                    
                    <div id="billable-details" class="mt-4 hidden">
                        <label for="project_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Project <span class="text-red-600">*</span>
                        </label>
                        <select name="project_id" id="project_id"
                                class="mt-1 block w-full py-3 px-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select a project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['project_id'] ?>">
                                    <?= htmlspecialchars($project['project_name']) ?> (<?= htmlspecialchars($project['client_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" id="cancel-button"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </button>
                    <button type="submit" name="save_draft"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-lucide="save" class="h-4 w-4 mr-2"></i>
                        Save as Draft
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-lucide="send" class="h-4 w-4 mr-2"></i>
                        Submit Claim
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle category selection to show max amount
    const categorySelect = document.getElementById('category_id');
    const maxAmountInfo = document.getElementById('max-amount-info');
    const maxAmountValue = document.getElementById('max-amount-value');
    const receiptRequired = document.querySelectorAll('.receipt-required');
    const receiptInput = document.getElementById('receipt');
    
    categorySelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const maxAmount = selectedOption.dataset.maxAmount;
        const isReceiptRequired = selectedOption.dataset.receiptRequired === 'true';
        
        if (maxAmount && maxAmount > 0) {
            maxAmountInfo.classList.remove('hidden');
            maxAmountValue.textContent = `$${parseFloat(maxAmount).toFixed(2)}`;
        } else {
            maxAmountInfo.classList.add('hidden');
        }
        
        // Toggle receipt required
        receiptRequired.forEach(el => {
            if (isReceiptRequired) {
                el.classList.remove('hidden');
                receiptInput.setAttribute('required', 'required');
            } else {
                el.classList.add('hidden');
                receiptInput.removeAttribute('required');
            }
        });
    });
    
    // Handle receipt upload preview
    const receiptPreview = document.getElementById('receipt-preview');
    const receiptName = document.getElementById('receipt-name');
    const removeReceipt = document.getElementById('remove-receipt');
    
    receiptInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            receiptPreview.classList.remove('hidden');
            receiptName.textContent = this.files[0].name;
        } else {
            receiptPreview.classList.add('hidden');
        }
    });
    
    removeReceipt.addEventListener('click', function() {
        receiptInput.value = '';
        receiptPreview.classList.add('hidden');
    });
    
    // Handle additional documents preview
    const additionalDocsInput = document.getElementById('additional_docs');
    const additionalDocsPreview = document.getElementById('additional-docs-preview');
    
    additionalDocsInput.addEventListener('change', function() {
        additionalDocsPreview.innerHTML = '';
        
        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            const filePreview = document.createElement('div');
            filePreview.className = 'flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded-lg';
            
            filePreview.innerHTML = `
                <div class="flex items-center">
                    <i data-lucide="file" class="h-5 w-5 mr-2 text-gray-600 dark:text-gray-400"></i>
                    <span class="text-sm text-gray-700 dark:text-gray-300">${file.name}</span>
                </div>
            `;
            
            additionalDocsPreview.appendChild(filePreview);
        }
        
        if (this.files.length > 0) {
            additionalDocsPreview.classList.remove('hidden');
        } else {
            additionalDocsPreview.classList.add('hidden');
        }
        
        // Initialize icons
        lucide.createIcons();
    });
    
    // Handle billable checkbox
    const isBillable = document.getElementById('is_billable');
    const billableDetails = document.getElementById('billable-details');
    const projectSelect = document.getElementById('project_id');
    
    isBillable.addEventListener('change', function() {
        if (this.checked) {
            billableDetails.classList.remove('hidden');
            projectSelect.setAttribute('required', 'required');
        } else {
            billableDetails.classList.add('hidden');
            projectSelect.removeAttribute('required');
        }
    });
    
    // Handle cancel button
    document.getElementById('cancel-button').addEventListener('click', function() {
        window.location.href = 'dashboard.php';
    });
    
    // Form validation on submit
    const form = document.getElementById('claim-form');
    form.addEventListener('submit', function(e) {
        const categorySelect = document.getElementById('category_id');
        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        
        if (selectedOption.value === '') {
            e.preventDefault();
            alert('Please select an expense category.');
            return;
        }
        
        const isReceiptRequired = selectedOption.dataset.receiptRequired === 'true';
        if (isReceiptRequired && receiptInput.files.length === 0) {
            e.preventDefault();
            alert('Receipt is required for this expense category.');
            return;
        }
        
        const amount = parseFloat(document.getElementById('amount').value);
        const maxAmount = parseFloat(selectedOption.dataset.maxAmount);
        
        if (maxAmount && amount > maxAmount) {
            if (!confirm(`The amount ($${amount.toFixed(2)}) exceeds the maximum allowed ($${maxAmount.toFixed(2)}) for this category. Additional approval may be required. Continue?`)) {
                e.preventDefault();
                return;
            }
        }
        
        if (isBillable.checked && projectSelect.value === '') {
            e.preventDefault();
            alert('Please select a project for billable expenses.');
            return;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>