<?php

namespace App\Interoperability\Http\Controllers\OpenEmr;

use App\Http\Controllers\Controller;
use App\Interoperability\Transformers\Ccda\CcdaClinicalDocumentGenerator;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\VitalSign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OpenEmrCompatibilityController extends Controller
{
    /**
     * OpenEMR patient_data Table Format.
     * GET /apis/default/api/patient
     */
    public function patient(Request $request): JsonResponse
    {
        $limit = min(50, (int) ($request->input('limit', 20)));
        $patients = Patient::with('user')->paginate($limit);

        $data = [];
        foreach ($patients as $p) {
            $user = $p->user;
            $data[] = [
                'pid' => (string) $p->id,
                'pubpid' => $p->file_no ?? (string) $p->id,
                'title' => 'Mr/Ms',
                'fname' => $user->firstname ?? '',
                'mname' => $user->othername ?? '',
                'lname' => $user->surname ?? '',
                'DOB' => $p->dob ? date('Y-m-d', strtotime($p->dob)) : null,
                'sex' => strtolower($p->gender ?? 'm') === 'female' ? 'Female' : 'Male',
                'phone_cell' => $p->phone_no ?? ($user->phone_number ?? ''),
                'email' => $user->email ?? '',
                'street' => $p->address ?? '',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'postal_code' => '100001',
                'country_code' => 'NG',
            ];
        }

        return response()->json([
            'success' => true,
            'total' => $patients->total(),
            'data' => $data,
        ], 200, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /**
     * OpenEMR form_encounter Table Format.
     * GET /apis/default/api/encounter
     */
    public function encounter(Request $request): JsonResponse
    {
        $limit = min(50, (int) ($request->input('limit', 20)));
        $encounters = Encounter::with(['patient.user', 'doctor'])->orderBy('created_at', 'desc')->paginate($limit);

        $data = [];
        foreach ($encounters as $enc) {
            $data[] = [
                'encounter' => (string) $enc->id,
                'pid' => (string) $enc->patient_id,
                'date' => $enc->created_at->format('Y-m-d H:i:s'),
                'reason' => $enc->reasons_for_encounter ?? 'Outpatient Visit',
                'facility' => 'Main Hospital Facility',
                'provider_id' => (string) ($enc->doctor_id ?? 1),
            ];
        }

        return response()->json([
            'success' => true,
            'total' => $encounters->total(),
            'data' => $data,
        ], 200, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /**
     * OpenEMR form_vitals Table Format.
     * GET /apis/default/api/vital_signs
     */
    public function vitals(Request $request): JsonResponse
    {
        $limit = min(50, (int) ($request->input('limit', 20)));
        $vitals = VitalSign::orderBy('created_at', 'desc')->paginate($limit);

        $data = [];
        foreach ($vitals as $v) {
            $systolic = null;
            $diastolic = null;
            if (!empty($v->blood_pressure) && str_contains($v->blood_pressure, '/')) {
                $parts = explode('/', trim($v->blood_pressure));
                $systolic = trim($parts[0]);
                $diastolic = trim($parts[1]);
            }

            $data[] = [
                'id' => (string) $v->id,
                'pid' => (string) $v->patient_id,
                'date' => $v->created_at->format('Y-m-d H:i:s'),
                'bps' => $systolic,
                'bpd' => $diastolic,
                'pulse' => (string) ($v->heart_rate ?? ''),
                'respiration' => (string) ($v->resp_rate ?? ''),
                'temperature' => (string) ($v->temp ?? ''),
                'temp_method' => 'Axillary',
                'weight' => (string) ($v->weight ?? ''),
                'height' => (string) ($v->height ?? ''),
                'BMI' => (string) ($v->bmi ?? ''),
                'oxygen_saturation' => (string) ($v->spo2 ?? ''),
            ];
        }

        return response()->json([
            'success' => true,
            'total' => $vitals->total(),
            'data' => $data,
        ], 200, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /**
     * Export HL7 C-CDA Continuity of Care Document (CCD) XML.
     * GET /apis/default/api/ccda/{patient_id}
     */
    public function ccda(int $patientId): Response
    {
        $patient = Patient::findOrFail($patientId);
        $xml = CcdaClinicalDocumentGenerator::generate($patient);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "inline; filename=\"ccda_patient_{$patientId}.xml\"",
        ]);
    }
}
