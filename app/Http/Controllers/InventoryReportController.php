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
            'group_by' => 'required|in:category,destination',
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
