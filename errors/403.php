<?php http_response_code(403); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="text-center p-8 bg-white rounded-lg shadow-lg max-w-md">
        <h1 class="text-3xl font-bold text-red-600 mb-4">403 - Forbidden</h1>
        <p class="text-gray-600 mb-4">You don't have permission to access this page.</p>
        <a href="/dashboard" class="text-blue-600 hover:underline">Return to Dashboard</a>
    </div>
</body>
</html>