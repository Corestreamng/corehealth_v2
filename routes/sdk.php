<?php

use App\Interoperability\Http\Controllers\Sdk\SdkAuthController;
use App\Interoperability\Http\Controllers\Sdk\SdkScribeSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CoreHealth AI Scribe Embeddable SDK Routes (/api/sdk/*)
|--------------------------------------------------------------------------
|
| Ephemeral JWT Token Exchange, Scribe Session Lifecycle,
| and Standards Handoff (openEHR Composition + HL7 FHIR R4 Bundle).
|
*/

Route::prefix('sdk')->group(function () {
    // 1. Auth Endpoint (Client Credentials Exchange -> Ephemeral SKT_ Token)
    Route::post('auth/token', [SdkAuthController::class, 'issue'])->name('sdk.auth.token');
    Route::post('auth/revoke', [SdkAuthController::class, 'revoke'])->name('sdk.auth.revoke');

    // 2. Scribe Sessions & Interop Handoff (Protected by interop.sdk middleware)
    Route::middleware(['interop.sdk'])->group(function () {
        Route::post('scribe/session', [SdkScribeSessionController::class, 'create'])->name('sdk.scribe.session.create');
        Route::get('scribe/session', [SdkScribeSessionController::class, 'status'])->name('sdk.scribe.session.status.direct');
        Route::get('scribe/session/{token?}', [SdkScribeSessionController::class, 'status'])->where('token', '.*')->name('sdk.scribe.session.status');
        Route::post('scribe/process', [SdkScribeSessionController::class, 'process'])->name('sdk.scribe.process');
        Route::post('scribe/convert-format', [SdkScribeSessionController::class, 'convertFormat'])->name('sdk.scribe.convert-format');
        Route::post('scribe/sign', [SdkScribeSessionController::class, 'sign'])->name('sdk.scribe.sign');
        Route::post('scribe/compositions', [SdkScribeSessionController::class, 'compositions'])->name('sdk.scribe.compositions.direct');
        Route::post('scribe/fhir-bundle', [SdkScribeSessionController::class, 'fhirBundle'])->name('sdk.scribe.fhir-bundle.direct');
        Route::post('scribe/session/{token?}/compositions', [SdkScribeSessionController::class, 'compositions'])->where('token', '.*')->name('sdk.scribe.compositions');
        Route::post('scribe/session/{token?}/fhir-bundle', [SdkScribeSessionController::class, 'fhirBundle'])->where('token', '.*')->name('sdk.scribe.fhir-bundle');

        // 3. Catalog & Categories Grounding
        Route::get('catalogs/categories', [SdkScribeSessionController::class, 'categories'])->name('sdk.catalogs.categories');
        Route::get('catalogs/items', [SdkScribeSessionController::class, 'catalogItems'])->name('sdk.catalogs.items');
    });

    // 3. Standalone iFrame Embed Route
    Route::get('embed', [SdkScribeSessionController::class, 'embed'])->name('sdk.embed');
});
