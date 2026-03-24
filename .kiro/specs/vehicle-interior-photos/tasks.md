# Implementation Plan: Vehicle Interior Photos

## Overview

Replace the single `interior` photo slot in `deliver.php` and `return.php` with dynamic multi-slot support (1–15 photos), using indexed field names `photos[interior_N]`. No database changes required.

## Tasks

- [x] 1. Update deliver.php validation
  - Replace `interior` in `$requiredPhotos` with a separate loop counting `interior_1`…`interior_15`
  - Require at least 1 successful interior upload; error if zero
  - _Requirements: 1.1, 1.2, 1.3_

- [x] 2. Update deliver.php HTML form and JS
  - [x] 2.1 Replace `$photoViews` interior entry with dynamic interior slots block
    - Remove `'interior' => 'Interior'` from `$photoViews`
    - Add dynamic interior slots container with 1 default slot after the foreach
    - Add "Add another interior photo" button (disabled at 15 slots)
    - _Requirements: 1.1, 4.1, 4.2, 4.3_
  - [x] 2.2 Add interior slot JS to deliver.php `$extraScripts`
    - Implement add/remove slot logic with re-indexing
    - Disable add button at MAX=15
    - _Requirements: 4.2, 4.3, 4.4_

- [x] 3. Update return.php validation
  - Replace `interior` in `$requiredPhotos` with a separate loop counting `interior_1`…`interior_15`
  - Require at least 1 successful interior upload; error if zero
  - _Requirements: 2.1, 2.2, 2.3_

- [x] 4. Update return.php HTML form and JS
  - [x] 4.1 Replace `$photoViews` interior entry with dynamic interior slots block (green accent)
    - Remove `'interior' => 'Interior'` from `$photoViews`
    - Add dynamic interior slots container with 1 default slot after the foreach
    - _Requirements: 2.1, 4.1, 4.2, 4.3_
  - [x] 4.2 Add interior slot JS to return.php `$extraScripts`
    - Same add/remove/reindex logic as deliver.php
    - _Requirements: 4.2, 4.3, 4.4_

- [x] 5. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
