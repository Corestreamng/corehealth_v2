<?php
use Illuminate\Support\Facades\Route;

Route::middleware([])->group(function () {
    // Medication Chart Routes
    Route::get('patients/{patient}/nurse-chart/medication', [\App\Http\Controllers\MedicationChartController::class, 'index'])->name('nurse.medication.index');
    Route::get('patients/{patient}/nurse-chart/medication/calendar/{medication}/{start_date?}', [\App\Http\Controllers\MedicationChartController::class, 'calendar'])->name('nurse.medication.calendar');
    Route::post('patients/nurse-chart/medication/schedule', [\App\Http\Controllers\MedicationChartController::class, 'storeTiming'])->name('nurse.medication.schedule');
    Route::post('patients/nurse-chart/medication/administer', [\App\Http\Controllers\MedicationChartController::class, 'administer'])->name('nurse.medication.administer');
    Route::post('patients/nurse-chart/medication/discontinue', [\App\Http\Controllers\MedicationChartController::class, 'discontinue'])->name('nurse.medication.discontinue');
    Route::post('patients/nurse-chart/medication/resume', [\App\Http\Controllers\MedicationChartController::class, 'resume'])->name('nurse.medication.resume');
    Route::post('patients/nurse-chart/medication/edit', [\App\Http\Controllers\MedicationChartController::class, 'editAdministration'])->name('nurse.medication.edit');
    Route::post('patients/nurse-chart/medication/delete', [\App\Http\Controllers\MedicationChartController::class, 'deleteAdministration'])->name('nurse.medication.delete');

    // Intake & Output Chart Routes
    Route::get('patients/{patient}/nurse-chart/intake-output', [\App\Http\Controllers\IntakeOutputChartController::class, 'index'])->name('nurse.intake_output.index');
    Route::get('patients/{patient}/nurse-chart/intake-output/logs/{period}', [\App\Http\Controllers\IntakeOutputChartController::class, 'periodLogs'])->name('nurse.intake_output.logs');
    Route::post('patients/nurse-chart/intake-output/start', [\App\Http\Controllers\IntakeOutputChartController::class, 'startPeriod'])->name('nurse.intake_output.start');
    Route::post('patients/nurse-chart/intake-output/end', [\App\Http\Controllers\IntakeOutputChartController::class, 'endPeriod'])->name('nurse.intake_output.end');
    Route::post('patients/nurse-chart/intake-output/record', [\App\Http\Controllers\IntakeOutputChartController::class, 'storeRecord'])->name('nurse.intake_output.record');
});
