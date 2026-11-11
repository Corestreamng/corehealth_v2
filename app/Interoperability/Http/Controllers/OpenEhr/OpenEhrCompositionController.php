<?php

namespace App\Interoperability\Http\Controllers\OpenEhr;

use App\Http\Controllers\Controller;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Mutators\OpenEhr\InboundOpenEhrCompositionMutator;
use App\Interoperability\Transformers\OpenEhr\EncounterCompositionTransformer;
use App\Models\Encounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenEhrCompositionController extends Controller
{
    /**
     * Retrieve an openEHR COMPOSITION.
     * GET /api/openehr/v1/ehr/{ehr_id}/composition/{versioned_object_uid}
     */
    public function show(string $ehrId, string $versionedUid): JsonResponse
    {
        $parts = explode('::', $versionedUid);
        $compositionUuid = $parts[0];

        $encounterId = InteropExternalIdentifier::resolveLocalId('OPENEHR', 'Composition', $compositionUuid);

        if (!$encounterId) {
            return response()->json(['message' => "Composition '{$versionedUid}' not found."], 404);
        }

        $encounter = Encounter::find($encounterId);
        if (!$encounter) {
            return response()->json(['message' => 'Encounter record not found.'], 404);
        }

        $composition = EncounterCompositionTransformer::toComposition($encounter);

        return response()->json($composition, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'ETag' => '"' . md5(json_encode($composition)) . '"',
        ]);
    }

    /**
     * Inbound Create openEHR COMPOSITION.
     * POST /api/openehr/v1/ehr/{ehr_id}/composition
     */
    public function store(Request $request, string $ehrId): JsonResponse
    {
        $payload = $request->json()->all();

        $encounter = InboundOpenEhrCompositionMutator::mutateAndPersist($payload, $ehrId);
        $composition = EncounterCompositionTransformer::toComposition($encounter);

        $uid = $composition['uid']['value'];

        return response()->json($composition, 201, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Location' => InteropRouteHelper::openEhrRoute("ehr/{$ehrId}/composition/{$uid}"),
            'ETag' => '"' . md5(json_encode($composition)) . '"',
        ]);
    }
}
