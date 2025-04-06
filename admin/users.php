<?php
include 'config.php';
requireRole('Admin');

// User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'create':
            // User creation logic
            break;
            
        case 'update':
            // Update user details
            break;
            
        case 'delete':
            // Soft delete user
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE userID = ?");
            $stmt->execute([$_POST['user_id']]);
            break;
    }
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users")->fetchAll();

include 'includes/header.php';
?>
<!-- User Management Interface -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>User Management</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newUserModal">
            Add New User
        </button>
    </div>
    
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['fullName'] ?></td>
                    <td><?= $user['email'] ?></td>
                    <td><?= $user['role'] ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editUserModal"
                                data-userid="<?= $user['userID'] ?>">
                            Edit
                        </button>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $user['userID'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals for User Creation/Editing -->
<div class="modal fade" id="newUserModal">
    <!-- Modal content -->
</div>

<?php include 'includes/footer.php'; ?>