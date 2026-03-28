# Requirements Document

## Introduction

This feature adds a "Remember Me" option to the oRentPHP login system to enable persistent authentication across browser sessions. Currently, users must re-login every time they close their browser because PHP sessions expire when the browser closes. This feature will allow users to opt-in to a 30-day persistent login using secure token-based authentication stored in cookies and a database table. The implementation will follow oRentPHP patterns including dark theme styling, idempotent migrations, and the UPDATE_SESSION_RULES.md workflow.

## Glossary

- **Remember_Me_System**: The subsystem responsible for persistent authentication across browser sessions
- **Token_Generator**: The component that creates cryptographically secure random tokens
- **Token_Validator**: The component that validates remember tokens on page load
- **Token_Store**: The database table that stores hashed remember tokens with expiry dates
- **Remember_Cookie**: The client-side cookie containing the unhashed token selector and validator
- **Token_Selector**: The first part of the remember token used to look up the token record (stored unhashed)
- **Token_Validator_Hash**: The second part of the remember token stored as a hash for security
- **Auto_Login**: The process of automatically authenticating a user based on a valid remember token
- **Session_Only_Login**: The current login behavior where authentication expires when browser closes
- **Secure_Token**: A cryptographically random token that cannot be guessed or brute-forced

## Requirements

### Requirement 1: Remember Me Checkbox on Login Form

**User Story:** As a user, I want a "Remember Me" checkbox on the login form, so that I can choose whether to stay logged in for 30 days.

#### Acceptance Criteria

1. WHEN the login form is displayed, THE Remember_Me_System SHALL display a "Remember Me" checkbox below the password field
2. THE Remember_Me_System SHALL render the checkbox as unchecked by default
3. THE Remember_Me_System SHALL label the checkbox with text "Remember me for 30 days"
4. THE Remember_Me_System SHALL style the checkbox consistently with the existing dark theme design
5. WHEN the checkbox is unchecked, THE Remember_Me_System SHALL perform session-only login (current behavior)

### Requirement 2: Remember Token Generation

**User Story:** As a system administrator, I want remember tokens to be cryptographically secure, so that user accounts cannot be compromised through token guessing.

#### Acceptance Criteria

1. WHEN a user logs in with "Remember Me" checked, THE Token_Generator SHALL generate a token selector of 16 random bytes
2. WHEN a user logs in with "Remember Me" checked, THE Token_Generator SHALL generate a token validator of 32 random bytes
3. THE Token_Generator SHALL use a cryptographically secure random number generator (random_bytes function)
4. THE Token_Generator SHALL encode the selector and validator as hexadecimal strings
5. THE Token_Generator SHALL combine selector and validator with a colon separator for the cookie value

### Requirement 3: Remember Token Storage

**User Story:** As a system administrator, I want remember tokens stored securely in the database, so that tokens cannot be used if the database is compromised.

#### Acceptance Criteria

1. THE Token_Store SHALL store the token selector as plain text for lookup
2. THE Token_Store SHALL hash the token validator using password_hash with PASSWORD_DEFAULT algorithm before storage
3. THE Token_Store SHALL store the user_id associated with the token
4. THE Token_Store SHALL store the token expiry timestamp as 30 days from creation
5. THE Token_Store SHALL store the token creation timestamp
6. THE Token_Store SHALL allow multiple active tokens per user (for multiple devices)

### Requirement 4: Remember Cookie Creation

**User Story:** As a user, I want my remember token stored in a secure cookie, so that I remain logged in across browser sessions.

#### Acceptance Criteria

1. WHEN a user logs in with "Remember Me" checked, THE Remember_Me_System SHALL create a cookie named "remember_token"
2. THE Remember_Me_System SHALL set the cookie expiry to 30 days from creation
3. THE Remember_Me_System SHALL set the cookie path to "/" to apply site-wide
4. THE Remember_Me_System SHALL set the HttpOnly flag to prevent JavaScript access
5. THE Remember_Me_System SHALL set the Secure flag to true when the site uses HTTPS
6. THE Remember_Me_System SHALL set the SameSite attribute to "Lax" to prevent CSRF attacks
7. THE Remember_Me_System SHALL store the combined selector:validator string as the cookie value

### Requirement 5: Auto-Login on Page Load

**User Story:** As a user, I want to be automatically logged in when I return to the site, so that I don't have to enter my credentials again.

#### Acceptance Criteria

1. WHEN a page loads and no active session exists, THE Token_Validator SHALL check for the remember_token cookie
2. WHEN the remember_token cookie exists, THE Token_Validator SHALL split the cookie value into selector and validator parts
3. WHEN the selector is found in the database, THE Token_Validator SHALL retrieve the stored hashed validator and expiry timestamp
4. WHEN the token has not expired, THE Token_Validator SHALL verify the validator using password_verify against the stored hash
5. WHEN the token is valid, THE Auto_Login SHALL create a new session for the user with the same structure as manual login
6. WHEN the token is valid, THE Auto_Login SHALL load user permissions from the database
7. WHEN the token is invalid or expired, THE Token_Validator SHALL delete the token from the database and clear the cookie
8. IF the token selector is not found in the database, THEN THE Token_Validator SHALL clear the remember_token cookie

### Requirement 6: Token Validation Integration

**User Story:** As a system administrator, I want token validation to run on every page load, so that remember me works transparently across the application.

#### Acceptance Criteria

1. THE Token_Validator SHALL run in config/db.php after session_start and before any auth_check calls
2. WHEN a valid session already exists, THE Token_Validator SHALL skip token validation
3. WHEN token validation succeeds, THE Token_Validator SHALL log the auto-login event using app_log
4. WHEN token validation fails, THE Token_Validator SHALL log the failure reason using app_log
5. THE Token_Validator SHALL complete within 100ms to avoid page load delays

### Requirement 7: Logout Token Cleanup

**User Story:** As a user, I want my remember token deleted when I log out, so that I must log in again on that device.

#### Acceptance Criteria

1. WHEN a user logs out, THE Remember_Me_System SHALL check for the remember_token cookie
2. WHEN the remember_token cookie exists, THE Remember_Me_System SHALL extract the selector from the cookie
3. WHEN the selector is extracted, THE Remember_Me_System SHALL delete the token record from the database
4. WHEN the selector is extracted, THE Remember_Me_System SHALL delete the remember_token cookie
5. THE Remember_Me_System SHALL set the cookie expiry to a past date to ensure browser deletion
6. THE Remember_Me_System SHALL perform logout cleanup before destroying the session

### Requirement 8: Token Expiry and Cleanup

**User Story:** As a system administrator, I want expired tokens automatically cleaned up, so that the database doesn't accumulate stale tokens.

#### Acceptance Criteria

1. WHEN a token is validated, THE Token_Validator SHALL check if the expiry timestamp is in the past
2. WHEN a token has expired, THE Token_Validator SHALL delete the token from the database
3. THE Token_Store SHALL provide a cleanup function that deletes all tokens with expiry timestamps older than the current time
4. THE cleanup function SHALL be callable via a scheduled task or cron job
5. THE cleanup function SHALL delete tokens in batches to avoid long-running queries

### Requirement 9: Database Schema for Remember Tokens

**User Story:** As a system administrator, I want a database table to store remember tokens, so that the system can validate tokens on subsequent visits.

#### Acceptance Criteria

1. THE Token_Store SHALL create a table named "remember_tokens" with columns: id, user_id, selector, validator_hash, expires_at, created_at
2. THE Token_Store SHALL set id as an auto-incrementing primary key
3. THE Token_Store SHALL set user_id as an integer foreign key referencing users.id
4. THE Token_Store SHALL set selector as a VARCHAR(32) with a unique index for fast lookup
5. THE Token_Store SHALL set validator_hash as a VARCHAR(255) to store password_hash output
6. THE Token_Store SHALL set expires_at as a DATETIME column
7. THE Token_Store SHALL set created_at as a DATETIME column with default CURRENT_TIMESTAMP
8. THE Token_Store SHALL add an index on expires_at for efficient cleanup queries
9. THE Token_Store SHALL add an index on user_id for efficient user token lookup

### Requirement 10: Migration File Creation

**User Story:** As a system administrator, I want a SQL migration file for this feature, so that I can safely apply database changes to production.

#### Acceptance Criteria

1. THE Token_Store SHALL provide a migration file at migrations/releases/2026-03-29_remember_me_tokens.sql
2. THE migration file SHALL use CREATE TABLE IF NOT EXISTS to ensure idempotency
3. THE migration file SHALL include a comment header with release ID, author, and description
4. THE migration file SHALL set FOREIGN_KEY_CHECKS to 0 before changes and restore to 1 after
5. THE migration file SHALL follow the SQL file template from UPDATE_SESSION_RULES.md

### Requirement 11: Production Database Steps Documentation

**User Story:** As a system administrator, I want the migration documented in PRODUCTION_DB_STEPS.md, so that I have a clear deployment checklist.

#### Acceptance Criteria

1. THE Token_Store SHALL add an entry to PRODUCTION_DB_STEPS.md under the Pending section
2. THE entry SHALL include the date 2026-03-29
3. THE entry SHALL include the release ID remember_me_tokens
4. THE entry SHALL include the SQL file path migrations/releases/2026-03-29_remember_me_tokens.sql
5. THE entry SHALL include notes describing the table being created and its purpose

### Requirement 12: Security Best Practices

**User Story:** As a security administrator, I want the remember me implementation to follow security best practices, so that user accounts remain secure.

#### Acceptance Criteria

1. THE Remember_Me_System SHALL never store passwords or password hashes in cookies
2. THE Remember_Me_System SHALL use timing-safe comparison for token validation to prevent timing attacks
3. THE Remember_Me_System SHALL generate new tokens on each login even if an old token exists
4. THE Remember_Me_System SHALL limit each user to a maximum of 5 active remember tokens
5. WHEN a user reaches the token limit, THE Remember_Me_System SHALL delete the oldest token before creating a new one
6. THE Remember_Me_System SHALL use constant-time string comparison (hash_equals) for validator verification
7. IF a token selector is found but validator verification fails, THEN THE Remember_Me_System SHALL delete all tokens for that user (potential compromise)

### Requirement 13: Remember Me Status Display

**User Story:** As a user, I want to see if I'm logged in via remember me, so that I understand my authentication status.

#### Acceptance Criteria

1. WHEN a user is auto-logged in via remember token, THE Remember_Me_System SHALL set a session flag indicating remember_me_login
2. WHEN the user profile or settings page is displayed, THE Remember_Me_System SHALL show "Logged in via Remember Me" if the flag is set
3. WHEN the user profile or settings page is displayed, THE Remember_Me_System SHALL show "Logged in via Password" if the flag is not set
4. THE Remember_Me_System SHALL provide a "Forget This Device" button that clears the current device's remember token
5. WHEN "Forget This Device" is clicked, THE Remember_Me_System SHALL delete only the current token, not all user tokens

### Requirement 14: Multi-Device Support

**User Story:** As a user, I want to use remember me on multiple devices, so that I can stay logged in on my phone, tablet, and computer.

#### Acceptance Criteria

1. THE Token_Store SHALL allow multiple active tokens per user_id
2. WHEN a user logs in with remember me on a new device, THE Remember_Me_System SHALL create a new token without deleting existing tokens
3. WHEN a user logs out on one device, THE Remember_Me_System SHALL delete only that device's token
4. WHEN a user changes their password, THE Remember_Me_System SHALL delete all remember tokens for that user
5. THE Remember_Me_System SHALL store a user_agent string with each token for device identification (optional display feature)

### Requirement 15: Error Handling and Logging

**User Story:** As a system administrator, I want comprehensive logging of remember me events, so that I can troubleshoot authentication issues.

#### Acceptance Criteria

1. WHEN a remember token is created, THE Remember_Me_System SHALL log the event with user_id and expiry date
2. WHEN auto-login succeeds, THE Remember_Me_System SHALL log the event with user_id and selector
3. WHEN auto-login fails due to invalid token, THE Remember_Me_System SHALL log the failure with reason
4. WHEN auto-login fails due to expired token, THE Remember_Me_System SHALL log the failure with expiry date
5. WHEN a token validation fails and all user tokens are deleted, THE Remember_Me_System SHALL log a security warning
6. THE Remember_Me_System SHALL use the existing app_log function with category "AUTH"
7. IF database errors occur during token operations, THEN THE Remember_Me_System SHALL log the error and fail gracefully without breaking the page
