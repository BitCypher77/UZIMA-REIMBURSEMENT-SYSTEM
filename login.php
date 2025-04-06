<?php
require 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: index.php?error=Invalid request. Please try again.");
        exit();
    }
    
    // Validate input
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        header("Location: index.php?error=Invalid email format");
        exit();
    }
    
    $password = $_POST['password'];
    if (empty($password)) {
        header("Location: index.php?error=Password is required");
        exit();
    }
    
    // Remember me option
    $remember_me = isset($_POST['remember_me']) ? true : false;
    
    try {
        // Get user from database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Valid login
            
            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE userID = ?");
            $updateStmt->execute([$user['userID']]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['userID'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['fullName'] = $user['fullName'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['profile_image'] = $user['profile_image'];
            
            // If remember me is checked, set a cookie to persist the session
            if ($remember_me) {
                $selector = bin2hex(random_bytes(8));
                $validator = bin2hex(random_bytes(32));
                
                // Store the token in the database (you'd need a remember_tokens table)
                // This is just a basic implementation that could be expanded
                $token_hash = hash('sha256', $validator);
                $expires = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60); // 30 days
                
                $stmt = $pdo->prepare("INSERT INTO user_tokens (user_id, selector, token, expires) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user['userID'], $selector, $token_hash, $expires]);
                
                // Set cookie with selector and validator
                setcookie(
                    'remember',
                    $selector . ':' . $validator,
                    time() + 30 * 24 * 60 * 60, // 30 days
                    '/',
                    '',
                    true, // Only send over HTTPS
                    true  // HttpOnly
                );
            }
            
            // Log user activity
            logUserActivity($user['userID'], 'login', 'User logged in');
            
            // Check if it's an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                // Send JSON response for AJAX requests
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'redirect' => 'dashboard.php'
                ]);
                exit();
            }
            
            // Regular form submission
            header("Location: dashboard.php");
            exit();
        } else {
            // Invalid credentials
            
            // Log failed login attempt (for security monitoring)
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, user_agent, attempted_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$email, $ip, $user_agent]);
            
            // Check if we should delay response (to prevent brute force attacks)
            // Check number of failed attempts in the last hour
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$email]);
            $attempts = $stmt->fetchColumn();
            
            if ($attempts > 5) {
                // Too many failed attempts, delay response
                sleep(2); // Add a delay to slow down brute force attacks
            }
            
            // AJAX response
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid credentials. Please check your email and password.'
                ]);
                exit();
            }
            
            // Regular response
            header("Location: index.php?error=Invalid credentials. Please check your email and password.");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        
        // AJAX response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'System error. Please try again later.'
            ]);
            exit();
        }
        
        header("Location: index.php?error=System error. Please try again later.");
        exit();
    }
} else {
    // Not a POST request
    header("Location: index.php");
    exit();
}
?>