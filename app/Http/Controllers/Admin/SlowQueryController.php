<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlowQuery;
use App\Models\ApplicationStatu;
use App\Services\SlowQueryService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SlowQueryController extends Controller
{
    protected $slowQueryService;

    public function __construct(SlowQueryService $slowQueryService)
    {
        $this->slowQueryService = $slowQueryService;
    }

    /**
     * Display the slow query monitor UI.
     */
    public function index(Request $request)
    {
        $config = $this->slowQueryService->checkConfiguration();
        $appSettings = ApplicationStatu::first();

        if ($request->ajax()) {
            $query = SlowQuery::query();

            // Filters
            if ($request->filled('start_date')) {
                $query->whereDate('timestamp', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('timestamp', '<=', $request->end_date);
            }
            if ($request->filled('min_duration')) {
                $query->where('query_time', '>=', $request->min_duration);
            }
            if ($request->filled('min_rows')) {
                $query->where('rows_examined', '>=', $request->min_rows);
            }
            if ($request->filled('search')) {
                $query->where('query', 'like', '%' . $request->search . '%');
            }

            $slowQueries = $query->orderBy('timestamp', 'desc')->paginate(50);

            return response()->json([
                'queries' => $slowQueries,
                'config' => $config,
                'last_check' => $appSettings->last_slow_query_check,
            ]);
        }

        return view('admin.slow-queries.index', compact('config', 'appSettings'));
    }

    /**
     * Attempt to configure MySQL settings.
     */
    public function setup(Request $request)
    {
        $request->validate([
            'threshold' => 'required|numeric|min:0.1',
            'custom_path' => 'nullable|string',
        ]);

        $result = $this->slowQueryService->configureMySQL($request->threshold, $request->custom_path);

        if ($result['success']) {
            $appSettings = ApplicationStatu::first();
            $appSettings->update([
                'slow_query_log_path' => $request->custom_path ?: ($this->slowQueryService->checkConfiguration()['log_file'] ?? null),
            ]);
        }

        return response()->json($result);
    }

    /**
     * Manually trigger a log parse.
     */
    public function refresh()
    {
        $this->slowQueryService->parseLog();
        $appSettings = ApplicationStatu::first();

        return response()->json([
            'success' => true,
            'message' => 'Log parsing completed.',
            'last_check' => $appSettings->last_slow_query_check,
        ]);
    }
}
