<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {

        return view('admin.home');
    }
    public function fetchClinicAppointments(Request $request)
    {
        $startDate = $request->input('start');
        $endDate = $request->input('end');

        $data = DB::table('product_or_service_requests as p')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->select(DB::raw('DATE(p.created_at) as date'), DB::raw('COUNT(*) as total'))
            ->where('s.category_id', 1)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('p.created_at', [$startDate, $endDate]);
            })
            ->groupBy(DB::raw('DATE(p.created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function chartAppointmentsOverTime(Request $request)
    {
        $startDate = $request->input('start');
        $endDate = $request->input('end');

        $data = DB::table('product_or_service_requests as p')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->where('s.category_id', 1)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('p.created_at', [$startDate, $endDate]);
            })
            ->selectRaw('DATE(p.created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(p.created_at)')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }


    public function chartAppointmentsByClinic(Request $request)
    {
        $startDate = $request->input('start');
        $endDate = $request->input('end');
        $data = DB::table('product_or_service_requests as p')
            ->leftJoin('doctor_queues as dq', 'dq.request_entry_id', '=', 'p.id')
            ->leftJoin('clinics as c', 'dq.clinic_id', '=', 'c.id')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->where('s.category_id', 1)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('p.created_at', [$startDate, $endDate]);
            })
            ->select('c.name as clinic', DB::raw('count(*) as total'))
            ->groupBy('c.name')
            ->get();

        return response()->json($data);
    }

    public function chartTopClinicServices(Request $request)
    {
        $startDate = $request->input('start');
        $endDate = $request->input('end');
        $data = DB::table('product_or_service_requests as p')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->where('s.category_id', 1)
            ->select('s.service_name as service', DB::raw('count(*) as total'))
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('p.created_at', [$startDate, $endDate]);
            })
            ->groupBy('s.service_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json($data);
    }

    public function chartQueueStatus(Request $request)
    {
        $startDate = $request->input('start');
        $endDate = $request->input('end');
        $data = DB::table('doctor_queues as dq')
            ->leftJoin('product_or_service_requests as p', 'dq.request_entry_id', '=', 'p.id')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->where('s.category_id', 1)
            ->select(
                DB::raw('CASE dq.status
            WHEN 1 THEN \'Queued\'
            WHEN 2 THEN \'Attended\'
            WHEN 3 THEN \'Cancelled\'
            ELSE \'Unknown\' END AS status'),
                DB::raw('count(*) as total')
            )
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('p.created_at', [$startDate, $endDate]);
            })
            ->groupBy('dq.status')
            ->get();

        return response()->json($data);
    }

    public function dashboardStats()
    {
        $today = now()->toDateString();

        // New patients registered today
        $newPatients = \App\Models\patient::whereDate('created_at', $today)->count();

        // Returning patients seen today (patients with encounters today, but not new)
        $returningPatients = \App\Models\Encounter::whereDate('created_at', $today)
            ->whereHas('patient', function ($q) use ($today) {
                $q->whereDate('created_at', '<', $today);
            })->distinct('patient_id')->count('patient_id');

        // Admissions today
        $admissions = \App\Models\AdmissionRequest::whereDate('created_at', $today)->count();

        // Bookings today (appointments scheduled for today)
        $bookings = \App\Models\ProductOrServiceRequest::whereDate('created_at', $today)
            ->whereHas('service', function ($q) {
                $q->where('category_id', 1); // Assuming category_id 1 is for appointments
            })->count();

        // Total patients
        $totalPatients = \App\Models\patient::count();

        // Total admissions (all time)
        $totalAdmissions = \App\Models\AdmissionRequest::count();

        // Total bookings (all time)
        $totalBookings = \App\Models\ProductOrServiceRequest::whereHas('service', function ($q) {
            $q->where('category_id', 1);
        })->count();

        // Total encounters (all time)
        $totalEncounters = \App\Models\Encounter::count();

        return response()->json([
            'new_patients' => $newPatients,
            'returning_patients' => $returningPatients,
            'admissions' => $admissions,
            'bookings' => $bookings,
            'total_patients' => $totalPatients,
            'total_admissions' => $totalAdmissions,
            'total_bookings' => $totalBookings,
            'total_encounters' => $totalEncounters,
        ]);
    }

    /**
     * Biller/Accounts Dashboard Stats
     */
    public function billerStats()
    {
        $today = now()->toDateString();

        // Today's revenue
        $todayRevenue = DB::table('payments')
            ->whereDate('created_at', $today)
            ->sum('total');

        // Payment requests today
        $paymentRequests = \App\Models\ProductOrServiceRequest::whereDate('created_at', $today)->count();

        // My payments today
        $myPayments = DB::table('payments')
            ->whereDate('created_at', $today)
            ->where('user_id', auth()->id())
            ->count();

        // Consultations today
        $consultationsToday = \App\Models\Encounter::whereDate('created_at', $today)->count();

        // Total revenue
        $totalRevenue = DB::table('payments')->sum('total');

        // Pending payments (requests without payment)
        $pendingPayments = \App\Models\ProductOrServiceRequest::whereNull('payment_id')->count();

        return response()->json([
            'today_revenue' => number_format($todayRevenue, 2),
            'payment_requests' => $paymentRequests,
            'my_payments' => $myPayments,
            'consultations' => $consultationsToday,
            'total_revenue' => number_format($totalRevenue, 2),
            'pending_payments' => $pendingPayments,
        ]);
    }

    /**
     * Admin Dashboard Stats
     */
    public function adminStats()
    {
        // Total staff
        $totalStaff = DB::table('users')->where('is_admin', '!=', 19)->count();

        // Total patients
        $totalPatients = \App\Models\patient::count();

        // Total clinics
        $totalClinics = DB::table('clinics')->count();

        // Total revenue
        $totalRevenue = DB::table('payments')->sum('total');

        // Encounters today (as a proxy for active users)
        $encountersToday = \App\Models\Encounter::whereDate('created_at', now()->toDateString())->count();

        // New registrations this month
        $newThisMonth = \App\Models\patient::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return response()->json([
            'total_staff' => $totalStaff,
            'total_patients' => $totalPatients,
            'total_clinics' => $totalClinics,
            'total_revenue' => number_format($totalRevenue, 2),
            'encounters_today' => $encountersToday,
            'new_this_month' => $newThisMonth,
        ]);
    }

    /**
     * Pharmacy/Store Dashboard Stats
     */
    public function pharmacyStats()
    {
        $today = now()->toDateString();

        // Queue today
        $queueToday = \App\Models\ProductRequest::whereDate('created_at', $today)->count();

        // Dispensed today
        $dispensedToday = \App\Models\ProductRequest::whereDate('created_at', $today)
            ->where('status', 2) // Dispensed status
            ->count();

        // Total products
        $totalProducts = DB::table('products')->count();

        // Low stock alerts (products with stock below reorder alert level)
        $lowStock = DB::table('products as p')
            ->join('stocks as s', 'p.id', '=', 's.product_id')
            ->whereRaw('s.current_quantity <= CAST(p.reorder_alert AS SIGNED)')
            ->where('p.reorder_alert', '>', 0)
            ->count();

        // Pending requests
        $pendingRequests = \App\Models\ProductRequest::where('status', 0)->count();

        // Today's sales (product-related payments)
        $todaySales = DB::table('product_or_service_requests as posr')
            ->join('payments', 'posr.payment_id', '=', 'payments.id')
            ->whereNotNull('posr.product_id')
            ->whereDate('payments.created_at', $today)
            ->sum('posr.payable_amount');

        return response()->json([
            'queue_today' => $queueToday,
            'dispensed_today' => $dispensedToday,
            'total_products' => $totalProducts,
            'low_stock' => $lowStock,
            'pending_requests' => $pendingRequests,
            'today_sales' => number_format($todaySales, 2),
        ]);
    }

    /**
     * Nursing Dashboard Stats
     */
    public function nursingStats()
    {
        $today = now()->toDateString();

        // Vitals queue (pending vital sign requests)
        $vitalsQueue = DB::table('vital_signs')
            ->whereDate('created_at', $today)
            ->where('status', 0)
            ->count();

        // Bed requests (pending admission requests)
        $bedRequests = \App\Models\AdmissionRequest::where('status', 0)->count();

        // Medication due (schedules not yet administered)
        $medicationDue = DB::table('medication_schedules as ms')
            ->leftJoin('medication_administrations as ma', 'ms.id', '=', 'ma.schedule_id')
            ->whereDate('ms.scheduled_time', $today)
            ->whereNull('ma.id')
            ->count();

        // Admitted patients
        $admittedPatients = \App\Models\AdmissionRequest::where('status', 1)->count();

        // Vitals taken today
        $vitalsTakenToday = DB::table('vital_signs')
            ->whereDate('created_at', $today)
            ->where('status', 1)
            ->count();

        // Injections today
        $injectionsToday = DB::table('injection_administrations')
            ->whereDate('created_at', $today)
            ->count();

        return response()->json([
            'vitals_queue' => $vitalsQueue,
            'bed_requests' => $bedRequests,
            'medication_due' => $medicationDue,
            'admitted_patients' => $admittedPatients,
            'vitals_taken' => $vitalsTakenToday,
            'injections_today' => $injectionsToday,
        ]);
    }

    /**
     * Lab/Imaging Dashboard Stats
     */
    public function labStats()
    {
        $today = now()->toDateString();

        // Lab queue (lab requests not yet completed)
        $labQueue = \App\Models\LabServiceRequest::whereDate('created_at', $today)
            ->whereHas('service', function ($q) {
                $q->where('category_id', appsettings('investigation_category_id', 2));
            })
            ->where('status', '<', 4)
            ->count();

        // Imaging queue
        $imagingQueue = DB::table('imaging_service_requests')
            ->whereDate('created_at', $today)
            ->where('status', '<', 4)
            ->count();

        // Completed today (lab + imaging)
        $completedLab = \App\Models\LabServiceRequest::whereDate('updated_at', $today)
            ->where('status', 4)
            ->count();
        $completedImaging = DB::table('imaging_service_requests')
            ->whereDate('updated_at', $today)
            ->where('status', 4)
            ->count();
        $completedToday = $completedLab + $completedImaging;

        // Total services
        $totalServices = DB::table('services')
            ->whereIn('category_id', [appsettings('investigation_category_id', 2), appsettings('imaging_category_id', 6)])
            ->count();

        // Pending results (sample collected, awaiting results)
        $pendingResults = \App\Models\LabServiceRequest::where('status', 3)->count();

        return response()->json([
            'lab_queue' => $labQueue,
            'imaging_queue' => $imagingQueue,
            'completed_today' => $completedToday,
            'total_services' => $totalServices,
            'pending_results' => $pendingResults,
        ]);
    }

    /**
     * Doctor Dashboard Stats
     */
    public function doctorStats()
    {
        $today = now()->toDateString();
        $userId = auth()->id();

        // Get the staff record for current user (doctor_queues uses staff_id)
        $staff = \App\Models\Staff::where('user_id', $userId)->first();
        $staffId = $staff ? $staff->id : 0;

        // Consultations today (by this doctor)
        $consultationsToday = \App\Models\Encounter::whereDate('created_at', $today)
            ->where('doctor_id', $userId)
            ->count();

        // Ward rounds (admitted patients under this doctor)
        $wardRounds = \App\Models\AdmissionRequest::where('status', 1)
            ->whereHas('encounter', function ($q) use ($userId) {
                $q->where('doctor_id', $userId);
            })
            ->count();

        // My patients (unique patients seen)
        $myPatients = \App\Models\Encounter::where('doctor_id', $userId)
            ->distinct('patient_id')
            ->count('patient_id');

        // Appointments today (using staff_id from doctor_queues)
        $appointmentsToday = DB::table('doctor_queues')
            ->whereDate('created_at', $today)
            ->where('staff_id', $staffId)
            ->count();

        // Queue waiting
        $queueWaiting = DB::table('doctor_queues')
            ->whereDate('created_at', $today)
            ->where('staff_id', $staffId)
            ->where('status', 1)
            ->count();

        // Completed today (encounters with notes filled)
        $completedToday = \App\Models\Encounter::whereDate('created_at', $today)
            ->where('doctor_id', $userId)
            ->whereNotNull('notes')
            ->count();

        return response()->json([
            'consultations_today' => $consultationsToday,
            'ward_rounds' => $wardRounds,
            'my_patients' => $myPatients,
            'appointments_today' => $appointmentsToday,
            'queue_waiting' => $queueWaiting,
            'completed_today' => $completedToday,
        ]);
    }

    /**
     * HMO Executive Dashboard Stats
     */
    public function hmoStats()
    {
        $today = now()->toDateString();

        // HMO patients
        $hmoPatients = \App\Models\patient::whereNotNull('hmo_id')->count();

        // Pending claims (HMO requests not yet validated)
        $pendingClaims = DB::table('product_or_service_requests as posr')
            ->join('patients as p', 'posr.user_id', '=', 'p.id')
            ->whereNotNull('p.hmo_id')
            ->whereNull('posr.validation_status')
            ->count();

        // Approved claims this month (validated HMO requests)
        $approvedClaims = DB::table('product_or_service_requests as posr')
            ->join('patients as p', 'posr.user_id', '=', 'p.id')
            ->whereNotNull('p.hmo_id')
            ->where('posr.validation_status', 'approved')
            ->whereMonth('posr.validated_at', now()->month)
            ->count();

        // Total HMOs
        $totalHmos = DB::table('hmos')->count();

        // Claims value this month (HMO patient requests)
        $claimsValue = DB::table('product_or_service_requests as posr')
            ->join('patients as p', 'posr.user_id', '=', 'p.id')
            ->whereNotNull('p.hmo_id')
            ->whereMonth('posr.created_at', now()->month)
            ->sum('posr.claims_amount');

        // New enrollees this month
        $newEnrollees = \App\Models\patient::whereNotNull('hmo_id')
            ->whereMonth('created_at', now()->month)
            ->count();

        return response()->json([
            'hmo_patients' => $hmoPatients,
            'pending_claims' => $pendingClaims,
            'approved_claims' => $approvedClaims,
            'total_hmos' => $totalHmos,
            'claims_value' => number_format($claimsValue, 2),
            'new_enrollees' => $newEnrollees,
        ]);
    }

    /**
     * Chart: Revenue over time
     */
    public function chartRevenueOverTime(Request $request)
    {
        $startDate = $request->input('start');
        $endDate = $request->input('end');

        $data = DB::table('payments')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    /**
     * Chart: Patient registrations over time
     */
    public function chartPatientRegistrations(Request $request)
    {
        $startDate = $request->input('start');
        $endDate = $request->input('end');

        $data = DB::table('patients')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }
}

