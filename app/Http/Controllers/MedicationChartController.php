<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductOrServiceRequest;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use App\Models\MedicationAdministration;

class MedicationChartController extends Controller
{
    public function index($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $userId = $patient->user_id ?? $patient->user->id ?? null;
        // Get all ProductOrServiceRequest where product_id is not null and not yet charted (no MedicationAdministration exists for it)
        $alreadyChartedIds = MedicationAdministration::where('patient_id', $patientId)
            ->pluck('product_or_service_request_id')->toArray();
        $prescriptions = ProductOrServiceRequest::where('user_id', $userId)
            ->with('product')
            ->whereNotNull('product_id')
            ->whereNotIn('id', $alreadyChartedIds)
            ->latest('created_at')
            ->get();
        $administrations = MedicationAdministration::where('patient_id', $patientId)->get();
        return response()->json(compact('prescriptions', 'administrations'));
    }

    public function storeTiming(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'product_or_service_request_id' => 'required|exists:product_or_service_requests,id',
            'scheduled_time' => 'required|date',
        ]);
        $data['nurse_id'] = Auth::id();
        $admin = MedicationAdministration::create($data);
        return response()->json(['success' => true, 'admin' => $admin]);
    }

    public function administer(Request $request)
    {
        $data = $request->validate([
            'schedule_id' => 'required|exists:medication_administrations,id',
            'administered_time' => 'required|date',
            'route' => 'required|string',
            'note' => 'nullable|string',
        ]);
        $admin = MedicationAdministration::findOrFail($data['schedule_id']);
        $admin->update([
            'administered_time' => $data['administered_time'],
            'route' => $data['route'],
            'note' => $data['note'],
            'nurse_id' => Auth::id(),
        ]);
        return response()->json(['success' => true]);
    }
}
