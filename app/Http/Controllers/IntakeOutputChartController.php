<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IntakeOutputPeriod;
use App\Models\IntakeOutputRecord;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;

class IntakeOutputChartController extends Controller
{
    public function index($patientId)
    {
        $fluidPeriods = IntakeOutputPeriod::with(['records', 'nurse'])
            ->where('patient_id', $patientId)->where('type', 'fluid')->get();
        $solidPeriods = IntakeOutputPeriod::with(['records', 'nurse'])
            ->where('patient_id', $patientId)->where('type', 'solid')->get();

        // Add nurse names to periods and records
        $fluidPeriods->each(function($period) {
            $period->nurse_name = $period->nurse_id ? userfullname($period->nurse_id) : 'Unknown';
            $period->records->each(function($record) {
                $record->nurse_name = $record->nurse_id ? userfullname($record->nurse_id) : 'Unknown';
            });
        });

        $solidPeriods->each(function($period) {
            $period->nurse_name = $period->nurse_id ? userfullname($period->nurse_id) : 'Unknown';
            $period->records->each(function($record) {
                $record->nurse_name = $record->nurse_id ? userfullname($record->nurse_id) : 'Unknown';
            });
        });

        return response()->json(compact('fluidPeriods', 'solidPeriods'));
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
     * Get logs/history for a specific period
     */
    public function periodLogs($patientId, $periodId)
    {
        $period = IntakeOutputPeriod::with(['records', 'nurse'])
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
            'history' => $history
        ]);
    }
}
