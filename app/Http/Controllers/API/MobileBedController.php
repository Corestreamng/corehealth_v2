<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BedController;
use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Native client adapter for the web "Manage Beds" page (BedController).
 *
 * Only adapts: the web list is a DataTable and its saves answer with redirects, which a native client
 * cannot use. This reads the same rows and hands saves to BedController, turning its redirect into JSON.
 */
class MobileBedController extends Controller
{
    /** GET /api/mobile/nursing/beds: the rows of the web bed list, plus the wards a bed can belong to. */
    public function index()
    {
        $rows = app(BedController::class)->listBeds()->getData(true)['data'] ?? [];

        return response()->json([
            'success' => true,
            'data' => collect($rows)->map(fn ($b) => [
                'id' => $b['id'],
                'name' => $b['name'],
                'ward_id' => $b['ward_id'] ?? null,
                'ward_name' => $b['ward_name'] ?? null,
                'price' => $b['price'] ?? null,
                'bed_status' => $b['bed_status'] ?? 'available',
                'unit' => $b['unit'] ?? null,
            ])->values(),
            'wards' => Ward::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** POST /api/mobile/nursing/beds */
    public function store(Request $request)
    {
        return $this->asJson(app(BedController::class)->store($request));
    }

    /** PUT /api/mobile/nursing/beds/{bed} */
    public function update(Request $request, Bed $bed)
    {
        return $this->asJson(app(BedController::class)->update($request, $bed));
    }

    /** The web controller answers with a redirect carrying a message or errors; say the same in JSON. */
    private function asJson($response)
    {
        if (!$response instanceof RedirectResponse) {
            return $response;
        }
        $session = $response->getSession();
        $errors = $session?->pull('errors');
        $error = $session?->pull('error');
        $message = $session?->pull('message');
        if ($errors || $error) {
            return response()->json(['success' => false, 'message' => $error ?: (is_array($errors) ? implode(' ', $errors) : (string) $errors)], 422);
        }

        return response()->json(['success' => true, 'message' => $message ?: 'Saved']);
    }
}
