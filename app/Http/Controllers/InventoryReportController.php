<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\InventoryReportService;

class InventoryReportController extends Controller
{
    protected $reportService;

    public function __construct(InventoryReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function getSummary(Request $request)
    {
        $request->validate([
            'store_id' => 'required',
            'mode' => 'required|in:given,received',
            'group_by' => 'required|in:category,destination,product',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $storeIds = is_array($request->store_id) ? $request->store_id : explode(',', $request->store_id);

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
            'store_id' => 'required',
            'mode' => 'required|in:given,received',
            'group_by' => 'required|in:category,destination,product',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $storeIds = is_array($request->store_id) ? $request->store_id : explode(',', $request->store_id);

        $data = $this->reportService->getSummaryData(
            $storeIds,
            $request->mode,
            $request->group_by,
            $request->start_date,
            $request->end_date
        );

        $viewData = [
            'data' => $data,
            'appsettings' => appsettings(),
            'pharmacist' => userfullname(\Illuminate\Support\Facades\Auth::id()),
            'print_date' => \Carbon\Carbon::now()->format('d M Y H:i'),
            'mode' => $request->mode,
            'group_by' => $request->group_by,
            'filters' => [
                'date_from' => \Carbon\Carbon::parse($request->start_date)->format('d M Y'),
                'date_to' => \Carbon\Carbon::parse($request->end_date)->format('d M Y'),
                'store' => $request->store_id ? (\App\Models\Store::find($storeIds[0])->store_name ?? 'Specific Stores') : 'All Stores'
            ]
        ];

        return view('admin.inventory.print.summary-report-print', $viewData);
    }

    public function getDrillDown(Request $request)
    {
        $request->validate([
            'store_id' => 'required',
            'mode' => 'required|in:given,received',
            'group_by' => 'required|in:category,destination',
            'group_key' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $storeIds = is_array($request->store_id) ? $request->store_id : explode(',', $request->store_id);

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
