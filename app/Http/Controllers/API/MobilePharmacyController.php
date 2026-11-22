<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreContextRule;
use App\Services\StoreContextResolver;
use Illuminate\Http\Request;

/**
 * Staff app, Pharmacy workspace. Only what the web page renders with: everything else is routed
 * straight to the web controllers (see routes/api.php, `mobile/pharmacy`).
 */
class MobilePharmacyController extends Controller
{
    /** The pharmacy store in use and the stores this user may dispense from (web: store-context badge). */
    public function context(Request $request, StoreContextResolver $resolver)
    {
        $user = $request->user();
        $resolved = $resolver->resolve($user);
        $row = fn (Store $s) => ['id' => $s->id, 'name' => $s->store_name, 'role' => $s->distribution_role ?? null];

        return response()->json([
            'success' => true,
            'resolved_store' => $resolved ? $row($resolved) : null,
            'fallback_action' => $resolved ? null : StoreContextRule::fallbackAction(),
            'stores' => $resolver->candidateStores($user, 'pharmacy')->map($row)->values(),
            'can_change_store' => $user->can('store-context.change-manual'),
        ]);
    }
}
