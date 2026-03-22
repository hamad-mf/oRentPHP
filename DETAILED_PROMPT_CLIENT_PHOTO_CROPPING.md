# DETAILED PROMPT: Client Photo + Image Cropping Feature

## WHAT YOU NEED TO BUILD

### Feature 1: Client Photo Upload
- Add option to upload a profile photo when creating/editing a client
- Store the photo path in the `clients` table (add `photo` column if not exists)
- Display the photo in a circular frame on the client details page

### Feature 2: Image Cropping
- When uploading client photo OR client proof images, show a crop interface
- Use Cropper.js library (https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js)
- Crop to square aspect ratio for client photo, free ratio for proof documents
- After cropping, save the cropped image

---

## FILES TO EDIT

| File | Action | Purpose |
|------|--------|---------|
| `clients/create.php` | Modify | Add client photo upload with crop |
| `clients/edit.php` | Modify | Add client photo upload/edit with crop |
| `clients/show.php` | Modify | Display client photo in profile |
| `migrations/releases/2026-03-22_client_photo.sql` | Create | Add `photo` column to clients table |

---

## DATABASE CHANGE

### New SQL File: `migrations/releases/2026-03-22_client_photo.sql`

```sql
-- Release: 2026-03-22_client_photo
-- Author: AI Assistant
-- Safe: idempotent (IF NOT EXISTS)
-- Notes: Adds photo column to clients table for profile picture.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS photo VARCHAR(500) DEFAULT NULL AFTER proof_file;

SET FOREIGN_KEY_CHECKS = 1;
```

**Note**: Add this migration to `PRODUCTION_DB_STEPS.md` under "Pending".

---

## IMPLEMENTATION: `clients/create.php`

### 1. Add Cropper.js CSS and JS (in header section, before closing `</head>`):

Add this around line 143 (before `require_once header.php`):
```php
<?php
// Flag for pages that need image cropping
$needsCropper = true;
?>
```

### 2. Replace the form section (find the form and add client photo field):

Add this HTML in the form, BEFORE the first input field (inside the `bg-mb-surface` div):
```html
<!-- Client Photo -->
<div class="mb-6">
    <label class="block text-sm text-mb-silver mb-3">Client Photo</label>
    <div class="flex items-start gap-6">
        <div class="relative">
            <div id="photoPreviewContainer" class="w-28 h-28 rounded-full overflow-hidden bg-mb-black border-2 border-dashed border-mb-subtle/30 flex items-center justify-center">
                <svg id="photoPlaceholder" class="w-10 h-10 text-mb-subtle/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <img id="photoPreview" class="w-full h-full object-cover hidden" src="" alt="Client photo preview">
            </div>
            <button type="button" onclick="document.getElementById('client_photo_input').click()"
                class="absolute -bottom-2 -right-2 w-8 h-8 bg-mb-accent rounded-full flex items-center justify-center text-white shadow-lg hover:bg-mb-accent/80 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>
            <input type="file" id="client_photo_input" name="client_photo" accept="image/*" class="hidden" onchange="handlePhotoSelect(this)">
            <input type="hidden" id="cropped_photo_data" name="cropped_photo_data">
        </div>
        <div class="flex-1">
            <p class="text-mb-subtle text-xs">Upload a profile photo for this client. Click the camera icon to select and crop an image.</p>
            <p class="text-mb-subtle text-xs mt-1">Supported: JPG, PNG, WEBP. Max 5MB.</p>
        </div>
    </div>
</div>
```

### 3. Handle Photo Upload in PHP (after address validation, around line 43):

Add BEFORE the `if (empty($errors))` block:
```php
// Handle client photo upload with cropping
$clientPhotoFile = null;
if (!empty($_POST['cropped_photo_data'])) {
    $photoData = $_POST['cropped_photo_data'];
    if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photoData, $matches)) {
        $ext = $matches[1];
        $data = base64_decode($matches[2]);
        if ($data !== false && strlen($data) <= 5 * 1024 * 1024) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array(strtolower($ext), $allowedExts)) {
                $uploadDir = __DIR__ . '/../uploads/clients/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                $fileName = 'client_' . uniqid() . '.' . $ext;
                if (file_put_contents($uploadDir . $fileName, $data)) {
                    $clientPhotoFile = 'uploads/clients/' . $fileName;
                }
            }
        }
    }
}
```

### 4. Update INSERT query (find and modify around line 114):

Change:
```php
$stmt = $pdo->prepare('INSERT INTO clients (name,email,phone,alternative_number,address,notes,proof_file) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([$name, $email ?: null, $phone, $alternativeNumber ?: null, $address, $notes, $proofFile]);
```

To:
```php
$photoCol = $supportsAlternativeNumber ? ',photo' : ',photo';
$photoVal = $supportsAlternativeNumber ? ',?' : ',?';
$cols = 'name,email,phone,alternative_number,address,notes,proof_file' . $photoCol;
$vals = [$name, $email ?: null, $phone, $alternativeNumber ?: null, $address, $notes, $proofFile, $clientPhotoFile];
if (!$supportsAlternativeNumber) {
    array_splice($vals, 4, 0, [null]); // insert null for alternative_number
}
$stmt = $pdo->prepare("INSERT INTO clients ($cols) VALUES (?,?,?,?,?,?,?$photoVal)");
$stmt->execute($vals);
```

### 5. Add Cropper.js and JavaScript (before `</body>`):

Add this HTML before footer include:
```html
<!-- Cropper.js -->
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>

<!-- Image Crop Modal -->
<div id="cropModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.8);">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-white font-semibold">Crop Image</h3>
            <button type="button" onclick="closeCropModal()" class="text-mb-subtle hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="relative" style="height: 300px;">
            <img id="cropImage" class="max-h-72 w-full object-contain" src="" alt="Crop preview">
        </div>
        <div class="flex justify-end gap-3 mt-4">
            <button type="button" onclick="closeCropModal()" class="px-4 py-2 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white text-sm">Cancel</button>
            <button type="button" onclick="cropImage()" class="px-6 py-2 rounded-full bg-mb-accent text-white text-sm hover:bg-mb-accent/80">Crop & Save</button>
        </div>
    </div>
</div>

<script>
let cropper = null;
let cropFieldName = '';

function handlePhotoSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be under 5MB.');
            return;
        }
        cropFieldName = 'client_photo';
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('cropImage').src = e.target.result;
            openCropModal('client_photo');
        };
        reader.readAsDataURL(file);
    }
}

function openCropModal(field) {
    document.getElementById('cropModal').classList.remove('hidden');
    if (cropper) { cropper.destroy(); cropper = null; }
    setTimeout(() => {
        cropper = new Cropper(document.getElementById('cropImage'), {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.9,
            cropBoxMovable: true,
            cropBoxResizable: true,
            guides: true,
            center: true,
            highlight: false,
            background: false,
        });
    }, 100);
}

function closeCropModal() {
    document.getElementById('cropModal').classList.add('hidden');
    if (cropper) { cropper.destroy(); cropper = null; }
}

function cropImage() {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas({
        width: 400,
        height: 400,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });
    if (canvas) {
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        document.getElementById('cropped_photo_data').value = dataUrl;
        document.getElementById('photoPreview').src = dataUrl;
        document.getElementById('photoPreview').classList.remove('hidden');
        document.getElementById('photoPlaceholder').classList.add('hidden');
    }
    closeCropModal();
}
</script>
```

---

## IMPLEMENTATION: `clients/edit.php`

### 1. Add Cropper.js includes (same as create.php, around line where header is included)

### 2. Load existing photo (find where other client data is loaded, around line 50):

After fetching client data, add:
```php
$existingPhoto = $c['photo'] ?? null;
```

### 3. Add photo upload HTML (same as create.php, but modify preview to show existing photo):

```html
<!-- Client Photo -->
<div class="mb-6">
    <label class="block text-sm text-mb-silver mb-3">Client Photo</label>
    <div class="flex items-start gap-6">
        <div class="relative">
            <div id="photoPreviewContainer" class="w-28 h-28 rounded-full overflow-hidden bg-mb-black border-2 <?= $existingPhoto ? 'border-mb-accent' : 'border-dashed border-mb-subtle/30' ?> flex items-center justify-center">
                <?php if ($existingPhoto): ?>
                    <img id="photoPreview" class="w-full h-full object-cover" src="../<?= e($existingPhoto) ?>" alt="Client photo">
                <?php else: ?>
                    <svg id="photoPlaceholder" class="w-10 h-10 text-mb-subtle/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <img id="photoPreview" class="w-full h-full object-cover hidden" src="" alt="Client photo preview">
                <?php endif; ?>
            </div>
            <button type="button" onclick="document.getElementById('client_photo_input').click()"
                class="absolute -bottom-2 -right-2 w-8 h-8 bg-mb-accent rounded-full flex items-center justify-center text-white shadow-lg hover:bg-mb-accent/80 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>
            <input type="file" id="client_photo_input" name="client_photo" accept="image/*" class="hidden" onchange="handlePhotoSelect(this)">
            <input type="hidden" id="cropped_photo_data" name="cropped_photo_data">
        </div>
        <div class="flex-1">
            <p class="text-mb-subtle text-xs">Upload a profile photo for this client.</p>
            <p class="text-mb-subtle text-xs mt-1">Supported: JPG, PNG, WEBP. Max 5MB.</p>
            <?php if ($existingPhoto): ?>
                <p class="text-mb-subtle text-xs mt-2">Leave empty to keep current photo.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
```

### 4. Handle photo upload in PHP (same logic as create.php)

### 5. Update client in database (find the UPDATE query, around line 130):

Add photo update:
```php
// Handle photo update
if (!empty($_POST['cropped_photo_data'])) {
    $photoData = $_POST['cropped_photo_data'];
    if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photoData, $matches)) {
        $ext = $matches[1];
        $data = base64_decode($matches[2]);
        if ($data !== false && strlen($data) <= 5 * 1024 * 1024) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array(strtolower($ext), $allowedExts)) {
                $uploadDir = __DIR__ . '/../uploads/clients/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                // Delete old photo if exists
                if ($existingPhoto && file_exists(__DIR__ . '/../' . $existingPhoto)) {
                    @unlink(__DIR__ . '/../' . $existingPhoto);
                }
                $fileName = 'client_' . uniqid() . '.' . $ext;
                if (file_put_contents($uploadDir . $fileName, $data)) {
                    $newPhotoPath = 'uploads/clients/' . $fileName;
                    $pdo->prepare('UPDATE clients SET photo = ? WHERE id = ?')->execute([$newPhotoPath, $id]);
                }
            }
        }
    }
}
```

### 6. Add crop modal and JavaScript (same as create.php)

---

## IMPLEMENTATION: `clients/show.php`

### 1. Load existing photo (find where client data is loaded, around line 44):

Add after fetching client:
```php
$clientPhoto = $c['photo'] ?? null;
```

### 2. Add photo display in hero section (find the client name display, around line 115):

Replace the simple text display with:
```php
<!-- Client Photo & Info -->
<div class="flex items-center gap-5">
    <?php if ($clientPhoto): ?>
        <img src="../<?= e($clientPhoto) ?>" alt="<?= e($c['name']) ?>" 
            class="w-20 h-20 rounded-full object-cover border-2 border-mb-accent/50">
    <?php else: ?>
        <div class="w-20 h-20 rounded-full bg-mb-black border-2 border-mb-subtle/30 flex items-center justify-center">
            <svg class="w-10 h-10 text-mb-subtle/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </div>
    <?php endif; ?>
    <div>
        <h2 class="text-white text-2xl font-light">
            <?= e($c['name']) ?>
        </h2>
        <p class="text-mb-silver text-sm mt-1">
            <?= e($c['phone']) ?>
            <?= $c['email'] ? ' &bull; ' . e($c['email']) : '' ?>
        </p>
    </div>
</div>
```

Also remove the old separate name/phone display if it exists.

---

## DESIGN SYSTEM

### CSS Classes to Use:
- Container: `bg-mb-surface border border-mb-subtle/20 rounded-xl`
- Photo circle: `w-28 h-28 rounded-full` (create/edit) or `w-20 h-20 rounded-full` (show)
- Placeholder icon: Use SVG of person silhouette
- Photo border: `border-2 border-mb-accent/50`
- Edit button: Absolute positioned, `w-8 h-8 bg-mb-accent rounded-full`

### Cropper.js Customization:
- Aspect ratio: 1:1 (square) for client photo
- View mode: 1 (restrict to container)
- Auto crop area: 0.9 (90% of crop box)
- Output: 400x400 pixels, JPEG, 90% quality

---

## CRITICAL RULES

1. **DO NOT change any existing styling or functionality**
2. **DO NOT modify other files** - only edit/create the files listed
3. **Use Cropper.js from CDN**: `https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/`
4. **Always validate file types and sizes**
5. **Delete old photo when updating with new one**
6. **Store photos in** `uploads/clients/` directory
7. **Use `db()` for database, `e()` for escaping**
8. **Log actions with `app_log()`**

---

## QUICK REFERENCE

| File | Changes |
|------|---------|
| `clients/create.php` | Add photo upload form, crop modal, JS, PHP handling |
| `clients/edit.php` | Add photo upload form, crop modal, JS, PHP handling, delete old photo |
| `clients/show.php` | Display photo in profile hero section |
| `migrations/releases/2026-03-22_client_photo.sql` | Create - add `photo` column |

---

## TESTING CHECKLIST

- [ ] Can upload and crop client photo on create
- [ ] Photo preview shows after cropping
- [ ] Photo saves correctly to database
- [ ] Photo displays on client details page
- [ ] Can change photo on edit
- [ ] Old photo is deleted when new one uploaded
- [ ] No photo shows placeholder
- [ ] Image cropper works correctly (drag, resize, zoom)
- [ ] File size validation works (5MB limit)
- [ ] File type validation works
- [ ] Light mode displays correctly

---

## HELPFUL NOTES

### Base64 Image Handling in PHP:
```php
// Decode base64 data URI
if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $data, $matches)) {
    $ext = $matches[1]; // jpg, png, webp
    $data = base64_decode($matches[2]);
    // Save with: file_put_contents($path, $data);
}
```

### Cropper.js Options Reference:
```javascript
new Cropper(imageElement, {
    aspectRatio: 1, // Square for profile photos
    viewMode: 1,    // Restrict crop box to container
    dragMode: 'move',
    autoCropArea: 0.9,
    guides: true,
    center: true,
    background: false,
})
```

### Get Cropped Canvas:
```javascript
const canvas = cropper.getCroppedCanvas({
    width: 400,
    height: 400,
    imageSmoothingEnabled: true,
    imageSmoothingQuality: 'high',
});
const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
```
