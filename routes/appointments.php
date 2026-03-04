<?php

use App\Http\Controllers\DoctorAppointmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Appointment Routes
|--------------------------------------------------------------------------
| These routes handle all appointment scheduling, check-in, rescheduling,
| follow-up, reassignment, and availability slot queries.
|
| Prefix: /appointments
| Middleware: auth (inherited from web.php group)
*/

Route::prefix('appointments')->name('appointments.')->group(function () {

    // ─── Reception: Appointment CRUD & DataTable ───────────────────────
    Route::get('/list', [DoctorAppointmentController::class, 'getAppointments'])->name('list');
    Route::get('/calendar-events', [DoctorAppointmentController::class, 'getCalendarEvents'])->name('calendar-events');
    Route::get('/today-counts', [DoctorAppointmentController::class, 'getTodayAppointmentCounts'])->name('today-counts');
    Route::post('/create', [DoctorAppointmentController::class, 'createAppointment'])->name('create');
    Route::put('/{appointment}', [DoctorAppointmentController::class, 'updateAppointment'])->name('update');

    // ─── Check-In / Cancel / No-Show ───────────────────────────────────
    Route::post('/{appointment}/check-in', [DoctorAppointmentController::class, 'checkIn'])->name('check-in');
    Route::post('/{appointment}/cancel', [DoctorAppointmentController::class, 'cancel'])->name('cancel');
    Route::post('/{appointment}/no-show', [DoctorAppointmentController::class, 'markNoShow'])->name('no-show');

    // ─── Reschedule ────────────────────────────────────────────────────
    Route::post('/{appointment}/reschedule', [DoctorAppointmentController::class, 'reschedule'])->name('reschedule');

    // ─── Reassignment ──────────────────────────────────────────────────
    Route::post('/{appointment}/reassign', [DoctorAppointmentController::class, 'reassignDoctor'])->name('reassign');
    Route::get('/{appointment}/available-doctors', [DoctorAppointmentController::class, 'getAvailableDoctors'])->name('available-doctors');

    // ─── Chain / History ───────────────────────────────────────────────
    Route::get('/{appointment}/chain', [DoctorAppointmentController::class, 'getAppointmentChain'])->name('chain');

    // ─── Availability Slots ────────────────────────────────────────────
    Route::get('/available-slots', [DoctorAppointmentController::class, 'getAvailableSlots'])->name('available-slots');

    // ─── Doctor-Facing ─────────────────────────────────────────────────
    Route::get('/doctor/list', [DoctorAppointmentController::class, 'getDoctorAppointments'])->name('doctor.list');
    Route::get('/doctor/counts', [DoctorAppointmentController::class, 'getDoctorAppointmentCounts'])->name('doctor.counts');
    Route::get('/doctor/queue-counts', [DoctorAppointmentController::class, 'getDoctorQueueCounts'])->name('doctor.queue-counts');
});

// ─── Follow-Up (from encounter context) ────────────────────────────────
Route::post('/encounters/{encounter}/schedule-followup', [DoctorAppointmentController::class, 'scheduleFollowUp'])->name('encounters.schedule-followup');

// ─── Timer Endpoints ───────────────────────────────────────────────────
Route::prefix('queue')->name('queue.')->group(function () {
    Route::post('/{queue}/timer/start', [DoctorAppointmentController::class, 'startTimer'])->name('timer.start');
    Route::post('/{queue}/timer/pause', [DoctorAppointmentController::class, 'pauseTimer'])->name('timer.pause');
    Route::get('/{queue}/timer/status', [DoctorAppointmentController::class, 'getTimerStatus'])->name('timer.status');
});
