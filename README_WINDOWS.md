# Uzima Expense Management System - Windows XAMPP Guide

This guide provides important information for running the Uzima Expense Management System on Windows using XAMPP. Follow these instructions to ensure the system works correctly on your Windows environment.

## What's Fixed

The following issues have been addressed in this update:

1. **Missing Navigation Links**: We've created the necessary files for the Reports, Notifications, Messages, and Approvals links that were previously causing "Not Found" errors.

2. **Database Schema**: We've added a database update script to ensure all required tables exist and have the correct structure.

3. **Navigation Structure**: A comprehensive navigation system has been implemented to ensure consistent navigation across all pages.

4. **File Path Handling**: The system now handles file paths in a way that's compatible with both macOS and Windows.

## Files Added

The following files have been added:

1. `notifications.php` - Handles user notifications
2. `messages.php` - Provides messaging functionality
3. `approvals.php` - Manages claim approvals workflow
4. `database_update.sql` - Database schema updates for Windows compatibility
5. `includes/nav.php` - Unified navigation component
6. `XAMPP_INSTALL_GUIDE.md` - Detailed installation guide for XAMPP

## Installation

For detailed installation instructions, please refer to the `XAMPP_INSTALL_GUIDE.md` file. Here's a quick summary:

1. Install XAMPP on your Windows machine
2. Copy the entire application to `C:\xampp\htdocs\uzima-expense` (or your preferred folder name)
3. Create a database in phpMyAdmin
4. Import `setup_database.sql`
5. Import `database_update.sql`
6. Configure the `.env` file with your database credentials
7. Ensure the `uploads` directory exists and has proper permissions
8. Access the application via `http://localhost/uzima-expense`

## Common Issues and Solutions

### Case Sensitivity

Windows file systems are case-insensitive, but PHP code may reference files with specific case. We've ensured all file references use consistent casing, but if you customize the system, keep this in mind.

### File Permissions

Windows and XAMPP handle file permissions differently than macOS or Linux. If you encounter permission issues:

1. Right-click on the `uploads` directory
2. Select "Properties"
3. Go to the "Security" tab
4. Click "Edit" and ensure the appropriate users have "Write" permissions

### Database Connection

If you encounter database connection issues:

1. Check that your `.env` file contains the correct database credentials
2. Ensure MySQL is running in XAMPP Control Panel
3. Verify that the database name matches what you created in phpMyAdmin

## Testing Your Installation

After installation, perform these tests to verify everything is working:

1. **Login/Registration**: Register a new account and log in
2. **Dashboard**: Ensure all dashboard elements display correctly
3. **Navigation Links**: Click on Reports, Notifications, Messages, and Approvals to verify they load correctly
4. **File Upload**: Create a new claim with an uploaded receipt to test file uploads
5. **Approval Workflow**: Create a claim and test the approval process (requires manager, admin, or finance officer account)

## Need Help?

If you encounter issues not covered in this guide:

1. Check the detailed `XAMPP_INSTALL_GUIDE.md` file
2. Look for error messages in the PHP error log: `C:\xampp\php\logs\php_error_log`
3. Enable error display for debugging:
   ```php
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
   ```

## Customization

When customizing the system for your organization:

1. Edit the company name, logo, and other settings in the database's `settings` table
2. Customize email templates in the `email_templates` table
3. Update the expense categories in the `expense_categories` table
4. Modify department information in the `departments` table

## Security Notes

For production use, ensure you:

1. Use strong passwords for all database users
2. Update XAMPP components regularly for security patches
3. Configure HTTPS with a valid SSL certificate
4. Use a non-root MySQL user with limited permissions
5. Remove any debugging code or tools before going live
