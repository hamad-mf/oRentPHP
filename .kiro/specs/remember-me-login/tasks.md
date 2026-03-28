# Implementation Plan: Remember Me Login

## Overview

This implementation adds a secure "Remember Me" feature to the oRentPHP login system using the selector/validator dual-token pattern. The feature enables persistent authentication across browser sessions for 30 days while maintaining security through token hashing, secure cookies, and comprehensive logging. Implementation follows oRentPHP patterns including idempotent migrations, UPDATE_SESSION_RULES.md workflow, and integration with existing session management in config/db.php.

## Tasks

- [x] 1. Create database migration for remember_tokens table
  - Create migration file at `migrations/releases/2026-03-29_remember_me_tokens.sql`
  - Use CREATE TABLE IF NOT EXISTS for idempotency
  - Include columns: id, user_id, selector, validator_hash, expires_at, created_at
  - Add indexes on selector (unique), expires_at, and user_id
  - Add foreign key constraint to users table with ON DELETE CASCADE
  - Follow SQL file template from UPDATE_SESSION_RULES.md
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 10.2, 10.4, 10.5_

- [x] 2. Update PRODUCTION_DB_STEPS.md with migration entry
  - Add entry to Pending section with date 2026-03-29
  - Include release ID: remember_me_tokens
  - Include SQL file path: migrations/releases/2026-03-29_remember_me_tokens.sql
  - Add notes describing the remember_tokens table and its purpose
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [x] 3. Implement token management helper functions
  - [x] 3.1 Create includes/remember_token_helpers.php with core functions
    - Implement generate_remember_token($user_id): generates selector (16 bytes), validator (32 bytes), hashes validator, stores in database, returns cookie value
    - Implement delete_remember_token($selector): deletes specific token by selector
    - Implement delete_all_user_tokens($user_id): deletes all tokens for a user
    - Implement count_user_tokens($user_id): counts active non-expired tokens
    - Implement delete_oldest_user_token($user_id): deletes token with oldest created_at
    - Implement clear_remember_cookie(): clears remember_token cookie with past expiry
    - Implement cleanup_expired_tokens(): batch deletes all expired tokens
    - Use random_bytes() for cryptographic randomness
    - Use password_hash() with PASSWORD_DEFAULT for validator hashing
    - Include comprehensive error handling with try-catch blocks
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 7.1, 7.2, 7.3, 7.4, 7.5, 8.1, 8.2, 8.3, 12.4, 12.5_

  - [ ]* 3.2 Write property test for token generation
    - **Property 1: Token Generation Round Trip**
    - **Property 2: Token Selector and Validator Length**
    - **Validates: Requirements 2.1, 2.2, 2.4, 2.5, 3.1, 3.2**

  - [ ]* 3.3 Write property test for token storage integrity
    - **Property 3: Token Storage Integrity**
    - **Validates: Requirements 3.1, 3.2**

  - [ ]* 3.4 Write property test for token expiry calculation
    - **Property 4: Token Expiry Calculation**
    - **Validates: Requirements 3.4**

- [x] 4. Implement token validation in config/db.php
  - [x] 4.1 Add validate_remember_token() function to config/db.php
    - Skip validation if session already exists
    - Check for remember_token cookie
    - Parse cookie value into selector and validator
    - Look up token by selector in database
    - Check token expiry timestamp
    - Verify validator using password_verify() against stored hash
    - On success: create session with user data and permissions, set remember_me_login flag
    - On failure: delete token and clear cookie
    - On validator mismatch: delete ALL user tokens (security event)
    - Include comprehensive logging for all outcomes
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 6.1, 6.2, 6.3, 6.4, 6.5, 12.6, 12.7, 15.2, 15.3, 15.4, 15.5, 15.7_

  - [x] 4.2 Call validate_remember_token() after session_start() in config/db.php
    - Place call immediately after session_start() line
    - Ensure it runs before any auth_check() calls
    - Wrap in try-catch for graceful error handling
    - _Requirements: 6.1, 6.2, 15.7_

  - [ ]* 4.3 Write property test for token validation
    - **Property 9: Token Validation Skips When Session Exists**
    - **Property 10: Expired Token Cleanup**
    - **Property 11: Missing Selector Cleanup**
    - **Validates: Requirements 5.7, 5.8, 6.2, 8.1, 8.2**

  - [ ]* 4.4 Write property test for auto-login session structure
    - **Property 12: Auto-Login Session Structure**
    - **Property 13: Auto-Login Session Flag**
    - **Validates: Requirements 5.5, 5.6, 13.1**

  - [ ]* 4.5 Write property test for security response
    - **Property 21: Validator Mismatch Security Response**
    - **Validates: Requirements 12.7, 15.5**

- [x] 5. Checkpoint - Ensure token generation and validation work
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Modify login form to add Remember Me checkbox
  - [x] 6.1 Add checkbox HTML to auth/login.php
    - Add checkbox input after password field, before submit button
    - Use name="remember_me" with value="on"
    - Set checkbox as unchecked by default
    - Add label "Remember me for 30 days"
    - Style consistently with existing dark theme (mb-black, mb-surface, mb-accent colors)
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 6.2 Process remember_me POST parameter in auth/login.php
    - After successful password verification and session creation
    - Check if $_POST['remember_me'] is set
    - If set: call generate_remember_token() with user_id
    - Enforce 5-token limit: if user has 5 tokens, delete oldest before creating new one
    - Set secure cookie with returned token value
    - Use setcookie() with 30-day expiry, HttpOnly, Secure (if HTTPS), SameSite=Lax
    - Log token creation event
    - If not set: skip token generation (session-only login)
    - _Requirements: 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 12.3, 12.4, 12.5, 15.1_

  - [ ]* 6.3 Write property test for cookie security configuration
    - **Property 5: Cookie Security Configuration**
    - **Property 6: Cookie Value Format**
    - **Validates: Requirements 2.5, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7**

  - [ ]* 6.4 Write property test for session-only login
    - **Property 7: Session-Only Login Without Remember Me**
    - **Validates: Requirements 1.5**

  - [ ]* 6.5 Write property test for token limit enforcement
    - **Property 16: Token Limit Enforcement**
    - **Property 17: Oldest Token Deletion at Limit**
    - **Validates: Requirements 12.4, 12.5**

- [x] 7. Modify logout to clean up remember tokens
  - [x] 7.1 Update auth/logout.php to handle remember tokens
    - Before session destruction, check for remember_token cookie
    - If cookie exists, extract selector from cookie value
    - Delete token record from database using selector
    - Clear remember_token cookie by setting past expiry
    - Log token deletion event
    - Continue with existing session destruction logic
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 14.3_

  - [ ]* 7.2 Write property test for device-specific logout
    - **Property 14: Device-Specific Logout**
    - **Property 15: Cookie Deletion Mechanism**
    - **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 14.3**

- [x] 8. Checkpoint - Ensure login and logout flows work correctly
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Implement multi-device support features
  - [x] 9.1 Add token limit enforcement to generate_remember_token()
    - Check token count before generation
    - If count >= 5, call delete_oldest_user_token()
    - Log when oldest token is deleted due to limit
    - _Requirements: 12.4, 12.5, 14.1, 14.2_

  - [x] 9.2 Add password change token invalidation
    - Identify password change location (likely in user/staff edit pages)
    - After successful password update, call delete_all_user_tokens($user_id)
    - Log token invalidation event
    - _Requirements: 14.4_

  - [ ]* 9.3 Write property test for multi-device support
    - **Property 8: Multi-Device Token Support**
    - **Property 22: Password Change Token Invalidation**
    - **Property 23: New Token Generation on Each Login**
    - **Validates: Requirements 3.6, 12.3, 14.1, 14.2, 14.4**

- [x] 10. Add logging for all remember me events
  - [x] 10.1 Add logging to token generation
    - Log with category "AUTH" when token is created
    - Include user_id and expiry date in log message
    - _Requirements: 15.1_

  - [x] 10.2 Add logging to token validation
    - Log success with user_id and selector
    - Log failures with specific reason (expired, invalid, missing)
    - Log security warnings for validator mismatches
    - Use category "AUTH" for all logs
    - _Requirements: 6.3, 6.4, 15.2, 15.3, 15.4, 15.5_

  - [x] 10.3 Add logging to logout token cleanup
    - Log when token is deleted during logout
    - Include user_id and selector
    - _Requirements: 15.1_

  - [ ]* 10.4 Write property test for logging
    - **Property 18: Token Creation Logging**
    - **Property 19: Auto-Login Success Logging**
    - **Property 20: Auto-Login Failure Logging**
    - **Validates: Requirements 6.3, 6.4, 15.1, 15.2, 15.3, 15.4**

- [x] 11. Implement security best practices
  - [x] 11.1 Add timing-safe comparison for token validation
    - Ensure password_verify() is used (already timing-safe)
    - Verify no custom string comparisons are used for validators
    - _Requirements: 12.2, 12.6_

  - [x] 11.2 Add validator mismatch security response
    - When password_verify() fails, delete all user tokens
    - Log security warning with user_id
    - Clear cookie
    - _Requirements: 12.7, 15.5_

  - [x] 11.3 Verify no password data in cookies
    - Review cookie value format (selector:validator only)
    - Ensure no password_hash or password is included
    - _Requirements: 12.1_

  - [ ]* 11.4 Write property test for security properties
    - **Property 24: No Password Data in Cookies**
    - **Property 26: Graceful Database Error Handling**
    - **Validates: Requirements 12.1, 15.7**

- [x] 12. Final checkpoint - Integration testing and verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional property-based tests and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties from the design document
- Unit tests (not listed) should validate specific examples and edge cases
- The migration SQL file must be run manually on production via phpMyAdmin before code deployment
- Follow UPDATE_SESSION_RULES.md: never auto-run migrations, always review SQL first
- Token validation runs transparently in config/db.php, no changes needed to existing pages
- Multi-device support is built-in: each device gets its own token, logout only affects current device
- Security is enforced through: token hashing, secure cookies, timing-safe comparison, and comprehensive logging
