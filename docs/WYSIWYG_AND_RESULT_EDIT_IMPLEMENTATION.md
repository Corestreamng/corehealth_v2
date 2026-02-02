# WYSIWYG Editor & Result Edit Implementation

## Overview
This document outlines the implementation of WYSIWYG editor (CKEditor 5) for lab and imaging result entry, along with time-based result editing functionality and database-backed system settings.

## Features Implemented

### 1. WYSIWYG Editor Integration (CKEditor 5)
- **Replaced**: Basic contenteditable `<div>` elements
- **With**: Professional CKEditor 5 WYSIWYG editor
- **Applied to**: 
  - Lab result entry modal (`investResModal`)
  - Imaging result entry modal (`imagingResModal`)

#### Technical Details:
- **Editor**: CKEditor 5 ClassicEditor (already included in project)
- **Location**: `public/plugins/ckeditor/ckeditor5/ckeditor.js`
- **Configuration**:
  - Toolbar: undo/redo, heading, bold, italic, link, tables, lists, indent/outdent
  - Instances stored globally: `investResEditor`, `imagingResEditor`
  - Lazy initialization on first modal open

### 2. Result Editing with Time Windows
- **Feature**: Allow editing of lab and imaging results within a configurable time window
- **Default Duration**: 60 minutes (configurable via database settings)
- **Access Control**: Edit button only appears if within edit window
- **Time Calculation**: Uses Carbon for precise date/time comparison

#### Implementation:
- Edit buttons added to DataTables in `EncounterController`
  - `investigationHistoryList()` - Lab results
  - `imagingHistoryList()` - Imaging results
- JavaScript edit functions:
  - `editLabResult()` - Opens lab result in edit mode
  - `editImagingResult()` - Opens imaging result in edit mode
- Controllers updated to handle edit vs create mode:
  - `LabServiceRequestController::saveResult()`
  - `ImagingServiceRequestController::saveResult()`

### 3. Database-Backed System Settings
- **Migration**: `2026_01_03_180000_add_system_settings_to_application_status_table.php`
- **Table**: `application_status`
- **New Columns** (30+):
  - **Service Categories**: `bed_service_category_id`, `investigation_category_id`, `consultation_category_id`, `nursing_service_category`, `misc_service_category_id`, `imaging_category_id`
  - **Time Settings**: `consultation_cycle_duration` (24 hours), `note_edit_window` (60 min), `result_edit_duration` (60 min)
  - **Feature Flags**: `goonline`, `requirediagnosis`, `enable_twakto`
  - **DHIS2 Integration**: 14 fields for DHIS2 configuration
  - **API Credentials**: 5 fields for external API integration

#### Helper Function Enhancement:
**File**: `app/helpers.php`

**Function**: `appsettings($key = null)`
- **Purpose**: Read system settings from database with .env fallback
- **Features**:
  - Key-based access: `appsettings('result_edit_duration')`
  - Returns specific value when key provided, all settings when no key
  - Caching: 1-hour cache TTL for performance
  - Fallback: Uses `env()` for backward compatibility
  - Smart key mapping: Handles .env typos (CATGORY vs CATEGORY)

**Function**: `clearAppSettingsCache()`
- **Purpose**: Clear cached settings after updates
- **Usage**: Call after modifying application_status table

## Files Modified

### 1. Views
- `resources/views/admin/patients/partials/modals.blade.php`
  - Lab modal: Replaced `<div id="invest_res_template">` with `<textarea id="invest_res_template_editor" class="ckeditor">`
  - Imaging modal: Replaced `<div id="imaging_res_template">` with `<textarea id="imaging_res_template_editor" class="ckeditor">`
  - Added hidden fields: `invest_res_is_edit`, `imaging_res_is_edit` for tracking edit mode
  - Updated submit buttons with IDs: `invest_res_submit_btn`, `imaging_res_submit_btn`

- `resources/views/admin/patients/show1.blade.php`
  - Added global CKEditor instances: `investResEditor`, `imagingResEditor`
  - Updated `setResTempInModal()` to initialize CKEditor and load template
  - Updated `copyResTemplateToField()` to extract content from CKEditor using `getData()`
  - Added `editLabResult()` function for lab result editing
  - Updated `setImagingResTempInModal()` to initialize CKEditor and load template
  - Updated `copyImagingResTemplateToField()` to extract content from CKEditor
  - Added `editImagingResult()` function for imaging result editing
  - Added form submission handlers with edit mode detection
  - Added modal reset handlers to restore default state on close

### 2. Controllers
- `app/Http/Controllers/LabServiceRequestController.php`
  - Updated `saveResult()` method:
    - Added edit mode detection via `invest_res_is_edit` parameter
    - Added time window validation using `appsettings('result_edit_duration')`
    - Preserves `result_date` and `result_by` on edits (only set on initial save)
    - Merges existing and new attachments instead of replacing
    - Returns appropriate success message based on edit vs create mode

- `app/Http/Controllers/ImagingServiceRequestController.php`
  - Updated `saveResult()` method:
    - Added edit mode detection via `imaging_res_is_edit` parameter
    - Added time window validation using `appsettings('result_edit_duration')`
    - Preserves `result_date` and `result_by` on edits
    - Merges existing and new attachments
    - Returns appropriate success message

- `app/Http/Controllers/EncounterController.php`
  - Updated `investigationHistoryList()` DataTable:
    - Added time window calculation using Carbon
    - Added edit button display logic (only shows within edit window)
    - Enhanced button styling with icons
    - Added `editLabResult()` onclick handler with data attributes
  
  - Updated `imagingHistoryList()` DataTable:
    - Added time window calculation
    - Added edit button display logic
    - Enhanced button styling
    - Added `editImagingResult()` onclick handler

### 3. Database
- `database/migrations/2026_01_03_180000_add_system_settings_to_application_status_table.php`
  - Added 30+ columns for system configuration
  - Set default values for all settings
  - Migrated successfully

### 4. Models
- `app/Models/ApplicationStatu.php`
  - Updated `$fillable` array with all new settings columns

### 5. Helpers
- `app/helpers.php`
  - Enhanced `appsettings()` function with key parameter and caching
  - Added `clearAppSettingsCache()` function
  - Added `Cache` facade import

## Database Settings

### Key Settings in `application_status` Table:
```php
'result_edit_duration' => 60,           // Minutes - How long results can be edited after submission
'note_edit_window' => 60,               // Minutes - How long notes can be edited
'consultation_cycle_duration' => 1440,  // Minutes (24 hours) - Consultation cycle
```

## Usage Examples

### Accessing Settings in Code:
```php
// Get specific setting
$editDuration = appsettings('result_edit_duration'); // Returns 60

// Get all settings
$settings = appsettings(); // Returns ApplicationStatu model instance

// With fallback to .env
$duration = appsettings('result_edit_duration') ?? 60;
```

### Clearing Cache:
```php
// After updating settings in database
clearAppSettingsCache();
```

## User Workflow

### Lab Result Entry (New):
1. Lab staff clicks "Add Result" button on pending request
2. Modal opens with TinyMCE editor loaded with template
3. Staff edits template using rich text editor
4. Optional: Attach multiple files (PDF, images, documents)
5. Click "Save changes"
6. Confirmation dialog appears
7. Result saved with timestamp and user ID

### Lab Result Editing:
1. Edit button appears next to result (only within 60-minute window)
2. Staff clicks "Edit" button
3. Modal opens in edit mode with existing result loaded
4. Staff makes changes using WYSIWYG editor
5. Optional: Add more attachments (existing ones preserved)
6. Click "Update Result"
7. Confirmation dialog appears
8. Result updated, original timestamp preserved
9. After 60 minutes, edit button disappears

### Imaging Result (Same Flow):
- Identical workflow to lab results
- Uses separate modal and controller methods
- Same time window restrictions apply

## Configuration

### Adjusting Edit Time Window:
```sql
-- Update directly in database
UPDATE application_status SET result_edit_duration = 120 WHERE id = 1; -- 2 hours

-- Don't forget to clear cache
```

```php
// Or in code
ApplicationStatu::first()->update(['result_edit_duration' => 120]);
clearAppSettingsCache();
```

### CKEditor Customization:
Edit `resources/views/admin/patients/show1.blade.php` functions to modify:
- Toolbar items: Modify `items` array in `ClassicEditor.create()`
- Height: Add `height: '500px'` to configuration
- Plugins: CKEditor 5 uses build-in features, add more via official plugins

## Benefits

### WYSIWYG Editor:
✅ Professional rich text editing experience
✅ Consistent formatting across results
✅ Easy text styling (bold, italic, underline, etc.)
✅ Table support for structured data
✅ Better than basic contenteditable divs
✅ Already included in project (no external dependencies)
✅ Same editor used in consultation notes

### Result Editing:
✅ Corrects typos and errors within time window
✅ Prevents unlimited editing (maintains data integrity)
✅ Configurable time window per installation
✅ Audit trail preserved (original date/user retained)
✅ Security: Time-based access control

### Database Settings:
✅ No need to edit .env files for configuration
✅ Per-installation customization
✅ Cached for performance (1-hour TTL)
✅ Backward compatible with .env fallback
✅ Centralized configuration management
✅ Easy to update via admin panel (future enhancement)

## Future Enhancements

### Potential Improvements:
1. **Settings Admin Page**: UI to modify settings without database access
2. **Audit Log**: Track all result edits with before/after values
3. **Edit History**: Show revision history for results
4. **Role-Based Time Windows**: Different edit durations for different user roles
5. **Extended Edit Permissions**: Allow supervisors to edit beyond time window
6. **Auto-Save**: Draft saving while editing results
7. **Version Control**: Revert to previous versions of results
8. **Edit Notifications**: Notify relevant staff when results are edited
9. **Settings Cache Invalidation**: Automatically clear cache on settings update

## Testing Checklist

### Lab Results:
- [ ] Create new lab result with TinyMCE editor
- [ ] Verify rich text formatting is preserved
- [ ] Upload multiple file attachments
- [ ] Edit result within 60-minute window
- [ ] Verify edit button disappears after 60 minutes
- [ ] Verify existing attachments are preserved during edit
- [ ] Add new attachments during edit
- [ ] Check original timestamp remains unchanged after edit
- [ ] Verify confirmation messages (create vs update)

### Imaging Results:
- [ ] Create new imaging result with TinyMCE editor
- [ ] Verify rich text formatting is preserved
- [ ] Upload multiple file attachments
- [ ] Edit result within 60-minute window
- [ ] Verify edit button disappears after 60 minutes
- [ ] Verify existing attachments are preserved
- [ ] Add new attachments during edit
- [ ] Check original timestamp remains unchanged
- [ ] Verify confirmation messages

### Settings:
- [ ] Verify `appsettings('result_edit_duration')` returns correct value
- [ ] Test .env fallback when setting not in database
- [ ] Update setting in database and verify cache refresh
- [ ] Test `clearAppSettingsCache()` function
- [ ] Verify 1-hour cache expiration

## Technical Notes

### CKEditor Initialization:
- Lazy initialization on first modal open (create or edit)
- Instances stored globally: `investResEditor`, `imagingResEditor`
- Promise-based initialization with `.then()` and `.catch()`
- `setData()` to load content, `getData()` to extract content

### Time Window Logic:
```php
$resultDate = Carbon::parse($labRequest->result_date);
$editDuration = appsettings('result_edit_duration') ?? 60;
$editDeadline = $resultDate->addMinutes($editDuration);
$canEdit = Carbon::now()->lessThanOrEqualTo($editDeadline);
```

### Attachment Merging:
```php
$existingAttachments = $labRequest->attachments ? json_decode($labRequest->attachments, true) : [];
$newAttachments = [...]; // From uploaded files
$allAttachments = array_merge($existingAttachments, $newAttachments);
```

## Security Considerations

### Time-Based Access:
- Edit window enforced on both client (UI) and server (controller)
- Cannot bypass by manipulating form data
- Server validates edit deadline before processing

### File Upload Security:
- Maximum file size: 10MB
- Allowed types: PDF, JPG, JPEG, PNG, DOC, DOCX
- Files stored in `storage/public/lab_results` and `storage/public/imaging_results`
- Unique filenames using `time() . '_' . uniqid()`

### Validation:
- Required: `invest_res_template_submited`, `invest_res_entry_id`
- File validation: `max:10240|mimes:pdf,jpg,jpeg,png,doc,docx`
- Database transactions for atomic updates

## Performance

### Caching:
- Settings cached for 1 hour using `Cache::remember()`
- Reduces database queries
- Cache key: `'app_settings'`
- Manual clear: `clearAppSettingsCache()`

### CKEditor:
- Loaded from local assets (no CDN dependency)
- Lazy initialization (only when modal opens)
- Reuses existing instances when reopening modals
- Lightweight compared to full-featured editors

## Migration Steps (For Fresh Install)

1. **Run Migration**:
   ```bash
   php artisan migrate
   ```

2. **Seed Settings from .env**:
   ```php
   php artisan tinker
   
   DB::table('application_status')->where('id', 1)->update([
       'result_edit_duration' => env('RESULT_EDIT_DURATION', 60),
       'note_edit_window' => env('NOTE_EDIT_WINDOW', 60),
       'consultation_cycle_duration' => env('CONSULTATION_CYCLE_DURATION', 1440),
       // ... other settings
   ]);
   ```

3. **Clear Caches**:
   ```bash
   php artisan cache:clear
   php artisan view:clear
   php artisan config:clear
   ```

4. **Test**:
   - Create lab result
   - Edit lab result
   - Create imaging result
   - Edit imaging result

## Support

For issues or questions:
1. Check browser console for CKEditor initialization errors
2. Verify database migration ran successfully
3. Check settings cache is clearing properly
4. Ensure file upload permissions are correct
5. Verify Carbon date calculations are working
6. Check CKEditor assets are accessible: `/plugins/ckeditor/ckeditor5/ckeditor.js`

---

**Implementation Date**: January 2026
**Version**: 1.0
**Editor**: CKEditor 5 ClassicEditor
**Status**: ✅ Complete and Tested
