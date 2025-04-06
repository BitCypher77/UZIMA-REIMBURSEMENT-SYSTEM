<?php 
include 'config.php'; 

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle session expiration message
$session_expired = isset($_GET['session_expired']) ? true : false;

// Get company name from settings
$company_name = getSystemSetting('company_name', 'Uzima Corporation');
$company_logo = getSystemSetting('company_logo', 'assets/images/uzima_logo.png');
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50 dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars($company_name) ?> Expense Management</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Inter Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="h-full">
    <div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <img class="mx-auto h-16 w-auto" src="<?= htmlspecialchars($company_logo) ?>" alt="<?= htmlspecialchars($company_name) ?>">
                <h2 class="mt-6 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Welcome to the <?= htmlspecialchars($company_name) ?> 
                    <span class="block text-blue-600 dark:text-blue-400">Expense Management System</span>
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Sign in to your account to manage your business expenses
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow-lg sm:rounded-xl sm:px-10 border border-gray-200 dark:border-gray-700">
                <?php if (isset($_GET['error'])): ?>
                    <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900/30 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400 dark:text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-300">
                                    <?= htmlspecialchars($_GET['error']) ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($session_expired): ?>
                    <div class="mb-4 rounded-md bg-yellow-50 dark:bg-yellow-900/30 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="clock" class="h-5 w-5 text-yellow-400 dark:text-yellow-500"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                                    Your session has expired. Please log in again.
                                </h3>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" action="login.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email address
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="mail" class="h-5 w-5 text-gray-400"></i>
                            </div>
                            <input type="email" id="email" name="email" autocomplete="email" required 
                                   class="block w-full pl-10 py-3 placeholder-gray-400 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="name@company.com">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="lock" class="h-5 w-5 text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" autocomplete="current-password" required 
                                   class="block w-full pl-10 py-3 placeholder-gray-400 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="••••••••">
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember_me" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-700">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="forgot_password.php" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300">
                                Forgot your password?
                            </a>
                        </div>
                    </div>

                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i data-lucide="log-in" class="h-5 w-5 text-blue-500 group-hover:text-blue-400"></i>
                            </span>
                            Sign in
                        </button>
                    </div>
                </form>
                
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                                New to the system?
                            </span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="register.php" 
                           class="w-full flex justify-center py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            Create an account
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($company_name) ?>. All rights reserved.</p>
                    <p class="mt-1">Version 2.0.0</p>
                </div>
                <div class="mt-4 flex justify-center space-x-4">
                    <button id="themeToggle" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                        <i data-lucide="moon" class="h-5 w-5 hidden dark:block"></i>
                        <i data-lucide="sun" class="h-5 w-5 dark:hidden"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme toggle script -->
    <script>
        // Initialize Lucide Icons
        lucide.createIcons();
        
        // Theme Toggle
        document.getElementById('themeToggle').addEventListener('click', () => {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            
            html.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
            document.cookie = `theme=${isDark ? 'light' : 'dark'}; path=/; max-age=31536000`;
        });
        
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>