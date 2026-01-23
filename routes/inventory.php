<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\StoreRequisitionController;
use App\Http\Controllers\StoreWorkbenchController;
use App\Http\Controllers\ExpenseController;

/**
 * Inventory Management Routes
 *
 * Plan Reference: Phase 5 - Routes
 * Purpose: Routes for the new inventory management system
 *
 * Features:
 * - Purchase Order management
 * - Store Requisitions
 * - Store Workbench
 * - Expense tracking
 *
 * Include this file in web.php:
 * require __DIR__.'/inventory.php';
 */

Route::middleware(['auth'])->prefix('inventory')->name('inventory.')->group(function () {

    // ===== PURCHASE ORDERS =====
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');

        // Accounts Payable (must be before {purchaseOrder} routes)
        Route::get('/accounts-payable', [PurchaseOrderController::class, 'accountsPayable'])->name('accounts-payable');

        // AJAX (must be before {purchaseOrder} routes)
        Route::get('/ajax/search-products', [PurchaseOrderController::class, 'searchProducts'])->name('search-products');

        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::get('/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');

        // Workflow actions
        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');

        // Receiving
        Route::get('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'showReceiveForm'])->name('receive');
        Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('receive.process');

        // Payments
        Route::get('/{purchaseOrder}/payment', [PurchaseOrderController::class, 'showPaymentForm'])->name('payment');
        Route::post('/{purchaseOrder}/payment', [PurchaseOrderController::class, 'recordPayment'])->name('payment.process');
    });

    // ===== STORE REQUISITIONS =====
    Route::prefix('requisitions')->name('requisitions.')->group(function () {
        Route::get('/', [StoreRequisitionController::class, 'index'])->name('index');
        Route::get('/create', [StoreRequisitionController::class, 'create'])->name('create');
        Route::post('/', [StoreRequisitionController::class, 'store'])->name('store');
        Route::get('/{requisition}', [StoreRequisitionController::class, 'show'])->name('show');

        // Workflow actions
        Route::post('/{requisition}/approve', [StoreRequisitionController::class, 'approve'])->name('approve');
        Route::post('/{requisition}/reject', [StoreRequisitionController::class, 'reject'])->name('reject');
        Route::post('/{requisition}/cancel', [StoreRequisitionController::class, 'cancel'])->name('cancel');
        Route::post('/{requisition}/fulfill', [StoreRequisitionController::class, 'fulfill'])->name('fulfill');

        // Queue views
        Route::get('/queue/pending-approval', [StoreRequisitionController::class, 'pendingApproval'])->name('pending-approval');
        Route::get('/queue/pending-fulfillment', [StoreRequisitionController::class, 'pendingFulfillment'])->name('pending-fulfillment');

        // AJAX
        Route::get('/{requisition}/available-batches', [StoreRequisitionController::class, 'getAvailableBatches'])->name('available-batches');
    });

    // ===== STORE WORKBENCH =====
    Route::prefix('store-workbench')->name('store-workbench.')->group(function () {
        Route::get('/', [StoreWorkbenchController::class, 'index'])->name('index');
        Route::get('/stock-overview', [StoreWorkbenchController::class, 'stockOverview'])->name('stock-overview');
        Route::get('/product/{product}/batches', [StoreWorkbenchController::class, 'productBatches'])->name('product-batches');

        // Stock adjustments
        Route::get('/batch/{batch}/adjust', [StoreWorkbenchController::class, 'adjustmentForm'])->name('adjustment-form');
        Route::post('/batch/{batch}/adjust', [StoreWorkbenchController::class, 'processAdjustment'])->name('process-adjustment');
        Route::post('/batch/{batch}/write-off-expired', [StoreWorkbenchController::class, 'writeOffExpired'])->name('write-off-expired');
        Route::post('/batch/{batch}/write-off-damaged', [StoreWorkbenchController::class, 'writeOffDamaged'])->name('write-off-damaged');

        // Manual batch entry
        Route::get('/manual-batch', [StoreWorkbenchController::class, 'manualBatchForm'])->name('manual-batch-form');
        Route::post('/manual-batch', [StoreWorkbenchController::class, 'createManualBatch'])->name('create-manual-batch');

        // Reports
        Route::get('/reports/expiry', [StoreWorkbenchController::class, 'expiryReport'])->name('expiry-report');
        Route::get('/reports/stock-value', [StoreWorkbenchController::class, 'stockValueReport'])->name('stock-value-report');

        // AJAX
        Route::get('/ajax/batch-availability', [StoreWorkbenchController::class, 'getBatchAvailability'])->name('batch-availability');
    });

    // ===== EXPENSES =====
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::get('/create', [ExpenseController::class, 'create'])->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->name('show');
        Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->name('edit');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');

        // Workflow actions
        Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])->name('approve');
        Route::post('/{expense}/reject', [ExpenseController::class, 'reject'])->name('reject');
        Route::post('/{expense}/void', [ExpenseController::class, 'void'])->name('void');

        // Reports
        Route::get('/reports/summary', [ExpenseController::class, 'summaryReport'])->name('summary-report');
    });
});

// ===== PHARMACY WORKBENCH BATCH ROUTES =====
// Add these to existing pharmacy routes
Route::middleware(['auth'])->prefix('pharmacy-workbench')->group(function () {
    Route::get('/product-batches', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getProductBatches'])->name('pharmacy.product-batches');
    Route::get('/batch-suggestion', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getBatchFulfillmentSuggestion'])->name('pharmacy.batch-suggestion');
    Route::post('/dispense-with-batch', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'dispenseMedicationWithBatch'])->name('pharmacy.dispense-with-batch');
    Route::get('/expiring-batches', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getExpiringBatches'])->name('pharmacy.expiring-batches');
    Route::get('/low-stock-items', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getLowStockItems'])->name('pharmacy.low-stock-items');

    // Product Adaptation Route
    Route::post('/prescription/{id}/adapt', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'adaptPrescription'])->name('pharmacy.adapt-prescription');
});


// ===== API ROUTES FOR STANDALONE PO/REQUISITION ACCESS =====
// These provide top-level access for common operations
Route::middleware(['auth'])->group(function () {
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
    Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
    Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::get('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'showReceiveForm'])->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive.process');

    Route::get('/requisitions', [StoreRequisitionController::class, 'index'])->name('requisitions.index');
    Route::get('/requisitions/create', [StoreRequisitionController::class, 'create'])->name('requisitions.create');
    Route::post('/requisitions', [StoreRequisitionController::class, 'store'])->name('requisitions.store');
    Route::get('/requisitions/{requisition}', [StoreRequisitionController::class, 'show'])->name('requisitions.show');
    Route::post('/requisitions/{requisition}/approve', [StoreRequisitionController::class, 'approve'])->name('requisitions.approve');
    Route::post('/requisitions/{requisition}/reject', [StoreRequisitionController::class, 'reject'])->name('requisitions.reject');
    Route::post('/requisitions/{requisition}/cancel', [StoreRequisitionController::class, 'cancel'])->name('requisitions.cancel');
    Route::post('/requisitions/{requisition}/fulfill', [StoreRequisitionController::class, 'fulfill'])->name('requisitions.fulfill');

    Route::get('/store-workbench', [StoreWorkbenchController::class, 'index'])->name('store-workbench.index');
});
