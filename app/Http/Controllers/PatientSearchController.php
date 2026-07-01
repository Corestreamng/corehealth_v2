<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use Carbon\Carbon;

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

        $context = $request->get('context', 'reception');

        $results = $merged->map(function ($patient) use ($context) {
            return $this->mapPatient($patient, $context);
        });

        return response()->json($results->values());
    }

    // ================================================================
    //  MAPPING
    // ================================================================

    private function mapPatient(patient $patient, string $context): array
    {
        $base = [
            'id'       => $patient->id,
            'user_id'  => $patient->user_id,
            'name'     => userfullname($patient->user_id),
            'file_no'  => $patient->file_no ?? 'N/A',
            'phone'    => $patient->phone_no ?? 'N/A',
            'gender'   => $patient->gender ?? 'N/A',
            'dob'      => $patient->dob,
            'age'      => $this->safeAge($patient->dob),
            'hmo'      => optional($patient->hmo)->name ?? 'Private',
            'hmo_id'   => $patient->hmo_id,
            'hmo_no'   => $patient->hmo_no ?? '',
            'photo'    => $patient->user && $patient->user->filename
                            ? asset('storage/image/user/' . $patient->user->filename)
                            : asset('assets/images/default-avatar.png'),
            'balance'  => optional($patient->account)->balance ?? 0,
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
            try { return Carbon::createFromFormat('d/m/Y', $dob); } catch (\Exception $e) {}
        }

        // Try standard Y-m-d
        try { return Carbon::parse($dob); } catch (\Exception $e) {}

        return null;
    }
}
