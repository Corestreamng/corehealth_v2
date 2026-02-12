<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DataEndpoint;
use App\Http\Controllers\API\MobileAuthController;
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
