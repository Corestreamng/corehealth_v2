<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductOrServiceRequest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\MedicationAdministration;
use App\Models\MedicationSchedule;
use App\Models\MedicationHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MedicationChartController extends Controller
{
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
    public function index($patientId, Request $request)
    {
        $patient = Patient::findOrFail($patientId);
        $userId = $patient->user_id ?? $patient->user->id ?? null;

        // No date filtering for medications anymore - removed all date filtering

        // Get all prescriptions without date filtering
        $prescriptions = ProductOrServiceRequest::where('user_id', $userId)
            ->with(['product.category', 'product.price', 'product.stock'])
            ->whereNotNull('product_id')
            ->latest('created_at')
            ->get();

        // Get all administrations with user information (no date filtering)
        $administrations = MedicationAdministration::where('patient_id', $patientId)
            ->with(['administeredBy', 'editedBy', 'deletedBy'])
            ->get();

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

        // Return data without date range information
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
        $patient = Patient::findOrFail($patientId);
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
            'history' => $history,
            'adminHistory' => $adminHistory,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    public function storeTiming(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'product_or_service_request_id' => 'required|exists:product_or_service_requests,id',
            'time' => 'required',
            'dose' => 'required|string',
            'route' => 'required|string',
            'repeat_type' => 'nullable|string|in:daily,specific,selected,once',
            'repeat_daily' => 'nullable|boolean',
            'selected_days' => 'nullable|array',
            'duration_days' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'note' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $patientId = $data['patient_id'];
        $medicationId = $data['product_or_service_request_id'];
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
                    $scheduledDateTime = $currentDate->format('Y-m-d') . ' ' . $time;

                    // Create schedule
                    $schedule = new MedicationSchedule();
                    $schedule->patient_id = $patientId;
                    $schedule->product_or_service_request_id = $medicationId;
                    $schedule->scheduled_time = $scheduledDateTime;
                    $schedule->dose = $dose;
                    $schedule->route = $route;
                    $schedule->created_by = Auth::id();
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
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|exists:medication_schedules,id',
            'administered_at' => 'required|date',
            'administered_dose' => 'required|string',
            'route' => 'required|string',
            'comment' => 'nullable|string'
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

            // Create administration record
            $admin = new MedicationAdministration();
            $admin->patient_id = $schedule->patient_id;
            $admin->schedule_id = $scheduleId;
            $admin->product_or_service_request_id = $schedule->product_or_service_request_id;
            $admin->administered_at = $data['administered_at'];
            $admin->dose = $data['administered_dose'];
            $admin->route = $data['route'];
            $admin->comment = $data['comment'] ?? null;
            $userId = Auth::id();
            $admin->administered_by = $userId;
            $admin->save();

            // Reload the administration with its relationships
            $admin = MedicationAdministration::with(['administeredBy'])
                ->find($admin->id);

            // Add name property for frontend display
            $admin->administered_by_name = userfullname($userId);

            // Also update name in related object
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

            // Reload the administration with its relationships
            $admin = MedicationAdministration::with(['administeredBy', 'editedBy', 'deletedBy'])
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
}
