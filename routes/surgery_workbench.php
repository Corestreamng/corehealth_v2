<?php

use App\Http\Controllers\SurgeryWorkbenchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('surgery-workbench')->name('surgery-workbench.')->group(function () {

    // Main workbench page
    Route::get('/', [SurgeryWorkbenchController::class, 'index'])->name('index');

    // Queue & stats
    Route::get('/queue-counts', [SurgeryWorkbenchController::class, 'getQueueCounts'])->name('queue-counts');
    Route::get('/queue', [SurgeryWorkbenchController::class, 'getQueue'])->name('queue');

    // Patient search
    Route::get('/search-patients', [SurgeryWorkbenchController::class, 'searchPatients'])->name('search-patients');

    // Patient procedures (inline drawer)
    Route::get('/patient/{patientId}/procedures', [SurgeryWorkbenchController::class, 'getPatientProcedures'])->name('patient-procedures');

    // Billing — services & consumables search
    // (search-services, search-products, product-batches, add-service-bill, add-consumable-bill,
    //  patient/{id}/pending-bills, remove-bill/{id} are shared from nursing-workbench routes)
});
