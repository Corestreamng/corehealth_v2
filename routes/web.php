<?php

use App\Http\Controllers\Account\accountsController;
use App\Http\Controllers\Account\paymentController;
use App\Http\Controllers\Account\productAccountController;
use App\Http\Controllers\AdmissionRequestController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChecklistTemplateController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\Doctor\DoctorConsultationsController;
use App\Http\Controllers\Doctor\DoctorDashboardController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\HmoController;
use App\Http\Controllers\HmoWorkbenchController;
use App\Http\Controllers\HmoReportsController;
use App\Http\Controllers\Admin\TariffManagementController;
use App\Http\Controllers\HospitalConfigController;
use App\Http\Controllers\LabServiceRequestController;
use App\Http\Controllers\ImagingServiceRequestController;
use App\Http\Controllers\MiscBillController;
use App\Http\Controllers\MoveStockController;
use App\Http\Controllers\NursingNoteController;
use App\Http\Controllers\NursingNoteTypeController;
use App\Http\Controllers\PatientAccountController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductOrServiceRequestController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\serviceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\servicePriceController;
use App\Http\Controllers\ProcedureCategoryController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreStockController;
use App\Http\Controllers\VitalSignController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\PatientProfileController;
use App\Http\Controllers\SpecializationController;
use App\Http\Controllers\WardController;
use App\Models\Clinic;
use App\Models\PatientProfile;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

//token refresh route
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

// Test route for department notifications (remove in production)
Route::get('/test-dept-notifications', function () {
    $service = app(\App\Services\DepartmentNotificationService::class);
    $results = $service->sendTestMessages();
    return response()->json([
        'message' => 'Test messages sent',
        'results' => $results
    ]);
})->middleware('auth');

Route::group(['middleware' => ['auth']], function () {
    // Route::put('staff/updateAvatar/{id}', 'Admin\UserController@updateAvatar')->name('users.updateAvatar');

    // Messages/Chat
    Route::get('/messages', [MessagesController::class, 'index'])->name('messages');
    Route::get('/messages/create', [MessagesController::class, 'create'])->name('messages.create');

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Users
        // Route::put('staff/{id}',[StaffController::class, 'update'])->name('staff.update');
        Route::resource('staff', StaffController::class);
        Route::get('liststaff', [StaffController::class, 'listStaff'])->name('listStaff');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Roles
        Route::get('listRoles', [RoleController::class, 'listRoles'])->name('listRoles');
        Route::resource('roles', RoleController::class);
    });

    Route::resource('specializations', SpecializationController::class);
    Route::resource('clinics', ClinicController::class);
    Route::resource('departments', DepartmentController::class);
    Route::get('departments-list', [DepartmentController::class, 'getAll'])->name('departments.list');

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::get('listPermissions', [PermissionController::class, 'listPermissions'])->name('listPermissions');
        Route::resource('permissions', PermissionController::class);
    });

    // Hospital Configuration
    Route::group(['middleware' => ['auth', 'role:SUPERADMIN|ADMIN']], function () {
        Route::get('hospital-config', [HospitalConfigController::class, 'index'])->name('hospital-config.index');
        Route::put('hospital-config', [HospitalConfigController::class, 'update'])->name('hospital-config.update');
    });

    // Vaccine Schedule Configuration
    Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'role:SUPERADMIN|ADMIN']], function () {
        Route::get('vaccine-schedule', [\App\Http\Controllers\VaccineScheduleController::class, 'index'])->name('vaccine-schedule.index');

        // Templates
        Route::get('vaccine-schedule/templates', [\App\Http\Controllers\VaccineScheduleController::class, 'getTemplates'])->name('vaccine-schedule.templates.list');
        Route::post('vaccine-schedule/templates', [\App\Http\Controllers\VaccineScheduleController::class, 'storeTemplate'])->name('vaccine-schedule.templates.store');
        Route::get('vaccine-schedule/templates/{id}', [\App\Http\Controllers\VaccineScheduleController::class, 'getTemplate'])->name('vaccine-schedule.templates.show');
        Route::put('vaccine-schedule/templates/{id}', [\App\Http\Controllers\VaccineScheduleController::class, 'updateTemplate'])->name('vaccine-schedule.templates.update');
        Route::delete('vaccine-schedule/templates/{id}', [\App\Http\Controllers\VaccineScheduleController::class, 'deleteTemplate'])->name('vaccine-schedule.templates.destroy');
        Route::post('vaccine-schedule/templates/{id}/set-default', [\App\Http\Controllers\VaccineScheduleController::class, 'setDefaultTemplate'])->name('vaccine-schedule.templates.set-default');
        Route::post('vaccine-schedule/templates/{id}/duplicate', [\App\Http\Controllers\VaccineScheduleController::class, 'duplicateTemplate'])->name('vaccine-schedule.templates.duplicate');
        Route::get('vaccine-schedule/templates/{id}/export', [\App\Http\Controllers\VaccineScheduleController::class, 'exportTemplate'])->name('vaccine-schedule.templates.export');
        Route::post('vaccine-schedule/templates/import', [\App\Http\Controllers\VaccineScheduleController::class, 'importTemplate'])->name('vaccine-schedule.templates.import');

        // Schedule Items
        Route::post('vaccine-schedule/items', [\App\Http\Controllers\VaccineScheduleController::class, 'storeScheduleItem'])->name('vaccine-schedule.items.store');
        Route::put('vaccine-schedule/items/{id}', [\App\Http\Controllers\VaccineScheduleController::class, 'updateScheduleItem'])->name('vaccine-schedule.items.update');
        Route::delete('vaccine-schedule/items/{id}', [\App\Http\Controllers\VaccineScheduleController::class, 'deleteScheduleItem'])->name('vaccine-schedule.items.destroy');

        // Product Mappings
        Route::get('vaccine-schedule/mappings', [\App\Http\Controllers\VaccineScheduleController::class, 'getProductMappings'])->name('vaccine-schedule.mappings.list');
        Route::post('vaccine-schedule/mappings', [\App\Http\Controllers\VaccineScheduleController::class, 'storeProductMapping'])->name('vaccine-schedule.mappings.store');
        Route::put('vaccine-schedule/mappings/{id}', [\App\Http\Controllers\VaccineScheduleController::class, 'updateProductMapping'])->name('vaccine-schedule.mappings.update');
        Route::delete('vaccine-schedule/mappings/{id}', [\App\Http\Controllers\VaccineScheduleController::class, 'deleteProductMapping'])->name('vaccine-schedule.mappings.destroy');
        Route::post('vaccine-schedule/mappings/{id}/set-primary', [\App\Http\Controllers\VaccineScheduleController::class, 'setPrimaryMapping'])->name('vaccine-schedule.mappings.set-primary');

        // Helpers
        Route::get('vaccine-schedule/vaccines', [\App\Http\Controllers\VaccineScheduleController::class, 'getVaccineNames'])->name('vaccine-schedule.vaccines.list');
        Route::get('vaccine-schedule/products/search', [\App\Http\Controllers\VaccineScheduleController::class, 'searchProducts'])->name('vaccine-schedule.products.search');
    });

    // Bank Configuration
    Route::group(['middleware' => ['auth', 'role:SUPERADMIN|ADMIN']], function () {
        Route::get('banks', [BankController::class, 'index'])->name('banks.index');
        Route::get('banks/list', [BankController::class, 'list'])->name('banks.list');
        Route::get('banks/active', [BankController::class, 'getActiveBanks'])->name('banks.active');
        Route::post('banks', [BankController::class, 'store'])->name('banks.store');
        Route::put('banks/{bank}', [BankController::class, 'update'])->name('banks.update');
        Route::delete('banks/{bank}', [BankController::class, 'destroy'])->name('banks.destroy');
    });

    // Audit Logs
    Route::group(['middleware' => ['auth']], function () {
        Route::get('audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/data', [\App\Http\Controllers\AuditLogController::class, 'getData'])->name('audit-logs.data');
        Route::get('audit-logs/stats', [\App\Http\Controllers\AuditLogController::class, 'stats'])->name('audit-logs.stats');
        Route::get('audit-logs/export', [\App\Http\Controllers\AuditLogController::class, 'export'])->name('audit-logs.export');
        Route::get('audit-logs/{id}', [\App\Http\Controllers\AuditLogController::class, 'show'])->name('audit-logs.show');
    });

    // Import/Export Module
    Route::group(['middleware' => ['auth'], 'prefix' => 'import-export'], function () {
        Route::get('/', [\App\Http\Controllers\ImportExportController::class, 'index'])->name('import-export.index');

        // Template Downloads
        Route::get('/template/products', [\App\Http\Controllers\ImportExportController::class, 'downloadProductTemplate'])->name('import-export.template.products');
        Route::get('/template/services', [\App\Http\Controllers\ImportExportController::class, 'downloadServiceTemplate'])->name('import-export.template.services');
        Route::get('/template/staff', [\App\Http\Controllers\ImportExportController::class, 'downloadStaffTemplate'])->name('import-export.template.staff');
        Route::get('/template/patients', [\App\Http\Controllers\ImportExportController::class, 'downloadPatientTemplate'])->name('import-export.template.patients');

        // Imports
        Route::post('/import/products', [\App\Http\Controllers\ImportExportController::class, 'importProducts'])->name('import-export.import.products');
        Route::post('/import/services', [\App\Http\Controllers\ImportExportController::class, 'importServices'])->name('import-export.import.services');
        Route::post('/import/staff', [\App\Http\Controllers\ImportExportController::class, 'importStaff'])->name('import-export.import.staff');
        Route::post('/import/patients', [\App\Http\Controllers\ImportExportController::class, 'importPatients'])->name('import-export.import.patients');

        // Exports
        Route::get('/export/products', [\App\Http\Controllers\ImportExportController::class, 'exportProducts'])->name('import-export.export.products');
        Route::get('/export/services', [\App\Http\Controllers\ImportExportController::class, 'exportServices'])->name('import-export.export.services');
        Route::get('/export/staff', [\App\Http\Controllers\ImportExportController::class, 'exportStaff'])->name('import-export.export.staff');
        Route::get('/export/patients', [\App\Http\Controllers\ImportExportController::class, 'exportPatients'])->name('import-export.export.patients');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('patient', PatientController::class);
        Route::get('patientsList', [PatientController::class, 'patientsList'])->name('patientsList');
        Route::get('patient-services-rendered/{patient_id}', [PatientController::class, 'PatientServicesRendered'])->name('patient-services-rendered');
        Route::get('add-to-queue', [PatientController::class, 'addToQueue'])->name('add-to-queue');
        Route::get('listReturningPatients', [PatientController::class, 'listReturningPatients'])->name('listReturningPatients');
        Route::get('getMyDependants/{id}', [PatientController::class, 'getMyDependants'])->name('getMyDependants');
        Route::get('get-doctors/{id}', [ClinicController::class, 'getDoctors'])->name('get-doctors');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('products', ProductController::class);
        Route::get('product-list', [ProductController::class, 'listProducts'])->name('product-list');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('product-category', ProductCategoryController::class);
        Route::get('product-category-list', [ProductCategoryController::class, 'listProductCategories'])->name('product-category-list');
        Route::get('listSalesProduct/{id}', [ProductController::class, 'listSalesProduct'])->name('listSalesProduct');
        Route::get('live-search-products', [ProductController::class, 'liveSearchProducts'])->name('live-search-products');
        Route::get('live-search-reasons', [EncounterController::class, 'liveSearchReasons'])->name('live-search-reasons');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('product-requests', ProductRequestController::class);
        Route::post('product-bill-patient', [ProductRequestController::class, 'bill'])->name('product-bill-patient');
        Route::post('product-dispense-patient', [ProductRequestController::class, 'dispense'])->name('product-dispense-patient');
        // AJAX versions for unified prescription component
        Route::post('product-bill-patient-ajax', [ProductRequestController::class, 'billAjax'])->name('product-bill-patient-ajax');
        Route::post('product-dispense-patient-ajax', [ProductRequestController::class, 'dispenseAjax'])->name('product-dispense-patient-ajax');
        Route::post('product-dismiss-patient-ajax', [ProductRequestController::class, 'dismissAjax'])->name('product-dismiss-patient-ajax');
        Route::get('prescQueueList', [ProductRequestController::class, 'prescQueueList'])->name('prescQueueList');
        Route::get('prescQueueHistoryList', [ProductRequestController::class, 'prescQueueHistoryList'])->name('prescQueueHistoryList');
        // Prescription history DataTable endpoint
        Route::get('prescHistoryList/{patient_id}', [EncounterController::class, 'prescHistoryList'])->name('prescHistoryList');
        Route::post('service-bill-patient', [LabServiceRequestController::class, 'bill'])->name('service-bill-patient');
        Route::post('service-sample-patient', [LabServiceRequestController::class, 'takeSample'])->name('service-sample-patient');
        Route::post('service-save-result', [LabServiceRequestController::class, 'saveResult'])->name('service-save-result');
        Route::post('account-make-deposit', [PatientAccountController::class, 'makeDeposit'])->name('account-make-deposit');
        Route::post('add-misc-bill', [PatientAccountController::class, 'addMsicBill'])->name('add-misc-bill');
        Route::resource('patient-account', PatientAccountController::class);
        Route::get('patientPaymentHistoryList/{patient_id}', [PatientAccountController::class, 'patientPaymentHistoryList'])->name('patientPaymentHistoryList');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('encounters', EncounterController::class);
        Route::get('allPrevEncounters', [EncounterController::class, 'allPrevEncounters'])->name('allPrevEncounters');
        Route::get('AllprevEncounterList', [EncounterController::class, 'AllprevEncounterList'])->name('AllprevEncounterList');
        Route::get('NewEncounterList', [EncounterController::class, 'NewEncounterList'])->name('NewEncounterList');
        Route::get('ContEncounterList', [EncounterController::class, 'ContEncounterList'])->name('ContEncounterList');
        Route::get('PrevEncounterList', [EncounterController::class, 'PrevEncounterList'])->name('PrevEncounterList');

        // AJAX endpoints for incremental encounter saving
        Route::post('encounters/{encounter}/save-diagnosis', [EncounterController::class, 'saveDiagnosis'])->name('encounters.saveDiagnosis');
        Route::post('encounters/{encounter}/save-labs', [EncounterController::class, 'saveLabs'])->name('encounters.saveLabs');
        Route::post('encounters/{encounter}/save-imaging', [EncounterController::class, 'saveImaging'])->name('encounters.saveImaging');
        Route::post('encounters/{encounter}/save-prescriptions', [EncounterController::class, 'savePrescriptions'])->name('encounters.savePrescriptions');
        Route::post('encounters/{encounter}/finalize', [EncounterController::class, 'finalizeEncounter'])->name('encounters.finalize');
        Route::get('encounters/{encounter}/summary', [EncounterController::class, 'getEncounterSummary'])->name('encounters.summary');

        // Delete endpoints for service requests
        Route::delete('encounters/{encounter}/labs/{lab}', [EncounterController::class, 'deleteLab'])->name('encounters.deleteLab');
        Route::delete('encounters/{encounter}/imaging/{imaging}', [EncounterController::class, 'deleteImaging'])->name('encounters.deleteImaging');
        Route::delete('encounters/{encounter}/prescriptions/{prescription}', [EncounterController::class, 'deletePrescription'])->name('encounters.deletePrescription');
        Route::delete('encounters/{encounter}', [EncounterController::class, 'deleteEncounter'])->name('encounters.delete');
        Route::put('encounters/{encounter}/notes', [EncounterController::class, 'updateEncounterNotes'])->name('encounters.updateNotes');

        // Procedure endpoints
        Route::post('encounters/{encounter}/save-procedures', [EncounterController::class, 'saveProcedures'])->name('encounters.saveProcedures');
        Route::get('procedureHistoryList/{patient_id}', [EncounterController::class, 'procedureHistoryList'])->name('procedureHistoryList');
        Route::delete('encounters/{encounter}/procedures/{procedure}', [EncounterController::class, 'deleteProcedure'])->name('encounters.deleteProcedure');
        Route::get('procedures/{procedure}', [EncounterController::class, 'getProcedureDetails'])->name('procedures.show');
        Route::put('procedures/{procedure}', [EncounterController::class, 'updateProcedure'])->name('procedures.update');
        Route::post('procedures/{procedure}/cancel', [EncounterController::class, 'cancelProcedure'])->name('procedures.cancel');
        Route::get('procedures/{procedure}/print', [EncounterController::class, 'printProcedure'])->name('procedures.print');

        // Procedure Team Members
        Route::get('procedures/{procedure}/team', [EncounterController::class, 'getProcedureTeam'])->name('procedures.team.index');
        Route::post('procedures/{procedure}/team', [EncounterController::class, 'addProcedureTeamMember'])->name('procedures.team.store');
        Route::put('procedures/{procedure}/team/{member}', [EncounterController::class, 'updateProcedureTeamMember'])->name('procedures.team.update');
        Route::delete('procedures/{procedure}/team/{member}', [EncounterController::class, 'deleteProcedureTeamMember'])->name('procedures.team.destroy');

        // Procedure Notes
        Route::get('procedures/{procedure}/notes', [EncounterController::class, 'getProcedureNotes'])->name('procedures.notes.index');
        Route::post('procedures/{procedure}/notes', [EncounterController::class, 'addProcedureNote'])->name('procedures.notes.store');
        Route::put('procedures/{procedure}/notes/{note}', [EncounterController::class, 'updateProcedureNote'])->name('procedures.notes.update');
        Route::delete('procedures/{procedure}/notes/{note}', [EncounterController::class, 'deleteProcedureNote'])->name('procedures.notes.destroy');

        // Patient Procedure Detail Page & Items Management (PatientProcedureController)
        // Spec Reference: Part 3.4, 3.5.2, 3.6
        Route::prefix('patient-procedures')->name('patient-procedures.')->group(function () {
            Route::get('{procedure}', [\App\Http\Controllers\PatientProcedureController::class, 'show'])->name('show');
            Route::put('{procedure}', [\App\Http\Controllers\PatientProcedureController::class, 'update'])->name('update');
            Route::put('{procedure}/outcome', [\App\Http\Controllers\PatientProcedureController::class, 'updateOutcome'])->name('outcome');
            Route::post('{procedure}/complete', [\App\Http\Controllers\PatientProcedureController::class, 'complete'])->name('complete');
            Route::post('{procedure}/cancel', [\App\Http\Controllers\PatientProcedureController::class, 'cancel'])->name('cancel');
            Route::get('{procedure}/print', [\App\Http\Controllers\PatientProcedureController::class, 'print'])->name('print');

            // Items Management (Bundled Billing)
            Route::get('{procedure}/items', [\App\Http\Controllers\PatientProcedureController::class, 'getItems'])->name('items.index');
            Route::post('{procedure}/items/lab', [\App\Http\Controllers\PatientProcedureController::class, 'addLabRequest'])->name('items.lab');
            Route::post('{procedure}/items/imaging', [\App\Http\Controllers\PatientProcedureController::class, 'addImagingRequest'])->name('items.imaging');
            Route::post('{procedure}/items/medication', [\App\Http\Controllers\PatientProcedureController::class, 'addMedication'])->name('items.medication');
            Route::delete('{procedure}/items/{item}', [\App\Http\Controllers\PatientProcedureController::class, 'removeItem'])->name('items.destroy');

            // Team Members (via PatientProcedureController)
            Route::get('{procedure}/team', [\App\Http\Controllers\PatientProcedureController::class, 'getTeam'])->name('team.index');
            Route::post('{procedure}/team', [\App\Http\Controllers\PatientProcedureController::class, 'addTeamMember'])->name('team.store');
            Route::put('{procedure}/team/{member}', [\App\Http\Controllers\PatientProcedureController::class, 'updateTeamMember'])->name('team.update');
            Route::delete('{procedure}/team/{member}', [\App\Http\Controllers\PatientProcedureController::class, 'removeTeamMember'])->name('team.destroy');

            // Notes (via PatientProcedureController)
            Route::get('{procedure}/notes', [\App\Http\Controllers\PatientProcedureController::class, 'getNotes'])->name('notes.index');
            Route::get('{procedure}/notes/{note}/edit', [\App\Http\Controllers\PatientProcedureController::class, 'getNote'])->name('notes.edit');
            Route::post('{procedure}/notes', [\App\Http\Controllers\PatientProcedureController::class, 'addNote'])->name('notes.store');
            Route::put('{procedure}/notes/{note}', [\App\Http\Controllers\PatientProcedureController::class, 'updateNote'])->name('notes.update');
            Route::delete('{procedure}/notes/{note}', [\App\Http\Controllers\PatientProcedureController::class, 'deleteNote'])->name('notes.destroy');

            // Procedure History Lists (DataTable endpoints for orders history)
            Route::get('{procedure}/lab-history', [\App\Http\Controllers\PatientProcedureController::class, 'labHistoryList'])->name('lab-history');
            Route::get('{procedure}/imaging-history', [\App\Http\Controllers\PatientProcedureController::class, 'imagingHistoryList'])->name('imaging-history');
            Route::get('{procedure}/medication-history', [\App\Http\Controllers\PatientProcedureController::class, 'medicationHistoryList'])->name('medication-history');

            // List procedures by patient (for clinical context modal and workbenches)
            Route::get('list-by-patient/{patient}', [\App\Http\Controllers\PatientProcedureController::class, 'listByPatient'])->name('list-by-patient');
        });

        Route::get('investigationHistoryList/{patient_id}', [EncounterController::class, 'investigationHistoryList'])->name('investigationHistoryList');
        Route::get('imagingHistoryList/{patient_id}', [EncounterController::class, 'imagingHistoryList'])->name('imagingHistoryList');
        Route::get('imagingBillList/{patient_id}', [EncounterController::class, 'imagingBillList'])->name('imagingBillList');
        Route::get('prescHistoryList/{patient_id}', [EncounterController::class, 'prescHistoryList'])->name('prescHistoryList');
        Route::get('prescBillList/{patient_id}', [EncounterController::class, 'prescBillList'])->name('prescBillList');
        Route::get('prescPendingList/{patient_id}', [EncounterController::class, 'prescPendingList'])->name('prescPendingList');
        Route::get('prescReadyList/{patient_id}', [EncounterController::class, 'prescReadyList'])->name('prescReadyList');
        Route::get('prescDispenseList/{patient_id}', [EncounterController::class, 'prescDispenseList'])->name('prescDispenseList');
        Route::get('investBillList/{patient_id}', [EncounterController::class, 'investBillList'])->name('investBillList');
        Route::get('investSampleList/{patient_id}', [EncounterController::class, 'investSampleList'])->name('investSampleList');
        Route::get('investResList/{patient_id}', [LabServiceRequestController::class, 'investResList'])->name('investResList');
        Route::resource('service-requests', LabServiceRequestController::class);
        Route::get('miscBillList/{patient_id}', [MiscBillController::class, 'miscBillList'])->name('miscBillList');
        Route::get('miscBillHistList/{patient_id?}', [MiscBillController::class, 'miscBillHistList'])->name('miscBillHistList');
        Route::post('bill-misc-bill', [MiscBillController::class, 'bill'])->name('bill-misc-bill');
        Route::get('investQueueList', [LabServiceRequestController::class, 'investQueueList'])->name('investQueueList');
        Route::get('investHistoryList', [LabServiceRequestController::class, 'investHistoryList'])->name('investHistoryList');

        // Billing Workbench Routes
        Route::get('/billing-workbench', [\App\Http\Controllers\BillingWorkbenchController::class, 'index'])->name('billing.workbench');
        Route::get('/billing-workbench/search-patients', [\App\Http\Controllers\BillingWorkbenchController::class, 'searchPatients'])->name('billing.search-patients');
        Route::get('/billing-workbench/payment-queue', [\App\Http\Controllers\BillingWorkbenchController::class, 'getPaymentQueue'])->name('billing.payment-queue');
        Route::get('/billing-workbench/queue-counts', [\App\Http\Controllers\BillingWorkbenchController::class, 'getQueueCounts'])->name('billing.queue-counts');
        Route::get('/billing-workbench/patient/{id}/billing-data', [\App\Http\Controllers\BillingWorkbenchController::class, 'getPatientBillingData'])->name('billing.patient-billing-data');
        Route::get('/billing-workbench/patient/{id}/receipts', [\App\Http\Controllers\BillingWorkbenchController::class, 'getPatientReceipts'])->name('billing.patient-receipts');
        Route::get('/billing-workbench/patient/{id}/transactions', [\App\Http\Controllers\BillingWorkbenchController::class, 'getPatientTransactions'])->name('billing.patient-transactions');
        Route::get('/billing-workbench/patient/{id}/account-transactions', [\App\Http\Controllers\BillingWorkbenchController::class, 'getAccountTransactions'])->name('billing.patient-account-transactions');
        Route::get('/billing-workbench/patient/{id}/account-summary', [\App\Http\Controllers\BillingWorkbenchController::class, 'getPatientAccountSummary'])->name('billing.patient-account-summary');
        Route::post('/billing-workbench/process-payment', [\App\Http\Controllers\BillingWorkbenchController::class, 'processPayment'])->name('billing.process-payment');
        Route::post('/billing-workbench/print-receipt', [\App\Http\Controllers\BillingWorkbenchController::class, 'printReceipt'])->name('billing.print-receipt');
        Route::get('/billing-workbench/my-transactions', [\App\Http\Controllers\BillingWorkbenchController::class, 'getMyTransactions'])->name('billing.my-transactions');
        Route::post('/billing-workbench/create-account', [\App\Http\Controllers\BillingWorkbenchController::class, 'createPatientAccount'])->name('billing.create-account');
        Route::post('/billing-workbench/make-deposit', [\App\Http\Controllers\BillingWorkbenchController::class, 'makeAccountDeposit'])->name('billing.make-deposit');
        Route::post('/billing-workbench/account-transaction', [\App\Http\Controllers\BillingWorkbenchController::class, 'processAccountTransaction'])->name('billing.account-transaction');

        // Pharmacy Workbench Routes
        Route::get('/pharmacy-workbench', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'index'])->name('pharmacy.workbench');
        Route::get('/pharmacy-workbench/search-patients', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'searchPatients'])->name('pharmacy.search-patients');
        Route::get('/pharmacy-workbench/search-products', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'searchProducts'])->name('pharmacy.search-products');
        Route::get('/pharmacy-workbench/prescription-queue', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getPrescriptionQueue'])->name('pharmacy.prescription-queue');
        Route::get('/pharmacy-workbench/queue-counts', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getQueueCounts'])->name('pharmacy.queue-counts');
        Route::get('/pharmacy-workbench/patient/{id}/prescription-data', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getPatientPrescriptionData'])->name('pharmacy.patient-prescription-data');
        Route::get('/pharmacy-workbench/patient/{id}/dispensing-history', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getPatientDispensingHistory'])->name('pharmacy.patient-dispensing-history');
        // Store and stock routes
        Route::get('/pharmacy-workbench/stores', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getStores'])->name('pharmacy.stores');
        Route::get('/pharmacy-workbench/product/{id}/stock', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getProductStockByStore'])->name('pharmacy.product-stock');
        Route::post('/pharmacy-workbench/validate-cart-stock', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'validateCartStock'])->name('pharmacy.validate-cart-stock');
        // DataTables endpoints (matching presc.blade.php pattern)
        Route::get('/pharmacy-workbench/presc-bill-list/{patient_id}', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'prescBillList'])->name('pharmacy.presc-bill-list');
        Route::get('/pharmacy-workbench/presc-dispense-list/{patient_id}', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'prescDispenseList'])->name('pharmacy.presc-dispense-list');
        Route::get('/pharmacy-workbench/presc-history-list/{patient_id}', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'prescHistoryList'])->name('pharmacy.presc-history-list');
        Route::post('/pharmacy-workbench/dispense', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'dispenseMedication'])->name('pharmacy.dispense');
        Route::post('/pharmacy-workbench/record-billing', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'recordBilling'])->name('pharmacy.record-billing');
        Route::post('/pharmacy-workbench/bill', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'billPrescriptions'])->name('pharmacy.bill');
        Route::post('/pharmacy-workbench/dismiss', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'dismissPrescriptions'])->name('pharmacy.dismiss');
        Route::post('/pharmacy-workbench/create-request', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'createPrescriptionRequest'])->name('pharmacy.create-request');
        Route::get('/pharmacy-workbench/my-transactions', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getMyTransactions'])->name('pharmacy.my-transactions');
        Route::post('/pharmacy-workbench/print-prescription-slip', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'printPrescriptionSlip'])->name('pharmacy.print-prescription-slip');

        // Pharmacy Reports & Analytics Routes
        Route::get('/pharmacy-workbench/pharmacists', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getPharmacists'])->name('pharmacy.pharmacists');
        Route::get('/pharmacy-workbench/filter-hmos', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getHmosForFilter'])->name('pharmacy.filterHmos');
        Route::get('/pharmacy-workbench/filter-doctors', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getDoctorsForFilter'])->name('pharmacy.filterDoctors');
        Route::get('/pharmacy-workbench/product-categories', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getProductCategories'])->name('pharmacy.productCategories');
        Route::get('/pharmacy-workbench/reports/statistics', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getReportStatistics'])->name('pharmacy.reports.statistics');
        Route::get('/pharmacy-workbench/reports/dispensing', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getDispensingReport'])->name('pharmacy.reports.dispensing');
        Route::get('/pharmacy-workbench/reports/revenue', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getRevenueReport'])->name('pharmacy.reports.revenue');
        Route::get('/pharmacy-workbench/reports/stock', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getStockReport'])->name('pharmacy.reports.stock');
        Route::get('/pharmacy-workbench/reports/performance', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getPerformanceReport'])->name('pharmacy.reports.performance');
        Route::get('/pharmacy-workbench/reports/hmo-claims', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getHmoClaimsReport'])->name('pharmacy.reports.hmo-claims');
        Route::get('/pharmacy-workbench/reports/top-products', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getTopProducts'])->name('pharmacy.reports.top-products');
        Route::get('/pharmacy-workbench/reports/payment-methods', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'getPaymentMethodsBreakdown'])->name('pharmacy.reports.payment-methods');
        Route::get('/pharmacy-workbench/reports/export', [\App\Http\Controllers\PharmacyWorkbenchController::class, 'exportReports'])->name('pharmacy.reports.export');

        // Lab Workbench Routes
        Route::get('/lab-workbench', [\App\Http\Controllers\LabWorkbenchController::class, 'index'])->name('lab.workbench');
        Route::get('/lab-workbench/patient-search', [\App\Http\Controllers\LabWorkbenchController::class, 'searchPatients'])->name('lab.search-patients');
        Route::get('/lab-workbench/queue', [\App\Http\Controllers\LabWorkbenchController::class, 'getLabQueue'])->name('lab.queue');
        Route::get('/lab-workbench/queue-counts', [\App\Http\Controllers\LabWorkbenchController::class, 'getQueueCounts'])->name('lab.queue-counts');
        Route::get('/lab-workbench/patient/{id}/requests', [\App\Http\Controllers\LabWorkbenchController::class, 'getPatientRequests'])->name('lab.patient-requests');
        Route::get('/lab-workbench/patient/{id}/vitals', [\App\Http\Controllers\LabWorkbenchController::class, 'getPatientVitals'])->name('lab.patient-vitals');
        Route::get('/lab-workbench/patient/{id}/notes', [\App\Http\Controllers\LabWorkbenchController::class, 'getPatientNotes'])->name('lab.patient-notes');
        Route::get('/lab-workbench/patient/{id}/medications', [\App\Http\Controllers\LabWorkbenchController::class, 'getPatientMedications'])->name('lab.patient-medications');
        Route::get('/lab-workbench/patient/{id}/clinical-context', [\App\Http\Controllers\LabWorkbenchController::class, 'getClinicalContext'])->name('lab.clinical-context');
        Route::post('/lab-workbench/record-billing', [\App\Http\Controllers\LabWorkbenchController::class, 'recordBilling'])->name('lab.recordBilling');
        Route::post('/lab-workbench/collect-sample', [\App\Http\Controllers\LabWorkbenchController::class, 'collectSample'])->name('lab.collectSample');
        Route::post('/lab-workbench/dismiss-requests', [\App\Http\Controllers\LabWorkbenchController::class, 'dismissRequests'])->name('lab.dismissRequests');
        Route::get('/lab-workbench/lab-service-requests/{id}', [\App\Http\Controllers\LabWorkbenchController::class, 'getLabRequest'])->name('lab.getRequest');
        Route::get('/lab-workbench/lab-service-requests/{id}/attachments', [\App\Http\Controllers\LabWorkbenchController::class, 'getRequestAttachments'])->name('lab.getAttachments');
        Route::post('/lab-workbench/save-result', [\App\Http\Controllers\LabWorkbenchController::class, 'saveResult'])->name('lab.saveResult');

        // Delete, Restore, Dismiss, Audit
        Route::delete('/lab-workbench/lab-service-requests/{id}', [\App\Http\Controllers\LabWorkbenchController::class, 'deleteRequest'])->name('lab.deleteRequest');
        Route::post('/lab-workbench/lab-service-requests/{id}/restore', [\App\Http\Controllers\LabWorkbenchController::class, 'restoreRequest'])->name('lab.restoreRequest');
        Route::post('/lab-workbench/lab-service-requests/{id}/dismiss', [\App\Http\Controllers\LabWorkbenchController::class, 'dismissRequest'])->name('lab.dismissRequest');
        Route::post('/lab-workbench/lab-service-requests/{id}/undismiss', [\App\Http\Controllers\LabWorkbenchController::class, 'undismissRequest'])->name('lab.undismissRequest');
        Route::get('/lab-workbench/deleted-requests/{patientId?}', [\App\Http\Controllers\LabWorkbenchController::class, 'getDeletedRequests'])->name('lab.deletedRequests');
        Route::get('/lab-workbench/dismissed-requests/{patientId?}', [\App\Http\Controllers\LabWorkbenchController::class, 'getDismissedRequests'])->name('lab.dismissedRequests');
        Route::get('/lab-workbench/audit-logs', [\App\Http\Controllers\LabWorkbenchController::class, 'getAuditLogs'])->name('lab.auditLogs');

        // New Request & Reports Routes
        Route::post('/lab-workbench/store-request', [\App\Http\Controllers\LabWorkbenchController::class, 'storeLabRequest'])->name('lab.storeRequest');
        Route::get('/lab-workbench/reports', [\App\Http\Controllers\LabWorkbenchController::class, 'getLabReports'])->name('lab.reports');
        Route::get('/lab-workbench/statistics', [\App\Http\Controllers\LabWorkbenchController::class, 'getLabStatistics'])->name('lab.statistics');
        Route::get('/lab-workbench/filter-doctors', [\App\Http\Controllers\LabWorkbenchController::class, 'getRequestingDoctors'])->name('lab.filterDoctors');
        Route::get('/lab-workbench/filter-hmos', [\App\Http\Controllers\LabWorkbenchController::class, 'getHmosForFilter'])->name('lab.filterHmos');
        Route::get('/lab-workbench/filter-services', [\App\Http\Controllers\LabWorkbenchController::class, 'getLabServicesForFilter'])->name('lab.filterServices');

        // Imaging Workbench Routes
        Route::get('/imaging-workbench', [\App\Http\Controllers\ImagingWorkbenchController::class, 'index'])->name('imaging.workbench');
        Route::get('/imaging-workbench/patient-search', [\App\Http\Controllers\ImagingWorkbenchController::class, 'searchPatients'])->name('imaging.search-patients');
        Route::get('/imaging-workbench/queue', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getImagingQueue'])->name('imaging.queue');
        Route::get('/imaging-workbench/queue-counts', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getQueueCounts'])->name('imaging.queue-counts');
        Route::get('/imaging-workbench/patient/{id}/requests', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getPatientRequests'])->name('imaging.patient-requests');
        Route::get('/imaging-workbench/patient/{id}/vitals', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getPatientVitals'])->name('imaging.patient-vitals');
        Route::get('/imaging-workbench/patient/{id}/notes', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getPatientNotes'])->name('imaging.patient-notes');
        Route::get('/imaging-workbench/patient/{id}/medications', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getPatientMedications'])->name('imaging.patient-medications');
        Route::get('/imaging-workbench/patient/{id}/clinical-context', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getClinicalContext'])->name('imaging.clinical-context');
        Route::get('/imaging-workbench/patient/{patientId}/history', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getImagingHistoryList'])->name('imaging.patient-history');
        Route::post('/imaging-workbench/record-billing', [\App\Http\Controllers\ImagingWorkbenchController::class, 'recordBilling'])->name('imaging.recordBilling');
        Route::post('/imaging-workbench/dismiss-requests', [\App\Http\Controllers\ImagingWorkbenchController::class, 'dismissRequests'])->name('imaging.dismissRequests');
        Route::get('/imaging-workbench/imaging-service-requests/{id}', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getImagingRequest'])->name('imaging.getRequest');
        Route::get('/imaging-workbench/imaging-service-requests/{id}/attachments', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getRequestAttachments'])->name('imaging.getAttachments');
        Route::post('/imaging-workbench/save-result', [\App\Http\Controllers\ImagingWorkbenchController::class, 'saveResult'])->name('imaging.saveResult');
        Route::delete('/imaging-workbench/imaging-service-requests/{id}', [\App\Http\Controllers\ImagingWorkbenchController::class, 'deleteRequest'])->name('imaging.deleteRequest');
        Route::post('/imaging-workbench/imaging-service-requests/{id}/restore', [\App\Http\Controllers\ImagingWorkbenchController::class, 'restoreRequest'])->name('imaging.restoreRequest');
        Route::post('/imaging-workbench/imaging-service-requests/{id}/dismiss', [\App\Http\Controllers\ImagingWorkbenchController::class, 'dismissRequest'])->name('imaging.dismissRequest');
        Route::post('/imaging-workbench/imaging-service-requests/{id}/undismiss', [\App\Http\Controllers\ImagingWorkbenchController::class, 'undismissRequest'])->name('imaging.undismissRequest');
        Route::get('/imaging-workbench/deleted-requests/{patientId?}', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getDeletedRequests'])->name('imaging.deletedRequests');
        Route::get('/imaging-workbench/dismissed-requests/{patientId?}', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getDismissedRequests'])->name('imaging.dismissedRequests');
        Route::get('/imaging-workbench/audit-logs', [\App\Http\Controllers\ImagingWorkbenchController::class, 'getAuditLog'])->name('imaging.auditLogs');
        Route::get('/imaging-workbench/search-services', [\App\Http\Controllers\ImagingWorkbenchController::class, 'searchServices'])->name('imaging.searchServices');
        Route::post('/imaging-workbench/create-request', [\App\Http\Controllers\ImagingWorkbenchController::class, 'createRequest'])->name('imaging.createRequest');

        // Imaging Service Request Routes (Legacy)
        Route::resource('imaging-requests', ImagingServiceRequestController::class);
        Route::post('bill-imaging', [ImagingServiceRequestController::class, 'bill'])->name('bill-imaging');
        Route::post('save-imaging-result', [ImagingServiceRequestController::class, 'saveResult'])->name('save-imaging-result');
        Route::get('imagingResList/{patient_id}', [ImagingServiceRequestController::class, 'imagingResList'])->name('imagingResList');
        Route::get('imagingBillList/{patient_id}', [ImagingServiceRequestController::class, 'imagingBillList'])->name('imagingBillList');
        Route::get('imagingQueueList', [ImagingServiceRequestController::class, 'imagingQueueList'])->name('imagingQueueList');
        Route::get('imagingHistoryList', [ImagingServiceRequestController::class, 'imagingHistoryList'])->name('imaging.historyList');

        Route::get('patientNursngNote/{patient_id}/{note_type}', [NursingNoteController::class, 'patientNursngNote'])->name('patientNursngNote');
        Route::get('EncounterHistoryList/{patient_id}', [EncounterController::class, 'EncounterHistoryList'])->name('EncounterHistoryList');
        Route::post('auto-save-encounter-note', [EncounterController::class, 'autosaveNotes'])->name('auto-save-encounter-note');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('services', ServiceController::class);
        Route::resource('beds', BedController::class);
        Route::get('bed-list', [BedController::class, 'listBeds'])->name('bed-list');

        // Procedure Categories Management
        Route::resource('procedure-categories', \App\Http\Controllers\ProcedureCategoryController::class);
        Route::get('procedure-categories-list', [\App\Http\Controllers\ProcedureCategoryController::class, 'list'])->name('procedure-categories.list');

        // Ward Management
        Route::resource('wards', WardController::class);
        Route::get('ward-list', [WardController::class, 'listWards'])->name('ward-list');
        Route::get('wards-for-select', [WardController::class, 'getWardsForSelect'])->name('wards-for-select');

        // Checklist Templates
        Route::resource('checklist-templates', ChecklistTemplateController::class);
        Route::get('checklist-template-list', [ChecklistTemplateController::class, 'listTemplates'])->name('checklist-template-list');
        Route::post('checklist-templates/{template}/items', [ChecklistTemplateController::class, 'addItem'])->name('checklist-templates.add-item');
        Route::put('checklist-template-items/{item}', [ChecklistTemplateController::class, 'updateItem'])->name('checklist-template-items.update');
        Route::delete('checklist-template-items/{item}', [ChecklistTemplateController::class, 'deleteItem'])->name('checklist-template-items.delete');

        Route::resource('admission-requests', AdmissionRequestController::class);
        Route::get('discharge-patient/{admission_req_id}', [AdmissionRequestController::class, 'dischargePatient'])->name('discharge-patient');
        Route::post('discharge-patient-api/{admission_req_id}', [AdmissionRequestController::class, 'dischargePatientApi'])->name('discharge-patient-api');
        Route::post('assign-bed', [AdmissionRequestController::class, 'assignBed'])->name('assign-bed');
        Route::post('assign-bill', [AdmissionRequestController::class, 'assignBill'])->name('assign-bill');
        Route::get('bed-coverage', [AdmissionRequestController::class, 'getBedCoverage'])->name('bed-coverage');
        Route::get('services-list', [ServiceController::class, 'listServices'])->name('services-list');
        Route::get('services/{id}/build-template', [ServiceController::class, 'buildTemplate'])->name('services.build-template');
        Route::post('services/{id}/save-template', [ServiceController::class, 'saveTemplate'])->name('services.save-template');
        Route::get('servicess/{id}', [accountsController::class, 'index'])->name('servicess');
        Route::get('services-list/{id}', [accountsController::class, 'services'])->name('service-list');
        Route::get('product-list/{id}', [accountsController::class, 'products'])->name('accounts.product-list');
        Route::get('settled-services/{id}', [accountsController::class, 'settledServices'])->name('settled-services');
        Route::get('settled-products/{id}', [accountsController::class, 'settledProducts'])->name('settled-products');
        Route::get('paid-services/{id}', [accountsController::class, 'serviceView'])->name('paid-services');
        Route::get('paid-products/{id}', [accountsController::class, 'productView'])->name('paid-products');
        Route::get('back', [paymentController::class, 'back'])->name('back');
        Route::post('service-payment', [paymentController::class, 'process'])->name('service-payment');
        Route::post('complete-payment', [paymentController::class, 'payment'])->name('complete-payment');
        // AJAX payment (no sessions)
        Route::get('ajax/unpaid-items/{user}', [paymentController::class, 'ajaxUnpaid'])->name('ajax-unpaid-items');
        Route::post('ajax/pay', [paymentController::class, 'ajaxPay'])->name('ajax-pay');
        Route::post('product-payment', [ProductAccountController::class, 'process'])->name('product-payment');
        Route::get('listSalesService/{id}', [ServiceController::class, 'listSalesService'])->name('listSalesService');
        Route::get('live-search-services', [ServiceController::class, 'liveSearchServices'])->name('live-search-services');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('services-category', ServiceCategoryController::class);
        Route::get('services-category-list', [ServiceCategoryController::class, 'listServiceCategories'])->name('services-category-list');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing patient forms
        Route::resource('patient-form', PatientProfileController::class);
        Route::get('patient-form-list/{patient_id}', [PatientProfileController::class, 'listPatientForm'])->name('patient-form-list');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('stocks', StockController::class);
        Route::get('stock-list', [StockController::class, 'listStock'])->name('stock-list');
    });

    // nursig note types routes
    Route::group(['middleware' => ['auth']], function () {
        Route::resource('nursing-note-types', NursingNoteTypeController::class);
        Route::resource('nursing-note', NursingNoteController::class);
        Route::post('nursing-note/new', [NursingNoteController::class, 'new_note'])->name('nursing-note.new');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('stores', StoreController::class);
        Route::get('store-list', [StoreController::class, 'listStores'])->name('store-list');
    });

    // Supplier routes
    Route::group(['middleware' => ['auth']], function () {
        Route::resource('suppliers', \App\Http\Controllers\SupplierController::class);
        Route::get('supplier-list', [\App\Http\Controllers\SupplierController::class, 'listSuppliers'])->name('suppliers.list');
        Route::get('suppliers-search', [\App\Http\Controllers\SupplierController::class, 'search'])->name('suppliers.search');
        Route::get('suppliers-export', [\App\Http\Controllers\SupplierController::class, 'export'])->name('suppliers.export');

        // Supplier Reports
        Route::prefix('suppliers/reports')->name('suppliers.reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SupplierController::class, 'reports'])->name('index');
            Route::get('/performance', [\App\Http\Controllers\SupplierController::class, 'performanceReport'])->name('performance');
            Route::get('/batches', [\App\Http\Controllers\SupplierController::class, 'batchesReport'])->name('batches');
        });
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('stores-stokes', StoreStockController::class);
        Route::resource('move-stock', MoveStockController::class);
        Route::get('store-stock-list', [StoreStockController::class, 'listStoreStock'])->name('store-stock-list');

        Route::get('listStoresProducts/{id}', [StoreStockController::class, 'listStoresProducts'])->name('listStoresProducts');
        Route::get('listProductslocations/{id}', [StoreStockController::class, 'listProductslocations'])->name('listProductslocations');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('prices', PriceController::class);
        Route::get('price-list', [PriceController::class, 'listPrice'])->name('price-list');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('service-prices', ServicePriceController::class);
        Route::get('service-price-list', [ServicePriceController::class, 'listServicePrice'])->name('service-price-list');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('product-or-service-request', ProductOrServiceRequestController::class);
        Route::get('product-services-requesters-list/{patient_user_id?}', [ProductOrServiceRequestController::class, 'productOrServicesRequestersList'])->name('product-services-requesters-list');
        Route::get('admission-requests-list', [AdmissionRequestController::class, 'admissionRequests'])->name('admission-requests-list');
        Route::get('my-admission-requests-list', [AdmissionRequestController::class, 'myAdmissionRequests'])->name('my-admission-requests-list');
        Route::get('patient-admission-requests-list/{patient_id}', [AdmissionRequestController::class, 'patientAdmissionRequests'])->name('patient-admission-requests-list');
        Route::resource('admission-requests', AdmissionRequestController::class);
    });

    // HMO Workbench (must come before hmo resource routes to avoid route conflict)
    Route::group(['middleware' => ['auth', 'role:SUPERADMIN|ADMIN|HMO Executive']], function () {
        Route::get('hmo/workbench', [HmoWorkbenchController::class, 'index'])->name('hmo.workbench');
        Route::get('hmo/requests', [HmoWorkbenchController::class, 'getRequests'])->name('hmo.requests');
        Route::get('hmo/requests/{id}', [HmoWorkbenchController::class, 'show'])->name('hmo.requests.show');
        Route::post('hmo/requests/{id}/approve', [HmoWorkbenchController::class, 'approveRequest'])->name('hmo.requests.approve');
        Route::post('hmo/requests/{id}/reject', [HmoWorkbenchController::class, 'rejectRequest'])->name('hmo.requests.reject');
        Route::post('hmo/requests/{id}/reverse', [HmoWorkbenchController::class, 'reverseApproval'])->name('hmo.requests.reverse');
        Route::post('hmo/requests/{id}/reapprove', [HmoWorkbenchController::class, 'reapproveRequest'])->name('hmo.requests.reapprove');
        Route::post('hmo/batch-approve', [HmoWorkbenchController::class, 'batchApprove'])->name('hmo.batch-approve');
        Route::post('hmo/batch-reject', [HmoWorkbenchController::class, 'batchReject'])->name('hmo.batch-reject');
        Route::get('hmo/queue-counts', [HmoWorkbenchController::class, 'getQueueCounts'])->name('hmo.queue-counts');
        Route::get('hmo/financial-summary', [HmoWorkbenchController::class, 'getFinancialSummary'])->name('hmo.financial-summary');
        Route::get('hmo/export-claims', [HmoWorkbenchController::class, 'exportClaimsReport'])->name('hmo.export-claims');
        Route::get('hmo/patient/{patientId}/history', [HmoWorkbenchController::class, 'getPatientHistory'])->name('hmo.patient.history');
        Route::get('hmo/patient/{patientId}/vitals', [HmoWorkbenchController::class, 'getPatientVitals'])->name('hmo.patient.vitals');
        Route::get('hmo/patient/{patientId}/notes', [HmoWorkbenchController::class, 'getPatientNotes'])->name('hmo.patient.notes');
        Route::get('hmo/patient/{patientId}/medications', [HmoWorkbenchController::class, 'getPatientMedications'])->name('hmo.patient.medications');

        // HMO Reports
        Route::get('hmo/reports', [HmoReportsController::class, 'index'])->name('hmo.reports');
        Route::get('hmo/reports/claims', [HmoReportsController::class, 'getClaimsReport'])->name('hmo.reports.claims');
        Route::get('hmo/reports/outstanding', [HmoReportsController::class, 'getOutstandingReport'])->name('hmo.reports.outstanding');
        Route::get('hmo/reports/patient/{patientId}', [HmoReportsController::class, 'getPatientReport'])->name('hmo.reports.patient');
        Route::get('hmo/reports/patient/{patientId}/print', [HmoReportsController::class, 'getPatientPrintData'])->name('hmo.reports.patient.print');
        Route::get('hmo/reports/monthly', [HmoReportsController::class, 'getMonthlySummary'])->name('hmo.reports.monthly');
        Route::get('hmo/reports/utilization', [HmoReportsController::class, 'getUtilizationReport'])->name('hmo.reports.utilization');
        Route::get('hmo/reports/auth-codes', [HmoReportsController::class, 'getAuthCodeReport'])->name('hmo.reports.auth-codes');
        Route::get('hmo/reports/remittances', [HmoReportsController::class, 'getRemittances'])->name('hmo.reports.remittances');
        Route::post('hmo/reports/remittances', [HmoReportsController::class, 'storeRemittance'])->name('hmo.reports.remittances.store');
        Route::get('hmo/reports/remittances/{id}', [HmoReportsController::class, 'showRemittance'])->name('hmo.reports.remittances.show');
        Route::put('hmo/reports/remittances/{id}', [HmoReportsController::class, 'updateRemittance'])->name('hmo.reports.remittances.update');
        Route::delete('hmo/reports/remittances/{id}', [HmoReportsController::class, 'deleteRemittance'])->name('hmo.reports.remittances.delete');
        Route::post('hmo/reports/mark-submitted', [HmoReportsController::class, 'markAsSubmitted'])->name('hmo.reports.mark-submitted');
        Route::post('hmo/reports/link-claims', [HmoReportsController::class, 'linkClaimsToRemittance'])->name('hmo.reports.link-claims');
        Route::get('hmo/reports/print-data', [HmoReportsController::class, 'getPrintData'])->name('hmo.reports.print-data');
        Route::get('hmo/reports/export-excel', [HmoReportsController::class, 'exportExcel'])->name('hmo.reports.export-excel');
        Route::get('hmo/reports/export-pdf', [HmoReportsController::class, 'exportPdf'])->name('hmo.reports.export-pdf');
        Route::get('hmo/reports/search-patients', [HmoReportsController::class, 'searchPatients'])->name('hmo.reports.search-patients');
    });

    // HMO Tariff Management
    Route::group(['middleware' => ['auth', 'role:SUPERADMIN|ADMIN']], function () {
        Route::get('admin/hmo-tariffs', [TariffManagementController::class, 'index'])->name('hmo-tariffs.index');
        Route::get('admin/hmo-tariffs/data', [TariffManagementController::class, 'getTariffs'])->name('hmo-tariffs.data');
        Route::post('admin/hmo-tariffs', [TariffManagementController::class, 'store'])->name('hmo-tariffs.store');
        Route::get('admin/hmo-tariffs/{id}', [TariffManagementController::class, 'show'])->name('hmo-tariffs.show');
        Route::put('admin/hmo-tariffs/{id}', [TariffManagementController::class, 'update'])->name('hmo-tariffs.update');
        Route::delete('admin/hmo-tariffs/{id}', [TariffManagementController::class, 'destroy'])->name('hmo-tariffs.destroy');
        Route::get('admin/hmo-tariffs/export/csv', [TariffManagementController::class, 'exportCsv'])->name('hmo-tariffs.export');
        Route::post('admin/hmo-tariffs/import/csv', [TariffManagementController::class, 'importCsv'])->name('hmo-tariffs.import');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('hmo', HmoController::class);
        Route::get('hmoList', [HmoController::class, 'listHmo'])->name('hmoList');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('vitals', VitalSignController::class);
        Route::get('patientVitalsQueue/{patient_id}', [VitalSignController::class, 'patientVitals'])->name('patient-vitals');
        Route::get('patientVitalsQueue', [VitalSignController::class, 'patientVitalsQueue'])->name('patientVitalsQueue');
        Route::get('patientVitalsHistoryQueue', [VitalSignController::class, 'patientVitalsHistoryQueue'])->name('patientVitalsHistoryQueue');
        Route::get('allPatientVitals/{patient_id}', [VitalSignController::class, 'allPatientVitals'])->name('allPatientVitals');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::get('merged-list/{id}', [App\Http\Controllers\Account\accountsController::class, 'mergedList']);
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::get('transactions', [App\Http\Controllers\Account\paymentController::class, 'transactions'])->name('transactions');
        Route::get('my-transactions', [App\Http\Controllers\Account\paymentController::class, 'myTransactions'])->name('my-transactions');
    });
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
// routes/web.php
Route::get('/api/chart/clinic-appointments', [App\Http\Controllers\HomeController::class, 'fetchClinicAppointments'])->name('api.clinic.appointments');
Route::prefix('api/chart/clinic')->group(function () {
    Route::get('/timeline', [App\Http\Controllers\HomeController::class, 'chartAppointmentsOverTime'])->name('api.chart.clinic.timeline');
    Route::get('/by', [App\Http\Controllers\HomeController::class, 'chartAppointmentsByClinic'])->name('api.chart.clinic.by');
    Route::get('/services', [App\Http\Controllers\HomeController::class, 'chartTopClinicServices'])->name('api.chart.clinic.services');
    Route::get('/status', [App\Http\Controllers\HomeController::class, 'chartQueueStatus'])->name('api.chart.clinic.status');
});

// Dashboard stats API
Route::get('/dashboard/receptionist-stats', [App\Http\Controllers\HomeController::class, 'dashboardStats'])->name('dashboard.receptionist-stats');
Route::get('/dashboard/biller-stats', [App\Http\Controllers\HomeController::class, 'billerStats'])->name('dashboard.biller-stats');
Route::get('/dashboard/admin-stats', [App\Http\Controllers\HomeController::class, 'adminStats'])->name('dashboard.admin-stats');
Route::get('/dashboard/pharmacy-stats', [App\Http\Controllers\HomeController::class, 'pharmacyStats'])->name('dashboard.pharmacy-stats');
Route::get('/dashboard/nursing-stats', [App\Http\Controllers\HomeController::class, 'nursingStats'])->name('dashboard.nursing-stats');
Route::get('/dashboard/lab-stats', [App\Http\Controllers\HomeController::class, 'labStats'])->name('dashboard.lab-stats');
Route::get('/dashboard/doctor-stats', [App\Http\Controllers\HomeController::class, 'doctorStats'])->name('dashboard.doctor-stats');
Route::get('/dashboard/hmo-stats', [App\Http\Controllers\HomeController::class, 'hmoStats'])->name('dashboard.hmo-stats');

// Dashboard charts API
Route::get('/dashboard/chart/revenue', [App\Http\Controllers\HomeController::class, 'chartRevenueOverTime'])->name('dashboard.chart.revenue');
Route::get('/dashboard/chart/registrations', [App\Http\Controllers\HomeController::class, 'chartPatientRegistrations'])->name('dashboard.chart.registrations');

Route::get('/accounts', [App\Http\Controllers\Account\accountsController::class, 'index']);

Route::group(['prefix' => 'doctor', 'middleware' => ['auth']], function () {
    Route::get('/home', [DoctorDashboardController::class, 'index'])->name('doctor.dashboard');

    Route::get('/consultations', [DoctorConsultationsController::class, 'index'])->name('doctor.consultations');
});
Route::group(['prefix' => 'chat', 'middleware' => ['auth']], function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/conversations', [ChatController::class, 'getConversations'])->name('chat.conversations');
    Route::get('/messages/{conversationId}', [ChatController::class, 'getMessages'])->name('chat.messages');
    Route::post('/mark-read/{conversationId}', [ChatController::class, 'markAsRead'])->name('chat.mark-read');
    Route::get('/check-unread', [ChatController::class, 'checkUnread'])->name('chat.check-unread');
    Route::post('/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('/create', [ChatController::class, 'createConversation'])->name('chat.create');
    Route::get('/search-users', [ChatController::class, 'searchUsers'])->name('chat.search-users');
    Route::get('/search-messages/{conversationId}', [ChatController::class, 'searchMessagesInConversation'])->name('chat.search-messages');
    Route::delete('/message/{messageId}', [ChatController::class, 'deleteMessage'])->name('chat.delete-message');
    Route::post('/archive/{conversationId}', [ChatController::class, 'archiveConversation'])->name('chat.archive');
    Route::post('/unarchive/{conversationId}', [ChatController::class, 'unarchiveConversation'])->name('chat.unarchive');
});

Route::get('my-profile', [StaffController::class, 'my_profile'])->name('my-profile');
Route::post('update-my-profile', [StaffController::class, 'update_my_profile'])->name('update-my-profile');

// Nurse Chart routes
require __DIR__ . '/nurse_chart.php';

// Reception Workbench routes
require __DIR__ . '/reception_workbench.php';

// Inventory Management routes (PO, Requisitions, Store Workbench)
require __DIR__ . '/inventory.php';
