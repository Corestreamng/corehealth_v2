<?php

use App\Http\Controllers\ReceptionWorkbenchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reception Workbench Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('reception')->name('reception.')->group(function () {
    // Main workbench view
    Route::get('/workbench', [ReceptionWorkbenchController::class, 'index'])->name('workbench');

    // Patient search and data
    Route::get('/search-patients', [ReceptionWorkbenchController::class, 'searchPatients'])->name('search-patients');
    Route::get('/patient/{id}', [ReceptionWorkbenchController::class, 'getPatient'])->name('patient');
    Route::get('/patient/{id}/visits', [ReceptionWorkbenchController::class, 'getVisitHistory'])->name('patient.visits');

    // Queue management
    Route::get('/queue-counts', [ReceptionWorkbenchController::class, 'getQueueCounts'])->name('queue-counts');
    Route::get('/queue-list', [ReceptionWorkbenchController::class, 'getQueueList'])->name('queue-list');

    // Reference data
    Route::get('/clinics', [ReceptionWorkbenchController::class, 'getClinics'])->name('clinics');
    Route::get('/clinics/{id}/doctors', [ReceptionWorkbenchController::class, 'getDoctorsByClinic'])->name('clinic.doctors');
    Route::get('/services/consultation', [ReceptionWorkbenchController::class, 'getConsultationServices'])->name('services.consultation');
    Route::get('/services/lab', [ReceptionWorkbenchController::class, 'getLabServices'])->name('services.lab');
    Route::get('/services/imaging', [ReceptionWorkbenchController::class, 'getImagingServices'])->name('services.imaging');
    Route::get('/products', [ReceptionWorkbenchController::class, 'getProducts'])->name('products');
    Route::get('/hmos', [ReceptionWorkbenchController::class, 'getHmos'])->name('hmos');

    // Tariff preview
    Route::post('/tariff-preview', [ReceptionWorkbenchController::class, 'getTariffPreview'])->name('tariff-preview');

    // Booking actions
    Route::post('/book-consultation', [ReceptionWorkbenchController::class, 'bookConsultation'])->name('book-consultation');
    Route::post('/book-walkin', [ReceptionWorkbenchController::class, 'bookWalkinServices'])->name('book-walkin');

    // Patient registration
    Route::post('/patient/quick-register', [ReceptionWorkbenchController::class, 'quickRegister'])->name('patient.quick-register');

    // Statistics
    Route::get('/today-stats', [ReceptionWorkbenchController::class, 'getTodayStats'])->name('today-stats');
});
