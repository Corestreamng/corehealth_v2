<?php

namespace App\Interoperability\Mutators\Fhir;

use App\Interoperability\Models\InteropExternalIdentifier;
use Illuminate\Http\Request;

class InboundFhirObservationMutator
{
    /**
     * Mutate an incoming FHIR R4 Observation JSON payload into an Illuminate Request
     * for VitalSignController@store.
     */
    public static function mutateToStoreRequest(array $payload, ?Request $originalRequest = null): Request
    {
        // 1. Resolve Patient ID
        $patientId = null;
        if (!empty($payload['subject']['reference'])) {
            $ref = $payload['subject']['reference'];
            $parts = explode('/', $ref);
            $rawId = end($parts);
            $patientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', $rawId);
        }

        $systolic = null;
        $diastolic = null;
        $temp = null;
        $heartRate = null;
        $respRate = null;
        $weight = null;
        $height = null;
        $spo2 = null;
        $bloodSugar = null;
        $painScore = null;
        $bmi = null;

        // Parse components
        if (!empty($payload['component']) && is_array($payload['component'])) {
            foreach ($payload['component'] as $comp) {
                $code = $comp['code']['coding'][0]['code'] ?? null;
                $val = $comp['valueQuantity']['value'] ?? ($comp['valueInteger'] ?? null);

                if ($code === '8480-6') {
                    $systolic = $val;
                } elseif ($code === '8462-4') {
                    $diastolic = $val;
                } elseif ($code === '8310-5') {
                    $temp = $val;
                } elseif ($code === '8867-4') {
                    $heartRate = $val;
                } elseif ($code === '9279-1') {
                    $respRate = $val;
                } elseif ($code === '29463-7') {
                    $weight = $val;
                } elseif ($code === '8302-2') {
                    $height = $val;
                } elseif ($code === '59408-5') {
                    $spo2 = $val;
                } elseif ($code === '2339-0' || $code === '1558-6') {
                    $bloodSugar = $val;
                } elseif ($code === '72514-3') {
                    $painScore = $val;
                } elseif ($code === '39156-5') {
                    $bmi = $val;
                }
            }
        }

        // Direct single value observation fallback
        if (empty($payload['component']) && !empty($payload['valueQuantity']['value'])) {
            $code = $payload['code']['coding'][0]['code'] ?? null;
            $val = $payload['valueQuantity']['value'];
            if ($code === '8310-5') {
                $temp = $val;
            } elseif ($code === '8867-4') {
                $heartRate = $val;
            } elseif ($code === '9279-1') {
                $respRate = $val;
            } elseif ($code === '29463-7') {
                $weight = $val;
            } elseif ($code === '8302-2') {
                $height = $val;
            } elseif ($code === '59408-5') {
                $spo2 = $val;
            } elseif ($code === '2339-0') {
                $bloodSugar = $val;
            }
        }

        $bloodPressure = ($systolic !== null && $diastolic !== null) ? "{$systolic}/{$diastolic}" : null;

        // Notes
        $notes = null;
        if (!empty($payload['note']) && is_array($payload['note'])) {
            $notes = $payload['note'][0]['text'] ?? null;
        }

        $localParams = [
            'patient_id' => $patientId,
            'bloodPressure' => $bloodPressure,
            'bodyTemperature' => $temp,
            'heartRate' => $heartRate,
            'respiratoryRate' => $respRate,
            'bodyWeight' => $weight,
            'height' => $height,
            'spo2' => $spo2,
            'bloodSugar' => $bloodSugar,
            'painScore' => $painScore,
            'bmi' => $bmi,
            'otherNotes' => $notes ?? 'Imported via FHIR R4 Observation API',
            'datetimeField' => $payload['effectiveDateTime'] ?? now()->toDateTimeString(),
        ];

        $mutatedRequest = new Request();
        $mutatedRequest->replace(array_filter($localParams, fn ($v) => !is_null($v)));

        if ($originalRequest) {
            $mutatedRequest->headers->replace($originalRequest->headers->all());
            $mutatedRequest->server->set('REMOTE_ADDR', $originalRequest->ip());
        }

        return $mutatedRequest;
    }
}
