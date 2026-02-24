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
        Route::get('/enrollment/{id}/timeline', [MaternityWorkbenchController::class, 'getTimeline'])->name('enrollment.timeline');

        // ── Mother's History ────────────────────────────────────────
        Route::post('/enrollment/{id}/medical-history', [MaternityWorkbenchController::class, 'saveMedicalHistory'])->name('enrollment.medical-history');
        Route::post('/enrollment/{id}/prev-pregnancy', [MaternityWorkbenchController::class, 'savePreviousPregnancy'])->name('enrollment.prev-pregnancy');
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

        // ── Delivery ────────────────────────────────────────────────
        Route::post('/enrollment/{id}/delivery', [MaternityWorkbenchController::class, 'saveDeliveryRecord'])->name('enrollment.delivery.store');
        Route::put('/delivery/{id}', [MaternityWorkbenchController::class, 'updateDeliveryRecord'])->name('delivery.update');
        Route::get('/delivery/{id}', [MaternityWorkbenchController::class, 'getDeliveryRecord'])->name('delivery.show');
        Route::post('/delivery/{id}/partograph', [MaternityWorkbenchController::class, 'savePartographEntry'])->name('delivery.partograph.store');
        Route::get('/delivery/{id}/partograph', [MaternityWorkbenchController::class, 'getPartographEntries'])->name('delivery.partograph.index');

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

        // ── Immunization (proxied to existing system) ───────────────
        Route::get('/baby/{id}/immunization-schedule', [MaternityWorkbenchController::class, 'getImmunizationSchedule'])->name('baby.immunization-schedule');
        Route::get('/baby/{id}/immunization-history', [MaternityWorkbenchController::class, 'getImmunizationHistory'])->name('baby.immunization-history');
        Route::post('/baby/{id}/immunization', [MaternityWorkbenchController::class, 'administerImmunization'])->name('baby.immunization.store');
        Route::post('/baby/{id}/immunization-from-schedule', [MaternityWorkbenchController::class, 'administerFromSchedule'])->name('baby.immunization-from-schedule');

        // ── Nursing Notes ───────────────────────────────────────────
        Route::get('/enrollment/{id}/notes', [MaternityWorkbenchController::class, 'getNotes'])->name('enrollment.notes');
        Route::post('/enrollment/{id}/note', [MaternityWorkbenchController::class, 'saveNote'])->name('enrollment.note.store');

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
