<?php

namespace App\Http\Controllers;

use App\Helpers\HmoHelper;
use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\HmoTariff;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\patient;
use App\Models\PatientAccount;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\service;
use App\Models\serviceCategory;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Yajra\DataTables\DataTables;

class ReceptionWorkbenchController extends Controller
{
    /**
     * Parse patient DOB - handles multiple date formats
     */
    private function parsePatientDob($dob)
    {
        if (!$dob) return null;

        try {
            // Try d/m/Y format first (common in legacy data)
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dob)) {
                return Carbon::createFromFormat('d/m/Y', $dob);
            }
            // Try standard Y-m-d format
            return Carbon::parse($dob);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Display the reception workbench
     */
    public function index()
    {
        $clinics = Clinic::all();
        $hmos = Hmo::with('scheme')->orderBy('name')->get();

        // Get registration services for optional registration fee
        $registrationCategoryId = appsettings('registration_category_id');
        $registrationServices = collect();
        if ($registrationCategoryId) {
            $registrationServices = service::with('price')
                ->where('category_id', $registrationCategoryId)
                ->where('status', 1)
                ->orderBy('service_name')
                ->get();
        }

        return view('admin.reception.workbench', compact('clinics', 'hmos', 'registrationServices'));
    }

    /**
     * Search patients for reception
     */
    public function searchPatients(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $patients = patient::with(['user', 'hmo', 'account'])
            ->whereHas('user', function($q) use ($query) {
                $q->where('surname', 'like', "%{$query}%")
                  ->orWhere('firstname', 'like', "%{$query}%")
                  ->orWhere('othername', 'like', "%{$query}%");
            })
            ->orWhere('phone_no', 'like', "%{$query}%")
            ->orWhere('file_no', 'like', "%{$query}%")
            ->limit(20)
            ->get()
            ->map(function($patient) {
                return [
                    'id' => $patient->id,
                    'user_id' => $patient->user_id,
                    'name' => userfullname($patient->user_id),
                    'file_no' => $patient->file_no ?? 'N/A',
                    'phone' => $patient->phone_no ?? 'N/A',
                    'gender' => $patient->gender,
                    'dob' => $patient->dob,
                    'age' => $patient->dob ? $this->parsePatientDob($patient->dob)?->age : null,
                    'hmo' => $patient->hmo->name ?? 'Private',
                    'hmo_id' => $patient->hmo_id,
                    'hmo_no' => $patient->hmo_no,
                    'photo' => $patient->user->filename ? asset('storage/image/user/' . $patient->user->filename) : asset('assets/images/default-avatar.png'),
                    'balance' => $patient->account->balance ?? 0,
                    'allergies' => $patient->allergies ?? [],
                ];
            });

        return response()->json($patients);
    }

    /**
     * Get full patient data for workspace
     */
    public function getPatient($patientId)
    {
        $patient = patient::with(['user', 'hmo.scheme', 'account'])
            ->findOrFail($patientId);

        // Get current queue entries for this patient
        $queueEntries = DoctorQueue::with(['clinic', 'doctor', 'request_entry.service'])
            ->where('patient_id', $patientId)
            ->whereIn('status', [1, 2, 3]) // Waiting, Vitals Pending, In Consultation
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($q) {
                return [
                    'id' => $q->id,
                    'clinic' => $q->clinic->name ?? 'N/A',
                    'doctor' => $q->doctor ? userfullname($q->doctor->id) : 'Any Available',
                    'service' => $q->request_entry->service->service_name ?? 'Consultation',
                    'status' => $q->status,
                    'status_text' => $this->getQueueStatusText($q->status),
                    'vitals_taken' => $q->vitals_taken,
                    'created_at' => $q->created_at->format('h:i A'),
                ];
            });

        // Get recent visits (last 5 encounters)
        $recentVisits = Encounter::with(['doctor', 'service'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($e) {
                return [
                    'id' => $e->id,
                    'date' => $e->created_at->format('M d, Y'),
                    'doctor' => $e->doctor ? userfullname($e->doctor->id) : 'N/A',
                    'service' => $e->service->service_name ?? 'Consultation',
                    'reason' => $e->reasons_for_encounter,
                ];
            });

        // Calculate detailed age
        $ageText = 'N/A';
        if ($patient->dob) {
            $dob = $this->parsePatientDob($patient->dob);
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
        }

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'surname' => $patient->user->surname ?? '',
                'firstname' => $patient->user->firstname ?? '',
                'othername' => $patient->user->othername ?? '',
                'file_no' => $patient->file_no ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'phone_no' => $patient->phone_no ?? '',
                'email' => $patient->user->email ?? 'N/A',
                'gender' => $patient->gender,
                'dob' => $patient->dob ? ($this->parsePatientDob($patient->dob)?->format('Y-m-d') ?? $patient->dob) : '',
                'dob_display' => $patient->dob ? ($this->parsePatientDob($patient->dob)?->format('M d, Y') ?? $patient->dob) : 'N/A',
                'age' => $ageText,
                'blood_group' => $patient->blood_group ?? '',
                'genotype' => $patient->genotype ?? '',
                'nationality' => $patient->nationality ?? 'Nigerian',
                'ethnicity' => $patient->ethnicity ?? '',
                'address' => $patient->address ?? '',
                'disability' => $patient->disability ?? 0,
                'disability_display' => $patient->disability == 1 ? 'Yes' : 'No',
                'next_of_kin_name' => $patient->next_of_kin_name ?? '',
                'next_of_kin_phone' => $patient->next_of_kin_phone ?? '',
                'next_of_kin_address' => $patient->next_of_kin_address ?? '',
                'hmo_name' => $patient->hmo->name ?? null,
                'hmo_id' => $patient->hmo_id,
                'hmo_no' => $patient->hmo_no ?? '',
                'hmo_plan' => $patient->hmo_plan ?? '',
                'hmo_category' => $patient->hmo && $patient->hmo->scheme ? $patient->hmo->scheme->name : 'N/A',
                'insurance_scheme' => $patient->insurance_scheme ?? 'N/A',
                'company' => $patient->company ?? '',
                'photo' => $patient->user->filename ? asset('storage/image/user/' . $patient->user->filename) : asset('assets/images/default-avatar.png'),
                'balance' => $patient->account->balance ?? 0,
                'allergies' => $patient->allergies ?? [],
                'medical_history' => $patient->medical_history ?? '',
                'misc' => $patient->misc ?? '',
                'filename' => $patient->user->filename ?? '',
                'passport_url' => $patient->user->filename ? asset('storage/image/user/' . $patient->user->filename) : null,
                'old_records' => $patient->user->old_records ?? '',
                'old_records_url' => $patient->user->old_records ? asset('storage/image/user/old_records/' . $patient->user->old_records) : null,
            ],
            'queue_entries' => $queueEntries,
            'recent_visits' => $recentVisits,
        ]);
    }

    /**
     * Get queue status text
     */
    private function getQueueStatusText($status)
    {
        $statuses = [
            1 => 'Waiting',
            2 => 'Vitals Pending',
            3 => 'In Consultation',
            4 => 'Completed',
        ];
        return $statuses[$status] ?? 'Unknown';
    }

    /**
     * Get patient's current queue entries
     */
    public function getPatientQueueEntries($id)
    {
        $today = Carbon::today();

        $entries = DoctorQueue::where('patient_id', $id)
            ->whereDate('created_at', $today)
            ->whereIn('status', [1, 2, 3]) // Only active entries
            ->with(['clinic', 'doctor', 'patient'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                // Generate queue number from ID (last 3-4 digits for readability)
                $queueNo = str_pad($entry->id % 1000, 3, '0', STR_PAD_LEFT);

                return [
                    'id' => $entry->id,
                    'queue_no' => $queueNo,
                    'patient_name' => $entry->patient ? userfullname($entry->patient->user_id) : 'N/A',
                    'patient_file_no' => $entry->patient->file_no ?? 'N/A',
                    'clinic_name' => $entry->clinic->name ?? 'N/A',
                    'doctor_name' => $entry->doctor ? userfullname($entry->doctor->id) : null,
                    'status' => $entry->status,
                    'created_at' => $entry->created_at->format('H:i'),
                ];
            });

        return response()->json($entries);
    }

    /**
     * Get queue counts for widgets
     */
    public function getQueueCounts()
    {
        $today = Carbon::today();

        $counts = [
            'waiting' => DoctorQueue::where('status', 1)->whereDate('created_at', $today)->count(),
            'vitals_pending' => DoctorQueue::where('status', 2)->whereDate('created_at', $today)->count(),
            'in_consultation' => DoctorQueue::where('status', 3)->whereDate('created_at', $today)->count(),
            'completed' => DoctorQueue::where('status', 4)->whereDate('created_at', $today)->count(),
            'total_today' => DoctorQueue::whereDate('created_at', $today)->count(),
            'admitted' => AdmissionRequest::where('discharged', 0)->count(),
            'emergency' => DoctorQueue::where('priority', 'emergency')->whereDate('created_at', $today)->whereIn('status', [1, 2, 3])->count(),
        ];

        return response()->json($counts);
    }

    /**
     * Get queue list for DataTable
     */
    public function getQueueList(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $clinicId = $request->get('clinic_id');
        $today = Carbon::today();

        // Handle admitted patients separately
        if ($filter === 'admitted') {
            return $this->getAdmittedPatientsList($request);
        }

        $query = DoctorQueue::with(['patient.user', 'patient.hmo', 'clinic', 'doctor', 'request_entry.service'])
            ->whereDate('created_at', $today)
            ->orderByRaw("FIELD(IFNULL(priority,'routine'), 'emergency', 'urgent', 'routine') ASC")
            ->orderBy('created_at', 'desc');

        // Apply status filter
        if ($filter === 'waiting') {
            $query->where('status', 1);
        } elseif ($filter === 'vitals') {
            $query->where('status', 2);
        } elseif ($filter === 'consultation') {
            $query->where('status', 3);
        } elseif ($filter === 'completed') {
            $query->where('status', 4);
        } elseif ($filter === 'emergency') {
            $query->where('priority', 'emergency')->whereIn('status', [1, 2, 3]);
        }

        // Apply clinic filter
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('patient_name', function($q) {
                return userfullname($q->patient->user_id);
            })
            ->addColumn('patient_file_no', function($q) {
                return $q->patient->file_no ?? 'N/A';
            })
            ->addColumn('patient_hmo', function($q) {
                return $q->patient->hmo->name ?? 'Private';
            })
            ->addColumn('clinic_name', function($q) {
                return $q->clinic->name ?? 'N/A';
            })
            ->addColumn('doctor_name', function($q) {
                return $q->doctor ? userfullname($q->doctor->id) : 'Any';
            })
            ->addColumn('service_name', function($q) {
                return $q->request_entry->service->service_name ?? 'Consultation';
            })
            ->addColumn('status_badge', function($q) {
                $badges = [
                    1 => '<span class="badge bg-warning">Waiting</span>',
                    2 => '<span class="badge bg-info">Vitals Pending</span>',
                    3 => '<span class="badge bg-success">In Consultation</span>',
                    4 => '<span class="badge bg-secondary">Completed</span>',
                ];
                $badge = $badges[$q->status] ?? '<span class="badge bg-secondary">Unknown</span>';
                if ($q->priority === 'emergency') {
                    $badge = '<span class="badge bg-danger"><i class="fa fa-bolt"></i> EMERGENCY</span> ' . $badge;
                } elseif ($q->priority === 'urgent') {
                    $badge = '<span class="badge bg-warning text-dark">Urgent</span> ' . $badge;
                }
                return $badge;
            })
            ->addColumn('time', function($q) {
                return $q->created_at->format('h:i A');
            })
            ->addColumn('actions', function($q) {
                return '<button class="btn btn-sm btn-primary btn-select-from-queue" data-patient-id="' . $q->patient_id . '">
                    <i class="mdi mdi-account"></i> Select
                </button>';
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Get admitted patients list for DataTable
     */
    protected function getAdmittedPatientsList(Request $request)
    {
        $query = AdmissionRequest::with(['patient.user', 'patient.hmo', 'bed.wardRelation', 'doctor'])
            ->where('discharged', 0)
            ->orderBy('created_at', 'desc');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('patient_name', function($q) {
                return $q->patient ? userfullname($q->patient->user_id) : 'N/A';
            })
            ->addColumn('patient_file_no', function($q) {
                return $q->patient->file_no ?? 'N/A';
            })
            ->addColumn('patient_hmo', function($q) {
                return $q->patient->hmo->name ?? 'Private';
            })
            ->addColumn('clinic_name', function($q) {
                return $q->bed && $q->bed->wardRelation ? $q->bed->wardRelation->name : 'Unassigned';
            })
            ->addColumn('doctor_name', function($q) {
                return $q->doctor_id ? userfullname($q->doctor_id) : 'N/A';
            })
            ->addColumn('service_name', function($q) {
                return $q->bed ? $q->bed->name : 'No Bed';
            })
            ->addColumn('status_badge', function($q) {
                if ($q->bed_id) {
                    return '<span class="badge bg-danger">Admitted</span>';
                }
                return '<span class="badge bg-warning">Pending Bed</span>';
            })
            ->addColumn('time', function($q) {
                return $q->created_at->format('M d, H:i');
            })
            ->addColumn('actions', function($q) {
                return '<button class="btn btn-sm btn-primary btn-select-from-queue" data-patient-id="' . $q->patient_id . '">
                    <i class="mdi mdi-account"></i> Select
                </button>';
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Get clinics list
     */
    public function getClinics()
    {
        $clinics = Clinic::orderBy('name')->get();
        return response()->json($clinics);
    }

    /**
     * Get doctors by clinic
     */
    public function getDoctorsByClinic($clinicId)
    {
        $doctors = Staff::with('user')
            ->where('clinic_id', $clinicId)
            ->get()
            ->map(function($staff) {
                return [
                    'id' => $staff->id,
                    'user_id' => $staff->user_id,
                    'name' => userfullname($staff->user_id),
                ];
            });

        return response()->json($doctors);
    }

    /**
     * Get consultation services
     */
    public function getConsultationServices()
    {
        // Get services in consultation-related categories
        $services = service::with('price')
            ->whereHas('category', function($q) {
                $q->where('category_name', 'like', '%consult%')
                  ->orWhere('category_name', 'like', '%clinic%');
            })
            ->orWhere('service_name', 'like', '%consult%')
            ->where('status', 1)
            ->orderBy('service_name')
            ->get()
            ->map(function($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->service_name,
                    'code' => $s->service_code,
                    'price' => $s->price->sale_price ?? 0,
                    'category' => $s->category->category_name ?? 'General',
                ];
            });

        return response()->json($services);
    }

    /**
     * Get lab services
     */
    public function getLabServices(Request $request)
    {
        $search = $request->get('q', '');

        $query = service::with(['price', 'category'])
            ->whereHas('category', function($q) {
                $q->where('category_name', 'like', '%lab%')
                  ->orWhere('category_name', 'like', '%investigation%')
                  ->orWhere('category_name', 'like', '%test%');
            })
            ->where('status', 1);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('service_name', 'like', "%{$search}%")
                  ->orWhere('service_code', 'like', "%{$search}%");
            });
        }

        $services = $query->orderBy('service_name')
            ->limit(50)
            ->get()
            ->map(function($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->service_name,
                    'code' => $s->service_code,
                    'price' => $s->price->sale_price ?? 0,
                    'category' => $s->category->category_name ?? 'Lab',
                ];
            });

        return response()->json($services);
    }

    /**
     * Get imaging services
     */
    public function getImagingServices(Request $request)
    {
        $search = $request->get('q', '');

        $query = service::with(['price', 'category'])
            ->whereHas('category', function($q) {
                $q->where('category_name', 'like', '%imaging%')
                  ->orWhere('category_name', 'like', '%radiology%')
                  ->orWhere('category_name', 'like', '%x-ray%')
                  ->orWhere('category_name', 'like', '%scan%')
                  ->orWhere('category_name', 'like', '%ultrasound%');
            })
            ->where('status', 1);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('service_name', 'like', "%{$search}%")
                  ->orWhere('service_code', 'like', "%{$search}%");
            });
        }

        $services = $query->orderBy('service_name')
            ->limit(50)
            ->get()
            ->map(function($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->service_name,
                    'code' => $s->service_code,
                    'price' => $s->price->sale_price ?? 0,
                    'category' => $s->category->category_name ?? 'Imaging',
                ];
            });

        return response()->json($services);
    }

    /**
     * Get products for walk-in sales
     */
    public function getProducts(Request $request)
    {
        $search = $request->get('q', '');

        $query = Product::with(['price', 'category'])
            ->where('status', 1);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('product_name')
            ->limit(50)
            ->get()
            ->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->product_name,
                    'code' => $p->product_code,
                    'price' => $p->price->current_sale_price ?? 0,
                    'category' => $p->category->category_name ?? 'Product',
                    'stock' => $p->current_quantity ?? 0,
                ];
            });

        return response()->json($products);
    }

    /**
     * Calculate tariff preview with HMO coverage
     */
    public function getTariffPreview(Request $request)
    {
        $patientId = $request->get('patient_id');
        $serviceId = $request->get('service_id');
        $productId = $request->get('product_id');
        $qty = $request->get('qty', 1);

        $patient = patient::with('hmo')->find($patientId);

        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        // Get base price
        $basePrice = 0;
        $itemName = '';

        if ($serviceId) {
            $service = service::with('price')->find($serviceId);
            if ($service) {
                $basePrice = $service->price->sale_price ?? 0;
                $itemName = $service->service_name;
            }
        } elseif ($productId) {
            $product = Product::with('price')->find($productId);
            if ($product) {
                $basePrice = $product->price->current_sale_price ?? 0;
                $itemName = $product->product_name;
            }
        }

        $totalBasePrice = $basePrice * $qty;

        // Check HMO coverage
        $payableAmount = $totalBasePrice;
        $claimsAmount = 0;
        $coverageMode = null;
        $validationStatus = 'not_required';
        $coveragePercent = 0;

        if ($patient->hmo_id && $patient->hmo_id > 1) { // HMO ID 1 is usually "Private"
            try {
                $hmoData = HmoHelper::applyHmoTariff($patientId, $productId, $serviceId);

                if ($hmoData) {
                    $payableAmount = ($hmoData['payable_amount'] ?? 0) * $qty;
                    $claimsAmount = ($hmoData['claims_amount'] ?? 0) * $qty;
                    $coverageMode = $hmoData['coverage_mode'];
                    $validationStatus = $hmoData['validation_status'];

                    if ($totalBasePrice > 0) {
                        $coveragePercent = round(($claimsAmount / $totalBasePrice) * 100);
                    }
                }
            } catch (\Exception $e) {
                // HMO tariff not found - patient pays full price
                $payableAmount = $totalBasePrice;
                $claimsAmount = 0;
                $coverageMode = null;
            }
        }

        return response()->json([
            'item_name' => $itemName,
            'qty' => $qty,
            'base_price' => $basePrice,
            'total_base_price' => $totalBasePrice,
            'payable_amount' => $payableAmount,
            'claims_amount' => $claimsAmount,
            'coverage_mode' => $coverageMode,
            'coverage_percent' => $coveragePercent,
            'validation_status' => $validationStatus,
            'validation_required' => in_array($coverageMode, ['primary', 'secondary']),
            'hmo_name' => $patient->hmo->name ?? 'Private',
        ]);
    }

    /**
     * Book consultation (send to queue)
     */
    public function bookConsultation(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'service_id' => 'required|exists:services,id',
            'clinic_id' => 'required|exists:clinics,id',
            'doctor_id' => 'nullable|exists:staff,id',
        ]);

        try {
            DB::beginTransaction();

            $patient = patient::find($request->patient_id);

            // Create ProductOrServiceRequest
            $serviceRequest = new ProductOrServiceRequest();
            $serviceRequest->service_id = $request->service_id;
            $serviceRequest->user_id = $patient->user_id;
            $serviceRequest->staff_user_id = Auth::id();

            // Apply HMO tariff if applicable
            if ($patient->hmo_id && $patient->hmo_id > 1) {
                try {
                    $hmoData = HmoHelper::applyHmoTariff($patient->id, null, $request->service_id);
                    if ($hmoData) {
                        $serviceRequest->payable_amount = $hmoData['payable_amount'];
                        $serviceRequest->claims_amount = $hmoData['claims_amount'];
                        $serviceRequest->coverage_mode = $hmoData['coverage_mode'];
                        $serviceRequest->validation_status = $hmoData['validation_status'];
                    }
                } catch (\Exception $e) {
                    // No HMO tariff found - log but continue
                    Log::warning('HMO tariff not found for service', [
                        'patient_id' => $patient->id,
                        'service_id' => $request->service_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $serviceRequest->save();

            // Create queue entry
            $queue = new DoctorQueue();
            $queue->patient_id = $patient->id;
            $queue->clinic_id = $request->clinic_id;
            $queue->receptionist_id = Auth::id();
            $queue->request_entry_id = $serviceRequest->id;
            $queue->status = 1; // Waiting

            if ($request->doctor_id) {
                $queue->staff_id = $request->doctor_id;
            }

            $queue->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Patient added to queue successfully',
                'queue_id' => $queue->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error booking consultation', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to book consultation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Book walk-in services (lab, imaging, products)
     */
    public function bookWalkinServices(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:lab,imaging,product',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'nullable|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $patient = patient::find($request->patient_id);
            $createdRequests = [];

            foreach ($request->items as $item) {
                $qty = $item['qty'] ?? 1;

                if ($item['type'] === 'lab') {
                    // Create lab service request
                    $serviceRequest = $this->createServiceRequest($patient, $item['id'], null);

                    $labRequest = new LabServiceRequest();
                    $labRequest->service_request_id = $serviceRequest->id;
                    $labRequest->service_id = $item['id'];
                    $labRequest->patient_id = $patient->id;
                    $labRequest->doctor_id = null; // Walk-in, no doctor
                    $labRequest->status = 1; // Awaiting billing
                    $labRequest->save();

                    $createdRequests[] = [
                        'type' => 'lab',
                        'id' => $labRequest->id,
                        'service_request_id' => $serviceRequest->id,
                    ];

                } elseif ($item['type'] === 'imaging') {
                    // Create imaging service request
                    $serviceRequest = $this->createServiceRequest($patient, $item['id'], null);

                    $imagingRequest = new ImagingServiceRequest();
                    $imagingRequest->service_request_id = $serviceRequest->id;
                    $imagingRequest->service_id = $item['id'];
                    $imagingRequest->patient_id = $patient->id;
                    $imagingRequest->doctor_id = null;
                    $imagingRequest->status = 1; // Awaiting billing
                    $imagingRequest->save();

                    $createdRequests[] = [
                        'type' => 'imaging',
                        'id' => $imagingRequest->id,
                        'service_request_id' => $serviceRequest->id,
                    ];

                } elseif ($item['type'] === 'product') {
                    // Create product request
                    $serviceRequest = $this->createServiceRequest($patient, null, $item['id'], $qty);

                    $productRequest = new ProductRequest();
                    $productRequest->product_request_id = $serviceRequest->id;
                    $productRequest->product_id = $item['id'];
                    $productRequest->patient_id = $patient->id;
                    $productRequest->doctor_id = null;
                    $productRequest->status = 1; // Awaiting billing
                    $productRequest->save();

                    $createdRequests[] = [
                        'type' => 'product',
                        'id' => $productRequest->id,
                        'service_request_id' => $serviceRequest->id,
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdRequests) . ' item(s) booked successfully. Awaiting billing.',
                'requests' => $createdRequests,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error booking walk-in services', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to book services: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to create ProductOrServiceRequest with HMO tariff
     */
    private function createServiceRequest($patient, $serviceId = null, $productId = null, $qty = 1)
    {
        $serviceRequest = new ProductOrServiceRequest();
        $serviceRequest->service_id = $serviceId;
        $serviceRequest->product_id = $productId;
        $serviceRequest->user_id = $patient->user_id;
        $serviceRequest->staff_user_id = Auth::id();
        $serviceRequest->qty = $qty;

        // Apply HMO tariff if applicable
        if ($patient->hmo_id && $patient->hmo_id > 1) {
            try {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, $productId, $serviceId);
                if ($hmoData) {
                    $serviceRequest->payable_amount = $hmoData['payable_amount'] * $qty;
                    $serviceRequest->claims_amount = $hmoData['claims_amount'] * $qty;
                    $serviceRequest->coverage_mode = $hmoData['coverage_mode'];
                    $serviceRequest->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                // No HMO tariff - patient pays standard price
                if ($serviceId) {
                    $service = service::with('price')->find($serviceId);
                    $serviceRequest->payable_amount = ($service->price->sale_price ?? 0) * $qty;
                } elseif ($productId) {
                    $product = Product::with('price')->find($productId);
                    $serviceRequest->payable_amount = ($product->price->current_sale_price ?? 0) * $qty;
                }
            }
        } else {
            // Private patient - standard pricing
            if ($serviceId) {
                $service = service::with('price')->find($serviceId);
                $serviceRequest->payable_amount = ($service->price->sale_price ?? 0) * $qty;
            } elseif ($productId) {
                $product = Product::with('price')->find($productId);
                $serviceRequest->payable_amount = ($product->price->current_sale_price ?? 0) * $qty;
            }
        }

        $serviceRequest->save();
        return $serviceRequest;
    }

    /**
     * Get patient visit history
     */
    public function getVisitHistory(Request $request, $patientId)
    {
        $query = Encounter::with(['doctor', 'service', 'labRequests.service'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('date', function($e) {
                return $e->created_at->format('M d, Y h:i A');
            })
            ->addColumn('doctor_name', function($e) {
                return $e->doctor ? userfullname($e->doctor->id) : 'N/A';
            })
            ->addColumn('service_name', function($e) {
                return $e->service->service_name ?? 'Consultation';
            })
            ->addColumn('reason', function($e) {
                return $e->reasons_for_encounter ?? '-';
            })
            ->addColumn('actions', function($e) {
                return '<a href="' . route('patient.show', $e->patient_id) . '?section=doctorNotesCardBody"
                    class="btn btn-sm btn-info" target="_blank">
                    <i class="mdi mdi-eye"></i> View Details
                </a>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Quick patient registration
     */
    public function quickRegister(Request $request)
    {
        $request->validate([
            'surname' => 'required|min:2|max:150',
            'firstname' => 'required|min:2|max:150',
            'gender' => 'required|in:Male,Female,Others',
            'dob' => 'required|date',
            'hmo_id' => 'nullable|exists:hmos,id',
            'filename' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'old_records' => 'nullable|file|mimes:pdf,doc,docx,jpeg,png,jpg|max:5120',
        ]);

        try {
            DB::beginTransaction();

            // Generate email if not provided
            $email = $request->email ?? strtolower($request->firstname . '.' . $request->surname . '.' . rand(100, 999) . '@hms.com');

            // Create user
            $user = new User();
            $user->surname = ucwords($request->surname);
            $user->firstname = ucwords($request->firstname);
            $user->othername = $request->othername ? ucwords($request->othername) : null;
            $user->email = $email;
            $user->password = bcrypt('123456');
            $user->is_admin = 19; // Patient role
            $user->status = 1;
            $user->save();

            // Create patient
            $patient = new patient();
            $patient->user_id = $user->id;
            $patient->file_no = $request->file_no; // Use provided file number
            $patient->gender = $request->gender;
            $patient->dob = $request->dob;
            $patient->phone_no = $request->phone_no;
            $patient->address = $request->address;
            $patient->blood_group = $request->blood_group;
            $patient->genotype = $request->genotype;
            $patient->disability = $request->disability ?? 0;
            $patient->nationality = $request->nationality ?? 'Nigerian';
            $patient->ethnicity = $request->ethnicity;
            $patient->allergies = $request->allergies;
            $patient->medical_history = $request->medical_history;
            $patient->misc = $request->misc;
            $patient->next_of_kin_name = $request->next_of_kin_name;
            $patient->next_of_kin_phone = $request->next_of_kin_phone;
            $patient->next_of_kin_address = $request->next_of_kin_address;
            $patient->hmo_id = $request->hmo_id ?? 1; // Default to Private
            $patient->hmo_no = $request->hmo_no;

            // Handle passport photo upload - save to user model
            if ($request->hasFile('filename')) {
                $file = $request->file('filename');
                $filename = 'patient_' . time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();

                // Save main image
                $path = storage_path('/app/public/image/user/');
                if (!file_exists($path)) {
                    mkdir($path, 0755, true);
                }
                Image::make($file)
                    ->resize(215, 215)
                    ->save($path . $filename);

                // Save thumbnail
                $thumbnail_path = storage_path('/app/public/image/user/thumbnail/');
                if (!file_exists($thumbnail_path)) {
                    mkdir($thumbnail_path, 0755, true);
                }
                Image::make($file)
                    ->resize(106, 106)
                    ->save($thumbnail_path . $filename);

                $user->filename = $filename;
                $user->save();
            } elseif ($request->passport_data) {
                // Handle webcam captured photo (base64 data)
                $imageData = $request->passport_data;

                // Remove data URL prefix if present
                if (strpos($imageData, 'data:image') === 0) {
                    $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
                }

                $decodedImage = base64_decode($imageData);
                $filename = 'patient_' . time() . '_' . $user->id . '.jpg';

                // Save main image
                $path = storage_path('/app/public/image/user/');
                if (!file_exists($path)) {
                    mkdir($path, 0755, true);
                }
                Image::make($decodedImage)
                    ->resize(215, 215)
                    ->save($path . $filename);

                // Save thumbnail
                $thumbnail_path = storage_path('/app/public/image/user/thumbnail/');
                if (!file_exists($thumbnail_path)) {
                    mkdir($thumbnail_path, 0755, true);
                }
                Image::make($decodedImage)
                    ->resize(106, 106)
                    ->save($thumbnail_path . $filename);

                $user->filename = $filename;
                $user->save();
            }

            // Handle old records upload
            if ($request->hasFile('old_records')) {
                $file = $request->file('old_records');
                $filename = 'records_' . time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
                Storage::disk('old_records')->put($filename, $file->get());
                $user->old_records = $filename;
                $user->save();
            }

            $patient->save();

            // Create patient account
            $account = new PatientAccount();
            $account->patient_id = $patient->id;
            $account->balance = 0;
            $account->save();

            // Create registration fee billing entry if selected
            if ($request->registration_service_id) {
                $regService = service::with('price')->find($request->registration_service_id);
                if ($regService && $regService->price) {
                    ProductOrServiceRequest::create([
                        'user_id' => $user->id,
                        'staff_user_id' => Auth::id(),
                        'service_id' => $regService->id,
                        'qty' => 1,
                        'payable_amount' => $regService->price->sale_price ?? 0,
                    ]);
                    Log::info("Registration fee added for patient via quickRegister: {$patient->id}, service: {$regService->id}");
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Patient registered successfully',
                'patient' => [
                    'id' => $patient->id,
                    'user_id' => $user->id,
                    'name' => userfullname($user->id),
                    'file_no' => $patient->file_no,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error registering patient', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register patient: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get HMOs list
     */
    public function getHmos()
    {
        $hmos = Hmo::with('scheme')
            ->orderBy('name')
            ->get()
            ->map(function($hmo) {
                return [
                    'id' => $hmo->id,
                    'name' => $hmo->name,
                    'scheme' => $hmo->scheme->name ?? 'General',
                    'scheme_name' => $hmo->scheme->name ?? 'General',
                ];
            });

        return response()->json($hmos);
    }

    /**
     * Get today's statistics for reception dashboard
     */
    public function getTodayStats()
    {
        $today = Carbon::today();

        $stats = [
            'new_registrations' => patient::whereDate('created_at', $today)->count(),
            'total_queued' => DoctorQueue::whereDate('created_at', $today)->count(),
            'consultations_done' => DoctorQueue::where('status', 4)->whereDate('created_at', $today)->count(),
            'pending_services' => ProductOrServiceRequest::whereNull('payment_id')
                ->whereDate('created_at', $today)
                ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get the next sequential file number
     * Handles various formats: 14-38-78, EMR009, EMR-90, 7809PT, etc.
     * Only increments the last numeric segment while preserving the format
     */
    public function getNextFileNumber()
    {
        // Get the last 5 patients with file numbers for reference
        $recentPatients = patient::whereNotNull('file_no')
            ->where('file_no', '!=', '')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get(['id', 'file_no']);

        $recentFileNumbers = $recentPatients->pluck('file_no')->toArray();

        if ($recentPatients->isEmpty()) {
            return response()->json([
                'file_no' => '1',
                'last_file_no' => null,
                'recent_file_nos' => [],
                'format_pattern' => null,
                'format_example' => null
            ]);
        }

        $lastFileNo = $recentPatients->first()->file_no;
        $nextFileNo = $this->incrementLastNumericSegment($lastFileNo);

        // Detect and describe the format pattern
        $formatInfo = $this->detectFileNumberFormat($lastFileNo);

        return response()->json([
            'file_no' => $nextFileNo,
            'last_file_no' => $lastFileNo,
            'recent_file_nos' => $recentFileNumbers,
            'format_pattern' => $formatInfo['pattern'],
            'format_example' => $formatInfo['example']
        ]);
    }

    /**
     * Check if a file number already exists
     */
    public function checkFileNumberExists(Request $request)
    {
        $fileNo = $request->input('file_no');
        $excludePatientId = $request->input('exclude_patient_id'); // For edit mode

        $query = patient::where('file_no', $fileNo);

        if ($excludePatientId) {
            $query->where('id', '!=', $excludePatientId);
        }

        $existingPatients = $query->with('user')->limit(3)->get();

        $patients = $existingPatients->map(function ($patient) {
            return [
                'id' => $patient->id,
                'name' => $patient->user ? userfullname($patient->user->id) : 'Unknown',
                'file_no' => $patient->file_no
            ];
        });

        return response()->json([
            'exists' => $existingPatients->isNotEmpty(),
            'count' => $existingPatients->count(),
            'patients' => $patients
        ]);
    }

    /**
     * Detect the format pattern of a file number
     */
    private function detectFileNumberFormat(string $fileNo): array
    {
        // Analyze the file number to describe its format
        $pattern = '';
        $example = '';

        if (preg_match('/^([A-Za-z]+)[-]?(\d+)$/', $fileNo, $matches)) {
            // Format: EMR001 or EMR-001
            $prefix = $matches[1];
            $hasHyphen = strpos($fileNo, '-') !== false;
            $numLength = strlen($matches[2]);
            $pattern = $prefix . ($hasHyphen ? '-' : '') . str_repeat('#', $numLength);
            $example = $prefix . ($hasHyphen ? '-' : '') . str_pad('X', $numLength, '0', STR_PAD_LEFT);
        } elseif (preg_match('/^(\d+)[-](\d+)[-](\d+)$/', $fileNo, $matches)) {
            // Format: 14-38-78
            $pattern = str_repeat('#', strlen($matches[1])) . '-' . str_repeat('#', strlen($matches[2])) . '-' . str_repeat('#', strlen($matches[3]));
            $example = 'XX-XX-XX';
        } elseif (preg_match('/^(\d+)([A-Za-z]+)$/', $fileNo, $matches)) {
            // Format: 7809PT
            $numLength = strlen($matches[1]);
            $suffix = $matches[2];
            $pattern = str_repeat('#', $numLength) . $suffix;
            $example = str_repeat('X', $numLength) . $suffix;
        } elseif (preg_match('/^\d+$/', $fileNo)) {
            // Pure numeric
            $pattern = str_repeat('#', strlen($fileNo));
            $example = 'Sequential number';
        } else {
            // Unknown format
            $pattern = 'Custom';
            $example = $fileNo;
        }

        return [
            'pattern' => $pattern,
            'example' => $example
        ];
    }

    /**
     * Increment only the last numeric segment of a file number
     * Examples:
     *   14-38-78  → 14-38-79
     *   EMR009    → EMR010
     *   EMR-90    → EMR-91
     *   7809PT    → 7810PT
     *   ABC       → ABC1 (no numbers found, append 1)
     */
    private function incrementLastNumericSegment(string $fileNo): string
    {
        // Find the last numeric segment in the string
        // This regex captures everything before the last number, the last number itself, and everything after
        if (preg_match('/^(.*?)(\d+)(\D*)$/', $fileNo, $matches)) {
            $prefix = $matches[1];      // Everything before the last number
            $number = $matches[2];      // The last numeric segment
            $suffix = $matches[3];      // Everything after the last number (non-digits)

            // Preserve leading zeros by tracking the original length
            $originalLength = strlen($number);
            $incrementedNumber = intval($number) + 1;

            // Pad with leading zeros to maintain format (if new number doesn't exceed original length)
            $newNumber = str_pad($incrementedNumber, $originalLength, '0', STR_PAD_LEFT);

            return $prefix . $newNumber . $suffix;
        }

        // No numeric segment found, append "1"
        return $fileNo . '1';
    }

    /**
     * Update patient information
     */
    public function updatePatient(Request $request, $patientId)
    {
        $request->validate([
            'surname' => 'required|min:2|max:150',
            'firstname' => 'required|min:2|max:150',
            'gender' => 'required|in:Male,Female,Others',
            'dob' => 'required|date',
            'filename' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'old_records' => 'nullable|file|mimes:pdf,doc,docx,jpeg,png,jpg|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $patient = patient::with('user')->findOrFail($patientId);
            $user = $patient->user;

            // Update user info
            $user->surname = ucwords($request->surname);
            $user->firstname = ucwords($request->firstname);
            $user->othername = $request->othername ? ucwords($request->othername) : null;
            if ($request->email) {
                $user->email = $request->email;
            }
            $user->save();

            // Update patient info
            $patient->file_no = $request->file_no ?? $patient->file_no;
            $patient->gender = $request->gender;
            $patient->dob = $request->dob;
            $patient->phone_no = $request->phone_no;
            $patient->address = $request->address;
            $patient->blood_group = $request->blood_group;
            $patient->genotype = $request->genotype;
            $patient->disability = $request->disability ?? 0;
            $patient->nationality = $request->nationality ?? 'Nigerian';
            $patient->ethnicity = $request->ethnicity;
            $patient->allergies = $request->allergies;
            $patient->medical_history = $request->medical_history;
            $patient->misc = $request->misc;
            $patient->next_of_kin_name = $request->next_of_kin_name;
            $patient->next_of_kin_phone = $request->next_of_kin_phone;
            $patient->next_of_kin_address = $request->next_of_kin_address;
            $patient->hmo_id = $request->hmo_id ?? 1;
            $patient->hmo_no = $request->hmo_no;

            // Handle passport photo upload - save to user model
            if ($request->hasFile('filename')) {
                // Delete old file if exists
                if ($user->filename && $user->filename !== 'avatar.png' && Storage::exists('public/image/user/' . $user->filename)) {
                    Storage::delete('public/image/user/' . $user->filename);
                }
                $file = $request->file('filename');
                $filename = 'patient_' . time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();

                // Save main image
                $path = storage_path('/app/public/image/user/');
                if (!file_exists($path)) {
                    mkdir($path, 0755, true);
                }
                Image::make($file)
                    ->resize(215, 215)
                    ->save($path . $filename);

                // Save thumbnail
                $thumbnail_path = storage_path('/app/public/image/user/thumbnail/');
                if (!file_exists($thumbnail_path)) {
                    mkdir($thumbnail_path, 0755, true);
                }
                Image::make($file)
                    ->resize(106, 106)
                    ->save($thumbnail_path . $filename);

                $user->filename = $filename;
                $user->save();
            } elseif ($request->passport_data) {
                // Handle webcam captured photo (base64 data)
                // Delete old file if exists
                if ($user->filename && $user->filename !== 'avatar.png' && Storage::exists('public/image/user/' . $user->filename)) {
                    Storage::delete('public/image/user/' . $user->filename);
                }

                $imageData = $request->passport_data;

                // Remove data URL prefix if present
                if (strpos($imageData, 'data:image') === 0) {
                    $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
                }

                $decodedImage = base64_decode($imageData);
                $filename = 'patient_' . time() . '_' . $user->id . '.jpg';

                // Save main image
                $path = storage_path('/app/public/image/user/');
                if (!file_exists($path)) {
                    mkdir($path, 0755, true);
                }
                Image::make($decodedImage)
                    ->resize(215, 215)
                    ->save($path . $filename);

                // Save thumbnail
                $thumbnail_path = storage_path('/app/public/image/user/thumbnail/');
                if (!file_exists($thumbnail_path)) {
                    mkdir($thumbnail_path, 0755, true);
                }
                Image::make($decodedImage)
                    ->resize(106, 106)
                    ->save($thumbnail_path . $filename);

                $user->filename = $filename;
                $user->save();
            }

            // Handle old records upload
            if ($request->hasFile('old_records')) {
                // Delete old file if exists
                if ($user->old_records && Storage::exists('public/image/user/old_records/' . $user->old_records)) {
                    Storage::delete('public/image/user/old_records/' . $user->old_records);
                }
                $file = $request->file('old_records');
                $filename = 'records_' . time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
                Storage::disk('old_records')->put($filename, $file->get());
                $user->old_records = $filename;
                $user->save();
            }

            $patient->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Patient updated successfully',
                'patient' => [
                    'id' => $patient->id,
                    'user_id' => $user->id,
                    'name' => userfullname($user->id),
                    'file_no' => $patient->file_no,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating patient', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update patient: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ==========================================
    // REPORTS METHODS
    // ==========================================

    /**
     * Get reports statistics
     */
    public function getReportsStatistics(Request $request)
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->endOfDay() : Carbon::now()->endOfDay();
        $clinicId = $request->clinic_id;
        $hmoId = $request->hmo_id;

        // New registrations
        $registrationsQuery = patient::whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($hmoId) {
            $registrationsQuery->where('hmo_id', $hmoId);
        }
        $newRegistrations = $registrationsQuery->count();

        // Queue entries
        $queueQuery = DoctorQueue::whereBetween('doctor_queues.created_at', [$dateFrom, $dateTo]);
        if ($clinicId) {
            $queueQuery->where('clinic_id', $clinicId);
        }
        $totalQueued = $queueQuery->count();
        $pendingQueue = (clone $queueQuery)->whereIn('status', [1, 2, 3])->count();

        // Completed visits (encounters)
        $visitsQuery = Encounter::whereBetween('encounters.created_at', [$dateFrom, $dateTo]);
        $completedVisits = $visitsQuery->count();

        // Average wait time - estimate from queue entries (no start_time column, use 15 min average)
        $avgWaitTime = 15; // Default estimate since start_time doesn't exist

        // Return visit rate (patients with multiple visits)
        $totalPatients = Encounter::whereBetween('created_at', [$dateFrom, $dateTo])
            ->distinct('patient_id')
            ->count('patient_id');

        $returnPatients = Encounter::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('patient_id')
            ->groupBy('patient_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $returnRate = $totalPatients > 0 ? round(($returnPatients / $totalPatients) * 100, 1) : 0;

        // Top clinics
        $topClinics = DoctorQueue::whereBetween('doctor_queues.created_at', [$dateFrom, $dateTo])
            ->join('clinics', 'doctor_queues.clinic_id', '=', 'clinics.id')
            ->select('clinics.name', DB::raw('COUNT(*) as visits'))
            ->groupBy('clinics.id', 'clinics.name')
            ->orderByDesc('visits')
            ->limit(5)
            ->get();

        $totalClinicVisits = $topClinics->sum('visits');

        return response()->json([
            'new_registrations' => $newRegistrations,
            'total_queued' => $totalQueued,
            'completed_visits' => $completedVisits,
            'pending_queue' => $pendingQueue,
            'avg_wait_time' => round($avgWaitTime ?? 0),
            'return_rate' => $returnRate,
            'top_clinics' => $topClinics->map(function ($clinic) use ($totalClinicVisits) {
                return [
                    'name' => $clinic->name,
                    'visits' => $clinic->visits,
                    'percentage' => $totalClinicVisits > 0 ? round(($clinic->visits / $totalClinicVisits) * 100, 1) : 0,
                ];
            }),
        ]);
    }

    /**
     * Get registrations report data (DataTables server-side)
     */
    public function getRegistrationsReport(Request $request)
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->endOfDay() : Carbon::now()->endOfDay();

        $query = patient::with(['user', 'hmo'])
            ->whereBetween('patients.created_at', [$dateFrom, $dateTo]);

        // Filters
        if ($request->hmo_id) {
            $query->where('hmo_id', $request->hmo_id);
        }

        if ($request->patient_search) {
            $search = $request->patient_search;
            $query->where(function ($q) use ($search) {
                $q->where('file_no', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('surname', 'like', "%{$search}%")
                            ->orWhere('firstname', 'like', "%{$search}%");
                    });
            });
        }

        return DataTables::of($query)
            ->addColumn('date', function ($patient) {
                return $patient->created_at->format('M d, Y H:i');
            })
            ->addColumn('file_no', function ($patient) {
                return $patient->file_no ?? 'N/A';
            })
            ->addColumn('patient_name', function ($patient) {
                return userfullname($patient->user_id);
            })
            ->addColumn('gender', function ($patient) {
                return $patient->gender ?? '-';
            })
            ->addColumn('age', function ($patient) {
                if ($patient->dob) {
                    $dob = $this->parsePatientDob($patient->dob);
                    return $dob ? $dob->age . 'y' : '-';
                }
                return '-';
            })
            ->addColumn('phone', function ($patient) {
                return $patient->phone_no ?? '-';
            })
            ->addColumn('hmo', function ($patient) {
                return $patient->hmo->name ?? 'Private';
            })
            ->addColumn('registered_by', function ($patient) {
                return 'System';
            })
            ->addColumn('actions', function ($patient) {
                return '<button class="btn btn-sm btn-info view-patient-btn" data-id="' . $patient->id . '">
                            <i class="mdi mdi-eye"></i>
                        </button>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Get queue report data (DataTables server-side)
     */
    public function getQueueReport(Request $request)
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->endOfDay() : Carbon::now()->endOfDay();

        $query = DoctorQueue::with(['patient.user', 'clinic', 'doctor', 'request_entry.service'])
            ->whereBetween('doctor_queues.created_at', [$dateFrom, $dateTo]);

        // Filters
        if ($request->clinic_id) {
            $query->where('clinic_id', $request->clinic_id);
        }

        if ($request->patient_search) {
            $search = $request->patient_search;
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('file_no', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('surname', 'like', "%{$search}%")
                            ->orWhere('firstname', 'like', "%{$search}%");
                    });
            });
        }

        return DataTables::of($query)
            ->addColumn('datetime', function ($q) {
                return $q->created_at->format('M d, Y H:i');
            })
            ->addColumn('file_no', function ($q) {
                return $q->patient->file_no ?? 'N/A';
            })
            ->addColumn('patient_name', function ($q) {
                return userfullname($q->patient->user_id ?? 0);
            })
            ->addColumn('clinic', function ($q) {
                return $q->clinic->name ?? '-';
            })
            ->addColumn('doctor', function ($q) {
                return $q->doctor ? userfullname($q->doctor->id) : 'Any Available';
            })
            ->addColumn('service', function ($q) {
                return $q->request_entry->service->service_name ?? 'Consultation';
            })
            ->addColumn('status', function ($q) {
                $statuses = [
                    1 => '<span class="badge bg-warning">Waiting</span>',
                    2 => '<span class="badge bg-info">Vitals Pending</span>',
                    3 => '<span class="badge bg-primary">In Consultation</span>',
                    4 => '<span class="badge bg-success">Completed</span>',
                ];
                return $statuses[$q->status] ?? '<span class="badge bg-secondary">Unknown</span>';
            })
            ->addColumn('wait_time', function ($q) {
                if ($q->start_time) {
                    $wait = Carbon::parse($q->created_at)->diffInMinutes(Carbon::parse($q->start_time));
                    return $wait . 'm';
                } elseif ($q->status < 4) {
                    $wait = Carbon::parse($q->created_at)->diffInMinutes(Carbon::now());
                    return $wait . 'm (ongoing)';
                }
                return '-';
            })
            ->addColumn('actions', function ($q) {
                return '<button class="btn btn-sm btn-info view-patient-btn" data-id="' . $q->patient_id . '">
                            <i class="mdi mdi-eye"></i>
                        </button>';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    /**
     * Get visits report data (DataTables server-side)
     */
    public function getVisitsReport(Request $request)
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->endOfDay() : Carbon::now()->endOfDay();

        $query = Encounter::with(['patient.user', 'patient.hmo', 'doctor', 'service'])
            ->whereBetween('encounters.created_at', [$dateFrom, $dateTo]);

        // Filters
        if ($request->hmo_id) {
            $query->whereHas('patient', function ($q) use ($request) {
                $q->where('hmo_id', $request->hmo_id);
            });
        }

        if ($request->patient_search) {
            $search = $request->patient_search;
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('file_no', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('surname', 'like', "%{$search}%")
                            ->orWhere('firstname', 'like', "%{$search}%");
                    });
            });
        }

        return DataTables::of($query)
            ->addColumn('date', function ($e) {
                return $e->created_at->format('M d, Y');
            })
            ->addColumn('file_no', function ($e) {
                return $e->patient->file_no ?? 'N/A';
            })
            ->addColumn('patient_name', function ($e) {
                return userfullname($e->patient->user_id ?? 0);
            })
            ->addColumn('clinic', function ($e) {
                // Get clinic from doctor queue if exists
                $queue = DoctorQueue::where('patient_id', $e->patient_id)
                    ->whereDate('created_at', $e->created_at->toDateString())
                    ->with('clinic')
                    ->first();
                return $queue && $queue->clinic ? $queue->clinic->name : '-';
            })
            ->addColumn('doctor', function ($e) {
                return $e->doctor ? userfullname($e->doctor->id) : '-';
            })
            ->addColumn('reason', function ($e) {
                $reason = $e->reasons_for_encounter ?? '-';
                return strlen($reason) > 30 ? substr($reason, 0, 30) . '...' : $reason;
            })
            ->addColumn('hmo', function ($e) {
                return $e->patient->hmo->name ?? 'Private';
            })
            ->addColumn('type', function ($e) {
                $previousVisits = Encounter::where('patient_id', $e->patient_id)
                    ->where('created_at', '<', $e->created_at)
                    ->count();
                return $previousVisits > 0
                    ? '<span class="badge bg-info">Return</span>'
                    : '<span class="badge bg-success">New</span>';
            })
            ->addColumn('actions', function ($e) {
                return '<button class="btn btn-sm btn-info view-patient-btn" data-id="' . $e->patient_id . '">
                            <i class="mdi mdi-eye"></i>
                        </button>';
            })
            ->rawColumns(['type', 'actions'])
            ->make(true);
    }

    /**
     * Get chart data for reports
     */
    public function getChartData(Request $request)
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->endOfDay() : Carbon::now()->endOfDay();

        // Registration trends
        // Registration trends
        $registrationTrends = patient::whereBetween('patients.created_at', [$dateFrom, $dateTo])
            ->select(DB::raw('DATE(patients.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(patients.created_at)'))
            ->orderBy('date')
            ->get();

        // HMO distribution
        $hmoDistribution = patient::whereBetween('patients.created_at', [$dateFrom, $dateTo])
            ->join('hmos', 'patients.hmo_id', '=', 'hmos.id')
            ->select('hmos.name', DB::raw('COUNT(*) as count'))
            ->groupBy('hmos.id', 'hmos.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        // Peak hours
        $peakHours = DoctorQueue::whereBetween('doctor_queues.created_at', [$dateFrom, $dateTo])
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get();

        return response()->json([
            'registration_trends' => [
                'labels' => $registrationTrends->pluck('date')->map(function ($d) {
                    return Carbon::parse($d)->format('M d');
                }),
                'data' => $registrationTrends->pluck('count'),
            ],
            'hmo_distribution' => [
                'labels' => $hmoDistribution->pluck('name'),
                'data' => $hmoDistribution->pluck('count'),
            ],
            'peak_hours' => [
                'labels' => $peakHours->pluck('hour')->map(function ($h) {
                    return sprintf('%02d:00', $h);
                }),
                'data' => $peakHours->pluck('count'),
            ],
        ]);
    }

    /**
     * Get recent requests for a patient (last 24 hours)
     */
    public function getRecentRequests($patientId)
    {
        $patient = patient::findOrFail($patientId);
        $since = Carbon::now()->subHours(24);

        $requests = collect();

        // Lab Requests
        $labRequests = LabServiceRequest::with(['service', 'productOrServiceRequest.payment'])
            ->where('patient_id', $patientId)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                $posr = $req->productOrServiceRequest;
                return [
                    'id' => $req->id,
                    'type' => 'lab',
                    'type_label' => 'Lab Test',
                    'name' => optional($req->service)->service_name ?? 'Unknown Service',
                    'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : optional(optional($req->service)->price)->sale_price ?? 0,
                    'hmo_covers' => $posr->claims_amount ?? 0,
                    'payable' => $posr->payable_amount ?? optional(optional($req->service)->price)->sale_price ?? 0,
                    'coverage_mode' => $posr->coverage_mode ?? null,
                    'billing_status' => $this->getBillingStatus($req->status, $posr),
                    'delivery_status' => $this->getDeliveryStatus($req->status, 'lab'),
                    'created_at' => $req->created_at,
                ];
            });
        $requests = $requests->merge($labRequests);

        // Imaging Requests
        $imagingRequests = ImagingServiceRequest::with(['service', 'productOrServiceRequest.payment'])
            ->where('patient_id', $patientId)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                $posr = $req->productOrServiceRequest;
                return [
                    'id' => $req->id,
                    'type' => 'imaging',
                    'type_label' => 'Imaging',
                    'name' => optional($req->service)->service_name ?? 'Unknown Service',
                    'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : optional(optional($req->service)->price)->sale_price ?? 0,
                    'hmo_covers' => $posr->claims_amount ?? 0,
                    'payable' => $posr->payable_amount ?? optional(optional($req->service)->price)->sale_price ?? 0,
                    'coverage_mode' => $posr->coverage_mode ?? null,
                    'billing_status' => $this->getBillingStatus($req->status, $posr),
                    'delivery_status' => $this->getDeliveryStatus($req->status, 'imaging'),
                    'created_at' => $req->created_at,
                ];
            });
        $requests = $requests->merge($imagingRequests);

        // Product Requests
        $productRequests = ProductRequest::with(['product.price', 'productOrServiceRequest.payment'])
            ->where('patient_id', $patientId)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                $posr = $req->productOrServiceRequest;
                $qty = $posr->qty ?? 1;
                $unitPrice = optional(optional($req->product)->price)->current_sale_price ?? 0;
                return [
                    'id' => $req->id,
                    'type' => 'product',
                    'type_label' => 'Product',
                    'name' => optional($req->product)->product_name ?? 'Unknown Product',
                    'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : ($unitPrice * $qty),
                    'hmo_covers' => $posr->claims_amount ?? 0,
                    'payable' => $posr->payable_amount ?? ($unitPrice * $qty),
                    'coverage_mode' => $posr->coverage_mode ?? null,
                    'billing_status' => $this->getProductBillingStatus($req->status, $posr),
                    'delivery_status' => $this->getProductDeliveryStatus($req->status),
                    'created_at' => $req->created_at,
                ];
            });
        $requests = $requests->merge($productRequests);

        // Sort all requests by created_at desc
        $requests = $requests->sortByDesc('created_at')->values();

        return response()->json([
            'success' => true,
            'requests' => $requests,
        ]);
    }

    /**
     * Get service requests for DataTable (all history with filters)
     */
    public function getServiceRequests($patientId, Request $request)
    {
        $patient = patient::findOrFail($patientId);

        // Build combined query results
        $allRequests = collect();

        // Date filters
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->endOfDay() : null;

        // Type filter
        $typeFilter = $request->type_filter;

        // Billing status filter
        $billingFilter = $request->billing_filter;

        // Delivery status filter
        $deliveryFilter = $request->delivery_filter;

        // Lab Requests
        if (!$typeFilter || $typeFilter === 'lab') {
            $labQuery = LabServiceRequest::with(['service.price', 'productOrServiceRequest.payment', 'doctor'])
                ->where('patient_id', $patientId);

            if ($dateFrom) $labQuery->where('created_at', '>=', $dateFrom);
            if ($dateTo) $labQuery->where('created_at', '<=', $dateTo);

            $labRequests = $labQuery->orderBy('created_at', 'desc')->get()
                ->map(function ($req) {
                    $posr = $req->productOrServiceRequest;
                    $basePrice = optional(optional($req->service)->price)->sale_price ?? 0;
                    return [
                        'id' => $req->id,
                        'request_no' => 'LAB-' . str_pad($req->id, 6, '0', STR_PAD_LEFT),
                        'type' => 'lab',
                        'type_label' => 'Lab Test',
                        'name' => optional($req->service)->service_name ?? 'Unknown Service',
                        'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : $basePrice,
                        'hmo_covers' => $posr->claims_amount ?? 0,
                        'payable' => $posr->payable_amount ?? $basePrice,
                        'coverage_mode' => $posr->coverage_mode ?? null,
                        'billing_status' => $this->getBillingStatus($req->status, $posr),
                        'billing_status_code' => $this->getBillingStatusCode($req->status, $posr),
                        'delivery_status' => $this->getDeliveryStatus($req->status, 'lab'),
                        'delivery_status_code' => $this->getDeliveryStatusCode($req->status, 'lab'),
                        'requested_by' => $req->doctor ? ($req->doctor->surname . ' ' . $req->doctor->firstname) : 'Walk-in',
                        'created_at' => $req->created_at,
                        'posr_id' => $posr->id ?? null,
                        'created_by_id' => $posr->staff_user_id ?? null,
                        'is_paid' => $posr && $posr->payment_id ? true : false,
                    ];
                });
            $allRequests = $allRequests->merge($labRequests);
        }

        // Imaging Requests
        if (!$typeFilter || $typeFilter === 'imaging') {
            $imagingQuery = ImagingServiceRequest::with(['service.price', 'productOrServiceRequest.payment', 'doctor'])
                ->where('patient_id', $patientId);

            if ($dateFrom) $imagingQuery->where('created_at', '>=', $dateFrom);
            if ($dateTo) $imagingQuery->where('created_at', '<=', $dateTo);

            $imagingRequests = $imagingQuery->orderBy('created_at', 'desc')->get()
                ->map(function ($req) {
                    $posr = $req->productOrServiceRequest;
                    $basePrice = optional(optional($req->service)->price)->sale_price ?? 0;
                    return [
                        'id' => $req->id,
                        'request_no' => 'IMG-' . str_pad($req->id, 6, '0', STR_PAD_LEFT),
                        'type' => 'imaging',
                        'type_label' => 'Imaging',
                        'name' => optional($req->service)->service_name ?? 'Unknown Service',
                        'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : $basePrice,
                        'hmo_covers' => $posr->claims_amount ?? 0,
                        'payable' => $posr->payable_amount ?? $basePrice,
                        'coverage_mode' => $posr->coverage_mode ?? null,
                        'billing_status' => $this->getBillingStatus($req->status, $posr),
                        'billing_status_code' => $this->getBillingStatusCode($req->status, $posr),
                        'delivery_status' => $this->getDeliveryStatus($req->status, 'imaging'),
                        'delivery_status_code' => $this->getDeliveryStatusCode($req->status, 'imaging'),
                        'requested_by' => $req->doctor ? ($req->doctor->surname . ' ' . $req->doctor->firstname) : 'Walk-in',
                        'created_at' => $req->created_at,
                        'posr_id' => $posr->id ?? null,
                        'created_by_id' => $posr->staff_user_id ?? null,
                        'is_paid' => $posr && $posr->payment_id ? true : false,
                    ];
                });
            $allRequests = $allRequests->merge($imagingRequests);
        }

        // Product Requests
        if (!$typeFilter || $typeFilter === 'product') {
            $productQuery = ProductRequest::with(['product.price', 'productOrServiceRequest.payment', 'doctor'])
                ->where('patient_id', $patientId);

            if ($dateFrom) $productQuery->where('created_at', '>=', $dateFrom);
            if ($dateTo) $productQuery->where('created_at', '<=', $dateTo);

            $productRequests = $productQuery->orderBy('created_at', 'desc')->get()
                ->map(function ($req) {
                    $posr = $req->productOrServiceRequest;
                    $qty = $posr->qty ?? 1;
                    $unitPrice = optional(optional($req->product)->price)->current_sale_price ?? 0;
                    $basePrice = $unitPrice * $qty;
                    return [
                        'id' => $req->id,
                        'request_no' => 'PRD-' . str_pad($req->id, 6, '0', STR_PAD_LEFT),
                        'type' => 'product',
                        'type_label' => 'Product',
                        'name' => optional($req->product)->product_name ?? 'Unknown Product',
                        'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : $basePrice,
                        'hmo_covers' => $posr->claims_amount ?? 0,
                        'payable' => $posr->payable_amount ?? $basePrice,
                        'coverage_mode' => $posr->coverage_mode ?? null,
                        'billing_status' => $this->getProductBillingStatus($req->status, $posr),
                        'billing_status_code' => $this->getProductBillingStatusCode($req->status, $posr),
                        'delivery_status' => $this->getProductDeliveryStatus($req->status),
                        'delivery_status_code' => $this->getProductDeliveryStatusCode($req->status),
                        'requested_by' => $req->doctor ? ($req->doctor->surname . ' ' . $req->doctor->firstname) : 'Walk-in',
                        'created_at' => $req->created_at,
                        'posr_id' => $posr->id ?? null,
                        'created_by_id' => $posr->staff_user_id ?? null,
                        'is_paid' => $posr && $posr->payment_id ? true : false,
                    ];
                });
            $allRequests = $allRequests->merge($productRequests);
        }

        // Apply billing filter
        if ($billingFilter) {
            $allRequests = $allRequests->filter(function ($req) use ($billingFilter) {
                return $req['billing_status_code'] === $billingFilter;
            });
        }

        // Apply delivery filter
        if ($deliveryFilter) {
            $allRequests = $allRequests->filter(function ($req) use ($deliveryFilter) {
                return $req['delivery_status_code'] === $deliveryFilter;
            });
        }

        // Sort by created_at desc
        $allRequests = $allRequests->sortByDesc('created_at')->values();

        return DataTables::of($allRequests)
            ->addColumn('date_formatted', function ($row) {
                return Carbon::parse($row['created_at'])->format('d M Y H:i');
            })
            ->addColumn('price_formatted', function ($row) {
                return '₦' . number_format($row['price'], 2);
            })
            ->addColumn('hmo_covers_formatted', function ($row) {
                return $row['hmo_covers'] > 0 ? '₦' . number_format($row['hmo_covers'], 2) : '-';
            })
            ->addColumn('payable_formatted', function ($row) {
                return '₦' . number_format($row['payable'], 2);
            })
            ->addColumn('billing_badge', function ($row) {
                $statusMap = [
                    'pending' => '<span class="billing-badge billing-pending">Pending</span>',
                    'billed' => '<span class="billing-badge billing-billed">Billed</span>',
                    'paid' => '<span class="billing-badge billing-paid">Paid</span>',
                ];
                return $statusMap[$row['billing_status_code']] ?? '<span class="billing-badge">Unknown</span>';
            })
            ->addColumn('delivery_badge', function ($row) {
                $statusMap = [
                    'pending' => '<span class="delivery-badge delivery-pending">Pending</span>',
                    'in_progress' => '<span class="delivery-badge delivery-progress">In Progress</span>',
                    'completed' => '<span class="delivery-badge delivery-completed">Completed</span>',
                ];
                return $statusMap[$row['delivery_status_code']] ?? '<span class="delivery-badge">Unknown</span>';
            })
            ->addColumn('type_badge', function ($row) {
                $typeColors = [
                    'lab' => 'badge-info',
                    'imaging' => 'badge-warning',
                    'product' => 'badge-success',
                ];
                return '<span class="badge ' . ($typeColors[$row['type']] ?? 'badge-secondary') . '">' . $row['type_label'] . '</span>';
            })
            ->addColumn('actions', function ($row) {
                $currentUserId = Auth::id();
                $actions = '<button class="btn btn-xs btn-outline-primary view-request-btn" data-type="' . $row['type'] . '" data-id="' . $row['id'] . '" title="View Details">
                    <i class="mdi mdi-eye"></i>
                </button>';

                // Show discard button only if:
                // 1. Not paid
                // 2. Created by current user OR user is admin
                $canDiscard = !$row['is_paid'] && ($row['created_by_id'] == $currentUserId || Auth::user()->hasRole(['SUPERADMIN', 'ADMIN']));

                if ($canDiscard) {
                    $actions .= ' <button class="btn btn-xs btn-outline-danger discard-request-btn"
                        data-type="' . $row['type'] . '"
                        data-id="' . $row['id'] . '"
                        data-name="' . htmlspecialchars($row['name']) . '"
                        data-request-no="' . $row['request_no'] . '"
                        title="Discard Request">
                        <i class="mdi mdi-delete"></i>
                    </button>';
                }

                return $actions;
            })
            ->rawColumns(['billing_badge', 'delivery_badge', 'type_badge', 'actions'])
            ->make(true);
    }

    /**
     * Get service requests stats summary
     */
    public function getServiceRequestsStats($patientId, Request $request)
    {
        $patient = patient::findOrFail($patientId);

        // Date filters
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->endOfDay() : null;

        $stats = [
            'total_requests' => 0,
            'hmo_covered' => 0,
            'patient_payable' => 0,
            'completed' => 0,
        ];

        // Lab Requests
        $labQuery = LabServiceRequest::with(['productOrServiceRequest'])
            ->where('patient_id', $patientId);
        if ($dateFrom) $labQuery->where('created_at', '>=', $dateFrom);
        if ($dateTo) $labQuery->where('created_at', '<=', $dateTo);
        $labRequests = $labQuery->get();

        $stats['total_requests'] += $labRequests->count();
        $stats['completed'] += $labRequests->where('status', 4)->count(); // status 4 = completed with result
        foreach ($labRequests as $req) {
            if ($req->productOrServiceRequest) {
                $stats['hmo_covered'] += $req->productOrServiceRequest->claims_amount ?? 0;
                $stats['patient_payable'] += $req->productOrServiceRequest->payable_amount ?? 0;
            }
        }

        // Imaging Requests
        $imagingQuery = ImagingServiceRequest::with(['productOrServiceRequest'])
            ->where('patient_id', $patientId);
        if ($dateFrom) $imagingQuery->where('created_at', '>=', $dateFrom);
        if ($dateTo) $imagingQuery->where('created_at', '<=', $dateTo);
        $imagingRequests = $imagingQuery->get();

        $stats['total_requests'] += $imagingRequests->count();
        $stats['completed'] += $imagingRequests->where('status', 3)->count(); // status 3 = completed with result
        foreach ($imagingRequests as $req) {
            if ($req->productOrServiceRequest) {
                $stats['hmo_covered'] += $req->productOrServiceRequest->claims_amount ?? 0;
                $stats['patient_payable'] += $req->productOrServiceRequest->payable_amount ?? 0;
            }
        }

        // Product Requests
        $productQuery = ProductRequest::with(['productOrServiceRequest'])
            ->where('patient_id', $patientId);
        if ($dateFrom) $productQuery->where('created_at', '>=', $dateFrom);
        if ($dateTo) $productQuery->where('created_at', '<=', $dateTo);
        $productRequests = $productQuery->get();

        $stats['total_requests'] += $productRequests->count();
        $stats['completed'] += $productRequests->where('status', 3)->count(); // status 3 = dispensed
        foreach ($productRequests as $req) {
            if ($req->productOrServiceRequest) {
                $stats['hmo_covered'] += $req->productOrServiceRequest->claims_amount ?? 0;
                $stats['patient_payable'] += $req->productOrServiceRequest->payable_amount ?? 0;
            }
        }

        return response()->json([
            'success' => true,
            'stats' => [
                'total_requests' => $stats['total_requests'],
                'hmo_covered' => '₦' . number_format($stats['hmo_covered'], 2),
                'patient_payable' => '₦' . number_format($stats['patient_payable'], 2),
                'completed' => $stats['completed'],
            ],
        ]);
    }

    /**
     * Helper: Get billing status for lab/imaging requests
     * Lab: status 1=pending billing, 2+=billed (has POSR)
     * Imaging: status 1=pending billing, 2+=billed (has POSR)
     */
    private function getBillingStatus($status, $posr)
    {
        // If ProductOrServiceRequest exists, check payment
        if ($posr) {
            if ($posr->payment_id) {
                return 'Paid';
            }
            return 'Billed';
        }

        // No POSR means pending billing
        return 'Pending Billing';
    }

    /**
     * Helper: Get billing status code for lab/imaging requests
     */
    private function getBillingStatusCode($status, $posr)
    {
        if ($posr) {
            if ($posr->payment_id) {
                return 'paid';
            }
            return 'billed';
        }

        return 'pending';
    }

    /**
     * Helper: Get delivery status for lab/imaging requests
     */
    private function getDeliveryStatus($status, $type)
    {
        // Lab: 1=billing, 2=awaiting sample, 3=awaiting results, 4=completed
        // Imaging: 1=billing, 2=awaiting results, 3=completed
        if ($type === 'lab') {
            if ($status == 1) return 'Pending Billing';
            if ($status == 2) return 'Awaiting Sample';
            if ($status == 3) return 'Awaiting Results';
            if ($status == 4) return 'Completed';
        } else {
            if ($status == 1) return 'Pending Billing';
            if ($status == 2) return 'Awaiting Results';
            if ($status == 3) return 'Completed';
        }
        return 'Unknown';
    }

    /**
     * Helper: Get delivery status code for lab/imaging requests
     */
    private function getDeliveryStatusCode($status, $type)
    {
        // Lab: 1=pending, 2=awaiting sample, 3=awaiting results, 4=completed
        // Imaging: 1=pending, 2=awaiting results, 3=completed
        if ($type === 'lab') {
            if ($status == 1) return 'pending';
            if ($status == 2) return 'in_progress'; // Awaiting sample
            if ($status == 3) return 'in_progress'; // Awaiting results
            if ($status == 4) return 'completed';
        } else {
            if ($status == 1) return 'pending';
            if ($status == 2) return 'in_progress';
            if ($status == 3) return 'completed';
        }
        return 'pending';
    }

    /**
     * Helper: Get billing status for product requests
     * Product: status 1=pending billing, 2+=billed (has POSR)
     */
    private function getProductBillingStatus($status, $posr)
    {
        // If ProductOrServiceRequest exists, check payment
        if ($posr) {
            if ($posr->payment_id) {
                return 'Paid';
            }
            return 'Billed';
        }

        // No POSR means pending billing
        return 'Pending Billing';
    }

    /**
     * Helper: Get billing status code for product requests
     */
    private function getProductBillingStatusCode($status, $posr)
    {
        if ($posr) {
            if ($posr->payment_id) {
                return 'paid';
            }
            return 'billed';
        }

        return 'pending';
    }

    /**
     * Helper: Get delivery status for product requests
     * Product: 1=pending billing, 2=billed (awaiting dispensing), 3=dispensed
     */
    private function getProductDeliveryStatus($status)
    {
        if ($status == 1) return 'Pending Billing';
        if ($status == 2) return 'Awaiting Dispensing';
        if ($status == 3) return 'Dispensed';
        return 'Unknown';
    }

    /**
     * Helper: Get delivery status code for product requests
     */
    private function getProductDeliveryStatusCode($status)
    {
        if ($status == 1) return 'pending';
        if ($status == 2) return 'in_progress';
        if ($status == 3) return 'completed';
        return 'pending';
    }

    /**
     * Get detailed information about a specific request
     */
    public function getRequestDetails($type, $id)
    {
        try {
            $details = [];

            switch ($type) {
                case 'lab':
                    $request = LabServiceRequest::with([
                        'service.price',
                        'productOrServiceRequest.payment',
                        'patient.user',
                        'patient.hmo',
                        'doctor',
                        'biller',
                        'resultBy',
                        'encounter'
                    ])->findOrFail($id);

                    $posr = $request->productOrServiceRequest;
                    $basePrice = optional(optional($request->service)->price)->sale_price ?? 0;

                    $details = [
                        'type' => 'lab',
                        'type_label' => 'Lab Test',
                        'request_no' => 'LAB-' . str_pad($request->id, 6, '0', STR_PAD_LEFT),
                        'service_name' => optional($request->service)->service_name ?? 'Unknown Service',
                        'service_category' => optional(optional($request->service)->category)->name ?? 'Uncategorized',
                        'patient_name' => userfullname($request->patient->user_id ?? 0),
                        'patient_file_no' => $request->patient->file_no ?? 'N/A',
                        'hmo_name' => optional($request->patient->hmo)->name ?? 'Private',
                        'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : $basePrice,
                        'hmo_covers' => $posr->claims_amount ?? 0,
                        'payable' => $posr->payable_amount ?? $basePrice,
                        'coverage_mode' => $posr->coverage_mode ?? null,
                        'billing_status' => $this->getBillingStatus($request->status, $posr),
                        'billing_status_code' => $this->getBillingStatusCode($request->status, $posr),
                        'delivery_status' => $this->getDeliveryStatus($request->status, 'lab'),
                        'delivery_status_code' => $this->getDeliveryStatusCode($request->status, 'lab'),
                        'requested_by' => $request->doctor ? ($request->doctor->surname . ' ' . $request->doctor->firstname) : 'Walk-in Request',
                        'requested_at' => $request->created_at->format('d M Y, H:i'),
                        'billed_by' => $request->biller ? ($request->biller->surname . ' ' . $request->biller->firstname) : null,
                        'billed_at' => $request->billed_date ? Carbon::parse($request->billed_date)->format('d M Y, H:i') : null,
                        'sample_taken' => $request->sample_taken ? true : false,
                        'sample_date' => $request->sample_date ? Carbon::parse($request->sample_date)->format('d M Y, H:i') : null,
                        'sample_taken_by' => $request->sample_taken_by ? userfullname($request->sample_taken_by) : null,
                        'result_by' => $request->resultBy ? ($request->resultBy->surname . ' ' . $request->resultBy->firstname) : null,
                        'result_date' => $request->result_date ? Carbon::parse($request->result_date)->format('d M Y, H:i') : null,
                        'has_result' => !empty($request->result) || !empty($request->result_data),
                        'result_summary' => $request->result ? substr(strip_tags($request->result), 0, 200) . '...' : null,
                        'clinical_note' => $request->note,
                        'encounter_id' => $request->encounter_id,
                        'payment_reference' => $posr && $posr->payment ? $posr->payment->payment_ref : null,
                        'payment_date' => $posr && $posr->payment ? Carbon::parse($posr->payment->created_at)->format('d M Y, H:i') : null,
                    ];
                    break;

                case 'imaging':
                    $request = ImagingServiceRequest::with([
                        'service.price',
                        'productOrServiceRequest.payment',
                        'patient.user',
                        'patient.hmo',
                        'doctor',
                        'biller',
                        'resultBy',
                        'encounter'
                    ])->findOrFail($id);

                    $posr = $request->productOrServiceRequest;
                    $basePrice = optional(optional($request->service)->price)->sale_price ?? 0;

                    $details = [
                        'type' => 'imaging',
                        'type_label' => 'Imaging',
                        'request_no' => 'IMG-' . str_pad($request->id, 6, '0', STR_PAD_LEFT),
                        'service_name' => optional($request->service)->service_name ?? 'Unknown Service',
                        'service_category' => optional(optional($request->service)->category)->name ?? 'Uncategorized',
                        'patient_name' => userfullname($request->patient->user_id ?? 0),
                        'patient_file_no' => $request->patient->file_no ?? 'N/A',
                        'hmo_name' => optional($request->patient->hmo)->name ?? 'Private',
                        'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : $basePrice,
                        'hmo_covers' => $posr->claims_amount ?? 0,
                        'payable' => $posr->payable_amount ?? $basePrice,
                        'coverage_mode' => $posr->coverage_mode ?? null,
                        'billing_status' => $this->getBillingStatus($request->status, $posr),
                        'billing_status_code' => $this->getBillingStatusCode($request->status, $posr),
                        'delivery_status' => $this->getDeliveryStatus($request->status, 'imaging'),
                        'delivery_status_code' => $this->getDeliveryStatusCode($request->status, 'imaging'),
                        'requested_by' => $request->doctor ? ($request->doctor->surname . ' ' . $request->doctor->firstname) : 'Walk-in Request',
                        'requested_at' => $request->created_at->format('d M Y, H:i'),
                        'billed_by' => $request->biller ? ($request->biller->surname . ' ' . $request->biller->firstname) : null,
                        'billed_at' => $request->billed_date ? Carbon::parse($request->billed_date)->format('d M Y, H:i') : null,
                        'result_by' => $request->resultBy ? ($request->resultBy->surname . ' ' . $request->resultBy->firstname) : null,
                        'result_date' => $request->result_date ? Carbon::parse($request->result_date)->format('d M Y, H:i') : null,
                        'has_result' => !empty($request->result) || !empty($request->result_data),
                        'result_summary' => $request->result ? substr(strip_tags($request->result), 0, 200) . '...' : null,
                        'has_attachments' => !empty($request->attachments),
                        'attachment_count' => is_array($request->attachments) ? count($request->attachments) : 0,
                        'clinical_note' => $request->note,
                        'encounter_id' => $request->encounter_id,
                        'payment_reference' => $posr && $posr->payment ? $posr->payment->payment_ref : null,
                        'payment_date' => $posr && $posr->payment ? Carbon::parse($posr->payment->created_at)->format('d M Y, H:i') : null,
                    ];
                    break;

                case 'product':
                    $request = ProductRequest::with([
                        'product.price',
                        'product.category',
                        'productOrServiceRequest.payment',
                        'patient.user',
                        'patient.hmo',
                        'doctor',
                        'biller',
                        'dispenser',
                        'encounter'
                    ])->findOrFail($id);

                    $posr = $request->productOrServiceRequest;
                    $qty = $posr->qty ?? 1;
                    $unitPrice = optional(optional($request->product)->price)->current_sale_price ?? 0;
                    $basePrice = $unitPrice * $qty;

                    $details = [
                        'type' => 'product',
                        'type_label' => 'Product/Drug',
                        'request_no' => 'PRD-' . str_pad($request->id, 6, '0', STR_PAD_LEFT),
                        'product_name' => optional($request->product)->product_name ?? 'Unknown Product',
                        'product_category' => optional(optional($request->product)->category)->name ?? 'Uncategorized',
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'dose' => $request->dose,
                        'patient_name' => userfullname($request->patient->user_id ?? 0),
                        'patient_file_no' => $request->patient->file_no ?? 'N/A',
                        'hmo_name' => optional($request->patient->hmo)->name ?? 'Private',
                        'price' => $posr ? ($posr->payable_amount + $posr->claims_amount) : $basePrice,
                        'hmo_covers' => $posr->claims_amount ?? 0,
                        'payable' => $posr->payable_amount ?? $basePrice,
                        'coverage_mode' => $posr->coverage_mode ?? null,
                        'billing_status' => $this->getProductBillingStatus($request->status, $posr),
                        'billing_status_code' => $this->getProductBillingStatusCode($request->status, $posr),
                        'delivery_status' => $this->getProductDeliveryStatus($request->status),
                        'delivery_status_code' => $this->getProductDeliveryStatusCode($request->status),
                        'requested_by' => $request->doctor ? ($request->doctor->surname . ' ' . $request->doctor->firstname) : 'Walk-in Request',
                        'requested_at' => $request->created_at->format('d M Y, H:i'),
                        'billed_by' => $request->biller ? ($request->biller->surname . ' ' . $request->biller->firstname) : null,
                        'billed_at' => $request->billed_date ? Carbon::parse($request->billed_date)->format('d M Y, H:i') : null,
                        'dispensed_by' => $request->dispenser ? ($request->dispenser->surname . ' ' . $request->dispenser->firstname) : null,
                        'dispense_date' => $request->dispense_date ? Carbon::parse($request->dispense_date)->format('d M Y, H:i') : null,
                        'encounter_id' => $request->encounter_id,
                        'payment_reference' => $posr && $posr->payment ? $posr->payment->payment_ref : null,
                        'payment_date' => $posr && $posr->payment ? Carbon::parse($posr->payment->created_at)->format('d M Y, H:i') : null,
                    ];
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Invalid request type'], 400);
            }

            return response()->json([
                'success' => true,
                'details' => $details,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching request details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }
    }

    /**
     * Discard a service request
     */
    public function discardServiceRequest(Request $request, $type, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        try {
            DB::beginTransaction();

            $currentUserId = Auth::id();

            switch ($type) {
                case 'lab':
                    $serviceRequest = LabServiceRequest::with('productOrServiceRequest')->findOrFail($id);
                    break;
                case 'imaging':
                    $serviceRequest = ImagingServiceRequest::with('productOrServiceRequest')->findOrFail($id);
                    break;
                case 'product':
                    $serviceRequest = ProductRequest::with('productOrServiceRequest')->findOrFail($id);
                    break;
                default:
                    return response()->json(['success' => false, 'message' => 'Invalid request type'], 400);
            }

            $posr = $serviceRequest->productOrServiceRequest;

            // Check if already paid
            if ($posr && $posr->payment_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot discard a paid request. Please process a refund instead.'
                ], 400);
            }

            // Check if user can discard (creator or admin)
            $canDiscard = ($posr && $posr->staff_user_id == $currentUserId) || Auth::user()->hasRole(['SUPERADMIN', 'ADMIN']);

            if (!$canDiscard) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only discard requests that you created.'
                ], 403);
            }

            // Soft delete the service request with reason
            $serviceRequest->deleted_by = $currentUserId;
            $serviceRequest->deletion_reason = $request->reason;
            $serviceRequest->save();
            $serviceRequest->delete();

            // Also delete the ProductOrServiceRequest if exists
            if ($posr) {
                $posr->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request discarded successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error discarding request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to discard request: ' . $e->getMessage()
            ], 500);
        }
    }
}
