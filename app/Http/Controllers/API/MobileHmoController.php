<?php

namespace App\Http\Controllers\API;

use App\Helpers\HmoHelper;
use App\Http\Controllers\HmoWorkbenchController;
use App\Models\Hmo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * HMO Workbench for the staff app. It extends the web controller, so every rule (which requests belong to a queue,
 * filters, search, who may reverse an approval) is the web's own; this class only answers as JSON what the web page
 * renders as DataTables HTML, one server page at a time.
 */
class MobileHmoController extends HmoWorkbenchController
{
    private function nameOf(?User $u): string
    {
        return $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : '';
    }

    /** What the workbench filters offer: HMOs, rejection reasons, who has validated before. */
    public function context(Request $request)
    {
        return response()->json([
            'hmos' => Hmo::with('scheme')->where('status', 1)->orderBy('name')->get()->map(fn ($h) => ['id' => $h->id, 'name' => $h->name, 'scheme' => $h->scheme?->name]),
            'rejection_reasons' => self::REJECTION_REASONS,
            'validators' => User::whereIn('id', fn ($q) => $q->select('validated_by')->from('product_or_service_requests')->whereNotNull('validated_by')->distinct())
                ->orderBy('firstname')->orderBy('surname')->get()->map(fn ($u) => ['id' => $u->id, 'name' => $this->nameOf($u)]),
            'can' => ['manage' => $request->user()->hasAnyRole(['SUPERADMIN', 'ADMIN'])],
        ]);
    }

    /** The queue as plain rows: the same query as the web table, paged by the server. */
    public function requests(Request $request)
    {
        $per = max(1, min(100, (int) $request->input('per_page', 25)));
        $page = max(1, (int) $request->input('page', 1));
        $query = $this->requestsQuery($request)->orderBy('product_or_service_requests.created_at', 'DESC');
        $total = (clone $query)->count();
        $emergency = $this->emergencyPatientIds();

        $rows = $query->forPage($page, $per)->get()->map(function ($r) use ($emergency) {
            $profile = $r->user?->patient_profile;
            $procedure = $r->procedure;
            $type = $procedure ? 'Procedure' : ($r->product_id ? 'Product' : ($r->service_id ? 'Service' : 'Item'));
            $name = $procedure ? ($procedure->procedureDefinition->name ?? 'Procedure') : HmoHelper::getDisplayName($r);
            $original = null;
            if ($r->product_id && $r->product?->price) {
                $original = (float) $r->product->price->current_sale_price;
            } elseif ($r->service_id && $r->service?->price) {
                $original = (float) $r->service->price->sale_price;
            }
            $delivery = in_array($r->validation_status, ['approved', 'rejected'], true) ? $this->checkServiceDeliveryStatus($r) : ['can_reverse' => false, 'reason' => null];

            return [
                'id' => $r->id,
                'patient' => $profile ? ['id' => $profile->id, 'name' => $this->nameOf($r->user), 'file_no' => $profile->file_no, 'hmo_no' => $profile->hmo_no, 'hmo' => $profile->hmo?->name, 'emergency' => $emergency->contains($profile->id)] : null,
                'requested_by' => $r->staff ? $this->nameOf($r->staff) : 'System',
                'created_at' => optional($r->created_at)->toIso8601String(),
                'waiting_hours' => $r->validation_status === 'pending' && $r->created_at ? $r->created_at->diffInHours(now()) : null,
                'item' => ['type' => $type, 'name' => $name, 'qty' => $r->qty, 'procedure_id' => $procedure?->id],
                'original_price' => $original,
                'claims_amount' => (float) $r->claims_amount,
                'payable_amount' => (float) $r->payable_amount,
                'coverage_mode' => $r->coverage_mode,
                'paid' => (bool) $r->payment_id,
                'validation_status' => $r->validation_status,
                'validated_by' => $r->validator ? $this->nameOf($r->validator) : null,
                'validated_at' => $r->validated_at ? Carbon::parse($r->validated_at)->toIso8601String() : null,
                'auth_code' => $r->auth_code ?? null,
                'reception_validated' => (bool) $r->reception_validated,
                'can_reverse' => $delivery['can_reverse'],
                'cannot_reverse_reason' => $delivery['reason'],
            ];
        })->values();

        return response()->json(['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per]);
    }

    /** Products or services to price (tariff and override forms): a short, searchable list instead of the whole catalogue. */
    public function searchItems(Request $request)
    {
        $term = trim((string) $request->input('term', $request->input('q', '')));
        $like = '%' . addcslashes($term, '%_\\') . '%';
        if ($request->input('type') === 'product') {
            $rows = \App\Models\Product::where('status', 1)->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('product_name', 'LIKE', $like)->orWhere('product_code', 'LIKE', $like)))->orderBy('product_name')->limit(25)->get(['id', 'product_name', 'product_code']);

            return response()->json($rows->map(fn ($p) => ['id' => $p->id, 'name' => $p->product_name, 'code' => $p->product_code]));
        }
        $rows = \App\Models\Service::where('status', 1)->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('service_name', 'LIKE', $like)->orWhere('service_code', 'LIKE', $like)))->orderBy('service_name')->limit(25)->get(['id', 'service_name', 'service_code']);

        return response()->json($rows->map(fn ($p) => ['id' => $p->id, 'name' => $p->service_name, 'code' => $p->service_code]));
    }
}
