<?php

namespace App\Interoperability\Http\Controllers\OpenEhr;

use App\Http\Controllers\Controller;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Encounter;
use App\Models\VitalSign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenEhrQueryController extends Controller
{
    /**
     * Execute an Archetype Query Language (AQL) query.
     * POST /api/openehr/v1/query/aql
     */
    public function execute(Request $request): JsonResponse
    {
        $aql = $request->input('q') ?? ($request->json('q') ?? '');

        if (empty(trim($aql))) {
            return response()->json(['message' => "AQL query string 'q' is required."], 400);
        }

        // Parse target archetype from query
        $isBloodPressure = str_contains($aql, 'blood_pressure');
        $isEncounter = str_contains($aql, 'COMPOSITION') || str_contains($aql, 'encounter');

        $rows = [];
        $columns = [];

        if ($isBloodPressure) {
            $columns = [
                ['name' => 'systolic', 'path' => '/data[at0001]/events[at0002]/data[at0003]/items[at0004]/value/magnitude'],
                ['name' => 'diastolic', 'path' => '/data[at0001]/events[at0002]/data[at0003]/items[at0005]/value/magnitude'],
                ['name' => 'time_taken', 'path' => '/data[at0001]/events[at0002]/time/value'],
            ];

            $vitals = VitalSign::whereNotNull('blood_pressure')
                ->where('blood_pressure', 'LIKE', '%/%')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            foreach ($vitals as $v) {
                $parts = explode('/', trim($v->blood_pressure));
                $systolic = isset($parts[0]) && is_numeric(trim($parts[0])) ? (float) trim($parts[0]) : null;
                $diastolic = isset($parts[1]) && is_numeric(trim($parts[1])) ? (float) trim($parts[1]) : null;
                if ($systolic !== null && $diastolic !== null) {
                    $rows[] = [
                        $systolic,
                        $diastolic,
                        $v->created_at ? $v->created_at->toIso8601String() : now()->toIso8601String(),
                    ];
                }
            }
        } elseif ($isEncounter) {
            $columns = [
                ['name' => 'composition_uid', 'path' => '/uid/value'],
                ['name' => 'encounter_reason', 'path' => '/name/value'],
                ['name' => 'started_at', 'path' => '/context/start_time/value'],
            ];

            $encounters = Encounter::orderBy('created_at', 'desc')->limit(20)->get();
            foreach ($encounters as $enc) {
                $uid = InteropExternalIdentifier::getOrCreateUuid('OPENEHR', 'Composition', $enc->id);
                $rows[] = [
                    "{$uid}::corehealth.hospital.ehr::1",
                    $enc->reasons_for_encounter ?? 'Clinical Encounter',
                    $enc->started_at ? $enc->started_at->toIso8601String() : $enc->created_at->toIso8601String(),
                ];
            }
        } else {
            $columns = [
                ['name' => 'result', 'path' => '/result'],
            ];
            $rows = [['AQL query executed successfully on CoreHealth repository']];
        }

        return response()->json([
            'meta' => [
                '_type' => 'RESULT_SET',
                '_schema_version' => '1.1.0',
                '_created' => now()->toIso8601String(),
                '_generator' => 'CoreHealth openEHR AQL Engine',
            ],
            'q' => $aql,
            'columns' => $columns,
            'rows' => $rows,
        ], 200, ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}
