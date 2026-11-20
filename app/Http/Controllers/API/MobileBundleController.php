<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use Illuminate\Http\Request;

/**
 * Native client adapter for service combos (bundles) a patient has had applied.
 *
 * Only adapts: the web builds this list into its history tables as buttons carrying the bundle's items
 * (view / remove modals). This returns the same parents and children as JSON. Removing a bundle or one
 * of its items goes to EncounterController (service-combo/remove-bundle, service-combo/remove-item).
 */
class MobileBundleController extends Controller
{
    /** GET /api/mobile/doctor/service-combo/bundles?patient_id= */
    public function forPatient(Request $request)
    {
        $request->validate(['patient_id' => 'required|integer|exists:patients,id']);
        $patient = Patient::findOrFail($request->patient_id);

        $parents = ProductOrServiceRequest::where('user_id', $patient->user_id)
            ->whereNull('parent_id')
            ->whereHas('service', fn ($q) => $q->where('is_combo', 1))
            ->with('service:id,service_name')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $children = ProductOrServiceRequest::whereIn('parent_id', $parents->pluck('id'))
            ->with(['service:id,service_name', 'product:id,product_name'])
            ->get()
            ->groupBy('parent_id');

        return response()->json([
            'success' => true,
            'bundles' => $parents->map(fn ($p) => [
                'id' => $p->id,
                'name' => optional($p->service)->service_name,
                'payable_amount' => $p->payable_amount,
                'claims_amount' => $p->claims_amount,
                'paid' => $p->payment_id !== null,
                'created_at' => optional($p->created_at)->format('d M Y H:i'),
                'items' => ($children[$p->id] ?? collect())->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => optional($c->service)->service_name ?? optional($c->product)->product_name ?? 'Item',
                    'type' => $c->service_id ? 'service' : 'product',
                    'qty' => $c->qty,
                    'removed' => $c->removed_at !== null,
                ])->values(),
            ])->values(),
        ]);
    }
}
