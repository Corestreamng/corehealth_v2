<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\Controller;
use App\Interoperability\Transformers\Fhir\CapabilityStatementTransformer;
use Illuminate\Http\JsonResponse;

class FhirCapabilityController extends Controller
{
    /**
     * Provide FHIR R4 CapabilityStatement metadata.
     * GET /api/fhir/r4/metadata
     */
    public function show(): JsonResponse
    {
        $capability = CapabilityStatementTransformer::build();

        return response()->json($capability, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }
}
