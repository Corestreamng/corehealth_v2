# ENV to appsettings() Migration

## Overview
Migrated configuration from .env file-based settings to database-backed settings using the `appsettings()` helper function. This allows administrators to configure settings via the UI without editing files.

## Migration Date
Completed: [Current Date]

## Settings Helper Function
- **Function**: `appsettings($key, $default = null)`
- **Location**: `app/helpers.php`
- **Cache**: 1 hour (3600 seconds)
- **Fallback**: Falls back to env() for backward compatibility
- **Source Table**: `application_status` (single row configuration)

## Files Modified

### Controllers

#### 1. BedController.php
**Lines Modified**: 73, 151

**Changes**:
- `env('BED_SERVICE_CATGORY_ID', 1)` → `appsettings('bed_service_category_id', 1)`

**Context**: Store and update methods for bed service creation

---

#### 2. ServiceController.php
**Lines Modified**: 88

**Changes**:
- `env('INVESTGATION_CATEGORY_ID')` → `appsettings('investigation_category_id')`

**Context**: Investigation service category filtering

---

#### 3. PatientController.php
**Lines Modified**: 248, 438, 550

**Changes**:
- `env('CONSULTATION_CATEGORY_ID')` → `appsettings('consultation_category_id')`
- `env('GOONLINE')` (2 occurrences) → `appsettings('goonline', 0)`

**Context**: 
- Line 248: Consultation service category
- Lines 438, 550: DHIS2 and SuperAdmin API sync flags

---

#### 4. PatientAccountController.php
**Lines Modified**: 143

**Changes**:
- `env('MISC_SERVICE_CATEGORY_ID')` → `appsettings('misc_service_category_id')`

**Context**: Miscellaneous service category filtering

---

#### 5. API/DataEndpoint.php
**Lines Modified**: 205, 271, 272, 273, 275

**Changes**:
- `env('INVESTGATION_CATEGORY_ID')` → `appsettings('investigation_category_id')`
- `env('BED_SERVICE_CATGORY_ID')` → `appsettings('bed_service_category_id')`
- `env('CONSULTATION_CATEGORY_ID')` → `appsettings('consultation_category_id')`
- `env('NUSRING_SERVICE_CATEGORY')` → `appsettings('nursing_service_category')`
- `env('MISC_SERVICE_CATEGORY_ID')` → `appsettings('misc_service_category_id')`

**Context**: Statistics and income reporting API endpoints

---

#### 6. VitalSignController.php
**Lines Modified**: 120

**Changes**:
- `env('CONSULTATION_CYCLE_DURATION')` → `appsettings('consultation_cycle_duration', 24)`

**Context**: Time threshold for active patient vitals queue

---

#### 7. ProcedureController.php
**Lines Modified**: 97

**Changes**:
- `env('CONSULTATION_CYCLE_DURATION')` → `appsettings('consultation_cycle_duration', 24)`

**Context**: Time threshold for active procedure list

---

#### 8. EncounterController.php
**Lines Modified**: 111, 189, 954, 1057

**Changes**:
- `env('CONSULTATION_CYCLE_DURATION')` (2 occurrences) → `appsettings('consultation_cycle_duration', 24)`
- `env('GOONLINE')` (2 occurrences) → `appsettings('goonline', 0)`

**Context**:
- Lines 111, 189: Old encounter cleanup and queue filtering
- Lines 954, 1057: DHIS2 and SuperAdmin API sync flags

---

#### 9. StaffController.php
**Lines Modified**: 499

**Changes**:
- `env('GOONLINE')` → `appsettings('goonline', 0)`

**Context**: SuperAdmin API sync for staff creation

---

### Views

#### 10. resources/views/admin/layouts/app.blade.php
**Lines Modified**: 1946

**Changes**:
- `@if (env('ENABLE_TWAKTO') == 1)` → `@if (appsettings('enable_twakto', 0) == 1)`

**Context**: Tawk.to chat widget conditional loading

---

#### 11. resources/views/admin/patients/partials/nurse_chart_scripts.blade.php
**Lines Modified**: 15

**Changes**:
- `var NOTE_EDIT_WINDOW = {{ env('NOTE_EDIT_WINDOW', 30) }};` → `var NOTE_EDIT_WINDOW = {{ appsettings('note_edit_window', 30) }};`

**Context**: JavaScript variable for medication administration edit window

---

#### 12. resources/views/admin/patients/partials/nurse_chart_scripts_enhanced.blade.php
**Lines Modified**: 104

**Changes**:
- `var NOTE_EDIT_WINDOW = {{ env('NOTE_EDIT_WINDOW', 30) }};` → `var NOTE_EDIT_WINDOW = {{ appsettings('note_edit_window', 30) }};`

**Context**: JavaScript variable for medication administration edit window (enhanced version)

---

## Settings Mapping

| Old ENV Variable | New Database Column | Default Value | Description |
|-----------------|---------------------|---------------|-------------|
| `BED_SERVICE_CATGORY_ID` | `bed_service_category_id` | 1 | Bed service category ID |
| `INVESTGATION_CATEGORY_ID` | `investigation_category_id` | 2 | Investigation/Lab service category ID |
| `CONSULTATION_CATEGORY_ID` | `consultation_category_id` | 1 | Consultation service category ID |
| `NUSRING_SERVICE_CATEGORY` | `nursing_service_category` | 4 | Nursing service category ID |
| `MISC_SERVICE_CATEGORY_ID` | `misc_service_category_id` | 5 | Miscellaneous service category ID |
| `IMAGING_CATEGORY_ID` | `imaging_category_id` | 6 | Imaging service category ID |
| `CONSULTATION_CYCLE_DURATION` | `consultation_cycle_duration` | 24 | Hours before consultation expires |
| `NOTE_EDIT_WINDOW` | `note_edit_window` | 30 | Minutes to allow note editing |
| `RESULT_EDIT_DURATION` | `result_edit_duration` | 60 | Minutes to allow result editing |
| `GOONLINE` | `goonline` | 0 | Enable external API sync (0/1) |
| `REQUIREDIAGNOSIS` | `requirediagnosis` | 0 | Require diagnosis entry (0/1) |
| `ENABLE_TWAKTO` | `enable_twakto` | 0 | Enable Tawk.to chat widget (0/1) |

## Files NOT Modified

### Config Files
Config files (`config/app.php`, etc.) still use `env()` as they should. These are read at application bootstrap and cached.

**Example**:
```php
// config/app.php - CORRECT, keep as is
'note_edit_window' => env('NOTE_EDIT_WINDOW', 30),
```

### Documentation
Documentation files (like `WYSIWYG_AND_RESULT_EDIT_IMPLEMENTATION.md`) reference env() for explanation purposes only.

## Testing Checklist

### Basic Functionality
- [ ] Lab result entry with CKEditor
- [ ] Lab result editing within time window
- [ ] Lab attachment deletion and addition
- [ ] Imaging result entry with CKEditor
- [ ] Imaging result editing within time window
- [ ] Imaging attachment deletion and addition

### Service Categories
- [ ] Bed service creation uses correct category
- [ ] Investigation requests use correct category
- [ ] Consultation queue filters correctly
- [ ] Nursing services use correct category
- [ ] Misc services use correct category
- [ ] API statistics show correct service breakdown

### Time Windows
- [ ] Consultation cycle duration filters old encounters
- [ ] Note edit window prevents old note edits
- [ ] Result edit duration prevents old result edits
- [ ] Vital signs queue respects time threshold
- [ ] Procedure list respects time threshold

### Feature Flags
- [ ] GOONLINE flag controls DHIS2 sync
- [ ] GOONLINE flag controls SuperAdmin sync
- [ ] ENABLE_TWAKTO flag shows/hides Tawk.to widget
- [ ] REQUIREDIAGNOSIS flag enforces diagnosis entry (if used)

### Settings Management
- [ ] Settings load from database
- [ ] Settings fall back to .env if DB row missing
- [ ] Settings cache for 1 hour
- [ ] Clear cache refreshes settings: `php artisan cache:clear`

## Deployment Notes

### Pre-Deployment
1. **Backup .env file** - Keep for reference and rollback
2. **Verify application_status table** exists and has data
3. **Test appsettings() helper** is working

### Deployment Steps
1. Deploy code changes to production
2. Verify `application_status` table has correct values
3. Update settings via admin UI (if available) or direct DB update
4. Clear application cache: `php artisan cache:clear`
5. Test critical workflows (see Testing Checklist)

### Post-Deployment
1. Monitor logs for any env() related errors
2. Verify external API sync works (if GOONLINE enabled)
3. Check statistics and reporting accuracy

### Rollback Plan
If issues occur:
1. Keep .env file values unchanged as fallback
2. The appsettings() helper automatically falls back to env()
3. If needed, revert code changes and redeploy previous version

## Database Schema Reference

```sql
-- application_status table (excerpt of relevant columns)
CREATE TABLE `application_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  -- Service Categories
  `bed_service_category_id` int DEFAULT 1,
  `investigation_category_id` int DEFAULT 2,
  `consultation_category_id` int DEFAULT 1,
  `nursing_service_category` int DEFAULT 4,
  `misc_service_category_id` int DEFAULT 5,
  `imaging_category_id` int DEFAULT 6,
  
  -- Time Windows (in minutes or hours)
  `consultation_cycle_duration` int DEFAULT 24,
  `note_edit_window` int DEFAULT 30,
  `result_edit_duration` int DEFAULT 60,
  
  -- Feature Flags
  `goonline` tinyint(1) DEFAULT 0,
  `requirediagnosis` tinyint(1) DEFAULT 0,
  `enable_twakto` tinyint(1) DEFAULT 0,
  
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Benefits

1. **Centralized Configuration**: All settings in one database table
2. **UI Management**: Can build admin interface to manage settings
3. **No File Editing**: No need to SSH and edit .env files
4. **Per-Installation**: Different hospitals can have different settings
5. **Cached Performance**: Settings cached for 1 hour, minimal DB queries
6. **Backward Compatible**: Falls back to .env if DB not configured
7. **Version Control Safe**: Sensitive settings not in version control

## Support

For issues or questions:
1. Check application logs: `storage/logs/laravel.log`
2. Verify `application_status` table has data
3. Clear cache: `php artisan cache:clear`
4. Check .env fallback values are correct
5. Test appsettings() helper: `php artisan tinker` → `appsettings('goonline')`
