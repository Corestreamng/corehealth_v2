<?php

use App\Http\Controllers\SpecialistReferralController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Referral Routes
|--------------------------------------------------------------------------
| Routes for the specialist referral system: creating referrals from
| encounters, managing pending referrals at reception, and viewing
| patient referral history.
|
| Middleware: auth
*/

Route::middleware(['auth'])->group(function () {

// ─── Doctor: Referrals from encounters ─────────────────────────────────
Route::prefix('encounters/{encounter}')->group(function () {
    Route::post('/referrals', [SpecialistReferralController::class, 'createReferral'])->name('encounters.referrals.create');
    Route::get('/referrals', [SpecialistReferralController::class, 'getEncounterReferrals'])->name('encounters.referrals.list');
    Route::get('/referrals/incoming', [SpecialistReferralController::class, 'getIncomingReferrals'])->name('encounters.referrals.incoming');
    Route::get('/referrals/patient-all', [SpecialistReferralController::class, 'getPatientReferrals'])->name('encounters.referrals.patient-all');
    Route::put('/referrals/{referral}', [SpecialistReferralController::class, 'updateReferral'])->name('encounters.referrals.update');
    Route::delete('/referrals/{referral}', [SpecialistReferralController::class, 'deleteReferral'])->name('encounters.referrals.delete');
});

// ─── Reception: Referral Management ────────────────────────────────────
Route::prefix('referrals')->name('referrals.')->group(function () {
    Route::get('/pending', [SpecialistReferralController::class, 'getPendingReferrals'])->name('pending');
    Route::get('/pending-count', [SpecialistReferralController::class, 'getPendingReferralCount'])->name('pending-count');
    Route::get('/doctor-list', [SpecialistReferralController::class, 'getDoctorReferralsList'])->name('doctor-list');
    Route::get('/all-list', [SpecialistReferralController::class, 'getAllReferralsList'])->name('all-list');
    Route::get('/{referral}/detail', [SpecialistReferralController::class, 'getReferralDetail'])->name('detail');
    Route::post('/{referral}/book', [SpecialistReferralController::class, 'bookReferralAppointment'])->name('book');
    Route::post('/{referral}/refer-out', [SpecialistReferralController::class, 'referOut'])->name('refer-out');
    Route::post('/{referral}/cancel', [SpecialistReferralController::class, 'cancelReferral'])->name('cancel');
    Route::post('/{referral}/decline', [SpecialistReferralController::class, 'declineReferral'])->name('decline');
    Route::post('/{referral}/accept', [SpecialistReferralController::class, 'acceptReferral'])->name('accept');
});

// ─── Patient Referral History ──────────────────────────────────────────
Route::get('/patients/{patient}/referral-history', [SpecialistReferralController::class, 'patientReferralHistory'])->name('patients.referral-history');

}); // end auth middleware
