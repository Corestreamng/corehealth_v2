<?php

use App\Http\Controllers\Account\accountsController;
use App\Http\Controllers\Account\paymentController;
use App\Http\Controllers\Account\productAccountController;
use App\Http\Controllers\AdmissionRequestController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\Doctor\DoctorConsultationsController;
use App\Http\Controllers\Doctor\DoctorDashboardController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\HmoController;
use App\Http\Controllers\HmoWorkbenchController;
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
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServicePriceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreStockController;
use App\Http\Controllers\VitalSignController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\PatientProfileController;
use App\Http\Controllers\SpecializationController;
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

Route::group(['middleware' => ['auth']], function () {
    // Route::put('staff/updateAvatar/{id}', 'Admin\UserController@updateAvatar')->name('users.updateAvatar');

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

    // Audit Logs
    Route::group(['middleware' => ['auth']], function () {
        Route::get('audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/data', [\App\Http\Controllers\AuditLogController::class, 'getData'])->name('audit-logs.data');
        Route::get('audit-logs/stats', [\App\Http\Controllers\AuditLogController::class, 'stats'])->name('audit-logs.stats');
        Route::get('audit-logs/export', [\App\Http\Controllers\AuditLogController::class, 'export'])->name('audit-logs.export');
        Route::get('audit-logs/{id}', [\App\Http\Controllers\AuditLogController::class, 'show'])->name('audit-logs.show');
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
        Route::get('prescQueueList', [ProductRequestController::class, 'prescQueueList'])->name('prescQueueList');
        Route::get('prescQueueHistoryList', [ProductRequestController::class, 'prescQueueHistoryList'])->name('prescQueueHistoryList');
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

        Route::get('investigationHistoryList/{patient_id}', [EncounterController::class, 'investigationHistoryList'])->name('investigationHistoryList');
        Route::get('imagingHistoryList/{patient_id}', [EncounterController::class, 'imagingHistoryList'])->name('imagingHistoryList');
        Route::get('imagingBillList/{patient_id}', [EncounterController::class, 'imagingBillList'])->name('imagingBillList');
        Route::get('prescHistoryList/{patient_id}', [EncounterController::class, 'prescHistoryList'])->name('prescHistoryList');
        Route::get('prescBillList/{patient_id}', [EncounterController::class, 'prescBillList'])->name('prescBillList');
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

        // Imaging Service Request Routes
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
        Route::resource('admission-requests', AdmissionRequestController::class);
        Route::get('discharge-patient/{admission_req_id}', [AdmissionRequestController::class, 'dischargePatient'])->name('discharge-patient');
        Route::post('discharge-patient-api/{admission_req_id}', [AdmissionRequestController::class, 'dischargePatientApi'])->name('discharge-patient-api');
        Route::post('assign-bed', [AdmissionRequestController::class, 'assignBed'])->name('assign-bed');
        Route::post('assign-bill', [AdmissionRequestController::class, 'assignBill'])->name('assign-bill');
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
        Route::get('hmo/queue-counts', [HmoWorkbenchController::class, 'getQueueCounts'])->name('hmo.queue-counts');
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
