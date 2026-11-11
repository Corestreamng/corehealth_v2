<?php

namespace App\Interoperability\Mutators\Fhir;

use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Models\InteropTerminologyMap;
use App\Models\Encounter;
use App\Models\Product;
use Illuminate\Http\Request;

class InboundFhirPrescriptionMutator
{
    /**
     * Mutate an incoming FHIR R4 MedicationRequest into an Illuminate Request
     * and target Encounter model for EncounterController@addSinglePrescriptionRequest.
     */
    public static function mutate(array $payload, ?Request $originalRequest = null): array
    {
        // 1. Resolve Patient ID
        $patientId = null;
        if (!empty($payload['subject']['reference'])) {
            $parts = explode('/', $payload['subject']['reference']);
            $patientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
        }

        // 2. Resolve Encounter
        $encounter = null;
        if (!empty($payload['encounter']['reference'])) {
            $parts = explode('/', $payload['encounter']['reference']);
            $encounterId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Encounter', end($parts));
            if ($encounterId) {
                $encounter = Encounter::find($encounterId);
            }
        }

        // If no encounter specified, find the latest active or open encounter for this patient
        if (!$encounter && $patientId) {
            $encounter = Encounter::where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (!$encounter && $patientId) {
            // Create a consultation encounter if none exists
            $encounter = new Encounter();
            $encounter->patient_id = $patientId;
            $encounter->doctor_id = auth()->id() ?? 1;
            $encounter->started_at = now();
            $encounter->save();
        }

        // 3. Resolve Product ID
        $productId = null;
        $codings = $payload['medicationCodeableConcept']['coding'] ?? [];
        foreach ($codings as $coding) {
            // Check direct product ID
            if (is_numeric($coding['code'] ?? null) && str_contains($coding['system'] ?? '', 'product')) {
                $productId = (int) $coding['code'];

                break;
            }

            // Check WHO ATC mapping
            if (($coding['system'] ?? '') === 'http://www.whocc.no/atc') {
                $map = InteropTerminologyMap::where('standard', 'ATC')
                    ->where('standard_code', $coding['code'])
                    ->first();
                if ($map && $map->local_entity_id) {
                    $productId = $map->local_entity_id;

                    break;
                }
            }
        }

        // Fallback: match by drug name
        if (!$productId && !empty($payload['medicationCodeableConcept']['text'])) {
            $text = $payload['medicationCodeableConcept']['text'];
            $matchedProduct = Product::where('product_name', 'LIKE', "%{$text}%")->first();
            if ($matchedProduct) {
                $productId = $matchedProduct->id;
            }
        }

        // 4. Resolve Dose
        $dose = '1 stat';
        if (!empty($payload['dosageInstruction']) && is_array($payload['dosageInstruction'])) {
            $dose = $payload['dosageInstruction'][0]['text'] ?? $dose;
        }

        $localParams = [
            'product_id' => (string) ($productId ?? 1),
            'dose' => $dose,
            'patient_id' => $patientId,
        ];

        $mutatedRequest = new Request();
        $mutatedRequest->replace($localParams);

        if ($originalRequest) {
            $mutatedRequest->headers->replace($originalRequest->headers->all());
            $mutatedRequest->server->set('REMOTE_ADDR', $originalRequest->ip());
        }

        return [
            'request' => $mutatedRequest,
            'encounter' => $encounter,
        ];
    }
}
