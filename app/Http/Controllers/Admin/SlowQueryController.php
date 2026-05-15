<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlowQuery;
use App\Models\ApplicationStatu;
use App\Services\SlowQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            if ($request->filled('source_filter') && $request->source_filter !== 'all') {
                $query->where('source', $request->source_filter);
            }
            if ($request->filled('search_query')) {
                $query->where('query', 'like', '%' . $request->search_query . '%');
            }

            return \Yajra\DataTables\DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('timestamp', function($q) {
                    return $q->timestamp->format('Y-m-d H:i:s');
                })
                ->editColumn('query_time', function($q) {
                    $color = $q->query_time > 10 ? 'text-danger font-weight-bold' : ($q->query_time > 5 ? 'text-warning' : '');
                    return '<span class="'.$color.'">'.$q->query_time.'s</span>';
                })
                ->editColumn('rows_examined', function($q) {
                    $badge = $q->rows_examined > 100000 ? 'badge-warning' : 'badge-light';
                    return '<span class="badge '.$badge.'">'.number_format($q->rows_examined).'</span>';
                })
                ->addColumn('source_info', function($q) {
                    $source = $q->source ?? 'Unknown';
                    $shortSource = strlen($source) > 40 ? substr($source, 0, 37) . '...' : $source;
                    return '<code title="'.$source.'" class="text-info">'.$shortSource.'</code>';
                })
                ->editColumn('query', function($q) {
                    return '<code class="text-truncate d-inline-block" style="max-width: 250px;" title="'.e($q->query).'">'.e(substr($q->query, 0, 80)).'...</code>';
                })
                ->addColumn('actions', function($q) {
                    return '<button class="btn btn-sm btn-outline-primary" onclick="showQuery('.e(json_encode($q)).')"><i class="mdi mdi-eye"></i></button>';
                })
                ->rawColumns(['query_time', 'rows_examined', 'source_info', 'query', 'actions'])
                ->make(true);
        }

        // Summary Stats
        $stats = [
            'total_count' => SlowQuery::count(),
            'avg_time' => round(SlowQuery::avg('query_time') ?? 0, 2),
            'max_time' => round(SlowQuery::max('query_time') ?? 0, 2),
            'total_examined' => SlowQuery::sum('rows_examined'),
            'top_source' => SlowQuery::select('source', DB::raw('count(*) as total'))
                ->whereNotNull('source')
                ->groupBy('source')
                ->orderBy('total', 'desc')
                ->first(),
        ];

        $sources = SlowQuery::whereNotNull('source')->distinct()->pluck('source');

        return view('admin.slow-queries.index', compact('config', 'appSettings', 'stats', 'sources'));
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
