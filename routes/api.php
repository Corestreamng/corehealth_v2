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
