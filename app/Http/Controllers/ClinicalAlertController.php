<?php

namespace App\Http\Controllers;

use App\Models\ClinicalAlert;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClinicalAlertController extends Controller
{
    /**
     * Get all active alerts for a patient.
     * Optionally filters based on the current user's role (handled on frontend, but we can do it here too).
     */
    public function index($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $alerts = ClinicalAlert::with('creator.user')
            ->where('patient_id', $patientId)
            ->where('is_active', true)
            ->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $alerts
        ]);
    }

    /**
     * Store a newly created alert in storage.
     */
    public function store(Request $request, $patientId)
    {
        $request->validate([
            'alert_text' => 'required|string',
            'severity' => 'required|in:low,medium,high',
            'visibility' => 'nullable|array',
        ]);

        $patient = Patient::findOrFail($patientId);

        $alert = ClinicalAlert::create([
            'patient_id' => $patient->id,
            'created_by' => Auth::user()->staff->id,
            'alert_text' => $request->alert_text,
            'severity' => $request->severity,
            'visibility' => $request->visibility ?? [],
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Clinical alert created successfully.',
            'data' => $alert->load('creator.user')
        ]);
    }

    /**
     * Update the specified alert in storage.
     */
    public function update(Request $request, $patientId, $alertId)
    {
        $request->validate([
            'alert_text' => 'required|string',
            'severity' => 'required|in:low,medium,high',
            'visibility' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $alert = ClinicalAlert::where('patient_id', $patientId)->findOrFail($alertId);
        
        $user = Auth::user();
        $isCreator = $user->staff && $user->staff->id == $alert->created_by;
        $isAdmin = $user->hasRole(['SUPERADMIN', 'ADMIN']);

        if (!$isCreator && !$isAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to edit this alert.'
            ], 403);
        }

        $alert->update([
            'alert_text' => $request->alert_text,
            'severity' => $request->severity,
            'visibility' => $request->visibility ?? [],
            'is_active' => $request->input('is_active', $alert->is_active),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Clinical alert updated successfully.',
            'data' => $alert->load('creator.user')
        ]);
    }

    /**
     * Remove the specified alert from storage (soft delete by setting is_active = false)
     */
    public function destroy($patientId, $alertId)
    {
        $alert = ClinicalAlert::where('patient_id', $patientId)->findOrFail($alertId);
        
        $user = Auth::user();
        $isCreator = $user->staff && $user->staff->id == $alert->created_by;
        $isAdmin = $user->hasRole(['SUPERADMIN', 'ADMIN']);

        if (!$isCreator && !$isAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this alert.'
            ], 403);
        }

        $alert->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Clinical alert deactivated successfully.'
        ]);
    }
}
