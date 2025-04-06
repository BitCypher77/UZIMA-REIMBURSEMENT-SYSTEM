<?php
// Start session with secure parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Set security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://unpkg.com cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Load environment variables if available
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'uzima_reimbursement');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv']);
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header("Location: index.php?session_expired=1");
    exit();
}
$_SESSION['last_activity'] = time();

// Database Connection with PDO
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true
        ]
    );
} catch(PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("We're experiencing technical difficulties. Please try again later.");
}

// Utility Functions
function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function generateReferenceNumber($prefix = 'CLM') {
    $year = date('Y');
    $randomDigits = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    return "$prefix-$year-$randomDigits";
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?error=Please log in to access this page");
        exit();
    }
}

function requireRole($roles) {
    requireLogin();
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: dashboard.php?error=You don't have permission to access this page");
        exit();
    }
}

function getSystemSetting($key, $default = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    return $result ? $result['setting_value'] : $default;
}

function logUserActivity($userId, $activityType, $description) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO user_activity_logs (user_id, activity_type, activity_description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId, 
        $activityType, 
        $description, 
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
}

function logClaimAudit($claimId, $actionType, $details, $prevStatus, $newStatus) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO claim_audit_logs (claimID, action_type, action_details, previous_status, new_status, performed_by, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $claimId,
        $actionType,
        $details,
        $prevStatus,
        $newStatus,
        $_SESSION['user_id'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
}

// Create notification
function createNotification($recipientId, $title, $message, $type, $referenceId = null, $referenceType = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO notifications (recipient_id, title, message, notification_type, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$recipientId, $title, $message, $type, $referenceId, $referenceType]);
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax']) && !isset($_FILES['file'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die("Invalid CSRF token. Please try refreshing the page.");
    }
}
?>