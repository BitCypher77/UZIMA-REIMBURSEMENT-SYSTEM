<?php
function authorize($roles) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit();
    }
    
    if (!in_array($_SESSION['role'], (array)$roles, true)) { // Strict type checking
        http_response_code(403);
        include __DIR__ . '/../errors/403.php'; // Absolute path
        exit();
    }
}



?>