# Windows XAMPP Installation Guide

This guide will help you set up the Uzima Expense Management System on a Windows machine using XAMPP.

## Prerequisites

1. [XAMPP](https://www.apachefriends.org/index.html) installed on your Windows machine (version 7.4.0 or later recommended)
2. A web browser (Chrome, Firefox, Edge, etc.)
3. The Uzima Expense Management System codebase

## Installation Steps

### 1. Install XAMPP

If you haven't already installed XAMPP:

- Download XAMPP from [https://www.apachefriends.org/index.html](https://www.apachefriends.org/index.html)
- Run the installer and follow the installation wizard
- Select at least the following components: Apache, MySQL, PHP, and phpMyAdmin
- Complete the installation

### 2. Deploy the Application

1. Start the XAMPP Control Panel and start both Apache and MySQL services.
2. Copy the entire application folder to the `htdocs` directory in your XAMPP installation folder:
   ```
   C:\xampp\htdocs\uzima-expense
   ```
   (You can name the folder whatever you prefer instead of "uzima-expense")

### 3. Create and Configure the Database

1. Open your web browser and navigate to phpMyAdmin:

   ```
   http://localhost/phpmyadmin
   ```

2. Create a new database named `uzima_expense` (or your preferred name):

   - Click on "New" in the left sidebar
   - Enter "uzima_expense" as the database name
   - Select "utf8mb4_unicode_ci" as the collation
   - Click "Create"

3. Import the database schema:

   - Select the newly created database from the left sidebar
   - Click the "Import" tab at the top
   - Click "Browse" and select the `setup_database.sql` file from the application files
   - Scroll down and click "Go" to import the schema

4. After the main database is imported, import the additional schema updates:
   - Stay in the same database
   - Click the "Import" tab again
   - Click "Browse" and select the `database_update.sql` file
   - Click "Go" to run the updates

### 4. Configure Database Connection

1. Open the `.env` file in the root of your application directory (create it if it doesn't exist):

   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=uzima_expense
   ```

   Note: If you set a password for your MySQL root user during XAMPP installation, replace the empty DB_PASS value with your password.

2. Make sure the `config.php` file correctly loads these environment variables.

### 5. Configure File Paths and Permissions

1. Create an `uploads` directory in the root of your application if it doesn't exist:

   ```
   C:\xampp\htdocs\uzima-expense\uploads
   ```

2. Make sure the `uploads` directory is writable by the web server:
   - Right-click on the folder
   - Select "Properties"
   - Go to the "Security" tab
   - Click "Edit" and ensure the "Users" group has "Write" permissions
   - Click "Apply" and "OK"

### 6. Test the Application

1. Open your web browser and navigate to:

   ```
   http://localhost/uzima-expense
   ```

   (Replace "uzima-expense" with the folder name you used in step 2)

2. You should see the login page of the application. If the database was set up correctly, you should be able to register and log in.

## Troubleshooting Common Issues

### Page Not Found Errors

If you encounter "Page Not Found" errors when clicking on Reports, Notifications, Messages, or Approvals:

1. Make sure all the files exist in the root directory:

   - reports.php
   - notifications.php
   - messages.php
   - approvals.php

2. Check that the file names match exactly, including case (Windows is case-insensitive, but the code might reference the files with specific case).

### Database Connection Issues

If you encounter database connection errors:

1. Verify the database credentials in the `.env` file.
2. Make sure MySQL is running in XAMPP Control Panel.
3. Check that the database name matches what you created in phpMyAdmin.

### Upload Folder Permission Issues

If file uploads aren't working:

1. Check that the `uploads` directory exists and has the correct permissions.
2. Temporarily try setting full permissions to test:
   ```
   chmod 777 uploads
   ```
   (Use this only for testing, not for production)

### Empty or Broken Pages

If pages load but appear empty or broken:

1. Check PHP error logs at `C:\xampp\php\logs\php_error_log`
2. Enable error display for debugging by adding these lines to the top of index.php:
   ```php
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
   ```

## Admin Access

After setting up the database, you can create an admin user by registering normally and then updating the user's role in the database:

1. Register a new user through the application
2. Go to phpMyAdmin
3. Select the `uzima_expense` database
4. Open the `users` table
5. Find your user and change the `role` column value to "Admin"
6. Click "Go" to save changes

You should now have admin access to the application.

## Security Notes

For production deployment, consider these security improvements:

1. Use a non-root MySQL user with limited permissions
2. Set strong passwords for all database users
3. Configure Apache and PHP for production use
4. Remove any debugging code or tools
5. Use HTTPS with a valid SSL certificate
6. Regularly update XAMPP components for security patches

## Need Help?

If you encounter issues not covered in this guide, please refer to:

- The main project documentation
- XAMPP documentation: [https://www.apachefriends.org/docs/](https://www.apachefriends.org/docs/)
- PHP documentation: [https://www.php.net/docs.php](https://www.php.net/docs.php)
