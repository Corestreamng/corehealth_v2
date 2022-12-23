<?php

use App\Http\Controllers\PatientController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\RoleController;
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
    Route::put('users/updateAvatar/{id}', 'Admin\UserController@updateAvatar')->name('users.updateAvatar');

    Route::group(['middleware' => ['role:Users']], function () {
        // Creating and Listing Users
        Route::get('listUsers', [StaffController::class, 'listUsers'])->name('listUsers');
        Route::resource('users', StaffController::class);
    });

    Route::group(['middleware' => ['role:Roles']], function () {
        // Creating and Listing Roles
        Route::get('listRoles', [RoleController::class,'listRoles'])->name('listRoles');
        Route::resource('roles', RoleController::class);
    });

    Route::group(['middleware' => ['role:Permissions']], function () {
        // Creating and Listing Permissions
        Route::get('listPermissions', [PermissionController::class, 'listPermissions'])->name('listPermissions');
        Route::resource('permissions', PermissionController::class);
    });

    Route::resource('patient', PatientController::class);
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
