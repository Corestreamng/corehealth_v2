<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MaternityWorkbenchController;

/*
|--------------------------------------------------------------------------
| Maternity Workbench Routes
|--------------------------------------------------------------------------
|
| Routes for the Maternity Module — ANC, Delivery, Postnatal & Child Wellness.
| All routes are protected by the MATERNITY role (plus SUPERADMIN/ADMIN).
|
*/

Route::middleware(['web', 'auth', 'role:SUPERADMIN|ADMIN|MATERNITY'])
    ->prefix('maternity-workbench')
    ->name('maternity-workbench.')
    ->group(function () {

        // ── Workbench Page ──────────────────────────────────────────
        Route::get('/', [MaternityWorkbenchController::class, 'index'])->name('index');

        // ── Patient Search ──────────────────────────────────────────
        Route::get('/search-patients', [MaternityWorkbenchController::class, 'searchPatients'])->name('search-patients');
        Route::get('/patient/{id}/details', [MaternityWorkbenchController::class, 'getPatientDetails'])->name('patient.details');

        // ── Enrollment ──────────────────────────────────────────────
        Route::post('/enroll', [MaternityWorkbenchController::class, 'enrollPatient'])->name('enroll');
        Route::get('/enrollment/{id}', [MaternityWorkbenchController::class, 'getEnrollment'])->name('enrollment.show');
        Route::put('/enrollment/{id}', [MaternityWorkbenchController::class, 'updateEnrollment'])->name('enrollment.update');
        Route::post('/enrollment/{id}/discharge', [MaternityWorkbenchController::class, 'dischargeEnrollment'])->name('enrollment.discharge');
        Route::get('/enrollment/{id}/timeline', [MaternityWorkbenchController::class, 'getTimeline'])->name('enrollment.timeline');
        Route::get('/enrollment/{id}/print-anc-card', [MaternityWorkbenchController::class, 'printAncCard'])->name('enrollment.print-anc-card');
        Route::get('/enrollment/{id}/print-road-health-card', [MaternityWorkbenchController::class, 'printRoadHealthCard'])->name('enrollment.print-road-health-card');
        Route::get('/enrollment/{id}/audit-trail', [MaternityWorkbenchController::class, 'getAuditTrail'])->name('enrollment.audit-trail');

        // ── Mother's History ────────────────────────────────────────
        Route::post('/enrollment/{id}/medical-history', [MaternityWorkbenchController::class, 'saveMedicalHistory'])->name('enrollment.medical-history');
        Route::put('/medical-history/{id}', [MaternityWorkbenchController::class, 'updateMedicalHistory'])->name('medical-history.update');
        Route::delete('/medical-history/{id}', [MaternityWorkbenchController::class, 'deleteMedicalHistory'])->name('medical-history.delete');
        Route::post('/enrollment/{id}/prev-pregnancy', [MaternityWorkbenchController::class, 'savePreviousPregnancy'])->name('enrollment.prev-pregnancy');
        Route::put('/prev-pregnancy/{id}', [MaternityWorkbenchController::class, 'updatePreviousPregnancy'])->name('prev-pregnancy.update');
        Route::delete('/prev-pregnancy/{id}', [MaternityWorkbenchController::class, 'deletePreviousPregnancy'])->name('prev-pregnancy.delete');

        // ── ANC Visits ──────────────────────────────────────────────
        Route::get('/enrollment/{id}/anc-visits', [MaternityWorkbenchController::class, 'getAncVisits'])->name('enrollment.anc-visits');
        Route::post('/enrollment/{id}/anc-visit', [MaternityWorkbenchController::class, 'saveAncVisit'])->name('enrollment.anc-visit.store');
        Route::put('/anc-visit/{id}', [MaternityWorkbenchController::class, 'updateAncVisit'])->name('anc-visit.update');
        Route::get('/anc-visit/{id}', [MaternityWorkbenchController::class, 'getAncVisitDetail'])->name('anc-visit.show');

        // ── Investigations (Lab & Imaging) ──────────────────────────
        Route::get('/enrollment/{id}/investigations', [MaternityWorkbenchController::class, 'getInvestigations'])->name('enrollment.investigations');
        Route::post('/enrollment/{id}/investigation', [MaternityWorkbenchController::class, 'orderInvestigation'])->name('enrollment.investigation.store');

        // ── Clinical Orders (via ClinicalOrdersTrait) ───────────────
        Route::post('/enrollment/{id}/labs', [MaternityWorkbenchController::class, 'saveMaternityLabs'])->name('enrollment.labs');
        Route::post('/enrollment/{id}/imaging', [MaternityWorkbenchController::class, 'saveMaternityImaging'])->name('enrollment.imaging');
        Route::post('/enrollment/{id}/prescriptions', [MaternityWorkbenchController::class, 'saveMaternityPrescriptions'])->name('enrollment.prescriptions');
        Route::post('/enrollment/{id}/procedures', [MaternityWorkbenchController::class, 'saveMaternityProcedures'])->name('enrollment.procedures');
        Route::post('/enrollment/{id}/re-prescribe', [MaternityWorkbenchController::class, 'maternityRePrescribe'])->name('enrollment.re-prescribe');
        Route::get('/enrollment/{id}/recent-encounters', [MaternityWorkbenchController::class, 'maternityRecentEncounters'])->name('enrollment.recent-encounters');
        Route::get('/enrollment/{id}/encounter-items/{encounterId}', [MaternityWorkbenchController::class, 'maternityEncounterItems'])->name('enrollment.encounter-items');
        Route::post('/enrollment/{id}/apply-treatment-plan', [MaternityWorkbenchController::class, 'applyMaternityTreatmentPlan'])->name('enrollment.apply-treatment-plan');

        // ── Clinical Orders — Single-item auto-save endpoints ───────
        Route::post('/enrollment/{id}/add-lab', [MaternityWorkbenchController::class, 'maternityAddSingleLab'])->name('enrollment.addLab');
        Route::delete('/enrollment/{id}/labs/{lab}', [MaternityWorkbenchController::class, 'maternityRemoveSingleLab'])->name('enrollment.removeLab');
        Route::post('/enrollment/{id}/add-imaging', [MaternityWorkbenchController::class, 'maternityAddSingleImaging'])->name('enrollment.addImaging');
        Route::delete('/enrollment/{id}/imaging/{imaging}', [MaternityWorkbenchController::class, 'maternityRemoveSingleImaging'])->name('enrollment.removeImaging');
        Route::post('/enrollment/{id}/add-prescription', [MaternityWorkbenchController::class, 'maternityAddSinglePrescription'])->name('enrollment.addPrescription');
        Route::put('/enrollment/{id}/prescriptions/{prescription}/dose', [MaternityWorkbenchController::class, 'maternityUpdatePrescriptionDose'])->name('enrollment.updatePrescriptionDose');
        Route::delete('/enrollment/{id}/prescriptions/{prescription}', [MaternityWorkbenchController::class, 'maternityRemoveSinglePrescription'])->name('enrollment.removePrescription');
        Route::post('/enrollment/{id}/add-procedure', [MaternityWorkbenchController::class, 'maternityAddSingleProcedure'])->name('enrollment.addProcedure');
        Route::delete('/enrollment/{id}/procedures/{procedure}', [MaternityWorkbenchController::class, 'maternityRemoveSingleProcedure'])->name('enrollment.removeProcedure');
        Route::put('/enrollment/{id}/labs/{lab}/note', [MaternityWorkbenchController::class, 'maternityUpdateLabNote'])->name('enrollment.updateLabNote');
        Route::put('/enrollment/{id}/imaging/{imaging}/note', [MaternityWorkbenchController::class, 'maternityUpdateImagingNote'])->name('enrollment.updateImagingNote');

        // ── Delivery ────────────────────────────────────────────────
        Route::post('/enrollment/{id}/delivery', [MaternityWorkbenchController::class, 'saveDeliveryRecord'])->name('enrollment.delivery.store');
        Route::put('/delivery/{id}', [MaternityWorkbenchController::class, 'updateDeliveryRecord'])->name('delivery.update');
        Route::get('/delivery/{id}', [MaternityWorkbenchController::class, 'getDeliveryRecord'])->name('delivery.show');
        Route::post('/delivery/{id}/partograph', [MaternityWorkbenchController::class, 'savePartographEntry'])->name('delivery.partograph.store');
        Route::get('/delivery/{id}/partograph', [MaternityWorkbenchController::class, 'getPartographEntries'])->name('delivery.partograph.index');
        Route::put('/partograph/{id}', [MaternityWorkbenchController::class, 'updatePartographEntry'])->name('partograph.update');
        Route::delete('/partograph/{id}', [MaternityWorkbenchController::class, 'deletePartographEntry'])->name('partograph.destroy');

        // ── Baby ────────────────────────────────────────────────────
        Route::post('/enrollment/{id}/baby', [MaternityWorkbenchController::class, 'registerBaby'])->name('enrollment.baby.store');
        Route::get('/baby/{id}', [MaternityWorkbenchController::class, 'getBabyDetails'])->name('baby.show');
        Route::put('/baby/{id}', [MaternityWorkbenchController::class, 'updateBaby'])->name('baby.update');
        Route::post('/baby/{id}/growth', [MaternityWorkbenchController::class, 'saveGrowthRecord'])->name('baby.growth.store');
        Route::get('/baby/{id}/growth-chart', [MaternityWorkbenchController::class, 'getGrowthChartData'])->name('baby.growth-chart');

        // ── Postnatal ───────────────────────────────────────────────
        Route::get('/enrollment/{id}/postnatal', [MaternityWorkbenchController::class, 'getPostnatalVisits'])->name('enrollment.postnatal.index');
        Route::post('/enrollment/{id}/postnatal', [MaternityWorkbenchController::class, 'savePostnatalVisit'])->name('enrollment.postnatal.store');
        Route::put('/postnatal/{id}', [MaternityWorkbenchController::class, 'updatePostnatalVisit'])->name('postnatal.update');

        // ── Immunization (unified with nursing schedule system) ─────
        Route::get('/patient/{patientId}/schedule', [MaternityWorkbenchController::class, 'getPatientScheduleMaternity'])->name('schedule.get');
        Route::post('/patient/{patientId}/generate-schedule', [MaternityWorkbenchController::class, 'generatePatientScheduleMaternity'])->name('schedule.generate');
        Route::get('/patient/{patientId}/immunization-history', [MaternityWorkbenchController::class, 'getImmunizationHistoryByPatient'])->name('immunization.history-data');
        Route::post('/administer-from-schedule', [MaternityWorkbenchController::class, 'administerFromScheduleMaternity'])->name('schedule.administer-new');
        Route::put('/schedule/{scheduleId}/status', [MaternityWorkbenchController::class, 'updateScheduleStatusMaternity'])->name('schedule.update-status');
        Route::get('/schedule-templates', [MaternityWorkbenchController::class, 'getScheduleTemplatesMaternity'])->name('schedule.templates');
        Route::get('/vaccine-products/{vaccineName}', [MaternityWorkbenchController::class, 'getVaccineProductsMaternity'])->name('vaccine.products');
        Route::get('/product-batches', [MaternityWorkbenchController::class, 'getProductBatchesMaternity'])->name('product-batches');

        // Mother-specific wrappers
        Route::get('/enrollment/{id}/mother-schedule', [MaternityWorkbenchController::class, 'getMotherSchedule'])->name('mother.schedule');
        Route::post('/enrollment/{id}/generate-mother-schedule', [MaternityWorkbenchController::class, 'generateMotherSchedule'])->name('mother.schedule.generate');
        Route::get('/enrollment/{id}/mother-immunization-history', [MaternityWorkbenchController::class, 'getMotherImmunizationHistory'])->name('mother.immunization-history');

        // Baby-specific wrappers
        Route::get('/baby/{id}/schedule', [MaternityWorkbenchController::class, 'getBabySchedule'])->name('baby.schedule');
        Route::post('/baby/{id}/generate-schedule', [MaternityWorkbenchController::class, 'generateBabySchedule'])->name('baby.schedule.generate');
        Route::get('/baby/{id}/immunization-history', [MaternityWorkbenchController::class, 'getBabyImmunizationHistory'])->name('baby.immunization-history');

        // Backward compatibility routes
        Route::get('/baby/{id}/immunization-schedule', [MaternityWorkbenchController::class, 'getImmunizationSchedule'])->name('baby.immunization-schedule');
        Route::post('/baby/{id}/immunization', [MaternityWorkbenchController::class, 'administerImmunization'])->name('baby.immunization.store');
        Route::post('/baby/{id}/immunization-from-schedule', [MaternityWorkbenchController::class, 'administerFromSchedule'])->name('baby.immunization-from-schedule');

        // ── Nursing Notes ───────────────────────────────────────────
        Route::get('/enrollment/{id}/notes', [MaternityWorkbenchController::class, 'getNotes'])->name('enrollment.notes');
        Route::post('/enrollment/{id}/note', [MaternityWorkbenchController::class, 'saveNote'])->name('enrollment.note.store');
        Route::put('/note/{id}', [MaternityWorkbenchController::class, 'updateNote'])->name('note.update');
        Route::delete('/note/{id}', [MaternityWorkbenchController::class, 'deleteNote'])->name('note.delete');

        // ── Vitals ──────────────────────────────────────────────────
        Route::get('/patient/{id}/vitals', [MaternityWorkbenchController::class, 'getPatientVitals'])->name('patient.vitals');
        Route::post('/patient/{id}/vital', [MaternityWorkbenchController::class, 'saveVital'])->name('patient.vital.store');

        // ── Queues ──────────────────────────────────────────────────
        Route::get('/queue/active-anc', [MaternityWorkbenchController::class, 'getActiveAncQueue'])->name('queue.active-anc');
        Route::get('/queue/due-visits', [MaternityWorkbenchController::class, 'getDueVisitsQueue'])->name('queue.due-visits');
        Route::get('/queue/upcoming-edd', [MaternityWorkbenchController::class, 'getUpcomingEddQueue'])->name('queue.upcoming-edd');
        Route::get('/queue/postnatal', [MaternityWorkbenchController::class, 'getPostnatalQueue'])->name('queue.postnatal');
        Route::get('/queue/overdue-immunization', [MaternityWorkbenchController::class, 'getOverdueImmunizationQueue'])->name('queue.overdue-immunization');
        Route::get('/queue/high-risk', [MaternityWorkbenchController::class, 'getHighRiskQueue'])->name('queue.high-risk');
        Route::get('/queue/counts', [MaternityWorkbenchController::class, 'getQueueCounts'])->name('queue.counts');

        // ── Reports ─────────────────────────────────────────────────
        Route::get('/reports/summary', [MaternityWorkbenchController::class, 'getReportsSummary'])->name('reports.summary');
        Route::get('/reports/delivery-stats', [MaternityWorkbenchController::class, 'getDeliveryStats'])->name('reports.delivery-stats');
        Route::get('/reports/immunization-coverage', [MaternityWorkbenchController::class, 'getImmunizationCoverage'])->name('reports.immunization-coverage');
        Route::get('/reports/anc-defaulters', [MaternityWorkbenchController::class, 'getAncDefaulters'])->name('reports.anc-defaulters');
        Route::get('/reports/high-risk-register', [MaternityWorkbenchController::class, 'getHighRiskRegister'])->name('reports.high-risk-register');

        // ── Search Services (for billing at enrollment) ─────────────
        Route::get('/search-services', [MaternityWorkbenchController::class, 'searchServices'])->name('search-services');
    });
