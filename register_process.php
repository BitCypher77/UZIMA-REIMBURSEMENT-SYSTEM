<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Check password match
    if ($password !== $confirmPassword) {
        header("Location: register.php?error=Passwords do not match");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=Invalid email format");
        exit();
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        header("Location: register.php?error=Email already registered");
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    try {
        $stmt = $pdo->prepare("INSERT INTO users (fullName, email, password, role) VALUES (?, ?, ?, 'Employee')");
        $stmt->execute([$fullName, $email, $hashedPassword]);
        
        header("Location: index.php?success=Registration successful. Please login.");
        exit();
    } catch (PDOException $e) {
        header("Location: register.php?error=Registration failed");
        exit();
    }
}

header("Location: register.php");
exit();
?>