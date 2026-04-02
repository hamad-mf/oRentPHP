# Bugfix Requirements Document

## Introduction

The Vehicle Inspection Job Card screen displays the error "Could not save inspection. Please try again." when users attempt to save an inspection. This bug prevents staff from recording vehicle inspections, blocking a critical workflow. Analysis reveals the issue is in the validation logic that checks the items array count, which may fail due to how PHP handles empty form fields or due to the strict count validation not accounting for the actual form structure.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user selects a vehicle and clicks "Save Inspection" THEN the system displays the error "Could not save inspection. Please try again."

1.2 WHEN the form is submitted THEN the validation check `count($items) !== 37` may fail if the items array structure doesn't match expectations

1.3 WHEN the validation fails THEN the database transaction is never initiated and no inspection data is saved

1.4 WHEN the validation error occurs THEN the actual cause is not logged or displayed to help diagnose the issue

### Expected Behavior (Correct)

2.1 WHEN a user fills out the inspection form with a selected vehicle THEN the system SHALL save the inspection successfully regardless of which optional "Check Table" or "Note" fields are filled

2.2 WHEN optional "Check Table" or "Note" fields are left empty THEN the system SHALL treat them as NULL values and save the inspection with 37 item records

2.3 WHEN the form is submitted THEN the system SHALL validate that all 37 item entries exist in the items array by checking for keys 1 through 37

2.4 WHEN validation fails THEN the system SHALL log the specific validation failure details to help diagnose issues

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user submits the form without selecting a vehicle THEN the system SHALL CONTINUE TO display the validation error "Please select a vehicle."

3.2 WHEN a user fills in "Check Table" or "Note" fields THEN the system SHALL CONTINUE TO save those values correctly in the database

3.3 WHEN the database save operation fails due to connection issues or constraint violations THEN the system SHALL CONTINUE TO rollback the transaction and display the error message

3.4 WHEN an inspection is saved successfully THEN the system SHALL CONTINUE TO log the action, display a success message, and redirect to clear the form
