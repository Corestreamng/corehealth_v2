<?php

namespace App\Http\Controllers;

use App\Models\NursingShift;
use App\Models\ShiftHandover;
use App\Models\ShiftAction;
use App\Models\Ward;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

/**
 * ShiftController
 *
 * Handles all shift management operations including:
 * - Starting/ending shifts
 * - Handover creation and acknowledgment
 * - Shift activity tracking
 * - Reports and calendar
 */
class ShiftController extends Controller
{
    /**
     * Check if user has an active shift
     */
    public function checkActiveShift()
    {
        try {
            $shift = NursingShift::getActiveForUser(auth()->id());

            if ($shift) {
                return response()->json([
                    'success' => true,
                    'has_active_shift' => true,
                    'shift' => [
                        'id' => $shift->id,
                        'shift_type' => $shift->shift_type,
                        'shift_type_label' => NursingShift::SHIFT_TYPES[$shift->shift_type]['label'] ?? ucfirst($shift->shift_type),
                        'ward_id' => $shift->ward_id,
                        'ward_name' => $shift->ward->name ?? 'All Wards',
                        'started_at' => $shift->started_at->format('h:i A'),
                        'started_at_full' => $shift->started_at->format('M d, Y h:i A'),
                        'started_at_timestamp' => $shift->started_at->timestamp,
                        'scheduled_end' => $shift->scheduled_end_at ? $shift->scheduled_end_at->format('h:i A') : null,
                        'scheduled_end_timestamp' => $shift->scheduled_end_at ? $shift->scheduled_end_at->timestamp : null,
                        'elapsed_seconds' => $shift->elapsed_seconds,
                        'elapsed_time' => $this->formatDuration($shift->elapsed_seconds),
                        'remaining_seconds' => $shift->remaining_seconds,
                        'remaining_time' => $shift->remaining_seconds > 0 ? $this->formatDuration($shift->remaining_seconds) : null,
                        'is_overdue' => $shift->is_overdue,
                        'status' => $shift->status,
                        'total_actions' => $shift->total_actions,
                        'counters' => [
                            'vitals' => $shift->vitals_count,
                            'medications' => $shift->medications_count,
                            'injections' => $shift->injections_count,
                            'immunizations' => $shift->immunizations_count,
                            'notes' => $shift->notes_count,
                            'bills' => $shift->bills_count,
                        ],
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'has_active_shift' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking active shift: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking shift status',
            ], 500);
        }
    }

    /**
     * Get pending handovers that need acknowledgment before starting shift
     */
    public function getPendingHandovers(Request $request)
    {
        try {
            $wardId = $request->ward_id;
            $hours = $request->input('hours', 24);

            $query = ShiftHandover::with(['creator', 'shift', 'ward'])
                ->recent($hours)
                ->unacknowledged()
                ->orderBy('created_at', 'desc');

            if ($wardId) {
                $query->where(function($q) use ($wardId) {
                    $q->forWard($wardId)->orWhereNull('ward_id');
                });
            }

            $handovers = $query->limit(10)->get();

            // Also get count of unacknowledged critical handovers
            $criticalCount = ShiftHandover::unacknowledged()
                ->withCriticalNotes()
                ->when($wardId, function($q) use ($wardId) {
                    $q->where(function($q2) use ($wardId) {
                        $q2->forWard($wardId)->orWhereNull('ward_id');
                    });
                })
                ->count();

            return response()->json([
                'success' => true,
                'handovers' => $handovers->map(function($handover) {
                    return [
                        'id' => $handover->id,
                        'shift_type' => $handover->shift_type,
                        'shift_type_badge' => $handover->shift_type_badge,
                        'ward_name' => $handover->ward->name ?? 'All Wards',
                        'created_by_name' => $handover->creator->name ?? 'Unknown',
                        'created_at' => $handover->created_at->format('M d, h:i A'),
                        'created_at_ago' => $handover->created_at->diffForHumans(),
                        'summary_preview' => \Str::limit(strip_tags($handover->summary), 150),
                        'has_critical_notes' => $handover->has_critical_notes,
                        'pending_tasks_count' => is_array($handover->pending_tasks) ? count($handover->pending_tasks) : 0,
                        'is_acknowledged' => $handover->is_acknowledged,
                    ];
                }),
                'total_pending' => $handovers->count(),
                'critical_count' => $criticalCount,
                'has_critical' => $criticalCount > 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting pending handovers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading handovers',
            ], 500);
        }
    }

    /**
     * Start a new shift
     */
    public function startShift(Request $request)
    {
        $request->validate([
            'ward_id' => 'nullable|exists:wards,id',
            'shift_type' => 'nullable|in:morning,afternoon,night',
            'acknowledged_handovers' => 'array',
            'acknowledged_handovers.*' => 'exists:shift_handovers,id',
        ]);

        try {
            DB::beginTransaction();

            // Check for existing active shift
            $existingShift = NursingShift::getActiveForUser(auth()->id());
            if ($existingShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active shift. Please end it first.',
                    'shift' => $existingShift,
                ], 422);
            }

            // Check for critical unacknowledged handovers
            $wardId = $request->ward_id;
            $criticalHandovers = ShiftHandover::getCriticalUnacknowledged($wardId);
            $acknowledgedIds = $request->acknowledged_handovers ?? [];

            // Filter out already acknowledged ones
            $remainingCritical = $criticalHandovers->whereNotIn('id', $acknowledgedIds);

            if ($remainingCritical->count() > 0 && !$request->force_start) {
                return response()->json([
                    'success' => false,
                    'requires_acknowledgment' => true,
                    'message' => 'Please acknowledge critical handovers before starting your shift.',
                    'critical_handovers' => $remainingCritical->map(function($h) {
                        return [
                            'id' => $h->id,
                            'created_by' => $h->creator->name ?? 'Unknown',
                            'created_at' => $h->created_at->format('M d, h:i A'),
                            'critical_notes_preview' => \Str::limit(strip_tags($h->critical_notes), 100),
                        ];
                    }),
                ], 422);
            }

            // Acknowledge the handovers
            if (!empty($acknowledgedIds)) {
                foreach ($acknowledgedIds as $handoverId) {
                    $handover = ShiftHandover::find($handoverId);
                    if ($handover && !$handover->is_acknowledged) {
                        $handover->acknowledge(auth()->id());
                    }
                }
            }

            // Start the shift
            $shift = NursingShift::startShift(
                auth()->id(),
                [
                    'ward_id' => $request->ward_id,
                    'shift_type' => $request->shift_type,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shift started successfully',
                'shift' => [
                    'id' => $shift->id,
                    'shift_type' => $shift->shift_type,
                    'shift_type_label' => NursingShift::SHIFT_TYPES[$shift->shift_type]['label'] ?? ucfirst($shift->shift_type),
                    'ward_name' => $shift->ward->name ?? 'All Wards',
                    'started_at' => $shift->started_at->format('h:i A'),
                    'scheduled_end' => $shift->scheduled_end_at->format('h:i A'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error starting shift: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error starting shift: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * End the current shift
     */
    public function endShift(Request $request)
    {
        $request->validate([
            'critical_notes' => 'nullable|string|max:5000',
            'concluding_notes' => 'nullable|string|max:5000',
            'pending_tasks' => 'nullable|array',
            'pending_tasks.*.description' => 'required|string',
            'pending_tasks.*.priority' => 'nullable|in:low,normal,high,urgent',
            'pending_tasks.*.patient_id' => 'nullable|exists:patients,id',
            'create_handover' => 'nullable',
        ]);

        // Convert create_handover to boolean (handles string "true"/"false" from form)
        $createHandover = filter_var($request->input('create_handover', true), FILTER_VALIDATE_BOOLEAN);

        try {
            DB::beginTransaction();

            $shift = NursingShift::getActiveForUser(auth()->id());

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active shift found',
                ], 404);
            }

            // End the shift
            // First update notes on the shift if provided
            if ($request->filled('concluding_notes') || $request->filled('critical_notes')) {
                $shift->update([
                    'concluding_notes' => $request->input('concluding_notes'),
                    'critical_notes' => $request->input('critical_notes'),
                ]);
            }

            $shift->endShift(false);

            // Create handover if requested
            $handover = null;
            if ($createHandover) {
                $handover = $shift->createHandover([
                    'pending_tasks' => $request->input('pending_tasks', []),
                    'critical_notes' => $request->input('critical_notes'),
                    'concluding_notes' => $request->input('concluding_notes'),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shift ended successfully',
                'shift_summary' => [
                    'duration' => $this->formatDuration($shift->elapsed_seconds),
                    'total_actions' => $shift->total_actions,
                    'counters' => [
                        'vitals' => $shift->vitals_count,
                        'medications' => $shift->medications_count,
                        'injections' => $shift->injections_count,
                        'immunizations' => $shift->immunizations_count,
                        'notes' => $shift->notes_count,
                        'bills' => $shift->bills_count,
                    ],
                ],
                'handover_created' => $handover !== null,
                'handover_id' => $handover?->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error ending shift: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error ending shift: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get shift preview with audit-based activities
     * Shows what will be included in the handover
     */
    public function getShiftPreview(Request $request)
    {
        try {
            $shift = NursingShift::getActiveForUser(auth()->id());

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active shift found',
                ], 404);
            }

            // Get grouped audit logs
            $groupedAuditLogs = $shift->getGroupedAuditLogs();

            // Get patient highlights
            $patientHighlights = $shift->getPatientHighlights();

            // Generate detailed summary
            $detailedSummary = $shift->generateDetailedSummary();

            // Build activity summary for display
            $activitySummary = [];
            foreach ($groupedAuditLogs as $type => $data) {
                $config = NursingShift::NURSING_AUDITABLE_TYPES[$type] ?? null;
                if ($config) {
                    $activitySummary[] = [
                        'type' => $type,
                        'label' => $config['label'],
                        'icon' => $config['icon'],
                        'color' => $config['color'],
                        'count' => $data['count'],
                        'events' => array_map(function($event, $count) {
                            return ucfirst($event) . ": $count";
                        }, array_keys($data['events']), $data['events']),
                        'patients_count' => count($data['patients']),
                    ];
                }
            }

            // Calculate totals
            $totalEvents = array_sum(array_column($activitySummary, 'count'));

            // Calculate unique patients safely
            $totalPatients = 0;
            if (!empty($groupedAuditLogs)) {
                $allPatientIds = [];
                foreach ($groupedAuditLogs as $data) {
                    $allPatientIds = array_merge($allPatientIds, array_keys($data['patients']));
                }
                $totalPatients = count(array_unique($allPatientIds));
            }

            return response()->json([
                'success' => true,
                'preview' => [
                    'shift_id' => $shift->id,
                    'started_at' => $shift->started_at->format('M d, Y h:i A'),
                    'elapsed_time' => $this->formatDuration($shift->elapsed_seconds),
                    'total_events' => $totalEvents,
                    'total_patients' => $totalPatients,
                    'activity_summary' => $activitySummary,
                    'patient_highlights' => $patientHighlights,
                    'detailed_summary' => $detailedSummary,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting shift preview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error getting shift preview',
            ], 500);
        }
    }

    /**
     * Get handovers list (Cards or DataTable compatible)
     */
    public function getHandovers(Request $request)
    {
        try {
            $query = ShiftHandover::with(['creator', 'receiver', 'ward', 'shift'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->ward_id) {
                $query->forWard($request->ward_id);
            }

            if ($request->shift_type) {
                $query->where('shift_type', $request->shift_type);
            }

            if ($request->status === 'acknowledged') {
                $query->acknowledged();
            } elseif ($request->status === 'pending') {
                $query->unacknowledged();
            }

            if ($request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Priority filter
            if ($request->priority === 'critical') {
                $query->withCriticalNotes();
            } elseif ($request->priority === 'has_tasks') {
                $query->whereRaw("JSON_LENGTH(pending_tasks) > 0");
            }

            // Search filter
            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('summary', 'like', "%{$search}%")
                      ->orWhere('critical_notes', 'like', "%{$search}%")
                      ->orWhere('concluding_notes', 'like', "%{$search}%")
                      ->orWhereHas('creator', function($q2) use ($search) {
                          $q2->where('firstname', 'like', "%{$search}%")
                             ->orWhere('surname', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Sorting
            if ($request->sort === 'oldest') {
                $query->reorder('created_at', 'asc');
            } elseif ($request->sort === 'priority') {
                $query->reorder()
                    ->orderByRaw("CASE WHEN critical_notes IS NOT NULL AND critical_notes != '' THEN 0 ELSE 1 END")
                    ->orderBy('created_at', 'desc');
            }

            // If format=cards, return paginated card-friendly data
            if ($request->format === 'cards') {
                return $this->getHandoversCards($query, $request);
            }

            // Otherwise return DataTable format
            return DataTables::of($query)
                ->addColumn('shift_type_badge', function($handover) {
                    return $handover->shift_type_badge;
                })
                ->addColumn('ward_name', function($handover) {
                    return $handover->ward->name ?? '<span class="text-muted">All Wards</span>';
                })
                ->addColumn('created_by_name', function($handover) {
                    return $handover->creator->name ?? 'Unknown';
                })
                ->addColumn('created_at_formatted', function($handover) {
                    return $handover->created_at->format('M d, Y h:i A');
                })
                ->addColumn('status_badge', function($handover) {
                    return $handover->status_badge;
                })
                ->addColumn('has_critical', function($handover) {
                    return $handover->has_critical_notes
                        ? '<span class="badge badge-danger"><i class="mdi mdi-alert"></i> Yes</span>'
                        : '<span class="text-muted">-</span>';
                })
                ->addColumn('pending_count', function($handover) {
                    $count = is_array($handover->pending_tasks) ? count($handover->pending_tasks) : 0;
                    return $count > 0
                        ? '<span class="badge badge-warning">' . $count . '</span>'
                        : '<span class="text-muted">0</span>';
                })
                ->addColumn('actions', function($handover) {
                    $buttons = '<div class="btn-group">';
                    $buttons .= '<button type="button" class="btn btn-sm btn-info view-handover" data-id="' . $handover->id . '" title="View Details"><i class="mdi mdi-eye"></i></button>';
                    if (!$handover->is_acknowledged) {
                        $buttons .= '<button type="button" class="btn btn-sm btn-success acknowledge-handover" data-id="' . $handover->id . '" title="Acknowledge"><i class="mdi mdi-check"></i></button>';
                    }
                    $buttons .= '</div>';
                    return $buttons;
                })
                ->rawColumns(['shift_type_badge', 'ward_name', 'status_badge', 'has_critical', 'pending_count', 'actions'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error('Error getting handovers: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error loading handovers',
            ], 500);
        }
    }

    /**
     * Get handovers in cards format with pagination
     */
    protected function getHandoversCards($query, Request $request)
    {
        $perPage = $request->input('per_page', 12);
        $page = $request->input('page', 1);

        // Clone query for stats
        $statsQuery = clone $query;

        // Get stats
        $totalCount = $statsQuery->count();
        $pendingCount = (clone $statsQuery)->unacknowledged()->count();
        $criticalCount = (clone $statsQuery)->withCriticalNotes()->count();

        // Paginate
        $handovers = $query->paginate($perPage, ['*'], 'page', $page);

        // Format data for cards
        $data = $handovers->map(function($handover) {
            $shiftLabels = [
                'morning' => 'Morning',
                'afternoon' => 'Afternoon',
                'night' => 'Night',
            ];

            return [
                'id' => $handover->id,
                'shift_type' => $handover->shift_type,
                'shift_type_label' => $shiftLabels[$handover->shift_type] ?? ucfirst($handover->shift_type),
                'ward_name' => $handover->ward->name ?? 'All Wards',
                'created_by_name' => $handover->creator->name ?? 'Unknown',
                'created_at' => $handover->created_at->format('M d, Y h:i A'),
                'created_at_full' => $handover->created_at->format('F d, Y \a\t h:i A'),
                'created_at_ago' => $handover->created_at->diffForHumans(),
                'summary_preview' => \Str::limit(strip_tags($handover->summary), 120),
                'critical_notes_preview' => $handover->critical_notes ? \Str::limit(strip_tags($handover->critical_notes), 80) : null,
                'has_critical_notes' => $handover->has_critical_notes,
                'pending_tasks_count' => is_array($handover->pending_tasks) ? count($handover->pending_tasks) : 0,
                'action_count' => is_array($handover->action_summary) ? array_sum(array_column($handover->action_summary, 'count')) : 0,
                'is_acknowledged' => $handover->is_acknowledged,
                'acknowledged_at' => $handover->acknowledged_at ? $handover->acknowledged_at->format('M d, h:i A') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'stats' => [
                'total' => $totalCount,
                'pending' => $pendingCount,
                'critical' => $criticalCount,
            ],
            'pagination' => [
                'current_page' => $handovers->currentPage(),
                'per_page' => $handovers->perPage(),
                'total' => $handovers->total(),
                'total_pages' => $handovers->lastPage(),
                'from' => $handovers->firstItem(),
                'to' => $handovers->lastItem(),
            ],
        ]);
    }

    /**
     * Get single handover details
     */
    public function getHandoverDetails($id)
    {
        try {
            $handover = ShiftHandover::with(['creator', 'receiver', 'acknowledgedByUser', 'ward', 'shift.actions'])
                ->findOrFail($id);

            $actionSummary = $handover->formatActionSummary();
            $patientHighlights = $handover->getPatientHighlightsFormatted();

            return response()->json([
                'success' => true,
                'handover' => [
                    'id' => $handover->id,
                    'shift_type' => $handover->shift_type,
                    'shift_type_badge' => $handover->shift_type_badge,
                    'ward_name' => $handover->ward->name ?? 'All Wards',
                    'created_by' => [
                        'name' => $handover->creator->name ?? 'Unknown',
                        'id' => $handover->created_by,
                    ],
                    'received_by' => $handover->receiver ? [
                        'name' => $handover->receiver->name,
                        'id' => $handover->received_by,
                    ] : null,
                    'created_at' => $handover->created_at->format('M d, Y h:i A'),
                    'created_at_ago' => $handover->created_at->diffForHumans(),
                    'shift_duration' => $handover->shift_duration,
                    'summary' => $handover->summary,
                    'critical_notes' => $handover->critical_notes,
                    'concluding_notes' => $handover->concluding_notes,
                    'has_critical_notes' => $handover->has_critical_notes,
                    'pending_tasks' => $handover->pending_tasks ?? [],
                    'action_summary' => $actionSummary,
                    'patient_highlights' => $patientHighlights,
                    'audit_details' => $handover->audit_details ?? [],
                    'is_acknowledged' => $handover->is_acknowledged,
                    'acknowledged_at' => $handover->acknowledged_at?->format('M d, Y h:i A'),
                    'acknowledged_by_name' => $handover->acknowledgedByUser->name ?? null,
                    'status_badge' => $handover->status_badge,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting handover details: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Handover not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Acknowledge a handover
     */
    public function acknowledgeHandover(Request $request, $id)
    {
        try {
            $handover = ShiftHandover::findOrFail($id);

            if ($handover->is_acknowledged) {
                return response()->json([
                    'success' => false,
                    'message' => 'This handover has already been acknowledged',
                ], 422);
            }

            $handover->acknowledge(auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Handover acknowledged successfully',
                'acknowledged_at' => $handover->acknowledged_at->format('M d, Y h:i A'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error acknowledging handover: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error acknowledging handover',
            ], 500);
        }
    }

    /**
     * Acknowledge multiple handovers
     */
    public function acknowledgeMultiple(Request $request)
    {
        $request->validate([
            'handover_ids' => 'required|array|min:1',
            'handover_ids.*' => 'exists:shift_handovers,id',
        ]);

        try {
            $acknowledged = 0;
            $alreadyAcknowledged = 0;

            foreach ($request->handover_ids as $id) {
                $handover = ShiftHandover::find($id);
                if ($handover) {
                    if ($handover->is_acknowledged) {
                        $alreadyAcknowledged++;
                    } else {
                        $handover->acknowledge(auth()->id());
                        $acknowledged++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Acknowledged {$acknowledged} handover(s)",
                'acknowledged' => $acknowledged,
                'already_acknowledged' => $alreadyAcknowledged,
            ]);
        } catch (\Exception $e) {
            Log::error('Error acknowledging multiple handovers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error acknowledging handovers',
            ], 500);
        }
    }

    /**
     * Get shift actions for current shift
     */
    public function getShiftActions(Request $request)
    {
        try {
            $shift = NursingShift::getActiveForUser(auth()->id());

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active shift',
                ], 404);
            }

            $grouped = ShiftAction::getGroupedForShift($shift->id);

            return response()->json([
                'success' => true,
                'actions' => $grouped,
                'total' => $shift->total_actions,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting shift actions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading actions',
            ], 500);
        }
    }

    /**
     * Get available wards for shift selection
     */
    public function getWards()
    {
        try {
            $wards = Ward::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type']);

            return response()->json([
                'success' => true,
                'wards' => $wards,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading wards: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading wards',
            ], 500);
        }
    }

    /**
     * Get shift calendar data
     */
    public function getCalendar(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        try {
            $shifts = NursingShift::with('user')
                ->whereBetween('started_at', [$request->start, $request->end])
                ->where('user_id', auth()->id()) // Only show user's own shifts
                ->get();

            $events = $shifts->map(function($shift) {
                $colors = [
                    'morning' => '#ffc107',
                    'afternoon' => '#17a2b8',
                    'night' => '#6c757d',
                ];

                return [
                    'id' => $shift->id,
                    'title' => ucfirst($shift->shift_type) . ' Shift',
                    'start' => $shift->started_at->toIso8601String(),
                    'end' => ($shift->ended_at ?? $shift->scheduled_end_at)->toIso8601String(),
                    'color' => $colors[$shift->shift_type] ?? '#007bff',
                    'extendedProps' => [
                        'status' => $shift->status,
                        'ward' => $shift->ward->name ?? 'All',
                        'total_actions' => $shift->total_actions,
                    ],
                ];
            });

            return response()->json($events);
        } catch (\Exception $e) {
            Log::error('Error getting shift calendar: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    /**
     * Get shift statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $userId = auth()->id();
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $shifts = NursingShift::where('user_id', $userId)
                ->whereBetween('started_at', [$startDate, $endDate])
                ->get();

            $totalHours = $shifts->sum(function($shift) {
                return $shift->elapsed_seconds / 3600;
            });

            $byType = $shifts->groupBy('shift_type')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'hours' => round($group->sum(function($s) { return $s->elapsed_seconds / 3600; }), 1),
                ];
            });

            $totalActions = [
                'vitals' => $shifts->sum('vitals_count'),
                'medications' => $shifts->sum('medications_count'),
                'injections' => $shifts->sum('injections_count'),
                'immunizations' => $shifts->sum('immunizations_count'),
                'notes' => $shifts->sum('notes_count'),
                'bills' => $shifts->sum('bills_count'),
            ];

            return response()->json([
                'success' => true,
                'statistics' => [
                    'total_shifts' => $shifts->count(),
                    'total_hours' => round($totalHours, 1),
                    'by_type' => $byType,
                    'total_actions' => $totalActions,
                    'period' => [
                        'start' => Carbon::parse($startDate)->format('M d, Y'),
                        'end' => Carbon::parse($endDate)->format('M d, Y'),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting shift statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading statistics',
            ], 500);
        }
    }

    /**
     * Format duration in seconds to human readable
     */
    private function formatDuration($seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }

        return $minutes . 'm';
    }
}
