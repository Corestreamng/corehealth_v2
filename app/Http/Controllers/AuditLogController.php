<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class AuditLogController extends Controller
{
    /**
     * Display the audit logs page
     */
    public function index()
    {
        // Check permission
        // if (!Auth::user()->can('manage-settings')  || !Auth::user()->hasRole('ADMIN') || !Auth::user()->hasRole('SUPER-ADMIN')) {
        //     abort(403, 'Unauthorized access to Audit Logs');
        // }

        // Get distinct model types for filter
        $modelTypes = Audit::selectRaw('DISTINCT auditable_type')
            ->whereNotNull('auditable_type')
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->map(function ($type) {
                // Extract the model name from the full namespace
                $parts = explode('\\', $type);
                return [
                    'full' => $type,
                    'short' => end($parts)
                ];
            });

        // Get distinct event types
        $eventTypes = Audit::selectRaw('DISTINCT event')
            ->whereNotNull('event')
            ->orderBy('event')
            ->pluck('event');

        // Get distinct users who made audits
        $users = \App\Models\User::whereIn('id', Audit::distinct()->pluck('user_id'))
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->firstname . ' ' . $user->surname
                ];
            });

        return view('admin.audit-logs.index', compact('modelTypes', 'eventTypes', 'users'));
    }

    /**
     * Get audit logs data for DataTables
     */
    public function getData(Request $request)
    {
        $query = Audit::with(['user'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('model_type')) {
            $query->where('auditable_type', $request->model_type);
        }

        if ($request->filled('event_type')) {
            $query->where('event', $request->event_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search_text')) {
            $searchText = $request->search_text;
            $query->where(function ($q) use ($searchText) {
                $q->where('url', 'like', "%{$searchText}%")
                    ->orWhere('ip_address', 'like', "%{$searchText}%")
                    ->orWhere('user_agent', 'like', "%{$searchText}%")
                    ->orWhereRaw('JSON_EXTRACT(old_values, "$") LIKE ?', ["%{$searchText}%"])
                    ->orWhereRaw('JSON_EXTRACT(new_values, "$") LIKE ?', ["%{$searchText}%"]);
            });
        }

        return DataTables::of($query)
            ->editColumn('user_id', function ($audit) {
                if ($audit->user) {
                    return $audit->user->firstname . ' ' . $audit->user->surname;
                }
                return 'System';
            })
            ->editColumn('auditable_type', function ($audit) {
                $parts = explode('\\', $audit->auditable_type);
                $modelName = end($parts);
                return '<span class="badge badge-info">' . $modelName . '</span>';
            })
            ->editColumn('event', function ($audit) {
                $badges = [
                    'created' => 'success',
                    'updated' => 'primary',
                    'deleted' => 'danger',
                    'restored' => 'warning',
                ];
                $color = $badges[$audit->event] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($audit->event) . '</span>';
            })
            ->addColumn('changes', function ($audit) {
                $oldValues = $audit->old_values ?? [];
                $newValues = $audit->new_values ?? [];

                if (empty($oldValues) && empty($newValues)) {
                    return '<span class="text-muted">No changes recorded</span>';
                }

                $changeCount = max(count($oldValues), count($newValues));
                return '<button class="btn btn-sm btn-outline-info view-changes" data-audit-id="' . $audit->id . '">
                    <i class="fa fa-eye"></i> View Changes (' . $changeCount . ')
                </button>';
            })
            ->editColumn('created_at', function ($audit) {
                return $audit->created_at->format('M d, Y h:i A');
            })
            ->addColumn('actions', function ($audit) {
                return '<button class="btn btn-sm btn-primary view-details" data-audit-id="' . $audit->id . '">
                    <i class="fa fa-info-circle"></i> Details
                </button>';
            })
            ->rawColumns(['auditable_type', 'event', 'changes', 'actions'])
            ->make(true);
    }

    /**
     * Get detailed information about a specific audit
     */
    public function show($id)
    {
        $audit = Audit::with(['user'])->findOrFail($id);

        // Format the data
        $data = [
            'id' => $audit->id,
            'event' => ucfirst($audit->event),
            'model' => class_basename($audit->auditable_type),
            'model_id' => $audit->auditable_id,
            'user' => $audit->user ? $audit->user->firstname . ' ' . $audit->user->surname : 'System',
            'ip_address' => $audit->ip_address,
            'user_agent' => $audit->user_agent,
            'url' => $audit->url,
            'created_at' => $audit->created_at->format('F d, Y h:i:s A'),
            'old_values' => $audit->old_values ?? [],
            'new_values' => $audit->new_values ?? [],
            'tags' => $audit->tags ?? null,
        ];

        return response()->json($data);
    }

    /**
     * Export audit logs
     */
    public function export(Request $request)
    {
        $query = Audit::with(['user'])
            ->orderBy('created_at', 'desc');

        // Apply same filters as getData
        if ($request->filled('model_type')) {
            $query->where('auditable_type', $request->model_type);
        }

        if ($request->filled('event_type')) {
            $query->where('event', $request->event_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $audits = $query->limit(5000)->get(); // Limit to prevent memory issues

        $filename = 'audit_logs_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($audits) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'ID',
                'Date/Time',
                'User',
                'Model',
                'Model ID',
                'Event',
                'IP Address',
                'URL',
                'Changes'
            ]);

            // Data rows
            foreach ($audits as $audit) {
                $userName = $audit->user ? $audit->user->firstname . ' ' . $audit->user->surname : 'System';
                $modelName = class_basename($audit->auditable_type);

                // Summarize changes
                $changes = [];
                if ($audit->old_values) {
                    foreach ($audit->old_values as $key => $value) {
                        $newValue = $audit->new_values[$key] ?? '';
                        $changes[] = "$key: '$value' → '$newValue'";
                    }
                }

                fputcsv($file, [
                    $audit->id,
                    $audit->created_at->format('Y-m-d H:i:s'),
                    $userName,
                    $modelName,
                    $audit->auditable_id,
                    ucfirst($audit->event),
                    $audit->ip_address,
                    $audit->url,
                    implode('; ', $changes)
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get statistics for dashboard
     */
    public function stats()
    {
        $totalAudits = Audit::count();
        $todayAudits = Audit::whereDate('created_at', today())->count();
        $thisWeekAudits = Audit::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $thisMonthAudits = Audit::whereMonth('created_at', now()->month)->count();

        $eventCounts = Audit::selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event');

        $modelCounts = Audit::selectRaw('auditable_type, COUNT(*) as count')
            ->groupBy('auditable_type')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->mapWithKeys(function ($item) {
                $modelName = class_basename($item->auditable_type);
                return [$modelName => $item->count];
            });

        return response()->json([
            'total' => $totalAudits,
            'today' => $todayAudits,
            'this_week' => $thisWeekAudits,
            'this_month' => $thisMonthAudits,
            'by_event' => $eventCounts,
            'by_model' => $modelCounts,
        ]);
    }
}
