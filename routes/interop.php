<?php

use App\Interoperability\Http\Controllers\Fhir\FhirCapabilityController;
use App\Interoperability\Http\Controllers\Fhir\FhirDiagnosticReportController;
use App\Interoperability\Http\Controllers\Fhir\FhirEncounterController;
use App\Interoperability\Http\Controllers\Fhir\FhirInvoiceController;
use App\Interoperability\Http\Controllers\Fhir\FhirMedicationRequestController;
use App\Interoperability\Http\Controllers\Fhir\FhirObservationController;
use App\Interoperability\Http\Controllers\Fhir\FhirPatientController;
use App\Interoperability\Http\Controllers\Fhir\FhirPractitionerController;
use App\Interoperability\Http\Controllers\Fhir\FhirServiceRequestController;
use App\Interoperability\Http\Controllers\OpenEhr\OpenEhrCompositionController;
use App\Interoperability\Http\Controllers\OpenEhr\OpenEhrEhrController;
use App\Interoperability\Http\Controllers\OpenEhr\OpenEhrQueryController;
use App\Interoperability\Http\Controllers\OpenEmr\OpenEmrCompatibilityController;
use App\Interoperability\Http\Controllers\PatientConsentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Interoperability Routes: HL7 FHIR R4, openEHR, OpenEMR & NDPA 2023
|--------------------------------------------------------------------------
|
| Isolated translation and compatibility layer.
| Inbound POST/PUT/PATCH requests mutate payloads to proxy existing hospital controllers.
|
*/

// NDPA 2023 Consent & Data Rights API
Route::prefix('ndpa/v1')->group(function () {
    Route::post('consent', [PatientConsentController::class, 'store'])->name('ndpa.consent.store');
    Route::get('consent/{patientId}', [PatientConsentController::class, 'show'])->name('ndpa.consent.show');
    Route::delete('consent/{id}', [PatientConsentController::class, 'revoke'])->name('ndpa.consent.revoke');
});

// HL7 FHIR R4 REST API (Levels 1–4)
Route::prefix('fhir/r4')->group(function () {
    Route::get('metadata', [FhirCapabilityController::class, 'show'])->name('fhir.metadata');

    Route::get('Patient', [FhirPatientController::class, 'index'])->name('fhir.patient.index');
    Route::get('Patient/{id}', [FhirPatientController::class, 'show'])->name('fhir.patient.read');
    Route::post('Patient', [FhirPatientController::class, 'store'])->name('fhir.patient.create');

    Route::get('Practitioner', [FhirPractitionerController::class, 'index'])->name('fhir.practitioner.index');
    Route::get('Practitioner/{id}', [FhirPractitionerController::class, 'show'])->name('fhir.practitioner.read');

    Route::get('Observation', [FhirObservationController::class, 'index'])->name('fhir.observation.index');
    Route::get('Observation/{id}', [FhirObservationController::class, 'show'])->name('fhir.observation.read');
    Route::post('Observation', [FhirObservationController::class, 'store'])->name('fhir.observation.create');

    Route::get('Encounter', [FhirEncounterController::class, 'index'])->name('fhir.encounter.index');
    Route::get('Encounter/{id}', [FhirEncounterController::class, 'show'])->name('fhir.encounter.read');
    Route::post('Encounter', [FhirEncounterController::class, 'store'])->name('fhir.encounter.create');

    Route::get('MedicationRequest', [FhirMedicationRequestController::class, 'index'])->name('fhir.medication-request.index');
    Route::get('MedicationRequest/{id}', [FhirMedicationRequestController::class, 'show'])->name('fhir.medication-request.read');
    Route::post('MedicationRequest', [FhirMedicationRequestController::class, 'store'])->name('fhir.medication-request.create');

    Route::get('ServiceRequest', [FhirServiceRequestController::class, 'index'])->name('fhir.service-request.index');
    Route::get('ServiceRequest/{id}', [FhirServiceRequestController::class, 'show'])->name('fhir.service-request.read');
    Route::post('ServiceRequest', [FhirServiceRequestController::class, 'store'])->name('fhir.service-request.create');

    Route::get('DiagnosticReport', [FhirDiagnosticReportController::class, 'index'])->name('fhir.diagnostic-report.index');
    Route::get('DiagnosticReport/{id}', [FhirDiagnosticReportController::class, 'show'])->name('fhir.diagnostic-report.read');
    Route::put('DiagnosticReport/{id}', [FhirDiagnosticReportController::class, 'update'])->name('fhir.diagnostic-report.update');

    Route::get('Invoice', [FhirInvoiceController::class, 'index'])->name('fhir.invoice.index');
    Route::get('Invoice/{id}', [FhirInvoiceController::class, 'show'])->name('fhir.invoice.read');
    Route::post('PaymentReconciliation', [FhirInvoiceController::class, 'reconcilePayment'])->name('fhir.payment-reconciliation.create');
});

// openEHR ITS-REST API (Archetypes & Compositions)
Route::prefix('openehr/v1')->group(function () {
    Route::get('ehr', [OpenEhrEhrController::class, 'findBySubject'])->name('openehr.ehr.search');
    Route::post('ehr', [OpenEhrEhrController::class, 'store'])->name('openehr.ehr.create');
    Route::get('ehr/{ehr_id}', [OpenEhrEhrController::class, 'show'])->name('openehr.ehr.read');

    Route::get('ehr/{ehr_id}/composition/{versioned_object_uid}', [OpenEhrCompositionController::class, 'show'])->name('openehr.composition.read');
    Route::post('ehr/{ehr_id}/composition', [OpenEhrCompositionController::class, 'store'])->name('openehr.composition.create');

    Route::post('query/aql', [OpenEhrQueryController::class, 'execute'])->name('openehr.query.aql');
});

// OpenEMR REST Compatibility & C-CDA XML
Route::prefix('apis/default')->group(function () {
    Route::get('api/patient', [OpenEmrCompatibilityController::class, 'patient'])->name('openemr.patient.index');
    Route::get('api/encounter', [OpenEmrCompatibilityController::class, 'encounter'])->name('openemr.encounter.index');
    Route::get('api/vital_signs', [OpenEmrCompatibilityController::class, 'vitals'])->name('openemr.vitals.index');
    Route::get('api/ccda/{patientId}', [OpenEmrCompatibilityController::class, 'ccda'])->name('openemr.ccda.export');

    Route::get('fhir/Patient', [FhirPatientController::class, 'index'])->name('openemr.fhir.patient.index');
    Route::get('fhir/Patient/{id}', [FhirPatientController::class, 'show'])->name('openemr.fhir.patient.read');
    Route::get('fhir/Observation', [FhirObservationController::class, 'index'])->name('openemr.fhir.observation.index');
    Route::get('fhir/Encounter', [FhirEncounterController::class, 'index'])->name('openemr.fhir.encounter.index');
});
