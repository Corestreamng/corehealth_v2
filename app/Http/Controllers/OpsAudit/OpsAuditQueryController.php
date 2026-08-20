<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class OpsAuditQueryController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        $viewData = compact('startDate', 'endDate');
        $viewData['zoneKey'] = 'queries-dashboard';
        
        $viewData['kpis'] = [
            'total_active' => \App\Models\AuditMark::where('status', 'queried')->count(),
            'total_resolved' => \App\Models\AuditMark::where('status', 'resolved')->count(),
            'active_in_period' => \App\Models\AuditMark::where('status', 'queried')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'resolved_in_period' => \App\Models\AuditMark::where('status', 'resolved')->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];
        
        return view('admin.ops_audit.queries_dashboard', $viewData);
    }

    public function data(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        $query = \App\Models\AuditMark::with(['auditor', 'resolver'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($tab === 'active-queries') {
            $query->where('status', 'queried');
        } elseif ($tab === 'resolved-queries') {
            $query->where('status', 'resolved');
        }

        return DataTables::eloquent($query)
            ->editColumn('created_at', function($r) {
                return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $r->created_at->format('h:i A') . '</small>';
            })
            ->addColumn('record_details', function($r) {
                $type = class_basename($r->auditable_type);
                $link = '<small class="text-primary">ID: ' . $r->auditable_id . '</small>';
                return '<div class="font-weight-bold text-dark">' . $type . '</div>' . $link . '<br><small class="text-muted">Zone: ' . ($r->zone_key ?? 'General') . '</small>';
            })
            ->addColumn('query_info', function($r) {
                $auditor = $r->auditor->name ?? 'Auditor';
                return '<div class="font-weight-bold text-danger">' . \Illuminate\Support\Str::limit($r->query_notes, 50) . '</div><small class="text-muted">By: ' . $auditor . '</small>';
            })
            ->addColumn('status_badge', function($r) {
                if ($r->status === 'resolved') {
                    return '<span class="badge bg-success">Resolved</span><br><small class="text-muted">By: ' . ($r->resolver->name ?? 'Unknown') . '</small>';
                }
                return '<span class="badge bg-warning text-dark">Active Query</span>';
            })
            ->addColumn('action', function($r) {
                return '<button class="btn btn-sm btn-outline-primary" onclick="viewQueryDetails(' . $r->id . ')"><i class="mdi mdi-eye"></i> View</button>';
            })
            ->rawColumns(['created_at', 'record_details', 'query_info', 'status_badge', 'action'])
            ->make(true);
    }

    public function details($id)
    {
        $mark = \App\Models\AuditMark::with(['auditor', 'resolver'])->find($id);

        if (!$mark) {
            return response()->json(['error' => 'Query record not found.'], 404);
        }

        $auditorName = $mark->auditor ? trim(($mark->auditor->firstname ?? '') . ' ' . ($mark->auditor->surname ?? '')) : 'System';
        if (empty($auditorName) && $mark->auditor) {
            $auditorName = $mark->auditor->name ?? 'Auditor';
        }

        $resolverName = $mark->resolver ? trim(($mark->resolver->firstname ?? '') . ' ' . ($mark->resolver->surname ?? '')) : 'N/A';
        if ($resolverName === 'N/A' && $mark->resolver) {
            $resolverName = $mark->resolver->name ?? 'System User';
        }

        // Target record details formatting
        $targetDetails = [];
        try {
            $modelClass = $mark->auditable_type;
            if (class_exists($modelClass)) {
                $record = $modelClass::find($mark->auditable_id);
                if ($record) {
                    $targetDetails['id'] = $record->id;
                    $targetDetails['created_at'] = isset($record->created_at) ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y h:i A') : 'N/A';

                    // Patient resolution
                    $patient = null;
                    if (isset($record->patient)) {
                        $patient = $record->patient;
                    } elseif (method_exists($record, 'patient')) {
                        $patient = $record->patient;
                    }

                    if ($patient) {
                        $pUser = $patient->user ?? null;
                        $pName = $pUser ? trim(($pUser->firstname ?? '') . ' ' . ($pUser->surname ?? '')) : ($patient->fullname ?? 'Unknown Patient');
                        $targetDetails['patient_name'] = $pName ?: 'Unknown Patient';
                        $targetDetails['file_no'] = $patient->file_no ?? 'N/A';
                        $targetDetails['hmo_name'] = $patient->hmo->name ?? null;
                        $targetDetails['hmo_scheme'] = $patient->hmoScheme->name ?? null;
                        $targetDetails['hmo_no'] = $patient->hmo_no ?? null;
                    }

                    // Item / Service / Product name
                    if (isset($record->product_name)) {
                        $targetDetails['item_name'] = $record->product_name;
                    } elseif (isset($record->service_name)) {
                        $targetDetails['item_name'] = $record->service_name;
                    } elseif (isset($record->product->product_name)) {
                        $targetDetails['item_name'] = $record->product->product_name;
                    } elseif (isset($record->service->service_name)) {
                        $targetDetails['item_name'] = $record->service->service_name;
                    } elseif (isset($record->procedure->procedure_name)) {
                        $targetDetails['item_name'] = $record->procedure->procedure_name;
                    }

                    // Amounts & Quantities
                    if (isset($record->amount)) {
                        $targetDetails['amount'] = '₦' . number_format((float)$record->amount, 2);
                    } elseif (isset($record->total_amount)) {
                        $targetDetails['amount'] = '₦' . number_format((float)$record->total_amount, 2);
                    } elseif (isset($record->total_price)) {
                        $targetDetails['amount'] = '₦' . number_format((float)$record->total_price, 2);
                    } elseif (isset($record->cost_price)) {
                        $targetDetails['amount'] = '₦' . number_format((float)$record->cost_price, 2);
                    }

                    if (isset($record->qty)) {
                        $targetDetails['qty'] = number_format($record->qty) . ' Units';
                    } elseif (isset($record->quantity)) {
                        $targetDetails['qty'] = number_format($record->quantity) . ' Units';
                    }

                    // Status
                    if (isset($record->status)) {
                        $targetDetails['record_status'] = ucfirst(str_replace('_', ' ', $record->status));
                    }
                }
            }
        } catch (\Throwable $e) {
            // Soft fallback if eager load fails
        }

        return response()->json([
            'id' => $mark->id,
            'status' => $mark->status,
            'model_type' => class_basename($mark->auditable_type),
            'full_model_type' => $mark->auditable_type,
            'model_id' => $mark->auditable_id,
            'zone_key' => ucfirst(str_replace(['_', '-'], ' ', $mark->zone_key ?? 'General')),
            'query_notes' => $mark->query_notes,
            'auditor' => $auditorName,
            'created_at' => $mark->created_at ? \Carbon\Carbon::parse($mark->created_at)->format('M d, Y h:i A') : 'N/A',
            'resolution_notes' => $mark->query_resolution_notes ?? $mark->resolution_notes,
            'resolver' => $resolverName,
            'resolved_at' => $mark->query_resolved_at ? \Carbon\Carbon::parse($mark->query_resolved_at)->format('M d, Y h:i A') : null,
            'target_details' => $targetDetails,
        ]);
    }
}
