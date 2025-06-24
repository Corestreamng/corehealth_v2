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
}
