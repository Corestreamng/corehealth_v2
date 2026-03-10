<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\patient as PatientLowerCase;
use App\Models\Product;
use App\Models\service;
use App\Models\VitalSign;
use App\Models\NursingNote;
use App\Models\NursingNoteType;
use App\Models\ImmunizationRecord;
use App\Models\User;
use App\Models\MaternityEnrollment;
use App\Models\MaternityMedicalHistory;
use App\Models\MaternityPreviousPregnancy;
use App\Models\AncVisit;
use App\Models\AncInvestigation;
use App\Models\DeliveryRecord;
use App\Models\DeliveryPartograph;
use App\Models\MaternityBaby;
use App\Models\ChildGrowthRecord;
use App\Models\PostnatalVisit;
use App\Models\WhoGrowthStandard;
use App\Models\TreatmentPlan;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\ProductRequest;
use App\Models\Procedure;
use App\Models\Store;
use App\Models\PatientImmunizationSchedule;
use App\Models\VaccineScheduleTemplate;
use App\Models\VaccineScheduleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\Str;
use App\Http\Traits\ClinicalOrdersTrait;

class MaternityWorkbenchController extends Controller
{
    use ClinicalOrdersTrait;

    /* ══════════════════════════════════════════════════════════════
       WORKBENCH PAGE
       ══════════════════════════════════════════════════════════════ */

    public function index()
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'MATERNITY'])) {
            abort(403, 'You do not have access to the Maternity Workbench.');
        }
        $stores = Store::orderBy('store_name')->get(['id', 'store_name']);
        return view('admin.maternity.workbench', compact('stores'));
    }

    private function nursingProxy(): NursingWorkbenchController
    {
        return app(NursingWorkbenchController::class);
    }

    /* ══════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════ */

    private function safeParseDate($dateString)
    {
        if (empty($dateString)) return null;
        if ($dateString instanceof Carbon) return $dateString;

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateString);
                if ($date && $date->format($format) === $dateString) return $date;
            } catch (\Exception $e) { continue; }
        }
        try { return Carbon::parse($dateString); } catch (\Exception $e) { return null; }
    }

    private function calculateAge($dob)
    {
        $date = $this->safeParseDate($dob);
        return $date ? $date->age : 'N/A';
    }

    private function formatAge($dob)
    {
        $date = $this->safeParseDate($dob);
        if (!$date) return 'N/A';
        $now = Carbon::now();
        $years = $date->diffInYears($now);
        $months = $date->copy()->addYears($years)->diffInMonths($now);
        $days = $date->copy()->addYears($years)->addMonths($months)->diffInDays($now);
        $parts = [];
        if ($years > 0) $parts[] = $years . 'y';
        if ($months > 0) $parts[] = $months . 'm';
        if ($days > 0) $parts[] = $days . 'd';
        return !empty($parts) ? implode(' ', $parts) : '0d';
    }

    /* ══════════════════════════════════════════════════════════════
       PATIENT SEARCH
       ══════════════════════════════════════════════════════════════ */

    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');
        if (strlen($term) < 2) return response()->json([]);

        $patients = PatientLowerCase::with(['user', 'hmo'])
            ->where(function ($query) use ($term) {
                $query->whereHas('user', function ($uq) use ($term) {
                    $uq->where('surname', 'like', "%{$term}%")
                       ->orWhere('firstname', 'like', "%{$term}%")
                       ->orWhere('othername', 'like', "%{$term}%");
                })
                ->orWhere('file_no', 'like', "%{$term}%")
                ->orWhere('phone_no', 'like', "%{$term}%");
            })
            ->where('gender', 'female')
            ->limit(15)
            ->get();

        $results = $patients->map(function ($p) {
            $enrollment = MaternityEnrollment::where('patient_id', $p->id)
                ->whereIn('status', ['active', 'postnatal'])
                ->first();

            return [
                'id'            => $p->id,
                'user_id'       => $p->user_id,
                'name'          => userfullname($p->user_id),
                'file_no'       => $p->file_no,
                'age'           => $this->calculateAge($p->dob),
                'gender'        => $p->gender ?? 'N/A',
                'phone'         => $p->phone_no ?? 'N/A',
                'photo'         => $p->user->photo ?? 'avatar.png',
                'hmo'           => $p->hmo ? $p->hmo->name : null,
                'has_enrollment'=> $enrollment ? true : false,
                'enrollment_id' => $enrollment ? $enrollment->id : null,
                'enrollment_status' => $enrollment ? $enrollment->status : null,
                'edd'           => $enrollment && $enrollment->edd ? $enrollment->edd->format('d M Y') : null,
            ];
        });

        return response()->json($results);
    }

    public function getPatientDetails($id)
    {
        $patient = PatientLowerCase::with(['user', 'hmo'])->findOrFail($id);

        $enrollment = MaternityEnrollment::where('patient_id', $id)
            ->whereIn('status', ['active', 'postnatal'])
            ->with(['ancVisits', 'deliveryRecord', 'babies', 'postnatalVisits'])
            ->first();

        $lastVitals = VitalSign::where('patient_id', $id)
            ->orderBy('created_at', 'desc')->first();

        return response()->json([
            'id'           => $patient->id,
            'user_id'      => $patient->user_id,
            'name'         => userfullname($patient->user_id),
            'file_no'      => $patient->file_no,
            'age'          => $this->formatAge($patient->dob),
            'dob'          => $this->safeParseDate($patient->dob) ? $this->safeParseDate($patient->dob)->format('d M Y') : 'N/A',
            'gender'       => $patient->gender ?? 'N/A',
            'blood_group'  => $patient->blood_group ?? 'N/A',
            'genotype'     => $patient->genotype ?? 'N/A',
            'phone'        => $patient->phone_no ?? 'N/A',
            'address'      => $patient->address ?? 'N/A',
            'photo'        => $patient->user->photo ?? 'avatar.png',
            'hmo'          => $patient->hmo ? $patient->hmo->name : 'N/A',
            'hmo_no'       => $patient->hmo_no ?? 'N/A',
            'allergies'    => $patient->allergies ?? [],
            'enrollment'   => $enrollment ? [
                'id'                => $enrollment->id,
                'status'            => $enrollment->status,
                'entry_point'       => $enrollment->entry_point,
                'booking_date'      => $enrollment->booking_date ? $enrollment->booking_date->format('d M Y') : null,
                'lmp'               => $enrollment->lmp ? $enrollment->lmp->format('d M Y') : null,
                'edd'               => $enrollment->edd ? $enrollment->edd->format('d M Y') : null,
                'gestational_age'   => $enrollment->getCurrentGestationalAge(),
                'gravida'           => $enrollment->gravida,
                'parity'            => $enrollment->parity,
                'risk_level'        => $enrollment->risk_level,
                'risk_factors'      => $enrollment->risk_factors,
                'blood_group'       => $enrollment->blood_group,
                'genotype'          => $enrollment->genotype,
                'booking_weight_kg' => $enrollment->booking_weight_kg,
                'booking_bp'        => $enrollment->booking_bp,
                'height_cm'         => $enrollment->height_cm,
                'anc_visit_count'   => $enrollment->ancVisits->count(),
                'has_delivery'      => $enrollment->deliveryRecord ? true : false,
                'delivery_date'     => $enrollment->deliveryRecord ? $enrollment->deliveryRecord->delivery_date->format('d M Y') : null,
                'baby_count'        => $enrollment->babies->count(),
                'postnatal_visit_count' => $enrollment->postnatalVisits->count(),
                'remaining_days'    => $enrollment->getRemainingDays(),
                'completed_at'      => $enrollment->completed_at ? $enrollment->completed_at->format('d M Y H:i') : null,
                'outcome_summary'   => $enrollment->outcome_summary,
            ] : null,
            'last_vitals'  => $lastVitals ? [
                'bp'        => $lastVitals->blood_pressure ?? 'N/A',
                'temp'      => $lastVitals->temp ?? 'N/A',
                'heart_rate'=> $lastVitals->heart_rate ?? 'N/A',
                'resp_rate' => $lastVitals->resp_rate ?? 'N/A',
                'weight'    => $lastVitals->weight ? (float)$lastVitals->weight : null,
                'spo2'      => $lastVitals->spo2 ? (float)$lastVitals->spo2 : null,
                'time'      => Carbon::parse($lastVitals->created_at)->format('h:i a, d M'),
            ] : null,
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       ENROLLMENT
       ══════════════════════════════════════════════════════════════ */

    public function enrollPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id'            => 'required|exists:patients,id',
            'entry_point'           => 'required|in:anc,delivery,postnatal',
            'lmp'                   => 'required|date',
            'edd'                   => 'nullable|date',
            'gravida'               => 'required|integer|min:1',
            'parity'                => 'nullable|integer|min:0',
            'alive'                 => 'nullable|integer|min:0',
            'abortion_miscarriage'  => 'nullable|integer|min:0',
            'blood_group'           => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'genotype'              => 'nullable|in:AA,AS,SS,AC,SC,CC,Others',
            'height_cm'             => 'nullable|numeric|min:50|max:250',
            'booking_weight_kg'     => 'nullable|numeric|min:20|max:300',
            'booking_bp'            => 'nullable|string|max:20',
            'risk_level'            => 'nullable|in:low,moderate,high,very_high',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check for existing active enrollment
        $existing = MaternityEnrollment::where('patient_id', $request->patient_id)
            ->whereIn('status', ['active', 'postnatal'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Patient already has an active maternity enrollment.',
                'enrollment_id' => $existing->id,
            ], 422);
        }

        try {
            DB::beginTransaction();

            $lmp = $this->safeParseDate($request->lmp);
            $edd = $this->safeParseDate($request->edd);

            // Auto-calculate EDD from LMP if not provided
            if ($lmp && !$edd) {
                $edd = $lmp->copy()->addDays(280);
            }

            // Determine initial status based on entry point
            $status = 'active';
            if ($request->entry_point === 'delivery') {
                $status = 'postnatal';
            } elseif ($request->entry_point === 'postnatal') {
                $status = 'postnatal';
            }

            $enrollment = MaternityEnrollment::create([
                'patient_id'              => $request->patient_id,
                'enrolled_by'             => Auth::id(),
                'entry_point'             => $request->entry_point,
                'status'                  => $status,
                'enrollment_date'         => Carbon::today(),
                'booking_date'            => Carbon::today(),
                'lmp'                     => $lmp,
                'edd'                     => $edd,
                'gestational_age_at_booking' => $lmp ? $lmp->diffInWeeks(Carbon::today()) : null,
                'gravida'                 => $request->gravida,
                'parity'                  => $request->parity ?? 0,
                'alive'                   => $request->alive ?? 0,
                'abortion_miscarriage'    => $request->abortion_miscarriage ?? 0,
                'blood_group'             => $request->blood_group,
                'genotype'                => $request->genotype,
                'height_cm'               => $request->height_cm,
                'booking_weight_kg'       => $request->booking_weight_kg,
                'booking_bmi'             => ($request->booking_weight_kg && $request->height_cm)
                    ? round($request->booking_weight_kg / (($request->height_cm / 100) ** 2), 1) : null,
                'booking_bp'              => $request->booking_bp,
                'risk_level'              => $request->risk_level ?? 'low',
                'risk_factors'            => $request->risk_factors,
                'birth_plan_notes'        => $request->birth_plan_notes,
                'preferred_delivery_place'=> $request->preferred_delivery_place,
            ]);

            // Save medical history items if provided
            if ($request->has('medical_history') && is_array($request->medical_history)) {
                foreach ($request->medical_history as $item) {
                    MaternityMedicalHistory::create([
                        'enrollment_id' => $enrollment->id,
                        'category'      => $item['category'] ?? 'medical',
                        'description'   => $item['description'] ?? '',
                        'year'          => $item['year'] ?? null,
                        'notes'         => $item['notes'] ?? null,
                    ]);
                }
            }

            // Save previous pregnancies if provided
            if ($request->has('previous_pregnancies') && is_array($request->previous_pregnancies)) {
                foreach ($request->previous_pregnancies as $pp) {
                    $babyAlive = filter_var($pp['baby_alive'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $babyDead = filter_var($pp['baby_dead'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $babyStillbirth = filter_var($pp['baby_stillbirth'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    MaternityPreviousPregnancy::create([
                        'enrollment_id'    => $enrollment->id,
                        'year'             => $pp['year'] ?? null,
                        'place_of_delivery'=> $pp['place_of_delivery'] ?? null,
                        'duration_weeks'   => $pp['duration_weeks'] ?? null,
                        'complications'    => $pp['complications'] ?? null,
                        'type_of_labour'   => $pp['type_of_labour'] ?? null,
                        'baby_alive'       => $babyAlive ? 1 : 0,
                        'baby_dead'        => $babyDead ? 1 : 0,
                        'baby_stillbirth'  => $babyStillbirth ? 1 : 0,
                        'baby_sex'         => $pp['baby_sex'] ?? null,
                        'birth_weight_kg'  => $pp['birth_weight_kg'] ?? null,
                        'present_health'   => $pp['present_health'] ?? null,
                        'notes'            => $pp['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success'       => true,
                'message'       => 'Patient enrolled in maternity care successfully.',
                'enrollment_id' => $enrollment->id,
                'enrollment'    => $enrollment->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error enrolling patient: ' . $e->getMessage()], 500);
        }
    }

    public function getEnrollment($id)
    {
        $enrollment = MaternityEnrollment::with([
            'patient.user', 'enrolledBy', 'medicalHistory',
            'previousPregnancies', 'ancVisits.seenBy',
            'deliveryRecord.deliveredBy', 'babies.patient.user',
            'postnatalVisits.seenBy',
        ])->findOrFail($id);

        return response()->json([
            'success'    => true,
            'enrollment' => $enrollment,
            'gestational_age' => $enrollment->getCurrentGestationalAge(),
            'remaining_days'  => $enrollment->getRemainingDays(),
        ]);
    }

    public function updateEnrollment(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $fillable = [
            'lmp', 'edd', 'gravida', 'parity', 'alive', 'abortion_miscarriage',
            'blood_group', 'genotype', 'height_cm', 'booking_weight_kg', 'booking_bp',
            'risk_level', 'risk_factors', 'birth_plan_notes', 'preferred_delivery_place',
            'status', 'outcome_summary',
        ];

        $data = $request->only($fillable);

        if (isset($data['lmp'])) $data['lmp'] = $this->safeParseDate($data['lmp']);
        if (isset($data['edd'])) $data['edd'] = $this->safeParseDate($data['edd']);

        // Recalculate BMI if weight or height changed
        $weight = $data['booking_weight_kg'] ?? $enrollment->booking_weight_kg;
        $height = $data['height_cm'] ?? $enrollment->height_cm;
        if ($weight && $height) {
            $data['booking_bmi'] = round($weight / (($height / 100) ** 2), 1);
        }

        if (isset($data['status']) && $data['status'] === 'completed') {
            $data['completed_at'] = Carbon::now();
        }

        $enrollment->update($data);

        return response()->json([
            'success'    => true,
            'message'    => 'Enrollment updated successfully.',
            'enrollment' => $enrollment->fresh(),
        ]);
    }

    /**
     * Discharge a maternity enrollment with validation and warnings.
     */
    public function dischargeEnrollment(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::with(['deliveryRecord', 'babies', 'postnatalVisits'])
            ->findOrFail($id);

        // Cannot discharge if already completed/transferred/deceased
        if (in_array($enrollment->status, ['completed', 'transferred', 'deceased'])) {
            return response()->json([
                'success' => false,
                'message' => 'This enrollment is already ' . $enrollment->status . ' and cannot be discharged.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'outcome_summary' => 'required|string|min:5',
            'confirm'         => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Build warnings based on current state
        $warnings = [];
        if ($enrollment->status === 'active' && !$enrollment->deliveryRecord) {
            $warnings[] = 'Patient has NOT delivered yet. Discharging now will close the enrollment without a delivery record.';
        }
        if ($enrollment->babies->count() === 0 && $enrollment->deliveryRecord) {
            $warnings[] = 'No baby records have been registered for this delivery.';
        }
        if ($enrollment->postnatalVisits->count() === 0 && $enrollment->deliveryRecord) {
            $warnings[] = 'No postnatal visits have been recorded.';
        }

        // If not confirmed yet, return warnings for user review
        if (!$request->confirm) {
            return response()->json([
                'success'  => false,
                'confirm'  => true,
                'warnings' => $warnings,
                'message'  => 'Please review the warnings and confirm discharge.',
            ]);
        }

        try {
            $enrollment->update([
                'status'          => 'completed',
                'completed_at'    => Carbon::now(),
                'outcome_summary' => $request->outcome_summary,
            ]);

            return response()->json([
                'success'    => true,
                'message'    => 'Patient has been discharged from maternity care.',
                'enrollment' => $enrollment->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getTimeline($id)
    {
        $enrollment = MaternityEnrollment::with([
            'ancVisits', 'deliveryRecord', 'postnatalVisits', 'babies',
        ])->findOrFail($id);

        $timeline = [];

        // Booking
        $timeline[] = [
            'date'  => $enrollment->booking_date ? $enrollment->booking_date->format('d M Y') : null,
            'type'  => 'booking',
            'title' => 'Maternity Enrollment',
            'detail'=> 'Entry: ' . ucfirst($enrollment->entry_point) . ' | G' . $enrollment->gravida . 'P' . $enrollment->parity,
            'icon'  => 'mdi-clipboard-plus',
            'color' => 'primary',
        ];

        // ANC visits
        foreach ($enrollment->ancVisits as $visit) {
            $timeline[] = [
                'date'  => $visit->visit_date ? $visit->visit_date->format('d M Y') : null,
                'type'  => 'anc_visit',
                'title' => 'ANC Visit #' . $visit->visit_number,
                'detail'=> 'GA: ' . $visit->getGestationalAge() . ' | BP: ' . $visit->getBloodPressure() . ' | FHR: ' . ($visit->fetal_heart_rate ?? 'N/A'),
                'icon'  => 'mdi-stethoscope',
                'color' => 'info',
                'id'    => $visit->id,
            ];
        }

        // Delivery
        if ($enrollment->deliveryRecord) {
            $dr = $enrollment->deliveryRecord;
            $timeline[] = [
                'date'  => $dr->delivery_date ? $dr->delivery_date->format('d M Y') : null,
                'type'  => 'delivery',
                'title' => 'Delivery - ' . strtoupper($dr->type_of_delivery ?? 'N/A'),
                'detail'=> $dr->number_of_babies . ' baby(ies) | Blood loss: ' . ($dr->blood_loss_ml ?? 'N/A') . 'ml',
                'icon'  => 'mdi-baby-carriage',
                'color' => 'success',
                'id'    => $dr->id,
            ];
        }

        // Postnatal visits
        foreach ($enrollment->postnatalVisits as $pnv) {
            $timeline[] = [
                'date'  => $pnv->visit_date ? $pnv->visit_date->format('d M Y') : null,
                'type'  => 'postnatal',
                'title' => 'Postnatal Visit (' . str_replace('_', ' ', $pnv->visit_type) . ')',
                'detail'=> 'Mother: ' . ($pnv->general_condition ?? 'N/A') . ' | Baby wt: ' . ($pnv->baby_weight_kg ?? 'N/A') . 'kg',
                'icon'  => 'mdi-mother-nurse',
                'color' => 'warning',
                'id'    => $pnv->id,
            ];
        }

        // Sort by date
        usort($timeline, function ($a, $b) {
            return strtotime($a['date'] ?? '1970-01-01') - strtotime($b['date'] ?? '1970-01-01');
        });

        return response()->json(['success' => true, 'timeline' => $timeline]);
    }

    public function printAncCard($id)
    {
        $enrollment = MaternityEnrollment::with([
            'patient.user',
            'medicalHistory',
            'previousPregnancies',
            'ancVisits.seenBy',
            'ancInvestigations',
            'deliveryRecord.deliveredBy',
            'postnatalVisits.seenBy',
            'babies.patient.user',
        ])->findOrFail($id);

        $mother = $enrollment->patient;
        $motherUser = $mother ? $mother->user : null;

        return view('admin.maternity.print.anc_card', [
            'enrollment' => $enrollment,
            'mother' => $mother,
            'motherUser' => $motherUser,
            'ancVisits' => $enrollment->ancVisits->sortBy('visit_date')->values(),
            'prevPregnancies' => $enrollment->previousPregnancies->sortBy('year')->values(),
            'medicalHistory' => $enrollment->medicalHistory,
            'delivery' => $enrollment->deliveryRecord,
            'postnatal' => $enrollment->postnatalVisits->sortBy('visit_date')->values(),
            'investigations' => $enrollment->ancInvestigations,
        ]);
    }

    public function printRoadHealthCard($id)
    {
        $enrollment = MaternityEnrollment::with([
            'patient.user',
            'deliveryRecord',
            'ancVisits',
            'babies.patient.user',
        ])->findOrFail($id);

        $mother = $enrollment->patient;
        $motherUser = $mother ? $mother->user : null;

        $babies = $enrollment->babies->map(function ($baby) {
            $babyPatient = $baby->patient;
            $babyUser = $babyPatient ? $babyPatient->user : null;

            $immunizations = ImmunizationRecord::where('patient_id', $baby->patient_id)
                ->orderBy('administered_at')
                ->get();

            $growth = ChildGrowthRecord::where('baby_id', $baby->id)
                ->orderBy('age_months')
                ->get();

            // WHO Weight-for-Age reference data for growth curves
            $sex = strtoupper(substr($baby->sex ?? 'M', 0, 1));
            $whoWfa = WhoGrowthStandard::getChartData('wfa', $sex);

            return [
                'model' => $baby,
                'patient' => $babyPatient,
                'user' => $babyUser,
                'immunizations' => $immunizations,
                'growth' => $growth,
                'whoWfa' => $whoWfa,
            ];
        });

        return view('admin.maternity.print.road_health_card', [
            'enrollment' => $enrollment,
            'mother' => $mother,
            'motherUser' => $motherUser,
            'delivery' => $enrollment->deliveryRecord,
            'babies' => $babies,
        ]);
    }

    public function getAuditTrail($id)
    {
        $enrollment = MaternityEnrollment::with(['patient.user'])->findOrFail($id);

        $auditableMap = [
            MaternityEnrollment::class => ['label' => 'Enrollment', 'ids' => [$enrollment->id]],
            MaternityMedicalHistory::class => ['label' => 'Medical History', 'ids' => MaternityMedicalHistory::where('enrollment_id', $enrollment->id)->pluck('id')->all()],
            MaternityPreviousPregnancy::class => ['label' => 'Previous Pregnancy', 'ids' => MaternityPreviousPregnancy::where('enrollment_id', $enrollment->id)->pluck('id')->all()],
            AncVisit::class => ['label' => 'ANC Visit', 'ids' => AncVisit::where('enrollment_id', $enrollment->id)->pluck('id')->all()],
            AncInvestigation::class => ['label' => 'Investigation', 'ids' => AncInvestigation::where('enrollment_id', $enrollment->id)->pluck('id')->all()],
            DeliveryRecord::class => ['label' => 'Delivery', 'ids' => DeliveryRecord::where('enrollment_id', $enrollment->id)->pluck('id')->all()],
            DeliveryPartograph::class => ['label' => 'Partograph', 'ids' => DeliveryPartograph::whereIn('delivery_record_id', DeliveryRecord::where('enrollment_id', $enrollment->id)->pluck('id'))->pluck('id')->all()],
            MaternityBaby::class => ['label' => 'Baby Record', 'ids' => MaternityBaby::where('enrollment_id', $enrollment->id)->pluck('id')->all()],
            ChildGrowthRecord::class => ['label' => 'Growth Record', 'ids' => ChildGrowthRecord::whereIn('baby_id', MaternityBaby::where('enrollment_id', $enrollment->id)->pluck('id'))->pluck('id')->all()],
            PostnatalVisit::class => ['label' => 'Postnatal Visit', 'ids' => PostnatalVisit::where('enrollment_id', $enrollment->id)->pluck('id')->all()],
        ];

        $query = Audit::query()->with('user');

        $query->where(function ($outer) use ($auditableMap) {
            foreach ($auditableMap as $auditableType => $meta) {
                if (empty($meta['ids'])) {
                    continue;
                }

                $outer->orWhere(function ($inner) use ($auditableType, $meta) {
                    $inner->where('auditable_type', $auditableType)
                        ->whereIn('auditable_id', $meta['ids']);
                });
            }
        });

        $audits = $query->orderByDesc('created_at')->limit(500)->get()->map(function ($audit) use ($auditableMap) {
            $typeLabel = $auditableMap[$audit->auditable_type]['label'] ?? class_basename($audit->auditable_type);

            return [
                'id' => $audit->id,
                'event' => $audit->event,
                'module' => $typeLabel,
                'auditable_type' => class_basename($audit->auditable_type),
                'auditable_id' => $audit->auditable_id,
                'user' => $audit->user ? userfullname($audit->user_id) : 'System',
                'old_values' => $audit->old_values,
                'new_values' => $audit->new_values,
                'url' => $audit->url,
                'ip_address' => $audit->ip_address,
                'created_at' => $audit->created_at ? $audit->created_at->format('Y-m-d H:i:s') : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'enrollment_id' => $enrollment->id,
            'patient' => [
                'id' => $enrollment->patient_id,
                'name' => $enrollment->patient ? userfullname($enrollment->patient->user_id) : null,
                'file_no' => $enrollment->patient ? $enrollment->patient->file_no : null,
            ],
            'total' => $audits->count(),
            'audits' => $audits,
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       MOTHER'S HISTORY
       ══════════════════════════════════════════════════════════════ */

    public function saveMedicalHistory(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'items'            => 'required|array|min:1',
            'items.*.category' => 'required|in:medical,surgical,obstetric,family,social',
            'items.*.description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Clear existing and re-save
            MaternityMedicalHistory::where('enrollment_id', $id)->delete();

            foreach ($request->items as $item) {
                MaternityMedicalHistory::create([
                    'enrollment_id' => $id,
                    'category'      => $item['category'],
                    'description'   => $item['description'],
                    'year'          => $item['year'] ?? null,
                    'notes'         => $item['notes'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Medical history saved successfully.',
                'history' => MaternityMedicalHistory::where('enrollment_id', $id)->get(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function savePreviousPregnancy(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'year'              => 'nullable|integer|min:1950|max:' . date('Y'),
            'place_of_delivery' => 'nullable|string',
            'duration_weeks'    => 'nullable|integer|min:1|max:45',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $babyAlive = filter_var($request->baby_alive ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $babyDead = filter_var($request->baby_dead ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $babyStillbirth = filter_var($request->baby_stillbirth ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            $pp = MaternityPreviousPregnancy::create([
                'enrollment_id'    => $id,
                'year'             => $request->year,
                'place_of_delivery'=> $request->place_of_delivery,
                'duration_weeks'   => $request->duration_weeks,
                'complications'    => $request->complications,
                'type_of_labour'   => $request->type_of_labour,
                'baby_alive'       => $babyAlive ? 1 : 0,
                'baby_dead'        => $babyDead ? 1 : 0,
                'baby_stillbirth'  => $babyStillbirth ? 1 : 0,
                'baby_sex'         => $request->baby_sex,
                'birth_weight_kg'  => $request->birth_weight_kg,
                'present_health'   => $request->present_health,
                'notes'            => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Previous pregnancy record saved.',
                'pregnancy' => $pp,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function deletePreviousPregnancy($id)
    {
        $pp = MaternityPreviousPregnancy::findOrFail($id);
        $pp->delete();
        return response()->json(['success' => true, 'message' => 'Record deleted.']);
    }

    public function updatePreviousPregnancy(Request $request, $id)
    {
        $pp = MaternityPreviousPregnancy::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'year'              => 'nullable|integer|min:1950|max:' . date('Y'),
            'place_of_delivery' => 'nullable|string',
            'duration_weeks'    => 'nullable|integer|min:1|max:45',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $babyAlive = filter_var($request->baby_alive ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $babyDead = filter_var($request->baby_dead ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $babyStillbirth = filter_var($request->baby_stillbirth ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $pp->update([
            'year'             => $request->year,
            'place_of_delivery'=> $request->place_of_delivery,
            'duration_weeks'   => $request->duration_weeks,
            'complications'    => $request->complications,
            'type_of_labour'   => $request->type_of_labour,
            'baby_alive'       => $babyAlive ? 1 : 0,
            'baby_dead'        => $babyDead ? 1 : 0,
            'baby_stillbirth'  => $babyStillbirth ? 1 : 0,
            'baby_sex'         => $request->baby_sex,
            'birth_weight_kg'  => $request->birth_weight_kg,
            'present_health'   => $request->present_health,
            'notes'            => $request->notes,
        ]);

        return response()->json(['success' => true, 'message' => 'Previous pregnancy updated.', 'pregnancy' => $pp->fresh()]);
    }

    public function updateMedicalHistory(Request $request, $id)
    {
        $history = MaternityMedicalHistory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category'    => 'required|in:medical,surgical,obstetric,family,social',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $history->update([
            'category'    => $request->category,
            'description' => $request->description,
            'year'        => $request->year,
            'notes'       => $request->notes,
        ]);

        return response()->json(['success' => true, 'message' => 'Medical history updated.', 'history' => $history->fresh()]);
    }

    public function deleteMedicalHistory($id)
    {
        $history = MaternityMedicalHistory::findOrFail($id);
        $history->delete();
        return response()->json(['success' => true, 'message' => 'Medical history entry deleted.']);
    }

    /* ══════════════════════════════════════════════════════════════
       ANC VISITS
       ══════════════════════════════════════════════════════════════ */

    public function getAncVisits($id)
    {
        $visits = AncVisit::where('enrollment_id', $id)
            ->with('seenBy')
            ->orderBy('visit_number', 'asc')
            ->get()
            ->map(function ($v) {
                return [
                    'id'              => $v->id,
                    'visit_number'    => $v->visit_number,
                    'visit_type'      => $v->visit_type,
                    'visit_date'      => $v->visit_date ? $v->visit_date->format('d M Y') : null,
                    'visit_date_raw'  => $v->visit_date ? $v->visit_date->format('Y-m-d') : null,
                    'gestational_age' => $v->getGestationalAge(),
                    'gestational_age_weeks' => $v->gestational_age_weeks,
                    'gestational_age_days'  => $v->gestational_age_days,
                    'weight_kg'       => $v->weight_kg,
                    'blood_pressure_systolic'  => $v->blood_pressure_systolic,
                    'blood_pressure_diastolic' => $v->blood_pressure_diastolic,
                    'bp'              => $v->getBloodPressure(),
                    'fundal_height'   => $v->fundal_height_cm,
                    'fundal_height_cm'=> $v->fundal_height_cm,
                    'fhr'             => $v->fetal_heart_rate,
                    'fetal_heart_rate'=> $v->fetal_heart_rate,
                    'presentation'    => $v->presentation,
                    'oedema'          => $v->oedema,
                    'foetal_movement' => $v->foetal_movement,
                    'urine_protein'   => $v->urine_protein,
                    'urine_glucose'   => $v->urine_glucose,
                    'haemoglobin'     => $v->haemoglobin,
                    'next_appointment'=> $v->next_appointment ? $v->next_appointment->format('d M Y') : null,
                    'next_appointment_raw' => $v->next_appointment ? $v->next_appointment->format('Y-m-d') : null,
                    'seen_by'         => $v->seenBy ? userfullname($v->seenBy->id) : 'N/A',
                    'clinical_notes'  => $v->clinical_notes,
                ];
            });

        return response()->json(['success' => true, 'visits' => $visits]);
    }

    public function saveAncVisit(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'visit_date'             => 'required|date',
            'gestational_age_weeks'  => 'required|integer|min:1|max:45',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Auto-increment visit number
            $nextVisitNumber = AncVisit::where('enrollment_id', $id)->max('visit_number') + 1;
            $oedemaRaw = strtolower(trim((string) ($request->oedema ?? '')));
            $oedemaMap = [
                '' => null,
                'none' => 'none',
                'nil' => 'none',
                '-' => 'none',
                '+' => 'mild',
                'mild' => 'mild',
                '++' => 'moderate',
                'moderate' => 'moderate',
                '+++' => 'severe',
                'severe' => 'severe',
            ];
            $normalizedOedema = $oedemaMap[$oedemaRaw] ?? null;

            $visit = AncVisit::create([
                'enrollment_id'          => $id,
                'patient_id'             => $enrollment->patient_id,
                'visit_number'           => $nextVisitNumber,
                'visit_type'             => $request->visit_type ?: ($nextVisitNumber === 1 ? 'booking' : 'routine'),
                'visit_date'             => $this->safeParseDate($request->visit_date),
                'gestational_age_weeks'  => $request->gestational_age_weeks,
                'gestational_age_days'   => $request->gestational_age_days ?? 0,
                'weight_kg'              => $request->weight_kg,
                'blood_pressure_systolic'=> $request->blood_pressure_systolic,
                'blood_pressure_diastolic'=> $request->blood_pressure_diastolic,
                'fundal_height_cm'       => $request->fundal_height_cm,
                'presentation'           => $request->presentation,
                'fetal_heart_rate'       => $request->fetal_heart_rate,
                'foetal_movement'        => $request->foetal_movement,
                'oedema'                 => $normalizedOedema,
                'urine_protein'          => $request->urine_protein,
                'urine_glucose'          => $request->urine_glucose,
                'haemoglobin'            => $request->haemoglobin,
                'clinical_notes'         => $request->clinical_notes,
                'next_appointment'       => $this->safeParseDate($request->next_appointment),
                'seen_by'                => Auth::id(),
            ]);

            // Auto-detect risk based on BP
            if ($request->blood_pressure_systolic >= 140 || $request->blood_pressure_diastolic >= 90) {
                if ($enrollment->risk_level !== 'high') {
                    $enrollment->update([
                        'risk_level'   => 'high',
                        'risk_factors' => array_merge($enrollment->risk_factors ?? [], ['Hypertension detected at ANC visit #' . $nextVisitNumber]),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'ANC visit #' . $nextVisitNumber . ' recorded successfully.',
                'visit'   => $visit,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateAncVisit(Request $request, $id)
    {
        $visit = AncVisit::findOrFail($id);
        $payload = $request->only([
            'visit_date', 'visit_type', 'gestational_age_weeks', 'gestational_age_days',
            'weight_kg', 'blood_pressure_systolic', 'blood_pressure_diastolic',
            'fundal_height_cm', 'presentation', 'fetal_heart_rate',
            'foetal_movement', 'oedema', 'urine_protein', 'urine_glucose',
            'haemoglobin', 'clinical_notes', 'next_appointment',
        ]);

        if (array_key_exists('oedema', $payload)) {
            $oedemaRaw = strtolower(trim((string) ($payload['oedema'] ?? '')));
            $oedemaMap = [
                '' => null,
                'none' => 'none',
                'nil' => 'none',
                '-' => 'none',
                '+' => 'mild',
                'mild' => 'mild',
                '++' => 'moderate',
                'moderate' => 'moderate',
                '+++' => 'severe',
                'severe' => 'severe',
            ];
            $payload['oedema'] = $oedemaMap[$oedemaRaw] ?? null;
        }

        $visit->update($payload);

        return response()->json(['success' => true, 'message' => 'ANC visit updated.', 'visit' => $visit->fresh()]);
    }

    public function getAncVisitDetail($id)
    {
        $visit = AncVisit::with(['seenBy', 'investigations.labRequest', 'investigations.imagingRequest'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'visit' => $visit]);
    }

    /* ══════════════════════════════════════════════════════════════
       INVESTIGATIONS
       ══════════════════════════════════════════════════════════════ */

    public function getInvestigations($id)
    {
        $investigations = AncInvestigation::where('enrollment_id', $id)
            ->with(['ancVisit', 'labRequest', 'imagingRequest'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($inv) {
                $type = $inv->lab_service_request_id ? 'lab' : ($inv->imaging_service_request_id ? 'imaging' : 'other');
                $status = 'pending';
                if ($type === 'lab' && $inv->labRequest) {
                    $status = $inv->labRequest->status ?? 'pending';
                } elseif ($type === 'imaging' && $inv->imagingRequest) {
                    $status = $inv->imagingRequest->status ?? 'pending';
                }

                return [
                    'id'                => $inv->id,
                    'investigation_name'=> $inv->investigation_name,
                    'type'              => $type,
                    'is_routine'        => $inv->is_routine,
                    'result_summary'    => $inv->result_summary,
                    'status'            => $status,
                    'visit_number'      => $inv->ancVisit ? $inv->ancVisit->visit_number : null,
                    'created_at'        => Carbon::parse($inv->created_at)->format('d M Y'),
                ];
            });

        return response()->json(['success' => true, 'investigations' => $investigations]);
    }

    public function orderInvestigation(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'investigation_name' => 'required|string',
            'type'               => 'required|in:lab,imaging',
            'service_id'         => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $labId = null;
            $imagingId = null;

            if ($request->type === 'lab') {
                $lab = $this->addSingleLab($request->service_id, $request->note, $enrollment->patient_id, null);
                $labId = $lab->id;
            } else {
                $imaging = $this->addSingleImaging($request->service_id, $request->note, $enrollment->patient_id, null);
                $imagingId = $imaging->id;
            }

            $investigation = AncInvestigation::create([
                'enrollment_id'           => $id,
                'anc_visit_id'            => $request->anc_visit_id,
                'lab_service_request_id'  => $labId,
                'imaging_service_request_id' => $imagingId,
                'investigation_name'      => $request->investigation_name,
                'is_routine'              => $request->is_routine ?? false,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Investigation ordered successfully.',
                'investigation' => $investigation,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /* ══════════════════════════════════════════════════════════════
       CLINICAL ORDERS (via ClinicalOrdersTrait)
       ══════════════════════════════════════════════════════════════ */

    public function saveMaternityLabs(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'labs'              => 'required|array|min:1',
            'labs.*.service_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $created = [];
            foreach ($request->labs as $labData) {
                $lab = $this->addSingleLab(
                    $labData['service_id'],
                    $labData['note'] ?? null,
                    $enrollment->patient_id,
                    null
                );
                $created[] = $lab;
            }

            return response()->json([
                'success' => true,
                'message' => count($created) . ' lab(s) ordered successfully.',
                'labs'    => $created,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function saveMaternityImaging(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'imaging'              => 'required|array|min:1',
            'imaging.*.service_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $created = [];
            foreach ($request->imaging as $imgData) {
                $img = $this->addSingleImaging(
                    $imgData['service_id'],
                    $imgData['note'] ?? null,
                    $enrollment->patient_id,
                    null
                );
                $created[] = $img;
            }

            return response()->json([
                'success' => true,
                'message' => count($created) . ' imaging order(s) placed successfully.',
                'imaging' => $created,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function saveMaternityPrescriptions(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'prescriptions'              => 'required|array|min:1',
            'prescriptions.*.product_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $created = [];
            foreach ($request->prescriptions as $pData) {
                $presc = $this->addSinglePrescription(
                    $pData['product_id'],
                    $pData['dose'] ?? null,
                    $enrollment->patient_id,
                    null
                );
                $created[] = $presc;
            }

            return response()->json([
                'success' => true,
                'message' => count($created) . ' prescription(s) created successfully.',
                'prescriptions' => $created,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function saveMaternityProcedures(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'procedures'                     => 'required|array|min:1',
            'procedures.*.service_id'        => 'required|integer',
            'procedures.*.priority'          => 'nullable|in:routine,urgent,emergency',
            'procedures.*.scheduled_date'    => 'nullable|date',
            'procedures.*.pre_notes'         => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $created = [];
            foreach ($request->procedures as $procData) {
                $procedure = $this->addSingleProcedure([
                    'service_id'     => $procData['service_id'],
                    'priority'       => $procData['priority'] ?? 'routine',
                    'scheduled_date' => $procData['scheduled_date'] ?? null,
                    'pre_notes'      => $procData['pre_notes'] ?? null,
                ], $enrollment->patient_id, null, null);

                $created[] = $procedure;
            }

            return response()->json([
                'success' => true,
                'message' => count($created) . ' procedure request(s) created successfully.',
                'procedures' => $created,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function maternityRePrescribe(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $request->validate([
            'source_type'  => 'required|in:labs,imaging,prescriptions,procedures',
            'source_ids'   => 'required|array|min:1',
            'source_ids.*' => 'integer',
            'adjust_doses' => 'nullable|array',
        ]);

        try {
            $created = $this->rePrescribeItems(
                $request->input('source_type'),
                $request->input('source_ids'),
                $enrollment->patient_id,
                null,
                $request->input('adjust_doses', [])
            );

            return response()->json([
                'success' => true,
                'items'   => $created->map(fn($item) => ['id' => $item->id]),
                'count'   => $created->count(),
                'message' => $created->count() . ' item(s) re-prescribed',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityRecentEncounters(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);
        $encounters = $this->recentEncountersForPatient($enrollment->patient_id, 5);
        return response()->json(['success' => true, 'encounters' => $encounters]);
    }

    public function maternityEncounterItems(Request $request, $id, int $encounterId)
    {
        MaternityEnrollment::findOrFail($id);
        $items = $this->getEncounterItems($encounterId);
        return response()->json(['success' => true, 'items' => $items]);
    }

    public function applyMaternityTreatmentPlan(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $request->validate([
            'treatment_plan_id'   => 'required|integer|exists:treatment_plans,id',
            'selected_item_ids'   => 'nullable|array',
            'selected_item_ids.*' => 'integer',
        ]);

        $plan = TreatmentPlan::active()->findOrFail($request->input('treatment_plan_id'));

        try {
            $results = $this->applyTreatmentPlan(
                $plan,
                $enrollment->patient_id,
                null,
                $request->input('selected_item_ids', [])
            );

            $totalCount = $results->reduce(fn($carry, $items) => $carry + count($items), 0);

            return response()->json([
                'success' => true,
                'results' => $results->map(fn($items) => collect($items)->map(fn($r) => ['id' => $r->id])),
                'count'   => $totalCount,
                'message' => "{$totalCount} item(s) added from '{$plan->name}'",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
     * Single-item add/remove endpoints (auto-save — nursing parity)
     * Maternity: patient_id from enrollment, encounter_id = null
     * ═══════════════════════════════════════════════════════════════ */

    public function maternityAddSingleLab(Request $request, $id)
    {
        try {
            $enrollment = MaternityEnrollment::findOrFail($id);
            $request->validate(['service_id' => 'required|integer']);
            $lab = $this->addSingleLab(
                $request->input('service_id'),
                $request->input('note'),
                $enrollment->patient_id,
                null
            );
            return response()->json([
                'success' => true,
                'id' => $lab->id,
                'item' => ['id' => $lab->id, 'service_id' => $lab->service_id, 'note' => $lab->note, 'created_at' => $lab->created_at],
                'message' => 'Lab added'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityRemoveSingleLab(LabServiceRequest $lab)
    {
        try {
            $this->removeSingleLab($lab->id);
            return response()->json(['success' => true, 'message' => 'Lab removed']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityAddSingleImaging(Request $request, $id)
    {
        try {
            $enrollment = MaternityEnrollment::findOrFail($id);
            $request->validate(['service_id' => 'required|integer']);
            $imaging = $this->addSingleImaging(
                $request->input('service_id'),
                $request->input('note'),
                $enrollment->patient_id,
                null
            );
            return response()->json([
                'success' => true,
                'id' => $imaging->id,
                'item' => ['id' => $imaging->id, 'service_id' => $imaging->service_id, 'note' => $imaging->note, 'created_at' => $imaging->created_at],
                'message' => 'Imaging added'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityRemoveSingleImaging(ImagingServiceRequest $imaging)
    {
        try {
            $this->removeSingleImaging($imaging->id);
            return response()->json(['success' => true, 'message' => 'Imaging removed']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityAddSinglePrescription(Request $request, $id)
    {
        try {
            $enrollment = MaternityEnrollment::findOrFail($id);
            $request->validate(['product_id' => 'required|integer']);
            $presc = $this->addSinglePrescription(
                $request->input('product_id'),
                $request->input('dose', ''),
                $enrollment->patient_id,
                null
            );
            return response()->json([
                'success' => true,
                'id' => $presc->id,
                'item' => ['id' => $presc->id, 'product_id' => $presc->product_id, 'dose' => $presc->dose, 'created_at' => $presc->created_at],
                'message' => 'Prescription added'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityUpdatePrescriptionDose(ProductRequest $prescription)
    {
        try {
            $dose = request()->input('dose', '');
            $presc = $this->updatePrescriptionDose($prescription->id, $dose);
            return response()->json(['success' => true, 'id' => $presc->id, 'message' => 'Dose updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityRemoveSinglePrescription(ProductRequest $prescription)
    {
        try {
            $this->removeSinglePrescription($prescription->id);
            return response()->json(['success' => true, 'message' => 'Prescription removed']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityAddSingleProcedure(Request $request, $id)
    {
        try {
            $enrollment = MaternityEnrollment::findOrFail($id);
            $request->validate([
                'service_id' => 'required|integer',
                'priority' => 'required|string',
            ]);
            $procedure = $this->addSingleProcedure(
                $request->only(['service_id', 'priority', 'scheduled_date', 'pre_notes']),
                $enrollment->patient_id,
                null,
                null
            );
            return response()->json([
                'success' => true,
                'id' => $procedure->id,
                'item' => ['id' => $procedure->id, 'service_id' => $procedure->service_id, 'priority' => $procedure->priority, 'created_at' => $procedure->created_at],
                'message' => 'Procedure added'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityRemoveSingleProcedure(Procedure $procedure)
    {
        try {
            $this->removeSingleProcedure($procedure->id);
            return response()->json(['success' => true, 'message' => 'Procedure removed']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityUpdateLabNote(LabServiceRequest $lab)
    {
        try {
            $lab = $this->updateSingleLabNote($lab->id, request()->input('note', ''));
            return response()->json(['success' => true, 'id' => $lab->id, 'message' => 'Note updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function maternityUpdateImagingNote(ImagingServiceRequest $imaging)
    {
        try {
            $imaging = $this->updateSingleImagingNote($imaging->id, request()->input('note', ''));
            return response()->json(['success' => true, 'id' => $imaging->id, 'message' => 'Note updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ══════════════════════════════════════════════════════════════
       DELIVERY
       ══════════════════════════════════════════════════════════════ */

    public function saveDeliveryRecord(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'delivery_date'    => 'required|date',
            'type_of_delivery' => 'required|in:svd,cs,vacuum,forceps,breech',
            'number_of_babies' => 'required|integer|min:1|max:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if delivery record already exists
        if (DeliveryRecord::where('enrollment_id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Delivery record already exists for this enrollment.'], 422);
        }

        try {
            DB::beginTransaction();

            $delivery = DeliveryRecord::create([
                'enrollment_id'          => $id,
                'patient_id'             => $enrollment->patient_id,
                'delivery_date'          => $this->safeParseDate($request->delivery_date),
                'delivery_time'          => $request->delivery_time ? Carbon::parse($request->delivery_time) : null,
                'place_of_delivery'      => $request->place_of_delivery,
                'duration_of_labour_hours'=> $request->duration_of_labour_hours,
                'type_of_delivery'       => $request->type_of_delivery,
                'episiotomy'             => $request->episiotomy ?? 'none',
                'complications'          => $request->complications,
                'blood_loss_ml'          => $request->blood_loss_ml,
                'placenta_complete'      => $request->placenta_complete ?? true,
                'placenta_notes'         => $request->placenta_notes,
                'perineal_tear_degree'   => $request->perineal_tear_degree,
                'oxytocin_given'         => $request->oxytocin_given ?? false,
                'number_of_babies'       => $request->number_of_babies,
                'delivered_by'           => Auth::id(),
                'notes'                  => $request->notes,
            ]);

            // Update enrollment status — delivery transitions directly to postnatal
            $enrollment->update(['status' => 'postnatal']);

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => 'Delivery record saved successfully.',
                'delivery' => $delivery,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateDeliveryRecord(Request $request, $id)
    {
        $delivery = DeliveryRecord::findOrFail($id);

        $delivery->update($request->only([
            'delivery_date', 'delivery_time', 'place_of_delivery',
            'duration_of_labour_hours', 'type_of_delivery', 'episiotomy',
            'complications', 'blood_loss_ml', 'placenta_complete', 'placenta_notes',
            'perineal_tear_degree', 'oxytocin_given', 'number_of_babies', 'notes',
        ]));

        return response()->json(['success' => true, 'message' => 'Delivery record updated.', 'delivery' => $delivery->fresh()]);
    }

    public function getDeliveryRecord($id)
    {
        $delivery = DeliveryRecord::with(['deliveredBy', 'babies.patient.user', 'partographEntries'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'delivery' => $delivery]);
    }

    /* ── Partograph ──────────────────────────────────────────────── */

    public function savePartographEntry(Request $request, $id)
    {
        $delivery = DeliveryRecord::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'recorded_at'            => 'required|date',
            'cervical_dilation_cm'   => 'required|numeric|min:0|max:10',
            'descent'                => 'nullable|string|max:20',
            'contractions_per_10min' => 'nullable|integer|min:0|max:20',
            'contraction_duration_sec' => 'nullable|integer|min:0|max:180',
            'fetal_heart_rate'       => 'nullable|integer|min:60|max:220',
            'amniotic_fluid'         => 'nullable|in:intact,clear,meconium_stained,bloody,absent',
            'moulding'               => 'nullable|in:none,+,++,+++',
            'maternal_pulse'         => 'nullable|integer|min:20|max:220',
            'maternal_bp_systolic'   => 'nullable|integer|min:40|max:300',
            'maternal_bp_diastolic'  => 'nullable|integer|min:20|max:220',
            'maternal_temp_c'        => 'nullable|numeric|min:30|max:45',
            'urine_output_ml'        => 'nullable|integer|min:0',
            'urine_protein'          => 'nullable|in:nil,trace,+,++,+++',
            'oxytocin_dose'          => 'nullable|string|max:100',
            'iv_fluids'              => 'nullable|string|max:255',
            'medications'            => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $payload = $this->buildPartographPayload($request, $id);

            $entry = new DeliveryPartograph();
            foreach ($payload as $column => $value) {
                $entry->{$column} = $value;
            }
            $entry->save();

            return response()->json([
                'success' => true,
                'message' => 'Partograph entry recorded.',
                'entry'   => $this->normalizePartographEntry($entry),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getPartographEntries($id)
    {
        $entries = DeliveryPartograph::where('delivery_record_id', $id)
            ->with('recordedBy')
            ->orderBy('recorded_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'entries' => $entries->map(function ($entry) {
                return $this->normalizePartographEntry($entry);
            })->values(),
        ]);
    }

    public function updatePartographEntry(Request $request, $id)
    {
        $entry = DeliveryPartograph::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'recorded_at'            => 'required|date',
            'cervical_dilation_cm'   => 'required|numeric|min:0|max:10',
            'descent'                => 'nullable|string|max:20',
            'contractions_per_10min' => 'nullable|integer|min:0|max:20',
            'contraction_duration_sec' => 'nullable|integer|min:0|max:180',
            'fetal_heart_rate'       => 'nullable|integer|min:60|max:220',
            'amniotic_fluid'         => 'nullable|in:intact,clear,meconium_stained,bloody,absent',
            'moulding'               => 'nullable|in:none,+,++,+++',
            'maternal_pulse'         => 'nullable|integer|min:20|max:220',
            'maternal_bp_systolic'   => 'nullable|integer|min:40|max:300',
            'maternal_bp_diastolic'  => 'nullable|integer|min:20|max:220',
            'maternal_temp_c'        => 'nullable|numeric|min:30|max:45',
            'urine_output_ml'        => 'nullable|integer|min:0',
            'urine_protein'          => 'nullable|in:nil,trace,+,++,+++',
            'oxytocin_dose'          => 'nullable|string|max:100',
            'iv_fluids'              => 'nullable|string|max:255',
            'medications'            => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $payload = $this->buildPartographPayload($request, $entry->delivery_record_id);
            unset($payload['delivery_record_id'], $payload['recorded_by']);

            foreach ($payload as $column => $value) {
                $entry->{$column} = $value;
            }
            $entry->save();

            return response()->json([
                'success' => true,
                'message' => 'Partograph entry updated.',
                'entry'   => $this->normalizePartographEntry($entry->fresh(['recordedBy'])),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function deletePartographEntry($id)
    {
        $entry = DeliveryPartograph::findOrFail($id);

        try {
            $deliveryId = $entry->delivery_record_id;
            $entry->delete();

            return response()->json([
                'success' => true,
                'message' => 'Partograph entry deleted.',
                'delivery_record_id' => $deliveryId,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function buildPartographPayload(Request $request, int $deliveryId): array
    {
        $payload = [
            'delivery_record_id' => $deliveryId,
            'recorded_at' => Carbon::parse($request->recorded_at),
            'cervical_dilation_cm' => $request->cervical_dilation_cm,
            'amniotic_fluid' => $request->amniotic_fluid,
            'moulding' => $request->moulding,
            'maternal_pulse' => $request->maternal_pulse,
            'contraction_duration_sec' => $request->contraction_duration_sec,
            'urine_output_ml' => $request->urine_output_ml,
            'medications' => $request->medications,
            'recorded_by' => Auth::id(),
        ];

        $descent = $this->firstPresent($request, ['descent', 'descent_of_head']);
        if ($this->partographHasColumn('descent')) {
            $payload['descent'] = $descent;
        } elseif ($this->partographHasColumn('descent_of_head')) {
            $payload['descent_of_head'] = $descent;
        }

        $contractions = $this->firstPresent($request, ['contractions_per_10min', 'contractions_per_10_min']);
        if ($this->partographHasColumn('contractions_per_10min')) {
            $payload['contractions_per_10min'] = $contractions;
        } elseif ($this->partographHasColumn('contractions_per_10_min')) {
            $payload['contractions_per_10_min'] = $contractions;
        }

        $fhr = $this->firstPresent($request, ['fetal_heart_rate', 'foetal_heart_rate']);
        if ($this->partographHasColumn('fetal_heart_rate')) {
            $payload['fetal_heart_rate'] = $fhr;
        } elseif ($this->partographHasColumn('foetal_heart_rate')) {
            $payload['foetal_heart_rate'] = $fhr;
        }

        $temp = $this->firstPresent($request, ['maternal_temp_c', 'maternal_temp']);
        if ($this->partographHasColumn('maternal_temp_c')) {
            $payload['maternal_temp_c'] = $temp;
        } elseif ($this->partographHasColumn('maternal_temp')) {
            $payload['maternal_temp'] = $temp;
        }

        $systolic = $this->firstPresent($request, ['maternal_bp_systolic']);
        $diastolic = $this->firstPresent($request, ['maternal_bp_diastolic']);
        $maternalBp = $this->firstPresent($request, ['maternal_bp']);

        if ($this->partographHasColumn('maternal_bp_systolic')) {
            $payload['maternal_bp_systolic'] = $systolic;
        }
        if ($this->partographHasColumn('maternal_bp_diastolic')) {
            $payload['maternal_bp_diastolic'] = $diastolic;
        }
        if ($this->partographHasColumn('maternal_bp')) {
            if ($maternalBp) {
                $payload['maternal_bp'] = $maternalBp;
            } elseif ($systolic || $diastolic) {
                $payload['maternal_bp'] = trim(($systolic ?: '') . '/' . ($diastolic ?: ''), '/');
            }
        }

        if ($this->partographHasColumn('urine_protein')) {
            $payload['urine_protein'] = $this->firstPresent($request, ['urine_protein']);
        }
        if ($this->partographHasColumn('oxytocin_dose')) {
            $payload['oxytocin_dose'] = $this->firstPresent($request, ['oxytocin_dose']);
        }
        if ($this->partographHasColumn('iv_fluids')) {
            $payload['iv_fluids'] = $this->firstPresent($request, ['iv_fluids']);
        }

        return $payload;
    }

    private function normalizePartographEntry(DeliveryPartograph $entry): array
    {
        $maternalBp = data_get($entry, 'maternal_bp');
        $systolic = data_get($entry, 'maternal_bp_systolic');
        $diastolic = data_get($entry, 'maternal_bp_diastolic');

        if ((!$systolic || !$diastolic) && $maternalBp && str_contains((string) $maternalBp, '/')) {
            [$parsedSys, $parsedDia] = array_pad(explode('/', (string) $maternalBp, 2), 2, null);
            $systolic = $systolic ?: trim((string) $parsedSys);
            $diastolic = $diastolic ?: trim((string) $parsedDia);
        }

        return [
            'id' => $entry->id,
            'delivery_record_id' => $entry->delivery_record_id,
            'recorded_at' => optional($entry->recorded_at)->toDateTimeString(),
            'cervical_dilation_cm' => data_get($entry, 'cervical_dilation_cm'),
            'descent' => data_get($entry, 'descent') ?? data_get($entry, 'descent_of_head'),
            'contractions_per_10min' => data_get($entry, 'contractions_per_10min') ?? data_get($entry, 'contractions_per_10_min'),
            'contraction_duration_sec' => data_get($entry, 'contraction_duration_sec'),
            'fetal_heart_rate' => data_get($entry, 'fetal_heart_rate') ?? data_get($entry, 'foetal_heart_rate'),
            'amniotic_fluid' => data_get($entry, 'amniotic_fluid'),
            'moulding' => data_get($entry, 'moulding'),
            'maternal_pulse' => data_get($entry, 'maternal_pulse'),
            'maternal_bp_systolic' => $systolic,
            'maternal_bp_diastolic' => $diastolic,
            'maternal_bp' => $maternalBp ?: (($systolic || $diastolic) ? trim(($systolic ?: '') . '/' . ($diastolic ?: ''), '/') : null),
            'maternal_temp_c' => data_get($entry, 'maternal_temp_c') ?? data_get($entry, 'maternal_temp'),
            'urine_output_ml' => data_get($entry, 'urine_output_ml'),
            'urine_protein' => data_get($entry, 'urine_protein'),
            'oxytocin_dose' => data_get($entry, 'oxytocin_dose'),
            'iv_fluids' => data_get($entry, 'iv_fluids'),
            'medications' => data_get($entry, 'medications'),
            'recorded_by' => data_get($entry, 'recorded_by'),
            'recorded_by_name' => data_get($entry, 'recordedBy.name') ?: userfullname(data_get($entry, 'recorded_by')),
        ];
    }

    private function firstPresent(Request $request, array $keys)
    {
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                return $request->input($key);
            }
        }

        foreach ($keys as $key) {
            if ($request->has($key)) {
                return $request->input($key);
            }
        }

        return null;
    }

    private function partographHasColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::hasTable('delivery_partograph')
                ? Schema::getColumnListing('delivery_partograph')
                : [];
        }

        return in_array($column, $columns, true);
    }

    /* ══════════════════════════════════════════════════════════════
       BABY REGISTRATION & GROWTH
       ══════════════════════════════════════════════════════════════ */

    public function registerBaby(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'sex'             => 'required|in:male,female,ambiguous',
            'birth_weight_kg' => 'required|numeric|min:0.3|max:8',
            'baby_surname'    => 'required|string',
            'baby_firstname'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Create a User record for the baby
            $babyUser = User::create([
                'surname'   => $request->baby_surname,
                'firstname' => $request->baby_firstname,
                'othername' => $request->baby_othername ?? '',
                'email'     => 'baby_' . time() . '_' . rand(100, 999) . '@placeholder.local',
                'password'  => bcrypt(Str::random(16)),
                'user_category_id' => 1,
            ]);

            // Get mother's patient record for defaults
            $motherPatient = Patient::find($enrollment->patient_id);

            // Generate file number for baby
            $lastFile = Patient::orderBy('id', 'desc')->first();
            $nextFileNo = $lastFile ? intval(preg_replace('/[^0-9]/', '', $lastFile->file_no)) + 1 : 1;
            $fileNo = str_pad($nextFileNo, 6, '0', STR_PAD_LEFT);

            // Create patient record for baby
            $babyPatient = Patient::create([
                'user_id'     => $babyUser->id,
                'file_no'     => $fileNo,
                'gender'      => $request->sex,
                'dob'         => $enrollment->deliveryRecord ? $enrollment->deliveryRecord->delivery_date : Carbon::today(),
                'blood_group' => null,
                'genotype'    => null,
                'hmo_id'      => $motherPatient ? $motherPatient->hmo_id : null,
                'hmo_no'      => $motherPatient ? $motherPatient->hmo_no : null,
                'phone_no'    => $motherPatient ? $motherPatient->phone_no : null,
                'address'     => $motherPatient ? $motherPatient->address : null,
                'next_of_kin_name'  => $motherPatient ? userfullname($motherPatient->user_id) : null,
                'next_of_kin_phone' => $motherPatient ? $motherPatient->phone_no : null,
            ]);

            // Determine birth order
            $birthOrder = MaternityBaby::where('enrollment_id', $id)->max('birth_order') + 1;

            // Create maternity baby record
            $baby = MaternityBaby::create([
                'enrollment_id'        => $id,
                'patient_id'           => $babyPatient->id,
                'birth_order'          => $birthOrder,
                'sex'                  => $request->sex,
                'birth_weight_kg'      => $request->birth_weight_kg,
                'length_cm'            => $request->length_cm,
                'head_circumference_cm'=> $request->head_circumference_cm,
                'chest_circumference_cm'=> $request->chest_circumference_cm,
                'apgar_1_min'          => $request->apgar_1_min,
                'apgar_5_min'          => $request->apgar_5_min,
                'apgar_10_min'         => $request->apgar_10_min,
                'resuscitation'        => $request->resuscitation ?? false,
                'resuscitation_details'=> $request->resuscitation_details,
                'birth_defects'        => $request->birth_defects,
                'feeding_method'       => $request->feeding_method ?? 'exclusive_breastfeeding',
                'bcg_given'            => $request->bcg_given ?? false,
                'opv0_given'           => $request->opv0_given ?? false,
                'hbv0_given'           => $request->hbv0_given ?? false,
                'vitamin_k_given'      => $request->vitamin_k_given ?? false,
                'eye_prophylaxis'      => $request->eye_prophylaxis ?? false,
                'date_first_seen'      => Carbon::today(),
                'reasons_for_special_care' => $request->reasons_for_special_care,
                'status'               => 'alive',
                'notes'                => $request->notes,
            ]);

            // Create initial growth record from birth measurements with WHO z-scores
            if ($request->birth_weight_kg) {
                $babySex = $baby->sex === 'female' ? 'F' : 'M';
                $waz = WhoGrowthStandard::calculateZScore('wfa', $babySex, 0, $request->birth_weight_kg);
                $laz = $request->length_cm ? WhoGrowthStandard::calculateZScore('lhfa', $babySex, 0, $request->length_cm) : null;
                $hcz = $request->head_circumference_cm ? WhoGrowthStandard::calculateZScore('hcfa', $babySex, 0, $request->head_circumference_cm) : null;
                $nutritionalStatus = WhoGrowthStandard::classifyNutritionalStatus($waz);

                ChildGrowthRecord::create([
                    'baby_id'     => $baby->id,
                    'patient_id'  => $babyPatient->id,
                    'record_date' => Carbon::today(),
                    'age_months'  => 0,
                    'weight_kg'   => $request->birth_weight_kg,
                    'length_height_cm'     => $request->length_cm,
                    'head_circumference_cm'=> $request->head_circumference_cm,
                    'weight_for_age_z'     => $waz,
                    'length_for_age_z'     => $laz,
                    'nutritional_status'   => $nutritionalStatus,
                    'recorded_by'          => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success'   => true,
                'message'   => 'Baby registered successfully. File No: ' . $fileNo,
                'baby'      => $baby->load('patient.user'),
                'file_no'   => $fileNo,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getBabyDetails($id)
    {
        $baby = MaternityBaby::with(['patient.user', 'growthRecords' => function ($q) {
            $q->orderBy('record_date', 'desc');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'baby'    => $baby,
            'age'     => $baby->getAgeInMonths() . ' months',
            'apgar'   => $baby->getApgarSummary(),
        ]);
    }

    public function updateBaby(Request $request, $id)
    {
        $baby = MaternityBaby::findOrFail($id);
        $baby->update($request->only([
            'feeding_method', 'bcg_given', 'opv0_given', 'hbv0_given',
            'vitamin_k_given', 'eye_prophylaxis', 'status',
            'reasons_for_special_care', 'notes',
        ]));

        return response()->json(['success' => true, 'message' => 'Baby record updated.', 'baby' => $baby->fresh()]);
    }

    public function saveGrowthRecord(Request $request, $id)
    {
        $baby = MaternityBaby::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'record_date' => 'required|date',
            'weight_kg'   => 'required|numeric|min:0.3|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Calculate age in months
            $dob = $baby->patient ? $baby->patient->dob : null;
            $recordDate = $this->safeParseDate($request->record_date);
            $ageMonths = $dob && $recordDate
                ? $this->safeParseDate($dob)->diffInMonths($recordDate) + ($this->safeParseDate($dob)->diffInDays($recordDate) % 30) / 30
                : null;
            $roundedAge = $ageMonths ? round($ageMonths, 1) : null;

            // Determine sex for WHO lookup (baby sex or patient sex)
            $babySex = $baby->sex === 'female' ? 'F' : 'M';

            // Auto-compute WHO z-scores from LMS reference data
            $waz = ($roundedAge !== null && $request->weight_kg)
                ? WhoGrowthStandard::calculateZScore('wfa', $babySex, $roundedAge, $request->weight_kg) : null;

            $laz = ($roundedAge !== null && $request->length_height_cm)
                ? WhoGrowthStandard::calculateZScore('lhfa', $babySex, $roundedAge, $request->length_height_cm) : null;

            $baz = null;
            if ($roundedAge !== null && $request->weight_kg && $request->length_height_cm) {
                $heightM = $request->length_height_cm / 100;
                if ($heightM > 0) {
                    $bmi = $request->weight_kg / ($heightM * $heightM);
                    $baz = WhoGrowthStandard::calculateZScore('bfa', $babySex, $roundedAge, $bmi);
                }
            }

            // Weight-for-length z not computed (requires length-based LMS table)
            $wlz = null;

            // Auto-classify nutritional status from z-scores
            $nutritionalStatus = WhoGrowthStandard::classifyNutritionalStatus($waz, $baz);

            $record = ChildGrowthRecord::create([
                'baby_id'              => $id,
                'patient_id'           => $baby->patient_id,
                'record_date'          => $recordDate,
                'age_months'           => $roundedAge,
                'weight_kg'            => $request->weight_kg,
                'length_height_cm'     => $request->length_height_cm,
                'head_circumference_cm'=> $request->head_circumference_cm,
                'muac_cm'              => $request->muac_cm,
                'weight_for_age_z'     => $waz,
                'length_for_age_z'     => $laz,
                'weight_for_length_z'  => $wlz,
                'bmi_for_age_z'        => $baz,
                'nutritional_status'   => $nutritionalStatus,
                'milestones'           => $request->milestones,
                'feeding_method'       => $request->feeding_method,
                'dietary_notes'        => $request->dietary_notes,
                'notes'                => $request->notes,
                'recorded_by'          => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Growth record saved.',
                'record'  => $record,
                'z_scores' => [
                    'WAZ' => $waz,
                    'LAZ' => $laz,
                    'BAZ' => $baz,
                    'status' => $nutritionalStatus,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getGrowthChartData($id)
    {
        $baby = MaternityBaby::findOrFail($id);
        $babySex = $baby->sex === 'female' ? 'F' : 'M';

        $records = ChildGrowthRecord::where('baby_id', $id)
            ->orderBy('age_months', 'asc')
            ->get(['age_months', 'weight_kg', 'length_height_cm', 'head_circumference_cm',
                   'weight_for_age_z', 'length_for_age_z', 'bmi_for_age_z',
                   'nutritional_status', 'record_date']);

        // Sex-specific WHO reference lines from seeded LMS data (all 7 SD bands)
        return response()->json([
            'success' => true,
            'sex'     => $babySex,
            'data'    => $records,
            'who_reference' => [
                'weight_for_age'       => WhoGrowthStandard::getChartData('wfa', $babySex),
                'length_for_age'       => WhoGrowthStandard::getChartData('lhfa', $babySex),
                'head_circumference'   => WhoGrowthStandard::getChartData('hcfa', $babySex),
                'bmi_for_age'          => WhoGrowthStandard::getChartData('bfa', $babySex),
            ],
            'chart_config' => [
                'bands' => [
                    ['from' => 'sd_neg3', 'to' => 'sd_neg2', 'color' => '#ff4444', 'label' => 'Severely underweight'],
                    ['from' => 'sd_neg2', 'to' => 'sd_neg1', 'color' => '#ff9800', 'label' => 'Underweight'],
                    ['from' => 'sd_neg1', 'to' => 'sd_pos1', 'color' => '#4caf50', 'label' => 'Normal'],
                    ['from' => 'sd_pos1', 'to' => 'sd_pos2', 'color' => '#ff9800', 'label' => 'Overweight risk'],
                    ['from' => 'sd_pos2', 'to' => 'sd_pos3', 'color' => '#ff4444', 'label' => 'Overweight/Obese'],
                ],
                'who_source' => 'WHO Child Growth Standards (2006)',
            ],
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       POSTNATAL VISITS
       ══════════════════════════════════════════════════════════════ */

    public function getPostnatalVisits($id)
    {
        $visits = PostnatalVisit::where('enrollment_id', $id)
            ->with('seenBy')
            ->orderBy('visit_date', 'asc')
            ->get()
            ->map(function ($v) {
                return [
                    'id'               => $v->id,
                    'visit_type'       => $v->visit_type,
                    'visit_type_label' => str_replace('_', ' ', ucfirst($v->visit_type)),
                    'visit_date'       => $v->visit_date ? $v->visit_date->format('d M Y') : null,
                    'visit_date_raw'   => $v->visit_date ? $v->visit_date->format('Y-m-d') : null,
                    'days_postpartum'  => $v->days_postpartum,
                    'general_condition'=> $v->general_condition,
                    'blood_pressure'   => $v->blood_pressure,
                    'temperature_c'    => $v->temperature_c,
                    'uterus_assessment'=> $v->uterus_assessment,
                    'lochia'           => $v->lochia,
                    'wound_assessment' => $v->wound_assessment,
                    'breast_assessment'=> $v->breast_assessment,
                    'breastfeeding_support' => $v->breastfeeding_support,
                    'emotional_wellbeing' => $v->emotional_wellbeing,
                    'emotional_notes'  => $v->emotional_notes,
                    'baby_weight_kg'   => $v->baby_weight_kg,
                    'baby_feeding'     => $v->baby_feeding,
                    'cord_status'      => $v->cord_status,
                    'jaundice'         => $v->jaundice,
                    'baby_general_condition' => $v->baby_general_condition,
                    'baby_notes'       => $v->baby_notes,
                    'family_planning_counselled' => $v->family_planning_counselled,
                    'family_planning_method' => $v->family_planning_method,
                    'seen_by'          => $v->seenBy ? userfullname($v->seenBy->id) : 'N/A',
                    'clinical_notes'   => $v->clinical_notes,
                    'next_appointment' => $v->next_appointment ? $v->next_appointment->format('d M Y') : null,
                    'next_appointment_raw' => $v->next_appointment ? $v->next_appointment->format('Y-m-d') : null,
                ];
            });

        return response()->json(['success' => true, 'visits' => $visits]);
    }

    public function savePostnatalVisit(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'visit_type' => 'required|in:within_24h,day_3,week_1_2,week_6,other',
            'visit_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Calculate days postpartum
            $deliveryDate = $enrollment->deliveryRecord ? $enrollment->deliveryRecord->delivery_date : null;
            $visitDate = $this->safeParseDate($request->visit_date);
            $daysPostpartum = $deliveryDate && $visitDate ? $deliveryDate->diffInDays($visitDate) : null;

            $visit = PostnatalVisit::create([
                'enrollment_id'      => $id,
                'patient_id'         => $enrollment->patient_id,
                'visit_type'         => $request->visit_type,
                'visit_date'         => $visitDate,
                'days_postpartum'    => $daysPostpartum,
                'general_condition'  => $request->general_condition,
                'blood_pressure'     => $request->blood_pressure,
                'temperature_c'      => $request->temperature_c,
                'uterus_assessment'  => $request->uterus_assessment,
                'lochia'             => $request->lochia,
                'wound_assessment'   => $request->wound_assessment,
                'breast_assessment'  => $request->breast_assessment,
                'breastfeeding_support' => $request->breastfeeding_support,
                'emotional_wellbeing'=> $request->emotional_wellbeing,
                'emotional_notes'    => $request->emotional_notes,
                'baby_weight_kg'     => $request->baby_weight_kg,
                'baby_feeding'       => $request->baby_feeding,
                'cord_status'        => $request->cord_status,
                'jaundice'           => $request->jaundice ?? false,
                'baby_general_condition' => $request->baby_general_condition,
                'baby_notes'         => $request->baby_notes,
                'family_planning_counselled' => $request->family_planning_counselled ?? false,
                'family_planning_method' => $request->family_planning_method,
                'clinical_notes'     => $request->clinical_notes,
                'next_appointment'   => $this->safeParseDate($request->next_appointment),
                'seen_by'            => Auth::id(),
            ]);



            return response()->json([
                'success' => true,
                'message' => 'Postnatal visit recorded.',
                'visit'   => $visit,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updatePostnatalVisit(Request $request, $id)
    {
        $visit = PostnatalVisit::findOrFail($id);
        $visit->update($request->only([
            'general_condition', 'blood_pressure', 'temperature_c',
            'uterus_assessment', 'lochia', 'wound_assessment',
            'breast_assessment', 'breastfeeding_support',
            'emotional_wellbeing', 'emotional_notes',
            'baby_weight_kg', 'baby_feeding', 'cord_status',
            'jaundice', 'baby_general_condition', 'baby_notes',
            'family_planning_counselled', 'family_planning_method',
            'clinical_notes', 'next_appointment',
        ]));

        return response()->json(['success' => true, 'message' => 'Postnatal visit updated.', 'visit' => $visit->fresh()]);
    }

    /* ══════════════════════════════════════════════════════════════
       IMMUNIZATION (unified with nursing schedule system)
       ══════════════════════════════════════════════════════════════ */

    public function getPatientScheduleMaternity($patientId)
    {
        return $this->nursingProxy()->getPatientSchedule($patientId);
    }

    public function generatePatientScheduleMaternity(Request $request, $patientId)
    {
        return $this->nursingProxy()->generatePatientSchedule($request, $patientId);
    }

    public function getImmunizationHistoryByPatient($patientId)
    {
        return $this->nursingProxy()->getImmunizationHistory($patientId);
    }

    public function administerFromScheduleMaternity(Request $request)
    {
        return $this->nursingProxy()->administerFromScheduleNew($request);
    }

    public function updateScheduleStatusMaternity(Request $request, $scheduleId)
    {
        return $this->nursingProxy()->updateScheduleStatus($request, $scheduleId);
    }

    public function getScheduleTemplatesMaternity()
    {
        return $this->nursingProxy()->getScheduleTemplates();
    }

    public function getVaccineProductsMaternity($vaccineName)
    {
        return $this->nursingProxy()->getVaccineProducts($vaccineName);
    }

    public function getProductBatchesMaternity(Request $request)
    {
        return $this->nursingProxy()->getProductBatches($request);
    }

    public function getMotherSchedule($enrollmentId)
    {
        $enrollment = MaternityEnrollment::findOrFail($enrollmentId);
        $patientId = $enrollment->patient_id;
        $this->ensureMotherScheduleGenerated($enrollment);
        return $this->getPatientScheduleMaternity($patientId);
    }

    public function generateMotherSchedule(Request $request, $enrollmentId)
    {
        $enrollment = MaternityEnrollment::findOrFail($enrollmentId);
        if (!$enrollment->lmp) {
            return response()->json([
                'success' => false,
                'message' => 'Mother LMP is required to generate maternal schedule.',
            ], 400);
        }

        $template = null;
        if ($request->filled('template_id')) {
            $template = VaccineScheduleTemplate::find($request->template_id);
        }
        if (!$template) {
            $template = VaccineScheduleTemplate::where('name', 'Nigeria ANC Maternal Schedule')->first();
        }
        if (!$template) {
            return response()->json(['success' => false, 'message' => 'Maternal schedule template not found.'], 404);
        }

        $items = VaccineScheduleItem::where('template_id', $template->id)
            ->orderBy('age_days')
            ->orderBy('sort_order')
            ->get();

        $created = 0;
        foreach ($items as $item) {
            $dueDate = Carbon::parse($enrollment->lmp)->addDays((int) $item->age_days);
            $schedule = PatientImmunizationSchedule::firstOrCreate(
                [
                    'patient_id' => $enrollment->patient_id,
                    'schedule_item_id' => $item->id,
                ],
                [
                    'due_date' => $dueDate,
                    'status' => PatientImmunizationSchedule::STATUS_PENDING,
                    'updated_by' => Auth::id(),
                ]
            );
            if ($schedule->wasRecentlyCreated) $created++;
        }

        PatientImmunizationSchedule::updateStatusesForPatient($enrollment->patient_id);

        return response()->json([
            'success' => true,
            'message' => $created . ' maternal schedule entries created.',
            'count' => $created,
        ]);
    }

    public function getMotherImmunizationHistory($enrollmentId)
    {
        $enrollment = MaternityEnrollment::findOrFail($enrollmentId);
        return $this->getImmunizationHistoryByPatient($enrollment->patient_id);
    }

    public function getBabySchedule($babyId)
    {
        $baby = MaternityBaby::findOrFail($babyId);
        $this->ensureBabyScheduleGenerated($baby->patient_id);
        return $this->getPatientScheduleMaternity($baby->patient_id);
    }

    public function generateBabySchedule(Request $request, $babyId)
    {
        $baby = MaternityBaby::findOrFail($babyId);

        if (!$request->filled('template_id')) {
            $npiTemplate = VaccineScheduleTemplate::where('name', 'Nigeria NPI Schedule')->first();
            if ($npiTemplate) {
                $request->merge(['template_id' => $npiTemplate->id]);
            }
        }

        return $this->generatePatientScheduleMaternity($request, $baby->patient_id);
    }

    public function getBabyImmunizationHistory($babyId)
    {
        $baby = MaternityBaby::findOrFail($babyId);
        return $this->getImmunizationHistoryByPatient($baby->patient_id);
    }

    public function getImmunizationSchedule($babyId)
    {
        return $this->getBabySchedule($babyId);
    }

    public function getImmunizationHistory($babyId)
    {
        return $this->getBabyImmunizationHistory($babyId);
    }

    public function administerImmunization(Request $request, $babyId)
    {
        return response()->json([
            'success' => false,
            'message' => 'Direct baby immunization endpoint is deprecated. Use schedule-based administration.',
        ], 400);
    }

    public function administerFromSchedule(Request $request, $babyId)
    {
        return $this->administerFromScheduleMaternity($request);
    }

    private function ensureBabyScheduleGenerated($patientId): void
    {
        $hasSchedule = PatientImmunizationSchedule::where('patient_id', $patientId)->exists();
        if ($hasSchedule) return;

        $template = VaccineScheduleTemplate::where('name', 'Nigeria NPI Schedule')->first();
        $request = request();
        if ($template) {
            $request->merge(['template_id' => $template->id]);
        }
        try {
            $this->nursingProxy()->generatePatientSchedule($request, $patientId);
        } catch (\Throwable $e) {
        }
    }

    private function ensureMotherScheduleGenerated(MaternityEnrollment $enrollment): void
    {
        if (!$enrollment->lmp) return;

        $template = VaccineScheduleTemplate::where('name', 'Nigeria ANC Maternal Schedule')->first();
        if (!$template) return;

        $hasMaternalSchedule = PatientImmunizationSchedule::where('patient_id', $enrollment->patient_id)
            ->whereIn('schedule_item_id', function ($query) use ($template) {
                $query->select('id')
                    ->from('vaccine_schedule_items')
                    ->where('template_id', $template->id);
            })->exists();

        if ($hasMaternalSchedule) return;

        $items = VaccineScheduleItem::where('template_id', $template->id)->orderBy('age_days')->orderBy('sort_order')->get();
        foreach ($items as $item) {
            PatientImmunizationSchedule::firstOrCreate(
                [
                    'patient_id' => $enrollment->patient_id,
                    'schedule_item_id' => $item->id,
                ],
                [
                    'due_date' => Carbon::parse($enrollment->lmp)->addDays((int) $item->age_days),
                    'status' => PatientImmunizationSchedule::STATUS_PENDING,
                    'updated_by' => Auth::id(),
                ]
            );
        }
        PatientImmunizationSchedule::updateStatusesForPatient($enrollment->patient_id);
    }

    /* ══════════════════════════════════════════════════════════════
       NURSING NOTES
       ══════════════════════════════════════════════════════════════ */

    public function getNotes($id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $notes = NursingNote::with(['type', 'createdBy'])
            ->where('patient_id', $enrollment->patient_id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($note) {
                return [
                    'id'           => $note->id,
                    'note'         => $note->note,
                    'type'         => $note->type ? $note->type->name : 'General',
                    'note_type_id' => $note->nursing_note_type_id,
                    'created_by'   => $note->createdBy ? userfullname($note->createdBy->id) : 'N/A',
                    'created_by_id'=> $note->created_by,
                    'created_at'   => Carbon::parse($note->created_at)->format('h:i a, d M Y'),
                    'time_ago'     => Carbon::parse($note->created_at)->diffForHumans(),
                    'can_edit'     => Auth::id() == $note->created_by && Carbon::parse($note->created_at)->diffInMinutes(now()) < (function_exists('appsettings') ? (appsettings('note_edit_duration') ?? 60) : 60),
                ];
            });

        $noteTypes = NursingNoteType::orderBy('name')->get(['id', 'name']);

        return response()->json(['success' => true, 'notes' => $notes, 'note_types' => $noteTypes]);
    }

    public function saveNote(Request $request, $id)
    {
        $enrollment = MaternityEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'note_type_id' => 'required|exists:nursing_note_types,id',
            'note'         => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $note = NursingNote::create([
                'patient_id'          => $enrollment->patient_id,
                'nursing_note_type_id'=> $request->note_type_id,
                'note'                => $request->note,
                'created_by'          => Auth::id(),
                'completed'           => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note saved successfully.',
                'note'    => $note,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateNote(Request $request, $id)
    {
        $note = NursingNote::findOrFail($id);

        // Ownership + time-window check (same as nursing workbench)
        if (Auth::id() != $note->created_by) {
            return response()->json(['success' => false, 'message' => 'You can only edit your own notes.'], 403);
        }

        $editDuration = function_exists('appsettings') ? (appsettings('note_edit_duration') ?? 60) : 60;
        if (Carbon::parse($note->created_at)->addMinutes($editDuration)->isPast()) {
            return response()->json(['success' => false, 'message' => 'Edit window has expired.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
            'note_type_id' => 'nullable|exists:nursing_note_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $updateData = ['note' => $request->note];
        if ($request->filled('note_type_id')) {
            $updateData['nursing_note_type_id'] = $request->note_type_id;
        }
        $note->update($updateData);

        return response()->json(['success' => true, 'message' => 'Note updated successfully.', 'note' => $note->fresh()]);
    }

    public function deleteNote($id)
    {
        $note = NursingNote::findOrFail($id);

        // Ownership + time-window check
        if (Auth::id() != $note->created_by) {
            return response()->json(['success' => false, 'message' => 'You can only delete your own notes.'], 403);
        }

        $editDuration = function_exists('appsettings') ? (appsettings('note_edit_duration') ?? 60) : 60;
        if (Carbon::parse($note->created_at)->addMinutes($editDuration)->isPast()) {
            return response()->json(['success' => false, 'message' => 'Delete window has expired.'], 403);
        }

        $note->delete();

        return response()->json(['success' => true, 'message' => 'Note deleted.']);
    }

    /* ══════════════════════════════════════════════════════════════
       VITALS
       ══════════════════════════════════════════════════════════════ */

    public function getPatientVitals($patientId)
    {
        $vitals = VitalSign::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($v) {
                return [
                    'id'             => $v->id,
                    'blood_pressure' => $v->blood_pressure,
                    'temp'           => $v->temp,
                    'heart_rate'     => $v->heart_rate,
                    'resp_rate'      => $v->resp_rate,
                    'weight'         => $v->weight ? (float)$v->weight : null,
                    'spo2'           => $v->spo2 ? (float)$v->spo2 : null,
                    'other_notes'    => $v->other_notes,
                    'taken_by'       => $v->taken_by ? userfullname($v->taken_by) : 'N/A',
                    'created_at'     => Carbon::parse($v->created_at)->format('h:i a, d M Y'),
                ];
            });

        return response()->json(['success' => true, 'vitals' => $vitals]);
    }

    public function saveVital(Request $request, $patientId)
    {
        $validator = Validator::make($request->all(), [
            'blood_pressure' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $vital = VitalSign::create([
                'patient_id'     => $patientId,
                'blood_pressure' => $request->blood_pressure,
                'temp'           => $request->temp,
                'heart_rate'     => $request->heart_rate,
                'resp_rate'      => $request->resp_rate,
                'weight'         => $request->weight,
                'spo2'           => $request->spo2,
                'other_notes'    => $request->other_notes,
                'taken_by'       => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vital signs recorded.',
                'vital'   => $vital,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /* ══════════════════════════════════════════════════════════════
       QUEUES
       ══════════════════════════════════════════════════════════════ */

    public function getQueueCounts()
    {
        $activeAnc = MaternityEnrollment::where('status', 'active')->count();

        $dueVisits = MaternityEnrollment::where('status', 'active')
            ->whereHas('ancVisits', function ($q) {
                $q->where('next_appointment', '<=', Carbon::today());
            })
            ->count();

        $upcomingEdd = MaternityEnrollment::where('status', 'active')
            ->whereNotNull('edd')
            ->where('edd', '<=', Carbon::today()->addWeeks(4))
            ->where('edd', '>=', Carbon::today())
            ->count();

        $postnatal = MaternityEnrollment::where('status', 'postnatal')->count();

        $overdueImmunization = MaternityBaby::where('status', 'alive')
            ->whereHas('patient', function ($q) {
                $q->where('dob', '<=', Carbon::today()->subWeeks(6));
            })
            ->count();

        $highRisk = MaternityEnrollment::whereIn('status', ['active', 'postnatal'])
            ->where('risk_level', 'high')
            ->count();

        return response()->json([
            'active_anc'          => $activeAnc,
            'due_visits'          => $dueVisits,
            'upcoming_edd'        => $upcomingEdd,
            'postnatal'           => $postnatal,
            'overdue_immunization'=> $overdueImmunization,
            'high_risk'           => $highRisk,
        ]);
    }

    public function getActiveAncQueue()
    {
        $enrollments = MaternityEnrollment::where('status', 'active')
            ->with('patient.user')
            ->orderBy('edd', 'asc')
            ->get()
            ->map(function ($e) {
                return [
                    'id'              => $e->id,
                    'patient_id'      => $e->patient_id,
                    'name'            => userfullname($e->patient->user_id),
                    'file_no'         => $e->patient->file_no,
                    'edd'             => $e->edd ? $e->edd->format('d M Y') : 'N/A',
                    'gestational_age' => $e->getCurrentGestationalAge(),
                    'risk_level'      => $e->risk_level,
                    'anc_visits'      => $e->ancVisits()->count(),
                    'photo'           => $e->patient->user->photo ?? 'avatar.png',
                ];
            });

        return response()->json($enrollments);
    }

    public function getDueVisitsQueue()
    {
        $enrollments = MaternityEnrollment::where('status', 'active')
            ->with('patient.user')
            ->whereHas('ancVisits', function ($q) {
                $q->where('next_appointment', '<=', Carbon::today())
                  ->orderBy('next_appointment', 'desc');
            })
            ->get()
            ->map(function ($e) {
                $lastVisit = $e->ancVisits()->orderBy('visit_date', 'desc')->first();
                return [
                    'id'               => $e->id,
                    'patient_id'       => $e->patient_id,
                    'name'             => userfullname($e->patient->user_id),
                    'file_no'          => $e->patient->file_no,
                    'next_appointment' => $lastVisit && $lastVisit->next_appointment ? $lastVisit->next_appointment->format('d M Y') : 'N/A',
                    'days_overdue'     => $lastVisit && $lastVisit->next_appointment ? max(0, $lastVisit->next_appointment->diffInDays(Carbon::today())) : 0,
                    'photo'            => $e->patient->user->photo ?? 'avatar.png',
                ];
            });

        return response()->json($enrollments);
    }

    public function getUpcomingEddQueue()
    {
        $enrollments = MaternityEnrollment::where('status', 'active')
            ->whereNotNull('edd')
            ->where('edd', '>=', Carbon::today())
            ->where('edd', '<=', Carbon::today()->addWeeks(4))
            ->with('patient.user')
            ->orderBy('edd', 'asc')
            ->get()
            ->map(function ($e) {
                return [
                    'id'             => $e->id,
                    'patient_id'     => $e->patient_id,
                    'name'           => userfullname($e->patient->user_id),
                    'file_no'        => $e->patient->file_no,
                    'edd'            => $e->edd->format('d M Y'),
                    'days_to_edd'    => Carbon::today()->diffInDays($e->edd),
                    'risk_level'     => $e->risk_level,
                    'photo'          => $e->patient->user->photo ?? 'avatar.png',
                ];
            });

        return response()->json($enrollments);
    }

    public function getPostnatalQueue()
    {
        $enrollments = MaternityEnrollment::where('status', 'postnatal')
            ->with(['patient.user', 'deliveryRecord', 'babies'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($e) {
                $deliveryDate = $e->deliveryRecord ? $e->deliveryRecord->delivery_date : null;
                return [
                    'id'              => $e->id,
                    'patient_id'      => $e->patient_id,
                    'name'            => userfullname($e->patient->user_id),
                    'file_no'         => $e->patient->file_no,
                    'delivery_date'   => $deliveryDate ? $deliveryDate->format('d M Y') : 'N/A',
                    'days_postpartum' => $deliveryDate ? $deliveryDate->diffInDays(Carbon::today()) : null,
                    'baby_count'      => $e->babies->count(),
                    'status'          => $e->status,
                    'photo'           => $e->patient->user->photo ?? 'avatar.png',
                ];
            });

        return response()->json($enrollments);
    }

    public function getOverdueImmunizationQueue()
    {
        $babies = MaternityBaby::where('status', 'alive')
            ->with(['patient.user', 'enrollment.patient.user'])
            ->get()
            ->filter(function ($baby) {
                if (!$baby->patient || !$baby->patient->dob) return false;
                $dob = Carbon::parse($baby->patient->dob);
                $ageWeeks = $dob->diffInWeeks(Carbon::today());
                if ($ageWeeks < 6) return false;

                $vaccineCount = ImmunizationRecord::where('patient_id', $baby->patient_id)
                    ->whereNull('deleted_at')->count();

                $expected = 3;
                if ($ageWeeks >= 6) $expected += 5;
                if ($ageWeeks >= 10) $expected += 4;
                if ($ageWeeks >= 14) $expected += 4;

                return $vaccineCount < $expected;
            })
            ->map(function ($baby) {
                return [
                    'baby_id'     => $baby->id,
                    'patient_id'  => $baby->patient_id,
                    'baby_name'   => userfullname($baby->patient->user_id),
                    'mother_name' => $baby->enrollment && $baby->enrollment->patient
                        ? userfullname($baby->enrollment->patient->user_id) : 'N/A',
                    'age'         => $this->formatAge($baby->patient->dob),
                    'file_no'     => $baby->patient->file_no,
                ];
            })->values();

        return response()->json($babies);
    }

    public function getHighRiskQueue()
    {
        $enrollments = MaternityEnrollment::whereIn('status', ['active', 'postnatal'])
            ->where('risk_level', 'high')
            ->with('patient.user')
            ->orderBy('edd', 'asc')
            ->get()
            ->map(function ($e) {
                return [
                    'id'              => $e->id,
                    'patient_id'      => $e->patient_id,
                    'name'            => userfullname($e->patient->user_id),
                    'file_no'         => $e->patient->file_no,
                    'risk_factors'    => $e->risk_factors,
                    'status'          => $e->status,
                    'edd'             => $e->edd ? $e->edd->format('d M Y') : 'N/A',
                    'gestational_age' => $e->getCurrentGestationalAge(),
                    'photo'           => $e->patient->user->photo ?? 'avatar.png',
                ];
            });

        return response()->json($enrollments);
    }

    /* ══════════════════════════════════════════════════════════════
       REPORTS
       ══════════════════════════════════════════════════════════════ */

    public function getReportsSummary()
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $yearStart = $now->copy()->startOfYear();

        return response()->json([
            'success' => true,
            'data' => [
                'total_enrollments' => MaternityEnrollment::count(),
                'active_enrollments'=> MaternityEnrollment::where('status', 'active')->count(),
                'deliveries_this_month' => DeliveryRecord::where('delivery_date', '>=', $monthStart)->count(),
                'deliveries_this_year'  => DeliveryRecord::where('delivery_date', '>=', $yearStart)->count(),
                'total_babies'      => MaternityBaby::count(),
                'high_risk_count'   => MaternityEnrollment::where('risk_level', 'high')->whereIn('status', ['active'])->count(),
                'completed'         => MaternityEnrollment::where('status', 'completed')->count(),
            ],
        ]);
    }

    public function getDeliveryStats()
    {
        $yearStart = Carbon::now()->startOfYear();

        $stats = DeliveryRecord::where('delivery_date', '>=', $yearStart)
            ->selectRaw('type_of_delivery, COUNT(*) as count')
            ->groupBy('type_of_delivery')
            ->pluck('count', 'type_of_delivery');

        $monthlyDeliveries = DeliveryRecord::where('delivery_date', '>=', $yearStart)
            ->selectRaw("MONTH(delivery_date) as month, COUNT(*) as count")
            ->groupBy(DB::raw('MONTH(delivery_date)'))
            ->pluck('count', 'month');

        return response()->json([
            'success' => true,
            'by_type' => $stats,
            'by_month' => $monthlyDeliveries,
        ]);
    }

    public function getImmunizationCoverage()
    {
        $totalBabies = MaternityBaby::where('status', 'alive')->count();
        if ($totalBabies === 0) {
            return response()->json(['success' => true, 'coverage' => []]);
        }

        $vaccines = ['BCG', 'OPV-0', 'HBV-0', 'Penta-1', 'Penta-2', 'Penta-3', 'Measles-1'];
        $coverage = [];

        foreach ($vaccines as $vaccine) {
            $given = ImmunizationRecord::whereIn('patient_id',
                MaternityBaby::where('status', 'alive')->pluck('patient_id')
            )->where('vaccine_name', $vaccine)->whereNull('deleted_at')->count();

            $coverage[$vaccine] = [
                'given' => $given,
                'total' => $totalBabies,
                'percentage' => round(($given / $totalBabies) * 100, 1),
            ];
        }

        return response()->json(['success' => true, 'coverage' => $coverage]);
    }

    public function getAncDefaulters()
    {
        $defaulters = MaternityEnrollment::where('status', 'active')
            ->with('patient.user')
            ->get()
            ->filter(function ($e) {
                $lastVisit = $e->ancVisits()->orderBy('visit_date', 'desc')->first();
                if (!$lastVisit || !$lastVisit->next_appointment) return false;
                return $lastVisit->next_appointment->isPast() && $lastVisit->next_appointment->diffInDays(Carbon::today()) > 7;
            })
            ->map(function ($e) {
                $lastVisit = $e->ancVisits()->orderBy('visit_date', 'desc')->first();
                return [
                    'id'               => $e->id,
                    'patient_id'       => $e->patient_id,
                    'name'             => userfullname($e->patient->user_id),
                    'file_no'          => $e->patient->file_no,
                    'phone'            => $e->patient->phone_no,
                    'last_visit'       => $lastVisit->visit_date->format('d M Y'),
                    'missed_date'      => $lastVisit->next_appointment->format('d M Y'),
                    'days_overdue'     => $lastVisit->next_appointment->diffInDays(Carbon::today()),
                ];
            })->values();

        return response()->json(['success' => true, 'defaulters' => $defaulters]);
    }

    public function getHighRiskRegister()
    {
        $register = MaternityEnrollment::where('risk_level', 'high')
            ->whereIn('status', ['active', 'postnatal'])
            ->with('patient.user')
            ->get()
            ->map(function ($e) {
                return [
                    'id'              => $e->id,
                    'patient_id'      => $e->patient_id,
                    'name'            => userfullname($e->patient->user_id),
                    'file_no'         => $e->patient->file_no,
                    'phone'           => $e->patient->phone_no,
                    'risk_factors'    => $e->risk_factors,
                    'edd'             => $e->edd ? $e->edd->format('d M Y') : 'N/A',
                    'gestational_age' => $e->getCurrentGestationalAge(),
                    'status'          => $e->status,
                    'anc_visits'      => $e->ancVisits()->count(),
                ];
            });

        return response()->json(['success' => true, 'register' => $register]);
    }

    /* ══════════════════════════════════════════════════════════════
       SERVICE SEARCH (for billing)
       ══════════════════════════════════════════════════════════════ */

    public function searchServices(Request $request)
    {
        $term = $request->get('term', '');
        $type = $request->get('type', 'all');

        if (strlen($term) < 2) return response()->json([]);

        $results = [];

        if (in_array($type, ['lab', 'all'])) {
            $labServices = service::where('name', 'like', "%{$term}%")
                ->whereHas('category', function ($q) {
                    $q->where('name', 'like', '%lab%');
                })
                ->limit(10)
                ->get(['id', 'name', 'price']);

            foreach ($labServices as $s) {
                $results[] = ['id' => $s->id, 'name' => $s->name, 'price' => $s->price, 'type' => 'lab'];
            }
        }

        if (in_array($type, ['imaging', 'all'])) {
            $imagingServices = service::where('name', 'like', "%{$term}%")
                ->whereHas('category', function ($q) {
                    $q->where('name', 'like', '%imag%')
                      ->orWhere('name', 'like', '%radiol%')
                      ->orWhere('name', 'like', '%scan%');
                })
                ->limit(10)
                ->get(['id', 'name', 'price']);

            foreach ($imagingServices as $s) {
                $results[] = ['id' => $s->id, 'name' => $s->name, 'price' => $s->price, 'type' => 'imaging'];
            }
        }

        if (in_array($type, ['product', 'all'])) {
            $products = Product::where('name', 'like', "%{$term}%")
                ->limit(10)
                ->get(['id', 'name', 'price']);

            foreach ($products as $p) {
                $results[] = ['id' => $p->id, 'name' => $p->name, 'price' => $p->price, 'type' => 'product'];
            }
        }

        return response()->json($results);
    }
}
