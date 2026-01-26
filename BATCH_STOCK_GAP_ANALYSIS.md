# Batch Stock System - Gap Analysis Report

**Generated**: January 2025  
**Scope**: Pharmacy Workbench, Nursing Workbench, Store/Requisition Flow  
**Reference**: Store/Requisition flow (source of truth)

---

## Executive Summary

This document provides a comprehensive gap analysis comparing the batch stock implementation across:
1. **Store/Requisition Flow** - ✅ Fully implemented (Source of Truth)
2. **Pharmacy Workbench** - ⚠️ Mostly Complete (Minor gaps)
3. **Nursing Workbench** - ⚠️ Partially Complete (Critical gaps in medication chart)

---

## 1. Source of Truth: Store/Requisition Flow

### ✅ Implementation Status: COMPLETE

The Store Workbench and Requisition system properly uses `StockService` for all operations:

#### Key Files:
- [StoreWorkbenchController.php](app/Http/Controllers/StoreWorkbenchController.php)
- [StoreRequisitionController.php](app/Http/Controllers/StoreRequisitionController.php)
- [StockService.php](app/Services/StockService.php)

#### Features Working:
| Feature | Status | Method |
|---------|--------|--------|
| Dependency Injection | ✅ | `__construct(StockService $stockService)` |
| Batch Creation | ✅ | `$stockService->createBatch()` |
| Stock Adjustment | ✅ | `$stockService->adjustStock()` |
| Expired Write-off | ✅ | `$stockService->writeOffExpired()` |
| Damaged Write-off | ✅ | `$stockService->writeOffDamaged()` |
| FIFO Transfer | ✅ | `$stockService->transferStock()` (FIFO mode) |
| Specific Batch Transfer | ✅ | `$stockService->transferStock($sourceBatchId)` |
| Multi-batch Fulfillment | ✅ | UI sends batch→qty map, service handles transfer |
| Expiry Alerts | ✅ | `$stockService->getExpiringBatches()` |
| Low Stock Alerts | ✅ | `$stockService->getLowStockProducts()` |

#### Requisition Fulfillment Flow (Gold Standard):
```
1. UI presents available batches with expiry dates
2. User selects which batches to use and quantities
3. Controller receives: items[{item_id}].batches[{batch_id}] = qty
4. StockService.transferStock() creates:
   - StockBatchTransaction (TYPE_TRANSFER_OUT) on source
   - New StockBatch in destination store
   - StockBatchTransaction (TYPE_IN) on destination
5. store_stocks aggregate table synced
```

---

## 2. Pharmacy Workbench Analysis

### Status: ⚠️ MOSTLY COMPLETE

#### Files Reviewed:
- [PharmacyWorkbenchController.php](app/Http/Controllers/PharmacyWorkbenchController.php)
- [pharmacy/workbench.blade.php](resources/views/admin/pharmacy/workbench.blade.php)

### ✅ Working Features:

| Feature | Location | Implementation |
|---------|----------|----------------|
| Batch API Endpoints | Controller lines 2085-2140 | `getProductBatches()`, `getBatchFulfillmentSuggestion()` |
| Dispense with Batch Selection | Controller lines 2140-2345 | `dispenseMedicationWithBatch()` |
| FIFO Auto-dispense | Controller line 2294 | `$stockService->dispenseStock()` |
| Manual Batch Dispense | Controller line 2284 | `$stockService->dispenseFromBatch()` |
| Cart Batch Display | Blade line 12149 | `buildBatchDropdown()` |
| FIFO Mode Toggle | Blade line 5116 | Checkbox to switch FIFO/manual |
| Stock Validation | Controller lines 2213-2245 | Pre-validates all items before dispense |
| Product Adaptation | Controller line 2425+ | `adaptPrescription()` method |

#### UI Flow (Working):
```
1. Add medications to dispense cart
2. Select dispensing store → triggers fetchCartStockLevels()
3. Cart shows batch dropdown for each item (FIFO or manual)
4. Submit → POST /pharmacy-workbench/dispense-with-batch
5. Backend uses StockService for batch deduction
6. ProductRequest updated with dispensed_from_batch_id
```

### ⚠️ Gaps Identified:

| Gap | Severity | Description |
|-----|----------|-------------|
| Quantity Adjustment at Billing | Low | Adaptation only changes product, not qty post-billing |
| Batch info not shown in history | Low | `prescHistoryList()` doesn't show which batch was used |
| No batch tracking for old dispenses | Info | Legacy dispenses don't have `dispensed_from_batch_id` |

### 📋 Recommended Fixes:

1. **Add batch info to dispensed history view** - Display `dispensed_from_batch_id` in history DataTable
2. **Quantity edit at billing stage** - Allow qty change before dispense (optional)

---

## 3. Nursing Workbench Analysis

### Status: ⚠️ PARTIALLY COMPLETE

#### Files Reviewed:
- [NursingWorkbenchController.php](app/Http/Controllers/NursingWorkbenchController.php)
- [nursing/workbench.blade.php](resources/views/admin/nursing/workbench.blade.php)
- [MedicationChartController.php](app/Http/Controllers/MedicationChartController.php) ⚠️
- [nurse_chart_medication_enhanced.blade.php](resources/views/admin/patients/partials/nurse_chart_medication_enhanced.blade.php)

### ✅ Working Features (Nursing Workbench):

| Feature | Location | Implementation |
|---------|----------|----------------|
| Batch API Endpoints | Controller lines 1100-1250 | `getProductBatches()`, `getBatchFulfillmentSuggestion()`, `getProductStockByStore()` |
| Injection Batch Selection | Blade line 11022 | Dropdown `injection_batch_id[]` |
| Injection Admin with Batch | Controller line 703 | `$stockService->dispenseFromBatch()` or FIFO |
| Vaccine Batch Selection | Blade line 12568 | `batch_id` sent in AJAX |
| Vaccine Admin with Batch | Controller line 2304 | `$stockService->dispenseFromBatch()` for immunizations |
| Consumable Batch Selection | Blade line 13031 | `batch_id` parameter in request |
| Consumable Bill with Batch | Controller line 1437 | `$stockService->dispenseFromBatch()` |

#### Nursing Workbench Flow (Working):
```
Injection/Vaccine/Consumable:
1. Select product and store
2. Batch dropdown populated via AJAX
3. User selects batch (or uses FIFO default)
4. Submit → batch_id sent to controller
5. Controller uses StockService to deduct from batch
```

### 🚨 CRITICAL GAP: Medication Chart Administration

The **Medication Chart** module (`MedicationChartController`) does NOT use the batch system!

#### Problem Location:
- [MedicationChartController.php](app/Http/Controllers/MedicationChartController.php) lines 388-500

#### Current (Broken) Implementation:
```php
// MedicationChartController::administer() - LINE 445-459
if ($productId && $storeId) {
    $storeStock = StoreStock::where('store_id', $storeId)
        ->where('product_id', $productId)
        ->first();

    if ($storeStock && $storeStock->current_quantity >= 1) {
        // ❌ OLD METHOD: Directly decrements aggregate table
        $storeStock->decrement('current_quantity', 1);
    } else {
        // ❌ Falls back to ProductStock (also old method)
        $productStock = ProductStock::where('product_id', $productId)->first();
        if ($productStock && $productStock->current_quantity >= 1) {
            $productStock->decrement('current_quantity', 1);
        }
    }
}
```

#### Issues:
1. ❌ Uses `StoreStock::decrement()` instead of `StockService`
2. ❌ No batch tracking - no audit trail of which batch was dispensed
3. ❌ No FIFO enforcement - stock just disappears from aggregate
4. ❌ `store_stocks` aggregate gets out of sync with `stock_batches`
5. ❌ UI has no batch selection dropdown

#### UI Gap:
The `nurse_chart_medication_enhanced.blade.php` has **store selection** but **NO batch selection**:
- Line 737-746: Store dropdown exists
- Line 743-752: Stock display exists  
- ❌ No batch dropdown like other nursing modules

---

## 4. Complete Gap Summary

### By Module:

| Module | Batch API | Batch UI | StockService | Audit Trail | Status |
|--------|-----------|----------|--------------|-------------|--------|
| Store Workbench | ✅ | ✅ | ✅ | ✅ | ✅ COMPLETE |
| Requisitions | ✅ | ✅ | ✅ | ✅ | ✅ COMPLETE |
| Pharmacy Dispense | ✅ | ✅ | ✅ | ✅ | ✅ COMPLETE |
| Pharmacy Adapt | ✅ | ✅ | N/A | ✅ | ✅ COMPLETE |
| Nursing: Injection | ✅ | ✅ | ✅ | ✅ | ✅ COMPLETE |
| Nursing: Vaccine | ✅ | ✅ | ✅ | ✅ | ✅ COMPLETE |
| Nursing: Consumable | ✅ | ✅ | ✅ | ✅ | ✅ COMPLETE |
| **Nursing: Med Chart** | ❌ | ❌ | ❌ | ❌ | 🚨 **CRITICAL** |

### Priority List:

| Priority | Module | Gap | Effort |
|----------|--------|-----|--------|
| **P0** | MedicationChartController | Replace StoreStock with StockService | Medium |
| **P0** | nurse_chart_medication_enhanced.blade.php | Add batch selection dropdown | Medium |
| P1 | nurse_chart_medication.blade.php | Add store + batch selection | Medium |
| P2 | pharmacy/workbench.blade.php | Show batch in dispense history | Low |

---

## 5. Implementation Plan: Medication Chart Fix

### 5.1 Backend Changes (MedicationChartController.php)

```php
// ADD at top:
use App\Services\StockService;
use App\Models\StockBatch;

// MODIFY administer() method:
public function administer(Request $request)
{
    $validator = Validator::make($request->all(), [
        'schedule_id' => 'required|exists:medication_schedules,id',
        'administered_at' => 'required|date',
        'administered_dose' => 'required|string',
        'route' => 'required|string',
        'comment' => 'nullable|string',
        'store_id' => 'required|exists:stores,id',
        'batch_id' => 'nullable|exists:stock_batches,id', // NEW
        'product_id' => 'nullable|exists:products,id'
    ]);
    
    // ... existing validation ...
    
    // REPLACE stock deduction block with:
    if ($productId && $storeId) {
        $stockService = app(StockService::class);
        $batchId = $data['batch_id'] ?? null;
        $qty = 1;
        
        if ($batchId) {
            // Manual batch selection
            $stockService->dispenseFromBatch(
                $batchId,
                $qty,
                MedicationAdministration::class,
                $admin->id,
                "Medication chart administration"
            );
            $dispensedBatchId = $batchId;
        } else {
            // FIFO automatic
            $dispensed = $stockService->dispenseStock(
                $productId,
                $storeId,
                $qty,
                MedicationAdministration::class,
                $admin->id,
                "Medication chart administration (FIFO)"
            );
            $dispensedBatchId = array_key_first($dispensed);
        }
        
        // Store batch info on administration record
        $admin->dispensed_from_batch_id = $dispensedBatchId;
        $admin->save();
    }
}
```

### 5.2 Database Migration

```php
// Add batch tracking to medication_administrations table
Schema::table('medication_administrations', function (Blueprint $table) {
    $table->unsignedBigInteger('dispensed_from_batch_id')->nullable()->after('store_id');
    $table->foreign('dispensed_from_batch_id')->references('id')->on('stock_batches')->nullOnDelete();
});
```

### 5.3 Frontend Changes (nurse_chart_medication_enhanced.blade.php)

Add batch selection dropdown after store selection:
```html
<!-- Batch Selection (add after store selection) -->
<div class="mb-3" id="administer-batch-section" style="display: none;">
    <label for="administer_batch_id" class="form-label">
        <i class="mdi mdi-package-variant text-info"></i> Select Batch (optional)
    </label>
    <select class="form-select" id="administer_batch_id" name="batch_id">
        <option value="">Use FIFO (Auto)</option>
        <!-- Populated via AJAX -->
    </select>
    <small class="text-muted">Leave empty for automatic FIFO selection</small>
</div>
```

Add JavaScript to fetch batches when store changes:
```javascript
$(document).on('change', '#administer_store_id', function() {
    const storeId = $(this).val();
    const productId = $('#administer_product_id').val();
    
    if (storeId && productId) {
        fetchProductBatchesForMedChart(productId, storeId);
    }
});

function fetchProductBatchesForMedChart(productId, storeId) {
    $.get('/nursing-workbench/product-batches', {
        product_id: productId,
        store_id: storeId
    }, function(response) {
        const $select = $('#administer_batch_id');
        $select.html('<option value="">Use FIFO (Auto)</option>');
        
        if (response.success && response.batches.length > 0) {
            response.batches.forEach(batch => {
                const expiry = batch.expiry_formatted || 'No expiry';
                const warning = batch.is_expiring_soon ? '⚠️' : '';
                $select.append(`<option value="${batch.id}">${warning} ${batch.batch_number} (${batch.qty} units, Exp: ${expiry})</option>`);
            });
            $('#administer-batch-section').show();
        } else {
            $('#administer-batch-section').hide();
        }
    });
}
```

---

## 6. Testing Checklist

### After Implementation:

| Test Case | Expected Result | Status |
|-----------|-----------------|--------|
| Administer med without batch selection | Uses FIFO, deducts from oldest batch | ☐ |
| Administer med with batch selection | Deducts from selected batch | ☐ |
| Check StockBatchTransaction audit | New transaction created with MedicationAdministration ref | ☐ |
| Check store_stocks sync | Aggregate updated correctly | ☐ |
| Administer when stock = 0 | Error message, no stock deducted | ☐ |
| View administration details | Shows batch number used | ☐ |

---

## 7. Conclusion

The batch stock system is **well-implemented** in Store/Requisition and Pharmacy Workbench. The **critical gap** is the `MedicationChartController` which bypasses the batch system entirely, causing:

1. Loss of audit trail for medication administration
2. Stock inconsistency between `store_stocks` and `stock_batches`  
3. No FIFO enforcement for medications
4. Inability to track which batch was used for each administration

**Recommended Action**: Fix MedicationChartController as Priority 0 before any new pharmacy/nursing features.

---

## Appendix: File Reference Quick Links

### Core Services
- [StockService.php](app/Services/StockService.php) - Central stock management
- [BatchHelper.php](app/Helpers/BatchHelper.php) - Batch selection utilities

### Models
- [StockBatch.php](app/Models/StockBatch.php) - Batch entity
- [StockBatchTransaction.php](app/Models/StockBatchTransaction.php) - Audit trail

### Controllers
- [StoreWorkbenchController.php](app/Http/Controllers/StoreWorkbenchController.php) - ✅ Source of truth
- [StoreRequisitionController.php](app/Http/Controllers/StoreRequisitionController.php) - ✅ Good example
- [PharmacyWorkbenchController.php](app/Http/Controllers/PharmacyWorkbenchController.php) - ✅ Complete
- [NursingWorkbenchController.php](app/Http/Controllers/NursingWorkbenchController.php) - ✅ Complete for injections/vaccines/consumables
- [MedicationChartController.php](app/Http/Controllers/MedicationChartController.php) - 🚨 NEEDS FIX

### Routes
- [routes/inventory.php](routes/inventory.php) - Pharmacy batch routes
- [routes/nursing_workbench.php](routes/nursing_workbench.php) - Nursing batch routes
- [routes/nurse_chart.php](routes/nurse_chart.php) - Medication chart routes
