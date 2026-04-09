# Design Document: Delivery Inspection Preview on Return

## Overview

This feature adds a read-only preview section to the vehicle return screen (reservations/return.php) that displays delivery inspection data. When staff process a vehicle return, they need to reference the delivery condition (location, mileage, fuel level, photos, and notes) to compare against return readings and identify changes or damage.

The preview section will:
- Query delivery inspection data from existing database tables (no schema changes needed)
- Display data in a collapsible, read-only format above the return form
- Handle backward compatibility for old reservations without delivery data
- Organize photos by type (standard views, interior, scratch/damage)

This is a display-only feature that leverages existing data structures without requiring database migrations.

## Architecture

### Component Structure

```
reservations/return.php
├── Delivery Preview Section (NEW)
│   ├── Collapsible Header
│   ├── Delivery Metadata (location, mileage, fuel, notes)
│   ├── Standard Photos Grid (front, back, left, right, odometer, with_customer)
│   ├── Interior Photos Grid (interior_1 through interior_15)
│   └── Scratch/Damage Photos Grid (from reservation_scratch_photos)
└── Existing Return Form (unchanged)
```

### Data Flow

1. Page loads → Query delivery inspection data
2. Check if delivery inspection exists
3. If exists: Query photos from inspection_photos and reservation_scratch_photos
4. Render preview section with collapsible UI
5. Continue with existing return form logic

### Database Queries

**Delivery Inspection Query:**
```sql
SELECT * FROM vehicle_inspections 
WHERE reservation_id = ? AND type = 'delivery' 
LIMIT 1
```

**Standard Photos Query:**
```sql
SELECT * FROM inspection_photos 
WHERE inspection_id = ? 
ORDER BY view_name
```

**Scratch Photos Query:**
```sql
SELECT * FROM reservation_scratch_photos 
WHERE reservation_id = ? AND event_type = 'delivery' 
ORDER BY slot_index
```

**Delivery Location Query:**
```sql
SELECT delivery_location FROM reservations WHERE id = ?
```

## Components and Interfaces

### Preview Section Component

**Location:** reservations/return.php (inline HTML/PHP)

**Responsibilities:**
- Query and display delivery inspection data
- Handle missing data gracefully
- Provide collapsible UI with summary
- Display photos in organized grids
- Implement photo lightbox/modal on click

**State:**
- `$deliveryInspection`: Delivery inspection record or null
- `$deliveryPhotos`: Array of standard inspection photos
- `$scratchPhotos`: Array of scratch/damage photos
- `$deliveryLocation`: Location string from reservations table
- `$isCollapsed`: Boolean for collapse state (client-side)

### Photo Display Component

**Responsibilities:**
- Render photo thumbnails with consistent sizing
- Group photos by type (standard, interior, scratch)
- Provide click-to-enlarge functionality
- Handle missing photos gracefully

**Photo Categories:**
1. **Standard Views**: front, back, left, right, odometer, with_customer
2. **Interior**: interior_1 through interior_15
3. **Scratch/Damage**: Photos from reservation_scratch_photos table

## Data Models

### Existing Tables (No Changes)

**vehicle_inspections**
- `id`: INT (PK)
- `reservation_id`: INT (FK)
- `type`: ENUM('delivery', 'return')
- `fuel_level`: INT (0-100)
- `mileage`: INT
- `notes`: TEXT
- `created_at`: TIMESTAMP

**inspection_photos**
- `id`: INT (PK)
- `inspection_id`: INT (FK)
- `view_name`: VARCHAR(50) - e.g., 'front', 'interior_1'
- `file_path`: VARCHAR(500)
- `created_at`: TIMESTAMP

**reservation_scratch_photos**
- `id`: INT (PK)
- `reservation_id`: INT (FK)
- `event_type`: ENUM('delivery', 'return')
- `slot_index`: TINYINT (1-15)
- `file_path`: VARCHAR(255)
- `created_at`: DATETIME

**reservations**
- `delivery_location`: VARCHAR(255) - Added in previous feature

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Delivery data display completeness

*For any* reservation with a delivery inspection record, the preview section should display all non-null fields from that record (mileage, fuel_level, notes).

**Validates: Requirements 1.3, 1.4, 1.5**

### Property 2: Photo display completeness

*For any* reservation with delivery photos in the inspection_photos table, all photos associated with the delivery inspection should be rendered in the preview section.

**Validates: Requirements 1.6, 2.1**

### Property 3: Scratch photo separation

*For any* reservation with scratch photos (event_type='delivery'), those photos should be displayed in a separate section from standard inspection photos.

**Validates: Requirements 1.7, 2.2**

### Property 4: Read-only data display

*For any* data displayed in the preview section, there should be no editable form elements (input, textarea, select) within the preview container.

**Validates: Requirements 4.3**

### Property 5: Location display

*For any* reservation with a non-null delivery_location value, that location should be displayed in the preview section.

**Validates: Requirements 1.2**

## Error Handling

### Missing Data Scenarios

1. **No Delivery Inspection Record**
   - Display: "No delivery inspection data available for this reservation"
   - Reason: Old reservations before inspection feature was implemented
   - Action: Show message, hide data fields, continue with return form

2. **Inspection Exists, No Location**
   - Display: "Location not recorded" in location field
   - Reason: Delivery completed before location tracking was added
   - Action: Show other inspection data normally

3. **Inspection Exists, No Photos**
   - Display: "No photos available" in photos section
   - Reason: Photos failed to upload or were not captured
   - Action: Show inspection metadata, hide photo grids

4. **Partial Photo Sets**
   - Display: Only available photos
   - Reason: Some photos failed to upload
   - Action: Render available photos, no error message needed

### Database Query Failures

- Wrap queries in try-catch blocks
- Log errors using `app_log('ERROR', ...)`
- Display generic message: "Unable to load delivery data"
- Allow return form to function normally

### File Access Issues

- Check if photo files exist before rendering
- Display placeholder or skip missing photos
- Log missing files for investigation
- Don't block return process

## Testing Strategy

### Unit Testing Approach

**Test Coverage:**
- Specific examples of data display (e.g., "Location: Airport Terminal 3")
- Edge cases (no inspection, no photos, no location)
- Error conditions (missing files, query failures)
- UI interactions (collapse/expand, photo click)

**Example Unit Tests:**
1. Test preview section renders when delivery inspection exists
2. Test "No delivery inspection data available" message when inspection is null
3. Test "Location not recorded" when delivery_location is null
4. Test "No photos available" when photo arrays are empty
5. Test collapse/expand toggle functionality
6. Test photo modal opens on click

### Property-Based Testing Approach

**Library:** PHPUnit with property testing extensions (or manual property test implementation)

**Configuration:**
- Minimum 100 iterations per property test
- Generate random reservation data with varying states
- Test across different data combinations

**Property Tests:**

1. **Property Test 1: Delivery data display completeness**
   - Generate: Random reservations with delivery inspections
   - Test: All non-null inspection fields appear in rendered HTML
   - Tag: `Feature: delivery-inspection-preview-on-return, Property 1: For any reservation with a delivery inspection record, the preview section should display all non-null fields`

2. **Property Test 2: Photo display completeness**
   - Generate: Random reservations with varying numbers of photos
   - Test: Count of rendered photo elements matches database photo count
   - Tag: `Feature: delivery-inspection-preview-on-return, Property 2: For any reservation with delivery photos, all photos should be rendered`

3. **Property Test 3: Scratch photo separation**
   - Generate: Random reservations with both standard and scratch photos
   - Test: Scratch photos appear in separate container from standard photos
   - Tag: `Feature: delivery-inspection-preview-on-return, Property 3: Scratch photos should be displayed separately`

4. **Property Test 4: Read-only data display**
   - Generate: Random reservations with delivery data
   - Test: Preview section HTML contains no input/textarea/select elements
   - Tag: `Feature: delivery-inspection-preview-on-return, Property 4: Preview section should have no editable fields`

5. **Property Test 5: Location display**
   - Generate: Random reservations with non-null delivery_location
   - Test: Location string appears in preview section HTML
   - Tag: `Feature: delivery-inspection-preview-on-return, Property 5: Non-null delivery locations should be displayed`

### Integration Testing

- Test with real database containing old reservations (no delivery data)
- Test with reservations having partial data (inspection but no photos)
- Test with complete delivery data
- Verify return form continues to work normally in all cases
- Test photo file access with missing files

### Manual Testing Checklist

- [ ] Preview section appears above return form
- [ ] Collapse/expand functionality works smoothly
- [ ] Photos display in correct categories
- [ ] Photo click opens lightbox/modal
- [ ] Layout is visually distinct from return form
- [ ] Old reservations show appropriate "no data" message
- [ ] Return form submission works with preview section present
- [ ] Mobile responsive layout works correctly

## Implementation Notes

### Code Location

All changes will be made in `reservations/return.php`:
- Add delivery data queries after existing reservation query
- Insert preview section HTML before existing form
- Add JavaScript for collapse/expand and photo modal
- Add CSS for preview section styling

### Styling Approach

Use existing Tailwind CSS classes from the application:
- `bg-mb-surface` for preview container background
- `border-mb-subtle/20` for borders
- `text-mb-silver` for labels
- `text-white` for data values
- Distinct border color (e.g., `border-blue-500/30`) to differentiate from return form

### JavaScript Requirements

**Collapse/Expand:**
```javascript
function toggleDeliveryPreview() {
    const content = document.getElementById('deliveryPreviewContent');
    const summary = document.getElementById('deliveryPreviewSummary');
    const icon = document.getElementById('deliveryPreviewIcon');
    
    content.classList.toggle('hidden');
    summary.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}
```

**Photo Modal:**
```javascript
function openPhotoModal(imageSrc) {
    // Create/show modal with full-size image
    // Add close button and click-outside-to-close
}
```

### Performance Considerations

- Queries are simple and indexed (reservation_id, inspection_id)
- Photo thumbnails should be reasonably sized (max-width: 200px)
- Lazy load photos if more than 20 total photos
- Consider caching delivery data in session if return form is re-rendered after validation errors

### Backward Compatibility

- All queries use `LEFT JOIN` or check for null
- Missing data shows friendly messages, not errors
- Return form functionality is completely independent
- No breaking changes to existing code

## Security Considerations

- Verify user has `do_return` permission (already checked at page top)
- Ensure reservation_id is validated and belongs to accessible reservations
- Photo file paths should be validated to prevent directory traversal
- Use `e()` function for all output to prevent XSS
- No new user input in preview section (read-only)

## Future Enhancements

1. **Photo Comparison View**: Side-by-side delivery vs return photos
2. **Damage Highlighting**: Visual indicators on photos showing damage areas
3. **Export to PDF**: Include delivery preview in reservation bill/report
4. **Photo Annotations**: Allow staff to add notes to specific photos
5. **Mobile App Integration**: Display delivery preview in mobile return flow
