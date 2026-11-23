<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ProductCategory;
use App\Models\ServiceCategory;
use App\Services\MyActivity\MyActivityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The Transactions Console's "What I did" view (web modal and staff app): the stages a person can work at in
 * their workbench with my counts, and the items I did at a stage (or all of them). See MyActivityService.
 */
class ProcessedTransactionsController extends Controller
{
    private const WORKBENCHES = ['reception', 'billing', 'pharmacy', 'lab', 'imaging', 'nursing', 'maternity', 'doctor'];

    public function activity(Request $request, MyActivityService $service)
    {
        $user = $request->user();
        $workbench = in_array($request->input('workbench'), self::WORKBENCHES, true) ? $request->input('workbench') : 'reception';

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : Carbon::today();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : Carbon::today()->endOfDay();
        if ($request->filled('to') && !str_contains((string) $request->input('to'), ':')) {
            $to = $to->copy()->endOfDay();
        }

        $result = $service->run(
            $workbench,
            $user,
            $from,
            $to,
            (string) $request->input('stage', 'all'),
            $request->only(['state', 'department_id', 'item_type', 'category'])
        );

        return response()->json($result + [
            'success' => true,
            'workbench' => $workbench,
            // The cash views (paid through me, manage) follow the web's biller rule.
            'can_collect' => MyActivityService::canCollect($user),
            'all_departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'all_categories' => [
                'services' => ServiceCategory::where('status', 1)->orderBy('category_name')->pluck('category_name'),
                'products' => ProductCategory::where('status', 1)->orderBy('category_name')->pluck('category_name'),
            ],
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
        ]);
    }
}
