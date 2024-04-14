<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdmissionRequest;
use App\Models\ApplicationStatu;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\LabServiceRequest;
use App\Models\patient;
use App\Models\ProductOrServiceRequest;
use App\Models\service;
use App\Models\Staff;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataEndpoint extends Controller
{
    public function getFacilitySetting()
    {
        try {
            $a = ApplicationStatu::first();
            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Settings: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Settings'], 500);
        }
    }

    public function getFullStats()
    {
        try {
            $patients  = patient::count();
            $bookings = DoctorQueue::count();
            $consultations = Encounter::count();
            $hmos = Hmo::count();
            $staff = Staff::count();
            $nurses = User::where('is_admin', 22)->count();
            $doctors = User::where('is_admin', 21)->count();
            $admissions = AdmissionRequest::where('billed_by', '!=', null)->count();
            $male_staff = Staff::where('gender', 'Male')->count();
            $female_staff = Staff::where('gender', 'Female')->count();
            $other_gender_staff = Staff::where('gender', 'Others')->count();
            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => [
                'all_patients' => $patients,
                'all_bookings' => $bookings,
                'all_consultations' => $consultations,
                'all_hmos' => $hmos,
                'all_staff' => $staff,
                'all_nurses' => $nurses,
                'all_doctors' => $doctors,
                'all_admissions' => $admissions,
                'all_male_staff' => $male_staff,
                'all_female_staff' => $female_staff,
                'all_other_gender_staff' => $other_gender_staff,
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Statistics: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Statistics'], 500);
        }
    }

    public function getAgeDistribution()
    {
        try {
            // Retrieve all patients from the database
            $patients = patient::all();

            // Initialize an array to store the count of patients in each age group
            $ageCounts = [
                "0-2" => 0,
                "3-5" => 0,
                "6-12" => 0,
                "13-18" => 0,
                "18-35" => 0,
                "36-55" => 0,
                "55-64" => 0,
                "65+" => 0
            ];

            // Calculate age distribution
            foreach ($patients as $patient) {
                // Calculate age
                $dob = $patient->dob;
                $dob = date_create($dob);
                if ($dob instanceof \DateTime) {
                    $age = date_diff($dob, date_create('today'))->y;

                    // Determine age group
                    $ageGroup = $this->getAgeGroup($age);

                    // Increment count for the corresponding age group
                    if (array_key_exists($ageGroup, $ageCounts)) {
                        $ageCounts[$ageGroup]++;
                    }
                }
            }

            // Create JSON object
            $jsonObject = [
                "age_groups" => array_keys($ageCounts),
                "counts" => array_values($ageCounts)
            ];

            // Return JSON response
            return response()->json(['status' => true, 'data' => $jsonObject]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Statistics: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Statistics'], 500);
        }
    }

    // Function to determine age group
    private function getAgeGroup($age)
    {
        if ($age >= 0 && $age <= 2) {
            return "0-2";
        } elseif ($age >= 3 && $age <= 5) {
            return "3-5";
        } elseif ($age >= 6 && $age <= 12) {
            return "6-12";
        } elseif ($age >= 13 && $age <= 18) {
            return "13-18";
        } elseif ($age >= 18 && $age <= 35) {
            return "18-35";
        } elseif ($age >= 36 && $age <= 55) {
            return "36-55";
        } elseif ($age >= 55 && $age <= 64) {
            return "55-64";
        } else {
            return "65+";
        }
    }


    public function encountersPerMonth($year)
    {
        try {
            // Initialize an array to store the count of encounters per month
            $encountersPerMonth = [];

            // Loop through each month of the year
            for ($month = 1; $month <= 12; $month++) {
                // Count the number of encounters for the current month and year
                $encounterCount = Encounter::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count();

                // Store the encounter count for the current month
                $encountersPerMonth[$month] = $encounterCount;
            }

            // Return the encounter counts per month
            return response()->json(['status' => true, 'data' => $encountersPerMonth]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Statistics: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Statistics'], 500);
        }
    }

    public function hospitalizationsPerMonth($year)
    {
        try {
            // Initialize an array to store the count of hospitalizationssPerMonth
            $hospitalizationssPerMonth = [];

            // Loop through each month of the year
            for ($month = 1; $month <= 12; $month++) {
                // Count the number of encounters for the current month and year
                $hospitalizationsCount = AdmissionRequest::whereYear('created_at', $year)
                    ->where('bed_id', '!=', null)
                    ->whereMonth('created_at', $month)
                    ->count();

                $dischargeCount = AdmissionRequest::whereYear('created_at', $year)
                    ->where('bed_id', '!=', null)
                    // ->whereMonth('created_at', $month)
                    ->whereMonth('discharge_date', $month)
                    ->count();

                // Store the hospitalizations count for the current month
                $hospitalizationssPerMonth[$month]['admitted'] = $hospitalizationsCount;
                $hospitalizationssPerMonth[$month]['discharged'] = $dischargeCount;
            }

            // Return the hospitalizations counts per month
            return response()->json(['status' => true, 'data' => $hospitalizationssPerMonth]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Statistics: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Statistics'], 500);
        }
    }

    public function investigationsPerMonth($year)
    {
        try {
            // Initialize an array to store the count of investigationsPerMonth
            $investigationsPerMonth = [];

            $investigation_services = service::where('category_id', env('INVESTGATION_CATEGORY_ID'))->get();

            // Loop through each month of the year
            for ($month = 1; $month <= 12; $month++) {
                foreach ($investigation_services as $service) {
                    // Count the number of occurance for the current month and year
                    $investigationssCount = LabServiceRequest::whereYear('created_at', $year)
                        ->where('service_id', $service->id)
                        ->whereMonth('created_at', $month)
                        ->count();
                    // Store the hospitalizations count for the current month
                    $investigationsPerMonth[$month][$service->service_name] = $investigationssCount;
                }
            }

            // Return the investigationsPerMonth counts
            return response()->json(['status' => true, 'data' => $investigationsPerMonth]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Statistics: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Statistics'], 500);
        }
    }

    public function incomePerMonth($year)
    {
        try {
            // Initialize an array to store the count of encounters per month
            $incomePerMonth = [
                1 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                2 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                3 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                4 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                5 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                6 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                7 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                8 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                9 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                10 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                11 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ],
                12 => [
                    'consultations' => 0, 'investigations' => 0, 'admissions' => 0, 'nursing_services' => 0, 'misc_services' => 0
                ]
            ];

            $bed_services = service::where('category_id', env('BED_SERVICE_CATGORY_ID'))->get()->pluck('id')->toArray();
            $inves_services = service::where('category_id', env('INVESTGATION_CATEGORY_ID'))->get()->pluck('id')->toArray();
            $consult_services = service::where('category_id', env('CONSULTATION_CATEGORY_ID'))->get()->pluck('id')->toArray();
            $nursing_services = service::where('category_id', env('NUSRING_SERVICE_CATEGORY'))->get()->pluck('id')->toArray();
            $misc_services = service::where('category_id', env('MISC_SERVICE_CATEGORY_ID'))->get()->pluck('id')->toArray();

            // Fetch prices for the provided service IDs
            $pricesPerMonth = ProductOrServiceRequest::whereIn('service_id', $inves_services)
                ->whereYear('product_or_service_requests.created_at', $year)
                ->join('payments', 'product_or_service_requests.payment_id', '=', 'payments.id')
                ->select(
                    DB::raw('MONTH(payments.created_at) as month'),
                    DB::raw('YEAR(payments.created_at) as year'),
                    DB::raw('SUM(payments.total) as total_price')
                )
                ->groupBy(DB::raw('MONTH(payments.created_at)'), DB::raw('YEAR(payments.created_at)'))
                ->get();

            // Format the prices per month
            foreach ($pricesPerMonth as $price) {
                $incomePerMonth[$price->month]['investigations'] = $price->total_price;
            }

            // Fetch prices for the provided service IDs
            $pricesPerMonth = ProductOrServiceRequest::whereIn('service_id', $bed_services)
                ->whereYear('product_or_service_requests.created_at', $year)
                ->join('payments', 'product_or_service_requests.payment_id', '=', 'payments.id')
                ->select(
                    DB::raw('MONTH(payments.created_at) as month'),
                    DB::raw('YEAR(payments.created_at) as year'),
                    DB::raw('SUM(payments.total) as total_price')
                )
                ->groupBy(DB::raw('MONTH(payments.created_at)'), DB::raw('YEAR(payments.created_at)'))
                ->get();

            // Format the prices per month
            foreach ($pricesPerMonth as $price) {
                $incomePerMonth[$price->month]['admissions'] = $price->total_price;
            }

            // Fetch prices for the provided service IDs
            $pricesPerMonth = ProductOrServiceRequest::whereIn('service_id', $nursing_services)
                ->whereYear('product_or_service_requests.created_at', $year)
                ->join('payments', 'product_or_service_requests.payment_id', '=', 'payments.id')
                ->select(
                    DB::raw('MONTH(payments.created_at) as month'),
                    DB::raw('YEAR(payments.created_at) as year'),
                    DB::raw('SUM(payments.total) as total_price')
                )
                ->groupBy(DB::raw('MONTH(payments.created_at)'), DB::raw('YEAR(payments.created_at)'))
                ->get();

            // Format the prices per month
            foreach ($pricesPerMonth as $price) {
                $incomePerMonth[$price->month]['nursing_services'] = $price->total_price;
            }

            // Fetch prices for the provided service IDs
            $pricesPerMonth = ProductOrServiceRequest::whereIn('service_id', $misc_services)
                ->whereYear('product_or_service_requests.created_at', $year)
                ->join('payments', 'product_or_service_requests.payment_id', '=', 'payments.id')
                ->select(
                    DB::raw('MONTH(payments.created_at) as month'),
                    DB::raw('YEAR(payments.created_at) as year'),
                    DB::raw('SUM(payments.total) as total_price')
                )
                ->groupBy(DB::raw('MONTH(payments.created_at)'), DB::raw('YEAR(payments.created_at)'))
                ->get();

            // Format the prices per month
            foreach ($pricesPerMonth as $price) {
                $incomePerMonth[$price->month]['misc_services'] = $price->total_price;
            }

            // Fetch prices for the provided service IDs
            $pricesPerMonth = ProductOrServiceRequest::whereIn('service_id', $consult_services)
                ->whereYear('product_or_service_requests.created_at', $year)
                ->join('payments', 'product_or_service_requests.payment_id', '=', 'payments.id')
                ->select(
                    DB::raw('MONTH(payments.created_at) as month'),
                    DB::raw('YEAR(payments.created_at) as year'),
                    DB::raw('SUM(payments.total) as total_price')
                )
                ->groupBy(DB::raw('MONTH(payments.created_at)'), DB::raw('YEAR(payments.created_at)'))
                ->get();

            // Format the prices per month
            foreach ($pricesPerMonth as $price) {
                $incomePerMonth[$price->month]['consultations'] = $price->total_price;
            }

            // Return the prices per month
            return response()->json(['status' => true, 'data' => $incomePerMonth]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Statistics: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Statistics'], 500);
        }
    }

    public function getAllPatients(Request $request)
    {
        try {

            $query = patient::with(['user', 'hmo', 'account']);
            // Define pagination parameters
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Apply filtering
            if ($request->has('filter_column') && $request->has('filter_value')) {
                $filterColumn = $request->input('filter_column');
                $filterValue = $request->input('filter_value');

                // Validate if the filter column exists in the model
                if (in_array($filterColumn, (new User())->getFillable())) {
                    $query->where($filterColumn, $filterValue);
                }
            }

            // Apply search
            if ($request->has('search_keyword')) {
                $searchKeyword = $request->input('search_keyword');
                $query->whereHas('user', function ($query) use ($searchKeyword) {
                    $query->where(function ($query) use ($searchKeyword) {
                        $query->where('firstname', 'like', "%$searchKeyword%")
                            ->orWhere('surname', 'like', "%$searchKeyword%")
                            ->orWhere('othername', 'like', "%$searchKeyword%")
                            ->orWhere('email', 'like', "%$searchKeyword%");
                    });
                });
            }

            // Apply sorting
            if ($request->has('sort_by')) {
                $sortField = $request->input('sort_by');
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }

            // Fetch products with applied filters, search, and sorting
            $a = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a->items(), 'metadata' => [
                'current_page' => $a->currentPage(),
                'per_page' => $a->perPage(),
                'total' => $a->total(),
                'last_page' => $a->lastPage(),
                'next_page_url' => $a->nextPageUrl(),
                'previous_page_url' => $a->previousPageUrl()
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Patients: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Patients'], 500);
        }
    }

    public function getAllBookings(Request $request)
    {
        try {

            $query = DoctorQueue::with(['patient', 'patient.user', 'clinic', 'doctor', 'receptionist', 'request_entry']);
            // Define pagination parameters
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Apply filtering
            if ($request->has('filter_column') && $request->has('filter_value')) {
                $filterColumn = $request->input('filter_column');
                $filterValue = $request->input('filter_value');

                // Validate if the filter column exists in the model
                if (in_array($filterColumn, (new DoctorQueue())->getFillable())) {
                    $query->where($filterColumn, $filterValue);
                }
            }

            // Apply search
            if ($request->has('search_keyword')) {
                $searchKeyword = $request->input('search_keyword');
                $query->whereHas('patient.user', function ($query) use ($searchKeyword) {
                    $query->where(function ($query) use ($searchKeyword) {
                        $query->where('firstname', 'like', "%$searchKeyword%")
                            ->orWhere('surname', 'like', "%$searchKeyword%")
                            ->orWhere('othername', 'like', "%$searchKeyword%")
                            ->orWhere('email', 'like', "%$searchKeyword%");
                    });
                });
            }

            // Apply sorting
            if ($request->has('sort_by')) {
                $sortField = $request->input('sort_by');
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }

            // Fetch products with applied filters, search, and sorting
            $a = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a->items(), 'metadata' => [
                'current_page' => $a->currentPage(),
                'per_page' => $a->perPage(),
                'total' => $a->total(),
                'last_page' => $a->lastPage(),
                'next_page_url' => $a->nextPageUrl(),
                'previous_page_url' => $a->previousPageUrl()
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Bookings: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Bookings'], 500);
        }
    }

    public function getAllConsultations(Request $request)
    {
        try {

            $query = Encounter::with(['patient', 'patient.user', 'labRequests', 'doctor', 'service', 'productOrServiceRequest', 'admission_request']);
            // Define pagination parameters
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Apply filtering
            if ($request->has('filter_column') && $request->has('filter_value')) {
                $filterColumn = $request->input('filter_column');
                $filterValue = $request->input('filter_value');

                // Validate if the filter column exists in the model
                if (in_array($filterColumn, (new Encounter())->getFillable())) {
                    $query->where($filterColumn, $filterValue);
                }
            }

            // Apply search
            if ($request->has('search_keyword')) {
                $searchKeyword = $request->input('search_keyword');
                $query->whereHas('patient.user', function ($query) use ($searchKeyword) {
                    $query->where(function ($query) use ($searchKeyword) {
                        $query->where('firstname', 'like', "%$searchKeyword%")
                            ->orWhere('surname', 'like', "%$searchKeyword%")
                            ->orWhere('othername', 'like', "%$searchKeyword%")
                            ->orWhere('email', 'like', "%$searchKeyword%");
                    });
                });
            }

            // Apply sorting
            if ($request->has('sort_by')) {
                $sortField = $request->input('sort_by');
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }

            // Fetch products with applied filters, search, and sorting
            $a = $query->paginate($perPage, ['*'], 'page', $page);


            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a->items(), 'metadata' => [
                'current_page' => $a->currentPage(),
                'per_page' => $a->perPage(),
                'total' => $a->total(),
                'last_page' => $a->lastPage(),
                'next_page_url' => $a->nextPageUrl(),
                'previous_page_url' => $a->previousPageUrl()
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Consultations: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Consultations'], 500);
        }
    }

    public function getAllStaff(Request $request)
    {
        try {

            $query = Staff::with(['user', 'clinic', 'specialization']);
            // Define pagination parameters
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Apply filtering
            if ($request->has('filter_column') && $request->has('filter_value')) {
                $filterColumn = $request->input('filter_column');
                $filterValue = $request->input('filter_value');

                // Validate if the filter column exists in the model
                if (in_array($filterColumn, (new Staff())->getFillable())) {
                    $query->where($filterColumn, $filterValue);
                }
            }

            // Apply search
            if ($request->has('search_keyword')) {
                $searchKeyword = $request->input('search_keyword');
                $query->whereHas('user', function ($query) use ($searchKeyword) {
                    $query->where(function ($query) use ($searchKeyword) {
                        $query->where('firstname', 'like', "%$searchKeyword%")
                            ->orWhere('surname', 'like', "%$searchKeyword%")
                            ->orWhere('othername', 'like', "%$searchKeyword%")
                            ->orWhere('email', 'like', "%$searchKeyword%");
                    });
                });
            }

            // Apply sorting
            if ($request->has('sort_by')) {
                $sortField = $request->input('sort_by');
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }

            // Fetch products with applied filters, search, and sorting
            $a = $query->paginate($perPage, ['*'], 'page', $page);


            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a->items(), 'metadata' => [
                'current_page' => $a->currentPage(),
                'per_page' => $a->perPage(),
                'total' => $a->total(),
                'last_page' => $a->lastPage(),
                'next_page_url' => $a->nextPageUrl(),
                'previous_page_url' => $a->previousPageUrl()
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Staff: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Staff'], 500);
        }
    }

    public function getAllNurses(Request $request)
    {
        try {

            $query = Staff::with(['user']);
            // Define pagination parameters
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Apply account type filter
            $query->whereHas('user', function ($query) {
                $query->where(function ($query) {
                    $query->where('is_admin', "22"); //TODO:Note that 19=patient, 20=reception, 21=doctor, 22=nurse, 23=Pahrm, 24=Lab, 25=Others
                });
            });

            // Apply filtering
            if ($request->has('filter_column') && $request->has('filter_value')) {
                $filterColumn = $request->input('filter_column');
                $filterValue = $request->input('filter_value');

                // Validate if the filter column exists in the model
                if (in_array($filterColumn, (new Staff())->getFillable())) {
                    $query->where($filterColumn, $filterValue);
                }
            }

            // Apply search
            if ($request->has('search_keyword')) {
                $searchKeyword = $request->input('search_keyword');
                $query->whereHas('user', function ($query) use ($searchKeyword) {
                    $query->where(function ($query) use ($searchKeyword) {
                        $query->where('firstname', 'like', "%$searchKeyword%")
                            ->orWhere('surname', 'like', "%$searchKeyword%")
                            ->orWhere('othername', 'like', "%$searchKeyword%")
                            ->orWhere('email', 'like', "%$searchKeyword%");
                    });
                });
            }

            // Apply sorting
            if ($request->has('sort_by')) {
                $sortField = $request->input('sort_by');
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }

            // Fetch products with applied filters, search, and sorting
            $a = $query->paginate($perPage, ['*'], 'page', $page);


            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a->items(), 'metadata' => [
                'current_page' => $a->currentPage(),
                'per_page' => $a->perPage(),
                'total' => $a->total(),
                'last_page' => $a->lastPage(),
                'next_page_url' => $a->nextPageUrl(),
                'previous_page_url' => $a->previousPageUrl()
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Nurses: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Nurses'], 500);
        }
    }

    public function getAllDoctors(Request $request)
    {
        try {

            $query = Staff::with(['user']);
            // Define pagination parameters
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Apply account type filter
            $query->whereHas('user', function ($query) {
                $query->where(function ($query) {
                    $query->where('is_admin', "21"); //TODO:Note that 19=patient, 20=reception, 21=doctor, 22=nurse, 23=Pahrm, 24=Lab, 25=Others
                });
            });

            // Apply filtering
            if ($request->has('filter_column') && $request->has('filter_value')) {
                $filterColumn = $request->input('filter_column');
                $filterValue = $request->input('filter_value');

                // Validate if the filter column exists in the model
                if (in_array($filterColumn, (new Staff())->getFillable())) {
                    $query->where($filterColumn, $filterValue);
                }
            }

            // Apply search
            if ($request->has('search_keyword')) {
                $searchKeyword = $request->input('search_keyword');
                $query->whereHas('user', function ($query) use ($searchKeyword) {
                    $query->where(function ($query) use ($searchKeyword) {
                        $query->where('firstname', 'like', "%$searchKeyword%")
                            ->orWhere('surname', 'like', "%$searchKeyword%")
                            ->orWhere('othername', 'like', "%$searchKeyword%")
                            ->orWhere('email', 'like', "%$searchKeyword%");
                    });
                });
            }

            // Apply sorting
            if ($request->has('sort_by')) {
                $sortField = $request->input('sort_by');
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }

            // Fetch products with applied filters, search, and sorting
            $a = $query->paginate($perPage, ['*'], 'page', $page);


            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a->items(), 'metadata' => [
                'current_page' => $a->currentPage(),
                'per_page' => $a->perPage(),
                'total' => $a->total(),
                'last_page' => $a->lastPage(),
                'next_page_url' => $a->nextPageUrl(),
                'previous_page_url' => $a->previousPageUrl()
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Nurses: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Nurses'], 500);
        }
    }

    public function getAllAdmissions(Request $request)
    {
        try {

            $query = AdmissionRequest::with(['patient', 'patient.user', 'doctor', 'bed']);
            // Define pagination parameters
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);



            // Apply filtering
            if ($request->has('filter_column') && $request->has('filter_value')) {
                $filterColumn = $request->input('filter_column');
                $filterValue = $request->input('filter_value');

                // Validate if the filter column exists in the model
                if (in_array($filterColumn, (new Staff())->getFillable())) {
                    $query->where($filterColumn, $filterValue);
                }
            }

            // Apply search
            if ($request->has('search_keyword')) {
                $searchKeyword = $request->input('search_keyword');
                $query->whereHas('patient.user', function ($query) use ($searchKeyword) {
                    $query->where(function ($query) use ($searchKeyword) {
                        $query->where('firstname', 'like', "%$searchKeyword%")
                            ->orWhere('surname', 'like', "%$searchKeyword%")
                            ->orWhere('othername', 'like', "%$searchKeyword%")
                            ->orWhere('email', 'like', "%$searchKeyword%");
                    });
                });
            }

            // Apply sorting
            if ($request->has('sort_by')) {
                $sortField = $request->input('sort_by');
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }

            // Fetch products with applied filters, search, and sorting
            $a = $query->paginate($perPage, ['*'], 'page', $page);


            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a->items(), 'metadata' => [
                'current_page' => $a->currentPage(),
                'per_page' => $a->perPage(),
                'total' => $a->total(),
                'last_page' => $a->lastPage(),
                'next_page_url' => $a->nextPageUrl(),
                'previous_page_url' => $a->previousPageUrl()
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Admissions: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility Admissions'], 500);
        }
    }

    public function getAllHMOs(Request $request)
    {
        try {

            $query = Hmo::query();
            // Define pagination parameters
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Apply filtering
            if ($request->has('filter_column') && $request->has('filter_value')) {
                $filterColumn = $request->input('filter_column');
                $filterValue = $request->input('filter_value');

                // Validate if the filter column exists in the model
                if (in_array($filterColumn, (new Hmo())->getFillable())) {
                    $query->where($filterColumn, $filterValue);
                }
            }

            // Apply search
            if ($request->has('search_keyword')) {
                $searchKeyword = $request->input('search_keyword');
                $query->where(function ($query) use ($searchKeyword) {
                    $query->where('name', 'like', "%$searchKeyword%");
                });
            }

            // Apply sorting
            if ($request->has('sort_by')) {
                $sortField = $request->input('sort_by');
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }

            // Fetch products with applied filters, search, and sorting
            $a = $query->paginate($perPage, ['*'], 'page', $page);


            return response()->json(['status' => true, 'message' => 'Successfully retrived data', 'data' => $a->items(), 'metadata' => [
                'current_page' => $a->currentPage(),
                'per_page' => $a->perPage(),
                'total' => $a->total(),
                'last_page' => $a->lastPage(),
                'next_page_url' => $a->nextPageUrl(),
                'previous_page_url' => $a->previousPageUrl()
            ]]);
        } catch (Exception $e) {
            Log::error('Failed to get Facility Consultations: ' . $e->getMessage(), [$e]);
            return response()->json(['status' => false, 'message' => 'Failed to get Facility HMOs'], 500);
        }
    }
}
