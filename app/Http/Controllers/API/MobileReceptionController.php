<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BillingWorkbenchController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReceptionWorkbenchController;
use Illuminate\Http\Request;

/**
 * Native client adapter for the Health Records (reception) workspace.
 *
 * Only adapts: it exposes what the web workbench receives through its Blade view
 * (billing permission, registration fees) and gates payment exactly as the web page does.
 * All work is done by ReceptionWorkbenchController and BillingWorkbenchController.
 */
class MobileReceptionController extends Controller
{
    /**
     * GET /api/mobile/reception/context
     * The values the web workbench page is rendered with.
     */
    public function context(Request $request)
    {
        return response()->json([
            'success' => true,
            'can_bill' => ReceptionWorkbenchController::userCanBill($request->user()),
            'registration_services' => ReceptionWorkbenchController::registrationServicesQuery()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->service_name,
                    'price' => (float) ($s->price->sale_price ?? 0),
                ])->values(),
            'departments' => ReceptionWorkbenchController::activeDepartmentsQuery()
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
        ]);
    }

    /**
     * POST /api/mobile/reception/process-payment
     * The web workbench only offers checkout to users who can bill; enforce the same rule here,
     * then hand over to the billing workbench's processPayment (observers, GL and receipts run there).
     */
    public function processPayment(Request $request)
    {
        if (!ReceptionWorkbenchController::userCanBill($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to take payments.',
            ], 403);
        }

        return app(BillingWorkbenchController::class)->processPayment($request);
    }
}
