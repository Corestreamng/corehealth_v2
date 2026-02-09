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

    // Patient registration - these must come BEFORE /patient/{id} to avoid conflicts
    Route::post('/patient/quick-register', [ReceptionWorkbenchController::class, 'quickRegister'])->name('patient.quick-register');
    Route::get('/patient/next-file-number', [ReceptionWorkbenchController::class, 'getNextFileNumber'])->name('patient.next-file-number');
    Route::post('/patient/check-file-number', [ReceptionWorkbenchController::class, 'checkFileNumberExists'])->name('patient.check-file-number');

    // Patient data with ID parameter
    Route::get('/patient/{id}', [ReceptionWorkbenchController::class, 'getPatient'])->name('patient');
    Route::get('/patient/{id}/visits', [ReceptionWorkbenchController::class, 'getVisitHistory'])->name('patient.visits');
    Route::get('/patient/{id}/queue', [ReceptionWorkbenchController::class, 'getPatientQueueEntries'])->name('patient.queue');
    Route::put('/patient/{id}/update', [ReceptionWorkbenchController::class, 'updatePatient'])->name('patient.update');

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

    // Statistics
    Route::get('/today-stats', [ReceptionWorkbenchController::class, 'getTodayStats'])->name('today-stats');

    // Reports
    Route::get('/reports/statistics', [ReceptionWorkbenchController::class, 'getReportsStatistics'])->name('reports.statistics');
    Route::get('/reports/registrations', [ReceptionWorkbenchController::class, 'getRegistrationsReport'])->name('reports.registrations');
    Route::get('/reports/queue', [ReceptionWorkbenchController::class, 'getQueueReport'])->name('reports.queue');
    Route::get('/reports/visits', [ReceptionWorkbenchController::class, 'getVisitsReport'])->name('reports.visits');
    Route::get('/reports/chart-data', [ReceptionWorkbenchController::class, 'getChartData'])->name('reports.chart-data');

    // Service Requests
    Route::get('/patient/{id}/recent-requests', [ReceptionWorkbenchController::class, 'getRecentRequests'])->name('patient.recent-requests');
    Route::get('/patient/{id}/service-requests', [ReceptionWorkbenchController::class, 'getServiceRequests'])->name('patient.service-requests');
    Route::get('/patient/{id}/service-requests-stats', [ReceptionWorkbenchController::class, 'getServiceRequestsStats'])->name('patient.service-requests-stats');

    // Request Details
    Route::get('/request/{type}/{id}/details', [ReceptionWorkbenchController::class, 'getRequestDetails'])->name('request.details');

    // Discard Service Request
    Route::delete('/request/{type}/{id}/discard', [ReceptionWorkbenchController::class, 'discardServiceRequest'])->name('request.discard');
});
