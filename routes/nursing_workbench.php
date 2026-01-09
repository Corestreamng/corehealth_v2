<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NursingWorkbenchController;

/*
|--------------------------------------------------------------------------
| Nursing Workbench Routes
|--------------------------------------------------------------------------
|
| These routes handle all nursing workbench functionality including
| patient queue, injections, immunizations, billing, and nursing notes.
|
*/

Route::middleware(['web', 'auth'])->prefix('nursing-workbench')->name('nursing-workbench.')->group(function () {

    // Main workbench view
    Route::get('/', [NursingWorkbenchController::class, 'index'])->name('index');

    // =====================================
    // Patient Search & Queue
    // =====================================
    Route::get('/search-patients', [NursingWorkbenchController::class, 'searchPatients'])->name('search-patients');
    Route::get('/admitted-patients', [NursingWorkbenchController::class, 'getAdmittedPatients'])->name('admitted-patients');
    Route::get('/queue-counts', [NursingWorkbenchController::class, 'getQueueCounts'])->name('queue-counts');
    Route::get('/wards', [NursingWorkbenchController::class, 'getWards'])->name('wards');

    // =====================================
    // Patient Details & Context
    // =====================================
    Route::get('/patient/{patientId}/details', [NursingWorkbenchController::class, 'getPatientDetails'])->name('patient-details');
    Route::get('/patient/{patientId}/vitals', [NursingWorkbenchController::class, 'getPatientVitals'])->name('patient-vitals');
    Route::get('/patient/{patientId}/orders', [NursingWorkbenchController::class, 'getPatientOrders'])->name('patient-orders');

    // =====================================
    // Injection Service
    // =====================================
    Route::get('/search-injectables', [NursingWorkbenchController::class, 'searchInjectables'])->name('search-injectables');
    Route::get('/patient/{patientId}/injections', [NursingWorkbenchController::class, 'getInjections'])->name('injection.history');
    Route::post('/administer-injection', [NursingWorkbenchController::class, 'administerInjection'])->name('injection.administer');

    // =====================================
    // Immunization Module
    // =====================================
    Route::get('/search-vaccines', [NursingWorkbenchController::class, 'getVaccines'])->name('search-vaccines');
    Route::get('/patient/{patientId}/immunizations', [NursingWorkbenchController::class, 'getImmunizations'])->name('immunization.history');
    Route::get('/patient/{patientId}/immunization-schedule', [NursingWorkbenchController::class, 'getImmunizationSchedule'])->name('immunization.schedule');
    Route::post('/administer-immunization', [NursingWorkbenchController::class, 'administerImmunization'])->name('immunization.administer');

    // =====================================
    // Nurse Billing
    // =====================================
    Route::get('/search-services', [NursingWorkbenchController::class, 'searchServices'])->name('search-services');
    Route::get('/search-products', [NursingWorkbenchController::class, 'searchProducts'])->name('search-products');
    Route::get('/service-categories', [NursingWorkbenchController::class, 'getServiceCategories'])->name('service-categories');
    Route::get('/product-categories', [NursingWorkbenchController::class, 'getProductCategories'])->name('product-categories');
    Route::get('/patient/{patientId}/pending-bills', [NursingWorkbenchController::class, 'getPendingBills'])->name('billing.pending');
    Route::post('/add-service-bill', [NursingWorkbenchController::class, 'addServiceBill'])->name('billing.add-service');
    Route::post('/add-consumable-bill', [NursingWorkbenchController::class, 'addConsumableBill'])->name('billing.add-consumable');
    Route::delete('/remove-bill/{id}', [NursingWorkbenchController::class, 'removeBillItem'])->name('billing.remove');

    // =====================================
    // Nursing Notes
    // =====================================
    Route::get('/patient/{patientId}/nursing-notes', [NursingWorkbenchController::class, 'getNursingNotes'])->name('notes.list');
    Route::get('/nursing-note/{noteId}', [NursingWorkbenchController::class, 'getNoteDetails'])->name('notes.show');
    Route::get('/note-types', [NursingWorkbenchController::class, 'getNoteTypes'])->name('note-types');
    Route::post('/nursing-note', [NursingWorkbenchController::class, 'saveNursingNote'])->name('notes.store');
    Route::put('/nursing-note/{noteId}', [NursingWorkbenchController::class, 'updateNursingNote'])->name('notes.update');

    // =====================================
    // Immunization Schedule System
    // =====================================
    Route::get('/patient/{patientId}/schedule', [NursingWorkbenchController::class, 'getPatientSchedule'])->name('schedule.get');
    Route::post('/patient/{patientId}/generate-schedule', [NursingWorkbenchController::class, 'generatePatientSchedule'])->name('schedule.generate');
    Route::put('/schedule/{scheduleId}/status', [NursingWorkbenchController::class, 'updateScheduleStatus'])->name('schedule.update-status');
    Route::post('/schedule/{scheduleId}/administer', [NursingWorkbenchController::class, 'administerFromSchedule'])->name('schedule.administer');
    Route::post('/administer-from-schedule', [NursingWorkbenchController::class, 'administerFromScheduleNew'])->name('schedule.administer-new');
    Route::get('/schedule-templates', [NursingWorkbenchController::class, 'getScheduleTemplates'])->name('schedule.templates');
    Route::get('/vaccine-products/{vaccineName}', [NursingWorkbenchController::class, 'getVaccineProducts'])->name('vaccine.products');
    Route::get('/patient/{patientId}/immunization-history', [NursingWorkbenchController::class, 'getImmunizationHistory'])->name('immunization.history-data');

    // =====================================
    // Reports
    // =====================================
    Route::get('/shift-summary', [NursingWorkbenchController::class, 'getShiftSummary'])->name('shift-summary');
    Route::get('/handover-summary', [NursingWorkbenchController::class, 'generateHandoverReport'])->name('handover.summary');
    Route::get('/handover-export', [NursingWorkbenchController::class, 'exportHandoverReport'])->name('handover.export');
});
