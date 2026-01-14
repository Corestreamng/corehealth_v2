<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\ProductRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\Product;
use App\Models\User;
use App\Helpers\HmoHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use PDF;

/**
 * PharmacyWorkbenchController
 *
 * References:
 * - Plan Section: Phase 1.1 - Backend Setup
 * - Models: ProductRequest, ProductOrServiceRequest, Product, Patient
 * - Related Controllers: ProductRequestController (dispense/bill methods)
 */
class PharmacyWorkbenchController extends Controller
{
    /**
     * Display the pharmacy workbench main page
     */
    public function index()
    {
        return view('admin.pharmacy.workbench');
    }

    /**
     * DataTables endpoint for prescriptions pending billing (status=1)
     * Matches EncounterController::prescBillList() format
     */
    public function prescBillList($patientId)
    {
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', 1)
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('select', function ($item) {
                $price = optional(optional($item->product)->price)->current_sale_price ?? 0;
                return "<input type='checkbox' name='selectedPrescBillRows[]' onclick='checkPrescBillRow(this)' data-price='{$price}' value='{$item->id}' class='form-control'>";
            })
            ->editColumn('dose', function ($item) {
                $code = optional($item->product)->product_code ?? '';
                $name = optional($item->product)->product_name ?? 'Unknown';
                $str = "<span class='badge badge-success'>[{$code}] {$name}</span>";
                $str .= '<hr><b>Dose/Freq:</b> ' . ($item->dose ?? 'N/A');
                $str .= '<br><b>Qty:</b> ' . ($item->qty ?? 1);
                return $str;
            })
            ->editColumn('created_at', function ($item) {
                $str = '<small>';
                $str .= '<b>Requested By:</b> ' . ($item->doctor_id ? userfullname($item->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($item->created_at)) . ')' : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b>Last Updated:</b> ' . date('h:i a D M j, Y', strtotime($item->updated_at)) . '<br>';
                $str .= '</small>';
                return $str;
            })
            ->rawColumns(['select', 'dose', 'created_at'])
            ->make(true);
    }

    /**
     * DataTables endpoint for prescriptions pending dispense (status=2)
     * Matches EncounterController::prescDispenseList() format
     */
    public function prescDispenseList($patientId)
    {
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller'])
            ->where('status', 2)
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('select', function ($item) {
                $posr = $item->productOrServiceRequest;
                $isPaid = optional(optional($posr)->payment)->payment_status === 'paid';
                $isValidated = optional($posr)->validation_status === 'validated';
                $canDispense = $isPaid || $isValidated;

                $disabled = !$canDispense ? 'disabled' : '';
                $tooltip = !$canDispense ? 'title="Payment or HMO validation required"' : '';

                return "<input type='checkbox' name='selectedPrescDispenseRows[]' {$disabled} {$tooltip} value='{$item->id}' class='form-control'>";
            })
            ->editColumn('dose', function ($item) {
                $code = optional($item->product)->product_code ?? '';
                $name = optional($item->product)->product_name ?? 'Unknown';
                $posr = $item->productOrServiceRequest;

                $str = "<span class='badge badge-success'>[{$code}] {$name}</span>";

                // Show HMO/payment status
                if ($posr) {
                    $isPaid = optional($posr->payment)->payment_status === 'paid';
                    $isValidated = $posr->validation_status === 'validated';
                    $coverageMode = $posr->coverage_mode ?? 'none';

                    if ($coverageMode !== 'none' && $posr->claims_amount > 0) {
                        $validationBadge = $isValidated
                            ? "<span class='badge badge-success'>HMO Validated</span>"
                            : "<span class='badge badge-warning'>Awaiting HMO Validation</span>";
                        $str .= '<br>' . $validationBadge;
                    }

                    $paymentBadge = $isPaid
                        ? "<span class='badge badge-success'>Paid</span>"
                        : "<span class='badge badge-danger'>Unpaid</span>";
                    $str .= ' ' . $paymentBadge;

                    $str .= '<br><small>Pay: ₦' . number_format($posr->payable_amount ?? 0, 2) . '</small>';
                    if ($posr->claims_amount > 0) {
                        $str .= '<br><small>HMO Claim: ₦' . number_format($posr->claims_amount, 2) . '</small>';
                    }
                }

                $str .= '<hr><b>Dose/Freq:</b> ' . ($item->dose ?? 'N/A');
                $str .= '<br><b>Qty:</b> ' . ($item->qty ?? 1);
                return $str;
            })
            ->editColumn('created_at', function ($item) {
                $str = '<small>';
                $str .= '<b>Requested By:</b> ' . ($item->doctor_id ? userfullname($item->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($item->created_at)) . ')' : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b>Billed By:</b> ' . ($item->billed_by ? userfullname($item->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($item->billed_date)) . ')' : "<span class='badge badge-secondary'>Not billed</span>") . '<br>';
                $str .= '</small>';
                return $str;
            })
            ->rawColumns(['select', 'dose', 'created_at'])
            ->make(true);
    }

    /**
     * DataTables endpoint for dispensed prescriptions history (status=3)
     */
    public function prescHistoryList($patientId)
    {
        $items = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller', 'dispenser'])
            ->where('status', 3)
            ->where('patient_id', $patientId)
            ->orderBy('dispense_date', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->editColumn('dose', function ($item) {
                $code = optional($item->product)->product_code ?? '';
                $name = optional($item->product)->product_name ?? 'Unknown';
                $posr = $item->productOrServiceRequest;

                $str = "<span class='badge badge-success'>[{$code}] {$name}</span>";
                $str .= '<hr><b>Dose/Freq:</b> ' . ($item->dose ?? 'N/A');
                $str .= '<br><b>Qty:</b> ' . ($item->qty ?? 1);

                if ($posr) {
                    $str .= '<br><small>Amount: ₦' . number_format($posr->payable_amount ?? 0, 2) . '</small>';
                }
                return $str;
            })
            ->editColumn('created_at', function ($item) {
                $str = '<small>';
                $str .= '<b>Requested By:</b> ' . ($item->doctor_id ? userfullname($item->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($item->created_at)) . ')' : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b>Billed By:</b> ' . ($item->billed_by ? userfullname($item->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($item->billed_date)) . ')' : "N/A") . '<br>';
                $str .= '<b>Dispensed By:</b> ' . ($item->dispensed_by ? userfullname($item->dispensed_by) . ' (' . date('h:i a D M j, Y', strtotime($item->dispense_date)) . ')' : "N/A") . '<br>';
                $str .= '</small>';
                return $str;
            })
            ->rawColumns(['dose', 'created_at'])
            ->make(true);
    }

    /**
     * Search for patients (autocomplete)
     *
     * References:
     * - Plan Section: Tab 2 - Patient Medications (Context-Aware)
     */
    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $patients = Patient::with('user', 'hmo')
            ->where(function ($query) use ($term) {
                $query->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('surname', 'like', "%{$term}%")
                        ->orWhere('firstname', 'like', "%{$term}%")
                        ->orWhere('othername', 'like', "%{$term}%");
                })
                ->orWhere('file_no', 'like', "%{$term}%")
                ->orWhere('phone_no', 'like', "%{$term}%");
            })
            ->limit(10)
            ->get();

        $results = $patients->map(function ($patient) {
            // Count pending prescriptions (status 1=Requested, 2=Billed)
            $pendingCount = ProductRequest::where('patient_id', $patient->id)
                ->whereIn('status', [1, 2])
                ->count();

            return [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'age' => $patient->dob ? Carbon::parse($patient->dob)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'photo' => $patient->user->filename ?? null,
                'hmo' => optional($patient->hmo)->name ?? 'Self',
                'hmo_no' => $patient->hmo_no ?? '',
                'pending_count' => $pendingCount,
            ];
        });

        return response()->json($results);
    }

    /**
     * Get prescription queue with optional filters
     *
     * References:
     * - Plan Section: Tab 1 - Prescription Queue (Primary Workspace)
     * - ProductRequest status: 1=Requested, 2=Billed, 3=Dispensed, 0=Dismissed
     */
    public function getPrescriptionQueue(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $query = ProductRequest::query()
            ->whereIn('status', [1, 2]); // Requested or Billed only

        // Apply filters
        if ($filter === 'unbilled') {
            $query->where('status', 1);
        } elseif ($filter === 'billed') {
            $query->where('status', 2);
        } elseif ($filter === 'hmo') {
            // Filter for prescriptions with HMO coverage
            $query->whereHas('productOrServiceRequest', function($q) {
                $q->where('claims_amount', '>', 0);
            });
        }

        $results = $query
            ->select([
                'patient_id',
                DB::raw('COUNT(*) as prescription_count'),
                DB::raw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as unbilled_count'),
                DB::raw('SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as ready_count'),
                DB::raw('MAX(created_at) as last_created')
            ])
            ->groupBy('patient_id')
            ->orderByDesc('last_created')
            ->get();

        // Preload patient data
        $patients = Patient::with('user', 'hmo')
            ->whereIn('id', $results->pluck('patient_id'))
            ->get()
            ->keyBy('id');

        $queue = $results->map(function ($item) use ($patients) {
            $patient = $patients->get($item->patient_id);

            if (!$patient) {
                return null;
            }

            return [
                'patient_id' => $patient->id,
                'patient_name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'prescription_count' => $item->prescription_count,
                'unbilled_count' => $item->unbilled_count,
                'ready_count' => $item->ready_count,
                'hmo' => optional($patient->hmo)->name,
            ];
        })->filter();

        return response()->json($queue->values());
    }

    /**
     * Get queue counts for filters
     *
     * References:
     * - Plan Section: Tab 1 - Prescription Queue filters
     */
    public function getQueueCounts()
    {
        $totalCount = ProductRequest::whereIn('status', [1, 2])
            ->select('patient_id')
            ->distinct()
            ->count();

        $unbilledCount = ProductRequest::where('status', 1)
            ->select('patient_id')
            ->distinct()
            ->count();

        $readyCount = ProductRequest::where('status', 2)
            ->select('patient_id')
            ->distinct()
            ->count();

        $hmoCount = ProductRequest::whereIn('status', [1, 2])
            ->whereHas('productOrServiceRequest', function($q) {
                $q->where('claims_amount', '>', 0);
            })
            ->select('patient_id')
            ->distinct()
            ->count();

        return response()->json([
            'total' => $totalCount,
            'unbilled' => $unbilledCount,
            'ready' => $readyCount,
            'hmo' => $hmoCount,
        ]);
    }

    /**
     * Get patient's prescription data (pending prescriptions)
     *
     * References:
     * - Plan Section: Tab 2 - Patient Medications > Pending Prescriptions
     * - Models: ProductRequest with relationships (product, doctor, biller, productOrServiceRequest)
     */
    public function getPatientPrescriptionData($patientId, Request $request)
    {
        $patient = Patient::with('hmo', 'user')->findOrFail($patientId);
        $statusFilter = $request->input('status');

        // Build query for items
        $query = ProductRequest::with([
                'product.price',
                'product.category',
                'doctor',
                'biller',
                'productOrServiceRequest.payment'
            ])
            ->where('patient_id', $patientId)
            ->whereIn('status', [1, 2]); // Requested or Billed

        // Apply status filter if provided
        if ($statusFilter) {
            if ($statusFilter === 'unbilled') {
                $query->where('status', 1);
            } elseif ($statusFilter === 'billed') {
                // Billed but not yet paid/validated for HMO
                $query->where('status', 2)
                      ->where(function($q) {
                          $q->whereDoesntHave('productOrServiceRequest.payment')
                            ->orWhereHas('productOrServiceRequest', function($sq) {
                                $sq->whereNull('validation_status')
                                   ->orWhere('validation_status', '!=', 'validated');
                            });
                      });
            } elseif ($statusFilter === 'ready') {
                // Ready to dispense: billed AND (paid OR HMO validated)
                $query->where('status', 2)
                      ->where(function($q) {
                          $q->whereHas('productOrServiceRequest', function($sq) {
                                $sq->whereNotNull('payment_id');
                            })
                            ->orWhereHas('productOrServiceRequest', function($sq) {
                                $sq->where('validation_status', 'validated');
                            });
                      });
            }
        }

        $items = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($pr) {
                $basePrice = optional(optional($pr->product)->price)->current_sale_price ?? 0;
                $posr = $pr->productOrServiceRequest;
                $payment = optional($posr)->payment;

                // Determine ready status
                $isPaid = optional($payment)->payment_status === 'paid';
                $isValidated = optional($posr)->validation_status === 'validated';
                $isReady = $isPaid || $isValidated;

                // Status label logic
                $statusLabel = $pr->status == 1 ? 'Unbilled' : 'Billed';
                if ($pr->status == 2 && $isReady) {
                    $statusLabel = 'Ready to Dispense';
                }

                return [
                    'id' => $pr->id,
                    'product_request_id' => $pr->id,
                    'product_name' => optional($pr->product)->product_name,
                    'product_code' => optional($pr->product)->product_code,
                    'category' => optional(optional($pr->product)->category)->category_name,
                    'dose' => $pr->dose ?? 'N/A',
                    'qty' => $pr->qty ?? 1,
                    'status' => $pr->status,
                    'status_label' => $statusLabel,
                    'is_ready' => $isReady,
                    'is_paid' => $isPaid,
                    'is_validated' => $isValidated,
                    'price' => $posr ? $posr->payable_amount : $basePrice,
                    'base_price' => $basePrice,
                    'payable_amount' => optional($posr)->payable_amount ?? $basePrice,
                    'claims_amount' => optional($posr)->claims_amount ?? 0,
                    'coverage_mode' => optional($posr)->coverage_mode ?? 'none',
                    'validation_status' => optional($posr)->validation_status ?? null,
                    'doctor_name' => $pr->doctor ? userfullname($pr->doctor_id) : 'N/A',
                    'billed_by' => $pr->billed_by ? userfullname($pr->billed_by) : null,
                    'billed_date' => $pr->billed_date ? Carbon::parse($pr->billed_date)->format('Y-m-d H:i') : null,
                    'created_at' => $pr->created_at->format('Y-m-d H:i'),
                ];
            });

        // Get counts for subtabs
        $allPending = ProductRequest::where('patient_id', $patientId)->whereIn('status', [1, 2])->count();
        $unbilledCount = ProductRequest::where('patient_id', $patientId)->where('status', 1)->count();

        // Billed but not ready
        $billedNotReadyCount = ProductRequest::where('patient_id', $patientId)
            ->where('status', 2)
            ->where(function($q) {
                $q->whereDoesntHave('productOrServiceRequest.payment')
                  ->orWhereHas('productOrServiceRequest', function($sq) {
                      $sq->whereNull('validation_status')
                         ->orWhere('validation_status', '!=', 'validated');
                  });
            })
            ->count();

        // Ready to dispense count
        $readyCount = ProductRequest::where('patient_id', $patientId)
            ->where('status', 2)
            ->where(function($q) {
                $q->whereHas('productOrServiceRequest', function($sq) {
                      $sq->whereNotNull('payment_id');
                  })
                  ->orWhereHas('productOrServiceRequest', function($sq) {
                      $sq->where('validation_status', 'validated');
                  });
            })
            ->count();

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'age' => $patient->dob ? Carbon::parse($patient->dob)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'hmo_name' => optional($patient->hmo)->name,
                'hmo_no' => $patient->hmo_no,
                'photo' => $patient->user->filename ?? null,
            ],
            'items' => $items,
            'counts' => [
                'all' => $allPending,
                'unbilled' => $unbilledCount,
                'billed' => $billedNotReadyCount,
                'ready' => $readyCount,
            ],
        ]);
    }

    /**
     * Get patient's dispensing history
     *
     * References:
     * - Plan Section: Tab 3 - Dispensing History
     */
    public function getPatientDispensingHistory($patientId, Request $request)
    {
        $patient = Patient::findOrFail($patientId);

        $query = ProductRequest::with(['product', 'doctor', 'dispenser', 'productOrServiceRequest.payment'])
            ->where('patient_id', $patientId)
            ->where('status', 3); // Dispensed only

        // Apply date filters
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('dispense_date', '>=', $request->from_date);
        }
        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('dispense_date', '<=', $request->to_date);
        }

        $history = $query->orderBy('dispense_date', 'desc')->get();

        $items = $history->map(function ($pr) {
            $posr = $pr->productOrServiceRequest;
            $basePrice = optional(optional($pr->product)->price)->current_sale_price ?? 0;
            return [
                'product_request_id' => $pr->id,
                'product_name' => optional($pr->product)->product_name,
                'product_code' => optional($pr->product)->product_code,
                'dose' => $pr->dose ?? 'N/A',
                'qty' => $pr->qty ?? 1,
                'dispensed_by' => $pr->dispensed_by ? userfullname($pr->dispensed_by) : 'N/A',
                'dispense_date' => $pr->dispense_date ? Carbon::parse($pr->dispense_date)->format('Y-m-d H:i') : null,
                'doctor_name' => $pr->doctor ? userfullname($pr->doctor_id) : 'N/A',
                'payment_type' => optional(optional($posr)->payment)->payment_type ?? 'N/A',
                'base_price' => $basePrice,
                'payable_amount' => $posr->payable_amount ?? $basePrice,
                'claims_amount' => $posr->claims_amount ?? 0,
            ];
        });

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
            ],
            'items' => $items,
        ]);
    }

    /**
     * Dispense medication (single or bulk)
     *
     * References:
     * - Plan Section: Phase 3.2 - Dispense Medication
     * - Related: ProductRequestController::dispense()
     * - Uses: HmoHelper::canDeliverService()
     */
    public function dispenseMedication(Request $request)
    {
        $request->validate([
            'product_request_ids' => 'required|array',
            'product_request_ids.*' => 'exists:product_requests,id'
        ]);

        try {
            DB::beginTransaction();

            $dispensedCount = 0;
            $errors = [];

            foreach ($request->product_request_ids as $prId) {
                $productRequest = ProductRequest::with('productOrServiceRequest')->find($prId);

                // Validate status
                if ($productRequest->status != 2) {
                    $errors[] = "PR#{$prId}: Not billed";
                    continue;
                }

                // Check HMO delivery requirements
                if ($productRequest->productOrServiceRequest) {
                    $deliveryCheck = HmoHelper::canDeliverService($productRequest->productOrServiceRequest);
                    if (!$deliveryCheck['can_deliver']) {
                        $errors[] = "PR#{$prId}: {$deliveryCheck['reason']}";
                        continue;
                    }
                }

                // Dispense
                $productRequest->update([
                    'status' => 3,
                    'dispensed_by' => Auth::id(),
                    'dispense_date' => now()
                ]);

                $dispensedCount++;
            }

            DB::commit();

            $message = "Successfully dispensed {$dispensedCount} medication(s)";
            if (count($errors) > 0) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'dispensed_count' => $dispensedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pharmacy dispense error: ' . $e->getMessage());
            return response()->json(['message' => 'Dispense error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get my dispensing transactions (current pharmacist)
     *
     * References:
     * - Plan Section: Tab 6 - Reports > Daily Dispensing Summary
     */
    public function getMyTransactions(Request $request)
    {
        $query = ProductRequest::with(['product', 'patient.user', 'productOrServiceRequest.payment'])
            ->where('dispensed_by', Auth::id())
            ->where('status', 3);

        // Apply date filters
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('dispense_date', '>=', $request->from_date);
        }
        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('dispense_date', '<=', $request->to_date);
        }

        $transactions = $query->orderBy('dispense_date', 'desc')->get();

        $items = $transactions->map(function ($pr) {
            $posr = $pr->productOrServiceRequest;
            return [
                'product_request_id' => $pr->id,
                'patient_name' => userfullname($pr->patient->user_id),
                'file_no' => $pr->patient->file_no,
                'product_name' => optional($pr->product)->product_name,
                'dose' => $pr->dose ?? 'N/A',
                'dispense_date' => $pr->dispense_date ? Carbon::parse($pr->dispense_date)->format('Y-m-d H:i') : null,
                'payment_type' => optional($posr->payment)->payment_type ?? 'N/A',
                'amount' => $posr ? $posr->payable_amount : 0,
            ];
        });

        $totalAmount = $items->sum('amount');
        $cashCount = $transactions->filter(function($pr) {
            return optional($pr->productOrServiceRequest)->coverage_mode == 'none';
        })->count();

        $stats = [
            'count' => $items->count(),
            'total' => $totalAmount,
            'cash_count' => $cashCount,
            'hmo_count' => $items->count() - $cashCount,
        ];

        return response()->json([
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    /**
     * Print prescription slip for selected prescriptions
     *
     * References:
     * - Plan Section: Phase 5 - Prescription Slip Print Template
     * - Includes: Hospital branding, doctor, pharmacist, patient details
     */
    public function printPrescriptionSlip(Request $request)
    {
        $request->validate([
            'product_request_ids' => 'required|array',
            'product_request_ids.*' => 'exists:product_requests,id'
        ]);

        $prescriptions = ProductRequest::with([
            'product',
            'patient.user',
            'patient.hmo',
            'doctor',
            'dispenser',
            'encounter'
        ])
        ->whereIn('id', $request->product_request_ids)
        ->orderBy('created_at', 'asc')
        ->get();

        if ($prescriptions->isEmpty()) {
            return response()->json(['message' => 'No prescriptions found'], 404);
        }

        $patient = $prescriptions->first()->patient;
        $appsettings = appsettings();

        // Determine pharmacist name
        $pharmacist = Auth::user();
        $pharmacistName = userfullname(Auth::id());

        $data = [
            'prescriptions' => $prescriptions,
            'patient' => $patient,
            'appsettings' => $appsettings,
            'pharmacist' => $pharmacistName,
            'print_date' => Carbon::now()->format('d M Y H:i'),
        ];

        return view('admin.pharmacy.prescription_slip', $data);
    }

    /**
     * Search for products/medications
     *
     * References:
     * - Plan Section: New Request Tab - Product Search
     */
    public function searchProducts(Request $request)
    {
        $term = $request->input('term', '');
        $patientId = $request->input('patient_id', null);

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        // Get the patient's HMO if patient_id is provided
        $patient = null;
        if ($patientId) {
            $patient = Patient::find($patientId);
        }

        $products = Product::with(['category', 'price', 'stock'])
            ->where(function($q) use ($term) {
                $q->where('product_name', 'like', "%{$term}%")
                  ->orWhere('product_code', 'like', "%{$term}%");
            })
            ->where('status', 1) // Active products only
            ->orderBy('product_name')
            ->limit(20)
            ->get()
            ->map(function($product) use ($patient) {
                $basePrice = optional($product->price)->current_sale_price ?? 0;
                $stockQty = optional($product->stock)->current_quantity ?? 0;

                // Get HMO coverage info if patient has HMO
                $payableAmount = $basePrice;
                $claimsAmount = 0;
                $coverageMode = null;

                if ($patient && $patient->hmo_id) {
                    try {
                        $tariffInfo = HmoHelper::applyHmoTariff($patient->id, $product->id);
                        if ($tariffInfo) {
                            $payableAmount = $tariffInfo['payable_amount'] ?? $basePrice;
                            $claimsAmount = $tariffInfo['claims_amount'] ?? 0;
                            $coverageMode = $tariffInfo['coverage_mode'] ?? null;
                        }
                    } catch (\Exception $e) {
                        // No tariff found, use base price
                    }
                }

                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                    'category_name' => optional($product->category)->category_name,
                    'price' => $basePrice,
                    'stock_qty' => $stockQty,
                    'payable_amount' => $payableAmount,
                    'claims_amount' => $claimsAmount,
                    'coverage_mode' => $coverageMode,
                ];
            });

        return response()->json($products);
    }

    /**
     * Create a new prescription request from pharmacy
     *
     * This creates ONLY ProductRequest with status=1 (unbilled).
     * ProductOrServiceRequest is NOT created here - it's created when billing.
     * This matches the flow in EncounterController::savePrescriptions()
     *
     * References:
     * - Plan Section: New Request Tab - Create Prescription Request
     * - Models: ProductRequest only (ProductOrServiceRequest created on billing)
     */
    public function createPrescriptionRequest(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.qty' => 'required|integer|min:1',
            'products.*.dose' => 'nullable|string',
        ]);

        $patient = Patient::findOrFail($request->patient_id);

        DB::beginTransaction();
        try {
            $createdRequests = [];

            foreach ($request->products as $productData) {
                $product = Product::with('price')->findOrFail($productData['product_id']);

                // Create ProductRequest ONLY (status=1 means unbilled/requested)
                // This matches EncounterController::savePrescriptions() behavior
                $productRequest = ProductRequest::create([
                    'patient_id' => $patient->id,
                    'product_id' => $product->id,
                    'encounter_id' => $patient->current_encounter_id ?? null,
                    'qty' => $productData['qty'] ?? 1,
                    'dose' => $productData['dose'] ?? null,
                    'doctor_id' => Auth::id(), // Requester
                    'status' => 1, // 1 = Unbilled/Requested - ALWAYS start here
                    'created_at' => now(),
                ]);

                // NOTE: ProductOrServiceRequest is NOT created here.
                // It will be created when the prescription is BILLED via billPrescriptions()
                // This ensures items don't appear in billing waitlist until actually billed.

                $createdRequests[] = $productRequest;
            }

            DB::commit();

            Log::info('Prescription request created from pharmacy workbench', [
                'patient_id' => $patient->id,
                'user_id' => Auth::id(),
                'product_count' => count($createdRequests),
            ]);

            return response()->json([
                'success' => true,
                'message' => count($createdRequests) . ' medication(s) requested successfully. Items are now in the billing queue.',
                'requests' => $createdRequests,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create prescription request', [
                'error' => $e->getMessage(),
                'patient_id' => $patient->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create prescription request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bill prescriptions - creates ProductOrServiceRequest and updates status to 2
     *
     * This matches the flow in ProductRequestController::bill()
     * - For existing ProductRequests: Creates ProductOrServiceRequest and updates status to 2
     * - For added products: Creates both ProductRequest and ProductOrServiceRequest
     * - Decrements stock
     */
    public function billPrescriptions(Request $request)
    {
        $request->validate([
            'prescription_ids' => 'nullable|array',
            'prescription_ids.*' => 'exists:product_requests,id',
            'addedPrescBillRows' => 'nullable|array',
            'addedPrescBillRows.*' => 'exists:products,id',
            'consult_presc_dose' => 'nullable|array',
            'patient_user_id' => 'required|exists:users,id',
            'patient_id' => 'required|exists:patients,id'
        ]);

        try {
            DB::beginTransaction();

            $billedCount = 0;
            $errors = [];
            $patient = Patient::findOrFail($request->patient_id);

            // Process existing ProductRequests (from DataTable checkboxes)
            if ($request->has('prescription_ids') && is_array($request->prescription_ids)) {
                foreach ($request->prescription_ids as $prId) {
                    $productRequest = ProductRequest::with('product')->find($prId);

                    if (!$productRequest) {
                        $errors[] = "PR#{$prId}: Not found";
                        continue;
                    }

                    // Only unbilled items can be billed
                    if ($productRequest->status != 1) {
                        $errors[] = "PR#{$prId}: Already billed or dispensed";
                        continue;
                    }

                    $prodId = $productRequest->product_id;

                    // Create ProductOrServiceRequest (billing record)
                    $billReq = new ProductOrServiceRequest();
                    $billReq->user_id = $request->patient_user_id;
                    $billReq->staff_user_id = Auth::id();
                    $billReq->product_id = $prodId;

                    // Apply HMO tariff if patient has HMO
                    $this->applyHmoTariffToRequest($billReq, $patient, $prodId, $productRequest->product);

                    $billReq->save();

                    // Update ProductRequest to billed status
                    $productRequest->update([
                        'status' => 2,
                        'billed_by' => Auth::id(),
                        'billed_date' => now(),
                        'product_request_id' => $billReq->id
                    ]);

                    // Decrement stock
                    $product = Product::with('stock')->find($prodId);
                    if ($product && $product->stock) {
                        $qty = $productRequest->qty ?? 1;
                        $product->stock->decrement('current_quantity', $qty);
                        $product->stock->save();
                    }

                    $billedCount++;
                }
            }

            // Process newly added products (from search & add)
            if ($request->has('addedPrescBillRows') && is_array($request->addedPrescBillRows)) {
                $doses = $request->consult_presc_dose ?? [];

                for ($i = 0; $i < count($request->addedPrescBillRows); $i++) {
                    $productId = $request->addedPrescBillRows[$i];
                    $dose = $doses[$i] ?? '';

                    $product = Product::with(['price', 'stock'])->find($productId);
                    if (!$product) {
                        $errors[] = "Product #{$productId}: Not found";
                        continue;
                    }

                    // Create ProductOrServiceRequest first
                    $billReq = new ProductOrServiceRequest();
                    $billReq->user_id = $request->patient_user_id;
                    $billReq->staff_user_id = Auth::id();
                    $billReq->product_id = $productId;

                    // Apply HMO tariff
                    $this->applyHmoTariffToRequest($billReq, $patient, $productId, $product);

                    $billReq->save();

                    // Create ProductRequest with status=2 (billed)
                    $productRequest = new ProductRequest();
                    $productRequest->product_id = $productId;
                    $productRequest->dose = $dose;
                    $productRequest->billed_by = Auth::id();
                    $productRequest->billed_date = now();
                    $productRequest->patient_id = $patient->id;
                    $productRequest->doctor_id = Auth::id();
                    $productRequest->product_request_id = $billReq->id;
                    $productRequest->status = 2; // Billed
                    $productRequest->save();

                    // Decrement stock
                    if ($product->stock) {
                        $product->stock->decrement('current_quantity', 1);
                        $product->stock->save();
                    }

                    $billedCount++;
                }
            }

            DB::commit();

            $message = "Successfully billed {$billedCount} prescription(s)";
            if (count($errors) > 0) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'billed_count' => $billedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bill prescriptions error: ' . $e->getMessage() . ' at line ' . $e->getLine());
            return response()->json(['message' => 'Failed to bill prescriptions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to apply HMO tariff to a ProductOrServiceRequest
     */
    private function applyHmoTariffToRequest(ProductOrServiceRequest $billReq, Patient $patient, $productId, $product = null)
    {
        try {
            if ($patient->hmo_id) {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, $productId, null);
                if ($hmoData) {
                    $billReq->payable_amount = $hmoData['payable_amount'];
                    $billReq->claims_amount = $hmoData['claims_amount'];
                    $billReq->coverage_mode = $hmoData['coverage_mode'];
                    $billReq->validation_status = $hmoData['validation_status'] ?? 'pending';
                    return;
                }
            }
        } catch (\Exception $e) {
            Log::warning('HMO tariff lookup failed', [
                'patient_id' => $patient->id,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback: No HMO or tariff lookup failed - use product price
        if (!$product) {
            $product = Product::with('price')->find($productId);
        }
        $price = optional(optional($product)->price)->current_sale_price ?? 0;
        $billReq->payable_amount = $price;
        $billReq->claims_amount = 0;
        $billReq->coverage_mode = 'none';
    }

    /**
     * Record billing for prescriptions (mark as billed - status 1 to 2)
     * @deprecated Use billPrescriptions() instead
     */
    public function recordBilling(Request $request)
    {
        // Redirect to the new billPrescriptions method
        return $this->billPrescriptions($request);
    }

    /**
     * Dismiss prescriptions (soft delete or cancel)
     */
    public function dismissPrescriptions(Request $request)
    {
        $request->validate([
            'prescription_ids' => 'required|array',
            'prescription_ids.*' => 'exists:product_requests,id'
        ]);

        try {
            DB::beginTransaction();

            $dismissedCount = 0;
            $errors = [];

            foreach ($request->prescription_ids as $prId) {
                $productRequest = ProductRequest::find($prId);

                // Can't dismiss already dispensed items
                if ($productRequest->status == 3) {
                    $errors[] = "PR#{$prId}: Already dispensed - cannot dismiss";
                    continue;
                }

                // Soft delete or mark as cancelled
                $productRequest->delete(); // Soft delete if using SoftDeletes trait

                $dismissedCount++;
            }

            DB::commit();

            $message = "Successfully dismissed {$dismissedCount} prescription(s)";
            if (count($errors) > 0) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'dismissed_count' => $dismissedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Dismiss prescriptions error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to dismiss prescriptions: ' . $e->getMessage()], 500);
        }
    }
}
