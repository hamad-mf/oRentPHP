# Bugfix Requirements Document

## Introduction

When editing a client who doesn't currently have a profile photo (photo field is NULL) and attempting to add a photo for the first time, the system incorrectly displays an error message "photo already in use". This validation error should only apply when checking if a photo file path is being used by a different client, not when the current client has no photo. This bug prevents users from adding photos to clients who don't have any photo yet.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a client has no profile photo (photo field is NULL) AND a user attempts to add a photo during edit THEN the system incorrectly shows error "photo already in use"

1.2 WHEN a client has no profile photo (photo field is NULL) AND the photo being uploaded is not used by any other client THEN the system still prevents the photo upload with a false positive validation error

### Expected Behavior (Correct)

2.1 WHEN a client has no profile photo (photo field is NULL) AND a user attempts to add a photo during edit THEN the system SHALL allow the photo upload without showing "photo already in use" error

2.2 WHEN a client has no profile photo (photo field is NULL) AND the photo being uploaded is not used by any other client THEN the system SHALL successfully save the photo to the client record

2.3 WHEN a client has an existing profile photo AND a user attempts to change it to a photo that is already used by a different client THEN the system SHALL show "photo already in use" error

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a client has an existing profile photo AND a user attempts to change it to a different photo that is not used by any other client THEN the system SHALL CONTINUE TO allow the photo update successfully

3.2 WHEN a client has an existing profile photo AND a user leaves the photo unchanged during edit THEN the system SHALL CONTINUE TO preserve the existing photo without validation errors

3.3 WHEN email or phone uniqueness validation is triggered THEN the system SHALL CONTINUE TO validate these fields correctly excluding the current client's own records
