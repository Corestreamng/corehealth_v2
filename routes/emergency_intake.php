<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmergencyIntakeController;

/*
|--------------------------------------------------------------------------
| Emergency Intake Routes
|--------------------------------------------------------------------------
|
| These routes handle emergency/walk-in patient intake from any workbench.
| Accessible by any authenticated staff member.
|
*/

Route::middleware(['web', 'auth'])->prefix('emergency')->name('emergency.')->group(function () {

    // Process emergency intake (patient registration + triage + disposition)
    Route::post('/intake', [EmergencyIntakeController::class, 'intake'])->name('intake');

    // Get available emergency beds
    Route::get('/available-beds', [EmergencyIntakeController::class, 'getEmergencyBeds'])->name('available-beds');

    // Search patients (for emergency modal)
    Route::get('/search-patient', [EmergencyIntakeController::class, 'searchPatient'])->name('search-patient');

    // Get emergency queue (for nursing workbench)
    Route::get('/queue', [EmergencyIntakeController::class, 'getEmergencyQueue'])->name('queue');

    // Get clinics (for consultation routing)
    Route::get('/clinics', [EmergencyIntakeController::class, 'getClinics'])->name('clinics');

    // Get services grouped by type (admission + consultation) for select boxes
    Route::get('/services', [EmergencyIntakeController::class, 'getServices'])->name('services');
});
