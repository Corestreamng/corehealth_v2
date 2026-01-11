<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\patient as PatientLowerCase;
use App\Models\AdmissionRequest;
use App\Models\Bed;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\Product;
use App\Models\service;
use App\Models\ServiceCategory;
use App\Models\ProductCategory;
use App\Models\VitalSign;
use App\Models\Encounter;
use App\Models\NursingNote;
use App\Models\NursingNoteType;
use App\Models\MedicationSchedule;
use App\Models\MedicationAdministration;
use App\Models\IntakeOutputPeriod;
use App\Models\IntakeOutputRecord;
use App\Models\InjectionAdministration;
use App\Models\ImmunizationRecord;
use App\Models\VaccineScheduleTemplate;
use App\Models\VaccineScheduleItem;
use App\Models\VaccineProductMapping;
use App\Models\PatientImmunizationSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Helpers\HmoHelper;
use Yajra\DataTables\DataTables;

class NursingWorkbenchController extends Controller{

    /**
     * Get patients with medication due (for medication-due queue).
    */
    public function getMedicationDueQueue(Request $request)
    {
        // Find all admitted patients with overdue or due medications
        $admissions = AdmissionRequest::with(['patient.user', 'bed'])
            ->where('discharged', 0)
            ->whereNotNull('bed_id')
            ->get();

        $results = $admissions->map(function ($admission) {
            $patient = $admission->patient;

            // Get overdue medications (scheduled_time < now, not administered)
            $overdueMeds = MedicationSchedule::where('patient_id', $patient->id)
                ->where('scheduled_time', '<', \Carbon\Carbon::now())
                ->whereDoesntHave('administrations', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->count();

            // Get due medications (scheduled_time <= now, not administered)
            $dueMeds = MedicationSchedule::where('patient_id', $patient->id)
                ->where('scheduled_time', '<=', \Carbon\Carbon::now())
                ->whereDoesntHave('administrations', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->count();

            if ($overdueMeds > 0 || $dueMeds > 0) {
                return [
                    'admission_id' => $admission->id,
                    'patient_id' => $patient->id,
                    'name' => userfullname($patient->user_id),
                    'file_no' => $patient->file_no,
                    'bed' => $admission->bed ? $admission->bed->ward . ' - ' . $admission->bed->name : 'N/A',
                    'medication_count' => $dueMeds,
                    'overdue' => $overdueMeds > 0,
                ];
            }
            return null;
        })->filter()->values();

        return response()->json($results);
    }

    /**
     * Safely parse a date string to Carbon instance.
     * Handles multiple date formats commonly used in the system.
     *
     * @param string|null $dateString
     * @return Carbon|null
     */
    private function safeParseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        // If already a Carbon instance, return it
        if ($dateString instanceof Carbon) {
            return $dateString;
        }

        // Try common date formats
        $formats = [
            'Y-m-d',           // 1976-06-19
            'd/m/Y',           // 19/6/1976 or 19/06/1976
            'd-m-Y',           // 19-6-1976 or 19-06-1976
            'm/d/Y',           // 6/19/1976
            'Y-m-d H:i:s',     // 1976-06-19 00:00:00
            'd/m/Y H:i:s',     // 19/06/1976 00:00:00
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateString);
                if ($date && $date->format($format) === $dateString) {
                    return $date;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback: try Carbon::parse with error handling
        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate age from date of birth string.
     *
     * @param string|null $dob
     * @return string
     */
    private function calculateAge($dob)
    {
        $date = $this->safeParseDate($dob);
        return $date ? $date->age : 'N/A';
    }

    /**
     * Display the nursing workbench main page.
     */
    public function index()
    {
        // Check permission
        if (!Auth::user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'NURSE'])) {
            abort(403, 'Unauthorized access to Nursing Workbench');
        }

        return view('admin.nursing.workbench');
    }

    // =====================================
    // PATIENT SEARCH & QUEUE METHODS
    // =====================================

    /**
     * Search for patients (AJAX).
     */
    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $patients = PatientLowerCase::with(['user', 'hmo'])
            ->where(function ($query) use ($term) {
                $query->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('surname', 'like', "%{$term}%")
                        ->orWhere('firstname', 'like', "%{$term}%")
                        ->orWhere('othername', 'like', "%{$term}%");
                })
                ->orWhere('file_no', 'like', "%{$term}%")
                ->orWhere('phone_no', 'like', "%{$term}%");
            })
            ->limit(15)
            ->get();

        $results = $patients->map(function ($patient) {
            // Check if patient is admitted
            $isAdmitted = AdmissionRequest::where('patient_id', $patient->id)
                ->where('discharged', 0)
                ->whereNotNull('bed_id')
                ->exists();

            // Get pending medication count
            $pendingMeds = MedicationSchedule::where('patient_id', $patient->id)
                ->whereDate('scheduled_time', Carbon::today())
                ->whereDoesntHave('administrations', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->count();

            return [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'age' => $this->calculateAge($patient->dob),
                'gender' => $patient->gender ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'photo' => $patient->user->photo ?? 'avatar.png',
                'hmo' => $patient->hmo ? $patient->hmo->name : null,
                'is_admitted' => $isAdmitted,
                'pending_meds' => $pendingMeds,
            ];
        });

        return response()->json($results);
    }

    /**
     * Get admitted patients list for queue.
     */
    public function getAdmittedPatients(Request $request)
    {
        $query = AdmissionRequest::with([
                'patient.user',
                'patient.hmo',
                'bed',
                'doctor'
            ])
            ->where('discharged', 0)
            ->whereNotNull('bed_id')
            ->orderBy('bed_assign_date', 'desc');

        // Filter by ward if provided
        if ($request->has('ward') && $request->ward !== 'all') {
            $query->whereHas('bed', function ($q) use ($request) {
                $q->where('ward', $request->ward);
            });
        }

        $admissions = $query->get();

        $results = $admissions->map(function ($admission) {
            $patient = $admission->patient;

            // Calculate days admitted
            $daysAdmitted = $admission->bed_assign_date
                ? Carbon::parse($admission->bed_assign_date)->diffInDays(Carbon::now())
                : 0;

            // Get pending medications for today
            $pendingMeds = MedicationSchedule::where('patient_id', $patient->id)
                ->whereDate('scheduled_time', Carbon::today())
                ->where('scheduled_time', '<=', Carbon::now())
                ->whereDoesntHave('administrations', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->count();

            // Get overdue medications (past due but not administered)
            $overdueMeds = MedicationSchedule::where('patient_id', $patient->id)
                ->where('scheduled_time', '<', Carbon::now())
                ->whereDoesntHave('administrations', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->count();

            // Check if vitals are due (no vitals in last 4 hours)
            $lastVital = VitalSign::where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')
                ->first();
            $vitalsDue = !$lastVital || Carbon::parse($lastVital->created_at)->diffInHours(Carbon::now()) >= 4;

            return [
                'admission_id' => $admission->id,
                'patient_id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'age' => $this->calculateAge($patient->dob) . ($this->calculateAge($patient->dob) !== 'N/A' ? 'y' : ''),
                'gender' => $patient->gender ?? 'N/A',
                'photo' => $patient->user->photo ?? 'avatar.png',
                'hmo' => $patient->hmo ? $patient->hmo->name : null,
                'bed' => $admission->bed ? $admission->bed->ward . ' - ' . $admission->bed->name : 'N/A',
                'ward' => $admission->bed ? $admission->bed->ward : 'N/A',
                'bed_name' => $admission->bed ? $admission->bed->name : 'N/A',
                'days_admitted' => $daysAdmitted,
                'admitted_date' => $admission->bed_assign_date ? Carbon::parse($admission->bed_assign_date)->format('d M Y') : 'N/A',
                'doctor' => $admission->doctor ? userfullname($admission->doctor->id) : 'N/A',
                'admission_reason' => $admission->admission_reason ?? 'N/A',
                'pending_meds' => $pendingMeds,
                'overdue_meds' => $overdueMeds,
                'vitals_due' => $vitalsDue,
                'priority' => $admission->priority ?? 'normal',
            ];
        });

        return response()->json($results);
    }

    /**
     * Get queue counts for dashboard widgets.
     */
    public function getQueueCounts()
    {
        // Admitted patients count
        $admittedCount = AdmissionRequest::where('discharged', 0)
            ->whereNotNull('bed_id')
            ->count();

        // Patients with overdue medications
        $overdueMedsCount = MedicationSchedule::where('scheduled_time', '<', Carbon::now())
            ->whereDoesntHave('administrations', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->distinct('patient_id')
            ->count('patient_id');

        // Vitals queue (requested but not taken)
        $vitalsQueueCount = VitalSign::where('status', 1)->count();

        // Critical patients (based on priority)
        $criticalCount = AdmissionRequest::where('discharged', 0)
            ->whereNotNull('bed_id')
            ->where('priority', 'critical')
            ->count();

        return response()->json([
            'admitted' => $admittedCount,
            'overdue_meds' => $overdueMedsCount,
            'vitals_queue' => $vitalsQueueCount,
            'critical' => $criticalCount,
            'total' => $admittedCount,
        ]);
    }

    /**
     * Get list of wards for filtering.
     */
    public function getWards()
    {
        $wards = Bed::select('ward')
            ->distinct()
            ->whereNotNull('ward')
            ->pluck('ward');

        return response()->json($wards);
    }

    // =====================================
    // PATIENT DETAILS & CONTEXT
    // =====================================

    /**
     * Get patient details for the right panel.
     */
    public function getPatientDetails($patientId)
    {
        $patient = PatientLowerCase::with(['user', 'hmo.scheme'])->findOrFail($patientId);

        // Calculate detailed age
        $ageText = 'N/A';
        $dob = $this->safeParseDate($patient->dob);
        if ($dob) {
            $now = Carbon::now();
            $years = $dob->diffInYears($now);
            $months = $dob->copy()->addYears($years)->diffInMonths($now);
            $days = $dob->copy()->addYears($years)->addMonths($months)->diffInDays($now);

            $ageParts = [];
            if ($years > 0) $ageParts[] = $years . 'y';
            if ($months > 0) $ageParts[] = $months . 'm';
            if ($days > 0) $ageParts[] = $days . 'd';
            $ageText = !empty($ageParts) ? implode(' ', $ageParts) : '0d';
        }

        // Check admission status
        $admission = AdmissionRequest::with('bed')
            ->where('patient_id', $patientId)
            ->where('discharged', 0)
            ->whereNotNull('bed_id')
            ->first();

        // Get recent vitals
        $lastVitals = VitalSign::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->first();

        // Get latest nursing note
        $lastNursingNote = NursingNote::with(['createdBy', 'type'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->first();

        // Get latest doctor note (Encounter)
        $lastDoctorNote = Encounter::with('doctor')
            ->where('patient_id', $patientId)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'id' => $patient->id,
            'user_id' => $patient->user_id,
            'name' => userfullname($patient->user_id),
            'file_no' => $patient->file_no,
            'age' => $ageText,
            'dob' => $this->safeParseDate($patient->dob) ? $this->safeParseDate($patient->dob)->format('d M Y') : 'N/A',
            'gender' => $patient->gender ?? 'N/A',
            'blood_group' => $patient->blood_group ?? 'N/A',
            'genotype' => $patient->genotype ?? 'N/A',
            'phone' => $patient->phone_no ?? 'N/A',
            'address' => $patient->address ?? 'N/A',
            'photo' => $patient->user->photo ?? 'avatar.png',
            'hmo' => $patient->hmo ? $patient->hmo->name : 'N/A',
            'hmo_category' => $patient->hmo && $patient->hmo->scheme ? $patient->hmo->scheme->name : 'N/A',
            'hmo_no' => $patient->hmo_no ?? 'N/A',
            'allergies' => $patient->allergies ?? [],
            'medical_history' => $patient->medical_history ?? 'N/A',
            'is_admitted' => $admission ? true : false,
            'admission' => $admission ? [
                'id' => $admission->id,
                'bed' => $admission->bed ? $admission->bed->ward . ' - ' . $admission->bed->name : 'N/A',
                'days_admitted' => $admission->bed_assign_date ? Carbon::parse($admission->bed_assign_date)->diffInDays(Carbon::now()) : 0,
                'admitted_date' => $admission->bed_assign_date ? Carbon::parse($admission->bed_assign_date)->format('d M Y') : 'N/A',
                'reason' => $admission->admission_reason ?? 'N/A',
            ] : null,
            'last_vitals' => $lastVitals ? [
                'bp' => $lastVitals->blood_pressure ?? 'N/A',
                'temp' => $lastVitals->temp ?? 'N/A',
                'heart_rate' => $lastVitals->heart_rate ?? 'N/A',
                'resp_rate' => $lastVitals->resp_rate ?? 'N/A',
                'time' => Carbon::parse($lastVitals->created_at)->format('h:i a, d M'),
            ] : null,
            'latest_nurse_note' => $lastNursingNote ? [
                'note' => \Illuminate\Support\Str::limit(strip_tags($lastNursingNote->note), 150),
                'type' => $lastNursingNote->type ? $lastNursingNote->type->name : 'General',
                'created_by' => $lastNursingNote->createdBy ? userfullname($lastNursingNote->createdBy->id) : 'N/A',
                'created_at' => Carbon::parse($lastNursingNote->created_at)->format('h:i a, d M Y'),
                'time_ago' => Carbon::parse($lastNursingNote->created_at)->diffForHumans(),
            ] : null,
            'latest_doctor_note' => $lastDoctorNote ? [
                'note' => \Illuminate\Support\Str::limit(strip_tags($lastDoctorNote->notes), 150),
                'created_by' => $lastDoctorNote->doctor ? userfullname($lastDoctorNote->doctor_id) : 'N/A',
                'created_at' => Carbon::parse($lastDoctorNote->created_at)->format('h:i a, d M Y'),
                'time_ago' => Carbon::parse($lastDoctorNote->created_at)->diffForHumans(),
            ] : null,
        ]);
    }

    /**
     * Get patient's recent vitals.
     */
    public function getPatientVitals($patientId, Request $request)
    {
        $limit = $request->get('limit', 10);

        $vitals = VitalSign::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $results = $vitals->map(function ($v) {
            return [
                'id' => $v->id,
                'blood_pressure' => $v->blood_pressure,
                'temp' => $v->temp,
                'heart_rate' => $v->heart_rate,
                'resp_rate' => $v->resp_rate,
                'other_notes' => $v->other_notes,
                'time_taken' => $v->time_taken ? Carbon::parse($v->time_taken)->format('h:i a, d M Y') : null,
                'taken_by' => $v->taken_by ? userfullname($v->taken_by) : 'N/A',
                'created_at' => Carbon::parse($v->created_at)->format('h:i a, d M Y'),
            ];
        });

        return response()->json($results);
    }

    /**
     * Get patient's active medication orders (for quick view).
     */
    public function getPatientOrders($patientId)
    {
        $patient = PatientLowerCase::findOrFail($patientId);

        // Get today's medication schedules
        $todaySchedules = MedicationSchedule::with(['productOrServiceRequest.product'])
            ->where('patient_id', $patientId)
            ->whereDate('scheduled_time', Carbon::today())
            ->orderBy('scheduled_time')
            ->get();

        $orders = $todaySchedules->map(function ($schedule) {
            $admin = MedicationAdministration::where('schedule_id', $schedule->id)
                ->whereNull('deleted_at')
                ->first();

            return [
                'id' => $schedule->id,
                'drug_name' => $schedule->productOrServiceRequest->product->product_name ?? 'Unknown',
                'dose' => $schedule->dose,
                'route' => $schedule->route,
                'scheduled_time' => Carbon::parse($schedule->scheduled_time)->format('h:i a'),
                'status' => $admin ? 'administered' : (Carbon::parse($schedule->scheduled_time)->isPast() ? 'overdue' : 'pending'),
                'administered_at' => $admin ? Carbon::parse($admin->administered_at)->format('h:i a') : null,
            ];
        });

        return response()->json($orders);
    }

    // =====================================
    // INJECTION SERVICE
    // =====================================

    /**
     * Search for injectable products.
     */
    public function searchInjectables(Request $request)
    {
        $term = $request->get('term', '');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        // Search products - you may want to filter by a specific category for injectables
        $products = Product::with(['price', 'stock', 'category'])
            ->where(function ($q) use ($term) {
                $q->where('product_name', 'like', "%{$term}%")
                  ->orWhere('product_code', 'like', "%{$term}%");
            })
            ->where('status', 1)
            ->limit(20)
            ->get();

        $results = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->product_name,
                'code' => $product->product_code,
                'price' => $product->price ? $product->price->selling_price : 0,
                'stock' => $product->stock ? $product->stock->current_quantity : 0,
                'category' => $product->category ? $product->category->category_name : 'N/A',
            ];
        });

        return response()->json($results);
    }

    /**
     * Get patient's injection history.
     */
    public function getInjections($patientId, Request $request)
    {
        $limit = $request->get('limit', 20);

        $injections = InjectionAdministration::with(['product', 'administeredBy'])
            ->where('patient_id', $patientId)
            ->orderBy('administered_at', 'desc')
            ->limit($limit)
            ->get();

        $results = $injections->map(function ($inj) {
            return [
                'id' => $inj->id,
                'product_name' => $inj->product ? $inj->product->product_name : 'N/A',
                'dose' => $inj->dose,
                'route' => $inj->route,
                'site' => $inj->site,
                'administered_at' => Carbon::parse($inj->administered_at)->format('h:i a, d M Y'),
                'administered_by' => userfullname($inj->administered_by),
                'batch_number' => $inj->batch_number,
                'notes' => $inj->notes,
            ];
        });

        return response()->json($results);
    }

    /**
     * Administer an injection (creates billing record + injection record).
     */
    public function administerInjection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.dose' => 'required|string|max:100',
            'products.*.payable_amount' => 'nullable|numeric',
            'products.*.claims_amount' => 'nullable|numeric',
            'products.*.coverage_mode' => 'nullable|string',
            'route' => 'required|in:IM,IV,SC,ID',
            'site' => 'nullable|string|max:100',
            'administered_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $patient = PatientLowerCase::findOrFail($request->patient_id);
            $injections = [];

            // Process each product
            foreach ($request->products as $productData) {
                $product = Product::with('price')->findOrFail($productData['product_id']);

                // Create ProductOrServiceRequest for billing
                $billReq = new ProductOrServiceRequest();
                $billReq->user_id = $patient->user_id;
                $billReq->staff_user_id = Auth::id();
                $billReq->product_id = $product->id;
                $billReq->qty = 1;

                // Use provided HMO tariff data from frontend
                if (isset($productData['payable_amount'])) {
                    $billReq->payable_amount = $productData['payable_amount'];
                    $billReq->claims_amount = $productData['claims_amount'] ?? 0;
                    $billReq->coverage_mode = $productData['coverage_mode'] ?? 'FULL_PAYMENT';
                } else {
                    // Fallback to applying HMO tariff
                    try {
                        $hmoData = HmoHelper::applyHmoTariff($patient->id, $product->id, null);
                        if ($hmoData) {
                            $billReq->payable_amount = $hmoData['payable_amount'];
                            $billReq->claims_amount = $hmoData['claims_amount'];
                            $billReq->coverage_mode = $hmoData['coverage_mode'];
                            $billReq->validation_status = $hmoData['validation_status'];
                        }
                    } catch (\Exception $e) {
                        // If no HMO tariff, use regular price
                        $billReq->payable_amount = $product->price ? $product->price->selling_price : 0;
                    }
                }

                $billReq->save();

                // Create injection administration record
                $injection = InjectionAdministration::create([
                    'patient_id' => $patient->id,
                    'product_id' => $product->id,
                    'product_or_service_request_id' => $billReq->id,
                    'dose' => $productData['dose'],
                    'route' => $request->route,
                    'site' => $request->site,
                    'administered_at' => $request->administered_at,
                    'administered_by' => Auth::id(),
                ]);

                $injections[] = $injection;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($injections) > 1
                    ? count($injections) . ' injections administered successfully'
                    : 'Injection administered successfully',
                'injections' => $injections,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error administering injection: ' . $e->getMessage()
            ], 500);
        }
    }

    // =====================================
    // IMMUNIZATION MODULE
    // =====================================

    /**
     * Get vaccines list for search.
     */
    public function getVaccines(Request $request)
    {
        $term = $request->get('term', '');

        // You may want to filter by a specific category for vaccines
        $query = Product::with(['price', 'stock', 'category'])
            ->where('status', 1);

        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('product_name', 'like', "%{$term}%")
                  ->orWhere('product_code', 'like', "%{$term}%");
            });
        }

        $products = $query->limit(30)->get();

        $results = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->product_name,
                'code' => $product->product_code,
                'price' => $product->price ? $product->price->selling_price : 0,
                'stock' => $product->stock ? $product->stock->current_quantity : 0,
                'category' => $product->category ? $product->category->category_name : 'N/A',
            ];
        });

        return response()->json($results);
    }

    /**
     * Get patient's immunization records.
     */
    public function getImmunizations($patientId, Request $request)
    {
        $limit = $request->get('limit', 50);

        $immunizations = ImmunizationRecord::with(['product', 'administeredBy'])
            ->where('patient_id', $patientId)
            ->orderBy('administered_at', 'desc')
            ->limit($limit)
            ->get();

        $results = $immunizations->map(function ($imm) {
            return [
                'id' => $imm->id,
                'vaccine_name' => $imm->vaccine_name,
                'product_name' => $imm->product ? $imm->product->product_name : 'N/A',
                'dose_number' => $imm->dose_number,
                'dose' => $imm->dose,
                'route' => $imm->route,
                'site' => $imm->site,
                'administered_at' => Carbon::parse($imm->administered_at)->format('d M Y'),
                'administered_by' => userfullname($imm->administered_by),
                'batch_number' => $imm->batch_number,
                'manufacturer' => $imm->manufacturer,
                'next_due_date' => $imm->next_due_date ? Carbon::parse($imm->next_due_date)->format('d M Y') : null,
                'adverse_reaction' => $imm->adverse_reaction,
                'notes' => $imm->notes,
            ];
        });

        return response()->json($results);
    }

    /**
     * Get immunization schedule for a patient.
     * Returns a structured view of which vaccines are given, due, or pending.
     */
    public function getImmunizationSchedule($patientId)
    {
        $patient = PatientLowerCase::findOrFail($patientId);

        // Standard immunization schedule (can be made configurable)
        $schedule = [
            ['vaccine' => 'BCG', 'ages' => ['At Birth']],
            ['vaccine' => 'OPV', 'ages' => ['At Birth', '6 weeks', '10 weeks', '14 weeks']],
            ['vaccine' => 'Hepatitis B', 'ages' => ['At Birth', '6 weeks', '14 weeks']],
            ['vaccine' => 'Pentavalent (DPT-HepB-Hib)', 'ages' => ['6 weeks', '10 weeks', '14 weeks']],
            ['vaccine' => 'PCV', 'ages' => ['6 weeks', '10 weeks', '14 weeks']],
            ['vaccine' => 'Rotavirus', 'ages' => ['6 weeks', '10 weeks']],
            ['vaccine' => 'IPV', 'ages' => ['14 weeks']],
            ['vaccine' => 'Vitamin A', 'ages' => ['6 months', '12 months', '18 months']],
            ['vaccine' => 'Measles', 'ages' => ['9 months', '15 months']],
            ['vaccine' => 'Yellow Fever', 'ages' => ['9 months']],
            ['vaccine' => 'Meningitis', 'ages' => ['9 months']],
        ];

        // Get all immunizations for this patient
        $given = ImmunizationRecord::where('patient_id', $patientId)
            ->get()
            ->groupBy('vaccine_name');

        $scheduleWithStatus = collect($schedule)->map(function ($item) use ($given) {
            $vaccineRecords = $given->get($item['vaccine'], collect());

            $doses = collect($item['ages'])->map(function ($age, $index) use ($vaccineRecords, $item) {
                $doseNum = $index + 1;
                $record = $vaccineRecords->firstWhere('dose_number', $doseNum);

                return [
                    'age' => $age,
                    'dose_number' => $doseNum,
                    'status' => $record ? 'given' : 'pending',
                    'given_date' => $record ? Carbon::parse($record->administered_at)->format('d M Y') : null,
                ];
            });

            return [
                'vaccine' => $item['vaccine'],
                'doses' => $doses,
                'total_doses' => count($item['ages']),
                'doses_given' => $vaccineRecords->count(),
            ];
        });

        return response()->json([
            'patient_age' => $this->safeParseDate($patient->dob) ? $this->safeParseDate($patient->dob)->age : null,
            'schedule' => $scheduleWithStatus,
        ]);
    }

    /**
     * Administer an immunization.
     */
    public function administerImmunization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.dose_number' => 'required|string|max:100',
            'products.*.payable_amount' => 'nullable|numeric',
            'products.*.claims_amount' => 'nullable|numeric',
            'products.*.coverage_mode' => 'nullable|string',
            'route' => 'required|in:IM,SC,Oral,ID',
            'site' => 'nullable|string|max:100',
            'administered_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $patient = PatientLowerCase::findOrFail($request->patient_id);
            $immunizations = [];

            // Process each vaccine product
            foreach ($request->products as $productData) {
                $product = Product::with('price')->findOrFail($productData['product_id']);

                // Create ProductOrServiceRequest for billing
                $billReq = new ProductOrServiceRequest();
                $billReq->user_id = $patient->user_id;
                $billReq->staff_user_id = Auth::id();
                $billReq->product_id = $product->id;
                $billReq->qty = 1;

                // Use provided HMO tariff data from frontend
                if (isset($productData['payable_amount'])) {
                    $billReq->payable_amount = $productData['payable_amount'];
                    $billReq->claims_amount = $productData['claims_amount'] ?? 0;
                    $billReq->coverage_mode = $productData['coverage_mode'] ?? 'FULL_PAYMENT';
                } else {
                    // Fallback to applying HMO tariff
                    try {
                        $hmoData = HmoHelper::applyHmoTariff($patient->id, $product->id, null);
                        if ($hmoData) {
                            $billReq->payable_amount = $hmoData['payable_amount'];
                            $billReq->claims_amount = $hmoData['claims_amount'];
                            $billReq->coverage_mode = $hmoData['coverage_mode'];
                            $billReq->validation_status = $hmoData['validation_status'];
                        }
                    } catch (\Exception $e) {
                        // If no HMO tariff, use regular price
                        $billReq->payable_amount = $product->price ? $product->price->selling_price : 0;
                    }
                }

                $billReq->save();

                // Create immunization record
                $immunization = ImmunizationRecord::create([
                    'patient_id' => $patient->id,
                    'product_id' => $product->id,
                    'product_or_service_request_id' => $billReq->id,
                    'vaccine_name' => $product->product_name,  // Use product name as vaccine name
                    'dose_number' => $productData['dose_number'],
                    'route' => $request->route,
                    'site' => $request->site,
                    'administered_at' => $request->administered_at,
                    'administered_by' => Auth::id(),
                ]);

                $immunizations[] = $immunization;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($immunizations) > 1
                    ? count($immunizations) . ' immunizations administered successfully'
                    : 'Immunization administered successfully',
                'immunizations' => $immunizations,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error administering immunization: ' . $e->getMessage()
            ], 500);
        }
    }

    // =====================================
    // NURSE BILLING MODULE
    // =====================================

    /**
     * Search for services.
     */
    public function searchServices(Request $request)
    {
        $term = $request->get('term', '');
        $categoryId = $request->get('category_id');

        $query = service::with(['price', 'category'])
            ->where('status', 1);

        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('service_name', 'like', "%{$term}%")
                  ->orWhere('service_code', 'like', "%{$term}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $services = $query->limit(30)->get();

        $results = $services->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->service_name,
                'code' => $service->service_code,
                'price' => $service->price ? $service->price->amount : 0,
                'category' => $service->category ? $service->category->category_name : 'N/A',
            ];
        });

        return response()->json($results);
    }

    /**
     * Search for products (consumables).
     */
    public function searchProducts(Request $request)
    {
        $term = $request->get('term', '');
        $categoryId = $request->get('category_id');

        $query = Product::with(['price', 'stock', 'category'])
            ->where('status', 1);

        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('product_name', 'like', "%{$term}%")
                  ->orWhere('product_code', 'like', "%{$term}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->limit(30)->get();

        $results = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->product_name,
                'code' => $product->product_code,
                'price' => $product->price ? $product->price->selling_price : 0,
                'stock' => $product->stock ? $product->stock->current_quantity : 0,
                'category' => $product->category ? $product->category->category_name : 'N/A',
            ];
        });

        return response()->json($results);
    }

    /**
     * Get service categories.
     */
    public function getServiceCategories()
    {
        $categories = ServiceCategory::where('status', 1)
            ->orderBy('category_name')
            ->get(['id', 'category_name', 'category_code']);

        return response()->json($categories);
    }

    /**
     * Get product categories.
     */
    public function getProductCategories()
    {
        $categories = ProductCategory::orderBy('category_name')
            ->get(['id', 'category_name', 'category_code']);

        return response()->json($categories);
    }

    /**
     * Get patient's pending bills (not yet paid/processed by biller).
     */
    public function getPendingBills($patientId)
    {
        $patient = PatientLowerCase::findOrFail($patientId);

        $bills = ProductOrServiceRequest::with(['product', 'service', 'staff'])
            ->where('user_id', $patient->user_id)
            ->whereNull('payment_id') // Not yet paid
            ->orderBy('created_at', 'desc')
            ->get();

        $results = $bills->map(function ($bill) {
            $itemName = 'N/A';
            $type = 'unknown';

            if ($bill->product_id && $bill->product) {
                $itemName = $bill->product->product_name;
                $type = 'product';
            } elseif ($bill->service_id && $bill->service) {
                $itemName = $bill->service->service_name;
                $type = 'service';
            }

            return [
                'id' => $bill->id,
                'item_name' => $itemName,
                'type' => $type,
                'qty' => $bill->qty ?? 1,
                'payable_amount' => $bill->payable_amount,
                'claims_amount' => $bill->claims_amount,
                'coverage_mode' => $bill->coverage_mode,
                'validation_status' => $bill->validation_status,
                'created_at' => Carbon::parse($bill->created_at)->format('h:i a, d M Y'),
                'added_by' => $bill->staff_user_id ? userfullname($bill->staff_user_id) : 'N/A',
                'staff_user_id' => $bill->staff_user_id,
                'can_delete' => $bill->staff_user_id === Auth::id(),
            ];
        });

        return response()->json($results);
    }

    /**
     * Add a service bill for a patient.
     */
    public function addServiceBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'service_id' => 'required|exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $patient = PatientLowerCase::findOrFail($request->patient_id);
            $service = service::with('price')->findOrFail($request->service_id);

            $billReq = new ProductOrServiceRequest();
            $billReq->user_id = $patient->user_id;
            $billReq->staff_user_id = Auth::id();
            $billReq->service_id = $service->id;

            // Apply HMO tariff if applicable
            try {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, null, $service->id);
                if ($hmoData) {
                    $billReq->payable_amount = $hmoData['payable_amount'];
                    $billReq->claims_amount = $hmoData['claims_amount'];
                    $billReq->coverage_mode = $hmoData['coverage_mode'];
                    $billReq->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                // If no HMO tariff, use regular price
                $billReq->payable_amount = $service->price ? $service->price->amount : 0;
            }

            $billReq->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Service added to bill successfully',
                'bill' => $billReq,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error adding service: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a consumable (product) bill for a patient.
     */
    public function addConsumableBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $patient = PatientLowerCase::findOrFail($request->patient_id);
            $product = Product::with('price')->findOrFail($request->product_id);

            $billReq = new ProductOrServiceRequest();
            $billReq->user_id = $patient->user_id;
            $billReq->staff_user_id = Auth::id();
            $billReq->product_id = $product->id;
            $billReq->qty = $request->qty;

            // Apply HMO tariff if applicable
            try {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, $product->id, null);
                if ($hmoData) {
                    $billReq->payable_amount = $hmoData['payable_amount'] * $request->qty;
                    $billReq->claims_amount = $hmoData['claims_amount'] * $request->qty;
                    $billReq->coverage_mode = $hmoData['coverage_mode'];
                    $billReq->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                // If no HMO tariff, use regular price
                $unitPrice = $product->price ? $product->price->selling_price : 0;
                $billReq->payable_amount = $unitPrice * $request->qty;
            }

            $billReq->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Consumable added to bill successfully',
                'bill' => $billReq,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error adding consumable: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a bill item (if not yet paid and added by current user).
     */
    public function removeBillItem($id)
    {
        try {
            $bill = ProductOrServiceRequest::findOrFail($id);

            // Check if already paid
            if ($bill->payment_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove a paid item'
                ], 400);
            }

            // Check if the current user added this item
            if ($bill->staff_user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only remove items that you added'
                ], 403);
            }

            $bill->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bill item removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing item: ' . $e->getMessage()
            ], 500);
        }
    }

    // =====================================
    // NURSING NOTES MODULE
    // =====================================

    /**
     * Get patient's nursing notes.
     */
    public function getNursingNotes($patientId, Request $request)
    {
        $typeId = $request->get('type_id');

        $query = NursingNote::with(['type', 'createdBy'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc');

        if ($typeId) {
            $query->where('nursing_note_type_id', $typeId);
        }

        return \Yajra\DataTables\Facades\DataTables::of($query)
            ->addColumn('info', function ($note) {
                // Determine badge color based on note type or status
                $badgeColor = 'bg-primary';

                // Format Date
                $createdAt = \Carbon\Carbon::parse($note->created_at)->format('h:i a, d M Y');
                $creatorName = $note->createdBy ? userfullname($note->createdBy->id) : 'N/A';
                $typeName = $note->type ? $note->type->name : 'N/A';

                $html = '<div class="card mb-3 nursing-note-card shadow-sm">';

                // Header
                $html .= '<div class="card-header bg-light d-flex justify-content-between align-items-center py-2">';
                $html .= '<div>';
                $html .= '<span class="badge ' . $badgeColor . ' me-2">' . htmlspecialchars($typeName) . '</span>';
                $html .= '<small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $createdAt . '</small>';
                $html .= '</div>';
                $html .= '<div>';
                $html .= '<small class="text-muted">By: <span class="fw-bold text-dark">' . htmlspecialchars($creatorName) . '</span></small>';
                $html .= '</div>';
                $html .= '</div>'; // End Header

                // Body
                $html .= '<div class="card-body p-3">';
                $html .= '<div class="note-content">' . $note->note . '</div>';
                $html .= '</div>'; // End Body

                // Footer (Actions) - Edit Button Logic
                $canEdit = false;
                if ($note->created_at) {
                    $createdDate = \Carbon\Carbon::parse($note->created_at);
                    $editDuration = appsettings('note_edit_duration') ?? 60; // Default 60 minutes
                    $editDeadline = $createdDate->copy()->addMinutes($editDuration);
                    $canEdit = \Carbon\Carbon::now()->lessThanOrEqualTo($editDeadline);
                }

                // Check if user is creator
                $isCreator = Auth::id() == $note->created_by;

                if ($canEdit && $isCreator) {
                     // Escape content for data attributes
                     $noteContentEscaped = htmlspecialchars($note->note, ENT_QUOTES);
                     $typeId = $note->nursing_note_type_id;

                     $html .= '<div class="card-footer bg-white border-top-0 d-flex justify-content-end pt-0 pb-3">';
                     $html .= "<button class='btn btn-sm btn-outline-primary edit-note-btn'
                                  onclick='openEditNoteModal(this)'
                                  data-id='{$note->id}'
                                  data-type-id='{$typeId}'
                                  data-content='{$noteContentEscaped}'>";
                     $html .= '<i class="mdi mdi-pencil"></i> Edit Note';
                     $html .= '</button>';
                     $html .= '</div>';
                }

                $html .= '</div>'; // End Card

                return $html;
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    /**
     * Get nursing note types.
     */
    public function getNoteTypes()
    {
        $types = NursingNoteType::all(['id', 'name', 'template']);
        return response()->json($types);
    }

    /**
     * Save a nursing note.
     */
    public function saveNursingNote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'note_type_id' => 'required|exists:nursing_note_types,id',
            'note' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $patientId = $request->patient_id;

        try {
            // Check if there's an existing open note of this type
            $existingNote = NursingNote::where('patient_id', $patientId)
                ->where('nursing_note_type_id', $request->note_type_id)
                ->where('completed', false)
                ->first();

            if ($existingNote) {
                $existingNote->update([
                    'note' => $request->note,
                    'updated_by' => Auth::id(),
                    'completed' => true,
                ]);
                $note = $existingNote;
                $message = 'Nursing note updated successfully';
            } else {
                $note = NursingNote::create([
                    'patient_id' => $patientId,
                    'nursing_note_type_id' => $request->note_type_id,
                    'note' => $request->note,
                    'created_by' => Auth::id(),
                    'completed' => true,
                ]);
                $message = 'Nursing note created successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'note' => $note,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a nursing note.
     */
    public function updateNursingNote($noteId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $note = NursingNote::findOrFail($noteId);

            // Check permissions again just to be safe (backend validation)
            if (Auth::id() != $note->created_by) {
                 return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

             // Check time window
            $createdDate = \Carbon\Carbon::parse($note->created_at);
            $editDuration = appsettings('note_edit_duration') ?? 60;
            $editDeadline = $createdDate->copy()->addMinutes($editDuration);

            if (\Carbon\Carbon::now()->greaterThan($editDeadline)) {
                 return response()->json(['success' => false, 'message' => 'Edit window has expired'], 403);
            }

            $note->update([
                'note' => $request->note,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nursing note updated successfully',
                'note' => $note,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating note: ' . $e->getMessage()
            ], 500);
        }
    }

    // =====================================
    // REPORTS
    // =====================================

    /**
     * Get shift summary report.
     * Supports both shift-based and date range queries.
     */
    public function getShiftSummary(Request $request)
    {
        // Support date range from reports module (from/to) or shift times
        $fromDate = $request->get('from');
        $toDate = $request->get('to');

        if ($fromDate && $toDate) {
            $shiftStart = Carbon::parse($fromDate)->startOfDay()->format('Y-m-d H:i:s');
            $shiftEnd = Carbon::parse($toDate)->endOfDay()->format('Y-m-d H:i:s');
        } else {
            $shiftStart = $request->get('shift_start', Carbon::today()->setHour(7)->format('Y-m-d H:i:s'));
            $shiftEnd = $request->get('shift_end', Carbon::today()->setHour(19)->format('Y-m-d H:i:s'));
        }

        // Admitted patients summary
        $admittedCount = AdmissionRequest::where('discharged', 0)
            ->whereNotNull('bed_id')
            ->count();

        $criticalCount = AdmissionRequest::where('discharged', 0)
            ->whereNotNull('bed_id')
            ->where('priority', 'critical')
            ->count();

        // Medications administered during shift
        $medsAdministered = MedicationAdministration::whereBetween('administered_at', [$shiftStart, $shiftEnd])
            ->whereNull('deleted_at')
            ->count();

        $medsMissed = MedicationSchedule::whereBetween('scheduled_time', [$shiftStart, $shiftEnd])
            ->whereDoesntHave('administrations', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->count();

        // Pending medications
        $medsPending = MedicationSchedule::where('scheduled_time', '>=', Carbon::now())
            ->where('scheduled_time', '<=', $shiftEnd)
            ->whereDoesntHave('administrations', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->count();

        // Injections given
        $injectionsGiven = InjectionAdministration::whereBetween('administered_at', [$shiftStart, $shiftEnd])
            ->count();

        // Immunizations given
        $immunizationsGiven = ImmunizationRecord::whereBetween('administered_at', [$shiftStart, $shiftEnd])
            ->count();

        // Vitals taken
        $vitalsTaken = VitalSign::whereBetween('created_at', [$shiftStart, $shiftEnd])
            ->count();

        // Vitals with abnormal values (basic check - could be enhanced with proper ranges)
        $vitalsAbnormal = VitalSign::whereBetween('created_at', [$shiftStart, $shiftEnd])
            ->where(function ($q) {
                $q->where('systolic', '>', 140)
                    ->orWhere('systolic', '<', 90)
                    ->orWhere('diastolic', '>', 90)
                    ->orWhere('diastolic', '<', 60)
                    ->orWhere('temperature', '>', 38)
                    ->orWhere('temperature', '<', 36)
                    ->orWhere('pulse', '>', 100)
                    ->orWhere('pulse', '<', 60)
                    ->orWhere('spo2', '<', 95);
            })
            ->count();

        // Critical vitals
        $vitalsCritical = VitalSign::whereBetween('created_at', [$shiftStart, $shiftEnd])
            ->where(function ($q) {
                $q->where('systolic', '>', 180)
                    ->orWhere('systolic', '<', 70)
                    ->orWhere('temperature', '>', 40)
                    ->orWhere('temperature', '<', 35)
                    ->orWhere('pulse', '>', 120)
                    ->orWhere('pulse', '<', 40)
                    ->orWhere('spo2', '<', 90);
            })
            ->count();

        // Patients seen (unique patients with activity)
        $patientsSeen = VitalSign::whereBetween('created_at', [$shiftStart, $shiftEnd])
            ->distinct('patient_id')
            ->count('patient_id');

        // Admissions during period
        $admissions = AdmissionRequest::whereBetween('created_at', [$shiftStart, $shiftEnd])
            ->count();

        // Discharges during period
        $discharges = AdmissionRequest::whereBetween('updated_at', [$shiftStart, $shiftEnd])
            ->where('discharged', 1)
            ->count();

        // Nursing notes (from nurse_notes if exists)
        $notesCreated = 0;
        $progressNotes = 0;
        $incidentReports = 0;
        if (\Schema::hasTable('nurse_notes')) {
            $notesCreated = \DB::table('nurse_notes')
                ->whereBetween('created_at', [$shiftStart, $shiftEnd])
                ->count();
            $progressNotes = \DB::table('nurse_notes')
                ->whereBetween('created_at', [$shiftStart, $shiftEnd])
                ->where('note_type', 'progress')
                ->count();
            $incidentReports = \DB::table('nurse_notes')
                ->whereBetween('created_at', [$shiftStart, $shiftEnd])
                ->where('note_type', 'incident')
                ->count();
        }

        // Return flat structure for reports UI compatibility
        return response()->json([
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
            // Summary stats
            'patients_seen' => $patientsSeen,
            'vitals_recorded' => $vitalsTaken,
            'vitals_abnormal' => $vitalsAbnormal,
            'vitals_critical' => $vitalsCritical,
            'medications_administered' => $medsAdministered,
            'medications_pending' => $medsPending,
            'medications_missed' => $medsMissed,
            'admissions' => $admissions,
            'discharges' => $discharges,
            'transfers' => 0, // Would need transfer tracking table
            'notes_created' => $notesCreated,
            'progress_notes' => $progressNotes,
            'incident_reports' => $incidentReports,
            'pending_tasks' => $medsPending,
            'critical_alerts' => $vitalsCritical + $criticalCount,
            // Legacy structure for backward compatibility
            'summary' => [
                'admitted_patients' => $admittedCount,
                'critical_patients' => $criticalCount,
                'medications_administered' => $medsAdministered,
                'medications_missed' => $medsMissed,
                'injections_given' => $injectionsGiven,
                'immunizations_given' => $immunizationsGiven,
                'vitals_taken' => $vitalsTaken,
            ],
        ]);
    }

    /**
     * Generate handover report.
     */
    public function generateHandoverReport(Request $request)
    {
        // Get all admitted patients with their current status
        $admissions = AdmissionRequest::with([
                'patient.user',
                'bed',
                'doctor'
            ])
            ->where('discharged', 0)
            ->whereNotNull('bed_id')
            ->orderBy('priority', 'desc')
            ->get();

        $patientReports = $admissions->map(function ($admission) {
            $patient = $admission->patient;

            // Get recent vitals
            $lastVitals = VitalSign::where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Get today's medication status
            $todayMeds = MedicationSchedule::where('patient_id', $patient->id)
                ->whereDate('scheduled_time', Carbon::today())
                ->count();

            $todayMedsGiven = MedicationSchedule::where('patient_id', $patient->id)
                ->whereDate('scheduled_time', Carbon::today())
                ->whereHas('administrations', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->count();

            // Get recent nursing notes
            $recentNote = NursingNote::where('patient_id', $patient->id)
                ->orderBy('created_at', 'desc')
                ->first();

            return [
                'patient_name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'bed' => $admission->bed ? $admission->bed->ward . ' - ' . $admission->bed->name : 'N/A',
                'priority' => $admission->priority ?? 'normal',
                'admission_reason' => $admission->admission_reason ?? 'N/A',
                'doctor' => $admission->doctor ? userfullname($admission->doctor->id) : 'N/A',
                'days_admitted' => $admission->bed_assign_date ? Carbon::parse($admission->bed_assign_date)->diffInDays(Carbon::now()) : 0,
                'last_vitals' => $lastVitals ? [
                    'bp' => $lastVitals->blood_pressure,
                    'temp' => $lastVitals->temp,
                    'time' => Carbon::parse($lastVitals->created_at)->format('h:i a'),
                ] : null,
                'medications_today' => "{$todayMedsGiven}/{$todayMeds} given",
                'recent_note' => $recentNote ? \Illuminate\Support\Str::limit(strip_tags($recentNote->note), 100) : null,
            ];
        });

        return response()->json([
            'generated_at' => Carbon::now()->format('h:i a, d M Y'),
            'generated_by' => userfullname(Auth::id()),
            'total_patients' => $patientReports->count(),
            'patients' => $patientReports,
        ]);
    }

    /**
     * Get patient's immunization schedule.
     */
    public function getPatientSchedule($patientId)
    {
        $patient = PatientLowerCase::findOrFail($patientId);

        // Get patient's schedule
        $schedules = PatientImmunizationSchedule::with(['scheduleItem.template'])
            ->where('patient_id', $patientId)
            ->join('vaccine_schedule_items', 'patient_immunization_schedules.schedule_item_id', '=', 'vaccine_schedule_items.id')
            ->orderBy('vaccine_schedule_items.age_days')
            ->orderBy('vaccine_schedule_items.sort_order')
            ->select('patient_immunization_schedules.*')
            ->get();

        // Update statuses based on current date
        PatientImmunizationSchedule::updateStatusesForPatient($patientId);

        // Reload schedules after status update
        $schedules = PatientImmunizationSchedule::with(['scheduleItem', 'immunizationRecord'])
            ->where('patient_id', $patientId)
            ->join('vaccine_schedule_items', 'patient_immunization_schedules.schedule_item_id', '=', 'vaccine_schedule_items.id')
            ->orderBy('vaccine_schedule_items.age_days')
            ->orderBy('vaccine_schedule_items.sort_order')
            ->select('patient_immunization_schedules.*')
            ->get();

        // Group by age display
        $grouped = [];
        foreach ($schedules as $schedule) {
            $ageDisplay = $schedule->scheduleItem->age_display;
            if (!isset($grouped[$ageDisplay])) {
                $grouped[$ageDisplay] = [
                    'age_display' => $ageDisplay,
                    'age_days' => $schedule->scheduleItem->age_days,
                    'vaccines' => [],
                ];
            }

            // Get product mapping for this vaccine
            $productMapping = VaccineProductMapping::getPrimaryProduct($schedule->scheduleItem->vaccine_name);

            $grouped[$ageDisplay]['vaccines'][] = [
                'id' => $schedule->id,
                'schedule_item_id' => $schedule->schedule_item_id,
                'vaccine_name' => $schedule->scheduleItem->vaccine_name,
                'vaccine_code' => $schedule->scheduleItem->vaccine_code,
                'dose_label' => $schedule->scheduleItem->dose_label ?? $schedule->scheduleItem->vaccine_name,
                'dose_number' => $schedule->scheduleItem->dose_number,
                'route' => $schedule->scheduleItem->route,
                'site' => $schedule->scheduleItem->site,
                'due_date' => $schedule->due_date->format('Y-m-d'),
                'due_date_formatted' => $schedule->due_date->format('d M Y'),
                'administered_date' => $schedule->administered_date ? $schedule->administered_date->format('Y-m-d') : null,
                'status' => $schedule->status,
                'status_label' => $schedule->status_label,
                'status_badge_class' => $schedule->status_badge_class,
                'is_required' => $schedule->scheduleItem->is_required,
                'notes' => $schedule->notes ?? $schedule->scheduleItem->notes,
                'skip_reason' => $schedule->skip_reason,
                'product' => $productMapping ? [
                    'id' => $productMapping->id,
                    'name' => $productMapping->name,
                ] : null,
            ];
        }

        // Calculate summary stats
        $stats = [
            'total' => $schedules->count(),
            'administered' => $schedules->where('status', 'administered')->count(),
            'pending' => $schedules->whereIn('status', ['pending', 'due'])->count(),
            'overdue' => $schedules->where('status', 'overdue')->count(),
            'skipped' => $schedules->where('status', 'skipped')->count(),
        ];

        // Get unique templates for this patient's schedules
        $activeTemplates = [];
        $templateIds = [];
        foreach ($schedules as $schedule) {
            if ($schedule->scheduleItem && $schedule->scheduleItem->template_id && !in_array($schedule->scheduleItem->template_id, $templateIds)) {
                $templateIds[] = $schedule->scheduleItem->template_id;
                $template = VaccineScheduleTemplate::find($schedule->scheduleItem->template_id);
                if ($template) {
                    $activeTemplates[] = [
                        'id' => $template->id,
                        'name' => $template->name,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'patient' => [
                'id' => $patient->id,
                'name' => userfullname($patient->user_id),
                'dob' => $patient->dob,
                'age' => $this->calculateAge($patient->dob),
            ],
            'schedule' => array_values($grouped),
            'stats' => $stats,
            'has_schedule' => $schedules->count() > 0,
            'active_templates' => $activeTemplates,
        ]);
    }

    /**
     * Generate immunization schedule for a patient.
     */
    public function generatePatientSchedule(Request $request, $patientId)
    {
        $patient = PatientLowerCase::findOrFail($patientId);

        if (!$patient->dob) {
            return response()->json([
                'success' => false,
                'message' => 'Patient date of birth is required to generate immunization schedule.',
            ], 400);
        }

        $templateId = $request->input('template_id');

        try {
            $schedules = PatientImmunizationSchedule::generateForPatient($patientId, $templateId);

            return response()->json([
                'success' => true,
                'message' => count($schedules) . ' schedule entries created.',
                'count' => count($schedules),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update a patient's schedule item status (skip/contraindicate).
     */
    public function updateScheduleStatus(Request $request, $scheduleId)
    {
        $validated = $request->validate([
            'status' => 'required|in:skipped,contraindicated',
            'reason' => 'required_if:status,skipped,contraindicated|string|max:500',
        ]);

        $schedule = PatientImmunizationSchedule::findOrFail($scheduleId);

        if ($validated['status'] === 'skipped') {
            $schedule->markAsSkipped($validated['reason']);
        } elseif ($validated['status'] === 'contraindicated') {
            $schedule->markAsContraindicated($validated['reason']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Schedule status updated successfully.',
        ]);
    }

    /**
     * Administer a vaccine from the schedule.
     */
    public function administerFromSchedule(Request $request, $scheduleId)
    {
        $schedule = PatientImmunizationSchedule::with('scheduleItem')->findOrFail($scheduleId);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'dose' => 'nullable|string|max:100',
            'route' => 'nullable|string|max:50',
            'site' => 'required|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'administered_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $patient = PatientLowerCase::findOrFail($schedule->patient_id);
        $product = Product::with('price')->findOrFail($validated['product_id']);

        DB::beginTransaction();
        try {
            // Create billing record
            $billing = new ProductOrServiceRequest();
            $billing->user_id = $patient->user_id;
            $billing->staff_user_id = Auth::id();
            $billing->product_id = $product->id;
            $billing->qty = 1;

            // Try to apply HMO tariff, fallback to regular price if not found
            try {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, $product->id, null);
                if ($hmoData) {
                    $billing->payable_amount = $hmoData['payable_amount'];
                    $billing->claims_amount = $hmoData['claims_amount'];
                    $billing->coverage_mode = $hmoData['coverage_mode'];
                    $billing->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                // If no HMO tariff, use regular price (non-HMO patient or tariff not configured)
                $billing->payable_amount = $product->price ? $product->price->selling_price : 0;
            }

            $billing->save();

            // Create immunization record
            $immunizationRecord = ImmunizationRecord::create([
                'patient_id' => $schedule->patient_id,
                'product_id' => $product->id,
                'product_or_service_request_id' => $billing->id,
                'vaccine_name' => $schedule->scheduleItem->vaccine_name,
                'dose_number' => $schedule->scheduleItem->dose_number,
                'dose' => $validated['dose'] ?? $schedule->scheduleItem->dose_label,
                'route' => $validated['route'] ?? $schedule->scheduleItem->route,
                'site' => $validated['site'],
                'administered_at' => $validated['administered_at'],
                'administered_by' => Auth::id(),
                'batch_number' => $validated['batch_number'],
                'expiry_date' => $validated['expiry_date'],
                'notes' => $validated['notes'],
            ]);

            // Update schedule entry
            $schedule->markAsAdministered($immunizationRecord->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vaccine administered successfully.',
                'immunization_record' => $immunizationRecord,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to administer vaccine: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available schedule templates.
     */
    public function getScheduleTemplates()
    {
        $templates = VaccineScheduleTemplate::getActive();

        return response()->json([
            'success' => true,
            'templates' => $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'is_default' => $template->is_default,
                    'country' => $template->country,
                ];
            }),
        ]);
    }

    /**
     * Get products mapped to a vaccine name.
     */
    public function getVaccineProducts($vaccineName)
    {
        $products = VaccineProductMapping::getProductsForVaccine($vaccineName);

        return response()->json([
            'success' => true,
            'products' => $products->map(function ($mapping) {
                return [
                    'mapping_id' => $mapping->id,
                    'product_id' => $mapping->product_id,
                    'product_name' => $mapping->product->name,
                    'is_primary' => $mapping->is_primary,
                ];
            }),
        ]);
    }

    /**
     * Administer a vaccine from the schedule (new modal-based method).
     */
    public function administerFromScheduleNew(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:patient_immunization_schedules,id',
            'product_id' => 'required|exists:products,id',
            'route' => 'required|string|max:50',
            'site' => 'required|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'administered_at' => 'required|date',
            'manufacturer' => 'nullable|string|max:200',
            'vis_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $schedule = PatientImmunizationSchedule::with('scheduleItem')->findOrFail($validated['schedule_id']);
        $patient = PatientLowerCase::findOrFail($schedule->patient_id);
        $product = Product::with('price')->findOrFail($validated['product_id']);

        DB::beginTransaction();
        try {
            // Create billing record
            $billing = new ProductOrServiceRequest();
            $billing->user_id = $patient->user_id;
            $billing->staff_user_id = Auth::id();
            $billing->product_id = $product->id;
            $billing->qty = 1;

            // Try to apply HMO tariff, fallback to regular price if not found
            try {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, $product->id, null);
                if ($hmoData) {
                    $billing->payable_amount = $hmoData['payable_amount'];
                    $billing->claims_amount = $hmoData['claims_amount'];
                    $billing->coverage_mode = $hmoData['coverage_mode'];
                    $billing->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                // If no HMO tariff, use regular price (non-HMO patient or tariff not configured)
                $billing->payable_amount = $product->price ? $product->price->selling_price : 0;
            }

            $billing->save();

            // Create immunization record
            $immunizationRecord = ImmunizationRecord::create([
                'patient_id' => $schedule->patient_id,
                'product_id' => $product->id,
                'product_or_service_request_id' => $billing->id,
                'vaccine_name' => $schedule->scheduleItem->vaccine_name,
                'dose_number' => $schedule->scheduleItem->dose_number,
                'dose' => $schedule->scheduleItem->dose_label,
                'route' => $validated['route'],
                'site' => $validated['site'],
                'administered_at' => $validated['administered_at'],
                'administered_by' => Auth::id(),
                'batch_number' => $validated['batch_number'],
                'expiry_date' => $validated['expiry_date'],
                'manufacturer' => $validated['manufacturer'],
                'vis_date' => $validated['vis_date'],
                'notes' => $validated['notes'],
            ]);

            // Update schedule entry
            $schedule->markAsAdministered($immunizationRecord->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vaccine administered successfully.',
                'immunization_record' => $immunizationRecord,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to administer vaccine: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get immunization history for timeline/calendar view.
     */
    public function getImmunizationHistory($patientId)
    {
        $records = ImmunizationRecord::where('patient_id', $patientId)
            ->with(['product', 'administeredBy'])
            ->orderBy('administered_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'records' => $records->map(function ($record) {
                return [
                    'id' => $record->id,
                    'vaccine_name' => $record->vaccine_name ?: ($record->product ? $record->product->name : 'Unknown'),
                    'dose_number' => $record->dose_number,
                    'dose' => $record->dose,
                    'route' => $record->route,
                    'site' => $record->site,
                    'administered_date' => $record->administered_at ? Carbon::parse($record->administered_at)->format('M d, Y') : null,
                    'administered_at' => $record->administered_at,
                    'administered_by' => $record->administeredBy ? userfullname($record->administeredBy->id) : null,
                    'batch_number' => $record->batch_number,
                    'manufacturer' => $record->manufacturer,
                    'notes' => $record->notes,
                ];
            }),
        ]);
    }

    public function getPatientVitalsDt($patientId)
    {
        $query = VitalSign::with(['takenBy'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc');

        return \Yajra\DataTables\Facades\DataTables::of($query)
            ->addColumn('info', function ($vital) {
                // Format Date
                $createdAt = \Carbon\Carbon::parse($vital->created_at)->format('h:i a, d M Y');
                $takenByName = $vital->takenBy ? userfullname($vital->takenBy->id) : 'N/A';

                $html = '<div class="card mb-3 vital-card shadow-sm">';

                // Header
                $html .= '<div class="card-header bg-light d-flex justify-content-between align-items-center py-2">';
                $html .= '<div>';
                $html .= '<span class="badge bg-info me-2">Vitals</span>';
                $html .= '<small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $createdAt . '</small>';
                $html .= '</div>';
                $html .= '<div>';
                $html .= '<small class="text-muted">Taken By: <span class="fw-bold text-dark">' . htmlspecialchars($takenByName) . '</span></small>';
                $html .= '</div>';
                $html .= '</div>'; // End Header

                // Body
                $html .= '<div class="card-body p-3">';

                // Row 1: Primary Vitals
                $html .= '<div class="row mb-2">';

                // BP - with status indicator
                $bpStatus = $this->getVitalStatus('bp', $vital->blood_pressure);
                $html .= '<div class="col-6 col-md-4 col-lg-2 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-heart-pulse text-danger fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">Blood Pressure</small>';
                $html .= '<strong class="' . $bpStatus['class'] . '">' . ($vital->blood_pressure ?? 'N/A') . '</strong>';
                $html .= ' <span class="text-muted small">mmHg</span></div>';
                $html .= '</div></div>';

                // Temp - with status indicator
                $tempStatus = $this->getVitalStatus('temp', $vital->temp);
                $html .= '<div class="col-6 col-md-4 col-lg-2 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-thermometer text-warning fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">Temperature</small>';
                $html .= '<strong class="' . $tempStatus['class'] . '">' . ($vital->temp ?? 'N/A') . '</strong>';
                $html .= ' <span class="text-muted small">°C</span></div>';
                $html .= '</div></div>';

                // Heart Rate - with status indicator
                $hrStatus = $this->getVitalStatus('hr', $vital->heart_rate);
                $html .= '<div class="col-6 col-md-4 col-lg-2 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-heart text-danger fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">Heart Rate</small>';
                $html .= '<strong class="' . $hrStatus['class'] . '">' . ($vital->heart_rate ?? 'N/A') . '</strong>';
                $html .= ' <span class="text-muted small">bpm</span></div>';
                $html .= '</div></div>';

                // Resp Rate - with status indicator
                $rrStatus = $this->getVitalStatus('rr', $vital->resp_rate);
                $html .= '<div class="col-6 col-md-4 col-lg-2 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-lungs text-primary fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">Resp. Rate</small>';
                $html .= '<strong class="' . $rrStatus['class'] . '">' . ($vital->resp_rate ?? 'N/A') . '</strong>';
                $html .= ' <span class="text-muted small">bpm</span></div>';
                $html .= '</div></div>';

                // SpO2 - with status indicator
                $spo2Status = $this->getVitalStatus('spo2', $vital->spo2);
                $html .= '<div class="col-6 col-md-4 col-lg-2 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-percent text-info fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">SpO2</small>';
                $html .= '<strong class="' . $spo2Status['class'] . '">' . ($vital->spo2 ?? 'N/A') . '</strong>';
                $html .= ' <span class="text-muted small">%</span></div>';
                $html .= '</div></div>';

                // Pain Score - with status indicator
                $painStatus = $this->getVitalStatus('pain', $vital->pain_score);
                $html .= '<div class="col-6 col-md-4 col-lg-2 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-emoticon-sad text-secondary fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">Pain Score</small>';
                $html .= '<strong class="' . $painStatus['class'] . '">' . ($vital->pain_score !== null ? $vital->pain_score . '/10' : 'N/A') . '</strong></div>';
                $html .= '</div></div>';

                $html .= '</div>'; // End Row 1

                // Row 2: Measurements
                $html .= '<div class="row">';

                // Weight
                $html .= '<div class="col-6 col-md-3 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-weight text-success fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">Weight</small>';
                $html .= '<strong>' . ($vital->weight ?? 'N/A') . '</strong>';
                $html .= ' <span class="text-muted small">kg</span></div>';
                $html .= '</div></div>';

                // Height
                $html .= '<div class="col-6 col-md-3 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-human-male-height text-primary fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">Height</small>';
                $html .= '<strong>' . ($vital->height ?? 'N/A') . '</strong>';
                $html .= ' <span class="text-muted small">cm</span></div>';
                $html .= '</div></div>';

                // BMI
                $bmiClass = $this->getBmiClass($vital->bmi);
                $html .= '<div class="col-6 col-md-3 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-calculator text-secondary fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">BMI</small>';
                $html .= '<strong class="' . $bmiClass . '">' . ($vital->bmi ?? 'N/A') . '</strong></div>';
                $html .= '</div></div>';

                // Blood Sugar
                $sugarStatus = $this->getVitalStatus('sugar', $vital->blood_sugar);
                $html .= '<div class="col-6 col-md-3 mb-2">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="me-2"><i class="mdi mdi-water text-info fs-4"></i></div>';
                $html .= '<div><small class="text-muted d-block">Blood Sugar</small>';
                $html .= '<strong class="' . $sugarStatus['class'] . '">' . ($vital->blood_sugar ?? 'N/A') . '</strong>';
                $html .= ' <span class="text-muted small">mg/dL</span></div>';
                $html .= '</div></div>';

                $html .= '</div>'; // End Row 2

                if(!empty($vital->other_notes)){
                    $html .= '<div class="mt-2 pt-2 border-top">';
                    $html .= '<small class="text-muted">Notes:</small>';
                    $html .= '<p class="mb-0 text-dark">' . htmlspecialchars($vital->other_notes) . '</p>';
                    $html .= '</div>';
                }

                $html .= '</div>'; // End Body

                // Footer (Actions) - Edit Button Logic
                $canEdit = false;
                if ($vital->created_at) {
                    $createdDate = \Carbon\Carbon::parse($vital->created_at);
                    $editDuration = appsettings('note_edit_duration') ?? 60; // Default 60 minutes
                    $editDeadline = $createdDate->copy()->addMinutes($editDuration);
                    $canEdit = \Carbon\Carbon::now()->lessThanOrEqualTo($editDeadline);
                }

                // Check if user is creator
                $isCreator = \Auth::id() == $vital->taken_by;

                if ($canEdit && $isCreator) {
                    // Encode vital data for edit modal
                    $vitalData = json_encode([
                        'id' => $vital->id,
                        'blood_pressure' => $vital->blood_pressure,
                        'temp' => $vital->temp,
                        'weight' => $vital->weight,
                        'heart_rate' => $vital->heart_rate,
                        'resp_rate' => $vital->resp_rate,
                        'height' => $vital->height,
                        'bmi' => $vital->bmi,
                        'spo2' => $vital->spo2,
                        'blood_sugar' => $vital->blood_sugar,
                        'pain_score' => $vital->pain_score,
                        'other_notes' => $vital->other_notes,
                    ]);
                    $vitalDataEscaped = htmlspecialchars($vitalData, ENT_QUOTES);

                    $html .= '<div class="card-footer bg-white border-top d-flex justify-content-end py-2">';
                    $html .= "<button class='btn btn-sm btn-outline-primary edit-vital-btn'
                                 onclick='openEditVitalModal(this)'
                                 data-vital='{$vitalDataEscaped}'>";
                    $html .= '<i class="mdi mdi-pencil"></i> Edit Vitals';
                    $html .= '</button>';
                    $html .= '</div>';
                }

                $html .= '</div>'; // End Card

                return $html;
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    /**
     * Get vital status indicator (normal, warning, critical)
     */
    private function getVitalStatus($type, $value)
    {
        if ($value === null || $value === '') {
            return ['status' => 'unknown', 'class' => ''];
        }

        switch ($type) {
            case 'bp':
                if (!str_contains($value, '/')) {
                    return ['status' => 'unknown', 'class' => ''];
                }
                $parts = explode('/', $value);
                $sys = (int)$parts[0];
                $dia = (int)$parts[1];
                if ($sys > 180 || $sys < 80 || $dia > 110 || $dia < 50) {
                    return ['status' => 'critical', 'class' => 'text-danger'];
                } elseif ($sys > 140 || $sys < 90 || $dia > 90 || $dia < 60) {
                    return ['status' => 'warning', 'class' => 'text-warning'];
                }
                return ['status' => 'normal', 'class' => 'text-success'];

            case 'temp':
                $t = (float)$value;
                if ($t < 34 || $t > 39) {
                    return ['status' => 'critical', 'class' => 'text-danger'];
                } elseif ($t < 36.1 || $t > 38) {
                    return ['status' => 'warning', 'class' => 'text-warning'];
                }
                return ['status' => 'normal', 'class' => 'text-success'];

            case 'hr':
                $hr = (int)$value;
                if ($hr < 50 || $hr > 150) {
                    return ['status' => 'critical', 'class' => 'text-danger'];
                } elseif ($hr < 60 || $hr > 100) {
                    return ['status' => 'warning', 'class' => 'text-warning'];
                }
                return ['status' => 'normal', 'class' => 'text-success'];

            case 'rr':
                $rr = (int)$value;
                if ($rr < 8 || $rr > 30) {
                    return ['status' => 'critical', 'class' => 'text-danger'];
                } elseif ($rr < 12 || $rr > 20) {
                    return ['status' => 'warning', 'class' => 'text-warning'];
                }
                return ['status' => 'normal', 'class' => 'text-success'];

            case 'spo2':
                $spo2 = (float)$value;
                if ($spo2 < 90) {
                    return ['status' => 'critical', 'class' => 'text-danger'];
                } elseif ($spo2 < 95) {
                    return ['status' => 'warning', 'class' => 'text-warning'];
                }
                return ['status' => 'normal', 'class' => 'text-success'];

            case 'sugar':
                $sugar = (float)$value;
                if ($sugar < 70 || $sugar > 200) {
                    return ['status' => 'critical', 'class' => 'text-danger'];
                } elseif ($sugar < 80 || $sugar > 140) {
                    return ['status' => 'warning', 'class' => 'text-warning'];
                }
                return ['status' => 'normal', 'class' => 'text-success'];

            case 'pain':
                $pain = (int)$value;
                if ($pain >= 7) {
                    return ['status' => 'critical', 'class' => 'text-danger'];
                } elseif ($pain >= 4) {
                    return ['status' => 'warning', 'class' => 'text-warning'];
                }
                return ['status' => 'normal', 'class' => 'text-success'];

            default:
                return ['status' => 'unknown', 'class' => ''];
        }
    }

    /**
     * Get BMI classification CSS class
     */
    private function getBmiClass($bmi)
    {
        if ($bmi === null) {
            return '';
        }
        $bmi = (float)$bmi;
        if ($bmi < 18.5) {
            return 'text-warning'; // Underweight
        } elseif ($bmi < 25) {
            return 'text-success'; // Normal
        } elseif ($bmi < 30) {
            return 'text-warning'; // Overweight
        }
        return 'text-danger'; // Obese
    }

    public function getVitalsQueue(Request $request)
    {
        $query = \App\Models\DoctorQueue::with(['patient.user', 'doctor'])
            ->where(function($q) {
                $q->where('vitals_taken', 0)
                  ->orWhereNull('vitals_taken');
            })
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'asc');

        return \Yajra\DataTables\Facades\DataTables::of($query)
            ->addColumn('info', function ($queue) {
                $patient = $queue->patient;
                $patientId = $patient ? $patient->id : 0;
                $patientName = $patient ? $patient->name : 'Unknown';
                $fileNo = $patient ? $patient->file_no : 'N/A';
                $doctorName = $queue->doctor ? $queue->doctor->name : 'N/A';
                $time = $queue->created_at->format('h:i A');

                $html = '<div class="card mb-2 queue-card shadow-sm">';
                $html .= '<div class="card-body p-3 d-flex justify-content-between align-items-center">';
                $html .= '<div>';
                $html .= '<h6 class="mb-1 text-primary fw-bold">' . htmlspecialchars($patientName) . '</h6>';
                $html .= '<small class="text-muted"><i class="mdi mdi-file-document"></i> ' . htmlspecialchars($fileNo) . '</small> | ';
                $html .= '<small class="text-muted"><i class="mdi mdi-doctor"></i> ' . htmlspecialchars($doctorName) . '</small>';
                $html .= '</div>';
                $html .= '<div>';
                $html .= '<span class="badge bg-warning text-dark me-2">Pending Vitals</span>';
                $html .= '<small class="text-muted">' . $time . '</small>';
                $html .= '<button class="btn btn-sm btn-outline-primary ms-3" onclick="loadPatient('.$patientId.')">Open</button>';
                $html .= '</div>';
                $html .= '</div></div>';

                return $html;
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    public function getBedRequestsQueue(Request $request)
    {
        $query = AdmissionRequest::with(['patient.user', 'doctor'])
            ->whereNull('bed_id')
            ->where('discharged', 0)
            ->orderBy('created_at', 'desc');

        return \Yajra\DataTables\Facades\DataTables::of($query)
             ->addColumn('info', function ($request) {
                $patient = $request->patient;
                $patientId = $patient ? $patient->id : 0;
                $patientName = $patient ? $patient->name : 'Unknown';
                $fileNo = $patient ? $patient->file_no : 'N/A';
                $doctorName = $request->doctor ? $request->doctor->name : 'N/A';
                $time = $request->created_at->format('d M h:i A');

                $html = '<div class="card mb-2 queue-card shadow-sm">';
                $html .= '<div class="card-body p-3 d-flex justify-content-between align-items-center">';
                $html .= '<div>';
                $html .= '<h6 class="mb-1 text-danger fw-bold">' . htmlspecialchars($patientName) . '</h6>';
                $html .= '<small class="text-muted"><i class="mdi mdi-file-document"></i> ' . htmlspecialchars($fileNo) . '</small> | ';
                $html .= '<small class="text-muted"><i class="mdi mdi-doctor"></i> ' . htmlspecialchars($doctorName) . '</small>';
                $html .= '</div>';
                $html .= '<div>';
                $html .= '<span class="badge bg-danger me-2">Bed Request</span>';
                $html .= '<small class="text-muted">' . $time . '</small>';
                $html .= '<button class="btn btn-sm btn-outline-danger ms-3" onclick="loadPatient('.$patientId.')">Open</button>';
                $html .= '</div>';
                $html .= '</div></div>';

                return $html;
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    /**
     * Update a vital sign record (with time-based edit restriction).
     */
    public function updateVital(Request $request, $vitalId)
    {
        $vital = VitalSign::findOrFail($vitalId);

        // Check if user is the creator
        if (\Auth::id() != $vital->taken_by) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit vitals that you recorded.'
            ], 403);
        }

        // Check edit time window
        $editDuration = appsettings('note_edit_duration') ?? 60;
        $editDeadline = \Carbon\Carbon::parse($vital->created_at)->addMinutes($editDuration);

        if (\Carbon\Carbon::now()->greaterThan($editDeadline)) {
            return response()->json([
                'success' => false,
                'message' => 'The edit window has expired. Vitals can only be edited within ' . $editDuration . ' minutes of recording.'
            ], 403);
        }

        // Validate and update
        $validated = $request->validate([
            'blood_pressure' => 'nullable|string|max:50',
            'temp' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'heart_rate' => 'nullable|numeric',
            'resp_rate' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'bmi' => 'nullable|numeric',
            'spo2' => 'nullable|numeric',
            'blood_sugar' => 'nullable|numeric',
            'other_notes' => 'nullable|string',
        ]);

        $vital->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vitals updated successfully.'
        ]);
    }

    // ==========================================
    // WARD DASHBOARD METHODS
    // ==========================================

    /**
     * Get ward dashboard statistics
     */
    public function getWardDashboardStats()
    {
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::whereHas('currentAdmission', function($q) {
            $q->where('discharged', 0);
        })->count();
        $availableBeds = Bed::where(function($q) {
            $q->where('bed_status', 'available')
              ->orWhereNull('bed_status');
        })->whereDoesntHave('currentAdmission', function($q) {
            $q->where('discharged', 0);
        })->count();

        $pendingAdmissions = AdmissionRequest::whereNull('bed_id')
            ->where('discharged', 0)
            ->count();

        return response()->json([
            'total_beds' => $totalBeds,
            'occupied_beds' => $occupiedBeds,
            'available_beds' => $availableBeds,
            'pending_admissions' => $pendingAdmissions,
        ]);
    }

    /**
     * Get all wards with their beds
     */
    public function getWardDashboardWards()
    {
        // Check if Ward model exists, if not fall back to grouping beds by ward column
        if (class_exists('\\App\\Models\\Ward')) {
            $wards = \App\Models\Ward::with(['beds' => function($q) {
                $q->select('id', 'name', 'ward_id', 'bed_status')
                  ->with(['currentAdmission.patient.user']);
            }])->get();

            return response()->json($wards->map(function($ward) {
                return [
                    'id' => $ward->id,
                    'name' => $ward->name,
                    'type' => $ward->type ?? 'general',
                    'capacity' => $ward->capacity ?? $ward->beds->count(),
                    'occupied_beds' => $ward->beds->filter(function($bed) {
                        return $bed->currentAdmission && !$bed->currentAdmission->discharged;
                    })->count(),
                    'available_beds' => $ward->beds->filter(function($bed) {
                        return (!$bed->currentAdmission || $bed->currentAdmission->discharged)
                               && ($bed->bed_status === 'available' || $bed->bed_status === null);
                    })->count(),
                    'beds' => $ward->beds->map(function($bed) {
                        $status = 'available';
                        $currentPatient = null;

                        if ($bed->currentAdmission && !$bed->currentAdmission->discharged) {
                            $status = 'occupied';
                            $currentPatient = $bed->currentAdmission->patient
                                ? userfullname($bed->currentAdmission->patient->user_id)
                                : 'Unknown';
                        } elseif ($bed->bed_status === 'maintenance') {
                            $status = 'maintenance';
                        } elseif ($bed->bed_status === 'reserved') {
                            $status = 'reserved';
                        }

                        return [
                            'id' => $bed->id,
                            'name' => $bed->name,
                            'status' => $status,
                            'current_patient' => $currentPatient,
                        ];
                    }),
                ];
            }));
        }

        // Fallback: Group beds by ward text column
        $beds = Bed::with(['currentAdmission.patient.user'])->get();
        $wardGroups = $beds->groupBy('ward');

        return response()->json($wardGroups->map(function($beds, $wardName) {
            return [
                'id' => md5($wardName),
                'name' => $wardName ?: 'Unassigned',
                'type' => 'general',
                'capacity' => $beds->count(),
                'occupied_beds' => $beds->filter(function($bed) {
                    return $bed->currentAdmission && !$bed->currentAdmission->discharged;
                })->count(),
                'available_beds' => $beds->filter(function($bed) {
                    return !$bed->currentAdmission || $bed->currentAdmission->discharged;
                })->count(),
                'beds' => $beds->map(function($bed) {
                    $status = 'available';
                    $currentPatient = null;

                    if ($bed->currentAdmission && !$bed->currentAdmission->discharged) {
                        $status = 'occupied';
                        $currentPatient = $bed->currentAdmission->patient
                            ? userfullname($bed->currentAdmission->patient->user_id)
                            : 'Unknown';
                    }

                    return [
                        'id' => $bed->id,
                        'name' => $bed->name,
                        'status' => $status,
                        'current_patient' => $currentPatient,
                    ];
                }),
            ];
        })->values());
    }

    /**
     * Get admission queue (patients awaiting bed assignment)
     */
    public function getAdmissionQueue()
    {
        $queue = AdmissionRequest::with(['patient.user', 'doctor'])
            ->whereNull('bed_id')
            ->where('discharged', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($queue->map(function($admission) {
            return [
                'id' => $admission->id,
                'patient_name' => userfullname($admission->patient->user_id ?? 0),
                'file_no' => $admission->patient->file_no ?? 'N/A',
                'doctor_name' => $admission->doctor ? $admission->doctor->name : 'N/A',
                'requested_at' => $admission->created_at->format('M d, Y H:i'),
                'admission_status' => $admission->admission_status ?? 'pending_checklist',
            ];
        }));
    }

    /**
     * Get discharge queue (patients with discharge requested)
     */
    public function getDischargeQueue()
    {
        $queue = AdmissionRequest::with(['patient.user', 'bed'])
            ->whereNotNull('bed_id')
            ->where('discharged', 0)
            ->where(function($q) {
                $q->where('admission_status', 'discharge_requested')
                  ->orWhere('admission_status', 'discharge_checklist');
            })
            ->orderBy('updated_at', 'asc')
            ->get();

        return response()->json($queue->map(function($admission) {
            return [
                'id' => $admission->id,
                'patient_name' => userfullname($admission->patient->user_id ?? 0),
                'file_no' => $admission->patient->file_no ?? 'N/A',
                'bed_name' => $admission->bed ? ($admission->bed->ward . ' - ' . $admission->bed->name) : 'N/A',
                'discharge_requested_at' => $admission->updated_at->format('M d, Y H:i'),
            ];
        }));
    }

    /**
     * Get available beds for assignment
     */
    public function getAvailableBeds(Request $request)
    {
        $query = Bed::whereDoesntHave('currentAdmission', function($q) {
            $q->where('discharged', 0);
        })->where(function($q) {
            $q->where('bed_status', 'available')
              ->orWhereNull('bed_status');
        });

        if ($request->has('ward_id') && $request->ward_id) {
            $query->where('ward_id', $request->ward_id);
        }

        $beds = $query->get();

        return response()->json($beds->map(function($bed) {
            return [
                'id' => $bed->id,
                'name' => $bed->name,
                'ward_name' => $bed->ward ?? 'Unassigned',
            ];
        }));
    }

    /**
     * Get bed details
     */
    public function getBedDetails($bedId)
    {
        $bed = Bed::with(['currentAdmission.patient.user'])->findOrFail($bedId);

        $currentPatient = null;
        $admittedDate = null;

        if ($bed->currentAdmission && !$bed->currentAdmission->discharged) {
            $currentPatient = [
                'name' => userfullname($bed->currentAdmission->patient->user_id ?? 0),
                'file_no' => $bed->currentAdmission->patient->file_no ?? 'N/A',
            ];
            $admittedDate = $bed->currentAdmission->created_at->format('M d, Y H:i');
        }

        return response()->json([
            'id' => $bed->id,
            'name' => $bed->name,
            'ward_name' => $bed->ward ?? 'Unassigned',
            'current_patient' => $currentPatient,
            'admitted_date' => $admittedDate,
        ]);
    }

    /**
     * Set bed to maintenance status
     */
    public function setBedMaintenance($bedId)
    {
        $bed = Bed::findOrFail($bedId);

        // Check if bed is occupied
        if ($bed->currentAdmission && !$bed->currentAdmission->discharged) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot set occupied bed to maintenance'
            ], 400);
        }

        $bed->bed_status = 'maintenance';
        $bed->save();

        return response()->json(['success' => true]);
    }

    /**
     * Set bed to available status
     */
    public function setBedAvailable($bedId)
    {
        $bed = Bed::findOrFail($bedId);
        $bed->bed_status = 'available';
        $bed->save();

        return response()->json(['success' => true]);
    }

    /**
     * Get admission checklist for a patient
     */
    public function getAdmissionChecklist($admissionId)
    {
        $admission = AdmissionRequest::findOrFail($admissionId);

        // Check if checklist exists, create if not
        if (class_exists('\\App\\Models\\AdmissionChecklist')) {
            $checklist = \App\Models\AdmissionChecklist::where('admission_request_id', $admissionId)->first();

            if (!$checklist) {
                // Create from template
                $template = \App\Models\ChecklistTemplate::where('type', 'admission')
                    ->where('is_active', true)
                    ->first();

                if ($template) {
                    $checklist = \App\Models\AdmissionChecklist::createFromTemplate($admission, $template);
                } else {
                    // Create default checklist
                    $checklist = \App\Models\AdmissionChecklist::create([
                        'admission_request_id' => $admissionId,
                        'created_by' => Auth::id(),
                    ]);
                }
            }

            $items = \App\Models\AdmissionChecklistItem::where('admission_checklist_id', $checklist->id)->get();
            $completedCount = $items->filter(function($item) {
                return $item->completed || $item->waived;
            })->count();
            $totalCount = $items->count();
            $progress = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 100;

            return response()->json([
                'id' => $checklist->id,
                'progress' => $progress,
                'all_complete' => $progress >= 100,
                'items' => $items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->item_name,
                        'description' => $item->description,
                        'completed' => (bool) $item->completed,
                        'waived' => (bool) $item->waived,
                        'waived_by' => $item->waivedBy ? userfullname($item->waivedBy->id) : null,
                        'waived_reason' => $item->waived_reason,
                        'is_waivable' => true,
                    ];
                }),
            ]);
        }

        // Fallback: Return empty checklist structure
        return response()->json([
            'id' => 0,
            'progress' => 100,
            'all_complete' => true,
            'items' => [],
        ]);
    }

    /**
     * Complete an admission checklist item
     */
    public function completeAdmissionChecklistItem(Request $request, $itemId)
    {
        if (!class_exists('\\App\\Models\\AdmissionChecklistItem')) {
            return response()->json(['success' => true, 'progress' => 100]);
        }

        $item = \App\Models\AdmissionChecklistItem::findOrFail($itemId);
        $item->completed = $request->completed ?? true;
        $item->completed_by = Auth::id();
        $item->completed_at = now();
        $item->save();

        // Calculate progress
        $checklist = $item->admissionChecklist;
        $items = \App\Models\AdmissionChecklistItem::where('admission_checklist_id', $checklist->id)->get();
        $completedCount = $items->filter(fn($i) => $i->completed || $i->waived)->count();
        $progress = $items->count() > 0 ? round(($completedCount / $items->count()) * 100) : 100;

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    /**
     * Waive an admission checklist item
     */
    public function waiveAdmissionChecklistItem(Request $request, $itemId)
    {
        if (!class_exists('\\App\\Models\\AdmissionChecklistItem')) {
            return response()->json(['success' => true]);
        }

        $item = \App\Models\AdmissionChecklistItem::findOrFail($itemId);
        $item->waived = true;
        $item->waived_by = Auth::id();
        $item->waived_at = now();
        $item->waived_reason = $request->reason;
        $item->save();

        return response()->json(['success' => true]);
    }

    /**
     * Assign a bed to an admission
     */
    public function assignBed(Request $request, $admissionId)
    {
        $request->validate([
            'bed_id' => 'required|exists:beds,id',
        ]);

        $admission = AdmissionRequest::findOrFail($admissionId);
        $bed = Bed::findOrFail($request->bed_id);

        // Check if bed is available
        $existingAdmission = AdmissionRequest::where('bed_id', $bed->id)
            ->where('discharged', 0)
            ->first();

        if ($existingAdmission) {
            return response()->json([
                'success' => false,
                'message' => 'This bed is already occupied'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $admission->bed_id = $bed->id;
            $admission->admission_status = 'admitted';
            $admission->save();

            $bed->bed_status = 'occupied';
            $bed->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bed assigned successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign bed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get discharge checklist for an admission
     */
    public function getDischargeChecklist($admissionId)
    {
        $admission = AdmissionRequest::findOrFail($admissionId);

        if (class_exists('\\App\\Models\\DischargeChecklist')) {
            $checklist = \App\Models\DischargeChecklist::where('admission_request_id', $admissionId)->first();

            if (!$checklist) {
                $template = \App\Models\ChecklistTemplate::where('type', 'discharge')
                    ->where('is_active', true)
                    ->first();

                if ($template) {
                    $checklist = \App\Models\DischargeChecklist::createFromTemplate($admission, $template);
                } else {
                    $checklist = \App\Models\DischargeChecklist::create([
                        'admission_request_id' => $admissionId,
                        'created_by' => Auth::id(),
                    ]);
                }
            }

            $items = \App\Models\DischargeChecklistItem::where('discharge_checklist_id', $checklist->id)->get();
            $completedCount = $items->filter(fn($item) => $item->completed || $item->waived)->count();
            $progress = $items->count() > 0 ? round(($completedCount / $items->count()) * 100) : 100;

            return response()->json([
                'id' => $checklist->id,
                'progress' => $progress,
                'all_complete' => $progress >= 100,
                'items' => $items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->item_name,
                        'description' => $item->description,
                        'completed' => (bool) $item->completed,
                        'waived' => (bool) $item->waived,
                        'waived_by' => $item->waivedBy ? userfullname($item->waivedBy->id) : null,
                        'waived_reason' => $item->waived_reason,
                        'is_waivable' => true,
                    ];
                }),
            ]);
        }

        return response()->json([
            'id' => 0,
            'progress' => 100,
            'all_complete' => true,
            'items' => [],
        ]);
    }

    /**
     * Complete a discharge checklist item
     */
    public function completeDischargeChecklistItem(Request $request, $itemId)
    {
        if (!class_exists('\\App\\Models\\DischargeChecklistItem')) {
            return response()->json(['success' => true, 'progress' => 100]);
        }

        $item = \App\Models\DischargeChecklistItem::findOrFail($itemId);
        $item->completed = $request->completed ?? true;
        $item->completed_by = Auth::id();
        $item->completed_at = now();
        $item->save();

        $checklist = $item->dischargeChecklist;
        $items = \App\Models\DischargeChecklistItem::where('discharge_checklist_id', $checklist->id)->get();
        $completedCount = $items->filter(fn($i) => $i->completed || $i->waived)->count();
        $progress = $items->count() > 0 ? round(($completedCount / $items->count()) * 100) : 100;

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    /**
     * Waive a discharge checklist item
     */
    public function waiveDischargeChecklistItem(Request $request, $itemId)
    {
        if (!class_exists('\\App\\Models\\DischargeChecklistItem')) {
            return response()->json(['success' => true]);
        }

        $item = \App\Models\DischargeChecklistItem::findOrFail($itemId);
        $item->waived = true;
        $item->waived_by = Auth::id();
        $item->waived_at = now();
        $item->waived_reason = $request->reason;
        $item->save();

        return response()->json(['success' => true]);
    }

    /**
     * Complete discharge and release bed
     */
    public function completeDischarge(Request $request, $admissionId)
    {
        $admission = AdmissionRequest::with('bed')->findOrFail($admissionId);

        DB::beginTransaction();
        try {
            // Release bed
            if ($admission->bed) {
                $admission->bed->bed_status = 'available';
                $admission->bed->save();
            }

            // Mark as discharged
            $admission->discharged = 1;
            $admission->admission_status = 'discharged';
            $admission->discharged_at = now();
            $admission->discharged_by = Auth::id();
            $admission->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Patient discharged successfully, bed released'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete discharge: ' . $e->getMessage()
            ], 500);
        }
    }
}
