-- Release: 2026-03-22_client_photo
-- Author: AI Assistant
-- Safe: idempotent (IF NOT EXISTS)
-- Notes: Adds photo column to clients table for storing client profile picture path.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE clients
ADD COLUMN IF NOT EXISTS photo VARCHAR(500) DEFAULT NULL AFTER proof_file;

SET FOREIGN_KEY_CHECKS = 1;
