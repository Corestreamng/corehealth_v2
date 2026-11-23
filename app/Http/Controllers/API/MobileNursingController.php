<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\NursingShift;
use App\Models\StoreContextRule;
use App\Services\StoreContextResolver;
use Illuminate\Http\Request;

/**
 * Native client adapter for the Nursing workspace.
 *
 * Only adapts: it returns the values the web Nursing workbench page is rendered with (its resolved
 * ward store, candidate stores and default shift type). Everything the nurse does goes through
 * NursingWorkbenchController and ShiftController.
 */
class MobileNursingController extends Controller
{
    /** GET /api/mobile/nursing/context */
    public function context(Request $request, StoreContextResolver $resolver)
    {
        $user = $request->user();
        $resolved = $resolver->resolve($user);

        return response()->json([
            'success' => true,
            'resolved_store' => $resolved ? ['id' => $resolved->id, 'name' => $resolved->store_name, 'ward_id' => $resolved->ward_id] : null,
            'fallback_action' => $resolved ? null : StoreContextRule::fallbackAction(),
            'stores' => $resolver->candidateStores($user, 'ward')
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->store_name, 'ward_id' => $s->ward_id])->values(),
            'default_shift_type' => NursingShift::determineShiftType(),
            // The service categories the web's search filters by (settings), so a lab or imaging search asks
            // the server for that category instead of filtering a mixed top-15 afterwards.
            'categories' => [
                'lab' => (int) appsettings('investigation_category_id', 2),
                'imaging' => (int) appsettings('imaging_category_id', 6),
                'procedure' => (int) appsettings('procedure_category_id', 8),
            ],
        ]);
    }
}
