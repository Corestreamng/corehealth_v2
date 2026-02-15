<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DataEndpoint;
use App\Http\Controllers\API\MobileAuthController;
use App\Http\Controllers\API\MobileEncounterController;
use App\Http\Controllers\API\MobilePatientController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VitalSignController;
use App\Http\Controllers\NursingWorkbenchController;
use App\Models\service;
use App\Models\Product;
use App\Models\ProcedureCategory;

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
        $services = service::with('price')
            ->where('category_id', $labCategoryId)
            ->where('status', 1)
            ->orderBy('service_name')
            ->get();
        return response()->json(['data' => $services]);
    });

    // Imaging Services API - Returns services from the imaging category
    Route::get('imaging-services', function () {
        $imagingCategoryId = appsettings('imaging_category_id', 6);
        $services = service::with('price')
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

        $services = service::with(['price', 'procedureDefinition.procedureCategory'])
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

/*
|--------------------------------------------------------------------------
| Mobile App API Routes
|--------------------------------------------------------------------------
*/

// Public — no auth required (called before login)
Route::prefix('mobile')->group(function () {
    Route::get('instance-info',  [MobileAuthController::class, 'instanceInfo']);
    Route::post('staff/login',   [MobileAuthController::class, 'staffLogin']);
    Route::post('patient/login', [MobileAuthController::class, 'patientLogin']);
});

// Protected — requires Sanctum token
Route::prefix('mobile')->middleware('auth:sanctum')->group(function () {
    Route::post('logout', [MobileAuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| Mobile Doctor Routes — Encounter & Queue Management
|--------------------------------------------------------------------------
| These routes power the Doctor App's clinical workflow.
| Existing JSON-returning EncounterController methods are reused directly.
*/
Route::prefix('mobile/doctor')->middleware('auth:sanctum')->group(function () {

    // ── Queue Management ──────────────────────────────────────────
    Route::get('queues', [MobileEncounterController::class, 'queues']);

    // ── Encounter Lifecycle ───────────────────────────────────────
    Route::post('encounters/start', [MobileEncounterController::class, 'startEncounter']);
    Route::get('encounters/{encounter}', [MobileEncounterController::class, 'encounterDetail']);

    // ── Reused: Diagnosis & Notes (already return JSON) ──────────
    Route::post('encounters/{encounter}/save-diagnosis', [EncounterController::class, 'saveDiagnosis']);
    Route::put('encounters/{encounter}/notes', [EncounterController::class, 'updateEncounterNotes']);
    Route::post('encounters/autosave', [EncounterController::class, 'autosaveNotes']);

    // ── Reused: Lab / Imaging / Prescription / Procedure saves ──
    Route::post('encounters/{encounter}/save-labs', [EncounterController::class, 'saveLabs']);
    Route::post('encounters/{encounter}/save-imaging', [EncounterController::class, 'saveImaging']);
    Route::post('encounters/{encounter}/save-prescriptions', [EncounterController::class, 'savePrescriptions']);
    Route::post('encounters/{encounter}/save-procedures', [EncounterController::class, 'saveProcedures']);

    // ── Reused: Finalize & Summary ──────────────────────────────
    Route::post('encounters/{encounter}/finalize', [EncounterController::class, 'finalizeEncounter']);
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

    // ── Search / Autocomplete (reused, already return JSON) ─────
    Route::get('search/diagnosis', [EncounterController::class, 'liveSearchReasons']);
    Route::get('search/services', [ServiceController::class, 'liveSearchServices']);
    Route::get('search/products', [ProductController::class, 'liveSearchProducts']);

    // ── Vitals (reused, already JSON-aware) ─────────────────────
    Route::post('vitals', [VitalSignController::class, 'store']);
    Route::get('patient/{patientId}/vitals', [NursingWorkbenchController::class, 'getPatientVitals']);
});

/*
|--------------------------------------------------------------------------
| Mobile Patient Routes — Read-Only Health Records
|--------------------------------------------------------------------------
*/
Route::prefix('mobile/patient')->middleware('auth:sanctum')->group(function () {
    Route::get('profile', [MobilePatientController::class, 'myProfile']);
    Route::get('encounters', [MobilePatientController::class, 'myEncounters']);
    Route::get('encounters/{encounter}', [MobilePatientController::class, 'encounterDetail']);
    Route::get('vitals', [MobilePatientController::class, 'myVitals']);
    Route::get('lab-results', [MobilePatientController::class, 'myLabResults']);
    Route::get('imaging-results', [MobilePatientController::class, 'myImagingResults']);
    Route::get('prescriptions', [MobilePatientController::class, 'myPrescriptions']);
    Route::get('procedures', [MobilePatientController::class, 'myProcedures']);
    Route::get('admissions', [MobilePatientController::class, 'myAdmissions']);
});
