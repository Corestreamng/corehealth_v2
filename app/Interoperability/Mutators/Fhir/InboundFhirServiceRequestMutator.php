<?php

namespace App\Interoperability\Mutators\Fhir;

use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Models\InteropTerminologyMap;
use App\Models\Encounter;
use App\Models\Service;
use Illuminate\Http\Request;

class InboundFhirServiceRequestMutator
{
    /**
     * Mutate an incoming FHIR R4 ServiceRequest into an Illuminate Request
     * and target Encounter model for EncounterController@addSingleLabRequest.
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

        if (!$encounter && $patientId) {
            $encounter = Encounter::where('patient_id', $patientId)->orderBy('created_at', 'desc')->first();
        }

        if (!$encounter && $patientId) {
            $encounter = new Encounter();
            $encounter->patient_id = $patientId;
            $encounter->doctor_id = auth()->id() ?? 1;
            $encounter->started_at = now();
            $encounter->save();
        }

        // 3. Resolve Service ID
        $serviceId = null;
        $codings = $payload['code']['coding'] ?? [];
        foreach ($codings as $coding) {
            if (is_numeric($coding['code'] ?? null) && str_contains($coding['system'] ?? '', 'service')) {
                $serviceId = (int) $coding['code'];

                break;
            }

            if (($coding['system'] ?? '') === 'http://loinc.org') {
                $map = InteropTerminologyMap::where('standard', 'LOINC')
                    ->where('standard_code', $coding['code'])
                    ->first();
                if ($map && $map->local_entity_id) {
                    $serviceId = $map->local_entity_id;

                    break;
                }
            }
        }

        // Fallback: match by service name
        if (!$serviceId && !empty($payload['code']['text'])) {
            $text = $payload['code']['text'];
            $matched = Service::where('service_name', 'LIKE', "%{$text}%")->first();
            if ($matched) {
                $serviceId = $matched->id;
            }
        }

        // 4. Resolve Note
        $note = 'FHIR Service Request';
        if (!empty($payload['note']) && is_array($payload['note'])) {
            $note = $payload['note'][0]['text'] ?? $note;
        }

        $localParams = [
            'service_id' => (string) ($serviceId ?? 1),
            'note' => $note,
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
