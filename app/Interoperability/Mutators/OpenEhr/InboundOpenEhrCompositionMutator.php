<?php

namespace App\Interoperability\Mutators\OpenEhr;

use App\Http\Controllers\VitalSignController;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Encounter;
use App\Models\Patient;
use Illuminate\Http\Request;

class InboundOpenEhrCompositionMutator
{
    /**
     * Mutate an incoming openEHR COMPOSITION into CoreHealth models,
     * proxying VitalSignController and EncounterController.
     */
    public static function mutateAndPersist(array $composition, string $ehrId): Encounter
    {
        $patientId = InteropExternalIdentifier::resolveLocalId('OPENEHR', 'Ehr', $ehrId);

        if (!$patientId) {
            $patient = Patient::first();
            $patientId = $patient ? $patient->id : 1;
        }

        // 1. Create or resolve Encounter
        $encounter = new Encounter();
        $encounter->patient_id = $patientId;
        $encounter->doctor_id = auth()->id() ?? 1;
        $encounter->started_at = now();
        $encounter->reasons_for_encounter = $composition['name']['value'] ?? 'openEHR Inbound Clinical Encounter';
        $encounter->save();

        if (!empty($composition['uid']['value'])) {
            $parts = explode('::', $composition['uid']['value']);
            InteropExternalIdentifier::linkExternalId('OPENEHR', 'Composition', $encounter->id, $parts[0]);
        }

        // 2. Extract Observations & Archetypes from content
        $systolic = null;
        $diastolic = null;
        $pulse = null;
        $temp = null;

        $content = $composition['content'] ?? [];
        foreach ($content as $item) {
            $nodeId = $item['archetype_node_id'] ?? '';

            // Blood pressure
            if (str_contains($nodeId, 'blood_pressure')) {
                $events = $item['data']['events'] ?? [];
                foreach ($events as $event) {
                    $items = $event['data']['items'] ?? [];
                    foreach ($items as $elem) {
                        $elemId = $elem['archetype_node_id'] ?? '';
                        if ($elemId === 'at0004') {
                            $systolic = $elem['value']['magnitude'] ?? null;
                        } elseif ($elemId === 'at0005') {
                            $diastolic = $elem['value']['magnitude'] ?? null;
                        }
                    }
                }
            }

            // Pulse
            if (str_contains($nodeId, 'pulse')) {
                $events = $item['data']['events'] ?? [];
                foreach ($events as $event) {
                    $items = $event['data']['items'] ?? [];
                    foreach ($items as $elem) {
                        if (($elem['archetype_node_id'] ?? '') === 'at0004') {
                            $pulse = $elem['value']['magnitude'] ?? null;
                        }
                    }
                }
            }

            // Temperature
            if (str_contains($nodeId, 'body_temperature')) {
                $events = $item['data']['events'] ?? [];
                foreach ($events as $event) {
                    $items = $event['data']['items'] ?? [];
                    foreach ($items as $elem) {
                        if (($elem['archetype_node_id'] ?? '') === 'at0004') {
                            $temp = $elem['value']['magnitude'] ?? null;
                        }
                    }
                }
            }
        }

        // If any vitals are extracted, proxy VitalSignController@store
        if ($systolic !== null || $pulse !== null || $temp !== null) {
            $vitalsParams = [
                'patient_id' => $patientId,
                'bloodPressure' => ($systolic && $diastolic) ? "{$systolic}/{$diastolic}" : null,
                'heartRate' => $pulse,
                'bodyTemperature' => $temp,
                'otherNotes' => 'Captured via openEHR COMPOSITION.encounter.v1',
            ];

            $vitalsReq = new Request();
            $vitalsReq->replace(array_filter($vitalsParams, fn ($v) => !is_null($v)));

            app(VitalSignController::class)->store($vitalsReq);
        }

        return $encounter;
    }
}
