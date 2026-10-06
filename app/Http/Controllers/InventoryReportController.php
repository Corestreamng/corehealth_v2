<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\InventoryReportService;
use App\Services\StoreContextResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryReportController extends Controller
{
    protected $reportService;

    public function __construct(InventoryReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    protected function resolveStoreIds(Request $request): array
    {
        if ($request->filled('store_id')) {
            $raw = is_array($request->store_id) ? $request->store_id : explode(',', (string)$request->store_id);
            $storeIds = array_values(array_filter(array_map('trim', $raw), function ($val) {
                return $val !== '' && is_numeric($val) && (int)$val > 0;
            }));
            if (!empty($storeIds)) {
                return array_map('intval', $storeIds);
            }
        }

        // Fallback: resolve from user's store context
        if (Auth::check()) {
            $resolver = app(StoreContextResolver::class);
            $resolved = $resolver->resolve(Auth::user());
            if ($resolved) {
                return [(int)$resolved->id];
            }
        }

        // Fallback to default pharmacy store (is_default=1 or store_type='pharmacy') or ID 2
        $fallback = Store::where('is_default', 1)->value('id')
            ?? Store::where('store_type', 'pharmacy')->value('id')
            ?? 2;

        return [(int)$fallback];
    }

    public function getSummary(Request $request)
    {
        $request->validate([
            'store_id' => 'nullable',
            'mode' => 'required|in:given,received',
            'group_by' => 'required|in:category,destination,product',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $storeIds = $this->resolveStoreIds($request);

        $data = $this->reportService->getSummaryData(
            $storeIds,
            $request->mode,
            $request->group_by,
            $request->start_date,
            $request->end_date
        );

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function printSummary(Request $request)
    {
        $request->validate([
            'store_id' => 'nullable',
            'mode' => 'required|in:given,received',
            'group_by' => 'required|in:category,destination,product',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $storeIds = $this->resolveStoreIds($request);

        $data = $this->reportService->getSummaryData(
            $storeIds,
            $request->mode,
            $request->group_by,
            $request->start_date,
            $request->end_date
        );

        $firstStoreName = !empty($storeIds) ? (Store::find($storeIds[0])?->store_name ?? 'Specific Stores') : 'All Stores';

        $viewData = [
            'data' => $data,
            'appsettings' => appsettings(),
            'pharmacist' => userfullname(Auth::id()),
            'print_date' => Carbon::now()->format('d M Y H:i'),
            'mode' => $request->mode,
            'group_by' => $request->group_by,
            'filters' => [
                'date_from' => Carbon::parse($request->start_date)->format('d M Y'),
                'date_to' => Carbon::parse($request->end_date)->format('d M Y'),
                'store' => $firstStoreName,
            ],
        ];

        return view('admin.inventory.print.summary-report-print', $viewData);
    }

    public function getDrillDown(Request $request)
    {
        $request->validate([
            'store_id' => 'nullable',
            'mode' => 'required|in:given,received',
            'group_by' => 'required|in:category,destination,product',
            'group_key' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $storeIds = $this->resolveStoreIds($request);

        $data = $this->reportService->getDrillDownData(
            $storeIds,
            $request->mode,
            $request->group_by,
            $request->group_key,
            $request->start_date,
            $request->end_date
        );

        return response()->json(['status' => 'success', 'data' => $data]);
    }
}
