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
| Middleware: auth (inherited from web.php group)
*/

// ─── Doctor: Referrals from encounters ─────────────────────────────────
Route::prefix('encounters/{encounter}')->group(function () {
    Route::post('/referrals', [SpecialistReferralController::class, 'createReferral'])->name('encounters.referrals.create');
    Route::get('/referrals', [SpecialistReferralController::class, 'getEncounterReferrals'])->name('encounters.referrals.list');
});

// ─── Reception: Referral Management ────────────────────────────────────
Route::prefix('referrals')->name('referrals.')->group(function () {
    Route::get('/pending', [SpecialistReferralController::class, 'getPendingReferrals'])->name('pending');
    Route::get('/pending-count', [SpecialistReferralController::class, 'getPendingReferralCount'])->name('pending-count');
    Route::post('/{referral}/book', [SpecialistReferralController::class, 'bookReferralAppointment'])->name('book');
    Route::post('/{referral}/refer-out', [SpecialistReferralController::class, 'referOut'])->name('refer-out');
    Route::post('/{referral}/cancel', [SpecialistReferralController::class, 'cancelReferral'])->name('cancel');
    Route::post('/{referral}/decline', [SpecialistReferralController::class, 'declineReferral'])->name('decline');
});

// ─── Patient Referral History ──────────────────────────────────────────
Route::get('/patients/{patient}/referral-history', [SpecialistReferralController::class, 'patientReferralHistory'])->name('patients.referral-history');
