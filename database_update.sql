-- Database Schema Updates for Windows XAMPP Compatibility
-- Run this script on the Windows system to ensure all required tables exist

-- Check if notifications table exists and create if it doesn't
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    reference_id INT NULL,
    reference_type VARCHAR(50) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_recipient (recipient_id),
    INDEX idx_read_status (is_read),
    INDEX idx_created_at (created_at),
    CONSTRAINT fk_notification_recipient FOREIGN KEY (recipient_id) REFERENCES users (userID) ON DELETE CASCADE
);

-- Check if conversations table exists and create if it doesn't
CREATE TABLE IF NOT EXISTS conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user1 (user1_id),
    INDEX idx_user2 (user2_id),
    INDEX idx_last_message (last_message_at),
    CONSTRAINT fk_conversation_user1 FOREIGN KEY (user1_id) REFERENCES users (userID) ON DELETE CASCADE,
    CONSTRAINT fk_conversation_user2 FOREIGN KEY (user2_id) REFERENCES users (userID) ON DELETE CASCADE
);

-- Check if messages table exists and create if it doesn't
CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    message TEXT NOT NULL,
    sent_at DATETIME NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_conversation (conversation_id),
    INDEX idx_sender (sender_id),
    INDEX idx_recipient (recipient_id),
    INDEX idx_read_status (is_read),
    INDEX idx_sent_at (sent_at),
    CONSTRAINT fk_message_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (conversation_id) ON DELETE CASCADE,
    CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users (userID) ON DELETE CASCADE,
    CONSTRAINT fk_message_recipient FOREIGN KEY (recipient_id) REFERENCES users (userID) ON DELETE CASCADE
);

-- Check if claim_approvals table exists and create if it doesn't
CREATE TABLE IF NOT EXISTS claim_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    claimID INT NOT NULL,
    approver_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    approval_date DATETIME NOT NULL,
    INDEX idx_claim (claimID),
    INDEX idx_approver (approver_id),
    CONSTRAINT fk_approval_claim FOREIGN KEY (claimID) REFERENCES claims (claimID) ON DELETE CASCADE,
    CONSTRAINT fk_approval_approver FOREIGN KEY (approver_id) REFERENCES users (userID) ON DELETE CASCADE
);

-- Add recipient_id to messages if it doesn't exist
-- This is a safe operation that won't fail if the column already exists
SET @exist := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'messages'
    AND COLUMN_NAME = 'recipient_id'
);

SET @query = IF(@exist = 0,
    'ALTER TABLE messages ADD COLUMN recipient_id INT NOT NULL AFTER sender_id',
    'SELECT "Column recipient_id already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fix the last_message_at column if needed (ensure it has a DEFAULT value)
SET @exist := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'conversations'
    AND COLUMN_NAME = 'last_message_at'
);

SET @query = IF(@exist = 1,
    'ALTER TABLE conversations MODIFY COLUMN last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT "Column last_message_at is already properly configured"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add missing indexes if they don't exist
-- For notifications table
SET @exist := (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE()
    AND table_name = 'notifications'
    AND index_name = 'idx_recipient'
);

SET @query = IF(@exist = 0,
    'ALTER TABLE notifications ADD INDEX idx_recipient (recipient_id)',
    'SELECT "Index idx_recipient already exists on notifications table"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add trigger to update last_message_at in conversations table
DELIMITER //
DROP TRIGGER IF EXISTS update_conversation_timestamp //
CREATE TRIGGER update_conversation_timestamp
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    UPDATE conversations 
    SET last_message_at = NEW.sent_at 
    WHERE conversation_id = NEW.conversation_id;
END//
DELIMITER ; 