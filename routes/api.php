<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DataEndpoint;

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
