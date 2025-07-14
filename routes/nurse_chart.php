<?php
use Illuminate\Support\Facades\Route;

Route::middleware([])->group(function () {
    Route::get('patients/{patient}/nurse-chart/medication', [\App\Http\Controllers\MedicationChartController::class, 'index'])->name('nurse.medication.index');
    Route::post('patients/nurse-chart/medication/schedule', [\App\Http\Controllers\MedicationChartController::class, 'storeTiming'])->name('nurse.medication.schedule');
    Route::post('patients/nurse-chart/medication/administer', [\App\Http\Controllers\MedicationChartController::class, 'administer'])->name('nurse.medication.administer');

    Route::get('patients/{patient}/nurse-chart/intake-output', [\App\Http\Controllers\IntakeOutputChartController::class, 'index'])->name('nurse.intake_output.index');
    Route::post('patients/nurse-chart/intake-output/start', [\App\Http\Controllers\IntakeOutputChartController::class, 'startPeriod'])->name('nurse.intake_output.start');
    Route::post('patients/nurse-chart/intake-output/end', [\App\Http\Controllers\IntakeOutputChartController::class, 'endPeriod'])->name('nurse.intake_output.end');
    Route::post('patients/nurse-chart/intake-output/record', [\App\Http\Controllers\IntakeOutputChartController::class, 'storeRecord'])->name('nurse.intake_output.record');
});
