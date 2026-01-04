<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use App\Models\Vital;
use App\Models\Encounter;
use App\Models\ProductRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LabWorkbenchController extends Controller
{
    /**
     * Display the lab workbench main page
     */
    public function index()
    {
        // Check permission
        if (!Auth::user()->can('see-investigations')) {
            abort(403, 'Unauthorized access to Lab Workbench');
        }

        return view('admin.lab.workbench');
    }

    /**
     * Search for patients (same logic as receptionist lookup)
     */
    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');
        
        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $patients = Patient::with('user')
            ->whereHas('user', function ($query) use ($term) {
                $query->where('surname', 'like', "%{$term}%")
                    ->orWhere('firstname', 'like', "%{$term}%")
                    ->orWhere('othername', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })
            ->orWhere('file_no', 'like', "%{$term}%")
            ->limit(10)
            ->get();

        $results = $patients->map(function ($patient) {
            $pendingCount = ProductOrServiceRequest::where('patient_id', $patient->id)
                ->whereIn('status', ['requested', 'billed', 'sample_taken'])
                ->where('service_type', 'investigation')
                ->count();

            return [
                'id' => $patient->id,
                'name' => $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername,
                'file_no' => $patient->file_no,
                'age' => $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'phone' => $patient->user->phone ?? 'N/A',
                'photo' => $patient->user->photo ?? 'avatar.png',
                'pending_count' => $pendingCount,
            ];
        });

        return response()->json($results);
    }

    /**
     * Get queue counts (billing, sample, results)
     */
    public function getQueueCounts()
    {
        $billingCount = ProductOrServiceRequest::where('status', 'requested')
            ->where('service_type', 'investigation')
            ->count();

        $sampleCount = ProductOrServiceRequest::where('status', 'billed')
            ->where('service_type', 'investigation')
            ->count();

        $resultCount = ProductOrServiceRequest::where('status', 'sample_taken')
            ->where('service_type', 'investigation')
            ->count();

        return response()->json([
            'billing' => $billingCount,
            'sample' => $sampleCount,
            'results' => $resultCount,
            'total' => $billingCount + $sampleCount + $resultCount,
        ]);
    }

    /**
     * Get patient's pending requests
     */
    public function getPatientRequests($patientId)
    {
        $patient = Patient::with('user')->findOrFail($patientId);

        // Get all pending investigation requests
        $requests = ProductOrServiceRequest::with(['service', 'requested_by', 'billed_by', 'sample_by', 'results_by'])
            ->where('patient_id', $patientId)
            ->where('service_type', 'investigation')
            ->whereIn('status', ['requested', 'billed', 'sample_taken'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group by status
        $billing = $requests->where('status', 'requested')->values();
        $sample = $requests->where('status', 'billed')->values();
        $results = $requests->where('status', 'sample_taken')->values();

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->user->surname . ' ' . $patient->user->firstname,
                'file_no' => $patient->file_no,
                'age' => $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'blood_type' => $patient->blood_type ?? 'N/A',
                'phone' => $patient->user->phone ?? 'N/A',
            ],
            'requests' => [
                'billing' => $billing,
                'sample' => $sample,
                'results' => $results,
            ],
        ]);
    }

    /**
     * Get patient's recent vitals
     */
    public function getPatientVitals($patientId, Request $request)
    {
        $limit = $request->get('limit', 10);

        $vitals = Vital::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($vitals);
    }

    /**
     * Get patient's recent doctor notes
     */
    public function getPatientNotes($patientId, Request $request)
    {
        $limit = $request->get('limit', 10);

        $encounters = Encounter::with(['doctor'])
            ->where('patient_id', $patientId)
            ->whereNotNull('notes')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $notes = $encounters->map(function ($encounter) {
            return [
                'id' => $encounter->id,
                'date' => $encounter->created_at->format('M d, Y - h:i A'),
                'doctor' => $encounter->doctor ? $encounter->doctor->firstname . ' ' . $encounter->doctor->surname : 'N/A',
                'diagnosis' => $encounter->diagnosis ?? 'N/A',
                'notes' => $encounter->notes,
                'notes_preview' => \Illuminate\Support\Str::limit(strip_tags($encounter->notes), 150),
            ];
        });

        return response()->json($notes);
    }

    /**
     * Get patient's recent medications
     */
    public function getPatientMedications($patientId, Request $request)
    {
        $limit = $request->get('limit', 20);
        $status = $request->get('status', 'all'); // active, all, stopped

        $query = ProductRequest::with(['product', 'encounter.doctor'])
            ->where('patient_id', $patientId)
            ->where('request_type', 'prescription')
            ->orderBy('created_at', 'desc');

        if ($status === 'active') {
            $query->whereNull('stopped_at');
        } elseif ($status === 'stopped') {
            $query->whereNotNull('stopped_at');
        }

        $medications = $query->limit($limit)->get();

        $result = $medications->map(function ($med) {
            return [
                'id' => $med->id,
                'drug_name' => $med->product ? $med->product->product_name : 'N/A',
                'dosage' => $med->dose ?? 'N/A',
                'frequency' => $med->frequency ?? 'N/A',
                'status' => $med->stopped_at ? 'stopped' : 'active',
                'started' => $med->created_at->format('M d, Y'),
                'stopped' => $med->stopped_at ? \Carbon\Carbon::parse($med->stopped_at)->format('M d, Y') : null,
                'doctor' => $med->encounter && $med->encounter->doctor ? $med->encounter->doctor->firstname . ' ' . $med->encounter->doctor->surname : 'N/A',
            ];
        });

        return response()->json($result);
    }

    /**
     * Get patient's clinical context (all 3 panels)
     */
    public function getClinicalContext($patientId)
    {
        return response()->json([
            'vitals' => $this->getPatientVitals($patientId, new Request(['limit' => 10]))->getData(),
            'notes' => $this->getPatientNotes($patientId, new Request(['limit' => 10]))->getData(),
            'medications' => $this->getPatientMedications($patientId, new Request(['limit' => 20]))->getData(),
        ]);
    }
}
