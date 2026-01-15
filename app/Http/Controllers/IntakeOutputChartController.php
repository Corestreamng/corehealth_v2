<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IntakeOutputPeriod;
use App\Models\IntakeOutputRecord;
use App\Models\patient;
use Illuminate\Support\Facades\Auth;

class IntakeOutputChartController extends Controller
{
    public function index($patientId, Request $request)
    {
        // Get date filter parameters
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Parse and adjust dates to include full day range
        if ($startDate) {
            $startDate = \Carbon\Carbon::parse($startDate)->startOfDay();
        } else {
            $startDate = now()->subDays(30)->startOfDay();
        }

        if ($endDate) {
            $endDate = \Carbon\Carbon::parse($endDate)->endOfDay();
        } else {
            $endDate = now()->endOfDay();
        }

        // Apply date filtering to fluid periods
        $fluidPeriods = IntakeOutputPeriod::with(['records' => function($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('recorded_at', [$startDate, $endDate]);
                }
            }, 'nurse'])
            ->where('patient_id', $patientId)
            ->where('type', 'fluid')
            ->when($startDate && $endDate, function($query) use ($startDate, $endDate) {
                return $query->where(function($q) use ($startDate, $endDate) {
                    // Include periods that:
                    // 1. Started within the date range
                    $q->whereBetween('started_at', [$startDate, $endDate])
                      // 2. OR ended within the date range
                      ->orWhereBetween('ended_at', [$startDate, $endDate])
                      // 3. OR are still active (not ended) and started before or within the range
                      ->orWhere(function($innerQ) use ($endDate) {
                          $innerQ->whereNull('ended_at')
                                 ->where('started_at', '<=', $endDate);
                      })
                      // 4. OR span the entire date range (started before and ended after)
                      ->orWhere(function($innerQ) use ($startDate, $endDate) {
                          $innerQ->where('started_at', '<=', $startDate)
                                 ->where('ended_at', '>=', $endDate);
                      });
                });
            })
            ->orderBy('started_at', 'desc')
            ->get();

        // Apply date filtering to solid periods
        $solidPeriods = IntakeOutputPeriod::with(['records' => function($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('recorded_at', [$startDate, $endDate]);
                }
            }, 'nurse'])
            ->where('patient_id', $patientId)
            ->where('type', 'solid')
            ->when($startDate && $endDate, function($query) use ($startDate, $endDate) {
                return $query->where(function($q) use ($startDate, $endDate) {
                    // Include periods that:
                    // 1. Started within the date range
                    $q->whereBetween('started_at', [$startDate, $endDate])
                      // 2. OR ended within the date range
                      ->orWhereBetween('ended_at', [$startDate, $endDate])
                      // 3. OR are still active (not ended) and started before or within the range
                      ->orWhere(function($innerQ) use ($endDate) {
                          $innerQ->whereNull('ended_at')
                                 ->where('started_at', '<=', $endDate);
                      })
                      // 4. OR span the entire date range (started before and ended after)
                      ->orWhere(function($innerQ) use ($startDate, $endDate) {
                          $innerQ->where('started_at', '<=', $startDate)
                                 ->where('ended_at', '>=', $endDate);
                      });
                });
            })
            ->orderBy('started_at', 'desc')
            ->get();

        // Get edit duration for determining if records can be deleted
        $editDuration = appsettings('note_edit_duration') ?? 60;
        $cutoffTime = now()->subMinutes($editDuration);
        $currentUserId = Auth::id();

        // Add nurse names and calculate totals for periods and records
        $fluidPeriods->each(function($period) use ($currentUserId, $cutoffTime) {
            $period->nurse_name = $period->nurse_id ? userfullname($period->nurse_id) : 'Unknown';
            $period->total_intake = $period->records->where('type', 'intake')->sum('amount');
            $period->total_output = $period->records->where('type', 'output')->sum('amount');
            $period->records->each(function($record) use ($currentUserId, $cutoffTime) {
                $record->nurse_name = $record->nurse_id ? userfullname($record->nurse_id) : 'Unknown';
                // Check if the current user can delete this record
                $isOwner = $record->nurse_id == $currentUserId;
                $isWithinTime = \Carbon\Carbon::parse($record->created_at)->gte($cutoffTime);
                $record->can_delete = $isOwner && $isWithinTime;
            });
        });

        $solidPeriods->each(function($period) use ($currentUserId, $cutoffTime) {
            $period->nurse_name = $period->nurse_id ? userfullname($period->nurse_id) : 'Unknown';
            $period->total_intake = $period->records->where('type', 'intake')->sum('amount');
            $period->total_output = $period->records->where('type', 'output')->sum('amount');
            $period->records->each(function($record) use ($currentUserId, $cutoffTime) {
                $record->nurse_name = $record->nurse_id ? userfullname($record->nurse_id) : 'Unknown';
                // Check if the current user can delete this record
                $isOwner = $record->nurse_id == $currentUserId;
                $isWithinTime = \Carbon\Carbon::parse($record->created_at)->gte($cutoffTime);
                $record->can_delete = $isOwner && $isWithinTime;
            });
        });

        return response()->json([
            'fluidPeriods' => $fluidPeriods,
            'solidPeriods' => $solidPeriods,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    public function startPeriod(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'type' => 'required|in:fluid,solid',
        ]);
        $period = IntakeOutputPeriod::create([
            'patient_id' => $data['patient_id'],
            'type' => $data['type'],
            'started_at' => now(),
            'nurse_id' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'period' => $period]);
    }

    public function endPeriod(Request $request)
    {
        $data = $request->validate([
            'period_id' => 'required|exists:intake_output_periods,id',
        ]);
        $period = IntakeOutputPeriod::findOrFail($data['period_id']);
        $period->ended_at = now();
        $period->save();
        // Calculate balance
        $intake = $period->records()->where('type', 'intake')->sum('amount');
        $output = $period->records()->where('type', 'output')->sum('amount');
        $balance = $intake - $output;
        return response()->json(['success' => true, 'balance' => $balance]);
    }

    public function storeRecord(Request $request)
    {
        $data = $request->validate([
            'period_id' => 'required|exists:intake_output_periods,id',
            'type' => 'required|in:intake,output',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'recorded_at' => 'required|date',
        ]);
        $nurseId = Auth::id();
        $data['nurse_id'] = $nurseId;

        $record = IntakeOutputRecord::create($data);

        // Add the nurse name to the response
        $record->nurse_name = userfullname($nurseId);

        return response()->json(['success' => true, 'record' => $record]);
    }

    /**
     * Delete an I/O record (only within edit time window and by the creator)
     */
    public function deleteRecord($recordId)
    {
        $record = IntakeOutputRecord::findOrFail($recordId);

        // Check if the current user is the one who created the record
        if ($record->nurse_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own records.'
            ], 403);
        }

        // Get the edit duration from app settings (in minutes)
        $editDuration = appsettings('note_edit_duration') ?? 60;

        // Check if the record is within the edit time window
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        $cutoffTime = now()->subMinutes($editDuration);

        if ($createdAt->lt($cutoffTime)) {
            return response()->json([
                'success' => false,
                'message' => "You can only delete records within {$editDuration} minutes of creation."
            ], 403);
        }

        // Get the period type for the response
        $period = $record->period;
        $periodType = $period ? $period->type : 'fluid';

        // Delete the record
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Record deleted successfully.',
            'period_type' => $periodType
        ]);
    }

    /**
     * Get logs/history for a specific period
     */
    public function periodLogs($patientId, $periodId, Request $request)
    {
        // Get date filter parameters
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Default to last 30 days if no dates provided
        if (!$startDate) {
            $startDate = now()->subDays(30)->startOfDay()->format('Y-m-d');
        }
        if (!$endDate) {
            $endDate = now()->endOfDay()->format('Y-m-d');
        }

        $period = IntakeOutputPeriod::with(['records' => function($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('recorded_at', [$startDate, $endDate]);
                }
            }, 'nurse'])
            ->where('patient_id', $patientId)
            ->findOrFail($periodId);

        // Create history entries
        $history = [];

        // Add period start
        $history[] = [
            'date' => $period->started_at,
            'action' => 'create_period',
            'details' => 'Period started',
            'user' => $period->nurse_id ? userfullname($period->nurse_id) : 'Unknown'
        ];

        // Add each record
        foreach ($period->records as $record) {
            $history[] = [
                'date' => $record->recorded_at,
                'action' => 'add_record_' . $record->type,
                'details' => "Added " . $record->type . " record: " . $record->amount . " " .
                             ($period->type === 'fluid' ? 'ml' : 'g') .
                             ($record->description ? " - " . $record->description : ""),
                'user' => $record->nurse_id ? userfullname($record->nurse_id) : 'Unknown'
            ];
        }

        // Add period end if applicable
        if ($period->ended_at) {
            $history[] = [
                'date' => $period->ended_at,
                'action' => 'end_period',
                'details' => 'Period ended',
                'user' => $period->ended_by ? userfullname($period->ended_by) : 'Unknown'
            ];
        }

        // Sort by date
        usort($history, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return response()->json([
            'success' => true,
            'history' => $history,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }
}
