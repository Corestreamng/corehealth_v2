<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NursingWorkbenchController;
use App\Http\Controllers\ShiftController;

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
    Route::get('/queue/vitals', [NursingWorkbenchController::class, 'getVitalsQueue'])->name('vitals-queue');
    Route::get('/queue/bed-requests', [NursingWorkbenchController::class, 'getBedRequestsQueue'])->name('bed-requests-queue');
    Route::get('/queue/discharge-requests', [NursingWorkbenchController::class, 'getDischargeQueue'])->name('discharge-queue');
    Route::get('/queue/medication-due', [NursingWorkbenchController::class, 'getMedicationDueQueue'])->name('medication-due');
    Route::get('/queue-counts', [NursingWorkbenchController::class, 'getQueueCounts'])->name('queue-counts');
    Route::get('/wards', [NursingWorkbenchController::class, 'getWards'])->name('wards');

    // =====================================
    // Patient Details & Context
    // =====================================
    Route::get('/patient/{patientId}/details', [NursingWorkbenchController::class, 'getPatientDetails'])->name('patient-details');
    Route::get('/patient/{patientId}/vitals', [NursingWorkbenchController::class, 'getPatientVitals'])->name('patient-vitals');
    Route::get('/patient/{patientId}/vitals-history-dt', [NursingWorkbenchController::class, 'getPatientVitalsDt'])->name('patient-vitals-dt');
    Route::put('/vitals/{vitalId}', [NursingWorkbenchController::class, 'updateVital'])->name('vitals.update');
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
    // Stock Batch Selection (Store-Based FIFO)
    // =====================================
    Route::get('/product-batches', [NursingWorkbenchController::class, 'getProductBatches'])->name('product-batches');
    Route::get('/batch-fulfillment', [NursingWorkbenchController::class, 'getBatchFulfillmentSuggestion'])->name('batch-fulfillment');
    Route::get('/product-stock/{productId}/store/{storeId}', [NursingWorkbenchController::class, 'getProductStockByStore'])->name('product-stock');

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

    // Comprehensive Nursing Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/activity-summary', [NursingWorkbenchController::class, 'getReportsActivitySummary'])->name('activity-summary');
        Route::get('/vitals', [NursingWorkbenchController::class, 'getReportsVitals'])->name('vitals');
        Route::get('/medications', [NursingWorkbenchController::class, 'getReportsMedications'])->name('medications');
        Route::get('/injections', [NursingWorkbenchController::class, 'getReportsInjections'])->name('injections');
        Route::get('/immunizations', [NursingWorkbenchController::class, 'getReportsImmunizations'])->name('immunizations');
        Route::get('/io', [NursingWorkbenchController::class, 'getReportsIO'])->name('io');
        Route::get('/notes', [NursingWorkbenchController::class, 'getReportsNotes'])->name('notes');
        Route::get('/shifts', [NursingWorkbenchController::class, 'getReportsShifts'])->name('shifts');
        Route::get('/occupancy', [NursingWorkbenchController::class, 'getReportsOccupancy'])->name('occupancy');
        Route::get('/nurses', [NursingWorkbenchController::class, 'getReportsNurses'])->name('nurses');
    });

    // =====================================
    // Ward Dashboard
    // =====================================
    Route::get('/ward-dashboard/stats', [NursingWorkbenchController::class, 'getWardDashboardStats'])->name('ward-dashboard.stats');
    Route::get('/ward-dashboard/wards', [NursingWorkbenchController::class, 'getWardDashboardWards'])->name('ward-dashboard.wards');
    Route::get('/ward-dashboard/admission-queue', [NursingWorkbenchController::class, 'getAdmissionQueue'])->name('ward-dashboard.admission-queue');
    Route::get('/ward-dashboard/discharge-queue', [NursingWorkbenchController::class, 'getDischargeQueue'])->name('ward-dashboard.discharge-queue');
    Route::get('/ward-dashboard/available-beds', [NursingWorkbenchController::class, 'getAvailableBeds'])->name('ward-dashboard.available-beds');

    // Bed Management
    Route::get('/bed/{bedId}/details', [NursingWorkbenchController::class, 'getBedDetails'])->name('bed.details');
    Route::post('/bed/{bedId}/maintenance', [NursingWorkbenchController::class, 'setBedMaintenance'])->name('bed.maintenance');
    Route::post('/bed/{bedId}/available', [NursingWorkbenchController::class, 'setBedAvailable'])->name('bed.available');

    // Admission Workflow
    Route::get('/admission/{admissionId}/details', [NursingWorkbenchController::class, 'getAdmissionDetails'])->name('admission.details');
    Route::get('/admission/{admissionId}/checklist', [NursingWorkbenchController::class, 'getAdmissionChecklist'])->name('admission.checklist');
    Route::post('/admission-checklist/item/{itemId}/complete', [NursingWorkbenchController::class, 'completeAdmissionChecklistItem'])->name('admission-checklist.complete');
    Route::post('/admission-checklist/item/{itemId}/waive', [NursingWorkbenchController::class, 'waiveAdmissionChecklistItem'])->name('admission-checklist.waive');
    Route::post('/admission/{admissionId}/assign-bed', [NursingWorkbenchController::class, 'assignBed'])->name('admission.assign-bed');

    // Discharge Workflow
    Route::get('/admission/{admissionId}/discharge-checklist', [NursingWorkbenchController::class, 'getDischargeChecklist'])->name('discharge.checklist');
    Route::post('/discharge-checklist/item/{itemId}/complete', [NursingWorkbenchController::class, 'completeDischargeChecklistItem'])->name('discharge-checklist.complete');
    Route::post('/discharge-checklist/item/{itemId}/waive', [NursingWorkbenchController::class, 'waiveDischargeChecklistItem'])->name('discharge-checklist.waive');
    Route::post('/admission/{admissionId}/complete-discharge', [NursingWorkbenchController::class, 'completeDischarge'])->name('admission.complete-discharge');

    // =====================================
    // Shift Management
    // =====================================
    Route::get('/shift/check', [ShiftController::class, 'checkActiveShift'])->name('shift.check');
    Route::get('/shift/pending-handovers', [ShiftController::class, 'getPendingHandovers'])->name('shift.pending-handovers');
    Route::post('/shift/start', [ShiftController::class, 'startShift'])->name('shift.start');
    Route::post('/shift/end', [ShiftController::class, 'endShift'])->name('shift.end');
    Route::get('/shift/preview', [ShiftController::class, 'getShiftPreview'])->name('shift.preview');
    Route::get('/shift/actions', [ShiftController::class, 'getShiftActions'])->name('shift.actions');
    Route::get('/shift/wards', [ShiftController::class, 'getWards'])->name('shift.wards');
    Route::get('/shift/calendar', [ShiftController::class, 'getCalendar'])->name('shift.calendar');
    Route::get('/shift/statistics', [ShiftController::class, 'getStatistics'])->name('shift.statistics');

    // =====================================
    // Handovers
    // =====================================
    Route::get('/handovers', [ShiftController::class, 'getHandovers'])->name('handovers.list');
    Route::get('/handover/{id}', [ShiftController::class, 'getHandoverDetails'])->name('handover.details');
    Route::post('/handover/{id}/acknowledge', [ShiftController::class, 'acknowledgeHandover'])->name('handover.acknowledge');
    Route::post('/handovers/acknowledge-multiple', [ShiftController::class, 'acknowledgeMultiple'])->name('handovers.acknowledge-multiple');

    // =====================================
    // Nursing Reports Module
    // =====================================
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/activity-summary', [NursingWorkbenchController::class, 'getReportsActivitySummary'])->name('activity-summary');
        Route::get('/vitals', [NursingWorkbenchController::class, 'getReportsVitals'])->name('vitals');
        Route::get('/medications', [NursingWorkbenchController::class, 'getReportsMedications'])->name('medications');
        Route::get('/injections', [NursingWorkbenchController::class, 'getReportsInjections'])->name('injections');
        Route::get('/immunizations', [NursingWorkbenchController::class, 'getReportsImmunizations'])->name('immunizations');
        Route::get('/io', [NursingWorkbenchController::class, 'getReportsIO'])->name('io');
        Route::get('/notes', [NursingWorkbenchController::class, 'getReportsNotes'])->name('notes');
        Route::get('/shifts', [NursingWorkbenchController::class, 'getReportsShifts'])->name('shifts');
        Route::get('/occupancy', [NursingWorkbenchController::class, 'getReportsOccupancy'])->name('occupancy');
        Route::get('/nurses', [NursingWorkbenchController::class, 'getReportsNurses'])->name('nurses');
    });
});
