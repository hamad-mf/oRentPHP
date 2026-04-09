# Bugfix Requirements Document

## Introduction

When a user adds a credit payment for a specific reservation, the system successfully records the payment in the ledger but continues to display the "Add Payment" button for that credit entry. This occurs because the credit payment system treats credit as a pool without linking payments to specific credit income entries. As a result, the UI cannot determine which entries have been paid and which haven't, leading to confusion and potential duplicate payments.

The bug affects reservation #47 where a $1,000 credit payment was successfully recorded at 13:07:45, but the "Add Payment" button still appears on the credit page for that entry.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user adds a credit payment for a credit income entry THEN the system displays the "Add Payment" button for that same entry even after the payment is successfully recorded

1.2 WHEN there is any remaining credit balance in the system THEN the system displays "Add Payment" buttons for ALL credit income entries regardless of whether they have been paid

1.3 WHEN multiple credit income entries exist and a payment is made THEN the system cannot identify which specific entry the payment was applied to

### Expected Behavior (Correct)

2.1 WHEN a user adds a credit payment that fully covers a credit income entry THEN the system SHALL hide or disable the "Add Payment" button for that specific entry

2.2 WHEN a user adds a credit payment that partially covers a credit income entry THEN the system SHALL update the button to reflect the remaining unpaid amount for that entry

2.3 WHEN a credit income entry has been fully paid THEN the system SHALL persist this state and not show the "Add Payment" button on subsequent page loads

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a credit income entry has not received any payment THEN the system SHALL CONTINUE TO display the "Add Payment" button with the full entry amount

3.2 WHEN the total credit balance is zero THEN the system SHALL CONTINUE TO hide all "Add Payment" buttons

3.3 WHEN a credit payment is recorded THEN the system SHALL CONTINUE TO create the correct ledger entries (credit expense and cash/bank income)

3.4 WHEN displaying credit transactions THEN the system SHALL CONTINUE TO show all entries with correct amounts, dates, and client information

3.5 WHEN the "prioritize income" toggle is enabled THEN the system SHALL CONTINUE TO sort income entries first
