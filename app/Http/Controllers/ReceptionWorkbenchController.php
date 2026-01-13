<?php

namespace App\Http\Controllers;

use App\Helpers\HmoHelper;
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
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class ReceptionWorkbenchController extends Controller
{
    /**
     * Display the reception workbench
     */
    public function index()
    {
        $clinics = Clinic::all();
        $hmos = Hmo::with('scheme')->orderBy('name')->get();
        
        return view('admin.reception.workbench', compact('clinics', 'hmos'));
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
                    'age' => $patient->dob ? Carbon::parse($patient->dob)->age : null,
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
        $patient = patient::with(['user', 'hmo', 'account'])
            ->findOrFail($patientId);

        // Get current queue entries for this patient
        $queueEntries = DoctorQueue::with(['clinic', 'doctor.user', 'request_entry.service'])
            ->where('patient_id', $patientId)
            ->whereIn('status', [1, 2, 3]) // Waiting, Vitals Pending, In Consultation
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($q) {
                return [
                    'id' => $q->id,
                    'clinic' => $q->clinic->name ?? 'N/A',
                    'doctor' => $q->doctor ? userfullname($q->doctor->user_id) : 'Any Available',
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

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'gender' => $patient->gender,
                'dob' => $patient->dob ? Carbon::parse($patient->dob)->format('M d, Y') : 'N/A',
                'age' => $patient->dob ? Carbon::parse($patient->dob)->age . ' yrs' : 'N/A',
                'blood_group' => $patient->blood_group ?? 'N/A',
                'genotype' => $patient->genotype ?? 'N/A',
                'nationality' => $patient->nationality ?? 'N/A',
                'ethnicity' => $patient->ethnicity ?? 'N/A',
                'address' => $patient->address ?? 'N/A',
                'disability' => $patient->disability == 1 ? 'Yes' : 'No',
                'next_of_kin_name' => $patient->next_of_kin_name ?? 'N/A',
                'next_of_kin_phone' => $patient->next_of_kin_phone ?? 'N/A',
                'next_of_kin_address' => $patient->next_of_kin_address ?? 'N/A',
                'hmo' => $patient->hmo->name ?? 'Private',
                'hmo_id' => $patient->hmo_id,
                'hmo_no' => $patient->hmo_no ?? 'N/A',
                'photo' => $patient->user->filename ? asset('storage/image/user/' . $patient->user->filename) : asset('assets/images/default-avatar.png'),
                'balance' => $patient->account->balance ?? 0,
                'allergies' => $patient->allergies ?? [],
                'medical_history' => $patient->medical_history ?? '',
                'misc' => $patient->misc ?? '',
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

        $query = DoctorQueue::with(['patient.user', 'patient.hmo', 'clinic', 'doctor.user', 'request_entry.service'])
            ->whereDate('created_at', $today)
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
                return $q->doctor ? userfullname($q->doctor->user_id) : 'Any';
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
                return $badges[$q->status] ?? '<span class="badge bg-secondary">Unknown</span>';
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
            ->where('visible', 1);

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
            'phone_no' => 'required',
            'hmo_id' => 'nullable|exists:hmos,id',
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
            $patient->gender = $request->gender;
            $patient->dob = $request->dob;
            $patient->phone_no = $request->phone_no;
            $patient->hmo_id = $request->hmo_id ?? 1; // Default to Private
            $patient->hmo_no = $request->hmo_no;
            $patient->address = $request->address;
            $patient->save();

            // Create patient account
            $account = new PatientAccount();
            $account->patient_id = $patient->id;
            $account->balance = 0;
            $account->save();

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
}
