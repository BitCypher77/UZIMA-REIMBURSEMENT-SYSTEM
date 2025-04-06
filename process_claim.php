<?php
include 'config.php';
requireRole(['Admin', 'FinanceOfficer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF and input validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }
    
    $claimID = filter_input(INPUT_POST, 'claim_id', FILTER_SANITIZE_NUMBER_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $comments = sanitize($_POST['comments']);

    // Update claim status
    $stmt = $pdo->prepare("UPDATE claims SET status = ?, approval_date = NOW(), approverID = ? WHERE claimID = ?");
    $stmt->execute([$status, $_SESSION['user_id'], $claimID]);

    // If approved, update employee's total reimbursement
    if ($status === 'Approved') {
        $claim = $pdo->query("SELECT userID, amount FROM claims WHERE claimID = $claimID")->fetch();
        
        $updateStmt = $pdo->prepare("UPDATE users SET total_reimbursement = total_reimbursement + ? WHERE userID = ?");
        $updateStmt->execute([$claim['amount'], $claim['userID']]);
    }

    // Send email notification
    $user = $pdo->query("SELECT email FROM users WHERE userID = {$claim['userID']}")->fetch();
    $to = $user['email'];
    $subject = "Claim Status Update";
    $message = "Your claim #$claimID has been $status. Comments: $comments";
    mail($to, $subject, $message);

    header("Location: process_claim.php?id=$claimID&success=Status+updated");
    exit();
}

// Get claim details
$claimID = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$stmt = $pdo->prepare("SELECT * FROM claims WHERE claimID = ?");
$stmt->execute([$claimID]);
$claim = $stmt->fetch();

include 'includes/header.php';
?>
<!-- Processing Form -->
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="claim_id" value="<?= $claimID ?>">

    <div class="card">
        <div class="card-header">Process Claim #<?= $claimID ?></div>
        <div class="card-body">
            <div class="mb-3">
                <label>Status</label>
                <select name="status" class="form-select" required>
                    <option value="Approved" <?= $claim['status'] === 'Approved' ? 'selected' : '' ?>>Approve</option>
                    <option value="Rejected" <?= $claim['status'] === 'Rejected' ? 'selected' : '' ?>>Reject</option>
                </select>
            </div>

            <div class="mb-3">
                <label>Comments</label>
                <textarea name="comments" class="form-control" rows="3" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Update Status</button>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>