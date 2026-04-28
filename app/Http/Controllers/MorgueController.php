<?php

namespace App\Http\Controllers;

use App\Models\DeathRecord;
use App\Models\MorgueAdmission;
use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MorgueController extends Controller
{
    public function index()
    {
        return view('admin.morgue.workbench');
    }

    /**
     * Get queue for Morgue Workbench
     */
    public function getQueue()
    {
        // 1. Pending Admissions (Deceased patients with disposition 'morgue' but not yet admitted to morgue)
        $pending = DeathRecord::with(['patient.user', 'patient.hmo'])
            ->where('disposition', 'morgue')
            ->where('last_office_done', true)
            ->whereDoesntHave('morgueAdmission')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'patient_id' => $r->patient_id,
                    'name' => userfullname($r->patient->user_id),
                    'file_no' => $r->patient->file_no,
                    'death_type' => $r->death_type,
                    'date_of_death' => $r->date_of_death,
                    'time_of_death' => $r->time_of_death,
                ];
            });

        // 2. Active Admissions
        $active = MorgueAdmission::with(['patient.user', 'patient.hmo', 'serviceRequest'])
            ->whereNull('release_time')
            ->where('status', 'stored')
            ->get()
            ->map(function ($a) {
                $days = Carbon::parse($a->arrival_time)->diffInDays(now()) + 1;
                return [
                    'id' => $a->id,
                    'patient_id' => $a->patient_id,
                    'name' => userfullname($a->patient->user_id),
                    'file_no' => $a->patient->file_no,
                    'fridge_no' => $a->fridge_number,
                    'tray_no' => $a->tray_number,
                    'admitted_at' => $a->arrival_time->format('M d, Y H:i'),
                    'days_spent' => $days,
                    'status' => $a->status,
                ];
            });

        return response()->json([
            'pending' => $pending,
            'active' => $active
        ]);
    }

    /**
     * Admit body to morgue
     */
    public function admit(Request $request)
    {
        $request->validate([
            'death_record_id' => 'required_without:patient_id|exists:death_records,id',
            'patient_id' => 'required_without:death_record_id|exists:patients,id',
            'fridge_no' => 'nullable|string|max:50',
            'tray_no' => 'nullable|string|max:50',
            'daily_service_id' => 'required|exists:services,id',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            if ($request->death_record_id) {
                $deathRecord = DeathRecord::find($request->death_record_id);
            } else {
                // Bridge for Shared Modal: Find or create DeathRecord for the patient
                $deathRecord = DeathRecord::updateOrCreate(
                    ['patient_id' => $request->patient_id],
                    [
                        'death_type' => 'BID',
                        'date_of_death' => now()->toDateString(),
                        'time_of_death' => now()->toTimeString(),
                        'cause_of_death_primary' => 'Brought in Dead',
                        'certified_by_doctor_id' => Auth::id(),
                        'disposition' => 'pending'
                    ]
                );

                $patient = Patient::find($request->patient_id);
                if ($patient && !$patient->is_deceased) {
                    $patient->update(['is_deceased' => true]);
                }
            }

            // Create Service Request for Daily Fee (initial)
            $serviceRequest = ProductOrServiceRequest::create([
                'service_id' => $request->daily_service_id,
                'user_id' => $deathRecord->patient->user_id,
                'staff_user_id' => Auth::id(),
                'status' => 1, // Pending
                'qty' => 1
            ]);

            // Generate Body Code
            $year = now()->year;
            $count = MorgueAdmission::whereYear('arrival_time', $year)->count() + 1;
            $bodyCode = 'MORG-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Create Morgue Admission
            MorgueAdmission::create([
                'death_record_id' => $deathRecord->id,
                'patient_id' => $deathRecord->patient_id,
                'body_code' => $bodyCode,
                'fridge_number' => $request->fridge_no,
                'tray_number' => $request->tray_no,
                'arrival_time' => now(),
                'admitted_by_staff_id' => Auth::id(),
                'daily_service_id' => $request->daily_service_id,
                'current_service_request_id' => $serviceRequest->id,
                'notes' => $request->notes,
                'status' => 'stored'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Body successfully admitted to morgue.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Morgue admission error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to admit: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add ad-hoc service (embalming, etc.)
     */
    public function addService(Request $request)
    {
        $request->validate([
            'morgue_admission_id' => 'required|exists:morgue_admissions,id',
            'service_id' => 'required|exists:services,id',
            'qty' => 'required|integer|min:1'
        ]);

        try {
            $admission = MorgueAdmission::find($request->morgue_admission_id);

            ProductOrServiceRequest::create([
                'service_id' => $request->service_id,
                'user_id' => $admission->patient->user_id,
                'staff_user_id' => Auth::id(),
                'status' => 1,
                'qty' => $request->qty
            ]);

            return response()->json(['success' => true, 'message' => 'Service added to bill.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to add service.'], 500);
        }
    }

    /**
     * Release body
     */
    public function release(Request $request)
    {
        $request->validate([
            'morgue_admission_id' => 'required|exists:morgue_admissions,id',
            'released_to_name' => 'required|string|max:150',
            'released_to_phone' => 'nullable|string|max:20',
            'release_notes' => 'nullable|string',
        ]);

        try {
            $admission = MorgueAdmission::find($request->morgue_admission_id);

            // Check for pending bills?
            // In many systems, they must pay before release.
            // For now, let's just mark as released.

            $admission->update([
                'release_time' => now(),
                'released_by_staff_id' => Auth::id(),
                'released_to_name' => $request->released_to_name,
                'released_to_id_no' => $request->released_to_phone, // Using phone as ID for now
                'notes' => $request->release_notes,
                'status' => 'released'
            ]);

            return response()->json(['success' => true, 'message' => 'Body released successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to release body.'], 500);
        }
    }

    /**
     * Get reports/analytics data for the morgue workbench
     */
    public function getReports(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo   = $request->input('date_to',   Carbon::now()->toDateString());

        $categoryId = appsettings('morgue_category_id') ??
                     DB::table('service_categories')->where('category_name', 'MORGUE')->orWhere('category_name', 'Morgue')->value('id') ?? 9;

        // ── Summary Stats ───────────────────────────────────────────────
        $admissionsInRange = MorgueAdmission::whereBetween('arrival_time', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        $totalAdmissions = (clone $admissionsInRange)->count();
        $totalReleased   = (clone $admissionsInRange)->where('status', 'released')->count();
        $currentlyStored = MorgueAdmission::whereNull('release_time')->where('status', 'stored')->count();

        // Average stay (in hours → convert to days) for released bodies in range
        $avgStay = (clone $admissionsInRange)
            ->where('status', 'released')
            ->whereNotNull('release_time')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, arrival_time, release_time)) as avg_hours')
            ->value('avg_hours');
        $avgStayDays = $avgStay ? round($avgStay / 24, 1) : 0;

        // Revenue from morgue service requests in the range
        $revenue = ProductOrServiceRequest::whereHas('service', fn($q) => $q->where('category_id', $categoryId))
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->sum(DB::raw('qty * payable_amount'));

        $pendingRevenue = ProductOrServiceRequest::whereHas('service', fn($q) => $q->where('category_id', $categoryId))
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->whereNull('payment_id')
            ->sum(DB::raw('qty * payable_amount'));

        // ── Monthly Admission Trend (last 12 months) ─────────────────────
        $trend = MorgueAdmission::selectRaw("DATE_FORMAT(arrival_time, '%Y-%m') as month, COUNT(*) as total")
            ->where('arrival_time', '>=', Carbon::now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $trendLabels = [];
        $trendData   = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = Carbon::now()->subMonths($i)->format('Y-m');
            $trendLabels[] = Carbon::now()->subMonths($i)->format('M Y');
            $trendData[]   = $trend->has($key) ? (int) $trend[$key]->total : 0;
        }

        // ── Death Type Breakdown (in range) ───────────────────────────────
        $deathTypes = MorgueAdmission::with('deathRecord')
            ->whereBetween('arrival_time', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->get()
            ->groupBy(fn($a) => optional($a->deathRecord)->death_type ?? 'Unknown')
            ->map->count()
            ->toArray();

        // ── Admissions Table ──────────────────────────────────────────────
        $admissions = MorgueAdmission::with(['patient.user', 'deathRecord'])
            ->whereBetween('arrival_time', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->orderByDesc('arrival_time')
            ->get()
            ->map(function ($a) {
                $stay = $a->release_time
                    ? Carbon::parse($a->arrival_time)->diffInDays($a->release_time)
                    : Carbon::parse($a->arrival_time)->diffInDays(now());
                return [
                    'id'          => $a->id,
                    'patient_id'  => $a->patient_id,
                    'name'        => userfullname($a->patient->user_id),
                    'file_no'     => $a->patient->file_no,
                    'body_code'   => $a->body_code,
                    'death_type'  => optional($a->deathRecord)->death_type ?? 'N/A',
                    'admitted_at' => Carbon::parse($a->arrival_time)->format('M d, Y H:i'),
                    'released_at' => $a->release_time ? Carbon::parse($a->release_time)->format('M d, Y H:i') : null,
                    'days'        => $stay,
                    'status'      => $a->status,
                ];
            });

        return response()->json([
            'stats' => [
                'total_admissions' => $totalAdmissions,
                'total_released'   => $totalReleased,
                'currently_stored' => $currentlyStored,
                'avg_stay_days'    => $avgStayDays,
                'total_revenue'    => $revenue,
                'pending_revenue'  => $pendingRevenue,
            ],
            'trend'        => ['labels' => $trendLabels, 'data' => $trendData],
            'death_types'  => $deathTypes,
            'admissions'   => $admissions,
        ]);
    }

    /**
     * Get all bill items for a given morgue admission
     */
    public function getPatientBill($admissionId)
    {
        $admission = MorgueAdmission::with(['patient.user'])->findOrFail($admissionId);

        $categoryId = appsettings('morgue_category_id') ??
                     DB::table('service_categories')->where('category_name', 'MORGUE')->orWhere('category_name', 'Morgue')->value('id') ?? 9;

        $requests = ProductOrServiceRequest::with(['service', 'payment'])
            ->where('user_id', $admission->patient->user_id)
            ->whereHas('service', fn($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($r) {
                $unitPrice = $r->payable_amount ?? optional($r->service->price)->sale_price ?? 0;
                $total     = $r->qty * $unitPrice;
                return [
                    'id'           => $r->id,
                    'service_name' => optional($r->service)->service_name ?? 'Unknown',
                    'date'         => $r->created_at->format('M d, Y H:i'),
                    'qty'          => $r->qty,
                    'unit_price'   => $unitPrice,
                    'total'        => $total,
                    'paid'         => !is_null($r->payment_id),
                    'payment_ref'  => optional($r->payment)->reference_no,
                ];
            });

        $totalAmount   = $requests->sum('total');
        $paidAmount    = $requests->where('paid', true)->sum('total');
        $pendingAmount = $requests->where('paid', false)->sum('total');

        return response()->json([
            'patient_name'   => userfullname($admission->patient->user_id),
            'file_no'        => $admission->patient->file_no,
            'body_code'      => $admission->body_code,
            'items'          => $requests->values(),
            'total_amount'   => $totalAmount,
            'paid_amount'    => $paidAmount,
            'pending_amount' => $pendingAmount,
        ]);
    }

    /**
     * Get morgue services (for ad-hoc or daily fee)
     */
    public function getServices(Request $request)
    {
        $categoryId = appsettings('morgue_category_id') ??
                     DB::table('service_categories')->where('category_name', 'MORGUE')->orWhere('category_name', 'Morgue')->value('id') ??
                     9;

        $query = Service::where('status', 1)
            ->where('category_id', $categoryId)
            ->with(['price', 'category']);

        $services = $query->get()->map(function($service) use ($request) {
            $basePrice = optional($service->price)->sale_price;
            $coverage = null;

            if ($request->filled('patient_id')) {
                try {
                    $coverage = \App\Helpers\HmoHelper::applyHmoTariff($request->patient_id, null, $service->id);
                } catch (\Exception $e) {
                    $coverage = null;
                }
            }

            return [
                'id' => $service->id,
                'service_name' => $service->service_name,
                'service_code' => $service->service_code,
                'coverage_mode' => $coverage['coverage_mode'] ?? 'cash',
                'payable_amount' => $coverage['payable_amount'] ?? ($basePrice ?? 0),
                'claims_amount' => $coverage['claims_amount'] ?? 0,
                'validation_status' => $coverage['validation_status'] ?? null,
                'category' => $service->category,
                'price' => $service->price,
            ];
        });

        return response()->json($services);
    }
}
