<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Unified patient search controller used by all workbenches.
 *
 * Prioritises: file_no matches (10), then name matches (10), then phone matches (10).
 * An optional "context" query parameter drives domain‑specific extras like pending_count.
 */
class PatientSearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * GET /patient-search?q=&context=reception|nursing|lab|imaging|billing|pharmacy
     */
    public function search(Request $request)
    {
        $q = trim($request->get('q', $request->get('term', '')));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // ── Use the model's unified spaced partial search with priority ordering ──
        $merged = Patient::with(['user', 'hmo', 'account'])
            ->searchByTerm($q)
            ->limit(20)
            ->get();

        // Also search by lab_number across LabServiceRequests
        $normalizedQ = str_replace(' ', '', $q);
        $strippedQ = str_replace([' ', '-'], '', $q);
        $labMatchingRequests = \App\Models\LabServiceRequest::whereNotNull('lab_number')
            ->where('lab_number', '!=', '')
            ->where(function ($query) use ($q, $normalizedQ, $strippedQ) {
                $query->where('lab_number', 'like', "%{$q}%")
                      ->orWhereRaw("REPLACE(lab_number, ' ', '') LIKE ?", ["%{$normalizedQ}%"])
                      ->orWhereRaw("REPLACE(REPLACE(lab_number, ' ', ''), '-', '') LIKE ?", ["%{$strippedQ}%"]);
            })
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get(['id', 'patient_id', 'lab_number']);

        $labPatientIds = $labMatchingRequests->pluck('patient_id')->unique()->filter()->values()->toArray();

        if (!empty($labPatientIds)) {
            $existingIds = $merged->pluck('id')->toArray();
            $additionalIds = array_diff($labPatientIds, $existingIds);

            if (!empty($additionalIds)) {
                $additionalPatients = Patient::with(['user', 'hmo', 'account'])
                    ->whereIn('id', array_slice($additionalIds, 0, 10))
                    ->get();
                $merged = $merged->merge($additionalPatients);
            }
        }

        $context = $request->get('context', 'reception');

        $results = $merged->map(function ($patient) use ($context, $labMatchingRequests) {
            return $this->mapPatient($patient, $context, $labMatchingRequests);
        });

        return response()->json($results->values());
    }

    // ================================================================
    //  MAPPING
    // ================================================================

    private function mapPatient(Patient $patient, string $context, $labMatchingRequests = null): array
    {
        $user = $patient->user;
        $name = $user
            ? ($user->surname . ' ' . $user->firstname . ($user->othername ? ' ' . $user->othername : ''))
            : userfullname($patient->user_id);

        $matchedReq = $labMatchingRequests ? $labMatchingRequests->firstWhere('patient_id', $patient->id) : null;
        $latestLabNumber = $matchedReq ? $matchedReq->lab_number : null;

        if (!$latestLabNumber && ($context === 'lab' || ($labMatchingRequests && $labMatchingRequests->isNotEmpty()))) {
            $latestLabNumber = \App\Models\LabServiceRequest::where('patient_id', $patient->id)
                ->whereNotNull('lab_number')
                ->where('lab_number', '!=', '')
                ->orderBy('id', 'desc')
                ->value('lab_number');
        }

        $base = [
            'id' => $patient->id,
            'user_id' => $patient->user_id,
            'name' => $name,
            'file_no' => $patient->file_no ?? 'N/A',
            'phone' => $patient->phone_no ?? 'N/A',
            'gender' => $patient->gender ?? 'N/A',
            'dob' => $patient->dob,
            'age' => $this->safeAge($patient->dob),
            'hmo' => optional($patient->hmo)->name ?? 'Private',
            'hmo_id' => $patient->hmo_id,
            'hmo_no' => $patient->hmo_no ?? '',
            'photo' => $patient->user && $patient->user->filename
                            ? asset('storage/image/user/' . $patient->user->filename)
                            : asset('assets/images/default-avatar.png'),
            'balance' => optional($patient->account)->balance ?? 0,
            'latest_lab_number' => $latestLabNumber,
            'matched_by_lab' => $matchedReq !== null,
        ];

        // Domain‑specific extras
        switch ($context) {
            case 'lab':
                $base['pending_count'] = \App\Models\LabServiceRequest::where('patient_id', $patient->id)
                    ->whereIn('status', [1, 2, 3])->count();

                break;

            case 'imaging':
                $base['pending_count'] = \App\Models\ImagingServiceRequest::where('patient_id', $patient->id)
                    ->whereIn('status', [1, 2])->count();

                break;

            case 'billing':
                $base['pending_count'] = \App\Models\ProductOrServiceRequest::where('user_id', $patient->user_id)
                    ->whereNull('payment_id')->whereNull('invoice_id')->count();

                break;

            case 'pharmacy':
                $base['pending_count'] = \App\Models\ProductRequest::where('patient_id', $patient->id)
                    ->whereIn('status', [1, 2])->count();

                break;

            case 'nursing':
                $base['is_admitted'] = \App\Models\AdmissionRequest::where('patient_id', $patient->id)
                    ->where('discharged', 0)->whereNotNull('bed_id')->exists();
                $base['pending_meds'] = \App\Models\MedicationSchedule::where('patient_id', $patient->id)
                    ->whereDate('scheduled_time', Carbon::today())
                    ->whereDoesntHave('administrations', function ($aq) {
                        $aq->whereNull('deleted_at');
                    })->count();
                $base['pending_count'] = $base['pending_meds'];

                break;

            case 'hmo':
                $base['pending_count'] = \App\Models\ProductOrServiceRequest::where('user_id', $patient->user_id)
                    ->whereNotNull('coverage_mode')
                    ->where('validation_status', 'pending')
                    ->where('claims_amount', '>', 0)->count();

                break;

            default: // reception — no pending count needed
                $base['allergies'] = $patient->allergies ?? [];

                break;
        }

        return $base;
    }

    // ================================================================
    //  DATE HELPERS
    // ================================================================

    /**
     * Safely parse a DOB string in various formats and return age, or 'N/A'.
     */
    private function safeAge($dob)
    {
        $date = $this->safeParseDob($dob);

        return $date ? $date->age : 'N/A';
    }

    private function safeParseDob($dob)
    {
        if (empty($dob)) {
            return null;
        }

        if ($dob instanceof Carbon) {
            return $dob;
        }

        // Try d/m/Y first (common legacy format)
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dob)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $dob);
            } catch (\Exception $e) {
            }
        }

        // Try standard Y-m-d
        try {
            return Carbon::parse($dob);
        } catch (\Exception $e) {
        }

        return null;
    }
}
