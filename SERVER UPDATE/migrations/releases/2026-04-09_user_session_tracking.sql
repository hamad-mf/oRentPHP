-- Migration: User Session Tracking for Real-Time Online/Offline Status
-- Date: 2026-04-09
-- Description: Adds session tracking columns to users table for real-time presence monitoring

-- Add session tracking columns to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0 COMMENT 'Whether user is currently logged in',
ADD COLUMN IF NOT EXISTS last_login_at DATETIME DEFAULT NULL COMMENT 'Timestamp of last login',
ADD COLUMN IF NOT EXISTS last_logout_at DATETIME DEFAULT NULL COMMENT 'Timestamp of last logout',
ADD COLUMN IF NOT EXISTS session_id VARCHAR(255) DEFAULT NULL COMMENT 'Current PHP session ID for tracking';

-- Add index for performance on online status queries
ALTER TABLE users
ADD INDEX IF NOT EXISTS idx_is_online (is_online);

-- Notes:
-- - is_online: 1 = logged in, 0 = logged out
-- - For staff: Also check attendance punch status to determine final online/offline state
-- - For admin: Only check is_online flag
-- - session_id: Used to validate active sessions and handle multi-device scenarios
