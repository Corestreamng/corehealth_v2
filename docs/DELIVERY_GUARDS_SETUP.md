# Service Delivery Guards Implementation

## Overview
Comprehensive payment and HMO validation guards implemented across all service delivery points to ensure services cannot be delivered unless payment requirements are met and HMO validations are complete.

## Core Components

### 1. Unified Helper Function
**File:** `app/Helpers/HmoHelper.php`
**Method:** `canDeliverService(ProductOrServiceRequest $serviceRequest)`

**Validation Logic:**
- ✅ Checks if `payable_amount > 0` requires `payment_id` to be set
- ✅ Checks if `claims_amount > 0` with `validation_status='pending'` blocks delivery
- ✅ Checks if `validation_status='rejected'` blocks delivery
- ✅ Returns array with `can_deliver` flag, `reason`, and actionable `hint`

## Backend Guards (Safety Net)

### Service Result Entry
1. **Lab Results** - `app/Http/Controllers/LabWorkbenchController.php::saveResult()`
   - Returns 403 JSON with reason + hint if delivery blocked
   
2. **Imaging Results** - `app/Http/Controllers/ImagingServiceRequestController.php::saveResult()`
   - Redirects with error message if delivery blocked

### Product Dispense
3. **Pharmacy** - `app/Http/Controllers/ProductRequestController.php::dispense()`
   - Full payment + validation check before dispensing

### Universal Service Delivery (Payment)
4. **All Services** - `app/Http/Controllers/Account/paymentController.php::payment()`
   - Guards ALL services at payment when `payment_id` is set
   - Covers: Consultation, Nursing, Misc Bills, Bed Bills, and all other services
   - **CRITICAL:** Payment is when services are marked as "delivered" in the system

## Frontend Guards (Better UX)

### Lab Workbench
**Files:**
- Backend: `app/Http/Controllers/LabWorkbenchController.php::getLabQueue()`
- Frontend: `resources/views/admin/lab/workbench.blade.php::createRequestCard()`
- Shows warning badge, disables "Enter Result" button with tooltip

### Patient History Tabs
**File:** `app/Http/Controllers/EncounterController.php`

1. **Investigation Tab** (line 398-410)
   - Method: `investigationHistoryList()`
   - Shows warning badge, disables edit button

2. **Imaging Tab** (line 593-605)
   - Method: `imagingHistoryList()`
   - Shows warning badge, disables edit button

3. **Prescription Tab** (line 796-808)
   - Method: `prescHistoryList()`
   - Shows warning badge, disables dispense button

## Bed Billing Features

### 1. HMO Coverage Display
**File:** `app/Http/Controllers/AdmissionRequestController.php`
**Method:** `getBedCoverage()`
**Route:** `GET /bed-coverage`

**Modal:** `resources/views/admin/patients/partials/modals.blade.php::assignBedModal`

**Features:**
- Fetches HMO tariff when bed is selected
- Displays breakdown:
  - Bed price per day
  - Patient payable amount
  - HMO claims amount
  - Coverage mode (express/primary/secondary)
  - Validation requirements
- Updates via AJAX when bed selection changes

### 2. Daily Auto-Billing
**File:** `app/Providers/AppServiceProvider.php`
**Method:** `processDailyBedBills()`
**Trigger:** Runs automatically during application boot

**Features:**
- Processes all active admissions with assigned beds
- Creates one `ProductOrServiceRequest` per occupied bed per day
- Applies HMO tariff automatically
- Uses cache to ensure it only runs once per day (cache key expires at midnight)
- Logs all operations to Laravel log
- **No cron jobs required** - perfect for shared hosting
- Runs on first request after midnight each day

**How it works:**
- On application boot, checks cache key `bed_billing_processed_YYYY-MM-DD`
- If not processed today, processes all occupied beds
- Creates daily bills for each bed
- Sets cache key to prevent duplicate processing
- Cache expires at midnight, allowing next day's processing

### 3. Discharge Validation
**File:** `app/Http/Controllers/AdmissionRequestController.php`
**Methods:**
- `dischargePatient()`
- `dischargePatientApi()`

**Validation Checks:**
1. ✅ All bed bills must be paid (`payment_id IS NOT NULL`)
2. ✅ No pending HMO validations (`validation_status != 'pending'`)
3. ✅ No rejected HMO claims (`validation_status != 'rejected'`)
4. ✅ Shows clear error messages with bill counts if checks fail

## Setup Instructions

### No Setup Required! 🎉

The daily bed billing runs automatically when your application is accessed. The first user to access the application each day (after midnight) will trigger the bed billing process. It uses cache to ensure it only runs once per day.

**Optional:** You can manually trigger bed billing if needed:
```bash
php artisan beds:process-daily-bills
```

### View Logs
```bash
tail -f storage/logs/laravel.log | grep "Bed billing"
```

## Error Messages

### Payment Required
**Message:** "Payment Required"
**Hint:** "Please process payment for this service before proceeding."

### HMO Validation Pending
**Message:** "HMO Validation Pending"
**Hint:** "This service requires HMO approval. Please submit for validation in the HMO Workbench."

### HMO Validation Rejected
**Message:** "HMO Validation Rejected"
**Hint:** "HMO has rejected this claim. Patient must pay or request re-validation."

### Cannot Discharge
**Message:** "Cannot discharge patient: X unpaid bed bill(s) found. Please process all payments before discharge."
**or**
**Message:** "Cannot discharge patient: X bed bill(s) require HMO validation. Please validate all claims before discharge."

## Service Coverage

| Service Type | Backend Guard | Frontend Guard | Payment Guard |
|-------------|---------------|----------------|---------------|
| Lab Results | ✅ | ✅ | ✅ |
| Imaging Results | ✅ | ✅ | ✅ |
| Products/Pharmacy | ✅ | ✅ | ✅ |
| Consultation | N/A | N/A | ✅ |
| Nursing | N/A | N/A | ✅ |
| Misc Bills | N/A | N/A | ✅ |
| Bed Bills | ✅ Daily | ✅ Display | ✅ |

## Testing Scenarios

### Test 1: Unpaid Service Attempt
1. Create HMO patient with bed assignment
2. Wait for daily bill to drop (or run command manually)
3. Try to enter lab result - should see warning
4. Try to process payment - should be blocked
5. Pay the service
6. Should now allow result entry and payment

### Test 2: Pending HMO Validation
1. Create HMO patient with primary/secondary coverage
2. Request service (generates claims_amount > 0)
3. Try to deliver service - should be blocked
4. Validate in HMO Workbench
5. Should now allow delivery

### Test 3: Discharge with Unpaid Bills
1. Admit patient to bed
2. Wait for daily bills to accumulate
3. Try to discharge - should show error with bill count
4. Pay all bills
5. Discharge should succeed

### Test 4: Bed HMO Coverage Display
1. Create HMO admission request
2. Click "Assign Bed"
3. Select different beds
4. Should see HMO coverage breakdown update for each bed
5. Shows patient vs HMO responsibility

## Notes

- **Shared Hosting:** Uses Laravel scheduler, not queues
- **Performance:** All delivery checks are fast (single query)
- **Backward Compatible:** Existing cash patients unaffected
- **Extensible:** Add more delivery points by calling `HmoHelper::canDeliverService()`

## Troubleshooting

### Daily Bills Not Creating
1. Check Laravel logs: `storage/logs/laravel.log`
2. Look for "Bed billing" entries
3. Clear cache: `php artisan cache:clear`
4. Access the application to trigger the process
5. Run manually if needed: `php artisan beds:process-daily-bills`

### Bills Creating Multiple Times
- Should not happen - cache prevents duplicate processing
- If it does, check cache driver in `.env` (should be `file` or `database`)

### First Bill After Midnight
- The first user to access the app after midnight triggers billing
- This is normal and expected behavior
- Process is very fast (< 1 second for typical bed counts)

### Discharge Still Allowed Despite Unpaid Bills
Ensure you're using the updated discharge methods (after implementation date)

## Files Modified

### Backend
- `app/Helpers/HmoHelper.php` - Added `canDeliverService()`
- `app/Http/Controllers/LabWorkbenchController.php` - Result entry + queue data
- `app/Http/Controllers/ImagingServiceRequestController.php` - Result entry
- `app/Http/Controllers/ProductRequestController.php` - Dispense guard
- `app/Http/Controllers/Account/paymentController.php` - Universal payment guard
- `app/Http/Controllers/AdmissionRequestController.php` - Bed coverage, discharge validation
- `app/Http/Controllers/EncounterController.php` - History tabs delivery checks
- `app/Console/Commands/ProcessDailyBedBills.php` - NEW
- `app/Console/Kernel.php` - Scheduler configuration
- `routes/web.php` - Added bed coverage route

### Frontend
- `resources/views/admin/lab/workbench.blade.php` - Lab queue cards
- `resources/views/admin/patients/partials/modals.blade.php` - Bed assignment modal
- `resources/views/admin/patients/show1.blade.php` - Modal triggers

## Support

For issues or questions, refer to:
- HMO Implementation Docs: `ENV_TO_APPSETTINGS_MIGRATION.md`
- Lab Template Docs: `LAB_TEMPLATE_STRUCTURE.md`
- Result Edit Docs: `WYSIWYG_AND_RESULT_EDIT_IMPLEMENTATION.md`
