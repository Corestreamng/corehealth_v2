<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\BillingWorkbenchController;
use App\Http\Controllers\Controller;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Transformers\Fhir\InvoiceTransformer;
use App\Models\ProductOrServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirInvoiceController extends Controller
{
    /**
     * Search FHIR R4 Invoices.
     * GET /api/fhir/r4/Invoice
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductOrServiceRequest::with(['user.patient_profile', 'service', 'product']);

        if ($request->filled('patient')) {
            $parts = explode('/', $request->input('patient'));
            $localPatientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
            if ($localPatientId) {
                $query->whereHas('user.patient_profile', function ($q) use ($localPatientId) {
                    $q->where('id', $localPatientId);
                });
            }
        }

        $limit = min(50, (int) ($request->input('_count', 20)));
        $bills = $query->orderBy('created_at', 'desc')->paginate($limit);

        $entries = [];
        foreach ($bills as $bill) {
            $fhirInv = InvoiceTransformer::toFhir($bill);
            $entries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('Invoice', $fhirInv['id']),
                'resource' => $fhirInv,
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $bills->total(),
            'entry' => $entries,
        ];

        return response()->json($bundle, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Read a single FHIR R4 Invoice.
     * GET /api/fhir/r4/Invoice/{id}
     */
    public function show(string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Invoice', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Invoice '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $bill = ProductOrServiceRequest::with(['user.patient_profile', 'service', 'product'])->find($localId);

        if (!$bill) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Invoice not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        return response()->json(InvoiceTransformer::toFhir($bill), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Inbound FHIR PaymentReconciliation: Mutates payment payload to BillingWorkbenchController@processPayment
     * triggering PaymentObserver (Double-Entry General Ledger journal entries).
     * POST /api/fhir/r4/PaymentReconciliation
     */
    public function reconcilePayment(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        // Resolve Patient
        $patientId = null;
        if (!empty($payload['paymentIssuer']['reference'])) {
            $parts = explode('/', $payload['paymentIssuer']['reference']);
            $patientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
        }

        // Resolve Billing Line Item IDs
        $itemIds = [];
        if (!empty($payload['detail']) && is_array($payload['detail'])) {
            foreach ($payload['detail'] as $det) {
                if (!empty($det['request']['reference'])) {
                    $parts = explode('/', $det['request']['reference']);
                    $localBillId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Invoice', end($parts));
                    if ($localBillId) {
                        $itemIds[] = ['id' => $localBillId, 'qty' => 1];
                    }
                }
            }
        }

        if (empty($itemIds)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'required',
                        'diagnostics' => 'PaymentReconciliation requires valid invoice line item references in detail.request.',
                    ],
                ],
            ], 422, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $localParams = [
            'patient_id' => $patientId ?? 1,
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'items' => $itemIds,
        ];

        $mutatedRequest = new Request();
        $mutatedRequest->replace($localParams);

        $billingController = app(BillingWorkbenchController::class);
        $res = $billingController->processPayment($mutatedRequest);
        $responseData = json_decode($res->getContent(), true);

        return response()->json([
            'resourceType' => 'PaymentReconciliation',
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'active',
            'outcome' => 'complete',
            'disposition' => 'Payment processed and General Ledger balanced via BillingWorkbenchController proxy.',
            'corehealth_response' => $responseData,
        ], 200, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
    }
}
