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
        $fluidPeriods = IntakeOutputPeriod::with('records')
            ->where('patient_id', $patientId)->where('type', 'fluid')->get();
        $solidPeriods = IntakeOutputPeriod::with('records')
            ->where('patient_id', $patientId)->where('type', 'solid')->get();
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
        $data['nurse_id'] = Auth::id();
        $record = IntakeOutputRecord::create($data);
        return response()->json(['success' => true, 'record' => $record]);
    }
}
