<?php

use App\Http\Controllers\Doctor\DoctorConsultationsController;
use App\Http\Controllers\Doctor\DoctorDashboardController;
use App\Http\Controllers\HmoController;
use App\Http\Controllers\MoveStockController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductOrServiceRequestController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServicePriceController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreStockController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\Account\accountsController;
use App\Http\Controllers\Account\paymentController;
use App\Http\Controllers\Account\productAccountController;
use App\Http\Controllers\AdmissionRequestController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\LabServiceRequestController;
use App\Http\Controllers\MiscBillController;
use App\Http\Controllers\NursingNoteController;
use App\Http\Controllers\NursingNoteTypeController;
use App\Http\Controllers\PatientAccountController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\VitalSignController;
use App\Models\AdmissionRequest;
use App\Models\LabServiceRequest;
use App\Models\PatientAccount;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

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

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::get('listPermissions', [PermissionController::class, 'listPermissions'])->name('listPermissions');
        Route::resource('permissions', PermissionController::class);
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('patient', PatientController::class);
        Route::get('patientsList', [PatientController::class, 'patientsList'])->name('patientsList');
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
        Route::get('NewEncounterList', [EncounterController::class, 'NewEncounterList'])->name('NewEncounterList');
        Route::get('PrevEncounterList', [EncounterController::class, 'PrevEncounterList'])->name('PrevEncounterList');
        Route::get('investigationHistoryList/{patient_id}', [EncounterController::class, 'investigationHistoryList'])->name('investigationHistoryList');
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
        Route::get('patientNursngNote/{patient_id}/{note_type}', [NursingNoteController::class, 'patientNursngNote'])->name('patientNursngNote');
        Route::get('EncounterHistoryList/{patient_id}', [EncounterController::class, 'EncounterHistoryList'])->name('EncounterHistoryList');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('services', ServiceController::class);
        Route::resource('beds', BedController::class);
        Route::get('bed-list', [BedController::class, 'listBeds'])->name('bed-list');
        Route::resource('admission-requests', AdmissionRequestController::class);
        Route::get('discharge-patient/{admission_req_id}', [AdmissionRequestController::class, 'dischargePatient'])->name('discharge-patient');
        Route::post('assign-bed', [AdmissionRequestController::class, 'assignBed'])->name('assign-bed');
        Route::post('assign-bill', [AdmissionRequestController::class, 'assignBill'])->name('assign-bill');
        Route::get('services-list', [ServiceController::class, 'listServices'])->name('services-list');
        Route::get('servicess/{id}', [accountsController::class, 'index'])->name('servicess');
        Route::get('services-list/{id}', [accountsController::class, 'services'])->name('service-list');
        Route::get('product-list/{id}', [accountsController::class, 'products'])->name('product-list');
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
        // Creating and Listing Permissions
        Route::resource('stocks', StockController::class);
        Route::get('stock-list', [StockController::class, 'listStock'])->name('stock-list');
    });

    //nursig note types routes
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
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/accounts', [App\Http\Controllers\Account\accountsController::class, 'index']);



Route::group(['prefix' => 'doctor', 'middleware' => ['auth']], function () {
    Route::get('/home', [DoctorDashboardController::class, 'index'])->name('doctor.dashboard');


    Route::get('/consultations', [DoctorConsultationsController::class, 'index'])->name('doctor.consultations');
});
