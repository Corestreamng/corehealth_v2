<?php

use App\Http\Controllers\AdmissionModuleController;
use App\Http\Controllers\AdmissionRequestController;
use App\Http\Controllers\API\DataEndpoint;
use App\Http\Controllers\API\MobileAuthController;
use App\Http\Controllers\API\MobileChatController;
use App\Http\Controllers\API\MobileEncounterController;
use App\Http\Controllers\API\MobilePatientController;
use App\Http\Controllers\API\MobilePharmacyController;
use App\Http\Controllers\API\MobileReceptionController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BillingWorkbenchController;
use App\Http\Controllers\ClinicalAlertController;
use App\Http\Controllers\DiagnosisFavoriteController;
use App\Http\Controllers\DoctorAppointmentController;
use App\Http\Controllers\EmergencyIntakeController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\HospitalContactController;
use App\Http\Controllers\ImagingWorkbenchController;
use App\Http\Controllers\LabWorkbenchController;
use App\Http\Controllers\LlmController;
use App\Http\Controllers\MaternityWorkbenchController;
use App\Http\Controllers\MedicalReportController;
use App\Http\Controllers\MedicationChartController;
use App\Http\Controllers\MorgueController;
use App\Http\Controllers\NhmisWorkbenchController;
use App\Http\Controllers\NonPharmOrderController;
use App\Http\Controllers\NursingWorkbenchController;
use App\Http\Controllers\PatientProfileController;
use App\Http\Controllers\PharmacyDamagesController;
use App\Http\Controllers\PharmacyReportsController;
use App\Http\Controllers\PharmacyReturnsController;
use App\Http\Controllers\PharmacyWorkbenchController;
use App\Http\Controllers\ProcessedTransactionsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReceptionWorkbenchController;
use App\Http\Controllers\ResultViewController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SpecialistReferralController;
use App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\VitalSignController;
use App\Http\Controllers\WardController;
use App\Http\Controllers\WorkbenchPriceListController;
use App\Models\ProcedureCategory;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Internal API endpoints for procedure module (used by patient-procedures views)
// Using web middleware to share session with browser requests
Route::middleware(['web', 'auth'])->group(function () {
    // Lab Services API - Returns services from the investigation category
    Route::get('lab-services', function () {
        $labCategoryId = appsettings('investigation_category_id', 2);
        $services = Service::with('price')
            ->where('category_id', $labCategoryId)
            ->where('status', 1)
            ->orderBy('service_name')
            ->get();

        return response()->json(['data' => $services]);
    });

    // Imaging Services API - Returns services from the imaging category
    Route::get('imaging-services', function () {
        $imagingCategoryId = appsettings('imaging_category_id', 6);
        $services = Service::with('price')
            ->where('category_id', $imagingCategoryId)
            ->where('status', 1)
            ->orderBy('service_name')
            ->get();

        return response()->json(['data' => $services]);
    });

    // Products API - Returns active products
    Route::get('products', function (Request $request) {
        $query = Product::with('price')
            ->where('status', 1);

        // Optional search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('product_name', 'LIKE', "%{$search}%");
        }

        $products = $query->orderBy('product_name')->limit(100)->get();

        return response()->json(['data' => $products]);
    });

    // Procedure Categories API - Returns active procedure categories
    Route::get('procedure-categories', function () {
        $categories = ProcedureCategory::where('status', 1)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    });

    // Procedures API - Returns services from the procedures category with procedure definition
    // Spec Reference: PROCEDURE_MODULE_DESIGN_PLAN.md Part 3.6
    Route::get('procedures', function () {
        $procedureCategoryId = appsettings('procedure_category_id');

        if (!$procedureCategoryId) {
            return response()->json(['data' => [], 'message' => 'Procedure category not configured']);
        }

        $services = Service::with(['price', 'procedureDefinition.procedureCategory'])
            ->where('category_id', $procedureCategoryId)
            ->where('status', 1)
            ->orderBy('service_name')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'service_name' => $service->service_name,
                    'service_code' => $service->service_code,
                    'price' => $service->price ? $service->price->sale_price : 0,
                    'procedure_definition' => $service->procedureDefinition ? [
                        'id' => $service->procedureDefinition->id,
                        'is_surgical' => $service->procedureDefinition->is_surgical,
                        'estimated_duration_minutes' => $service->procedureDefinition->estimated_duration_minutes,
                        'category' => $service->procedureDefinition->procedureCategory ? [
                            'id' => $service->procedureDefinition->procedureCategory->id,
                            'name' => $service->procedureDefinition->procedureCategory->name,
                            'code' => $service->procedureDefinition->procedureCategory->code,
                        ] : null,
                    ] : null,
                ];
            });

        return response()->json(['data' => $services]);
    });

});

Route::get('get-facility-settings', [DataEndpoint::class, 'getFacilitySetting'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-patients', [DataEndpoint::class, 'getAllPatients'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-bookings', [DataEndpoint::class, 'getAllBookings'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-staff', [DataEndpoint::class, 'getAllStaff'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-nurses', [DataEndpoint::class, 'getAllNurses'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-doctors', [DataEndpoint::class, 'getAllDoctors'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-admissions', [DataEndpoint::class, 'getAllAdmissions'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-hmos', [DataEndpoint::class, 'getAllHMOs'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-consultations', [DataEndpoint::class, 'getAllConsultations'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-statistics', [DataEndpoint::class, 'getFullStats'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-age-dist', [DataEndpoint::class, 'getAgeDistribution'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-monthly-encounter/{year}', [DataEndpoint::class, 'encountersPerMonth'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-monthly-income/{year}', [DataEndpoint::class, 'incomePerMonth'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-monthly-investigations/{year}', [DataEndpoint::class, 'investigationsPerMonth'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-monthly-hospitalization/{year}', [DataEndpoint::class, 'hospitalizationsPerMonth'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-dispenses', [DataEndpoint::class, 'getAllDispenses'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-investigations', [DataEndpoint::class, 'getAllInvestigations'])->middleware(['auth.basic.sha256']);
Route::get('get-facility-procedures', [DataEndpoint::class, 'getAllProcedures'])->middleware(['auth.basic.sha256']);

// Ultra-fast Ping & Health Monitoring
Route::get('corehms/ping', [\App\Http\Controllers\API\CoreHmsHealthApiController::class, 'ping']);
Route::get('corehms/health', [\App\Http\Controllers\API\CoreHmsHealthApiController::class, 'health']);

/*
|--------------------------------------------------------------------------
| WeCare247 ↔ CoreHealth v2 Bidirectional Integration Endpoints
|--------------------------------------------------------------------------
|
| Public liveness probe (no auth) — matches /api/corehms/ping pattern.
| All write/lookup routes are protected by SHA-256 Basic Auth.
|
*/
Route::get('corehealth/ping', [\App\Http\Controllers\API\WeCareIntegrationController::class, 'ping']);

Route::prefix('corehealth')->middleware(['auth.basic.sha256'])->group(function () {
    // Patient lookup for WeCare247 invite autofill
    Route::get('patient/lookup', [\App\Http\Controllers\API\WeCareIntegrationController::class, 'lookupPatient']);

    // Longitudinal Clinical Story EHR Sync endpoint
    Route::get('patient/clinical-story', [\App\Http\Controllers\API\WeCareIntegrationController::class, 'getClinicalStory']);

    // Sync callbacks from WeCare247
    Route::post('sync/patient-linked', [\App\Http\Controllers\API\WeCareIntegrationController::class, 'patientLinked']);
    Route::post('sync/patient-unlink', [\App\Http\Controllers\API\WeCareIntegrationController::class, 'patientUnlink']);
    Route::post('sync/encounter', [\App\Http\Controllers\API\WeCareIntegrationController::class, 'ingestEncounter']);
});

/*
|--------------------------------------------------------------------------
| CoreHMS SuperAdmin Backup & Management Endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('corehms')->middleware(['auth.basic.sha256'])->group(function () {
    Route::get('backups/status', [\App\Http\Controllers\API\CoreHmsBackupApiController::class, 'status']);
    Route::get('backups', [\App\Http\Controllers\API\CoreHmsBackupApiController::class, 'list']);
    Route::post('backups/generate', [\App\Http\Controllers\API\CoreHmsBackupApiController::class, 'generate']);
    Route::get('backups/{filename}/download', [\App\Http\Controllers\API\CoreHmsBackupApiController::class, 'download']);
});

/*
|--------------------------------------------------------------------------
| Mobile App API Routes
|--------------------------------------------------------------------------
*/

// Public — no auth required (called before login)
Route::prefix('mobile')->middleware('throttle:10,1')->group(function () {
    Route::get('instance-info', [MobileAuthController::class, 'instanceInfo']);
    Route::post('staff/login', [MobileAuthController::class, 'staffLogin']);
    Route::post('patient/login', [MobileAuthController::class, 'patientLogin']);
});

// Protected — requires Sanctum token
Route::prefix('mobile')->middleware('auth:sanctum')->group(function () {
    Route::post('logout', [MobileAuthController::class, 'logout']);
    // Native staff client navigation: web sidebar sections the user may use
    Route::get('staff/workspaces', [MobileAuthController::class, 'staffWorkspaces']);
    Route::get('staff/settings', [MobileAuthController::class, 'staffSettings']);
    // Global search (the web top bar's Ctrl+K): same controller, same role/permission filtering
    Route::get('staff/search', [\App\Http\Controllers\RouteSearchController::class, 'search'])->middleware('native.workspace:global');
    // App → web hand-off: one-time code that opens a web page already signed in (WebHandoffService)
    Route::post('staff/web-handoff', [\App\Http\Controllers\Auth\WebHandoffController::class, 'issue'])->middleware(['native.workspace:global', 'throttle:20,1']);
});

/*
|--------------------------------------------------------------------------
| Mobile Doctor Routes — Encounter & Queue Management
|--------------------------------------------------------------------------
| These routes power the Doctor App's clinical workflow.
| Existing JSON-returning EncounterController methods are reused directly.
*/
Route::prefix('mobile/doctor')->middleware(['auth:sanctum', 'native.workspace:doctor'])->group(function () {

    // ── Queue Management ──────────────────────────────────────────
    Route::get('queues', [MobileEncounterController::class, 'queues']);

    // ── Encounter Lifecycle ───────────────────────────────────────
    Route::post('encounters/start', [MobileEncounterController::class, 'startEncounter']);
    Route::get('encounters/{encounter}', [MobileEncounterController::class, 'encounterDetail']);

    // ── Queue Stats ──────────────────────────────────────────────
    Route::get('queues/stats', [MobileEncounterController::class, 'queueStats']);

    // ── Reused: Diagnosis & Notes (already return JSON) ──────────
    Route::post('encounters/{encounter}/save-diagnosis', [EncounterController::class, 'saveDiagnosis']);
    Route::put('encounters/{encounter}/notes', [EncounterController::class, 'updateEncounterNotes']);
    Route::post('encounters/autosave', [EncounterController::class, 'autosaveNotes']);

    // ── Reused: Lab / Imaging / Prescription / Procedure saves ──
    Route::post('encounters/{encounter}/save-labs', [EncounterController::class, 'saveLabs']);
    Route::post('encounters/{encounter}/save-imaging', [EncounterController::class, 'saveImaging']);
    Route::post('encounters/{encounter}/save-prescriptions', [EncounterController::class, 'savePrescriptions']);
    Route::post('encounters/{encounter}/save-procedures', [EncounterController::class, 'saveProcedures']);

    // ── Single item add endpoints ────────────────────────────────
    Route::post('encounters/{encounter}/add-lab', [EncounterController::class, 'addSingleLabRequest']);
    Route::post('encounters/{encounter}/add-imaging', [EncounterController::class, 'addSingleImagingRequest']);
    Route::post('encounters/{encounter}/add-prescription', [EncounterController::class, 'addSinglePrescriptionRequest']);
    Route::post('encounters/{encounter}/add-procedure', [EncounterController::class, 'addSingleProcedureRequest']);

    // ── Note updates ─────────────────────────────────────────────
    Route::put('encounters/{encounter}/labs/{lab}/note', [EncounterController::class, 'updateLabNoteRequest']);
    Route::put('encounters/{encounter}/imaging/{imaging}/note', [EncounterController::class, 'updateImagingNoteRequest']);
    Route::put('encounters/{encounter}/prescriptions/{prescription}/dose', [EncounterController::class, 'updatePrescriptionDoseRequest']);

    // ── Re-prescription ──────────────────────────────────────────
    Route::get('encounters/{encounter}/recent-encounters', [EncounterController::class, 'recentEncounters']);
    Route::get('encounters/{encounter}/encounter-items/{sourceEncounter}', [EncounterController::class, 'encounterItems']);
    Route::post('encounters/{encounter}/re-prescribe', [EncounterController::class, 'rePrescribe']);

    // ── Reused: Finalize & Summary ──────────────────────────────
    Route::post('encounters/{encounter}/finalize', [EncounterController::class, 'finalizeEncounter']);
    Route::post('encounters/{encounter}/schedule-followup', [\App\Http\Controllers\DoctorAppointmentController::class, 'scheduleFollowUp']);
    Route::get('encounters/{encounter}/summary', [EncounterController::class, 'getEncounterSummary']);

    // ── Reused: Delete endpoints ────────────────────────────────
    Route::delete('encounters/{encounter}', [EncounterController::class, 'deleteEncounter']);
    Route::delete('encounters/{encounter}/labs/{lab}', [EncounterController::class, 'deleteLab']);
    Route::delete('encounters/{encounter}/imaging/{imaging}', [EncounterController::class, 'deleteImaging']);
    Route::delete('encounters/{encounter}/prescriptions/{prescription}', [EncounterController::class, 'deletePrescription']);
    Route::delete('encounters/{encounter}/procedures/{procedure}', [EncounterController::class, 'deleteProcedure']);

    // ── Reused: Procedure sub-endpoints ─────────────────────────
    Route::get('procedures/{procedure}', [EncounterController::class, 'getProcedureDetails']);
    Route::post('procedures/{procedure}/cancel', [EncounterController::class, 'cancelProcedure']);
    Route::get('procedures/{procedure}/team', [EncounterController::class, 'getProcedureTeam']);
    Route::post('procedures/{procedure}/team', [EncounterController::class, 'addProcedureTeamMember']);
    Route::delete('procedures/{procedure}/team/{member}', [EncounterController::class, 'deleteProcedureTeamMember']);
    Route::get('procedures/{procedure}/notes', [EncounterController::class, 'getProcedureNotes']);
    Route::post('procedures/{procedure}/notes', [EncounterController::class, 'addProcedureNote']);
    Route::delete('procedures/{procedure}/notes/{note}', [EncounterController::class, 'deleteProcedureNote']);

    // ── Patient History Lists (JSON versions) ───────────────────
    Route::get('patient/{patient}/encounter-history', [MobileEncounterController::class, 'encounterHistory']);
    Route::get('patient/{patient}/lab-history', [MobileEncounterController::class, 'labHistory']);
    Route::get('patient/{patient}/imaging-history', [MobileEncounterController::class, 'imagingHistory']);
    Route::get('patient/{patient}/prescription-history', [MobileEncounterController::class, 'prescriptionHistory']);
    Route::get('patient/{patient}/procedure-history', [MobileEncounterController::class, 'procedureHistory']);
    Route::get('patient/{patient}/allergies', [MobileEncounterController::class, 'getPatientAllergies']);
    Route::post('patient/{patient}/allergies', [MobileEncounterController::class, 'addPatientAllergy']);
    Route::delete('patient/{patient}/allergies/{index}', [MobileEncounterController::class, 'deletePatientAllergy']);

    // ── Admissions ───────────────────────────────────────────────
    Route::get('admissions', [MobileEncounterController::class, 'myAdmissions']);
    Route::get('patient/{id}/admissions', [\App\Http\Controllers\AdmissionModuleController::class, 'getPatientAdmissions']);

    // ── Referrals (reuse existing JSON-returning controller) ────
    Route::get('encounters/{encounter}/referrals', [SpecialistReferralController::class, 'getEncounterReferrals']);
    Route::post('encounters/{encounter}/referrals', [SpecialistReferralController::class, 'createReferral']);
    Route::put('encounters/{encounter}/referrals/{referral}', [SpecialistReferralController::class, 'updateReferral']);
    Route::delete('encounters/{encounter}/referrals/{referral}', [SpecialistReferralController::class, 'deleteReferral']);
    Route::get('encounters/{encounter}/referrals/incoming', [SpecialistReferralController::class, 'getIncomingReferrals']);
    Route::get('encounters/{encounter}/referrals/patient-all', [SpecialistReferralController::class, 'getPatientReferrals']);

    // ── Clinics, Doctors & HMOs (for form/filter dropdowns) ─────
    Route::get('clinics', [MobileEncounterController::class, 'getClinics']);
    Route::get('doctors', [MobileEncounterController::class, 'getDoctors']);
    Route::get('hmos', [MobileEncounterController::class, 'getHmos']);

    // ── Doctor Profile & Settings ───────────────────────────────
    Route::get('profile', [MobileEncounterController::class, 'doctorProfile']);
    Route::put('profile', [MobileEncounterController::class, 'updateDoctorProfile']);
    Route::post('change-password', [MobileEncounterController::class, 'changeDoctorPassword']);

    // ── My Investigations (cross-patient) ───────────────────────
    Route::get('my-investigations', [MobileEncounterController::class, 'myInvestigations']);

    // ── Search / Autocomplete (reused, already return JSON) ─────
    Route::get('search/diagnosis', [EncounterController::class, 'liveSearchReasons']);
    Route::get('search/services', [ServiceController::class, 'liveSearchServices']);
    Route::get('search/products', [ProductController::class, 'liveSearchProducts']);

    // ── Vitals (reused, already JSON-aware) ─────────────────────

    // ── Nursing Notes (mobile-friendly JSON) ────────────────────

    // ── Clinic Note Templates ───────────────────────────────────
    Route::get('clinic-note-templates', [MobileEncounterController::class, 'getClinicNoteTemplates']);

    // ── Medication Chart (reuse existing JSON controller) ────────
    // Medication administration record and intake/output actions (web: Nurse Charts tab)

    // ── Intake/Output Charts (reuse existing JSON controller) ────

    // ── Appointment Actions (reuse existing DoctorAppointmentController) ──
    Route::post('appointments/{appointment}/check-in', [\App\Http\Controllers\DoctorAppointmentController::class, 'checkIn']);
    Route::post('appointments/{appointment}/cancel', [\App\Http\Controllers\DoctorAppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/no-show', [\App\Http\Controllers\DoctorAppointmentController::class, 'markNoShow']);
    Route::post('appointments/{appointment}/reschedule', [\App\Http\Controllers\DoctorAppointmentController::class, 'reschedule']);
    Route::post('appointments/{appointment}/reassign', [\App\Http\Controllers\DoctorAppointmentController::class, 'reassignDoctor']);
    Route::get('appointments/available-slots', [\App\Http\Controllers\DoctorAppointmentController::class, 'getAvailableSlots']);

    // ── Doctor Referral Lists (mobile JSON, not DataTable) ─────
    Route::get('referrals/my-list', [MobileEncounterController::class, 'myReferralsList']);
    Route::get('referrals/all-list', [MobileEncounterController::class, 'allReferralsList']);
    Route::get('referrals/{referral}/detail', [SpecialistReferralController::class, 'getReferralDetail']);
    Route::post('referrals/{referral}/accept', [SpecialistReferralController::class, 'acceptReferral']);
    Route::post('referrals/{referral}/decline', [SpecialistReferralController::class, 'declineReferral']);

    // ── Other Admissions (hospital-wide) ─────────────────────────
    Route::get('admissions/all', [MobileEncounterController::class, 'allAdmissions']);

    // ── Queue calendar + consultation timer (My Queues / encounter header) ──
    Route::get('queue/calendar-events', [DoctorAppointmentController::class, 'getUnifiedCalendarEvents']);
    Route::get('queue/{queue}/timer/status', [DoctorAppointmentController::class, 'getTimerStatus']);
    Route::post('queue/{queue}/timer/start', [DoctorAppointmentController::class, 'startTimer']);
    Route::post('queue/{queue}/timer/pause', [DoctorAppointmentController::class, 'pauseTimer']);

    // ── Clinical notes: diagnosis favourites, AI note polish ──
    Route::get('diagnosis-favorites', [DiagnosisFavoriteController::class, 'index']);
    Route::post('diagnosis-favorites', [DiagnosisFavoriteController::class, 'store']);
    Route::delete('diagnosis-favorites/{id}', [DiagnosisFavoriteController::class, 'destroy']);

    // ── Clinical story, treatment plans, care plan (non-pharm) ──
    Route::get('encounters/{encounter}/clinical-story/timeline', [EncounterController::class, 'getClinicalStoryTimeline']);
    Route::get('encounters/{encounter}/clinical-story', [EncounterController::class, 'getPatientClinicalStory']);
    Route::get('patient/{patient}/treatment-plans', [TreatmentPlanController::class, 'forPatient']);
    Route::post('encounters/{encounter}/apply-treatment-plan', [TreatmentPlanController::class, 'applyToEncounter']);
    Route::post('treatment-plans/from-current', [TreatmentPlanController::class, 'fromCurrent']);
    Route::post('encounters/{encounter}/apply-combo', [EncounterController::class, 'applyCombo']);
    Route::post('patient/{patient}/treatment-plans', [TreatmentPlanController::class, 'storeForPatient']);
    Route::put('treatment-plans/{treatmentPlan}', [TreatmentPlanController::class, 'update']);
    Route::put('treatment-plans/{treatmentPlan}/progress', [TreatmentPlanController::class, 'updateProgress']);
    Route::put('treatment-plans/{treatmentPlan}/retire', [TreatmentPlanController::class, 'retire']);
    Route::get('treatment-plans/{treatmentPlan}/linked-items', [TreatmentPlanController::class, 'linkedItems']);
    Route::post('treatment-plans/{treatmentPlan}/attach-note', [TreatmentPlanController::class, 'attachNote']);
    Route::get('non-pharm-orders/patient/{patient_id}', [NonPharmOrderController::class, 'getPatientOrders']);
    Route::post('non-pharm-orders', [NonPharmOrderController::class, 'store']);
    Route::post('non-pharm-orders/{id}/complete', [NonPharmOrderController::class, 'complete']);
    Route::delete('non-pharm-orders/{id}', [NonPharmOrderController::class, 'destroy']);

    // ── Results: viewed tracking ──
    Route::post('result-views', [ResultViewController::class, 'store']);
    Route::get('result-views/unviewed-counts/{patient_id}', [ResultViewController::class, 'unviewedCounts']);

    // ── Result entry from the encounter (perform / enter / edit), same as the web doctor page ──
    Route::get('patient/{patient_id}/investigation-actions', [EncounterController::class, 'investigationHistoryList']);
    Route::get('patient/{patient_id}/imaging-actions', [EncounterController::class, 'imagingHistoryList']);
    Route::get('lab/requests/{id}', [LabWorkbenchController::class, 'getLabRequest']);
    Route::get('lab/requests/{id}/attachments', [LabWorkbenchController::class, 'getRequestAttachments']);
    Route::post('lab/record-billing', [LabWorkbenchController::class, 'recordBilling']);
    Route::post('lab/claim-self-perform', [LabWorkbenchController::class, 'claimSelfPerform']);
    Route::post('lab/save-result', [LabWorkbenchController::class, 'saveResult']);
    Route::get('imaging/requests/{id}', [ImagingWorkbenchController::class, 'getImagingRequest']);
    Route::get('imaging/requests/{id}/attachments', [ImagingWorkbenchController::class, 'getRequestAttachments']);
    Route::post('imaging/record-billing', [ImagingWorkbenchController::class, 'recordBilling']);
    Route::post('imaging/claim-self-perform', [ImagingWorkbenchController::class, 'claimSelfPerform']);
    Route::post('imaging/save-result', [ImagingWorkbenchController::class, 'saveResult']);
    Route::get('nhmis/service-mapping/{serviceId}', [NhmisWorkbenchController::class, 'getMappingForService']);

    // ── Patient profiles (saved questionnaires) and inpatient discharge ──
    Route::get('patient/{patient_id}/profiles', [PatientProfileController::class, 'listPatientForm']);

    // ── Medical reports (web: Report Builder) ──
    Route::get('patient/{patient_id}/medical-reports', [MedicalReportController::class, 'listByPatient']);
    Route::get('patient/{patient_id}/report-data', [MedicalReportController::class, 'getPatientData']);
    Route::post('medical-reports', [MedicalReportController::class, 'store']);
    Route::get('medical-reports/{medical_report}', [MedicalReportController::class, 'show']);
    Route::put('medical-reports/{medical_report}', [MedicalReportController::class, 'update']);
    Route::post('medical-reports/{medical_report}/finalize', [MedicalReportController::class, 'finalize']);
    Route::delete('medical-reports/{medical_report}', [MedicalReportController::class, 'destroy']);
    Route::post('admissions/{admission_req_id}/discharge', [AdmissionRequestController::class, 'dischargePatientApi']);
});

/*
|--------------------------------------------------------------------------
| Native Staff Client — Reception / Health Records workspace
|--------------------------------------------------------------------------
| Thin route wrappers: every route points at the same ReceptionWorkbenchController
| method the web workbench uses (routes/reception_workbench.php). No logic lives here.
| Guard = the web sidebar's Health Records guard (WorkspaceRegistry 'reception').
*/
/*
|--------------------------------------------------------------------------
| Charting shared by the Doctor, Nursing and Maternity workspaces
|--------------------------------------------------------------------------
| Vitals, nursing notes, the medication administration record and intake/output are the same
| controllers on every web workbench, so one native client path serves all three. The path keeps
| the `mobile/doctor` prefix the app already calls.
*/
Route::prefix('mobile/doctor')->middleware(['auth:sanctum', 'native.workspace:doctor|nursing|maternity'])->group(function () {
    // AI scribe, patient briefing and note polish: LlmController serves the doctor, nursing and maternity studios
    Route::post('llm/polish-note', [LlmController::class, 'polishNote']);
    Route::post('llm/patient-summary', [LlmController::class, 'generatePatientSummary']);
    Route::post('llm/clinical-scribe/process', [LlmController::class, 'processScribeSession']);
    Route::post('llm/clinical-scribe/convert-format', [LlmController::class, 'convertFormat']);
    Route::post('llm/clinical-scribe/sign', [LlmController::class, 'signScribeSession']);
    // Treatment plan templates the nurse and maternity order sets pick from
    Route::get('treatment-plans', [TreatmentPlanController::class, 'index']);
    Route::get('treatment-plans/{treatmentPlan}', [TreatmentPlanController::class, 'show']);
    // Service combos (bundles): what was applied, and removing a bundle or one of its items
    Route::get('service-combo/bundles', [\App\Http\Controllers\API\MobileBundleController::class, 'forPatient']);
    Route::post('service-combo/remove-bundle', [EncounterController::class, 'removeBundleGeneric']);
    Route::post('service-combo/remove-item', [EncounterController::class, 'removeBundleItemGeneric']);
    // Clinical story by patient (the web nursing and maternity pages include the same partial)
    Route::get('patients/{patient}/clinical-story', [EncounterController::class, 'getPatientClinicalStoryFallback'])->whereNumber('patient');
    Route::get('patients/{patient}/clinical-story/timeline', [EncounterController::class, 'getPatientClinicalStoryTimeline'])->whereNumber('patient');
    Route::post('vitals', [VitalSignController::class, 'store']);
    Route::get('patient/{patientId}/vitals', [NursingWorkbenchController::class, 'getPatientVitals']);
    Route::get('patient/{patientId}/nursing-notes', [MobileEncounterController::class, 'getNursingNotes']);
    Route::get('nursing-note-types', [MobileEncounterController::class, 'getNoteTypes']);
    Route::get('patient/{patient}/medication-chart', [MedicationChartController::class, 'getPatientPrescribedDrugs']);
    Route::get('patient/{patient}/medication-chart/overview', [MedicationChartController::class, 'overview']);
    Route::get('patient/{patient}/medication-chart/calendar/{medication}/{start_date?}', [MedicationChartController::class, 'calendar']);
    Route::get('patient/{patient}/medication-chart/direct-calendar', [MedicationChartController::class, 'directCalendar']);
    Route::post('patient/{patient}/medication-chart/administer-direct', [MedicationChartController::class, 'administerDirect']);
    Route::post('patient/{patient}/dismiss-prescription', [MedicationChartController::class, 'nurseDismissPrescription']);
    Route::post('medication-chart/schedule', [MedicationChartController::class, 'storeTiming']);
    Route::post('medication-chart/administer', [MedicationChartController::class, 'administer']);
    Route::post('medication-chart/edit', [MedicationChartController::class, 'editAdministration']);
    Route::post('medication-chart/delete', [MedicationChartController::class, 'deleteAdministration']);
    Route::post('medication-chart/remove-schedule', [MedicationChartController::class, 'removeSchedule']);
    Route::post('medication-chart/discontinue', [MedicationChartController::class, 'discontinue']);
    Route::post('medication-chart/resume', [MedicationChartController::class, 'resume']);
    Route::get('patient/{patient}/intake-output/logs/{period}', [\App\Http\Controllers\IntakeOutputChartController::class, 'periodLogs']);
    Route::post('intake-output/start', [\App\Http\Controllers\IntakeOutputChartController::class, 'startPeriod']);
    Route::post('intake-output/record', [\App\Http\Controllers\IntakeOutputChartController::class, 'storeRecord']);
    Route::post('intake-output/end', [\App\Http\Controllers\IntakeOutputChartController::class, 'endPeriod']);
    Route::delete('intake-output/record/{record}', [\App\Http\Controllers\IntakeOutputChartController::class, 'deleteRecord']);
    Route::get('patient/{patient}/intake-output', [\App\Http\Controllers\IntakeOutputChartController::class, 'index']);
});

Route::prefix('mobile/reception')->middleware(['auth:sanctum', 'native.workspace:reception'])->group(function () {
    Route::get('today-stats', [ReceptionWorkbenchController::class, 'getTodayStats']);
    Route::get('queue-counts', [ReceptionWorkbenchController::class, 'getQueueCounts']);
    Route::get('queue-list', [ReceptionWorkbenchController::class, 'getQueueList']);
    Route::get('search-patients', [ReceptionWorkbenchController::class, 'searchPatients']);
    Route::get('patient/next-file-number', [ReceptionWorkbenchController::class, 'getNextFileNumber']);
    Route::post('patient/check-file-number', [ReceptionWorkbenchController::class, 'checkFileNumberExists']);
    Route::post('patient/check-duplicate', [ReceptionWorkbenchController::class, 'checkDuplicatePatient']);
    Route::post('patient/quick-register', [ReceptionWorkbenchController::class, 'quickRegister']);
    Route::get('patient/{id}', [ReceptionWorkbenchController::class, 'getPatient']);
    Route::get('patient/{id}/visits', [ReceptionWorkbenchController::class, 'getVisitHistory']);
    Route::get('patient/{id}/queue', [ReceptionWorkbenchController::class, 'getPatientQueueEntries']);
    Route::put('patient/{id}/update', [ReceptionWorkbenchController::class, 'updatePatient']);
    Route::get('clinics', [ReceptionWorkbenchController::class, 'getClinics']);
    Route::get('clinics/{id}/doctors', [ReceptionWorkbenchController::class, 'getDoctorsByClinic']);
    Route::get('services/consultation', [ReceptionWorkbenchController::class, 'getConsultationServices']);
    Route::get('hmos', [ReceptionWorkbenchController::class, 'getHmos']);
    Route::post('tariff-preview', [ReceptionWorkbenchController::class, 'getTariffPreview']);
    Route::post('book-consultation', [ReceptionWorkbenchController::class, 'bookConsultation']);

    // Page context the web workbench is rendered with (billing permission, registration fees)
    Route::get('context', [MobileReceptionController::class, 'context']);

    // Walk-in sales (Walk-in Sales tab)
    Route::get('services/lab', [ReceptionWorkbenchController::class, 'getLabServices']);
    Route::get('services/imaging', [ReceptionWorkbenchController::class, 'getImagingServices']);
    Route::get('services/procedure', [ReceptionWorkbenchController::class, 'getProcedureServices']);
    Route::get('products', [ReceptionWorkbenchController::class, 'getProducts']);
    Route::post('book-walkin', [ReceptionWorkbenchController::class, 'bookWalkinServices']);
    Route::get('patient/{id}/recent-requests', [ReceptionWorkbenchController::class, 'getRecentRequests']);

    // Service Requests tab
    Route::get('patient/{id}/service-requests', [ReceptionWorkbenchController::class, 'getServiceRequests']);
    Route::get('patient/{id}/service-requests-stats', [ReceptionWorkbenchController::class, 'getServiceRequestsStats']);
    Route::get('request/{type}/{id}/details', [ReceptionWorkbenchController::class, 'getRequestDetails']);
    Route::delete('request/{type}/{id}/discard', [ReceptionWorkbenchController::class, 'discardServiceRequest']);

    // HMO Pending Validation
    Route::get('hmo-pending-validation', [ReceptionWorkbenchController::class, 'getHmoPendingValidation']);
    Route::get('hmo-pending-count', [ReceptionWorkbenchController::class, 'getHmoPendingCount']);
    Route::post('hmo-validate/{id}', [ReceptionWorkbenchController::class, 'validateHmoRequest']);
    Route::post('hmo-batch-validate', [ReceptionWorkbenchController::class, 'batchValidateHmo']);

    // Pending referrals desk
    Route::get('referrals/pending', [SpecialistReferralController::class, 'getPendingReferrals']);
    Route::get('referrals/pending-count', [SpecialistReferralController::class, 'getPendingReferralCount']);
    Route::get('referrals/{referral}/detail', [SpecialistReferralController::class, 'getReferralDetail']);
    Route::post('referrals/{referral}/book', [SpecialistReferralController::class, 'bookReferralAppointment']);
    Route::post('referrals/{referral}/refer-out', [SpecialistReferralController::class, 'referOut']);
    Route::post('referrals/{referral}/cancel', [SpecialistReferralController::class, 'cancelReferral']);
    Route::post('referrals/{referral}/decline', [SpecialistReferralController::class, 'declineReferral']);

    // Checkout and cashier (web: payment modal + My Transactions); payment gated like the web page
    Route::post('process-payment', [MobileReceptionController::class, 'processPayment']);
    Route::get('my-activity', [ProcessedTransactionsController::class, 'activity']); // what I did, by stage
    Route::get('my-transactions', [BillingWorkbenchController::class, 'getMyTransactions']);
    // The web transactions console's Manage tab and receipts, on the reception page too
    Route::get('manage-transactions', [BillingWorkbenchController::class, 'getManageTransactions']);
    Route::get('payment/{id}/details', [BillingWorkbenchController::class, 'getPaymentDetails']);
    Route::post('print-receipt', [BillingWorkbenchController::class, 'printReceipt']);
    Route::post('void-transaction', [BillingWorkbenchController::class, 'voidTransaction']);
    Route::post('adjust-payment', [BillingWorkbenchController::class, 'adjustPaymentMethod']);
    Route::get('banks/active', [BankController::class, 'getActiveBanks']);
    Route::get('billing/staff-list', [BillingWorkbenchController::class, 'getStaffList']);
    Route::get('billing/organization-list', [BillingWorkbenchController::class, 'getOrganizationList']);
    Route::get('patient/{id}/account-summary', [BillingWorkbenchController::class, 'getPatientAccountSummary']);

});

/*
|--------------------------------------------------------------------------
| Native Staff Client — Billing workspace
|--------------------------------------------------------------------------
| Same BillingWorkbenchController (and Reception walk-in) methods the web billing
| workbench calls. Guard = the web sidebar's Billing guard (WorkspaceRegistry 'billing').
*/
Route::prefix('mobile/billing')->middleware(['auth:sanctum', 'native.workspace:billing'])->group(function () {
    // Desk: shift, payment queue, search
    Route::get('active-shift', [BillingWorkbenchController::class, 'getActiveBillingShift']);
    Route::post('start-shift', [BillingWorkbenchController::class, 'startBillingShift']);
    Route::post('end-shift', [BillingWorkbenchController::class, 'endBillingShift']);
    Route::get('queue-counts', [BillingWorkbenchController::class, 'getQueueCounts']);
    Route::get('payment-queue', [BillingWorkbenchController::class, 'getPaymentQueue']);
    Route::get('search-patients', [BillingWorkbenchController::class, 'searchPatients']);

    // Patient: items to pay, receipts, account, admissions
    Route::get('patient/{id}/billing-data', [BillingWorkbenchController::class, 'getPatientBillingData']);
    Route::get('patient/{id}/billing-defaults', [BillingWorkbenchController::class, 'getPatientBillingDefaults']);
    Route::get('patient/{id}/receipts', [BillingWorkbenchController::class, 'getPatientReceipts']);
    Route::get('patient/{id}/transactions', [BillingWorkbenchController::class, 'getPatientTransactions']);
    Route::get('patient/{id}/account-summary', [BillingWorkbenchController::class, 'getPatientAccountSummary']);
    Route::get('patient/{id}/account-transactions', [BillingWorkbenchController::class, 'getAccountTransactions']);
    Route::post('patient/{id}/generate-statement', [BillingWorkbenchController::class, 'generateStatement']);
    Route::get('patient/{id}/admissions', [BillingWorkbenchController::class, 'getAdmissionHistory']);
    Route::get('admission/{id}/bill', [BillingWorkbenchController::class, 'getAdmissionBillDetail']);

    // Payments, receipts and account transactions
    Route::post('process-payment', [BillingWorkbenchController::class, 'processPayment']);
    Route::get('payment/{id}/details', [BillingWorkbenchController::class, 'getPaymentDetails']);
    Route::post('print-receipt', [BillingWorkbenchController::class, 'printReceipt']);
    Route::post('print-invoice', [BillingWorkbenchController::class, 'printInvoice']);
    Route::get('print-deposit-receipt/{id}', [BillingWorkbenchController::class, 'printDepositReceipt']);
    Route::post('create-account', [BillingWorkbenchController::class, 'createPatientAccount']);
    Route::post('make-deposit', [BillingWorkbenchController::class, 'makeAccountDeposit']);
    Route::post('account-transaction', [BillingWorkbenchController::class, 'processAccountTransaction']);
    Route::post('void-transaction', [BillingWorkbenchController::class, 'voidTransaction']);
    Route::post('adjust-payment', [BillingWorkbenchController::class, 'adjustPaymentMethod']);
    Route::get('my-activity', [ProcessedTransactionsController::class, 'activity']);
    Route::get('my-transactions', [BillingWorkbenchController::class, 'getMyTransactions']);
    Route::get('manage-transactions', [BillingWorkbenchController::class, 'getManageTransactions']);

    // Lookups for checkout
    Route::get('banks/active', [BankController::class, 'getActiveBanks']);
    Route::get('staff-list', [BillingWorkbenchController::class, 'getStaffList']);
    Route::get('organization-list', [BillingWorkbenchController::class, 'getOrganizationList']);

    // Walk-in sales (the web billing page embeds the same walk-in module)
    Route::get('context', [MobileReceptionController::class, 'context']);
    Route::get('services/lab', [ReceptionWorkbenchController::class, 'getLabServices']);
    Route::get('services/imaging', [ReceptionWorkbenchController::class, 'getImagingServices']);
    Route::get('services/procedure', [ReceptionWorkbenchController::class, 'getProcedureServices']);
    Route::get('products', [ReceptionWorkbenchController::class, 'getProducts']);
    Route::post('tariff-preview', [ReceptionWorkbenchController::class, 'getTariffPreview']);
    Route::post('book-walkin', [ReceptionWorkbenchController::class, 'bookWalkinServices']);
    Route::get('patient/{id}/recent-requests', [ReceptionWorkbenchController::class, 'getRecentRequests']);
    Route::delete('request/{type}/{id}/discard', [ReceptionWorkbenchController::class, 'discardServiceRequest']);
    Route::get('request/{type}/{id}/details', [ReceptionWorkbenchController::class, 'getRequestDetails']);
});

/*
|--------------------------------------------------------------------------
| Native Staff Client — Pharmacy workspace
|--------------------------------------------------------------------------
| The same PharmacyWorkbench / Returns / Damages / Reports controller methods the web pharmacy
| workbench calls. Guard = the web sidebar's Pharmacy guard (WorkspaceRegistry 'pharmacy').
| `context` is the only adapter (it replaces the page-render data: active store and candidates).
*/
Route::prefix('mobile/pharmacy')->middleware(['auth:sanctum', 'native.workspace:pharmacy'])->group(function () {
    // Desk: active store, queue, search
    Route::get('context', [MobilePharmacyController::class, 'context']);
    Route::get('stores', [PharmacyWorkbenchController::class, 'getStores']);
    Route::get('queue-counts', [PharmacyWorkbenchController::class, 'getQueueCounts']);
    Route::get('prescription-queue', [PharmacyWorkbenchController::class, 'getPrescriptionQueue']);
    Route::get('search-patients', [PharmacyWorkbenchController::class, 'searchPatients']);
    Route::get('search-products', [PharmacyWorkbenchController::class, 'searchProducts']);

    // Patient: prescriptions in their stages, history
    Route::get('patient/{id}/prescription-data', [PharmacyWorkbenchController::class, 'getPatientPrescriptionData']);
    Route::get('patient/{id}/dispensing-history', [PharmacyWorkbenchController::class, 'getPatientDispensingHistory']);

    // Billing stage (hands the charge to the cashier), dismiss, new request, bundles, slip
    Route::post('bill', [PharmacyWorkbenchController::class, 'billPrescriptions']);
    Route::post('dismiss', [PharmacyWorkbenchController::class, 'dismissPrescriptions']);
    Route::get('dismissed/{patientId?}', [PharmacyWorkbenchController::class, 'getDismissedPrescriptions'])->whereNumber('patientId');
    Route::post('restore-dismissed', [PharmacyWorkbenchController::class, 'restoreDismissedPrescriptions']);
    Route::post('create-request', [PharmacyWorkbenchController::class, 'createPrescriptionRequest']);
    Route::post('apply-combo', [PharmacyWorkbenchController::class, 'pharmacyApplyCombo']);
    Route::post('remove-bundle', [PharmacyWorkbenchController::class, 'removeBundle']);
    Route::post('remove-bundle-item', [PharmacyWorkbenchController::class, 'removeBundleItem']);
    Route::post('print-prescription-slip', [PharmacyWorkbenchController::class, 'printPrescriptionSlip']);
    Route::post('prescription/{id}/adjust-price', [PharmacyWorkbenchController::class, 'adjustPrice']);
    Route::post('prescription/{id}/adapt', [PharmacyWorkbenchController::class, 'adaptPrescription']);
    Route::post('prescription/{id}/adjust-quantity', [PharmacyWorkbenchController::class, 'adjustBilledQuantity']);

    // Dispensing: stock by store, batches (FIFO), cart validation, dispense
    Route::get('product/{id}/stock', [PharmacyWorkbenchController::class, 'getProductStockByStore']);
    Route::get('product-batches', [PharmacyWorkbenchController::class, 'getProductBatches']);
    Route::get('batch-suggestion', [PharmacyWorkbenchController::class, 'getBatchFulfillmentSuggestion']);
    Route::post('validate-cart-stock', [PharmacyWorkbenchController::class, 'validateCartStock']);
    Route::post('dispense', [PharmacyWorkbenchController::class, 'dispenseMedication']);
    Route::post('dispense-with-batch', [PharmacyWorkbenchController::class, 'dispenseMedicationWithBatch']);
    Route::post('dispense-free-form', [PharmacyWorkbenchController::class, 'dispenseFreeFormMedication']);
    Route::get('my-transactions', [PharmacyWorkbenchController::class, 'getMyTransactions']);
    Route::get('my-activity', [ProcessedTransactionsController::class, 'activity']); // what I did, by stage

    // Returns
    Route::get('returns', [PharmacyReturnsController::class, 'datatables']);
    Route::get('returns/stats', [PharmacyReturnsController::class, 'index']); // send stats_only=1
    Route::get('returns/search-dispensed', [PharmacyReturnsController::class, 'searchDispensedItems']);
    Route::post('returns', [PharmacyReturnsController::class, 'store']);
    Route::post('returns/bulk-store', [PharmacyReturnsController::class, 'bulkStore']);
    Route::post('returns/bulk-approve', [PharmacyReturnsController::class, 'bulkApprove']);
    Route::post('returns/bulk-reject', [PharmacyReturnsController::class, 'bulkReject']);
    Route::get('returns/{id}', [PharmacyReturnsController::class, 'show']);
    Route::post('returns/{id}/approve', [PharmacyReturnsController::class, 'approve']);
    Route::post('returns/{id}/reject', [PharmacyReturnsController::class, 'reject']);
    Route::post('returns/{id}/process-refund', [PharmacyReturnsController::class, 'processRefund']);

    // Damages
    Route::get('damages', [PharmacyDamagesController::class, 'datatables']);
    Route::get('damages/stats', [PharmacyDamagesController::class, 'index']); // send stats_only=1
    Route::get('damages/search-products', [PharmacyDamagesController::class, 'searchProducts']);
    Route::get('damages/batches', [PharmacyDamagesController::class, 'getBatches']);
    Route::post('damages', [PharmacyDamagesController::class, 'store']);
    Route::get('damages/{id}', [PharmacyDamagesController::class, 'show']);
    Route::post('damages/{id}/approve', [PharmacyDamagesController::class, 'approve']);
    Route::post('damages/{id}/reject', [PharmacyDamagesController::class, 'reject']);

    // Stock reports
    Route::get('stock/overview', [PharmacyReportsController::class, 'stockReport']);
    Route::get('stock/by-store', [PharmacyReportsController::class, 'stockByStore']);
    Route::get('stock/by-category', [PharmacyReportsController::class, 'stockByCategory']);
    Route::get('stock/valuation', [PharmacyReportsController::class, 'valuationReport']);
    Route::get('stock/expiring', [PharmacyReportsController::class, 'expiringStock']);
    Route::get('stock/movement', [PharmacyReportsController::class, 'movementAnalysis']);
    Route::get('stock/expiring-batches', [PharmacyWorkbenchController::class, 'getExpiringBatches']);
    Route::get('stock/low-stock', [PharmacyWorkbenchController::class, 'getLowStockItems']);

    // Summary reports and their filters
    Route::get('filters/pharmacists', [PharmacyWorkbenchController::class, 'getPharmacists']);
    Route::get('filters/hmos', [PharmacyWorkbenchController::class, 'getHmosForFilter']);
    Route::get('filters/doctors', [PharmacyWorkbenchController::class, 'getDoctorsForFilter']);
    Route::get('filters/categories', [PharmacyWorkbenchController::class, 'getProductCategories']);
    Route::get('reports/statistics', [PharmacyWorkbenchController::class, 'getReportStatistics']);
    Route::get('reports/dispensing', [PharmacyWorkbenchController::class, 'getDispensingReport']);
    Route::get('reports/revenue', [PharmacyWorkbenchController::class, 'getRevenueReport']);
    Route::get('reports/stock', [PharmacyWorkbenchController::class, 'getStockReport']);
    Route::get('reports/performance', [PharmacyWorkbenchController::class, 'getPerformanceReport']);
    Route::get('reports/hmo-claims', [PharmacyWorkbenchController::class, 'getHmoClaimsReport']);
    Route::get('reports/top-products', [PharmacyWorkbenchController::class, 'getTopProducts']);
    Route::get('reports/payment-methods', [PharmacyWorkbenchController::class, 'getPaymentMethodsBreakdown']);
    Route::get('reports/executive-summary', [PharmacyWorkbenchController::class, 'getExecutiveSummary']);

    // Pharmacy section's Stores, Products and Categories (the web's same pages): reads are adapters, writes the web controllers'
    $mm = \App\Http\Controllers\API\MobileStoreMastersController::class;
    Route::get('masters/options', [$mm, 'options']);
    Route::get('masters/{kind}', [$mm, 'index'])->where('kind', 'stores|products|categories');
    Route::get('masters/{kind}/{id}', [$mm, 'show'])->where('kind', 'stores|products|categories')->whereNumber('id');
    Route::post('masters/stores', [\App\Http\Controllers\StoreController::class, 'store']);
    Route::put('masters/stores/{id}', [\App\Http\Controllers\StoreController::class, 'update'])->whereNumber('id');
    Route::post('masters/products', [\App\Http\Controllers\ProductController::class, 'store']);
    Route::put('masters/products/{id}', [\App\Http\Controllers\ProductController::class, 'update'])->whereNumber('id');
    Route::post('masters/products/{id}/toggle-status', [\App\Http\Controllers\ProductController::class, 'toggleStatus'])->whereNumber('id');
    Route::post('masters/categories', [\App\Http\Controllers\ProductCategoryController::class, 'store']);
    Route::put('masters/categories/{id}', [\App\Http\Controllers\ProductCategoryController::class, 'update'])->whereNumber('id');
    Route::delete('masters/categories/{id}', [\App\Http\Controllers\ProductCategoryController::class, 'destroy'])->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Mobile Maternity Routes — wraps the web Maternity workbench
|--------------------------------------------------------------------------
| Every route points at the MaternityWorkbenchController method the web workbench calls
| (queues, enrolment, ANC, delivery, partograph, babies, postnatal, immunization, orders).
| Printable cards, reports and outreach reports stay on the web.
*/
Route::prefix('mobile/maternity')->middleware(['auth:sanctum', 'native.workspace:maternity'])->group(function () {
    Route::get('context', [\App\Http\Controllers\API\MobileNursingController::class, 'context']);
    // Reports, outreach and the printable cards (shown in the app as documents)
    Route::get('enrollment/{id}/print-anc-card', [MaternityWorkbenchController::class, 'printAncCard'])->whereNumber('id');
    Route::get('enrollment/{id}/print-road-health-card', [MaternityWorkbenchController::class, 'printRoadHealthCard'])->whereNumber('id');
    Route::get('outreach-reports', [MaternityWorkbenchController::class, 'getOutreachSessionsReportMaternity']);
    Route::get('outreach-session-details/{sessionId}', [MaternityWorkbenchController::class, 'getOutreachSessionDetailsMaternity'])->whereNumber('sessionId');
    Route::get('outreach-session/{sessionId}', [MaternityWorkbenchController::class, 'getOutreachSessionDetailsMaternity'])->whereNumber('sessionId');
    Route::get('outreach-sessions-report', [MaternityWorkbenchController::class, 'getOutreachSessionsReportMaternity']);
    Route::get('outreach-store-inventory', [MaternityWorkbenchController::class, 'getOutreachStoreInventoryMaternity']);
    Route::get('reports/admissions-stats', [MaternityWorkbenchController::class, 'getAdmissionsReportStats']);
    Route::get('reports/anc-defaulters', [MaternityWorkbenchController::class, 'getAncDefaulters']);
    Route::get('reports/delivery-stats', [MaternityWorkbenchController::class, 'getDeliveryStats']);
    Route::get('reports/high-risk-register', [MaternityWorkbenchController::class, 'getHighRiskRegister']);
    Route::get('reports/immunization-coverage', [MaternityWorkbenchController::class, 'getImmunizationCoverage']);
    Route::get('reports/summary', [MaternityWorkbenchController::class, 'getReportsSummary']);
    Route::post('outreach-tally', [MaternityWorkbenchController::class, 'saveOutreachTallyMaternity']);
    Route::post('save-outreach-tally', [MaternityWorkbenchController::class, 'saveOutreachTallyMaternity']);
    // Same product search the web maternity workbench uses for prescriptions
    Route::get('search-products', [ProductController::class, 'liveSearchProducts']);

    // Billing tab: the web maternity page shares billing-shared.js and calls the nursing billing routes
    Route::get('patient/{patientId}/pending-bills', [NursingWorkbenchController::class, 'getPendingBills'])->whereNumber('patientId');
    Route::get('patient/{patientId}/service-requests', [NursingWorkbenchController::class, 'getServiceRequests'])->whereNumber('patientId');
    Route::get('patient/{patientId}/service-requests-stats', [NursingWorkbenchController::class, 'getServiceRequestsStats'])->whereNumber('patientId');
    Route::post('billing/add-service', [NursingWorkbenchController::class, 'addServiceBill']);
    Route::post('billing/add-lab-bill', [NursingWorkbenchController::class, 'addLabBill']);
    Route::post('billing/add-imaging-bill', [NursingWorkbenchController::class, 'addImagingBill']);
    Route::post('billing/add-consumable-bill', [NursingWorkbenchController::class, 'addConsumableBill']);
    Route::delete('billing/remove-bill/{id}', [NursingWorkbenchController::class, 'removeBillItem'])->whereNumber('id');
    Route::get('product-stock/{productId}/store/{storeId}', [NursingWorkbenchController::class, 'getProductStockByStore'])->whereNumber('productId')->whereNumber('storeId');
    Route::post('administer-from-schedule', [MaternityWorkbenchController::class, 'administerFromScheduleMaternity']);
    Route::put('anc-visit/{id}', [MaternityWorkbenchController::class, 'updateAncVisit'])->whereNumber('id');
    Route::get('anc-visit/{id}', [MaternityWorkbenchController::class, 'getAncVisitDetail'])->whereNumber('id');
    Route::delete('anc-visit/{id}', [MaternityWorkbenchController::class, 'deleteAncVisit'])->whereNumber('id');
    Route::get('baby/{id}', [MaternityWorkbenchController::class, 'getBabyDetails'])->whereNumber('id');
    Route::put('baby/{id}', [MaternityWorkbenchController::class, 'updateBaby'])->whereNumber('id');
    Route::delete('baby/{id}', [MaternityWorkbenchController::class, 'deleteBaby'])->whereNumber('id');
    Route::post('baby/{id}/generate-schedule', [MaternityWorkbenchController::class, 'generateBabySchedule'])->whereNumber('id');
    Route::post('baby/{id}/growth', [MaternityWorkbenchController::class, 'saveGrowthRecord'])->whereNumber('id');
    Route::get('baby/{id}/growth-chart', [MaternityWorkbenchController::class, 'getGrowthChartData'])->whereNumber('id');
    Route::post('baby/{id}/immunization', [MaternityWorkbenchController::class, 'administerImmunization'])->whereNumber('id');
    Route::post('baby/{id}/immunization-from-schedule', [MaternityWorkbenchController::class, 'administerFromSchedule'])->whereNumber('id');
    Route::get('baby/{id}/immunization-history', [MaternityWorkbenchController::class, 'getBabyImmunizationHistory'])->whereNumber('id');
    Route::get('baby/{id}/immunization-schedule', [MaternityWorkbenchController::class, 'getImmunizationSchedule'])->whereNumber('id');
    Route::post('baby/{id}/mark-deceased', [MaternityWorkbenchController::class, 'markBabyDeceased'])->whereNumber('id');
    Route::get('baby/{id}/schedule', [MaternityWorkbenchController::class, 'getBabySchedule'])->whereNumber('id');
    Route::put('delivery/{id}', [MaternityWorkbenchController::class, 'updateDeliveryRecord'])->whereNumber('id');
    Route::get('delivery/{id}', [MaternityWorkbenchController::class, 'getDeliveryRecord'])->whereNumber('id');
    Route::delete('delivery/{id}', [MaternityWorkbenchController::class, 'deleteDeliveryRecord'])->whereNumber('id');
    Route::post('delivery/{id}/partograph', [MaternityWorkbenchController::class, 'savePartographEntry'])->whereNumber('id');
    Route::get('delivery/{id}/partograph', [MaternityWorkbenchController::class, 'getPartographEntries'])->whereNumber('id');
    Route::post('enroll', [MaternityWorkbenchController::class, 'enrollPatient']);
    Route::get('enrollment/{id}', [MaternityWorkbenchController::class, 'getEnrollment'])->whereNumber('id');
    Route::put('enrollment/{id}', [MaternityWorkbenchController::class, 'updateEnrollment'])->whereNumber('id');
    Route::post('enrollment/{id}/add-imaging', [MaternityWorkbenchController::class, 'maternityAddSingleImaging'])->whereNumber('id');
    Route::post('enrollment/{id}/add-lab', [MaternityWorkbenchController::class, 'maternityAddSingleLab'])->whereNumber('id');
    Route::post('enrollment/{id}/add-prescription', [MaternityWorkbenchController::class, 'maternityAddSinglePrescription'])->whereNumber('id');
    Route::post('enrollment/{id}/add-procedure', [MaternityWorkbenchController::class, 'maternityAddSingleProcedure'])->whereNumber('id');
    Route::post('enrollment/{id}/anc-visit', [MaternityWorkbenchController::class, 'saveAncVisit'])->whereNumber('id');
    Route::get('enrollment/{id}/anc-visits', [MaternityWorkbenchController::class, 'getAncVisits'])->whereNumber('id');
    Route::post('enrollment/{id}/apply-combo', [MaternityWorkbenchController::class, 'maternityApplyCombo'])->whereNumber('id');
    Route::post('enrollment/{id}/apply-treatment-plan', [MaternityWorkbenchController::class, 'applyMaternityTreatmentPlan'])->whereNumber('id');
    Route::get('enrollment/{id}/audit-trail', [MaternityWorkbenchController::class, 'getAuditTrail'])->whereNumber('id');
    Route::post('enrollment/{id}/baby', [MaternityWorkbenchController::class, 'registerBaby'])->whereNumber('id');
    Route::post('enrollment/{id}/delivery', [MaternityWorkbenchController::class, 'saveDeliveryRecord'])->whereNumber('id');
    Route::post('enrollment/{id}/discharge', [MaternityWorkbenchController::class, 'dischargeEnrollment'])->whereNumber('id');
    Route::get('enrollment/{id}/encounter-items/{encounterId}', [MaternityWorkbenchController::class, 'maternityEncounterItems'])->whereNumber('id')->whereNumber('encounterId');
    Route::post('enrollment/{id}/generate-mother-schedule', [MaternityWorkbenchController::class, 'generateMotherSchedule'])->whereNumber('id');
    Route::post('enrollment/{id}/imaging', [MaternityWorkbenchController::class, 'saveMaternityImaging'])->whereNumber('id');
    Route::delete('enrollment/{id}/imaging/{imaging}', [MaternityWorkbenchController::class, 'maternityRemoveSingleImaging'])->whereNumber('id');
    Route::put('enrollment/{id}/imaging/{imaging}/note', [MaternityWorkbenchController::class, 'maternityUpdateImagingNote'])->whereNumber('id');
    Route::post('enrollment/{id}/investigation', [MaternityWorkbenchController::class, 'orderInvestigation'])->whereNumber('id');
    Route::get('enrollment/{id}/investigations', [MaternityWorkbenchController::class, 'getInvestigations'])->whereNumber('id');
    Route::post('enrollment/{id}/labs', [MaternityWorkbenchController::class, 'saveMaternityLabs'])->whereNumber('id');
    Route::delete('enrollment/{id}/labs/{lab}', [MaternityWorkbenchController::class, 'maternityRemoveSingleLab'])->whereNumber('id');
    Route::put('enrollment/{id}/labs/{lab}/note', [MaternityWorkbenchController::class, 'maternityUpdateLabNote'])->whereNumber('id');
    Route::get('enrollment/{id}/maternity-partograph', [MaternityWorkbenchController::class, 'getMaternityPartographEntries'])->whereNumber('id');
    Route::post('enrollment/{id}/maternity-partograph', [MaternityWorkbenchController::class, 'saveMaternityPartographEntry'])->whereNumber('id');
    Route::put('enrollment/{id}/maternity-partograph/{entryId}', [MaternityWorkbenchController::class, 'updateMaternityPartographEntry'])->whereNumber('id')->whereNumber('entryId');
    Route::delete('enrollment/{id}/maternity-partograph/{entryId}', [MaternityWorkbenchController::class, 'deleteMaternityPartographEntry'])->whereNumber('id')->whereNumber('entryId');
    Route::post('enrollment/{id}/medical-history', [MaternityWorkbenchController::class, 'saveMedicalHistory'])->whereNumber('id');
    Route::get('enrollment/{id}/mother-immunization-history', [MaternityWorkbenchController::class, 'getMotherImmunizationHistory'])->whereNumber('id');
    Route::get('enrollment/{id}/mother-schedule', [MaternityWorkbenchController::class, 'getMotherSchedule'])->whereNumber('id');
    Route::post('enrollment/{id}/note', [MaternityWorkbenchController::class, 'saveNote'])->whereNumber('id');
    Route::get('enrollment/{id}/notes', [MaternityWorkbenchController::class, 'getNotes'])->whereNumber('id');
    Route::get('enrollment/{id}/postnatal', [MaternityWorkbenchController::class, 'getPostnatalVisits'])->whereNumber('id');
    Route::post('enrollment/{id}/postnatal', [MaternityWorkbenchController::class, 'savePostnatalVisit'])->whereNumber('id');
    Route::post('enrollment/{id}/prescriptions', [MaternityWorkbenchController::class, 'saveMaternityPrescriptions'])->whereNumber('id');
    Route::delete('enrollment/{id}/prescriptions/{prescription}', [MaternityWorkbenchController::class, 'maternityRemoveSinglePrescription'])->whereNumber('id');
    Route::put('enrollment/{id}/prescriptions/{prescription}/dose', [MaternityWorkbenchController::class, 'maternityUpdatePrescriptionDose'])->whereNumber('id');
    Route::post('enrollment/{id}/prev-pregnancy', [MaternityWorkbenchController::class, 'savePreviousPregnancy'])->whereNumber('id');
    Route::post('enrollment/{id}/procedures', [MaternityWorkbenchController::class, 'saveMaternityProcedures'])->whereNumber('id');
    Route::delete('enrollment/{id}/procedures/{procedure}', [MaternityWorkbenchController::class, 'maternityRemoveSingleProcedure'])->whereNumber('id');
    Route::post('enrollment/{id}/re-prescribe', [MaternityWorkbenchController::class, 'maternityRePrescribe'])->whereNumber('id');
    Route::get('enrollment/{id}/recent-encounters', [MaternityWorkbenchController::class, 'maternityRecentEncounters'])->whereNumber('id');
    Route::post('enrollment/{id}/remove-bundle', [MaternityWorkbenchController::class, 'removeBundle'])->whereNumber('id');
    Route::post('enrollment/{id}/remove-bundle-item', [MaternityWorkbenchController::class, 'removeBundleItem'])->whereNumber('id');
    Route::get('enrollment/{id}/timeline', [MaternityWorkbenchController::class, 'getTimeline'])->whereNumber('id');
    Route::put('medical-history/{id}', [MaternityWorkbenchController::class, 'updateMedicalHistory'])->whereNumber('id');
    Route::delete('medical-history/{id}', [MaternityWorkbenchController::class, 'deleteMedicalHistory'])->whereNumber('id');
    Route::put('note/{id}', [MaternityWorkbenchController::class, 'updateNote'])->whereNumber('id');
    Route::delete('note/{id}', [MaternityWorkbenchController::class, 'deleteNote'])->whereNumber('id');
    Route::put('partograph/{id}', [MaternityWorkbenchController::class, 'updatePartographEntry'])->whereNumber('id');
    Route::delete('partograph/{id}', [MaternityWorkbenchController::class, 'deletePartographEntry'])->whereNumber('id');
    Route::get('patient/{id}/details', [MaternityWorkbenchController::class, 'getPatientDetails'])->whereNumber('id');
    Route::post('patient/{id}/vital', [MaternityWorkbenchController::class, 'saveVital'])->whereNumber('id');
    Route::get('patient/{id}/vitals', [MaternityWorkbenchController::class, 'getPatientVitals'])->whereNumber('id');
    Route::post('patient/{patientId}/generate-schedule', [MaternityWorkbenchController::class, 'generatePatientScheduleMaternity'])->whereNumber('patientId');
    Route::get('patient/{patientId}/immunization-history', [MaternityWorkbenchController::class, 'getImmunizationHistoryByPatient'])->whereNumber('patientId');
    Route::get('patient/{patientId}/schedule', [MaternityWorkbenchController::class, 'getPatientScheduleMaternity'])->whereNumber('patientId');
    Route::put('postnatal/{id}', [MaternityWorkbenchController::class, 'updatePostnatalVisit'])->whereNumber('id');
    Route::delete('postnatal/{id}', [MaternityWorkbenchController::class, 'deletePostnatalVisit'])->whereNumber('id');
    Route::put('prev-pregnancy/{id}', [MaternityWorkbenchController::class, 'updatePreviousPregnancy'])->whereNumber('id');
    Route::delete('prev-pregnancy/{id}', [MaternityWorkbenchController::class, 'deletePreviousPregnancy'])->whereNumber('id');
    Route::get('product-batches', [MaternityWorkbenchController::class, 'getProductBatchesMaternity']);
    // Dispensing units of a product (web billing-shared.js: /products/{id}/packagings)
    Route::get('products/{product}/packagings', [ProductController::class, 'getPackagings'])->whereNumber('product');
    Route::get('queue/active-anc', [MaternityWorkbenchController::class, 'getActiveAncQueue']);
    Route::get('queue/admitted-patients', [MaternityWorkbenchController::class, 'getAdmittedPatientsQueue']);
    Route::get('queue/bed-requests', [MaternityWorkbenchController::class, 'getBedRequestsQueue']);
    Route::get('queue/counts', [MaternityWorkbenchController::class, 'getQueueCounts']);
    Route::get('queue/discharge-requests', [MaternityWorkbenchController::class, 'getDischargeRequestsQueue']);
    Route::get('queue/due-visits', [MaternityWorkbenchController::class, 'getDueVisitsQueue']);
    Route::get('queue/high-risk', [MaternityWorkbenchController::class, 'getHighRiskQueue']);
    Route::get('queue/overdue-immunization', [MaternityWorkbenchController::class, 'getOverdueImmunizationQueue']);
    Route::get('queue/postnatal', [MaternityWorkbenchController::class, 'getPostnatalQueue']);
    Route::get('queue/upcoming-edd', [MaternityWorkbenchController::class, 'getUpcomingEddQueue']);
    Route::get('schedule-templates', [MaternityWorkbenchController::class, 'getScheduleTemplatesMaternity']);
    Route::put('schedule/{scheduleId}/status', [MaternityWorkbenchController::class, 'updateScheduleStatusMaternity'])->whereNumber('scheduleId');
    Route::get('search-patients', [MaternityWorkbenchController::class, 'searchPatients']);
    Route::get('search-services', [MaternityWorkbenchController::class, 'searchServices']);
    Route::get('vaccine-products/{vaccineName}', [MaternityWorkbenchController::class, 'getVaccineProductsMaternity']);
});

/*
|--------------------------------------------------------------------------
| Mobile Laboratory Routes — the web Lab workbench's controller methods
|--------------------------------------------------------------------------
| Same LabWorkbenchController methods as routes/web.php (/lab-workbench/*): billing, samples and lab numbers,
| results, approvals, dismiss/delete, new requests, reports. Who may enter or approve a result is decided inside
| the controller (lab scientist always; requesting doctor/nurse by setting; approvers: unit/dept heads and admins).
*/
Route::prefix('mobile/lab')->middleware(['auth:sanctum', 'native.workspace:lab'])->group(function () {
    $c = LabWorkbenchController::class;
    Route::get('departments', [\App\Http\Controllers\API\MobileLabController::class, 'departments']);
    Route::get('meta', [\App\Http\Controllers\API\MobileLabController::class, 'meta']);
    Route::get('queue', [$c, 'getLabQueue']);
    Route::get('queue-counts', [$c, 'getQueueCounts']);
    Route::get('patient/{id}/requests', [$c, 'getPatientRequests'])->whereNumber('id');
    Route::get('patient/{patient_id}/history', [EncounterController::class, 'investigationHistoryList'])->whereNumber('patient_id');
    Route::post('record-billing', [$c, 'recordBilling']);
    Route::post('claim-self-perform', [$c, 'claimSelfPerform']);
    Route::post('collect-sample', [$c, 'collectSample']);
    Route::get('next-lab-number', [$c, 'getNextLabNumber']);
    Route::get('check-lab-number', [$c, 'checkLabNumberExists']);
    Route::post('dismiss-requests', [$c, 'dismissRequests']);
    Route::post('store-request', [$c, 'storeLabRequest']);
    Route::post('apply-combo', [$c, 'labApplyCombo']);
    Route::post('remove-bundle', [$c, 'removeBundle']);
    Route::post('remove-bundle-item', [$c, 'removeBundleItem']);
    Route::get('requests/{id}', [$c, 'getLabRequest'])->whereNumber('id');
    Route::get('requests/{id}/attachments', [$c, 'getRequestAttachments'])->whereNumber('id');
    Route::post('save-result', [$c, 'saveResult']);
    Route::delete('requests/{id}', [$c, 'deleteRequest'])->whereNumber('id');
    Route::post('requests/{id}/restore', [$c, 'restoreRequest'])->whereNumber('id');
    Route::post('requests/{id}/dismiss', [$c, 'dismissRequest'])->whereNumber('id');
    Route::post('requests/{id}/undismiss', [$c, 'undismissRequest'])->whereNumber('id');
    Route::post('requests/{id}/dismiss-free-form', [$c, 'dismissFreeForm'])->whereNumber('id');
    Route::get('deleted-requests/{patientId?}', [$c, 'getDeletedRequests'])->whereNumber('patientId');
    Route::get('dismissed-requests/{patientId?}', [$c, 'getDismissedRequests'])->whereNumber('patientId');
    Route::get('audit-logs', [$c, 'getAuditLogs']);
    Route::get('approval-queue', [$c, 'getApprovalQueue']);
    Route::get('approval-count', [$c, 'getApprovalCount']);
    Route::get('approval/{id}', [$c, 'getApprovalDetail'])->whereNumber('id');
    Route::post('approval/{id}/approve', [$c, 'approveResult'])->whereNumber('id');
    Route::post('approval/{id}/reject', [$c, 'rejectResult'])->whereNumber('id');
    Route::post('approval/{id}/reverse', [$c, 'reverseApproval'])->whereNumber('id');
    Route::post('self-approve/{id}', [$c, 'selfApproveResult'])->whereNumber('id');
    Route::get('reports', [$c, 'getLabReports']);
    Route::get('statistics', [$c, 'getLabStatistics']);
    Route::get('filter-doctors', [$c, 'getRequestingDoctors']);
    Route::get('filter-hmos', [$c, 'getHmosForFilter']);
    Route::get('filter-services', [$c, 'getLabServicesForFilter']);
    Route::get('v1-result-templates', [\App\Http\Controllers\V1ResultTemplateController::class, 'getTemplates']);
    Route::get('nhmis/service-mapping/{serviceId}', [NhmisWorkbenchController::class, 'getMappingForService']);
    Route::post('result-views', [ResultViewController::class, 'store']);
    Route::get('my-activity', [ProcessedTransactionsController::class, 'activity']); // what I did, by stage
    Route::get('search-services', [\App\Http\Controllers\ServiceController::class, 'liveSearchServices']);
});

/*
|--------------------------------------------------------------------------
| Mobile Imaging Routes — the web Imaging workbench's controller methods
|--------------------------------------------------------------------------
| Same ImagingWorkbenchController methods as routes/web.php (/imaging-workbench/*). Imaging has no sample step.
| Who may enter or approve a result is decided inside the controller, as for the laboratory.
*/
Route::prefix('mobile/radiology')->middleware(['auth:sanctum', 'native.workspace:radiology'])->group(function () {
    $c = \App\Http\Controllers\ImagingWorkbenchController::class;
    Route::get('departments', [\App\Http\Controllers\API\MobileImagingController::class, 'departments']);
    Route::get('meta', [\App\Http\Controllers\API\MobileImagingController::class, 'meta']);
    Route::get('queue', [$c, 'getImagingQueue']);
    Route::get('queue-counts', [$c, 'getQueueCounts']);
    Route::get('patient/{id}/requests', [$c, 'getPatientRequests'])->whereNumber('id');
    Route::get('patient/{patientId}/history', [$c, 'getImagingHistoryList'])->whereNumber('patientId');
    Route::post('record-billing', [$c, 'recordBilling']);
    Route::post('claim-self-perform', [$c, 'claimSelfPerform']);
    Route::post('dismiss-requests', [$c, 'dismissRequests']);
    Route::post('create-request', [$c, 'createRequest']);
    Route::get('search-services', [$c, 'searchServices']);
    Route::post('apply-combo', [$c, 'imagingApplyCombo']);
    Route::post('remove-bundle', [$c, 'removeBundle']);
    Route::post('remove-bundle-item', [$c, 'removeBundleItem']);
    Route::get('requests/{id}', [$c, 'getImagingRequest'])->whereNumber('id');
    Route::get('requests/{id}/attachments', [$c, 'getRequestAttachments'])->whereNumber('id');
    Route::post('save-result', [$c, 'saveResult']);
    Route::delete('requests/{id}', [$c, 'deleteRequest'])->whereNumber('id');
    Route::post('requests/{id}/restore', [$c, 'restoreRequest'])->whereNumber('id');
    Route::post('requests/{id}/dismiss', [$c, 'dismissRequest'])->whereNumber('id');
    Route::post('requests/{id}/undismiss', [$c, 'undismissRequest'])->whereNumber('id');
    Route::post('requests/{id}/dismiss-free-form', [$c, 'dismissFreeForm'])->whereNumber('id');
    Route::get('deleted-requests/{patientId?}', [$c, 'getDeletedRequests'])->whereNumber('patientId');
    Route::get('dismissed-requests/{patientId?}', [$c, 'getDismissedRequests'])->whereNumber('patientId');
    Route::get('audit-logs', [$c, 'getAuditLog']);
    Route::get('approval-queue', [$c, 'getApprovalQueue']);
    Route::get('approval-count', [$c, 'getApprovalCount']);
    Route::get('approval/{id}', [$c, 'getApprovalDetail'])->whereNumber('id');
    Route::post('approval/{id}/approve', [$c, 'approveResult'])->whereNumber('id');
    Route::post('approval/{id}/reject', [$c, 'rejectResult'])->whereNumber('id');
    Route::post('approval/{id}/reverse', [$c, 'reverseApproval'])->whereNumber('id');
    Route::post('self-approve/{id}', [$c, 'selfApproveResult'])->whereNumber('id');
    Route::get('reports', [$c, 'getImagingReports']);
    Route::get('statistics', [$c, 'getImagingStatistics']);
    Route::get('filter-doctors', [$c, 'getRequestingDoctors']);
    Route::get('filter-hmos', [$c, 'getHmosForFilter']);
    Route::get('filter-services', [$c, 'getImagingServicesForFilter']);
    Route::get('v1-result-templates', [\App\Http\Controllers\V1ResultTemplateController::class, 'getTemplates']);
    Route::get('nhmis/service-mapping/{serviceId}', [NhmisWorkbenchController::class, 'getMappingForService']);
    Route::post('result-views', [ResultViewController::class, 'store']);
    Route::get('my-activity', [ProcessedTransactionsController::class, 'activity']); // what I did, by stage
});

/*
|--------------------------------------------------------------------------
| Mobile Surgery Routes — wraps the web Surgery workbench
|--------------------------------------------------------------------------
*/
Route::prefix('mobile/surgery')->middleware(['auth:sanctum', 'native.workspace:surgery'])->group(function () {
    Route::get('queue', [\App\Http\Controllers\SurgeryWorkbenchController::class, 'getQueue']);
    Route::get('queue-counts', [\App\Http\Controllers\SurgeryWorkbenchController::class, 'getQueueCounts']);
    Route::get('search-patients', [\App\Http\Controllers\SurgeryWorkbenchController::class, 'searchPatients']);
    Route::get('patient/{patientId}/procedures', [\App\Http\Controllers\SurgeryWorkbenchController::class, 'getPatientProcedures'])->whereNumber('patientId');
    // JSON detail of a procedure (the page itself is a Blade view)
    Route::get('procedure/{procedure}/details', [\App\Http\Controllers\EncounterController::class, 'getProcedureDetails'])->whereNumber('procedure');
    Route::get('procedure/{procedure}/checklist', [\App\Http\Controllers\API\MobileSurgeryController::class, 'checklist'])->whereNumber('procedure');
    // Lists, flags and abilities the Blade page computes (team roles, note types, consent, what this user may do)
    Route::get('procedure/{procedure}/meta', [\App\Http\Controllers\API\MobileSurgeryController::class, 'meta'])->whereNumber('procedure');
    Route::get('staff-search', [\App\Http\Controllers\API\MobileSurgeryController::class, 'staffSearch']);
    // Attachments (the web's session routes, for the app's token)
    Route::get('procedure/{procedure}/attachments', [\App\Http\Controllers\API\MobileSurgeryController::class, 'attachments'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/attachments', [\App\Http\Controllers\PatientProcedureController::class, 'uploadAttachment'])->whereNumber('procedure');
    Route::get('procedure/{procedure}/attachments/{attachment}/download', [\App\Http\Controllers\API\MobileSurgeryController::class, 'downloadAttachment'])->whereNumber('procedure')->whereNumber('attachment');
    Route::delete('procedure/{procedure}/attachments/{attachment}', [\App\Http\Controllers\PatientProcedureController::class, 'deleteAttachment'])->whereNumber('procedure')->whereNumber('attachment');
    // The procedure page itself (PatientProcedureController): scheduling, team, notes, checklist, consent, items, outcome
    Route::get('procedure/{procedure}', [\App\Http\Controllers\PatientProcedureController::class, 'show'])->whereNumber('procedure');
    Route::put('procedure/{procedure}', [\App\Http\Controllers\PatientProcedureController::class, 'update'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/bill-base-fee', [\App\Http\Controllers\PatientProcedureController::class, 'billBaseFee'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/cancel', [\App\Http\Controllers\PatientProcedureController::class, 'cancel'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/checklist-toggle', [\App\Http\Controllers\PatientProcedureController::class, 'toggleChecklistItem'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/complete', [\App\Http\Controllers\PatientProcedureController::class, 'complete'])->whereNumber('procedure');
    Route::patch('procedure/{procedure}/consent', [\App\Http\Controllers\PatientProcedureController::class, 'updateConsent'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/consent/sign', [\App\Http\Controllers\PatientProcedureController::class, 'signConsent'])->whereNumber('procedure');
    Route::get('procedure/{procedure}/imaging-history', [\App\Http\Controllers\PatientProcedureController::class, 'imagingHistoryList'])->whereNumber('procedure');
    Route::get('procedure/{procedure}/items', [\App\Http\Controllers\PatientProcedureController::class, 'getItems'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/items/imaging', [\App\Http\Controllers\PatientProcedureController::class, 'addImagingRequest'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/items/lab', [\App\Http\Controllers\PatientProcedureController::class, 'addLabRequest'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/items/medication', [\App\Http\Controllers\PatientProcedureController::class, 'addMedication'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/items/service', [\App\Http\Controllers\PatientProcedureController::class, 'addServiceBill'])->whereNumber('procedure');
    Route::delete('procedure/{procedure}/items/{item}', [\App\Http\Controllers\PatientProcedureController::class, 'removeItem'])->whereNumber('procedure')->whereNumber('item');
    Route::get('procedure/{procedure}/lab-history', [\App\Http\Controllers\PatientProcedureController::class, 'labHistoryList'])->whereNumber('procedure');
    Route::get('procedure/{procedure}/medication-history', [\App\Http\Controllers\PatientProcedureController::class, 'medicationHistoryList'])->whereNumber('procedure');
    Route::get('procedure/{procedure}/notes', [\App\Http\Controllers\PatientProcedureController::class, 'getNotes'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/notes', [\App\Http\Controllers\PatientProcedureController::class, 'addNote'])->whereNumber('procedure');
    Route::put('procedure/{procedure}/notes/{note}', [\App\Http\Controllers\PatientProcedureController::class, 'updateNote'])->whereNumber('procedure')->whereNumber('note');
    Route::delete('procedure/{procedure}/notes/{note}', [\App\Http\Controllers\PatientProcedureController::class, 'deleteNote'])->whereNumber('procedure')->whereNumber('note');
    Route::get('procedure/{procedure}/notes/{note}/edit', [\App\Http\Controllers\PatientProcedureController::class, 'getNote'])->whereNumber('procedure')->whereNumber('note');
    Route::put('procedure/{procedure}/outcome', [\App\Http\Controllers\PatientProcedureController::class, 'updateOutcome'])->whereNumber('procedure');
    Route::get('procedure/{procedure}/tariff-guide', [\App\Http\Controllers\PatientProcedureController::class, 'getTariffGuide'])->whereNumber('procedure');
    Route::get('procedure/{procedure}/team', [\App\Http\Controllers\PatientProcedureController::class, 'getTeam'])->whereNumber('procedure');
    Route::post('procedure/{procedure}/team', [\App\Http\Controllers\PatientProcedureController::class, 'addTeamMember'])->whereNumber('procedure');
    Route::put('procedure/{procedure}/team/{member}', [\App\Http\Controllers\PatientProcedureController::class, 'updateTeamMember'])->whereNumber('procedure')->whereNumber('member');
    Route::delete('procedure/{procedure}/team/{member}', [\App\Http\Controllers\PatientProcedureController::class, 'removeTeamMember'])->whereNumber('procedure')->whereNumber('member');
    Route::get('procedure/{procedure}/{patient}/pending-bills', [\App\Http\Controllers\PatientProcedureController::class, 'getItems'])->whereNumber('procedure')->whereNumber('patient');

    // "Services rendered" and the item pickers: the web's procedure page uses the nursing workbench's billing kit
    // routes (any signed-in user). A surgery-only user is not a nurse, so the same methods are exposed here.
    Route::get('context', [\App\Http\Controllers\API\MobileNursingController::class, 'context']);
    Route::get('search-services', [\App\Http\Controllers\NursingWorkbenchController::class, 'searchServices']);
    Route::get('search-products', [\App\Http\Controllers\NursingWorkbenchController::class, 'searchProducts']);
    Route::get('product-batches', [\App\Http\Controllers\NursingWorkbenchController::class, 'getProductBatches']);
    Route::get('batch-fulfillment', [\App\Http\Controllers\NursingWorkbenchController::class, 'getBatchFulfillmentSuggestion']);
    Route::get('product-stock/{productId}/store/{storeId}', [\App\Http\Controllers\NursingWorkbenchController::class, 'getProductStockByStore'])->whereNumber('productId')->whereNumber('storeId');
    Route::get('patient/{patientId}/pending-bills', [\App\Http\Controllers\NursingWorkbenchController::class, 'getPendingBills'])->whereNumber('patientId');
    Route::get('patient/{patientId}/service-requests', [\App\Http\Controllers\NursingWorkbenchController::class, 'getServiceRequests'])->whereNumber('patientId');
    Route::get('patient/{patientId}/service-requests-stats', [\App\Http\Controllers\NursingWorkbenchController::class, 'getServiceRequestsStats'])->whereNumber('patientId');
    Route::get('products/{product}/packagings', [\App\Http\Controllers\ProductController::class, 'getPackagings'])->whereNumber('product');
    Route::post('billing/add-lab-bill', [\App\Http\Controllers\NursingWorkbenchController::class, 'addLabBill']);
    Route::post('billing/add-imaging-bill', [\App\Http\Controllers\NursingWorkbenchController::class, 'addImagingBill']);
    Route::post('billing/add-service', [\App\Http\Controllers\NursingWorkbenchController::class, 'addServiceBill']);
    Route::post('billing/add-consumable-bill', [\App\Http\Controllers\NursingWorkbenchController::class, 'addConsumableBill']);
    Route::delete('billing/remove-bill/{id}', [\App\Http\Controllers\NursingWorkbenchController::class, 'removeBillItem'])->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Mobile Nursing Routes — wraps the web Nursing workbench
|--------------------------------------------------------------------------
| Every route points at the controller method the web nursing workbench calls (queues, ward
| dashboard, patient workspace, notes, injections, immunizations, clinical requests, bills,
| admission and discharge checklists, beds, shifts and handovers). Printable reports and
| exports stay on the web.
*/
Route::prefix('mobile/nursing')->middleware(['auth:sanctum', 'native.workspace:nursing'])->group(function () {
    // Emergency Queue (web: nursing-core.js 'emergency' filter → emergency.queue)
    Route::get('emergency-queue', [EmergencyIntakeController::class, 'getEmergencyQueue']);
    Route::get('context', [\App\Http\Controllers\API\MobileNursingController::class, 'context']);
    // Reports (printable layouts and exports stay on the web; these are the same data)
    Route::get('reports/activity-summary', [NursingWorkbenchController::class, 'getReportsActivitySummary']);
    Route::get('reports/immunizations', [NursingWorkbenchController::class, 'getReportsImmunizations']);
    Route::get('reports/injections', [NursingWorkbenchController::class, 'getReportsInjections']);
    Route::get('reports/io', [NursingWorkbenchController::class, 'getReportsIO']);
    Route::get('reports/medications', [NursingWorkbenchController::class, 'getReportsMedications']);
    Route::get('reports/notes', [NursingWorkbenchController::class, 'getReportsNotes']);
    Route::get('reports/nurses', [NursingWorkbenchController::class, 'getReportsNurses']);
    Route::get('reports/occupancy', [NursingWorkbenchController::class, 'getReportsOccupancy']);
    Route::get('reports/shifts', [NursingWorkbenchController::class, 'getReportsShifts']);
    Route::get('reports/vitals', [NursingWorkbenchController::class, 'getReportsVitals']);
    Route::get('beds', [\App\Http\Controllers\API\MobileBedController::class, 'index']);
    Route::post('beds', [\App\Http\Controllers\API\MobileBedController::class, 'store']);
    Route::put('beds/{bed}', [\App\Http\Controllers\API\MobileBedController::class, 'update'])->whereNumber('bed');
    Route::post('administer-from-schedule', [NursingWorkbenchController::class, 'administerFromScheduleNew']);
    Route::post('administer-immunization', [NursingWorkbenchController::class, 'administerImmunization']);
    Route::post('administer-injection', [NursingWorkbenchController::class, 'administerInjection']);
    Route::post('admission-checklist/item/{itemId}/complete', [NursingWorkbenchController::class, 'completeAdmissionChecklistItem'])->whereNumber('itemId');
    Route::post('admission-checklist/item/{itemId}/waive', [NursingWorkbenchController::class, 'waiveAdmissionChecklistItem'])->whereNumber('itemId');
    Route::post('admission/{admissionId}/assign-bed', [NursingWorkbenchController::class, 'assignBed'])->whereNumber('admissionId');
    Route::get('admission/{admissionId}/checklist', [NursingWorkbenchController::class, 'getAdmissionChecklist'])->whereNumber('admissionId');
    Route::post('admission/{admissionId}/complete-discharge', [NursingWorkbenchController::class, 'completeDischarge'])->whereNumber('admissionId');
    Route::get('admission/{admissionId}/details', [NursingWorkbenchController::class, 'getAdmissionDetails'])->whereNumber('admissionId');
    Route::get('admission/{admissionId}/discharge-checklist', [NursingWorkbenchController::class, 'getDischargeChecklist'])->whereNumber('admissionId');
    Route::post('admission/{admissionId}/transfer-ward', [NursingWorkbenchController::class, 'transferWard'])->whereNumber('admissionId');
    Route::get('admitted-patients', [NursingWorkbenchController::class, 'getAdmittedPatients']);
    Route::get('batch-fulfillment', [NursingWorkbenchController::class, 'getBatchFulfillmentSuggestion']);
    Route::post('bed/{bedId}/available', [NursingWorkbenchController::class, 'setBedAvailable'])->whereNumber('bedId');
    Route::get('bed/{bedId}/details', [NursingWorkbenchController::class, 'getBedDetails'])->whereNumber('bedId');
    Route::post('bed/{bedId}/maintenance', [NursingWorkbenchController::class, 'setBedMaintenance'])->whereNumber('bedId');
    Route::post('billing/add-consumable-bill', [NursingWorkbenchController::class, 'addConsumableBill']);
    Route::post('billing/add-imaging-bill', [NursingWorkbenchController::class, 'addImagingBill']);
    Route::post('billing/add-lab-bill', [NursingWorkbenchController::class, 'addLabBill']);
    Route::post('billing/add-service', [NursingWorkbenchController::class, 'addServiceBill']);
    Route::delete('billing/remove-bill/{id}', [NursingWorkbenchController::class, 'removeBillItem'])->whereNumber('id');
    Route::post('clinical-requests/add-imaging', [NursingWorkbenchController::class, 'nurseAddSingleImaging']);
    Route::post('clinical-requests/add-lab', [NursingWorkbenchController::class, 'nurseAddSingleLab']);
    Route::post('clinical-requests/add-prescription', [NursingWorkbenchController::class, 'nurseAddSinglePrescription']);
    Route::post('clinical-requests/add-procedure', [NursingWorkbenchController::class, 'nurseAddSingleProcedure']);
    Route::post('clinical-requests/apply-combo', [NursingWorkbenchController::class, 'nursingApplyCombo']);
    Route::post('clinical-requests/apply-treatment-plan', [TreatmentPlanController::class, 'applyForNurse']);
    Route::get('clinical-requests/encounter-items/{encounterId}', [NursingWorkbenchController::class, 'nurseEncounterItems'])->whereNumber('encounterId');
    Route::post('clinical-requests/imaging', [NursingWorkbenchController::class, 'saveNurseImaging']);
    Route::delete('clinical-requests/imaging/{imaging}', [NursingWorkbenchController::class, 'nurseRemoveSingleImaging']);
    Route::put('clinical-requests/imaging/{imaging}/note', [NursingWorkbenchController::class, 'nurseUpdateImagingNote']);
    Route::post('clinical-requests/labs', [NursingWorkbenchController::class, 'saveNurseLabs']);
    Route::delete('clinical-requests/labs/{lab}', [NursingWorkbenchController::class, 'nurseRemoveSingleLab']);
    Route::put('clinical-requests/labs/{lab}/note', [NursingWorkbenchController::class, 'nurseUpdateLabNote']);
    Route::post('clinical-requests/prescriptions', [NursingWorkbenchController::class, 'saveNursePrescriptions']);
    Route::delete('clinical-requests/prescriptions/{prescription}', [NursingWorkbenchController::class, 'nurseRemoveSinglePrescription']);
    Route::put('clinical-requests/prescriptions/{prescription}/dose', [NursingWorkbenchController::class, 'nurseUpdatePrescriptionDose']);
    Route::post('clinical-requests/procedures', [NursingWorkbenchController::class, 'saveNurseProcedures']);
    Route::delete('clinical-requests/procedures/{procedure}', [NursingWorkbenchController::class, 'nurseRemoveSingleProcedure']);
    Route::post('clinical-requests/re-prescribe', [NursingWorkbenchController::class, 'nurseRePrescribe']);
    Route::get('clinical-requests/recent-encounters', [NursingWorkbenchController::class, 'nurseRecentEncounters']);
    Route::post('clinical-requests/remove-bundle', [NursingWorkbenchController::class, 'removeBundle']);
    Route::post('clinical-requests/remove-bundle-item', [NursingWorkbenchController::class, 'removeBundleItem']);
    Route::get('clinics', [NursingWorkbenchController::class, 'getClinics']);
    Route::get('deceased-queue', [NursingWorkbenchController::class, 'getDeceasedQueue']);
    Route::post('deceased/{recordId}/last-office', [NursingWorkbenchController::class, 'completeLastOffice'])->whereNumber('recordId');
    Route::post('discharge-checklist/item/{itemId}/complete', [NursingWorkbenchController::class, 'completeDischargeChecklistItem'])->whereNumber('itemId');
    Route::post('discharge-checklist/item/{itemId}/waive', [NursingWorkbenchController::class, 'waiveDischargeChecklistItem'])->whereNumber('itemId');
    Route::get('handover-summary', [NursingWorkbenchController::class, 'generateHandoverReport']);
    Route::get('handover/{id}', [ShiftController::class, 'getHandoverDetails'])->whereNumber('id');
    Route::post('handover/{id}/acknowledge', [ShiftController::class, 'acknowledgeHandover'])->whereNumber('id');
    Route::get('handovers', [ShiftController::class, 'getHandovers']);
    Route::post('handovers/acknowledge-multiple', [ShiftController::class, 'acknowledgeMultiple']);
    Route::get('note-types', [NursingWorkbenchController::class, 'getNoteTypes']);
    Route::post('nursing-note', [NursingWorkbenchController::class, 'saveNursingNote']);
    Route::get('nursing-note/{noteId}', [NursingWorkbenchController::class, 'getNoteDetails'])->whereNumber('noteId');
    Route::put('nursing-note/{noteId}', [NursingWorkbenchController::class, 'updateNursingNote'])->whereNumber('noteId');
    Route::get('outreach-session/{sessionId}', [NursingWorkbenchController::class, 'getOutreachSessionDetails'])->whereNumber('sessionId');
    Route::get('outreach-store-inventory', [NursingWorkbenchController::class, 'getOutreachStoreInventory']);
    Route::get('outreach-tallies', [NursingWorkbenchController::class, 'getOutreachTallies']);
    Route::post('outreach-tally', [NursingWorkbenchController::class, 'saveOutreachTally']);
    Route::get('patient/{patientId}/details', [NursingWorkbenchController::class, 'getPatientDetails'])->whereNumber('patientId');
    Route::post('patient/{patientId}/generate-schedule', [NursingWorkbenchController::class, 'generatePatientSchedule'])->whereNumber('patientId');
    Route::get('patient/{patientId}/immunization-history', [NursingWorkbenchController::class, 'getImmunizationHistory'])->whereNumber('patientId');
    Route::get('patient/{patientId}/immunization-schedule', [NursingWorkbenchController::class, 'getImmunizationSchedule'])->whereNumber('patientId');
    Route::get('patient/{patientId}/immunizations', [NursingWorkbenchController::class, 'getImmunizations'])->whereNumber('patientId');
    Route::get('patient/{patientId}/injections', [NursingWorkbenchController::class, 'getInjections'])->whereNumber('patientId');
    Route::get('patient/{patientId}/nursing-notes', [NursingWorkbenchController::class, 'getNursingNotes'])->whereNumber('patientId');
    Route::get('patient/{patientId}/orders', [NursingWorkbenchController::class, 'getPatientOrders'])->whereNumber('patientId');
    Route::get('patient/{patientId}/pending-bills', [NursingWorkbenchController::class, 'getPendingBills'])->whereNumber('patientId');
    Route::get('patient/{patientId}/schedule', [NursingWorkbenchController::class, 'getPatientSchedule'])->whereNumber('patientId');
    Route::get('patient/{patientId}/service-requests', [NursingWorkbenchController::class, 'getServiceRequests'])->whereNumber('patientId');
    Route::get('patient/{patientId}/service-requests-stats', [NursingWorkbenchController::class, 'getServiceRequestsStats'])->whereNumber('patientId');
    Route::get('patient/{patientId}/vitals', [NursingWorkbenchController::class, 'getPatientVitals'])->whereNumber('patientId');
    Route::get('patient/{patientId}/vitals-history-dt', [NursingWorkbenchController::class, 'getPatientVitalsDt'])->whereNumber('patientId');
    Route::get('product-batches', [NursingWorkbenchController::class, 'getProductBatches']);
    // Dispensing units of a product (web billing-shared.js: /products/{id}/packagings)
    Route::get('products/{product}/packagings', [ProductController::class, 'getPackagings'])->whereNumber('product');
    Route::get('product-categories', [NursingWorkbenchController::class, 'getProductCategories']);
    Route::get('product-stock/{productId}/store/{storeId}', [NursingWorkbenchController::class, 'getProductStockByStore'])->whereNumber('productId')->whereNumber('storeId');
    Route::get('queue-counts', [NursingWorkbenchController::class, 'getQueueCounts']);
    Route::get('queue/bed-requests', [NursingWorkbenchController::class, 'getBedRequestsQueue']);
    Route::get('queue/consulting', [NursingWorkbenchController::class, 'getConsultingQueue']);
    Route::get('queue/discharge-requests', [NursingWorkbenchController::class, 'getDischargeQueue']);
    Route::get('queue/medication-due', [NursingWorkbenchController::class, 'getMedicationDueQueue']);
    Route::get('queue/vitals', [NursingWorkbenchController::class, 'getVitalsQueue']);
    Route::get('schedule-templates', [NursingWorkbenchController::class, 'getScheduleTemplates']);
    Route::post('schedule/{scheduleId}/administer', [NursingWorkbenchController::class, 'administerFromSchedule'])->whereNumber('scheduleId');
    Route::put('schedule/{scheduleId}/status', [NursingWorkbenchController::class, 'updateScheduleStatus'])->whereNumber('scheduleId');
    Route::get('search-injectables', [NursingWorkbenchController::class, 'searchInjectables']);
    Route::get('search-patients', [NursingWorkbenchController::class, 'searchPatients']);
    Route::get('search-products', [NursingWorkbenchController::class, 'searchProducts']);
    Route::get('search-services', [NursingWorkbenchController::class, 'searchServices']);
    Route::get('search-vaccines', [NursingWorkbenchController::class, 'getVaccines']);
    Route::get('service-categories', [NursingWorkbenchController::class, 'getServiceCategories']);
    Route::get('shift-summary', [NursingWorkbenchController::class, 'getShiftSummary']);
    Route::get('shift/actions', [ShiftController::class, 'getShiftActions']);
    Route::get('shift/calendar', [ShiftController::class, 'getCalendar']);
    Route::get('shift/check', [ShiftController::class, 'checkActiveShift']);
    Route::post('shift/end', [ShiftController::class, 'endShift']);
    Route::get('shift/pending-handovers', [ShiftController::class, 'getPendingHandovers']);
    Route::get('shift/preview', [ShiftController::class, 'getShiftPreview']);
    Route::post('shift/start', [ShiftController::class, 'startShift']);
    Route::get('shift/statistics', [ShiftController::class, 'getStatistics']);
    Route::get('shift/wards', [ShiftController::class, 'getWards']);
    Route::get('vaccine-products/{vaccineName}', [NursingWorkbenchController::class, 'getVaccineProducts']);
    Route::put('vitals/{vitalId}', [NursingWorkbenchController::class, 'updateVital'])->whereNumber('vitalId');
    Route::get('ward-dashboard/admission-queue', [NursingWorkbenchController::class, 'getAdmissionQueue']);
    Route::get('ward-dashboard/available-beds', [NursingWorkbenchController::class, 'getAvailableBeds']);
    Route::get('ward-dashboard/discharge-queue', [NursingWorkbenchController::class, 'getDischargeQueue']);
    Route::get('ward-dashboard/stats', [NursingWorkbenchController::class, 'getWardDashboardStats']);
    Route::get('ward-dashboard/wards', [NursingWorkbenchController::class, 'getWardDashboardWards']);
    Route::get('wards', [NursingWorkbenchController::class, 'getWards']);

});

/*
|--------------------------------------------------------------------------
| Native Staff Client — features shared by Health Records and Doctors
|--------------------------------------------------------------------------
| Both web workbenches include these (contacts, price list, emergency intake, appointments,
| clinical alerts, patient edit, admission request). Same controllers as the web.
*/
// Shared patient search (web: admin/partials/patient_search_html + patient-search-widget.js → /patient-search)
Route::get('mobile/patient-search', [\App\Http\Controllers\PatientSearchController::class, 'search'])
    ->middleware(['auth:sanctum', 'native.workspace:reception|billing|pharmacy|nursing|maternity|surgery|lab|radiology|hmo|doctor']);

/*
| Shared Clinical Context (web: admin/partials/clinical_context_modal + public/js/clinical-context.js).
| Same controller methods as the web modal, for every workbench that includes it.
*/
Route::prefix('mobile/clinical-context')->middleware(['auth:sanctum', 'native.workspace:nursing|maternity|surgery|doctor|lab|radiology|pharmacy|hmo'])->group(function () {
    Route::get('patient/{patientId}/vitals', [\App\Http\Controllers\ClinicalContextController::class, 'getVitals'])->whereNumber('patientId');
    Route::get('patient/{patientId}/notes', [\App\Http\Controllers\ClinicalContextController::class, 'getNotes'])->whereNumber('patientId');
    Route::get('patient/{patientId}/medications', [\App\Http\Controllers\ClinicalContextController::class, 'getMedications'])->whereNumber('patientId');
    Route::get('patient/{patientId}/allergies', [\App\Http\Controllers\ClinicalContextController::class, 'getAllergies'])->whereNumber('patientId');
    Route::get('patient/{patientId}/labs', [\App\Http\Controllers\ClinicalContextController::class, 'getLabs'])->whereNumber('patientId');
    Route::get('patient/{patientId}/imaging', [\App\Http\Controllers\ClinicalContextController::class, 'getImaging'])->whereNumber('patientId');
    Route::get('patient/{patientId}/procedures', [\App\Http\Controllers\ClinicalContextController::class, 'getProcedures'])->whereNumber('patientId');
    Route::get('patient/{patientId}/injections', [NursingWorkbenchController::class, 'getInjections'])->whereNumber('patientId');
    Route::get('patient/{patientId}/immunization-history', [NursingWorkbenchController::class, 'getImmunizationHistory'])->whereNumber('patientId');
    Route::get('result-views/unviewed-counts/{patient_id}', [ResultViewController::class, 'unviewedCounts'])->whereNumber('patient_id');
    Route::post('result-views', [ResultViewController::class, 'store']);
});

Route::prefix('mobile/shared')->middleware(['auth:sanctum', 'native.workspace:reception|doctor|billing|nursing|maternity|surgery|pharmacy|lab|radiology'])->group(function () {
    Route::get('hospital-contacts', [HospitalContactController::class, 'index']);
    Route::post('hospital-contacts', [HospitalContactController::class, 'store']);
    Route::put('hospital-contacts/{id}', [HospitalContactController::class, 'update']);
    Route::delete('hospital-contacts/{id}', [HospitalContactController::class, 'destroy']);
    Route::get('price-list/services', [WorkbenchPriceListController::class, 'getServices']);
    Route::get('price-list/products', [WorkbenchPriceListController::class, 'getProducts']);
    Route::get('price-list/tariffs', [WorkbenchPriceListController::class, 'getTariffs']);

    // Patient registration and lookup (both web workbenches open the same patient form)
    Route::get('search-patients', [ReceptionWorkbenchController::class, 'searchPatients']);
    Route::get('patient/next-file-number', [ReceptionWorkbenchController::class, 'getNextFileNumber']);
    Route::post('patient/check-file-number', [ReceptionWorkbenchController::class, 'checkFileNumberExists']);
    Route::post('patient/check-duplicate', [ReceptionWorkbenchController::class, 'checkDuplicatePatient']);
    Route::post('patient/quick-register', [ReceptionWorkbenchController::class, 'quickRegister']);
    Route::get('patient/{id}', [ReceptionWorkbenchController::class, 'getPatient'])->whereNumber('id');
    Route::get('hmos', [ReceptionWorkbenchController::class, 'getHmos']);
    Route::get('registration-context', [MobileReceptionController::class, 'context']);

    // Patient record (edit) and safety alerts
    Route::put('patient/{id}/update', [ReceptionWorkbenchController::class, 'updatePatient']);
    Route::get('patient/{patientId}/alerts', [ClinicalAlertController::class, 'index']);
    Route::post('patient/{patientId}/alerts', [ClinicalAlertController::class, 'store']);
    Route::put('patient/{patientId}/alerts/{alertId}', [ClinicalAlertController::class, 'update']);
    Route::delete('patient/{patientId}/alerts/{alertId}', [ClinicalAlertController::class, 'destroy']);

    // Appointments (reception Appointments tab, doctor queue actions)
    Route::get('appointments/list', [DoctorAppointmentController::class, 'getAppointments']);
    Route::get('appointments/available-slots', [DoctorAppointmentController::class, 'getAvailableSlots']);
    Route::post('appointments/create', [DoctorAppointmentController::class, 'createAppointment']);
    Route::get('appointments/{appointment}/chain', [DoctorAppointmentController::class, 'getAppointmentChain']);
    Route::get('appointments/{appointment}/available-doctors', [DoctorAppointmentController::class, 'getAvailableDoctors']);
    Route::post('appointments/{appointment}/check-in', [DoctorAppointmentController::class, 'checkIn']);
    Route::post('appointments/{appointment}/cancel', [DoctorAppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/no-show', [DoctorAppointmentController::class, 'markNoShow']);
    Route::post('appointments/{appointment}/reschedule', [DoctorAppointmentController::class, 'reschedule']);
    Route::post('appointments/{appointment}/reassign', [DoctorAppointmentController::class, 'reassignDoctor']);

    // Emergency intake (both workbenches' red button)
    Route::get('emergency/clinics', [EmergencyIntakeController::class, 'getClinics']);
    Route::get('emergency/services', [EmergencyIntakeController::class, 'getServices']);
    Route::get('emergency/available-beds', [EmergencyIntakeController::class, 'getEmergencyBeds']);
    Route::get('emergency/search-patient', [EmergencyIntakeController::class, 'searchPatient']);
    Route::get('emergency/morgue-services', [MorgueController::class, 'getServices']);
    Route::post('emergency/intake', [EmergencyIntakeController::class, 'intake']);
    Route::get('services/lab', [ReceptionWorkbenchController::class, 'getLabServices']);
    Route::get('services/imaging', [ReceptionWorkbenchController::class, 'getImagingServices']);

    // Ward admission request (doctor Admit button; reception admits from emergency)
    Route::get('ward-availability', [WardController::class, 'getAvailability']);

    // Admissions module (reception Admissions tab; doctor header admission status)
    Route::get('patient/{id}/admissions', [AdmissionModuleController::class, 'getPatientAdmissions']);
    Route::get('admission/{id}/detail', [AdmissionModuleController::class, 'getAdmissionDetail']);
    Route::post('admission-requests', [AdmissionRequestController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Mobile Patient Routes — Read-Only Health Records
|--------------------------------------------------------------------------
*/
Route::prefix('mobile/patient')->middleware('auth:sanctum')->group(function () {
    Route::get('profile', [MobilePatientController::class, 'myProfile']);
    Route::put('profile', [MobilePatientController::class, 'updateProfile']);
    Route::post('change-password', [MobilePatientController::class, 'changePassword']);
    Route::get('encounters', [MobilePatientController::class, 'myEncounters']);
    Route::get('encounters/{encounter}', [MobilePatientController::class, 'encounterDetail']);
    Route::get('vitals', [MobilePatientController::class, 'myVitals']);
    Route::get('lab-results', [MobilePatientController::class, 'myLabResults']);
    Route::get('imaging-results', [MobilePatientController::class, 'myImagingResults']);
    Route::get('prescriptions', [MobilePatientController::class, 'myPrescriptions']);
    Route::get('procedures', [MobilePatientController::class, 'myProcedures']);
    Route::get('admissions', [MobilePatientController::class, 'myAdmissions']);
    Route::get('referrals', [MobilePatientController::class, 'myReferrals']);
    Route::get('appointments', [MobilePatientController::class, 'myAppointments']);
});

/*
|--------------------------------------------------------------------------
| Mobile Chat Routes — Doctor (full chat system)
|--------------------------------------------------------------------------
*/
Route::prefix('mobile/doctor/chat')->middleware(['auth:sanctum', 'native.workspace:global'])->group(function () {
    Route::get('conversations', [MobileChatController::class, 'getConversations']);
    Route::get('messages/{conversationId}', [MobileChatController::class, 'getMessages']);
    Route::post('send', [MobileChatController::class, 'sendMessage']);
    Route::post('create', [MobileChatController::class, 'createConversation']);
    Route::post('mark-read/{conversationId}', [MobileChatController::class, 'markAsRead']);
    Route::get('unread-count', [MobileChatController::class, 'unreadCount']);
    Route::delete('messages/{messageId}', [MobileChatController::class, 'deleteMessage']);
    Route::post('archive/{conversationId}', [MobileChatController::class, 'archiveConversation']);
    Route::post('unarchive/{conversationId}', [MobileChatController::class, 'unarchiveConversation']);
    Route::get('search-users', [MobileChatController::class, 'searchUsers']);
});

/*
|--------------------------------------------------------------------------
| Mobile Chat Routes — Patient (restricted to encounter doctors only)
|--------------------------------------------------------------------------
*/
Route::prefix('mobile/patient/chat')->middleware('auth:sanctum')->group(function () {
    Route::get('my-doctors', [MobileChatController::class, 'myDoctors']);
    Route::get('conversations', [MobileChatController::class, 'getConversations']);
    Route::get('messages/{conversationId}', [MobileChatController::class, 'getMessages']);
    Route::post('send', [MobileChatController::class, 'sendMessage']);
    Route::post('create', [MobileChatController::class, 'createConversation']);
    Route::post('mark-read/{conversationId}', [MobileChatController::class, 'markAsRead']);
    Route::get('unread-count', [MobileChatController::class, 'unreadCount']);
    Route::delete('messages/{messageId}', [MobileChatController::class, 'deleteMessage']);
});

/*
|--------------------------------------------------------------------------
| Multi-Standard Interoperability Routes (FHIR R4, openEHR, OpenEMR, NDPA)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/interop.php';

/*
|--------------------------------------------------------------------------
| AI Scribe Embeddable SDK Routes (/api/sdk/*)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/sdk.php';

/*
|--------------------------------------------------------------------------
| Staff app: Staff Supplies (requisitions) and My Stock
|--------------------------------------------------------------------------
| The Staff Workspace section of the web sidebar, so any active staff member. The web controllers decide what each
| person may see and do (candidate stores, store governance gates); this only exposes them.
*/
Route::prefix('mobile/stock')->middleware(['auth:sanctum', 'native.workspace:staff_workspace'])->group(function () {
    $req = \App\Http\Controllers\StoreRequisitionController::class;
    $ms = \App\Http\Controllers\API\MobileStoreController::class;
    $su = \App\Http\Controllers\StockUtilizationController::class;

    Route::get('context', [$ms, 'context']);
    Route::get('search-products', [$ms, 'searchProducts']);

    // Requisitions
    Route::get('requisitions', [$req, 'index']);
    Route::get('requisitions/form-data', [$ms, 'requisitionFormData']);
    Route::post('requisitions', [$req, 'store']);
    Route::get('requisitions/{requisition}', [$ms, 'requisition'])->whereNumber('requisition');
    Route::put('requisitions/{requisition}', [$req, 'update'])->whereNumber('requisition');
    Route::post('requisitions/{requisition}/approve', [$req, 'approve'])->whereNumber('requisition');
    Route::post('requisitions/{requisition}/reject', [$req, 'reject'])->whereNumber('requisition');
    Route::post('requisitions/{requisition}/cancel', [$req, 'cancel'])->whereNumber('requisition');
    Route::post('requisitions/{requisition}/fulfill', [$req, 'fulfill'])->whereNumber('requisition');
    Route::get('requisitions/{requisition}/available-batches', [$req, 'getAvailableBatches'])->whereNumber('requisition');
    Route::patch('requisitions/{requisition}/items/{item}/approve', [$req, 'approveItem'])->whereNumber(['requisition', 'item']);
    Route::patch('requisitions/{requisition}/items/{item}/reject', [$req, 'rejectItem'])->whereNumber(['requisition', 'item']);
    Route::patch('requisitions/{requisition}/items/{item}/reverse', [$req, 'reverseItem'])->whereNumber(['requisition', 'item']);
    Route::patch('requisitions/{requisition}/items/{item}/mark-returned', [$req, 'markItemReturned'])->whereNumber(['requisition', 'item']);

    // My Stock and utilisation
    Route::get('my-stock/context', [$ms, 'myStockContext']);
    Route::get('my-stock/products', [$su, 'getProducts']);
    Route::get('my-stock/batches', [$su, 'getBatches']);
    Route::get('my-stock/patients', [$su, 'searchPatients']);
    Route::get('my-stock/performers', [$su, 'searchPerformers']);
    Route::get('my-stock/tariff-preview', [$su, 'getTariffPreview']);
    Route::post('my-stock/utilize', [$su, 'utilize']);
    Route::get('my-stock/history', [$su, 'history']);
});

/*
|--------------------------------------------------------------------------
| Staff app: Store workspace (Store Workbench, tally card, purchase orders, returns, damages, masters, governance)
|--------------------------------------------------------------------------
| Guard of the web sidebar's Store section. Actions go to the web controllers, so the observers post the same
| journals; views are answered as JSON by MobileStoreController.
*/
Route::prefix('mobile/store')->middleware(['auth:sanctum', 'native.workspace:store'])->group(function () {
    $ms = \App\Http\Controllers\API\MobileStoreController::class;
    $wb = \App\Http\Controllers\StoreWorkbenchController::class;
    $po = \App\Http\Controllers\PurchaseOrderController::class;
    $dm = \App\Http\Controllers\StoreDamagesController::class;
    $rr = \App\Http\Controllers\StoreRequisitionReturnController::class;
    $pr = \App\Http\Controllers\PurchaseOrderReturnController::class;
    $ir = \App\Http\Controllers\InventoryReportController::class;

    Route::get('context', [$ms, 'context']);
    Route::get('dashboard', [$ms, 'dashboard']);
    Route::get('search-products', [$ms, 'searchProducts']);

    // Stock and reports
    Route::get('stock-overview', [$wb, 'stockOverview']);
    Route::get('product/{product}/batches', [$ms, 'productBatches'])->whereNumber('product');
    Route::get('reports/expiry', [$ms, 'expiry']);
    Route::get('reports/stock-value', [$ms, 'stockValue']);
    Route::get('reports/summary', [$ir, 'getSummary']);
    Route::get('reports/drill-down', [$ir, 'getDrillDown']);

    // Tally card
    Route::get('tally-card/data', [$wb, 'tallyCardData']);
    Route::get('tally-card/page', [$wb, 'tallyCardPage']);
    Route::get('tally-card/pending-actions', [$wb, 'pendingActions']);
    Route::get('batch-availability', [$wb, 'getBatchAvailability']);
    Route::get('store-batches', [$wb, 'getStoreBatches']);
    Route::post('journal-preview', [$wb, 'previewJournalImpact']);

    // Batch and stock actions (the tally card toolbar)
    Route::post('manual-batch', [$wb, 'createManualBatch']);
    Route::post('batch/{batch}/adjust', [$wb, 'processAdjustment'])->whereNumber('batch');
    Route::post('batch/{batch}/write-off-expired', [$wb, 'writeOffExpired'])->whereNumber('batch');
    Route::post('batch/{batch}/write-off-damaged', [$wb, 'writeOffDamaged'])->whereNumber('batch');
    Route::post('batch/{batch}/update', [$wb, 'updateBatch'])->whereNumber('batch');
    Route::post('batch/{batch}/void', [$wb, 'voidBatch'])->whereNumber('batch');
    Route::post('batch/{batch}/return', [$wb, 'returnBatch'])->whereNumber('batch');
    Route::post('adjustment/{transaction}/void', [$wb, 'voidAdjustment'])->whereNumber('transaction');

    // Purchase orders
    Route::get('purchase-orders', [$po, 'index']);
    Route::post('purchase-orders', [$po, 'store']);
    Route::get('purchase-orders/search-products', [$po, 'searchProducts']);
    Route::get('purchase-orders/{purchaseOrder}', [$ms, 'purchaseOrder'])->whereNumber('purchaseOrder');
    Route::put('purchase-orders/{purchaseOrder}', [$po, 'update'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/submit', [$po, 'submit'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/approve', [$po, 'approve'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [$po, 'cancel'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/receive', [$po, 'receive'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/payment', [$po, 'recordPayment'])->whereNumber('purchaseOrder');

    // Store damages
    Route::get('damages', [$dm, 'datatables']);
    Route::get('damages/search-products', [$dm, 'searchProducts']);
    Route::get('damages/batches', [$dm, 'getBatches']);
    Route::get('damages/recent-batches', [$dm, 'getRecentBatches']);
    Route::post('damages', [$dm, 'store']);
    Route::get('damages/{id}', [$dm, 'show'])->whereNumber('id');
    Route::post('damages/{id}/approve', [$dm, 'approve'])->whereNumber('id');
    Route::post('damages/{id}/reject', [$dm, 'reject'])->whereNumber('id');

    // Requisition returns
    Route::get('requisition-returns', [$rr, 'datatables']);
    Route::get('requisition-returns/search-requisitions', [$rr, 'searchRequisitions']);
    Route::get('requisition-returns/requisition-items', [$rr, 'getRequisitionItems']);
    Route::get('requisition-returns/batches-for-product', [$rr, 'getBatchesForProduct']);
    Route::post('requisition-returns', [$rr, 'store']);
    Route::get('requisition-returns/{id}', [$rr, 'show'])->whereNumber('id');
    Route::post('requisition-returns/{id}/approve', [$rr, 'approve'])->whereNumber('id');
    Route::post('requisition-returns/{id}/reject', [$rr, 'reject'])->whereNumber('id');

    // PO returns
    Route::get('po-returns', [$pr, 'datatables']);
    Route::get('po-returns/search-pos', [$pr, 'searchPOs']);
    Route::get('po-returns/po-items', [$pr, 'getPOItems']);
    Route::get('po-returns/batches-for-item', [$pr, 'getBatchesForItem']);
    Route::post('po-returns', [$pr, 'store']);
    Route::get('po-returns/{id}', [$pr, 'show'])->whereNumber('id');
    Route::post('po-returns/{id}/approve', [$pr, 'approve'])->whereNumber('id');
    Route::post('po-returns/{id}/reject', [$pr, 'reject'])->whereNumber('id');

    // Store expenses: the Store section's Expenses link (a store keeper is not in the accounting group). Same
    // ExpenseController, so its expenses.* permissions decide, exactly as on the web.
    $ex = \App\Http\Controllers\ExpenseController::class;
    Route::get('expenses', [$ex, 'index']);
    Route::post('expenses', [$ex, 'store']);
    Route::get('expenses/{expense}', [$ex, 'show'])->whereNumber('expense');
    Route::put('expenses/{expense}', [$ex, 'update'])->whereNumber('expense');
    Route::post('expenses/{expense}/approve', [$ex, 'approve'])->whereNumber('expense');
    Route::post('expenses/{expense}/reject', [$ex, 'reject'])->whereNumber('expense');
    Route::post('expenses/{expense}/void', [$ex, 'void'])->whereNumber('expense');

    // Masters: reads are adapters, writes are the web controllers
    $mm = \App\Http\Controllers\API\MobileStoreMastersController::class;
    $sc = \App\Http\Controllers\StoreController::class;
    $pc = \App\Http\Controllers\ProductController::class;
    $cc = \App\Http\Controllers\ProductCategoryController::class;
    $sp = \App\Http\Controllers\SupplierController::class;
    $sg = \App\Http\Controllers\StoreGovernanceController::class;
    Route::get('masters/options', [$mm, 'options']);
    Route::get('masters/{kind}', [$mm, 'index'])->where('kind', 'stores|products|categories|suppliers');
    Route::get('masters/{kind}/{id}', [$mm, 'show'])->where('kind', 'stores|products|categories|suppliers')->whereNumber('id');
    Route::post('masters/stores', [$sc, 'store']);
    Route::put('masters/stores/{id}', [$sc, 'update'])->whereNumber('id');
    Route::post('masters/products', [$pc, 'store']);
    Route::put('masters/products/{id}', [$pc, 'update'])->whereNumber('id');
    Route::post('masters/products/{id}/toggle-status', [$pc, 'toggleStatus'])->whereNumber('id');
    Route::post('masters/categories', [$cc, 'store']);
    Route::put('masters/categories/{id}', [$cc, 'update'])->whereNumber('id');
    Route::delete('masters/categories/{id}', [$cc, 'destroy'])->whereNumber('id');
    Route::post('masters/suppliers', [$sp, 'store']);
    Route::put('masters/suppliers/{supplier}', [$sp, 'update'])->whereNumber('supplier');
    Route::delete('masters/suppliers/{supplier}', [$sp, 'destroy'])->whereNumber('supplier');

    // Store Governance (needs store-governance.view / .manage, as the web)
    Route::get('governance/stores', [$mm, 'governanceStores']);
    Route::post('governance/stores/{store}', [$sg, 'updateStore'])->whereNumber('store');
    Route::get('governance/lane-matrix', [$sg, 'laneMatrix']);
    Route::post('governance/lane-matrix', [$sg, 'updateLane']);
    Route::get('governance/context-rules', [$mm, 'contextRules']);
    Route::post('governance/context-rules', [$sg, 'saveContextRule']);
    Route::delete('governance/context-rules/{rule}', [$sg, 'deleteContextRule'])->whereNumber('rule');
    Route::post('governance/context-rules/test', [$sg, 'testContextResolution']);

    Route::get('banks', [\App\Http\Controllers\BankController::class, 'getActiveBanks']);

    // Suppliers lookup used by the forms
    Route::get('suppliers/search', [\App\Http\Controllers\SupplierController::class, 'search']);
});

/*
|--------------------------------------------------------------------------
| Staff app: Accounting (journals, chart of accounts, periods, reports, credit notes, expenses and payables,
| patient deposits, petty cash, transfers, banks, KPIs)
|--------------------------------------------------------------------------
| Guard of the web sidebar's Accounting section. The web controllers keep their own permission middleware
| (accounting.journal.*, reports.*, expenses.*), so a route here is exactly as strict as its web page. Views the web
| renders as Blade are answered as JSON by MobileAccountingController from the same services.
*/
Route::prefix('mobile/accounting')->middleware(['auth:sanctum', 'native.workspace:accounting', 'mobile.json'])->group(function () {
    $ma = \App\Http\Controllers\API\MobileAccountingController::class;
    $je = \App\Http\Controllers\Accounting\JournalEntryController::class;
    $coa = \App\Http\Controllers\Accounting\ChartOfAccountsController::class;
    $cn = \App\Http\Controllers\Accounting\CreditNoteController::class;
    $ac = \App\Http\Controllers\Accounting\AccountingController::class;
    $ex = \App\Http\Controllers\ExpenseController::class;
    $po = \App\Http\Controllers\PurchaseOrderController::class;

    Route::get('dashboard', [$ma, 'dashboard']);
    Route::get('accounts', [$ma, 'accounts']);
    Route::get('account-groups', [$ma, 'accountGroups']);
    Route::get('petty-cash/custodians', [$ma, 'staffSearch']);
    Route::get('banks', [\App\Http\Controllers\BankController::class, 'getActiveBanks']);

    // ── Finance modules: the web controllers' own methods (their role and permission rules apply as on the web) ──
    $fa = \App\Http\Controllers\Accounting\FixedAssetController::class;
    Route::get('fixed-assets/datatable', [$fa, 'datatable']);
    Route::get('fixed-assets/categories', [$fa, 'categories']);
    Route::post('fixed-assets/categories', [$fa, 'storeCategory']);
    Route::get('fixed-assets/categories/{category}/defaults', [$fa, 'getCategoryDefaults'])->whereNumber('category');
    Route::get('fixed-assets/search', [$fa, 'searchAssets']);
    Route::post('fixed-assets/depreciation/run', [$fa, 'runDepreciation']);
    Route::post('fixed-assets', [$fa, 'store']);
    Route::get('fixed-assets/{fixedAsset}', [$fa, 'getAsset'])->whereNumber('fixedAsset');
    Route::put('fixed-assets/{fixedAsset}', [$fa, 'update'])->whereNumber('fixedAsset');
    Route::get('fixed-assets/{fixedAsset}/depreciation-history', [$fa, 'getDepreciationHistory'])->whereNumber('fixedAsset');
    Route::post('fixed-assets/{fixedAsset}/void', [$fa, 'void'])->whereNumber('fixedAsset');
    Route::post('fixed-assets/{fixedAsset}/dispose', [$fa, 'dispose'])->whereNumber('fixedAsset');

    $cx = \App\Http\Controllers\Accounting\CapexController::class;
    Route::get('capex/datatable', [$cx, 'datatable']);
    Route::get('capex', [$cx, 'index']);
    Route::get('capex/budget-overview', [$cx, 'budgetOverview']);
    Route::post('capex/budget', [$cx, 'storeBudget']);
    Route::post('capex', [$cx, 'store']);
    Route::get('capex/{capex}', [$cx, 'show'])->whereNumber('capex');
    Route::put('capex/{capex}', [$cx, 'update'])->whereNumber('capex');
    foreach (['submit' => 'submit', 'approve' => 'approve', 'reject' => 'reject', 'request-revision' => 'requestRevision', 'start' => 'startExecution', 'expense' => 'recordExpense', 'complete' => 'complete'] as $path => $method) {
        Route::post("capex/{capex}/{$path}", [$cx, $method])->whereNumber('capex');
    }

    $bg = \App\Http\Controllers\Accounting\BudgetController::class;
    Route::get('budgets', [$bg, 'index']);
    Route::get('budgets/datatable', [$bg, 'datatable']);
    Route::get('budgets/variance-report', [$bg, 'varianceReport']);
    Route::post('budgets', [$bg, 'store']);
    Route::get('budgets/{budget}', [$bg, 'show'])->whereNumber('budget');
    Route::put('budgets/{budget}', [$bg, 'update'])->whereNumber('budget');
    foreach (['submit', 'approve', 'reject', 'unapprove', 'lock'] as $m) {
        Route::post("budgets/{budget}/{$m}", [$bg, $m])->whereNumber('budget');
    }

    $sr = \App\Http\Controllers\Accounting\StatutoryRemittanceController::class;
    Route::get('statutory-remittances', [$sr, 'index']);
    Route::get('statutory-remittances/balances', [$sr, 'getOutstandingBalances']);
    Route::post('statutory-remittances', [$sr, 'store']);
    Route::get('statutory-remittances/{statutoryRemittance}', [$sr, 'show'])->whereNumber('statutoryRemittance');
    Route::put('statutory-remittances/{statutoryRemittance}', [$sr, 'update'])->whereNumber('statutoryRemittance');
    Route::delete('statutory-remittances/{statutoryRemittance}', [$sr, 'destroy'])->whereNumber('statutoryRemittance');
    foreach (['submit', 'approve', 'pay', 'void'] as $m) {
        Route::post("statutory-remittances/{statutoryRemittance}/{$m}", [$sr, $m])->whereNumber('statutoryRemittance');
    }

    $cf = \App\Http\Controllers\Accounting\CashFlowForecastController::class;
    Route::get('cash-flow-forecast', [$cf, 'index']);
    Route::get('cash-flow-forecast/datatable', [$cf, 'datatable']);
    Route::post('cash-flow-forecast', [$cf, 'store']);
    Route::get('cash-flow-forecast/patterns', [$cf, 'patterns']);
    Route::post('cash-flow-forecast/patterns', [$cf, 'storePattern']);
    Route::put('cash-flow-forecast/patterns/{pattern}', [$cf, 'updatePattern'])->whereNumber('pattern');
    Route::delete('cash-flow-forecast/patterns/{pattern}', [$cf, 'deletePattern'])->whereNumber('pattern');
    Route::post('cash-flow-forecast/patterns/{pattern}/toggle', [$cf, 'togglePattern'])->whereNumber('pattern');
    Route::get('cash-flow-forecast/periods/{period}/edit', [$cf, 'editPeriod'])->whereNumber('period');
    Route::put('cash-flow-forecast/periods/{period}', [$cf, 'updatePeriod'])->whereNumber('period');
    Route::post('cash-flow-forecast/periods/{period}/items', [$cf, 'addItem'])->whereNumber('period');
    Route::put('cash-flow-forecast/periods/{period}/actuals', [$cf, 'updateActuals'])->whereNumber('period');
    Route::get('cash-flow-forecast/{forecast}', [$cf, 'show'])->whereNumber('forecast');
    Route::post('cash-flow-forecast/{forecast}/activate', [$cf, 'activate'])->whereNumber('forecast');
    Route::post('cash-flow-forecast/{forecast}/apply-patterns', [$cf, 'applyPatterns'])->whereNumber('forecast');

    $ob = \App\Http\Controllers\Accounting\OpeningBalanceController::class;
    Route::get('opening-balances/datatable', [$ob, 'datatable']);
    Route::get('opening-balances/accounts', [$ob, 'getAccounts']);
    Route::post('opening-balances', [$ob, 'store']);
    Route::put('opening-balances/{accountId}', [$ob, 'update'])->whereNumber('accountId');

    $lb = \App\Http\Controllers\Accounting\LiabilityController::class;
    Route::get('liabilities', [$lb, 'index']);
    Route::get('liabilities/datatable', [$lb, 'datatable']);
    Route::get('liabilities/create', [$lb, 'create']);
    Route::post('liabilities', [$lb, 'store']);
    Route::get('liabilities/{liability}', [$lb, 'show'])->whereNumber('liability');
    Route::get('liabilities/{liability}/edit', [$lb, 'edit'])->whereNumber('liability');
    Route::put('liabilities/{liability}', [$lb, 'update'])->whereNumber('liability');
    Route::get('liabilities/{liability}/payment', [$lb, 'payment'])->whereNumber('liability');
    Route::post('liabilities/{liability}/payment', [$lb, 'recordPayment'])->whereNumber('liability');
    Route::get('liabilities/{liability}/schedule', [$lb, 'amortizationSchedule'])->whereNumber('liability');

    $ls = \App\Http\Controllers\Accounting\LeaseController::class;
    Route::get('leases', [$ls, 'index']);
    Route::get('leases/datatable', [$ls, 'datatable']);
    Route::get('leases/create', [$ls, 'create']);
    Route::post('leases', [$ls, 'store']);
    Route::get('leases/reports/ifrs16', [$ls, 'ifrs16Report']);
    Route::get('leases/{lease}', [$ls, 'show'])->whereNumber('lease');
    Route::get('leases/{lease}/edit', [$ls, 'edit'])->whereNumber('lease');
    Route::put('leases/{lease}', [$ls, 'update'])->whereNumber('lease');
    Route::get('leases/{lease}/payment', [$ls, 'payment'])->whereNumber('lease');
    Route::post('leases/{lease}/payment', [$ls, 'recordPayment'])->whereNumber('lease');
    Route::get('leases/{lease}/modification', [$ls, 'modification'])->whereNumber('lease');
    Route::post('leases/{lease}/modification', [$ls, 'storeModification'])->whereNumber('lease');
    Route::post('leases/{lease}/terminate', [$ls, 'terminate'])->whereNumber('lease');
    Route::get('leases/{lease}/schedule', [$ls, 'paymentSchedule'])->whereNumber('lease');

    $cc = \App\Http\Controllers\Accounting\CostCenterController::class;
    Route::get('cost-centers', [$cc, 'index']);
    Route::get('cost-centers/datatable', [$cc, 'datatable']);
    Route::get('cost-centers/create', [$cc, 'create']);
    Route::post('cost-centers', [$cc, 'store']);
    Route::get('cost-centers/allocations/index', [$cc, 'allocations']);
    Route::post('cost-centers/allocations', [$cc, 'storeAllocation']);
    Route::post('cost-centers/allocations/run', [$cc, 'runAllocation']);
    Route::get('cost-centers/{costCenter}', [$cc, 'show'])->whereNumber('costCenter');
    Route::get('cost-centers/{costCenter}/edit', [$cc, 'edit'])->whereNumber('costCenter');
    Route::put('cost-centers/{costCenter}', [$cc, 'update'])->whereNumber('costCenter');
    Route::get('cost-centers/{costCenter}/budgets', [$cc, 'budgets'])->whereNumber('costCenter');
    Route::post('cost-centers/{costCenter}/budgets', [$cc, 'storeBudget'])->whereNumber('costCenter');
    Route::put('cost-centers/{costCenter}/budgets/{budget}', [$cc, 'updateBudget'])->whereNumber('costCenter')->whereNumber('budget');
    Route::get('cost-centers/{costCenter}/report', [$cc, 'report'])->whereNumber('costCenter');

    $kp = \App\Http\Controllers\Accounting\KpiController::class;
    Route::get('kpi', [$kp, 'dashboard']);
    Route::get('kpi/definitions', [$kp, 'index']);
    Route::get('kpi/datatable', [$kp, 'datatable']);
    Route::get('kpi/create', [$kp, 'create']);
    Route::post('kpi', [$kp, 'store']);
    Route::post('kpi/calculate', [$kp, 'calculate']);
    Route::get('kpi/alerts', [$kp, 'alerts']);
    Route::post('kpi/alerts/{alert}/acknowledge', [$kp, 'acknowledgeAlert'])->whereNumber('alert');
    Route::get('kpi/configure', [$kp, 'configure']);
    Route::post('kpi/configure', [$kp, 'saveConfig']);
    Route::get('kpi/{kpi}/edit', [$kp, 'edit'])->whereNumber('kpi');
    Route::put('kpi/{kpi}', [$kp, 'update'])->whereNumber('kpi');
    Route::post('kpi/{kpi}/calculate', [$kp, 'calculateSingle'])->whereNumber('kpi');
    Route::get('kpi/{kpi}/history', [$kp, 'history'])->whereNumber('kpi');

    $br = \App\Http\Controllers\Accounting\BankReconciliationController::class;
    Route::get('bank-reconciliation', [$br, 'index']);
    Route::get('bank-reconciliation/datatable', [$br, 'datatable']);
    Route::get('bank-reconciliation/create/{bank}', [$br, 'create'])->whereNumber('bank');
    Route::post('bank-reconciliation/store/{bank}', [$br, 'store'])->whereNumber('bank');
    Route::get('bank-reconciliation/{reconciliation}', [$br, 'show'])->whereNumber('reconciliation');
    Route::get('bank-reconciliation/{reconciliation}/edit', [$br, 'edit'])->whereNumber('reconciliation');
    Route::put('bank-reconciliation/{reconciliation}', [$br, 'update'])->whereNumber('reconciliation');
    Route::put('bank-reconciliation/{reconciliation}/update-details', [$br, 'updateDetails'])->whereNumber('reconciliation');
    Route::get('bank-reconciliation/{reconciliation}/statements', [$br, 'getStatements'])->whereNumber('reconciliation');
    Route::post('bank-reconciliation/{reconciliation}/statement-item', [$br, 'addStatementItem'])->whereNumber('reconciliation');
    foreach (['match' => 'matchItems', 'unmatch' => 'unmatchItems', 'outstanding' => 'markOutstanding', 'submit-review' => 'submitForReview', 'finalize' => 'finalize', 'approve' => 'approve'] as $path => $method) {
        Route::post("bank-reconciliation/{reconciliation}/{$path}", [$br, $method])->whereNumber('reconciliation');
    }

    // File downloads: the web controllers' own PDF and Excel exports
    Route::get('fixed-assets/export', [$fa, 'export']);
    Route::get('capex/export', [$cx, 'export']);
    Route::get('budgets/variance-report/export', [$bg, 'varianceReportExport']);
    Route::get('budgets/{budget}/export', [$bg, 'export'])->whereNumber('budget');
    Route::get('liabilities/export/pdf', [$lb, 'exportPdf']);
    Route::get('liabilities/export/excel', [$lb, 'exportExcel']);
    Route::get('leases/export/pdf', [$ls, 'exportPortfolioPdf']);
    Route::get('leases/export/excel', [$ls, 'exportPortfolioExcel']);
    Route::get('leases/{lease}/export/pdf', [$ls, 'exportDetailPdf'])->whereNumber('lease');
    Route::get('leases/{lease}/export/excel', [$ls, 'exportDetailExcel'])->whereNumber('lease');
    Route::get('leases/{lease}/schedule/export/pdf', [$ls, 'exportSchedulePdf'])->whereNumber('lease');
    Route::get('leases/{lease}/schedule/export/excel', [$ls, 'exportScheduleExcel'])->whereNumber('lease');
    Route::get('cost-centers/{costCenter}/report/pdf', [$cc, 'exportReportPdf'])->whereNumber('costCenter');
    Route::get('cost-centers/{costCenter}/report/excel', [$cc, 'exportReportExcel'])->whereNumber('costCenter');
    Route::get('cash-flow-forecast/{forecast}/export/pdf', [$cf, 'exportPdf'])->whereNumber('forecast');
    Route::get('cash-flow-forecast/{forecast}/export/excel', [$cf, 'exportExcel'])->whereNumber('forecast');
    Route::get('bank-reconciliation/{reconciliation}/export/pdf', [$br, 'exportPdf'])->whereNumber('reconciliation');
    Route::post('bank-reconciliation/{reconciliation}/upload-statement', [$br, 'uploadStatement'])->whereNumber('reconciliation');
    Route::get('bank-reconciliation/{reconciliation}/statement/{import}', [$br, 'getStatementContent'])->whereNumber('reconciliation')->whereNumber('import');
    Route::delete('bank-reconciliation/{reconciliation}/statement/{import}', [$br, 'deleteStatement'])->whereNumber('reconciliation')->whereNumber('import');

    $bk = \App\Http\Controllers\BankController::class;
    Route::get('banks/list', [$bk, 'list']);
    Route::post('banks', [$bk, 'store']);
    Route::put('banks/{bank}', [$bk, 'update'])->whereNumber('bank');
    Route::delete('banks/{bank}', [$bk, 'destroy'])->whereNumber('bank');

    // Reports
    // Saved report filters (the web Reports page's own save, load and delete); the list is the page's data as JSON
    Route::get('reports/filters', [$ma, 'savedFilters']);
    Route::post('reports/filters', [\App\Http\Controllers\Accounting\ReportController::class, 'saveFilter']);
    Route::get('reports/filters/{id}', [\App\Http\Controllers\Accounting\ReportController::class, 'loadFilter'])->whereNumber('id');
    Route::delete('reports/filters/{id}', [\App\Http\Controllers\Accounting\ReportController::class, 'deleteFilter'])->whereNumber('id');
    Route::get('reports/{name}/download', [$ma, 'reportDownload'])->where('name', '[a-z-]+');
    Route::get('reports/{name}', [$ma, 'report'])->where('name', '[a-z-]+');

    // Fiscal periods
    Route::get('periods', [$ma, 'periods']);
    Route::post('fiscal-years', [$ac, 'createFiscalYear']);
    Route::post('periods/{periodId}/close', [$ac, 'closePeriod'])->whereNumber('periodId');
    Route::post('fiscal-years/{yearId}/close', [$ac, 'closeFiscalYear'])->whereNumber('yearId');

    // Journal entries
    Route::get('journal-entries', [$je, 'datatable']);
    Route::post('journal-entries', [$je, 'store']);
    Route::post('journal-entries/bulk-approve', [$je, 'bulkApprove']);
    Route::post('journal-entries/bulk-post', [$je, 'bulkPost']);
    Route::get('journal-entries/{id}', [$ma, 'journalEntry'])->whereNumber('id');
    Route::put('journal-entries/{id}', [$je, 'update'])->whereNumber('id');
    Route::delete('journal-entries/{id}', [$je, 'destroy'])->whereNumber('id');
    Route::post('journal-entries/{id}/submit', [$je, 'submit'])->whereNumber('id');
    Route::post('journal-entries/{id}/approve', [$je, 'approve'])->whereNumber('id');
    Route::post('journal-entries/{id}/reject', [$je, 'reject'])->whereNumber('id');
    Route::post('journal-entries/{id}/post', [$je, 'post'])->whereNumber('id');
    Route::post('journal-entries/{id}/reverse', [$je, 'reverse'])->whereNumber('id');
    Route::post('journal-entries/{id}/request-edit', [$je, 'requestEdit'])->whereNumber('id');
    Route::post('journal-entries/edit-requests/{editId}/approve', [$je, 'approveEditRequest'])->whereNumber('editId');
    Route::post('journal-entries/edit-requests/{editId}/reject', [$je, 'rejectEditRequest'])->whereNumber('editId');

    // Chart of accounts
    Route::get('chart-of-accounts', [$coa, 'datatable']);
    Route::post('chart-of-accounts', [$coa, 'store']);
    Route::get('chart-of-accounts/{id}/sub-accounts', [$coa, 'subAccounts'])->whereNumber('id');
    Route::post('chart-of-accounts/{id}/sub-accounts', [$coa, 'storeSubAccount'])->whereNumber('id');
    Route::put('chart-of-accounts/{id}', [$coa, 'update'])->whereNumber('id');
    Route::post('chart-of-accounts/{id}/activate', [$coa, 'activate'])->whereNumber('id');
    Route::post('chart-of-accounts/{id}/deactivate', [$coa, 'deactivate'])->whereNumber('id');

    // Credit notes
    Route::get('credit-notes', [$cn, 'datatable']);
    Route::post('credit-notes', [$cn, 'store']);
    Route::post('credit-notes/bulk-approve', [$cn, 'bulkApprove']);
    Route::get('credit-notes/patient/{patientId}/payments', [$cn, 'getPatientPayments'])->whereNumber('patientId');
    Route::put('credit-notes/{id}', [$cn, 'update'])->whereNumber('id');
    Route::post('credit-notes/{id}/submit', [$cn, 'submit'])->whereNumber('id');
    Route::post('credit-notes/{id}/approve', [$cn, 'approve'])->whereNumber('id');
    Route::post('credit-notes/{id}/void', [$cn, 'void'])->whereNumber('id');
    Route::post('credit-notes/{id}/process', [$cn, 'process'])->whereNumber('id');

    // Expenses and payables (the same controllers as the Store section's)
    Route::get('expenses', [$ex, 'index']);
    Route::post('expenses', [$ex, 'store']);
    Route::get('expenses/{expense}', [$ex, 'show'])->whereNumber('expense');
    Route::put('expenses/{expense}', [$ex, 'update'])->whereNumber('expense');
    Route::post('expenses/{expense}/approve', [$ex, 'approve'])->whereNumber('expense');
    Route::post('expenses/{expense}/reject', [$ex, 'reject'])->whereNumber('expense');
    Route::post('expenses/{expense}/void', [$ex, 'void'])->whereNumber('expense');
    Route::get('accounts-payable', [$po, 'accountsPayable']);
    Route::get('purchase-orders/{purchaseOrder}', [\App\Http\Controllers\API\MobileStoreController::class, 'purchaseOrder'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/payment', [$po, 'recordPayment'])->whereNumber('purchaseOrder');
    Route::get('suppliers/search', [\App\Http\Controllers\SupplierController::class, 'search']);

    // Patient deposits (wallet top-ups, applying and refunding them)
    $pd = \App\Http\Controllers\Accounting\PatientDepositController::class;
    Route::get('patient-deposits', [$pd, 'datatable']);
    Route::post('patient-deposits', [$pd, 'store']);
    Route::get('patient-deposits/search-patients', [$pd, 'searchPatients']);
    Route::get('patient-deposits/patient/{patient}/summary', [$pd, 'getPatientSummary'])->whereNumber('patient');
    Route::get('patient-deposits/{patientDeposit}', [$pd, 'show'])->whereNumber('patientDeposit');
    Route::post('patient-deposits/{patientDeposit}/apply', [$pd, 'apply'])->whereNumber('patientDeposit');
    Route::post('patient-deposits/{patientDeposit}/refund', [$pd, 'refund'])->whereNumber('patientDeposit');

    // Petty cash
    $pc = \App\Http\Controllers\Accounting\PettyCashController::class;
    Route::get('petty-cash/funds', [$pc, 'fundsDatatable']);
    Route::post('petty-cash/funds', [$pc, 'fundsStore']);
    Route::put('petty-cash/funds/{fund}', [$pc, 'fundsUpdate'])->whereNumber('fund');
    Route::get('petty-cash/transactions', [$pc, 'datatable']);
    Route::get('petty-cash/funds/{fund}/transactions', [$pc, 'transactionsDatatable'])->whereNumber('fund');
    Route::post('petty-cash/funds/{fund}/disbursement', [$pc, 'disbursementStore'])->whereNumber('fund');
    Route::post('petty-cash/funds/{fund}/replenishment', [$pc, 'replenishmentStore'])->whereNumber('fund');
    Route::post('petty-cash/transactions/{transaction}/approve', [$pc, 'approve'])->whereNumber('transaction');
    Route::post('petty-cash/transactions/{transaction}/reject', [$pc, 'reject'])->whereNumber('transaction');
    Route::post('petty-cash/transactions/{transaction}/disburse', [$pc, 'disburse'])->whereNumber('transaction');
    Route::post('petty-cash/funds/{fund}/reconcile', [$pc, 'storeReconciliation'])->whereNumber('fund');
    Route::get('petty-cash/reconciliations', [$pc, 'reconciliationsDatatable']);
    Route::post('petty-cash/reconciliations/{reconciliation}/approve', [$pc, 'approveReconciliation'])->whereNumber('reconciliation');
    Route::post('petty-cash/reconciliations/{reconciliation}/reject', [$pc, 'rejectReconciliation'])->whereNumber('reconciliation');

    // Inter-account transfers
    $tr = \App\Http\Controllers\Accounting\TransferController::class;
    Route::get('transfers', [$tr, 'datatable']);
    Route::post('transfers', [$tr, 'store']);
    Route::post('transfers/{transfer}/approve', [$tr, 'approve'])->whereNumber('transfer');
    Route::post('transfers/{transfer}/reject', [$tr, 'reject'])->whereNumber('transfer');
    Route::post('transfers/{transfer}/mark-failed', [$tr, 'markFailed'])->whereNumber('transfer');
    Route::post('transfers/{transfer}/confirm-clearance', [$tr, 'confirmClearance'])->whereNumber('transfer');
    Route::post('transfers/{transfer}/cancel', [$tr, 'cancel'])->whereNumber('transfer');

    // Purchase order actions an accountant may take (the web allows ACCOUNTS on every PO route)
    Route::get('purchase-orders', [$po, 'index']);
    Route::post('purchase-orders/{purchaseOrder}/submit', [$po, 'submit'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/approve', [$po, 'approve'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [$po, 'cancel'])->whereNumber('purchaseOrder');
    Route::post('purchase-orders/{purchaseOrder}/receive', [$po, 'receive'])->whereNumber('purchaseOrder');
});

/*
|--------------------------------------------------------------------------
| Staff app: HMO Executive (workbench, reports, management)
|--------------------------------------------------------------------------
| MobileHmoController extends the web HmoWorkbenchController, so a route here is the web's own method; only the list
| the web renders as DataTables HTML is answered as JSON. Guard: the registry's `hmo` section (SUPERADMIN, ADMIN,
| HMO Executive), the same roles as the web routes.
*/
Route::prefix('mobile/hmo')->middleware(['auth:sanctum', 'native.workspace:hmo', 'mobile.json'])->group(function () {
    $wb = \App\Http\Controllers\API\MobileHmoController::class;
    $rp = \App\Http\Controllers\HmoReportsController::class;

    Route::get('context', [$wb, 'context']);
    Route::get('requests', [$wb, 'requests']);
    Route::get('queue-counts', [$wb, 'getQueueCounts']);
    Route::get('financial-summary', [$wb, 'getFinancialSummary']);
    Route::get('requests/{id}', [$wb, 'show'])->whereNumber('id');
    Route::post('requests/{id}/approve', [$wb, 'approveRequest'])->whereNumber('id');
    Route::post('requests/{id}/reject', [$wb, 'rejectRequest'])->whereNumber('id');
    Route::post('requests/{id}/reverse', [$wb, 'reverseApproval'])->whereNumber('id');
    Route::post('requests/{id}/reapprove', [$wb, 'reapproveRequest'])->whereNumber('id');
    Route::get('requests/{id}/tariff-details', [$wb, 'getTariffDetails'])->whereNumber('id');
    Route::post('requests/{id}/update-tariff', [$wb, 'updateTariffInline'])->whereNumber('id');
    Route::post('requests/{id}/submit-auth-code', [$wb, 'submitAuthCode'])->whereNumber('id');
    Route::post('requests/batch-submit-auth-code', [$wb, 'batchSubmitAuthCode']);
    Route::post('batch-approve', [$wb, 'batchApprove']);
    Route::post('batch-reject', [$wb, 'batchReject']);
    Route::post('group-approve', [$wb, 'groupApprove']);
    Route::post('group-reject', [$wb, 'groupReject']);
    Route::get('export-claims', [$wb, 'exportClaimsReport']);
    Route::get('patient/{patientId}/history', [$wb, 'getPatientHistory'])->whereNumber('patientId');
    Route::get('patient/{patientId}/vitals', [$wb, 'getPatientVitals'])->whereNumber('patientId');
    Route::get('patient/{patientId}/notes', [$wb, 'getPatientNotes'])->whereNumber('patientId');
    Route::get('patient/{patientId}/medications', [$wb, 'getPatientMedications'])->whereNumber('patientId');
    Route::get('patient/{patientId}/allergies', [$wb, 'getPatientAllergies'])->whereNumber('patientId');
    Route::get('patient/{patientId}/pending-requests', [$wb, 'getPatientPendingRequests'])->whereNumber('patientId');
    Route::get('patient/{patientId}/all-requests', [$wb, 'getPatientAllRequests'])->whereNumber('patientId');
    Route::post('patient/{patient}/update-hmo', [$wb, 'updatePatientHmo'])->whereNumber('patient');
    Route::get('search-patients', [$rp, 'searchPatients']);

    Route::get('banks', [\App\Http\Controllers\BankController::class, 'getActiveBanks']);

    // Reports and remittances
    Route::get('reports/claims', [$rp, 'getClaimsReport']);
    Route::get('reports/outstanding', [$rp, 'getOutstandingReport']);
    Route::get('reports/patient/{patientId}', [$rp, 'getPatientReport'])->whereNumber('patientId');
    Route::get('reports/patient/{patientId}/print', [$rp, 'getPatientPrintData'])->whereNumber('patientId');
    Route::get('reports/monthly', [$rp, 'getMonthlySummary']);
    Route::get('reports/utilization', [$rp, 'getUtilizationReport']);
    Route::get('reports/auth-codes', [$rp, 'getAuthCodeReport']);
    Route::get('reports/remittances', [$rp, 'getRemittances']);
    Route::post('reports/remittances', [$rp, 'storeRemittance']);
    Route::get('reports/remittances/{id}', [$rp, 'showRemittance'])->whereNumber('id');
    Route::put('reports/remittances/{id}', [$rp, 'updateRemittance'])->whereNumber('id');
    Route::delete('reports/remittances/{id}', [$rp, 'deleteRemittance'])->whereNumber('id');
    Route::post('reports/mark-submitted', [$rp, 'markAsSubmitted']);
    Route::post('reports/link-claims', [$rp, 'linkClaimsToRemittance']);
    Route::get('reports/print-data', [$rp, 'getPrintData']);
    Route::get('reports/export-excel', [$rp, 'exportExcel']);
    Route::get('reports/export-pdf', [$rp, 'exportPdf']);

    // The HMO list of patients (the web Patients page filtered to those with an HMO)
    Route::get('patients', [\App\Http\Controllers\PatientController::class, 'patientsList']);

    // Management (HMOs, Tariffs, Billing Organizations): the sidebar shows these only to admins and HMO heads (hmo_mgmt)
    Route::middleware('native.condition:hmo_mgmt')->group(function () use ($wb) {
        $hm = \App\Http\Controllers\HmoController::class;
        $tf = \App\Http\Controllers\Admin\TariffManagementController::class;
        $og = \App\Http\Controllers\OrganizationController::class;
        $ob = \App\Http\Controllers\API\MobileHmoController::class;

        Route::get('hmos', [$hm, 'listHmo']);
        Route::get('hmos/create', [$hm, 'create']);
        Route::post('hmos', [$hm, 'store']);
        Route::get('hmos/{hmo}/edit', [$hm, 'edit'])->whereNumber('hmo');
        Route::put('hmos/{hmo}', [$hm, 'update'])->whereNumber('hmo');
        Route::post('hmos/{hmo}/toggle-status', [$hm, 'toggleStatus'])->whereNumber('hmo');

        Route::get('tariffs/lookups', [$tf, 'index']);
        Route::get('tariffs/items', [$ob, 'searchItems']);
        Route::get('tariffs', [$tf, 'getTariffs']);
        Route::post('tariffs', [$tf, 'store']);
        Route::post('tariffs/bulk-update', [$tf, 'bulkUpdate']);
        Route::post('tariffs/normalize', [$tf, 'normalizeScheme']);
        Route::post('tariffs/import/preview', [$tf, 'importPreview']);
        Route::post('tariffs/import/excel', [$tf, 'importExcel']);
        Route::post('tariffs/import/csv', [$tf, 'importCsv']);
        Route::get('tariffs/scheme-hmos/{id}', [$tf, 'getSchemeHmos'])->whereNumber('id');
        Route::get('tariffs/export/csv', [$tf, 'exportCsv']);
        Route::get('tariffs/export/excel', [$tf, 'exportExcel']);
        Route::get('tariffs/overrides', [$tf, 'getOverrides']);
        Route::post('tariffs/overrides', [$tf, 'storeOverride']);
        Route::get('tariffs/overrides/item-price', [$tf, 'getItemPrice']);
        Route::delete('tariffs/overrides/{id}', [$tf, 'deleteOverride'])->whereNumber('id');
        Route::get('tariffs/{id}', [$tf, 'show'])->whereNumber('id');
        Route::put('tariffs/{id}', [$tf, 'update'])->whereNumber('id');
        Route::delete('tariffs/{id}', [$tf, 'destroy'])->whereNumber('id');

        Route::get('organizations', [$og, 'data']);
        Route::post('organizations', [$og, 'store']);
        Route::get('organizations/{organization}', [$og, 'show'])->whereNumber('organization');
        Route::put('organizations/{organization}', [$og, 'update'])->whereNumber('organization');
        Route::delete('organizations/{organization}', [$og, 'destroy'])->whereNumber('organization');
        Route::get('organizations/{organization}/bills', [$og, 'bills'])->whereNumber('organization');
        Route::post('organizations/{organization}/settle', [$og, 'settleBill'])->whereNumber('organization');
    });
});

/*
|--------------------------------------------------------------------------
| Staff app: Human Resources and Staff Self-Service
|--------------------------------------------------------------------------
| Each route is the web HR controller's own method with the web's permission middleware (generated from the web
| route list), so a person can do on the phone exactly what the web lets them do. Views the web renders as Blade
| answer JSON when the app asks (see the controllers); redirect-with-flash answers go through mobile.json.
*/
Route::prefix('mobile/hr/ess')->middleware(['auth:sanctum', 'native.workspace:staff_workspace', 'mobile.json'])->group(function () {
    Route::get('/', [\App\Http\Controllers\HR\EssController::class, 'index'])->middleware('permission:ess.access');
    Route::get('my-calendar', [\App\Http\Controllers\HR\EssController::class, 'myCalendar'])->middleware('permission:ess.access');
    Route::get('my-calendar/events', [\App\Http\Controllers\HR\EssController::class, 'myCalendarEvents'])->middleware('permission:ess.access');
    Route::get('my-disciplinary', [\App\Http\Controllers\HR\EssController::class, 'myDisciplinary'])->middleware('permission:ess.access');
    Route::get('my-disciplinary/data', [\App\Http\Controllers\HR\EssController::class, 'myDisciplinaryData'])->middleware('permission:ess.access');
    Route::get('my-disciplinary/{disciplinaryQuery}', [\App\Http\Controllers\HR\EssController::class, 'showDisciplinaryQuery'])->whereNumber('disciplinaryQuery')->middleware('permission:ess.access');
    Route::post('my-disciplinary/{disciplinaryQuery}/respond', [\App\Http\Controllers\HR\EssController::class, 'respondToDisciplinaryQuery'])->whereNumber('disciplinaryQuery')->middleware('permission:ess.access');
    Route::get('my-leave', [\App\Http\Controllers\HR\EssController::class, 'myLeave'])->middleware('permission:ess.access');
    Route::get('my-leave/request', [\App\Http\Controllers\HR\EssController::class, 'requestLeave'])->middleware('permission:ess.access');
    Route::post('my-leave/request', [\App\Http\Controllers\HR\EssController::class, 'storeLeaveRequest'])->middleware('permission:ess.access');
    Route::post('my-leave/{leaveRequest}/cancel', [\App\Http\Controllers\HR\EssController::class, 'cancelLeaveRequest'])->whereNumber('leaveRequest')->middleware('permission:ess.access');
    Route::get('my-payslips', [\App\Http\Controllers\HR\EssController::class, 'myPayslips'])->middleware('permission:ess.access')->middleware('permission:ess.view-payslips');
    Route::get('my-payslips/{payrollItem}/download', [\App\Http\Controllers\HR\EssController::class, 'downloadPayslip'])->whereNumber('payrollItem')->middleware('permission:ess.access')->middleware('permission:ess.view-payslips');
    Route::get('my-payslips/{payrollItem}/print', [\App\Http\Controllers\HR\EssController::class, 'printPayslip'])->whereNumber('payrollItem')->middleware('permission:ess.access')->middleware('permission:ess.view-payslips');
    Route::get('my-profile', [\App\Http\Controllers\HR\EssController::class, 'myProfile'])->middleware('permission:ess.access');
    Route::put('my-profile', [\App\Http\Controllers\HR\EssController::class, 'updateProfile'])->middleware('permission:ess.access');
    Route::post('my-profile/password', [\App\Http\Controllers\HR\EssController::class, 'updatePassword'])->middleware('permission:ess.access');
    Route::get('team-approvals', [\App\Http\Controllers\HR\EssController::class, 'teamApprovals'])->middleware('permission:ess.access')->middleware('permission:leave-request.supervisor-approve');
    Route::get('team-approvals/{leaveRequest}', [\App\Http\Controllers\HR\EssController::class, 'showTeamLeaveRequest'])->whereNumber('leaveRequest')->middleware('permission:ess.access')->middleware('permission:leave-request.supervisor-approve');
    Route::post('team-approvals/{leaveRequest}/approve', [\App\Http\Controllers\HR\EssController::class, 'approveTeamLeave'])->whereNumber('leaveRequest')->middleware('permission:ess.access')->middleware('permission:leave-request.supervisor-approve');
    Route::post('team-approvals/{leaveRequest}/reject', [\App\Http\Controllers\HR\EssController::class, 'rejectTeamLeave'])->whereNumber('leaveRequest')->middleware('permission:ess.access')->middleware('permission:leave-request.supervisor-approve');
    Route::get('team-calendar', [\App\Http\Controllers\HR\EssController::class, 'teamCalendar'])->middleware('permission:ess.access')->middleware('permission:leave-request.supervisor-approve');
    Route::get('team-calendar/events', [\App\Http\Controllers\HR\EssController::class, 'teamCalendarEvents'])->middleware('permission:ess.access')->middleware('permission:leave-request.supervisor-approve');
    Route::get('team-calendar/on-leave', [\App\Http\Controllers\HR\EssController::class, 'teamOnLeave'])->middleware('permission:ess.access')->middleware('permission:leave-request.supervisor-approve');
});

Route::prefix('mobile/hr')->middleware(['auth:sanctum', 'native.workspace:hr', 'mobile.json'])->group(function () {
    Route::get('cadres', [\App\Http\Controllers\HR\CadreController::class, 'index'])->middleware('permission:hr-cadres.view');
    Route::post('cadres', [\App\Http\Controllers\HR\CadreController::class, 'store'])->middleware('permission:hr-cadres.view')->middleware('permission:hr-cadres.create');
    Route::put('cadres/{cadre}', [\App\Http\Controllers\HR\CadreController::class, 'update'])->whereNumber('cadre')->middleware('permission:hr-cadres.view')->middleware('permission:hr-cadres.edit');
    Route::delete('cadres/{cadre}', [\App\Http\Controllers\HR\CadreController::class, 'destroy'])->whereNumber('cadre')->middleware('permission:hr-cadres.view')->middleware('permission:hr-cadres.delete');
    Route::get('disciplinary', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'index'])->middleware('permission:disciplinary.view');
    Route::post('disciplinary', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'store'])->middleware('permission:disciplinary.create');
    Route::get('disciplinary/create', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'create'])->middleware('permission:disciplinary.create');
    Route::get('disciplinary/{disciplinaryQuery}', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'show'])->whereNumber('disciplinaryQuery')->middleware('permission:disciplinary.view');
    Route::put('disciplinary/{disciplinaryQuery}', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'update'])->whereNumber('disciplinaryQuery')->middleware('permission:disciplinary.edit');
    Route::delete('disciplinary/{disciplinaryQuery}', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'destroy'])->whereNumber('disciplinaryQuery')->middleware('permission:disciplinary.delete');
    Route::post('disciplinary/{disciplinaryQuery}/decide', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'decide'])->whereNumber('disciplinaryQuery')->middleware('permission:disciplinary.decide');
    Route::get('disciplinary/{disciplinaryQuery}/edit', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'edit'])->whereNumber('disciplinaryQuery')->middleware('permission:disciplinary.edit');
    Route::post('disciplinary/{disciplinaryQuery}/respond', [\App\Http\Controllers\HR\DisciplinaryQueryController::class, 'respond'])->whereNumber('disciplinaryQuery')->middleware('permission:disciplinary.respond');
    Route::get('follow-ups', [\App\Http\Controllers\HR\StaffFollowUpController::class, 'index'])->middleware('permission:hr-follow-ups.view');
    Route::post('follow-ups', [\App\Http\Controllers\HR\StaffFollowUpController::class, 'store'])->middleware('permission:hr-follow-ups.view')->middleware('permission:hr-follow-ups.create');
    Route::delete('follow-ups/{followUp}', [\App\Http\Controllers\HR\StaffFollowUpController::class, 'destroy'])->whereNumber('followUp')->middleware('permission:hr-follow-ups.view')->middleware('permission:hr-follow-ups.create');
    Route::post('follow-ups/{followUp}/resolve', [\App\Http\Controllers\HR\StaffFollowUpController::class, 'resolve'])->whereNumber('followUp')->middleware('permission:hr-follow-ups.view')->middleware('permission:hr-follow-ups.resolve');
    Route::post('follow-ups/{followUp}/start', [\App\Http\Controllers\HR\StaffFollowUpController::class, 'start'])->whereNumber('followUp')->middleware('permission:hr-follow-ups.view')->middleware('permission:hr-follow-ups.resolve');
    Route::get('grade-levels', [\App\Http\Controllers\HR\GradeLevelController::class, 'index'])->middleware('permission:hr-grade-levels.view');
    Route::post('grade-levels', [\App\Http\Controllers\HR\GradeLevelController::class, 'store'])->middleware('permission:hr-grade-levels.view')->middleware('permission:hr-grade-levels.create');
    Route::put('grade-levels/{gradeLevel}', [\App\Http\Controllers\HR\GradeLevelController::class, 'update'])->whereNumber('gradeLevel')->middleware('permission:hr-grade-levels.view')->middleware('permission:hr-grade-levels.edit');
    Route::delete('grade-levels/{gradeLevel}', [\App\Http\Controllers\HR\GradeLevelController::class, 'destroy'])->whereNumber('gradeLevel')->middleware('permission:hr-grade-levels.view')->middleware('permission:hr-grade-levels.delete');
    Route::get('leave-balances', [\App\Http\Controllers\HR\LeaveBalanceController::class, 'index'])->middleware('permission:leave-balance.view|leave-balance.manage');
    Route::post('leave-balances/adjust', [\App\Http\Controllers\HR\LeaveBalanceController::class, 'adjust'])->middleware('permission:leave-balance.view|leave-balance.manage')->middleware('permission:leave-balance.manage');
    Route::post('leave-balances/initialize', [\App\Http\Controllers\HR\LeaveBalanceController::class, 'initializeYear'])->middleware('permission:leave-balance.view|leave-balance.manage')->middleware('permission:leave-balance.manage');
    Route::get('leave-balances/{staff}', [\App\Http\Controllers\HR\LeaveBalanceController::class, 'show'])->whereNumber('staff')->middleware('permission:leave-balance.view|leave-balance.manage');
    Route::get('leave-calendar', [\App\Http\Controllers\HR\LeaveCalendarController::class, 'index'])->middleware('permission:leave-request.view');
    Route::get('leave-calendar/conflicts', [\App\Http\Controllers\HR\LeaveCalendarController::class, 'conflicts'])->middleware('permission:leave-request.view');
    Route::get('leave-calendar/department-summary', [\App\Http\Controllers\HR\LeaveCalendarController::class, 'departmentSummary'])->middleware('permission:leave-request.view');
    Route::get('leave-calendar/events', [\App\Http\Controllers\HR\LeaveCalendarController::class, 'events'])->middleware('permission:leave-request.view');
    Route::get('leave-calendar/heatmap', [\App\Http\Controllers\HR\LeaveCalendarController::class, 'heatmap'])->middleware('permission:leave-request.view');
    Route::get('leave-calendar/on-leave-today', [\App\Http\Controllers\HR\LeaveCalendarController::class, 'onLeaveToday'])->middleware('permission:leave-request.view');
    Route::get('leave-calendar/stats', [\App\Http\Controllers\HR\LeaveCalendarController::class, 'stats'])->middleware('permission:leave-request.view');
    Route::get('leave-requests', [\App\Http\Controllers\HR\LeaveRequestController::class, 'index'])->middleware('permission:leave-request.view');
    Route::post('leave-requests', [\App\Http\Controllers\HR\LeaveRequestController::class, 'store'])->middleware('permission:leave-request.create|leave-request.create-own');
    Route::get('leave-requests/create', [\App\Http\Controllers\HR\LeaveRequestController::class, 'create'])->middleware('permission:leave-request.create|leave-request.create-own');
    Route::get('leave-requests/{leaveRequest}', [\App\Http\Controllers\HR\LeaveRequestController::class, 'show'])->whereNumber('leaveRequest')->middleware('permission:leave-request.view|leave-request.view-own');
    Route::put('leave-requests/{leaveRequest}', [\App\Http\Controllers\HR\LeaveRequestController::class, 'update'])->whereNumber('leaveRequest')->middleware('permission:leave-request.edit');
    Route::delete('leave-requests/{leaveRequest}', [\App\Http\Controllers\HR\LeaveRequestController::class, 'destroy'])->whereNumber('leaveRequest')->middleware('permission:leave-request.delete');
    Route::post('leave-requests/{leaveRequest}/approve', [\App\Http\Controllers\HR\LeaveRequestController::class, 'approve'])->whereNumber('leaveRequest')->middleware('permission:leave-request.approve|leave-request.supervisor-approve|leave-request.hr-approve');
    Route::post('leave-requests/{leaveRequest}/cancel', [\App\Http\Controllers\HR\LeaveRequestController::class, 'cancel'])->whereNumber('leaveRequest');
    Route::get('leave-requests/{leaveRequest}/edit', [\App\Http\Controllers\HR\LeaveRequestController::class, 'edit'])->whereNumber('leaveRequest')->middleware('permission:leave-request.edit');
    Route::post('leave-requests/{leaveRequest}/hr-approve', [\App\Http\Controllers\HR\LeaveRequestController::class, 'hrApprove'])->whereNumber('leaveRequest')->middleware('permission:leave-request.hr-approve');
    Route::post('leave-requests/{leaveRequest}/recall', [\App\Http\Controllers\HR\LeaveRequestController::class, 'recall'])->whereNumber('leaveRequest')->middleware('permission:leave-request.recall');
    Route::post('leave-requests/{leaveRequest}/reject', [\App\Http\Controllers\HR\LeaveRequestController::class, 'reject'])->whereNumber('leaveRequest')->middleware('permission:leave-request.reject|leave-request.supervisor-approve|leave-request.hr-approve');
    Route::post('leave-requests/{leaveRequest}/supervisor-approve', [\App\Http\Controllers\HR\LeaveRequestController::class, 'supervisorApprove'])->whereNumber('leaveRequest')->middleware('permission:leave-request.supervisor-approve');
    Route::get('leave-types', [\App\Http\Controllers\HR\LeaveTypeController::class, 'index'])->middleware('permission:leave-type.view|leave-type.create');
    Route::post('leave-types', [\App\Http\Controllers\HR\LeaveTypeController::class, 'store'])->middleware('permission:leave-type.view|leave-type.create');
    Route::get('leave-types/create', [\App\Http\Controllers\HR\LeaveTypeController::class, 'create'])->middleware('permission:leave-type.view|leave-type.create');
    Route::get('leave-types/{leave_type}', [\App\Http\Controllers\HR\LeaveTypeController::class, 'show'])->whereNumber('leave_type')->middleware('permission:leave-type.view|leave-type.create');
    Route::put('leave-types/{leave_type}', [\App\Http\Controllers\HR\LeaveTypeController::class, 'update'])->whereNumber('leave_type')->middleware('permission:leave-type.view|leave-type.create');
    Route::delete('leave-types/{leave_type}', [\App\Http\Controllers\HR\LeaveTypeController::class, 'destroy'])->whereNumber('leave_type')->middleware('permission:leave-type.view|leave-type.create');
    Route::get('leave-types/{leave_type}/edit', [\App\Http\Controllers\HR\LeaveTypeController::class, 'edit'])->whereNumber('leave_type')->middleware('permission:leave-type.view|leave-type.create');
    Route::get('master-import', [\App\Http\Controllers\HR\StaffMasterImportController::class, 'showForm'])->middleware('permission:hr-staff-registry.export');
    Route::post('master-import/import', [\App\Http\Controllers\HR\StaffMasterImportController::class, 'import'])->middleware('permission:hr-staff-registry.export');
    Route::get('master-import/template', [\App\Http\Controllers\HR\StaffMasterImportController::class, 'importTemplate'])->middleware('permission:hr-staff-registry.export');
    Route::get('medical-exams', [\App\Http\Controllers\HR\StaffMedicalExamController::class, 'index'])->middleware('permission:hr-medical-exams.view');
    Route::post('medical-exams', [\App\Http\Controllers\HR\StaffMedicalExamController::class, 'store'])->middleware('permission:hr-medical-exams.view')->middleware('permission:hr-medical-exams.create');
    Route::put('medical-exams/{medicalExam}', [\App\Http\Controllers\HR\StaffMedicalExamController::class, 'update'])->whereNumber('medicalExam')->middleware('permission:hr-medical-exams.view')->middleware('permission:hr-medical-exams.create');
    Route::delete('medical-exams/{medicalExam}', [\App\Http\Controllers\HR\StaffMedicalExamController::class, 'destroy'])->whereNumber('medicalExam')->middleware('permission:hr-medical-exams.view')->middleware('permission:hr-medical-exams.create');
    Route::get('pay-heads', [\App\Http\Controllers\HR\PayHeadController::class, 'index'])->middleware('permission:pay-head.view|pay-head.create');
    Route::post('pay-heads', [\App\Http\Controllers\HR\PayHeadController::class, 'store'])->middleware('permission:pay-head.view|pay-head.create');
    Route::get('pay-heads/create', [\App\Http\Controllers\HR\PayHeadController::class, 'create'])->middleware('permission:pay-head.view|pay-head.create');
    Route::get('pay-heads/{pay_head}', [\App\Http\Controllers\HR\PayHeadController::class, 'show'])->whereNumber('pay_head')->middleware('permission:pay-head.view|pay-head.create');
    Route::put('pay-heads/{pay_head}', [\App\Http\Controllers\HR\PayHeadController::class, 'update'])->whereNumber('pay_head')->middleware('permission:pay-head.view|pay-head.create');
    Route::delete('pay-heads/{pay_head}', [\App\Http\Controllers\HR\PayHeadController::class, 'destroy'])->whereNumber('pay_head')->middleware('permission:pay-head.view|pay-head.create');
    Route::get('pay-heads/{pay_head}/edit', [\App\Http\Controllers\HR\PayHeadController::class, 'edit'])->whereNumber('pay_head')->middleware('permission:pay-head.view|pay-head.create');
    Route::get('payroll', [\App\Http\Controllers\HR\PayrollBatchController::class, 'index'])->middleware('permission:payroll-batch.view');
    Route::post('payroll', [\App\Http\Controllers\HR\PayrollBatchController::class, 'store'])->middleware('permission:payroll-batch.create');
    Route::post('payroll/check-duplicates', [\App\Http\Controllers\HR\PayrollBatchController::class, 'checkDuplicates'])->middleware('permission:payroll-batch.create');
    Route::get('payroll/create', [\App\Http\Controllers\HR\PayrollBatchController::class, 'create'])->middleware('permission:payroll-batch.create');
    Route::get('payroll/staff-by-criteria', [\App\Http\Controllers\HR\PayrollBatchController::class, 'staffByCriteria'])->middleware('permission:payroll-batch.create');
    Route::get('payroll/staff-summary', [\App\Http\Controllers\HR\PayrollBatchController::class, 'staffSummary'])->middleware('permission:payroll-batch.create');
    Route::get('payroll/{payrollBatch}', [\App\Http\Controllers\HR\PayrollBatchController::class, 'show'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.view');
    Route::put('payroll/{payrollBatch}', [\App\Http\Controllers\HR\PayrollBatchController::class, 'update'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.edit');
    Route::delete('payroll/{payrollBatch}', [\App\Http\Controllers\HR\PayrollBatchController::class, 'destroy'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.delete');
    Route::post('payroll/{payrollBatch}/approve', [\App\Http\Controllers\HR\PayrollBatchController::class, 'approve'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.approve');
    Route::get('payroll/{payrollBatch}/edit', [\App\Http\Controllers\HR\PayrollBatchController::class, 'edit'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.edit');
    Route::get('payroll/{payrollBatch}/export', [\App\Http\Controllers\HR\PayrollBatchController::class, 'export'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.view');
    Route::post('payroll/{payrollBatch}/generate', [\App\Http\Controllers\HR\PayrollBatchController::class, 'generate'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.create');
    Route::post('payroll/{payrollBatch}/mark-paid', [\App\Http\Controllers\HR\PayrollBatchController::class, 'markPaid'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.approve');
    Route::get('payroll/{payrollBatch}/payslip/{payrollItem}/print', [\App\Http\Controllers\HR\PayrollBatchController::class, 'printPayslip'])->whereNumber('payrollBatch')->whereNumber('payrollItem')->middleware('permission:payroll-batch.view');
    Route::get('payroll/{payrollBatch}/payslips', [\App\Http\Controllers\HR\PayrollBatchController::class, 'payslips'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.view');
    Route::post('payroll/{payrollBatch}/reject', [\App\Http\Controllers\HR\PayrollBatchController::class, 'reject'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.reject');
    Route::post('payroll/{payrollBatch}/revert-draft', [\App\Http\Controllers\HR\PayrollBatchController::class, 'revertToDraft'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.edit');
    Route::post('payroll/{payrollBatch}/submit', [\App\Http\Controllers\HR\PayrollBatchController::class, 'submit'])->whereNumber('payrollBatch')->middleware('permission:payroll-batch.submit');
    Route::get('promotions', [\App\Http\Controllers\HR\StaffPromotionController::class, 'index'])->middleware('permission:hr-promotions.view');
    Route::post('promotions', [\App\Http\Controllers\HR\StaffPromotionController::class, 'store'])->middleware('permission:hr-promotions.view')->middleware('permission:hr-promotions.create');
    Route::get('promotions/staff/{staff}', [\App\Http\Controllers\HR\StaffPromotionController::class, 'staffHistory'])->whereNumber('staff')->middleware('permission:hr-promotions.view');
    Route::get('promotions/{promotion}', [\App\Http\Controllers\HR\StaffPromotionController::class, 'show'])->whereNumber('promotion')->middleware('permission:hr-promotions.view');
    Route::delete('promotions/{promotion}', [\App\Http\Controllers\HR\StaffPromotionController::class, 'destroy'])->whereNumber('promotion')->middleware('permission:hr-promotions.view')->middleware('permission:hr-promotions.create');
    Route::get('qualifications', [\App\Http\Controllers\HR\StaffQualificationController::class, 'index'])->middleware('permission:hr-qualifications.view');
    Route::post('qualifications', [\App\Http\Controllers\HR\StaffQualificationController::class, 'store'])->middleware('permission:hr-qualifications.view');
    Route::post('qualifications/import', [\App\Http\Controllers\HR\StaffQualificationController::class, 'import'])->middleware('permission:hr-qualifications.view')->middleware('permission:hr-qualifications.verify');
    Route::get('qualifications/import-template', [\App\Http\Controllers\HR\StaffQualificationController::class, 'importTemplate'])->middleware('permission:hr-qualifications.view');
    Route::delete('qualifications/{qualification}', [\App\Http\Controllers\HR\StaffQualificationController::class, 'destroy'])->whereNumber('qualification')->middleware('permission:hr-qualifications.view');
    Route::post('qualifications/{qualification}/verify', [\App\Http\Controllers\HR\StaffQualificationController::class, 'verify'])->whereNumber('qualification')->middleware('permission:hr-qualifications.view')->middleware('permission:hr-qualifications.verify');
    Route::get('reports', [\App\Http\Controllers\HR\HrWorkbenchController::class, 'reports'])->middleware('permission:hr-workbench.access');
    Route::get('salary-profiles', [\App\Http\Controllers\HR\SalaryProfileController::class, 'index'])->middleware('permission:salary-profile.view');
    Route::post('salary-profiles', [\App\Http\Controllers\HR\SalaryProfileController::class, 'store'])->middleware('permission:salary-profile.create');
    Route::get('salary-profiles/create', [\App\Http\Controllers\HR\SalaryProfileController::class, 'create'])->middleware('permission:salary-profile.create');
    Route::get('salary-profiles/staff/{staff}', [\App\Http\Controllers\HR\SalaryProfileController::class, 'staffProfiles'])->whereNumber('staff')->middleware('permission:salary-profile.view');
    Route::get('salary-profiles/{salaryProfile}', [\App\Http\Controllers\HR\SalaryProfileController::class, 'show'])->whereNumber('salaryProfile')->middleware('permission:salary-profile.view');
    Route::put('salary-profiles/{salaryProfile}', [\App\Http\Controllers\HR\SalaryProfileController::class, 'update'])->whereNumber('salaryProfile')->middleware('permission:salary-profile.edit');
    Route::delete('salary-profiles/{salaryProfile}', [\App\Http\Controllers\HR\SalaryProfileController::class, 'destroy'])->whereNumber('salaryProfile')->middleware('permission:salary-profile.delete');
    Route::get('salary-profiles/{salaryProfile}/edit', [\App\Http\Controllers\HR\SalaryProfileController::class, 'edit'])->whereNumber('salaryProfile')->middleware('permission:salary-profile.edit');
    Route::get('staff-registry', [\App\Http\Controllers\HR\StaffRegistryController::class, 'index'])->middleware('permission:hr-staff-registry.view');
    Route::get('staff-registry/alerts', [\App\Http\Controllers\HR\StaffRegistryController::class, 'alerts'])->middleware('permission:hr-staff-registry.view');
    Route::get('staff-registry/data', [\App\Http\Controllers\HR\StaffRegistryController::class, 'data'])->middleware('permission:hr-staff-registry.view');
    Route::get('staff-registry/export', [\App\Http\Controllers\HR\StaffRegistryController::class, 'export'])->middleware('permission:hr-staff-registry.view')->middleware('permission:hr-staff-registry.export');
    Route::get('staff-registry/staff/{staff}/detail', [\App\Http\Controllers\HR\StaffRegistryController::class, 'staffDetail'])->whereNumber('staff')->middleware('permission:hr-staff-registry.view');
    Route::get('suspensions', [\App\Http\Controllers\HR\StaffSuspensionController::class, 'index'])->middleware('permission:suspension.view');
    Route::post('suspensions', [\App\Http\Controllers\HR\StaffSuspensionController::class, 'store'])->middleware('permission:suspension.create');
    Route::get('suspensions/create', [\App\Http\Controllers\HR\StaffSuspensionController::class, 'create'])->middleware('permission:suspension.create');
    Route::get('suspensions/{suspension}', [\App\Http\Controllers\HR\StaffSuspensionController::class, 'show'])->whereNumber('suspension')->middleware('permission:suspension.view');
    Route::post('suspensions/{suspension}/lift', [\App\Http\Controllers\HR\StaffSuspensionController::class, 'lift'])->whereNumber('suspension')->middleware('permission:suspension.lift');
    Route::get('terminations', [\App\Http\Controllers\HR\StaffTerminationController::class, 'index'])->middleware('permission:termination.view');
    Route::post('terminations', [\App\Http\Controllers\HR\StaffTerminationController::class, 'store'])->middleware('permission:termination.create');
    Route::get('terminations/create', [\App\Http\Controllers\HR\StaffTerminationController::class, 'create'])->middleware('permission:termination.create');
    Route::get('terminations/{termination}', [\App\Http\Controllers\HR\StaffTerminationController::class, 'show'])->whereNumber('termination')->middleware('permission:termination.view');
    Route::put('terminations/{termination}', [\App\Http\Controllers\HR\StaffTerminationController::class, 'update'])->whereNumber('termination')->middleware('permission:termination.edit');
    Route::post('terminations/{termination}/complete', [\App\Http\Controllers\HR\StaffTerminationController::class, 'complete'])->whereNumber('termination')->middleware('permission:termination.edit');
    Route::get('terminations/{termination}/edit', [\App\Http\Controllers\HR\StaffTerminationController::class, 'edit'])->whereNumber('termination')->middleware('permission:termination.edit');
    Route::get('tracking/calendar', [\App\Http\Controllers\HR\TrackingCalendarController::class, 'index'])->middleware('permission:hr-medical-exams.view');
    Route::get('tracking/calendar/events', [\App\Http\Controllers\HR\TrackingCalendarController::class, 'events'])->middleware('permission:hr-medical-exams.view');
    Route::get('tracking/staff/{staff}', [\App\Http\Controllers\HR\StaffTrackingProfileController::class, 'show'])->whereNumber('staff')->middleware('permission:hr-promotions.view');
    Route::get('trainings', [\App\Http\Controllers\HR\StaffTrainingController::class, 'index'])->middleware('permission:hr-trainings.view');
    Route::post('trainings', [\App\Http\Controllers\HR\StaffTrainingController::class, 'store'])->middleware('permission:hr-trainings.view')->middleware('permission:hr-trainings.create');
    Route::post('trainings/import', [\App\Http\Controllers\HR\StaffTrainingController::class, 'import'])->middleware('permission:hr-trainings.view')->middleware('permission:hr-trainings.create');
    Route::get('trainings/import-template', [\App\Http\Controllers\HR\StaffTrainingController::class, 'importTemplate'])->middleware('permission:hr-trainings.view');
    Route::put('trainings/{training}', [\App\Http\Controllers\HR\StaffTrainingController::class, 'update'])->whereNumber('training')->middleware('permission:hr-trainings.view')->middleware('permission:hr-trainings.edit');
    Route::delete('trainings/{training}', [\App\Http\Controllers\HR\StaffTrainingController::class, 'destroy'])->whereNumber('training')->middleware('permission:hr-trainings.view')->middleware('permission:hr-trainings.edit');
    Route::get('units', [\App\Http\Controllers\HR\UnitController::class, 'index'])->middleware('permission:hr-units.view');
    Route::post('units', [\App\Http\Controllers\HR\UnitController::class, 'store'])->middleware('permission:hr-units.view')->middleware('permission:hr-units.create');
    Route::get('units/for-department', [\App\Http\Controllers\HR\UnitController::class, 'forDepartment'])->middleware('permission:hr-units.view');
    Route::put('units/{unit}', [\App\Http\Controllers\HR\UnitController::class, 'update'])->whereNumber('unit')->middleware('permission:hr-units.view')->middleware('permission:hr-units.edit');
    Route::delete('units/{unit}', [\App\Http\Controllers\HR\UnitController::class, 'destroy'])->whereNumber('unit')->middleware('permission:hr-units.view')->middleware('permission:hr-units.delete');
    Route::get('workbench', [\App\Http\Controllers\HR\HrWorkbenchController::class, 'index'])->middleware('permission:hr-workbench.access');
    Route::get('workbench/stats', [\App\Http\Controllers\HR\HrWorkbenchController::class, 'stats'])->middleware('permission:hr-workbench.access');
});
