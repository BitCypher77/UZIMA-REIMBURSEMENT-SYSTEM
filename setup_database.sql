-- Uzima Enterprise Compensation Management System
-- Database Setup Script

-- Drop database if exists to start fresh
DROP DATABASE IF EXISTS uzima_reimbursement;
CREATE DATABASE uzima_reimbursement;
USE uzima_reimbursement;

-- Create Users Table with enhanced fields
CREATE TABLE users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    employeeID VARCHAR(20) UNIQUE,
    fullName VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department_id INT,
    position VARCHAR(100),
    role ENUM('Employee', 'Manager', 'FinanceOfficer', 'Admin') NOT NULL DEFAULT 'Employee',
    hire_date DATE,
    contact_number VARCHAR(20),
    profile_image VARCHAR(255) DEFAULT 'assets/images/default_profile.png',
    total_reimbursement DECIMAL(15, 2) DEFAULT 0.00,
    budget_limit DECIMAL(15, 2) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Department Table
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(20) NOT NULL UNIQUE,
    manager_id INT,
    budget_allocation DECIMAL(15, 2) DEFAULT 0.00,
    budget_remaining DECIMAL(15, 2) DEFAULT 0.00,
    fiscal_year_start DATE,
    fiscal_year_end DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(userID) ON DELETE SET NULL
);

-- Add foreign key to users table
ALTER TABLE users
ADD CONSTRAINT fk_department
FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL;

-- Create Expense Categories Table
CREATE TABLE expense_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    category_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    max_amount DECIMAL(15, 2) DEFAULT NULL,
    requires_approval_over DECIMAL(15, 2) DEFAULT NULL,
    receipt_required BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    gl_account VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Policies Table
CREATE TABLE policies (
    policy_id INT AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(100) NOT NULL,
    policy_description TEXT,
    policy_document VARCHAR(255),
    effective_date DATE,
    expiry_date DATE NULL,
    created_by INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(userID) ON DELETE SET NULL
);

-- Create Enhanced Claims Table
CREATE TABLE claims (
    claimID INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(50) UNIQUE,
    userID INT NOT NULL,
    department_id INT,
    category_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    description TEXT NOT NULL,
    purpose VARCHAR(255),
    incurred_date DATE NOT NULL,
    receipt_path VARCHAR(255),
    additional_documents TEXT,
    status ENUM('Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Paid', 'Cancelled') NOT NULL DEFAULT 'Draft',
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approval_date DATETIME,
    payment_date DATETIME,
    payment_reference VARCHAR(100),
    approverID INT,
    reviewer_id INT,
    remarks TEXT,
    rejection_reason TEXT,
    policy_id INT,
    tax_amount DECIMAL(15, 2) DEFAULT 0.00,
    billable_to_client BOOLEAN DEFAULT FALSE,
    client_id INT,
    project_id VARCHAR(50),
    is_recurring BOOLEAN DEFAULT FALSE,
    recurrence_pattern VARCHAR(50),
    is_advance BOOLEAN DEFAULT FALSE,
    advance_cleared BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (approverID) REFERENCES users(userID) ON DELETE SET NULL,
    FOREIGN KEY (reviewer_id) REFERENCES users(userID) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES expense_categories(category_id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    FOREIGN KEY (policy_id) REFERENCES policies(policy_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_submission_date (submission_date),
    INDEX idx_reference (reference_number)
);

-- Create Claim Audit Log Table
CREATE TABLE claim_audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    claimID INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_details TEXT,
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    performed_by INT,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    FOREIGN KEY (claimID) REFERENCES claims(claimID) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(userID) ON DELETE SET NULL
);

-- Create Budget Tracking Table
CREATE TABLE budget_tracking (
    tracking_id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    fiscal_period VARCHAR(20) NOT NULL,
    start_date DATE,
    end_date DATE,
    initial_budget DECIMAL(15, 2) DEFAULT 0.00,
    current_balance DECIMAL(15, 2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(userID) ON DELETE SET NULL
);

-- Create Budget Transactions Table
CREATE TABLE budget_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_id INT NOT NULL,
    transaction_type ENUM('Allocation', 'Expense', 'Adjustment', 'Transfer') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    related_claim_id INT,
    authorized_by INT,
    FOREIGN KEY (tracking_id) REFERENCES budget_tracking(tracking_id) ON DELETE CASCADE,
    FOREIGN KEY (related_claim_id) REFERENCES claims(claimID) ON DELETE SET NULL,
    FOREIGN KEY (authorized_by) REFERENCES users(userID) ON DELETE SET NULL
);

-- Create Approval Workflow Table
CREATE TABLE approval_workflows (
    workflow_id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_name VARCHAR(100) NOT NULL,
    department_id INT,
    category_id INT,
    min_amount DECIMAL(15, 2) DEFAULT 0.00,
    max_amount DECIMAL(15, 2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES expense_categories(category_id) ON DELETE CASCADE
);

-- Create Approval Steps Table
CREATE TABLE approval_steps (
    step_id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    step_order INT NOT NULL,
    approver_role ENUM('Manager', 'FinanceOfficer', 'Admin') NOT NULL,
    specific_approver_id INT,
    is_mandatory BOOLEAN DEFAULT TRUE,
    approval_timeout_hours INT DEFAULT 48,
    escalation_after_hours INT DEFAULT 72,
    escalation_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES approval_workflows(workflow_id) ON DELETE CASCADE,
    FOREIGN KEY (specific_approver_id) REFERENCES users(userID) ON DELETE SET NULL,
    FOREIGN KEY (escalation_to) REFERENCES users(userID) ON DELETE SET NULL
);

-- Create Claim Approvals Table
CREATE TABLE claim_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    claimID INT NOT NULL,
    step_id INT NOT NULL,
    approver_id INT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Escalated') DEFAULT 'Pending',
    decision_date DATETIME,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (claimID) REFERENCES claims(claimID) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES approval_steps(step_id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(userID) ON DELETE SET NULL
);

-- Create Notifications Table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    reference_id INT,
    reference_type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME,
    FOREIGN KEY (recipient_id) REFERENCES users(userID) ON DELETE CASCADE
);

-- Create Report Templates Table
CREATE TABLE report_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    template_description TEXT,
    template_type ENUM('Excel', 'PDF', 'CSV') NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_by INT,
    sql_query TEXT,
    parameters TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(userID) ON DELETE SET NULL
);

-- Create Integrations Table
CREATE TABLE integrations (
    integration_id INT AUTO_INCREMENT PRIMARY KEY,
    integration_name VARCHAR(100) NOT NULL,
    integration_type ENUM('Accounting', 'Banking', 'Payroll', 'API') NOT NULL,
    credentials TEXT,
    is_active BOOLEAN DEFAULT FALSE,
    last_sync DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Tax Settings Table
CREATE TABLE tax_settings (
    tax_id INT AUTO_INCREMENT PRIMARY KEY,
    tax_name VARCHAR(100) NOT NULL,
    tax_rate DECIMAL(10, 2) NOT NULL,
    tax_code VARCHAR(20),
    country VARCHAR(50),
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Currency Table
CREATE TABLE currencies (
    currency_id INT AUTO_INCREMENT PRIMARY KEY,
    currency_code VARCHAR(3) NOT NULL UNIQUE,
    currency_name VARCHAR(100) NOT NULL,
    currency_symbol VARCHAR(10) NOT NULL,
    exchange_rate DECIMAL(15, 6) DEFAULT 1.000000,
    is_base_currency BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Clients Table for billable expenses
CREATE TABLE clients (
    client_id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(100) NOT NULL,
    client_code VARCHAR(20) UNIQUE,
    contact_person VARCHAR(100),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    billing_address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Projects Table
CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_code VARCHAR(20) UNIQUE,
    project_name VARCHAR(100) NOT NULL,
    client_id INT,
    budget DECIMAL(15, 2),
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE SET NULL
);

-- Create System Settings Table
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create User Activity Logs
CREATE TABLE user_activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    activity_type VARCHAR(100) NOT NULL,
    activity_description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(userID) ON DELETE SET NULL
);

-- Insert initial data

-- Default Departments
INSERT INTO departments (department_name, department_code, budget_allocation, fiscal_year_start, fiscal_year_end) VALUES 
('Executive', 'EXEC', 500000.00, '2023-01-01', '2023-12-31'),
('Finance', 'FIN', 250000.00, '2023-01-01', '2023-12-31'),
('Human Resources', 'HR', 150000.00, '2023-01-01', '2023-12-31'),
('Information Technology', 'IT', 350000.00, '2023-01-01', '2023-12-31'),
('Marketing', 'MKT', 300000.00, '2023-01-01', '2023-12-31'),
('Operations', 'OPS', 400000.00, '2023-01-01', '2023-12-31'),
('Sales', 'SALES', 350000.00, '2023-01-01', '2023-12-31'),
('Research & Development', 'R&D', 450000.00, '2023-01-01', '2023-12-31');

-- Default admin user (Password: Admin@123)
INSERT INTO users (employeeID, fullName, email, password, department_id, position, role, hire_date) VALUES 
('EMP001', 'System Administrator', 'admin@uzima.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'System Administrator', 'Admin', '2023-01-01');

-- Default Finance Officer (Password: Finance@123)
INSERT INTO users (employeeID, fullName, email, password, department_id, position, role, hire_date) VALUES 
('EMP002', 'Finance Officer', 'finance@uzima.com', '$2y$10$okU9VF6H.C/OTBWK8fimCOGKEA4wBKn1eP9Nb0tGnpx76jlT0VH5.', 2, 'Finance Manager', 'FinanceOfficer', '2023-01-15');

-- Default Manager (Password: Manager@123)
INSERT INTO users (employeeID, fullName, email, password, department_id, position, role, hire_date) VALUES 
('EMP003', 'Department Manager', 'manager@uzima.com', '$2y$10$wfDs7xZRHFV/Ju0p7qlfVe.4sF3zDxtAZlM5YuFuCV4VD6R4MYN2.', 4, 'IT Manager', 'Manager', '2023-01-20');

-- Default Employee (Password: Employee@123)
INSERT INTO users (employeeID, fullName, email, password, department_id, position, role, hire_date) VALUES 
('EMP004', 'Regular Employee', 'employee@uzima.com', '$2y$10$uVtqU2ay.X/O57soNyIy8uGGnNrjA0y8r4YXHSXr2t7AdXbQ2Kj82', 4, 'Software Developer', 'Employee', '2023-02-01');

-- Update Department Managers
UPDATE departments SET manager_id = 3 WHERE department_id = 4;  -- IT Department
UPDATE departments SET manager_id = 1 WHERE department_id = 2;  -- Finance Department

-- Default Expense Categories
INSERT INTO expense_categories (category_name, category_code, description, max_amount, requires_approval_over, receipt_required, gl_account) VALUES
('Travel - Airfare', 'T-AIR', 'Flight tickets and related fees', 5000.00, 1000.00, TRUE, '6100-01'),
('Travel - Lodging', 'T-LODGE', 'Hotel and accommodation expenses', 3000.00, 500.00, TRUE, '6100-02'),
('Travel - Meals', 'T-MEAL', 'Food and beverages during business trips', 1000.00, 200.00, TRUE, '6100-03'),
('Travel - Ground Transportation', 'T-TRANS', 'Taxis, trains, rental cars, etc.', 1000.00, 200.00, TRUE, '6100-04'),
('Office Supplies', 'OFF-SUP', 'General office supplies and stationery', 500.00, 100.00, TRUE, '6200-01'),
('IT Equipment', 'IT-EQUIP', 'Computers, peripherals, and accessories', 3000.00, 500.00, TRUE, '6300-01'),
('Software & Subscriptions', 'IT-SOFT', 'Software licenses and online services', 2000.00, 300.00, TRUE, '6300-02'),
('Training & Development', 'TRAIN', 'Courses, certifications, and educational materials', 2500.00, 500.00, TRUE, '6400-01'),
('Conferences & Events', 'CONF', 'Registration fees and related expenses', 3000.00, 1000.00, TRUE, '6400-02'),
('Entertainment', 'ENTERTAIN', 'Client entertainment and business meals', 1000.00, 200.00, TRUE, '6500-01'),
('Marketing & Advertising', 'MKTG', 'Promotional materials and advertising expenses', 5000.00, 1000.00, TRUE, '6600-01'),
('Professional Services', 'PROF-SVC', 'Consulting, legal, and other professional fees', 10000.00, 2000.00, TRUE, '6700-01'),
('Telecommunications', 'TELECOM', 'Mobile phone and internet expenses', 500.00, 100.00, TRUE, '6800-01'),
('Health & Wellness', 'HEALTH', 'Medical expenses and wellness programs', 2000.00, 500.00, TRUE, '6900-01'),
('Miscellaneous', 'MISC', 'Other business-related expenses', 1000.00, 200.00, TRUE, '7000-01');

-- Default Policies
INSERT INTO policies (policy_name, policy_description, effective_date, created_by, is_active) VALUES
('General Expense Policy', 'Guidelines for all business expenses', '2023-01-01', 1, TRUE),
('Travel Policy', 'Rules and procedures for business travel', '2023-01-01', 1, TRUE),
('Per Diem Policy', 'Fixed allowances for travel expenses', '2023-01-01', 1, TRUE),
('Equipment Purchase Policy', 'Guidelines for purchasing business equipment', '2023-01-01', 1, TRUE);

-- Create Default Approval Workflows
INSERT INTO approval_workflows (workflow_name, department_id, category_id, min_amount, max_amount, is_active) VALUES
('Standard Expense Approval', NULL, NULL, 0.00, 1000.00, TRUE),
('High-Value Expense Approval', NULL, NULL, 1000.01, 5000.00, TRUE),
('Executive Approval', NULL, NULL, 5000.01, NULL, TRUE),
('IT Equipment Approval', 4, 6, 0.00, NULL, TRUE);

-- Create Default Approval Steps
INSERT INTO approval_steps (workflow_id, step_order, approver_role, specific_approver_id, is_mandatory) VALUES
(1, 1, 'Manager', NULL, TRUE),
(1, 2, 'FinanceOfficer', 2, TRUE),
(2, 1, 'Manager', NULL, TRUE),
(2, 2, 'FinanceOfficer', 2, TRUE),
(2, 3, 'Admin', 1, TRUE),
(3, 1, 'Manager', NULL, TRUE),
(3, 2, 'FinanceOfficer', 2, TRUE),
(3, 3, 'Admin', 1, TRUE),
(4, 1, 'Manager', 3, TRUE),
(4, 2, 'FinanceOfficer', 2, FALSE);

-- Default Currencies
INSERT INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate, is_base_currency, is_active) VALUES
('USD', 'US Dollar', '$', 1.000000, TRUE, TRUE),
('EUR', 'Euro', '€', 1.120000, FALSE, TRUE),
('GBP', 'British Pound', '£', 1.310000, FALSE, TRUE),
('JPY', 'Japanese Yen', '¥', 0.009120, FALSE, TRUE),
('CAD', 'Canadian Dollar', 'C$', 0.780000, FALSE, TRUE),
('AUD', 'Australian Dollar', 'A$', 0.750000, FALSE, TRUE);

-- Default Tax Settings
INSERT INTO tax_settings (tax_name, tax_rate, tax_code, country, is_default, is_active) VALUES
('No Tax', 0.00, 'NOTAX', 'Global', TRUE, TRUE),
('Standard VAT', 20.00, 'VAT20', 'United Kingdom', FALSE, TRUE),
('Reduced VAT', 5.00, 'VAT5', 'United Kingdom', FALSE, TRUE),
('Sales Tax', 7.00, 'STAX7', 'United States', FALSE, TRUE);

-- Default System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_description, is_public) VALUES
('company_name', 'Uzima Corporation', 'Company name for reports and interfaces', TRUE),
('company_logo', 'assets/images/uzima_logo.png', 'Company logo path', TRUE),
('fiscal_year_start', '01-01', 'Start of fiscal year (MM-DD)', TRUE),
('fiscal_year_end', '12-31', 'End of fiscal year (MM-DD)', TRUE),
('default_currency', 'USD', 'Default system currency', TRUE),
('expense_attachment_size_limit', '10', 'Maximum file size for expense attachments (MB)', TRUE),
('expense_attachment_types', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'Allowed file types for expense attachments', TRUE),
('approval_reminder_days', '2', 'Days after which to send approval reminders', FALSE),
('session_timeout', '30', 'Session timeout in minutes', FALSE),
('enable_2fa', 'false', 'Enable two-factor authentication', FALSE),
('smtp_host', '', 'SMTP server for email notifications', FALSE),
('smtp_port', '587', 'SMTP port', FALSE),
('smtp_user', '', 'SMTP username', FALSE),
('smtp_password', '', 'SMTP password (encrypted)', FALSE),
('smtp_from_email', 'noreply@uzima.com', 'From email for system notifications', FALSE),
('enable_audit_logs', 'true', 'Enable detailed audit logs', FALSE);

-- Create some sample clients
INSERT INTO clients (client_name, client_code, contact_person, contact_email, is_active) VALUES
('Acme Corporation', 'ACME-001', 'John Smith', 'john.smith@acme.com', TRUE),
('TechNova Solutions', 'TNOVA-001', 'Sarah Johnson', 'sarah.j@technova.com', TRUE),
('Global Industries', 'GLOBAL-001', 'Michael Wong', 'michael.w@globalind.com', TRUE);

-- Create sample projects
INSERT INTO projects (project_code, project_name, client_id, budget, start_date, end_date, is_active) VALUES
('PRJ-2023-001', 'Website Redesign', 1, 75000.00, '2023-03-01', '2023-08-31', TRUE),
('PRJ-2023-002', 'ERP Implementation', 2, 150000.00, '2023-02-15', '2023-12-31', TRUE),
('PRJ-2023-003', 'Mobile App Development', 3, 120000.00, '2023-04-01', '2023-10-31', TRUE);

-- Sample claims for testing
INSERT INTO claims (reference_number, userID, department_id, category_id, amount, currency, description, purpose, incurred_date, status, submission_date, policy_id)
VALUES
('CLM-2023-00001', 4, 4, 1, 850.75, 'USD', 'Flight to New York conference', 'Attending DevCon 2023', '2023-03-15', 'Approved', '2023-03-20 10:15:00', 2),
('CLM-2023-00002', 4, 4, 2, 625.50, 'USD', 'Hotel stay for 3 nights', 'Attending DevCon 2023', '2023-03-15', 'Approved', '2023-03-20 10:20:00', 2),
('CLM-2023-00003', 4, 4, 6, 1299.99, 'USD', 'New laptop for development', 'Replacement for damaged equipment', '2023-04-05', 'Under Review', '2023-04-07 14:30:00', 4),
('CLM-2023-00004', 3, 4, 9, 1500.00, 'USD', 'Conference registration fee', 'Annual Tech Leadership Summit', '2023-05-10', 'Submitted', '2023-05-12 09:45:00', 1); 