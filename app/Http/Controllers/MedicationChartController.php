<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductOrServiceRequest;
use App\Models\patient;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\ProductStock;
use App\Models\StockBatch;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use App\Models\MedicationAdministration;
use App\Models\MedicationSchedule;
use App\Models\MedicationHistory;
use App\Models\ProductRequest;
use App\Models\Product;
use App\Helpers\HmoHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MedicationChartController extends Controller
{
    public function getPatientPrescribedDrugs($patientId)
    {
        $prescriptions = ProductRequest::with([
            'product:id,product_name,product_code',
            'productOrServiceRequest:id,payment_id,payable_amount,claims_amount,coverage_mode,validation_status',
            'productOrServiceRequest.payment:id',
            'doctor:id,surname,firstname,othername',
            'dispensedFromStore:id,store_name'
        ])
        ->where('patient_id', $patientId)
        ->whereIn('status', [1, 2, 3]) // exclude dismissed
        ->orderByDesc('created_at')
        ->get()
        ->map(function ($rx) {
            // Count how many times this prescription's POSR has been administered
            $adminCount = MedicationAdministration::where('product_or_service_request_id', $rx->product_request_id)
                ->whereNull('deleted_at')
                ->count();

            // Sum total qty administered (for remaining_doses calculation)
            $qtyAdministered = (float) MedicationAdministration::where('product_or_service_request_id', $rx->product_request_id)
                ->whereNull('deleted_at')
                ->sum('qty');

            // Count how many schedules exist for this prescription
            $scheduleCount = MedicationSchedule::where('product_or_service_request_id', $rx->product_request_id)
                ->count();

            return [
                'id' => $rx->id,
                'posr_id' => $rx->product_request_id, // §6.1: POSR ID for calendar + schedule linking
                'product_request_id' => $rx->id,       // §6.1: ProductRequest ID for administer flow
                'product_id' => $rx->product_id,
                'product_name' => $rx->product->product_name ?? 'Unknown',
                'product_code' => $rx->product->product_code ?? '',
                'qty_prescribed' => $rx->qty,
                'dose' => $rx->dose,
                'doctor_name' => $rx->doctor
                    ? trim(($rx->doctor->surname ?? '') . ' ' . ($rx->doctor->firstname ?? ''))
                    : '',
                'prescribed_at' => $rx->created_at,

                // Status pipeline
                'status' => $rx->status,   // 1=requested, 2=billed, 3=dispensed
                'status_label' => match($rx->status) {
                    1 => 'Awaiting Billing',
                    2 => optional($rx->productOrServiceRequest)->payment_id
                        ? 'Awaiting Pharmacy'
                        : (optional($rx->productOrServiceRequest)->validation_status === 'validated'
                            ? 'Awaiting Pharmacy (HMO Validated)'
                            : 'Awaiting Payment'),
                    3 => 'Dispensed',
                    default => 'Unknown',
                },
                'status_color' => match($rx->status) {
                    1 => 'danger',         // red - not yet billed
                    2 => optional($rx->productOrServiceRequest)->payment_id ? 'warning' : 'secondary',
                    3 => 'success',        // green - dispensed
                    default => 'light',
                },

                // Financial
                'is_paid' => optional($rx->productOrServiceRequest)->payment_id !== null,
                'is_hmo_validated' => optional($rx->productOrServiceRequest)->validation_status === 'validated',
                'payable_amount' => optional($rx->productOrServiceRequest)->payable_amount,
                'claims_amount' => optional($rx->productOrServiceRequest)->claims_amount,
                'coverage_mode' => optional($rx->productOrServiceRequest)->coverage_mode,

                // Dispensing
                'is_dispensed' => $rx->status === 3,
                'dispensed_from_store' => $rx->dispensedFromStore->store_name ?? null,
                'dispense_date' => $rx->dispense_date,

                // Administration tracking
                'times_administered' => $adminCount,
                'times_scheduled' => $scheduleCount,
                'qty_administered' => $qtyAdministered,
                'remaining_doses' => max(0, $rx->qty - $qtyAdministered),
                'is_fully_administered' => $qtyAdministered >= $rx->qty,

                // Can chart from this?
                'can_chart' => $rx->status === 3, // Only dispensed drugs can be charted
            ];
        });

        // §6.1: Also fetch direct administration entries (ward stock + patient's own)
        // These have schedule_id = null and drug_source != 'pharmacy_dispensed'
        $directAdmins = MedicationAdministration::where('patient_id', $patientId)
            ->whereNull('schedule_id')
            ->whereIn('drug_source', ['ward_stock', 'patient_own'])
            ->whereNull('deleted_at')
            ->with(['administeredBy', 'product:id,product_name,product_code'])
            ->orderByDesc('administered_at')
            ->get();

        $directEntries = $directAdmins
            ->groupBy(function ($admin) {
                // Group patient_own by drug name, ward_stock by product_id
                if ($admin->drug_source === 'patient_own') {
                    return 'po_' . strtolower($admin->external_drug_name ?? 'unknown');
                }
                return 'ws_' . ($admin->product_id ?? $admin->id);
            })
            ->map(function ($group) {
                $first = $group->first();
                $isPatientOwn = $first->drug_source === 'patient_own';

                // Get product name — eager-loaded via product relationship (no N+1)
                $productName = $isPatientOwn
                    ? ($first->external_drug_name ?? 'Unknown Drug')
                    : (optional($first->product)->product_name ?? 'Unknown Product');
                $productCode = $isPatientOwn ? '' : (optional($first->product)->product_code ?? '');

                $nurseName = $first->administeredBy
                    ? trim(($first->administeredBy->surname ?? '') . ' ' . ($first->administeredBy->firstname ?? ''))
                    : (userfullname($first->administered_by) ?? 'Unknown');

                return [
                    'id' => 'direct_' . $first->id,
                    'drug_source' => $first->drug_source,
                    'product_id' => $first->product_id,
                    'product_name' => $productName,
                    'product_code' => $productCode,
                    'times_administered' => $group->count(),
                    'times_scheduled' => MedicationSchedule::where('patient_id', $first->patient_id)
                        ->where('drug_source', $first->drug_source)
                        ->when($first->drug_source === 'ward_stock', fn($q) => $q->where('product_id', $first->product_id))
                        ->when($first->drug_source === 'patient_own', fn($q) => $q->where('external_drug_name', $first->external_drug_name))
                        ->count(),
                    'last_administered_at' => $group->max('administered_at'),
                    'nurse_name' => $nurseName,
                    'store_id' => $first->store_id,
                    'external_drug_name' => $first->external_drug_name,
                    'external_source_note' => $first->external_source_note,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'prescriptions' => $prescriptions,
            'direct_entries' => $directEntries,
            'summary' => [
                'total' => $prescriptions->count(),
                'dispensed' => $prescriptions->where('is_dispensed', true)->count(),
                'awaiting_payment' => $prescriptions->where('status', 2)->where('is_paid', false)->count(),
                'awaiting_pharmacy' => $prescriptions->where('status', 2)->where('is_paid', true)->count(),
                'awaiting_billing' => $prescriptions->where('status', 1)->count(),
            ],
        ]);
    }

    public function nurseDismissPrescription($patientId, Request $request)
    {
        $validated = $request->validate([
            'product_request_id' => 'required|exists:product_requests,id',
            'reason' => 'required|string|max:500',
        ]);

        $rx = ProductRequest::findOrFail($validated['product_request_id']);

        // Verify this prescription belongs to the specified patient
        if ((int) $rx->patient_id !== (int) $patientId) {
            return response()->json(['error' => 'Prescription does not belong to this patient'], 403);
        }

        // Can only dismiss if NOT dispensed
        if ($rx->status === 3) {
            return response()->json(['error' => 'Cannot dismiss an already dispensed prescription'], 422);
        }

        $rx->update([
            'status' => 0,
            'deleted_by' => Auth::id(),
            'deletion_reason' => 'Nurse dismissed: ' . $validated['reason'],
        ]);

        return response()->json(['success' => true, 'message' => 'Prescription dismissed']);
    }

    // Remove a schedule entry if no administration has been done
    public function removeSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|exists:medication_schedules,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $scheduleId = $request->input('schedule_id');
        $schedule = MedicationSchedule::findOrFail($scheduleId);
        // Check for any administration for this schedule
        $adminCount = MedicationAdministration::where('schedule_id', $scheduleId)->count();
        if ($adminCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove schedule: administration already exists.'
            ], 422);
        }
        $schedule->delete();
        return response()->json([
            'success' => true,
            'message' => 'Schedule removed successfully.'
        ]);
    }
    /**
     * Overview endpoint: returns all medications with their schedules/administrations for a date range.
     * Powers the Overview and Prescriptions sub-tabs in the medication chart.
     */
    public function overview($patientId, Request $request)
    {
        $patient = patient::findOrFail($patientId);

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : Carbon::now()->startOfWeek()->startOfDay();

        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : Carbon::parse($startDate)->addDays(6)->endOfDay();

        // 1. All schedules for this patient in the range (across all medications)
        $schedules = MedicationSchedule::where('patient_id', $patientId)
            ->whereBetween('scheduled_time', [$startDate, $endDate])
            ->orderBy('scheduled_time')
            ->get();

        // 2. All administrations for this patient in the range
        $administrations = MedicationAdministration::where('patient_id', $patientId)
            ->whereBetween('administered_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->with(['administeredBy', 'product:id,product_name,product_code', 'store:id,store_name'])
            ->orderBy('administered_at')
            ->get();

        // Add formatted names
        $administrations->each(function ($admin) {
            $admin->administered_by_name = userfullname($admin->administered_by);
            $admin->store_name = optional($admin->store)->store_name;
            $admin->product_name = optional($admin->product)->product_name;
        });

        // 3. Map schedule IDs that have been administered
        $administeredScheduleIds = $administrations->whereNotNull('schedule_id')->pluck('schedule_id')->unique()->toArray();

        // 4. Resolve product names for all schedules
        $productIds = $schedules->pluck('product_id')->merge($administrations->pluck('product_id'))->filter()->unique();
        $products = Product::whereIn('id', $productIds)->pluck('product_name', 'id');

        // 5. Also resolve POSR → product name for pharmacy schedules
        $posrIds = $schedules->pluck('product_or_service_request_id')->filter()->unique();
        $posrProducts = [];
        if ($posrIds->isNotEmpty()) {
            $posrProducts = ProductOrServiceRequest::with('product:id,product_name')
                ->whereIn('id', $posrIds)
                ->get()
                ->mapWithKeys(function ($posr) {
                    return [$posr->id => optional($posr->product)->product_name ?? 'Unknown'];
                })
                ->toArray();
        }

        // 6. Build enriched schedule data
        $enrichedSchedules = $schedules->map(function ($s) use ($administeredScheduleIds, $products, $posrProducts) {
            $drugName = $s->external_drug_name;
            if (!$drugName && $s->product_id && isset($products[$s->product_id])) {
                $drugName = $products[$s->product_id];
            }
            if (!$drugName && $s->product_or_service_request_id && isset($posrProducts[$s->product_or_service_request_id])) {
                $drugName = $posrProducts[$s->product_or_service_request_id];
            }
            $drugName = $drugName ?: 'Unknown';

            return [
                'id' => $s->id,
                'scheduled_time' => $s->scheduled_time,
                'dose' => $s->dose,
                'route' => $s->route,
                'drug_source' => $s->drug_source ?? 'pharmacy_dispensed',
                'drug_name' => $drugName,
                'product_id' => $s->product_id,
                'product_or_service_request_id' => $s->product_or_service_request_id,
                'is_administered' => in_array($s->id, $administeredScheduleIds),
            ];
        });

        // 7. Build enriched administrations (unscheduled ones only — scheduled ones are tracked via is_administered)
        $unscheduledAdmins = $administrations->whereNull('schedule_id')->map(function ($a) use ($products) {
            $drugName = $a->external_drug_name;
            if (!$drugName && $a->product_id && isset($products[$a->product_id])) {
                $drugName = $products[$a->product_id];
            }
            $drugName = $drugName ?: ($a->product_name ?? 'Unknown');

            return [
                'id' => $a->id,
                'administered_at' => $a->administered_at,
                'dose' => $a->dose,
                'qty' => $a->qty,
                'route' => $a->route,
                'drug_source' => $a->drug_source ?? 'pharmacy_dispensed',
                'drug_name' => $drugName,
                'administered_by_name' => $a->administered_by_name,
                'store_name' => $a->store_name,
            ];
        })->values();

        // 8. Summary stats
        $totalScheduled = $schedules->count();
        $totalAdministered = $administrations->count();
        $totalGiven = count($administeredScheduleIds) + $unscheduledAdmins->count();
        $missedSchedules = $enrichedSchedules->filter(function ($s) {
            return !$s['is_administered'] && Carbon::parse($s['scheduled_time'])->isPast();
        })->count();
        $pendingSchedules = $enrichedSchedules->filter(function ($s) {
            return !$s['is_administered'] && Carbon::parse($s['scheduled_time'])->isFuture();
        })->count();

        return response()->json([
            'success' => true,
            'schedules' => $enrichedSchedules->values(),
            'unscheduled_admins' => $unscheduledAdmins,
            'stats' => [
                'total_medications' => $posrIds->count() + $schedules->where('drug_source', '!=', 'pharmacy_dispensed')->pluck('product_id')->merge(
                    $schedules->where('drug_source', 'patient_own')->pluck('external_drug_name')
                )->unique()->count(),
                'total_given' => $totalGiven,
                'total_scheduled' => $totalScheduled,
                'total_missed' => $missedSchedules,
                'total_pending' => $pendingSchedules,
            ],
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    public function index($patientId, Request $request)
    {
        $patient = patient::findOrFail($patientId);
        $userId = $patient->user_id ?? $patient->user->id ?? null;

        // Get date range from request or use defaults (30 days with today in middle)
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if ($startDate) {
            $startDate = Carbon::parse($startDate)->startOfDay();
        } else {
            $startDate = Carbon::now()->subDays(15)->startOfDay();
        }

        if ($endDate) {
            $endDate = Carbon::parse($endDate)->endOfDay();
        } else {
            $endDate = Carbon::now()->addDays(15)->endOfDay();
        }

        // Get all prescriptions
        $prescriptions = ProductOrServiceRequest::where('user_id', $userId)
            ->with(['product.category', 'product.price', 'product.stock'])
            ->whereNotNull('product_id')
            ->latest('created_at')
            ->get();

        // Load schedules for each prescription within the date range
        $prescriptionIds = $prescriptions->pluck('id')->toArray();
        $allSchedules = MedicationSchedule::whereIn('product_or_service_request_id', $prescriptionIds)
            ->where('patient_id', $patientId)
            ->whereBetween('scheduled_time', [$startDate, $endDate])
            ->orderBy('scheduled_time')
            ->get()
            ->groupBy('product_or_service_request_id');

        // Attach schedules to each prescription
        $prescriptions->each(function ($prescription) use ($allSchedules) {
            $prescription->schedules = $allSchedules->get($prescription->id, collect())->values();
        });

        // Get all administrations within the date range
        $administrations = MedicationAdministration::where('patient_id', $patientId)
            ->whereBetween('administered_at', [$startDate, $endDate])
            ->with(['administeredBy', 'editedBy', 'deletedBy', 'store:id,store_name', 'product:id,product_name,product_code'])
            ->get();

        // Add store_name and product_name as flat attributes for frontend consumption
        $administrations->each(function ($admin) {
            $admin->store_name = optional($admin->store)->store_name;
            // For ward_stock/patient_own entries — surface product_name & external fields
            $admin->product_name = optional($admin->product)->product_name;
        });

        // Format user information using the userfullname helper
        $administrations->each(function ($admin) {
            // Add name properties for direct access
            $admin->administered_by_name = userfullname($admin->administered_by);
            $admin->edited_by_name = $admin->edited_by ? userfullname($admin->edited_by) : null;
            $admin->deleted_by_name = $admin->deleted_by ? userfullname($admin->deleted_by) : null;

            // Also update name property in the related objects if they exist
            if ($admin->administeredBy) {
                $admin->administeredBy->name = userfullname($admin->administered_by);
            }

            if ($admin->editedBy && $admin->edited_by) {
                $admin->editedBy->name = userfullname($admin->edited_by);
            }

            if ($admin->deletedBy && $admin->deleted_by) {
                $admin->deletedBy->name = userfullname($admin->deleted_by);
            }
        });

        // Return data with schedules included
        return response()->json([
            'prescriptions' => $prescriptions,
            'administrations' => $administrations
        ]);
    }

    public function getSchedule($patientId, $scheduleId)
    {
        $schedule = MedicationSchedule::with(['medication.product'])
            ->where('id', $scheduleId)
            ->where('patient_id', $patientId)
            ->firstOrFail();

        return response()->json(['schedule' => $schedule]);
    }

    public function calendar($patientId, $medicationId, $startDate = null, Request $request)
    {
        $patient = patient::findOrFail($patientId);
        $medication = ProductOrServiceRequest::with(['product.category', 'productRequest.doctor'])
            ->where('id', $medicationId)
            ->where('user_id', $patient->user_id)
            ->firstOrFail();

        // Check if we have a start_date in the query parameters (which takes precedence)
        $startDateQuery = $request->query('start_date');
        if ($startDateQuery) {
            $startDate = Carbon::parse($startDateQuery)->startOfDay()->format('Y-m-d');
        }
        // If start_date URL parameter provided, use that
        else if (!$startDate) {
            // Default to 15 days before today if no date is provided
            $startDate = Carbon::now()->subDays(15)->startOfDay()->format('Y-m-d');
        } else {
            // Ensure start date is start of day
            $startDate = Carbon::parse($startDate)->startOfDay()->format('Y-m-d');
        }

        // Check if we have an end_date in the query parameters
        $endDate = $request->query('end_date');
        if (!$endDate) {
            // Default to 30 days from start if no end date provided
            $endDate = Carbon::parse($startDate)->addDays(30)->endOfDay()->format('Y-m-d');
        } else {
            // Ensure end date is end of day
            $endDate = Carbon::parse($endDate)->endOfDay()->format('Y-m-d');
        }

        // Ensure we have proper date objects for Carbon
        $startDateCarbon = Carbon::parse($startDate)->startOfDay();
        $endDateCarbon = Carbon::parse($endDate)->endOfDay();

        // Get all schedules for this medication in the date range
        $schedules = MedicationSchedule::where('patient_id', $patientId)
            ->where('product_or_service_request_id', $medicationId)
            ->whereBetween('scheduled_time', [$startDate, $endDate])
            ->orderBy('scheduled_time')
            ->get();

        // Get all administrations for these schedules
        $scheduleIds = $schedules->pluck('id')->toArray();
        $administrations = MedicationAdministration::with(['administeredBy', 'editedBy', 'deletedBy'])
            ->whereIn('schedule_id', $scheduleIds)
            ->orderBy('administered_at')
            ->get();

        // Add name properties for each related user using the userfullname helper
        $administrations->transform(function ($admin) {
            // Add name for administeredBy user
            if ($admin->administeredBy) {
                // Override the name property with the full name from the helper
                $admin->administeredBy->name = userfullname($admin->administered_by);

                // Also add direct access properties for the frontend
                $admin->administered_by_name = userfullname($admin->administered_by);
            }

            // Add name for editedBy user
            if ($admin->editedBy && $admin->edited_by) {
                $admin->editedBy->name = userfullname($admin->edited_by);
                $admin->edited_by_name = userfullname($admin->edited_by);
            }

            // Add name for deletedBy user
            if ($admin->deletedBy && $admin->deleted_by) {
                $admin->deletedBy->name = userfullname($admin->deleted_by);
                $admin->deleted_by_name = userfullname($admin->deleted_by);
            }

            return $admin;
        });            // Get medication history (discontinuations, resumptions)
        $history = MedicationHistory::with('user')
            ->where('patient_id', $patientId)
            ->where('product_or_service_request_id', $medicationId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Add user names to history records
        $history->each(function ($record) {
            $record->user_fullname = userfullname($record->user_id);
        });

        // Get all administration history for this medication
        $adminHistory = MedicationAdministration::with(['administeredBy', 'editedBy', 'deletedBy'])
            ->where('patient_id', $patientId)
            ->where('product_or_service_request_id', $medicationId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Add names to admin history records
        $adminHistory->each(function ($admin) {
            $admin->administered_by_name = userfullname($admin->administered_by);
            $admin->edited_by_name = $admin->edited_by ? userfullname($admin->edited_by) : null;
            $admin->deleted_by_name = $admin->deleted_by ? userfullname($admin->deleted_by) : null;
        });

        // Add discontinuation/resumption data to medication object
        $latestDiscontinue = $history->where('action', 'discontinue')->first();
        $latestResume = $history->where('action', 'resume')->first();

        if ($latestDiscontinue) {
            $medication->discontinued_at = $latestDiscontinue->created_at;
            $medication->discontinued_reason = $latestDiscontinue->reason;
            $medication->discontinued_by = userfullname($latestDiscontinue->user_id);
            $medication->discontinued_by_name = userfullname($latestDiscontinue->user_id);
            $medication->discontinued_by_id = $latestDiscontinue->user_id;
        }

        if ($latestResume && (!$latestDiscontinue || $latestResume->created_at > $latestDiscontinue->created_at)) {
            $medication->discontinued_at = null;
            $medication->resumed_at = $latestResume->created_at;
            $medication->resumed_by_name = userfullname($latestResume->user_id);
            $medication->resumed_reason = $latestResume->reason;
            $medication->resumed_by = userfullname($latestResume->user_id);
            $medication->resumed_by_id = $latestResume->user_id;
        }


        // Attach doctor's dose/freq, doctor name, and prescription date to the medication object for frontend
        $medication->doctor_dose = $medication->productRequest ? $medication->productRequest->dose : null;
        $medication->doctor_name = null;
        $medication->prescription_date = null;
        if ($medication->productRequest) {
            // Doctor name (use userfullname if doctor relation exists)
            if ($medication->productRequest->doctor) {
                $medication->doctor_name = userfullname($medication->productRequest->doctor->id);
            }
            // Fallback: if doctor_id exists but no relation loaded
            elseif (!empty($medication->productRequest->doctor_id)) {
                $medication->doctor_name = userfullname($medication->productRequest->doctor_id);
            }
            // Prescription date
            if (!empty($medication->productRequest->created_at)) {
                $medication->prescription_date = $medication->productRequest->created_at;
            }
        }

        return response()->json([
            'medication' => $medication,
            'schedules' => $schedules,
            'administrations' => $administrations,
            'total_scheduled' => $schedules->count(),
            'total_administered' => $administrations->whereNotNull('administered_at')->count(),
            'history' => $history,
            'adminHistory' => $adminHistory,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    /**
     * Calendar view for direct administration entries (ward_stock / patient_own).
     * These have no POSR, so we query by product_id + drug_source OR external_drug_name + drug_source.
     */
    public function directCalendar($patientId, Request $request)
    {
        $patient = patient::findOrFail($patientId);

        $drugSource = $request->query('drug_source');
        $productId = $request->query('product_id');
        $externalDrugName = $request->query('external_drug_name');

        if (!$drugSource || !in_array($drugSource, ['ward_stock', 'patient_own'])) {
            return response()->json(['error' => 'Invalid drug_source'], 422);
        }

        // Parse date range
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()->format('Y-m-d')
            : Carbon::now()->subDays(15)->startOfDay()->format('Y-m-d');
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()->format('Y-m-d')
            : Carbon::parse($startDate)->addDays(30)->endOfDay()->format('Y-m-d');

        // Build a pseudo medication object for the frontend
        $medicationData = [
            'id' => 'direct_' . $drugSource . '_' . ($productId ?: md5($externalDrugName ?? '')),
            'drug_source' => $drugSource,
            'is_direct_entry' => true,
        ];

        if ($drugSource === 'ward_stock' && $productId) {
            $product = Product::find($productId);
            $medicationData['product_name'] = $product ? $product->product_name : 'Unknown Product';
            $medicationData['product_code'] = $product ? $product->product_code : '';
            $medicationData['product_id'] = $productId;
        } elseif ($drugSource === 'patient_own' && $externalDrugName) {
            $medicationData['product_name'] = $externalDrugName;
            $medicationData['product_code'] = '';
            $medicationData['external_drug_name'] = $externalDrugName;
        } else {
            return response()->json(['error' => 'Missing product_id or external_drug_name'], 422);
        }

        // Query administrations for this direct entry
        $adminQuery = MedicationAdministration::with(['administeredBy', 'editedBy', 'deletedBy'])
            ->where('patient_id', $patientId)
            ->where('drug_source', $drugSource)
            ->whereNull('deleted_at');

        if ($drugSource === 'ward_stock') {
            $adminQuery->where('product_id', $productId);
        } else {
            $adminQuery->where('external_drug_name', $externalDrugName);
        }

        // Get all administrations (not limited to date range for admin history)
        $allAdmins = $adminQuery->orderByDesc('administered_at')->get();

        // Add name properties
        $allAdmins->transform(function ($admin) {
            if ($admin->administeredBy) {
                $admin->administeredBy->name = userfullname($admin->administered_by);
                $admin->administered_by_name = userfullname($admin->administered_by);
            }
            if ($admin->editedBy && $admin->edited_by) {
                $admin->editedBy->name = userfullname($admin->edited_by);
                $admin->edited_by_name = userfullname($admin->edited_by);
            }
            if ($admin->deletedBy && $admin->deleted_by) {
                $admin->deletedBy->name = userfullname($admin->deleted_by);
                $admin->deleted_by_name = userfullname($admin->deleted_by);
            }
            return $admin;
        });

        // Filter administrations within the date range (for calendar display)
        $rangeAdmins = $allAdmins->filter(function ($admin) use ($startDate, $endDate) {
            return $admin->administered_at >= $startDate && $admin->administered_at <= $endDate;
        })->values();

        // Query schedules for this direct entry
        $scheduleQuery = MedicationSchedule::where('patient_id', $patientId)
            ->where('drug_source', $drugSource)
            ->whereNull('product_or_service_request_id');

        if ($drugSource === 'ward_stock') {
            $scheduleQuery->where('product_id', $productId);
        } else {
            $scheduleQuery->where('external_drug_name', $externalDrugName);
        }

        $schedules = $scheduleQuery
            ->whereBetween('scheduled_time', [$startDate, $endDate])
            ->orderBy('scheduled_time')
            ->get();

        // Get administrations that are linked to these schedules
        $scheduleIds = $schedules->pluck('id')->toArray();
        $scheduledAdmins = MedicationAdministration::with(['administeredBy', 'editedBy', 'deletedBy'])
            ->whereIn('schedule_id', $scheduleIds)
            ->orderBy('administered_at')
            ->get();

        $scheduledAdmins->transform(function ($admin) {
            if ($admin->administeredBy) {
                $admin->administeredBy->name = userfullname($admin->administered_by);
                $admin->administered_by_name = userfullname($admin->administered_by);
            }
            if ($admin->editedBy && $admin->edited_by) {
                $admin->editedBy->name = userfullname($admin->edited_by);
                $admin->edited_by_name = userfullname($admin->edited_by);
            }
            if ($admin->deletedBy && $admin->deleted_by) {
                $admin->deletedBy->name = userfullname($admin->deleted_by);
                $admin->deleted_by_name = userfullname($admin->deleted_by);
            }
            return $admin;
        });

        // Merge scheduled administrations with unscheduled direct admins (avoid duplicates)
        $scheduledAdminIds = $scheduledAdmins->pluck('id')->toArray();
        $unscheduledAdmins = $rangeAdmins->filter(function ($admin) use ($scheduledAdminIds) {
            return !in_array($admin->id, $scheduledAdminIds);
        });
        $combinedAdmins = $scheduledAdmins->merge($unscheduledAdmins)->sortBy('administered_at')->values();

        return response()->json([
            'medication' => $medicationData,
            'schedules' => $schedules,
            'administrations' => $combinedAdmins,
            'total_scheduled' => $schedules->count(),
            'total_administered' => $allAdmins->count(),
            'history' => [],
            'adminHistory' => $allAdmins,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    public function storeTiming(Request $request)
    {
        $drugSource = $request->input('drug_source', 'pharmacy_dispensed');
        $isDirect = in_array($drugSource, ['ward_stock', 'patient_own']);

        // Build validation rules based on drug source
        $rules = [
            'patient_id' => 'required|exists:patients,id',
            'time' => 'required',
            'dose' => 'required|string',
            'route' => 'required|string',
            'repeat_type' => 'nullable|string|in:daily,specific,selected,once',
            'repeat_daily' => 'nullable|boolean',
            'selected_days' => 'nullable|array',
            'duration_days' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'note' => 'nullable|string',
            'drug_source' => 'nullable|string|in:pharmacy_dispensed,ward_stock,patient_own',
        ];

        if ($isDirect) {
            // Direct entries: no POSR, but need product_id (ward_stock) or external_drug_name (patient_own)
            $rules['product_or_service_request_id'] = 'nullable';
            if ($drugSource === 'ward_stock') {
                $rules['product_id'] = 'required|exists:products,id';
            } else {
                $rules['external_drug_name'] = 'required|string|max:255';
            }
        } else {
            $rules['product_or_service_request_id'] = 'required|exists:product_or_service_requests,id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $patientId = $data['patient_id'];
        $medicationId = $isDirect ? null : $data['product_or_service_request_id'];
        $time = $data['time'];
        $dose = $data['dose'];
        $route = $data['route'];
        $note = $data['note'] ?? null;
        $durationDays = $data['duration_days'];
        $startDate = Carbon::parse($data['start_date'])->startOfDay();

        // Handle both repeat_type and repeat_daily for backward compatibility
        // 'selected' is an alias for 'specific' (specific days of week)
        $repeatType = $data['repeat_type'] ?? ($data['repeat_daily'] ?? false ? 'daily' : 'once');
        if ($repeatType === 'selected') {
            $repeatType = 'specific';
        }
        $selectedDays = $data['selected_days'] ?? [];

        try {
            DB::beginTransaction();

            $schedules = [];

            // Create schedules for the requested duration
            for ($i = 0; $i < $durationDays; $i++) {
                $currentDate = $startDate->copy()->addDays($i);

                // Check if this day should be scheduled based on repeat type
                $shouldSchedule = false;

                if ($repeatType === 'daily') {
                    // Schedule every day
                    $shouldSchedule = true;
                } elseif ($repeatType === 'specific' && !empty($selectedDays)) {
                    // Schedule only on selected days of week (0=Sunday, 1=Monday, etc.)
                    $dayOfWeek = $currentDate->dayOfWeek; // 0=Sunday, 6=Saturday
                    $shouldSchedule = in_array($dayOfWeek, array_map('intval', $selectedDays));
                } elseif ($repeatType === 'once') {
                    // Only schedule on the first day
                    $shouldSchedule = ($i === 0);
                }

                // Create schedule if this day should be scheduled
                if ($shouldSchedule) {
                    $scheduledDateTime = $currentDate->format('Y-m-d') . ' ' . $time;

                    $schedule = new MedicationSchedule();
                    $schedule->patient_id = $patientId;
                    $schedule->product_or_service_request_id = $medicationId;
                    $schedule->drug_source = $drugSource;
                    $schedule->scheduled_time = $scheduledDateTime;
                    $schedule->dose = $dose;
                    $schedule->route = $route;
                    $schedule->created_by = Auth::id();

                    // Direct entry fields
                    if ($isDirect) {
                        $schedule->product_id = $data['product_id'] ?? null;
                        $schedule->external_drug_name = $data['external_drug_name'] ?? null;
                    }

                    $schedule->save();

                    $schedules[] = $schedule;
                }
            }

            DB::commit();

            // Load relationships for response
            $schedules = collect($schedules)->map(function($schedule) {
                return $schedule->load(['patient', 'productOrServiceRequest', 'creator']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Medication schedule created successfully',
                'count' => count($schedules),
                'schedules' => $schedules
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create medication schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    public function administer(Request $request)
    {
        // §6.5: Scheduled charting — always pharmacy_dispensed.
        // Ward stock and patient's own use administerDirect() instead.
        $validator = Validator::make($request->all(), [
            'schedule_id'         => 'required|exists:medication_schedules,id',
            'administered_at'     => 'required|date',
            'administered_dose'   => 'required|string|max:100',
            'qty'                 => 'nullable|numeric|min:0.01',
            'route'               => 'required|string|max:50',
            'comment'             => 'nullable|string|max:500',
            'drug_source'         => 'nullable|in:pharmacy_dispensed',
            'product_request_id'  => 'nullable|exists:product_requests,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $scheduleId = $data['schedule_id'];

        try {
            DB::beginTransaction();

            // Get the schedule
            $schedule = MedicationSchedule::findOrFail($scheduleId);

            // Check if medication has been discontinued
            $medication = ProductOrServiceRequest::find($schedule->product_or_service_request_id);
            $isDiscontinued = false;

            if ($medication) {
                $latestHistory = MedicationHistory::where('product_or_service_request_id', $medication->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($latestHistory && $latestHistory->action === 'discontinue') {
                    $isDiscontinued = true;
                }
            }

            if ($isDiscontinued) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot administer a discontinued medication'
                ], 422);
            }

            // Derive related ProductRequest
            $productRequest = null;

            if (!empty($data['product_request_id'])) {
                $productRequest = ProductRequest::find($data['product_request_id']);
            }

            if (!$productRequest && $schedule->product_or_service_request_id) {
                $productRequest = ProductRequest::where('product_request_id', $schedule->product_or_service_request_id)->first();
            }

            if ($productRequest && $productRequest->patient_id !== $schedule->patient_id) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Prescription does not belong to this patient',
                ], 422);
            }

            if ($productRequest && $schedule->product_or_service_request_id && $productRequest->product_request_id !== $schedule->product_or_service_request_id) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Prescription does not match this schedule',
                ], 422);
            }

            // Validate: must be dispensed for charting
            if (!$productRequest || $productRequest->status !== 3) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This prescription has not been dispensed yet'
                ], 422);
            }

            $userId = Auth::id();

            // Create administration record
            $admin = new MedicationAdministration();
            $admin->patient_id = $schedule->patient_id;
            $admin->product_id = $productRequest->product_id; // store product for direct lookup
            $admin->schedule_id = $scheduleId;
            $admin->product_or_service_request_id = $schedule->product_or_service_request_id;
            $admin->product_request_id = $productRequest->id ?? null;
            $admin->administered_at = $data['administered_at'];
            $admin->dose = $data['administered_dose'];
            $admin->qty = $data['qty'] ?? 1;
            $admin->route = $data['route'];
            $admin->comment = $data['comment'] ?? null;
            $admin->drug_source = 'pharmacy_dispensed';
            $admin->administered_by = $userId;
            $admin->save();

            // Reload the administration with its relationships
            $admin = MedicationAdministration::with(['administeredBy'])
                ->find($admin->id);

            // Add name property for frontend display
            $admin->administered_by_name = userfullname($userId);

            if ($admin->administeredBy) {
                $admin->administeredBy->name = userfullname($userId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Medication administered successfully',
                'administration' => $admin
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to administer medication: ' . $e->getMessage()
            ], 500);
        }
    }

    public function discontinue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'product_or_service_request_id' => 'required|exists:product_or_service_requests,id',
            'reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $userId = Auth::id();

        try {
            DB::beginTransaction();

            // Create history record
            $history = new MedicationHistory();
            $history->patient_id = $data['patient_id'];
            $history->product_or_service_request_id = $data['product_or_service_request_id'];
            $history->action = 'discontinue';
            $history->reason = $data['reason'];
            $history->user_id = $userId;
            $history->save();

            // Include user full name in the response
            $history->user_fullname = userfullname($userId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Medication discontinued successfully',
                'history' => $history
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to discontinue medication: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resume(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'product_or_service_request_id' => 'required|exists:product_or_service_requests,id',
            'reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $userId = Auth::id();

        try {
            DB::beginTransaction();

            // Create history record
            $history = new MedicationHistory();
            $history->patient_id = $data['patient_id'];
            $history->product_or_service_request_id = $data['product_or_service_request_id'];
            $history->action = 'resume';
            $history->reason = $data['reason'];
            $history->user_id = $userId;
            $history->save();

            // Include user full name in the response
            $history->user_fullname = userfullname($userId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Medication resumed successfully',
                'history' => $history
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to resume medication: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteAdministration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'administration_id' => 'required|exists:medication_administrations,id',
            'reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            DB::beginTransaction();

            $admin = MedicationAdministration::findOrFail($data['administration_id']);

            // Check if within edit window
            $adminTime = Carbon::parse($admin->administered_at);
            $now = Carbon::now();
            $diffMinutes = $now->diffInMinutes($adminTime);

            $editWindow = config('app.note_edit_window', 30); // Default 30 minutes

            if ($diffMinutes > $editWindow) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete administration after {$editWindow} minutes"
                ], 422);
            }

            // Soft delete the administration
            $admin->deleted_at = $now;
            $userId = Auth::id();
            $admin->deleted_by = $userId;
            $admin->delete_reason = $data['reason'];
            $admin->save();

            // Reload the administration with its relationships (withTrashed since we just soft-deleted)
            $admin = MedicationAdministration::withTrashed()->with(['administeredBy', 'editedBy', 'deletedBy'])
                ->find($data['administration_id']);

            // Add name properties for frontend display
            $admin->administered_by_name = userfullname($admin->administered_by);
            $admin->edited_by_name = $admin->edited_by ? userfullname($admin->edited_by) : null;
            $admin->deleted_by_name = userfullname($userId);

            // Also update name in related objects
            if ($admin->deletedBy) {
                $admin->deletedBy->name = userfullname($userId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Administration deleted successfully',
                'administration' => $admin
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete administration: ' . $e->getMessage()
            ], 500);
        }
    }

    public function editAdministration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'administration_id' => 'required|exists:medication_administrations,id',
            'administered_at' => 'required|date',
            'dose' => 'required|string',
            'route' => 'required|string',
            'comment' => 'nullable|string',
            'edit_reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            DB::beginTransaction();

            $admin = MedicationAdministration::findOrFail($data['administration_id']);

            // Check if within edit window
            $adminTime = Carbon::parse($admin->administered_at);
            $now = Carbon::now();
            $diffMinutes = $now->diffInMinutes($adminTime);

            $editWindow = config('app.note_edit_window', 30); // Default 30 minutes

            if ($diffMinutes > $editWindow) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Cannot edit administration after {$editWindow} minutes"
                ], 422);
            }

            // Store previous values for history
            $previousData = [
                'administered_at' => $admin->administered_at,
                'dose' => $admin->dose,
                'route' => $admin->route,
                'comment' => $admin->comment
            ];

            // Update the administration
            $admin->administered_at = $data['administered_at'];
            $admin->dose = $data['dose'];
            $admin->route = $data['route'];
            $admin->comment = $data['comment'];
            $admin->edited_at = $now;
            $userId = Auth::id();
            $admin->edited_by = $userId;
            $admin->edit_reason = $data['edit_reason'];
            $admin->previous_data = json_encode($previousData);
            $admin->save();

            // Reload the administration with its relationships
            $admin = MedicationAdministration::with(['administeredBy', 'editedBy', 'deletedBy'])
                ->find($data['administration_id']);

            // Add name properties for frontend display
            $admin->administered_by_name = userfullname($admin->administered_by);
            $admin->edited_by_name = userfullname($userId);
            $admin->deleted_by_name = $admin->deleted_by ? userfullname($admin->deleted_by) : null;

            // Also update name in related objects
            if ($admin->administeredBy) {
                $admin->administeredBy->name = userfullname($admin->administered_by);
            }

            if ($admin->editedBy) {
                $admin->editedBy->name = userfullname($userId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Administration updated successfully',
                'administration' => $admin
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update administration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * §6.2–6.4: Direct administration for ward stock and patient's own drugs.
     * These bypass the schedule system — no schedule_id, no POSR linkage for unscheduled entries.
     */
    public function administerDirect(Request $request, $patientId)
    {
        $drugSource = $request->input('drug_source');

        // Conditional validation rules per drug source
        $rules = [
            'drug_source'        => 'required|in:patient_own,ward_stock',
            'administered_at'    => 'required|date',
            'administered_dose'  => 'required|string|max:100',
            'route'              => 'required|string|max:50',
            'note'               => 'nullable|string|max:500',
            'schedule_id'        => 'nullable|exists:medication_schedules,id',
        ];

        if ($drugSource === 'patient_own') {
            $rules['external_drug_name']    = 'required|string|max:255';
            $rules['external_qty']          = 'required|numeric|min:0.01';
            $rules['external_batch_number'] = 'nullable|string|max:50';
            $rules['external_expiry_date']  = 'nullable|date';
            $rules['external_source_note']  = 'nullable|string|max:500';
        } elseif ($drugSource === 'ward_stock') {
            $rules['product_id'] = 'required|exists:products,id';
            $rules['store_id']   = 'required|exists:stores,id';
            $rules['qty']        = 'required|integer|min:1';
            $rules['bill_patient'] = 'nullable|boolean';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Verify patient exists
        $patient = patient::findOrFail($patientId);

        try {
            DB::beginTransaction();

            $userId = Auth::id();
            $dispensedBatchId = null;
            $productRequestId = null;
            $posrId = null;
            $scheduleId = !empty($data['schedule_id']) ? $data['schedule_id'] : null;

            // ─── PATH: PATIENT'S OWN ─────────────────────────
            // No POSR, no ProductRequest, no stock changes
            if ($drugSource === 'patient_own') {

                $admin = new MedicationAdministration();
                $admin->patient_id = $patientId;
                $admin->product_id = null; // patient's own — not a hospital product
                $admin->schedule_id = $scheduleId;
                $admin->product_or_service_request_id = null;
                $admin->product_request_id = null;
                $admin->administered_at = $data['administered_at'];
                $admin->dose = $data['administered_dose'];
                $admin->route = $data['route'];
                $admin->comment = $data['note'] ?? null;
                $admin->drug_source = 'patient_own';
                $admin->administered_by = $userId;
                $admin->store_id = null;
                $admin->dispensed_from_batch_id = null;
                $admin->external_drug_name = $data['external_drug_name'];
                $admin->external_qty = $data['external_qty'];
                $admin->external_batch_number = $data['external_batch_number'] ?? null;
                $admin->external_expiry_date = $data['external_expiry_date'] ?? null;
                $admin->external_source_note = $data['external_source_note'] ?? null;
                $admin->save();

            // ─── PATH: WARD STOCK ────────────────────────────
            } elseif ($drugSource === 'ward_stock') {

                $productId = $data['product_id'];
                $storeId = $data['store_id'];
                $qty = $data['qty'];
                $billPatient = !empty($data['bill_patient']);

                // Validate stock availability
                $stockService = app(StockService::class);
                $availableStock = $stockService->getAvailableStock($productId, $storeId);

                if ($availableStock < $qty) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock. Available: ' . $availableStock . ', Requested: ' . $qty
                    ], 422);
                }

                // Deduct stock (FIFO)
                $dispensed = $stockService->dispenseStock(
                    $productId,
                    $storeId,
                    $qty,
                    MedicationAdministration::class,
                    null,
                    'Medication chart direct ward stock administration'
                );
                $dispensedBatchId = array_key_first($dispensed);

                // ─── BILLED WARD STOCK: Create ProductRequest + POSR via tariff pipeline ───
                if ($billPatient) {
                    $product = Product::with('price')->find($productId);

                    // Create ProductRequest (status=2 = billed, mirrors PharmacyWorkbenchController::billPrescriptions)
                    $productRequest = ProductRequest::create([
                        'patient_id'   => $patientId,
                        'product_id'   => $productId,
                        'encounter_id' => $patient->current_encounter_id ?? null,
                        'doctor_id'    => null, // nurse-initiated
                        'qty'          => $qty,
                        'status'       => 2, // billed
                        'billed_by'    => $userId,
                        'billed_date'  => now(),
                    ]);

                    // Create POSR via tariff pipeline (mirrors PharmacyWorkbenchController)
                    $posr = new ProductOrServiceRequest();
                    $posr->user_id = $patient->user_id; // MUST be User table ID, not Patient ID
                    $posr->staff_user_id = $userId;
                    $posr->product_id = $productId;
                    $posr->qty = $qty;
                    $posr->dispensed_from_store_id = $storeId;
                    $posr->hmo_id = $patient->hmo_id ?? null;
                    $this->applyTariffToRequest($posr, $patient, $productId, $product, $qty);
                    $posr->save();

                    // Link ProductRequest → POSR
                    $productRequest->product_request_id = $posr->id;
                    $productRequest->save();

                    $productRequestId = $productRequest->id;
                    $posrId = $posr->id;
                }

                $admin = new MedicationAdministration();
                $admin->patient_id = $patientId;
                $admin->product_id = $productId; // always set for ward stock
                $admin->schedule_id = $scheduleId;
                $admin->product_or_service_request_id = $posrId;
                $admin->product_request_id = $productRequestId;
                $admin->administered_at = $data['administered_at'];
                $admin->dose = $data['administered_dose'];
                $admin->route = $data['route'];
                $admin->comment = $data['note'] ?? null;
                $admin->drug_source = 'ward_stock';
                $admin->administered_by = $userId;
                $admin->store_id = $storeId;
                $admin->dispensed_from_batch_id = $dispensedBatchId;
                $admin->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => ucfirst(str_replace('_', ' ', $drugSource)) . ' drug administered successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to administer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply HMO tariff pricing to a POSR — mirrors PharmacyWorkbenchController::applyHmoTariffToRequest().
     * Uses HmoHelper::applyHmoTariff() for HMO patients, falls back to product sale price for cash.
     */
    private function applyTariffToRequest(ProductOrServiceRequest $posr, $patient, $productId, $product, $qty)
    {
        $tariffData = HmoHelper::applyHmoTariff($patient->id, $productId, null);

        if ($tariffData && isset($tariffData['payable_amount'])) {
            $posr->payable_amount    = $tariffData['payable_amount'] * $qty;
            $posr->claims_amount     = ($tariffData['claims_amount'] ?? 0) * $qty;
            $posr->coverage_mode     = $tariffData['coverage_mode'] ?? null;
            $posr->validation_status = $tariffData['validation_status'] ?? null;

            if (isset($tariffData['hmo_id'])) {
                $posr->hmo_id = $tariffData['hmo_id'];
            }
        } else {
            // Cash fallback — use product sale price
            $unitPrice = optional(optional($product)->price)->current_sale_price ?? 0;
            $posr->payable_amount = $unitPrice * $qty;
            $posr->claims_amount  = 0;
        }
    }
}
