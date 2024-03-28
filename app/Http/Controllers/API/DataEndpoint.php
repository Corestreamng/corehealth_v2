<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdmissionRequest;
use App\Models\ApplicationStatu;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\patient;
use App\Models\Staff;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
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
