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
        Route::get('listReturningPatients', [PatientController::class,'listReturningPatients'])->name('listReturningPatients');
        Route::get('getMyDependants/{id}',[PatientController::class,'getMyDependants'])->name('getMyDependants');
        Route::get('get-doctors/{id}',[ClinicController::class,'getDoctors'])->name('get-doctors');
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
        Route::get('listSalesProduct/{id}', [ProductController::class,'listSalesProduct'])->name('listSalesProduct');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('services', ServiceController::class);
        Route::get('services-list', [ServiceController::class, 'listServices'])->name('services-list');
        Route::get('servicess/{id}',[accountsController::class,'index'])->name('servicess');
        Route::get('services-list/{id}',[accountsController::class,'services'])->name('service-list');
        Route::get('product-list/{id}',[accountsController::class,'products'])->name('product-list');
        Route::get('settled-services/{id}',[accountsController::class,'settledServices'])->name('settled-services');
        Route::get('settled-products/{id}',[accountsController::class,'settledProducts'])->name('settled-products');
        Route::get('paid-services/{id}',[accountsController::class,'serviceView'])->name('paid-services');
        Route::get('paid-products/{id}',[accountsController::class,'productView'])->name('paid-products');
        Route::post('service-payment',[paymentController::class,'process'])->name('service-payment');
        Route::post('complete-payment',[paymentController::class,'payment'])->name('complete-payment');
        Route::post('product-payment',[ProductAccountController::class,'process'])->name('product-payment');
        Route::get('listSalesService/{id}', [ServiceController::class,'listSalesService'])->name('listSalesService');
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

        Route::get('listStoresProducts/{id}', [StoreStockController::class,'listStoresProducts'])->name('listStoresProducts');
        Route::get('listProductslocations/{id}', [StoreStockController::class,'listProductslocations'])->name('listProductslocations');

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
        Route::get('product-services-requesters-list', [ProductOrServiceRequestController::class, 'productOrServicesRequestersList'])->name('product-services-requesters-list');
    });

    Route::group(['middleware' => ['auth']], function () {
        // Creating and Listing Permissions
        Route::resource('hmo', HmoController::class);
        Route::get('hmoList', [HmoController::class, 'listHmo'])->name('hmoList');
    });
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/accounts', [App\Http\Controllers\Account\accountsController::class, 'index']);



Route::group(['prefix' => 'doctor', 'middleware' => ['auth']], function () {
    Route::get('/home', [DoctorDashboardController::class, 'index'])->name('doctor.dashboard');


    Route::get('/consultations', [DoctorConsultationsController::class, 'index'])->name('doctor.consultations');
});
