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
    public function fetchClinicAppointments()
    {
        $data = DB::table('product_or_service_requests as p')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->select(DB::raw('DATE(p.created_at) as date'), DB::raw('COUNT(*) as total'))
            ->where('s.category_id', 1)
            ->groupBy(DB::raw('DATE(p.created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }
    public function chartAppointmentsOverTime()
    {
        $data = DB::table('product_or_service_requests as p')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->where('s.category_id', 1)
            ->selectRaw('DATE(p.created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(p.created_at)')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function chartAppointmentsByClinic()
    {
        $data = DB::table('product_or_service_requests as p')
            ->leftJoin('doctor_queues as dq', 'dq.request_entry_id', '=', 'p.id')
            ->leftJoin('clinics as c', 'dq.clinic_id', '=', 'c.id')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->where('s.category_id', 1)
            ->select('c.name as clinic', DB::raw('count(*) as total'))
            ->groupBy('c.name')
            ->get();

        return response()->json($data);
    }

    public function chartTopClinicServices()
    {
        $data = DB::table('product_or_service_requests as p')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->where('s.category_id', 1)
            ->select('s.service_name as service', DB::raw('count(*) as total'))
            ->groupBy('s.service_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json($data);
    }

    public function chartQueueStatus()
    {
        $data = DB::table('doctor_queues as dq')
            ->leftJoin('product_or_service_requests as p', 'dq.request_entry_id', '=', 'p.id')
            ->leftJoin('services as s', 'p.service_id', '=', 's.id')
            ->where('s.category_id', 1)
            ->select(
                DB::raw('CASE dq.status
            WHEN 1 THEN \"Queued\"
            WHEN 2 THEN \"Attended\"
            WHEN 3 THEN \"Cancelled\"
            ELSE \"Unknown\" END AS status'),
                DB::raw('count(*) as total')
            )
            ->groupBy('dq.status')
            ->get();

        return response()->json($data);
    }
}
