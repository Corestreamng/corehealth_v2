<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\User;
use App\Models\MedicationSchedule;
use App\Models\AdmissionRequest;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\ProductRequest;
use App\Models\Encounter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DepartmentNotificationService
{
    // Group names
    const GROUP_NURSING = 'Nursing Staff';
    const GROUP_LAB = 'Laboratory Staff';
    const GROUP_IMAGING = 'Imaging Staff';
    const GROUP_HMO = 'HMO Executives';

    // Cache durations
    const CACHE_DURATION = 3600; // 1 hour for notification tracking
    const GROUP_SYNC_INTERVAL = 3600; // Sync groups every hour

    /**
     * Get current hour key for cache cycling (resets every hour)
     */
    protected function getHourKey()
    {
        return Carbon::now()->format('Y-m-d-H');
    }

    /**
     * Get cutoff time - only process items created within last hour
     */
    protected function getCutoffTime()
    {
        return Carbon::now()->subHour();
    }

    /**
     * Run notification checks - call this from AppServiceProvider
     * Runs on every request but uses caching to prevent duplicates
     */
    public function runChecks()
    {
        try {
            // Sync groups (cached - only runs once per hour)
            $this->syncAllGroups();

            // Check for new items to notify
            // Each check uses caching to prevent duplicate notifications
            $this->checkNewLabRequests();
            $this->checkNewImagingRequests();
            $this->checkNewPrescriptions();
            $this->checkNewAdmissionRequests();
            $this->checkNewDischargeRequests();
            $this->checkUpcomingMedications();

        } catch (\Exception $e) {
            Log::error("Error in notification checks: " . $e->getMessage());
        }
    }

    /**
     * Send a test message to all groups (for verification)
     */
    public function sendTestMessages()
    {
        Log::info("Department notifications: Sending test messages to all groups");

        $results = [];
        $results['nursing'] = $this->sendToGroup(self::GROUP_NURSING, 'Test Notification', 'This is a test message to verify the nursing group is working.', '🧪');
        $results['lab'] = $this->sendToGroup(self::GROUP_LAB, 'Test Notification', 'This is a test message to verify the lab group is working.', '🧪');
        $results['imaging'] = $this->sendToGroup(self::GROUP_IMAGING, 'Test Notification', 'This is a test message to verify the imaging group is working.', '🧪');
        $results['hmo'] = $this->sendToGroup(self::GROUP_HMO, 'Test Notification', 'This is a test message to verify the HMO group is working.', '🧪');

        Log::info("Department notifications: Test results - " . json_encode($results));

        return $results;
    }

    /**
     * Sync all department groups
     */
    public function syncAllGroups()
    {
        $this->syncGroup(self::GROUP_NURSING, ['NURSE']);
        $this->syncGroup(self::GROUP_LAB, ['LAB SCIENTIST']);
        $this->syncGroup(self::GROUP_IMAGING, ['RADIOLOGIST']);
        $this->syncGroup(self::GROUP_HMO, ['HMO Executive']);
    }

    /**
     * Sync a department group with users of specified roles
     */
    protected function syncGroup($groupName, array $roles)
    {
        try {
            $cacheKey = 'dept_group_sync_' . str_replace(' ', '_', strtolower($groupName)) . '_' . $this->getHourKey();

            if (Cache::has($cacheKey)) {
                return;
            }

            // Find or create the group conversation
            $conversation = ChatConversation::firstOrCreate(
                ['title' => $groupName],
                ['is_group' => true]
            );

            // Get users with the specified roles
            $roleUsers = User::whereHas('roles', function($query) use ($roles) {
                $query->whereIn('name', $roles);
            })->pluck('id');

            // Also add SUPERADMIN and ADMIN for oversight
            $admins = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['SUPERADMIN', 'ADMIN']);
            })->pluck('id');

            $allUserIds = $roleUsers->merge($admins)->unique();

            if ($allUserIds->isEmpty()) {
                Cache::put($cacheKey, true, self::CACHE_DURATION);
                return;
            }

            // Get existing participants
            $existingParticipants = ChatParticipant::where('conversation_id', $conversation->id)
                ->pluck('user_id');

            // Add missing users
            $usersToAdd = $allUserIds->diff($existingParticipants);
            $addedCount = 0;

            foreach ($usersToAdd as $userId) {
                try {
                    ChatParticipant::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'joined_at' => now()
                    ]);
                    $addedCount++;
                } catch (\Exception $e) {
                    continue;
                }
            }

            Cache::put($cacheKey, true, self::CACHE_DURATION);

            if ($addedCount > 0) {
                Log::info("{$groupName} Group: Added {$addedCount} new participants");
            }

        } catch (\Exception $e) {
            Log::error("Error syncing {$groupName} group: " . $e->getMessage());
        }
    }

    /**
     * Get or create a group conversation
     */
    protected function getGroupConversation($groupName)
    {
        $cacheKey = 'dept_conversation_' . str_replace(' ', '_', strtolower($groupName));

        return Cache::remember($cacheKey, self::CACHE_DURATION, function() use ($groupName) {
            return ChatConversation::firstOrCreate(
                ['title' => $groupName],
                ['is_group' => true]
            );
        });
    }

    /**
     * Check if notification was already sent for an item
     */
    protected function wasNotified($type, $id)
    {
        $cacheKey = "notified_{$type}_{$id}_" . $this->getHourKey();
        return Cache::has($cacheKey);
    }

    /**
     * Mark item as notified
     */
    protected function markNotified($type, $id)
    {
        $cacheKey = "notified_{$type}_{$id}_" . $this->getHourKey();
        Cache::put($cacheKey, true, self::CACHE_DURATION);
    }

    /**
     * Send notification to a group
     */
    protected function sendToGroup($groupName, $title, $message, $icon = '🏥')
    {
        try {
            $conversation = $this->getGroupConversation($groupName);

            if (!$conversation) {
                Log::warning("Department notifications: No conversation found for {$groupName}");
                return false;
            }

            Log::info("Department notifications: Sending message to {$groupName} (conversation ID: {$conversation->id})");

            $chatMessage = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => Auth::id() ?? 1,
                'body' => "{$icon} **{$title}**\n\n{$message}\n\n_" . now()->format('h:i A, M j') . "_",
                'type' => 'text',
            ]);

            Log::info("Department notifications: Message sent successfully (ID: {$chatMessage->id})");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send {$groupName} notification: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        return false;
    }

    /**
     * Get patient's ward/bed info if admitted
     */
    protected function getPatientWardBed($patientId)
    {
        $admission = AdmissionRequest::where('patient_id', $patientId)
            ->where('discharged', 0)
            ->whereNotNull('bed_id')
            ->with(['bed.wardRelation'])
            ->first();

        if ($admission && $admission->bed) {
            return ($admission->bed->wardRelation->name ?? 'Ward') . ' - ' . $admission->bed->name;
        }
        return null;
    }

    /**
     * Check if patient is currently admitted
     */
    protected function isPatientAdmitted($patientId)
    {
        return AdmissionRequest::where('patient_id', $patientId)
            ->where('discharged', 0)
            ->exists();
    }

    // ========== HOURLY CHECK METHODS ==========

    /**
     * Check for new lab requests (within last hour, for admitted patients)
     */
    protected function checkNewLabRequests()
    {
        try {
            $cutoff = $this->getCutoffTime();

            $requests = LabServiceRequest::with(['patient.user', 'service', 'doctor', 'encounter'])
                ->where('created_at', '>=', $cutoff)
                ->get();

            foreach ($requests as $request) {
                if ($this->wasNotified('lab', $request->id)) {
                    continue;
                }

                $patientId = $request->patient_id;
                $isAdmitted = $this->isPatientAdmitted($patientId);

                // Only notify for admitted patients
                if (!$isAdmitted) {
                    $this->markNotified('lab', $request->id);
                    continue;
                }

                $patientName = $request->patient && $request->patient->user
                    ? $request->patient->user->name : 'Unknown';
                $wardBed = $this->getPatientWardBed($patientId);
                $testName = $request->service ? $request->service->service_name : 'Lab Test';
                $doctorName = $request->doctor ? $request->doctor->name : 'Unknown';

                $this->notifyLabRequest($patientName, $wardBed, $testName, $doctorName, true);
                $this->markNotified('lab', $request->id);
            }
        } catch (\Exception $e) {
            Log::error("Error checking lab requests: " . $e->getMessage());
        }
    }

    /**
     * Check for new imaging requests (within last hour, for admitted patients)
     */
    protected function checkNewImagingRequests()
    {
        try {
            $cutoff = $this->getCutoffTime();

            $requests = ImagingServiceRequest::with(['patient.user', 'service', 'doctor'])
                ->where('created_at', '>=', $cutoff)
                ->get();

            foreach ($requests as $request) {
                if ($this->wasNotified('imaging', $request->id)) {
                    continue;
                }

                $patientId = $request->patient_id;
                $isAdmitted = $this->isPatientAdmitted($patientId);

                // Only notify for admitted patients
                if (!$isAdmitted) {
                    $this->markNotified('imaging', $request->id);
                    continue;
                }

                $patientName = $request->patient && $request->patient->user
                    ? $request->patient->user->name : 'Unknown';
                $wardBed = $this->getPatientWardBed($patientId);
                $testName = $request->service ? $request->service->service_name : 'Imaging Study';
                $doctorName = $request->doctor ? $request->doctor->name : 'Unknown';

                $this->notifyImagingRequest($patientName, $wardBed, $testName, $doctorName, true);
                $this->markNotified('imaging', $request->id);
            }
        } catch (\Exception $e) {
            Log::error("Error checking imaging requests: " . $e->getMessage());
        }
    }

    /**
     * Check for new prescriptions (within last hour, for admitted patients)
     */
    protected function checkNewPrescriptions()
    {
        try {
            $cutoff = $this->getCutoffTime();

            $requests = ProductRequest::with(['patient.user', 'product', 'doctor'])
                ->where('created_at', '>=', $cutoff)
                ->get();

            // Group by encounter to send one notification per encounter
            $byEncounter = $requests->groupBy('encounter_id');

            foreach ($byEncounter as $encounterId => $prescriptions) {
                if (!$encounterId) continue;

                $cacheKey = "presc_enc_{$encounterId}";
                if ($this->wasNotified('prescription', $cacheKey)) {
                    continue;
                }

                $first = $prescriptions->first();
                $patientId = $first->patient_id;
                $isAdmitted = $this->isPatientAdmitted($patientId);

                // Only notify for admitted patients
                if (!$isAdmitted) {
                    $this->markNotified('prescription', $cacheKey);
                    continue;
                }

                $patientName = $first->patient && $first->patient->user
                    ? $first->patient->user->name : 'Unknown';
                $wardBed = $this->getPatientWardBed($patientId);
                $doctorName = $first->doctor ? $first->doctor->name : 'Unknown';

                $medNames = $prescriptions->map(function($p) {
                    return $p->product ? $p->product->product_name : 'Unknown';
                })->take(5)->toArray();

                if ($prescriptions->count() > 5) {
                    $medNames[] = '+ ' . ($prescriptions->count() - 5) . ' more';
                }

                $this->notifyNewMedication($patientName, $wardBed, $medNames, $doctorName);
                $this->markNotified('prescription', $cacheKey);
            }
        } catch (\Exception $e) {
            Log::error("Error checking prescriptions: " . $e->getMessage());
        }
    }

    /**
     * Check for new admission requests (within last hour)
     */
    protected function checkNewAdmissionRequests()
    {
        try {
            $cutoff = $this->getCutoffTime();

            $requests = AdmissionRequest::with(['patient.user', 'doctor'])
                ->where('created_at', '>=', $cutoff)
                ->whereIn('admission_status', ['pending_checklist', 'pending'])
                ->get();

            foreach ($requests as $request) {
                if ($this->wasNotified('admission', $request->id)) {
                    continue;
                }

                $patientName = $request->patient && $request->patient->user
                    ? $request->patient->user->name : 'Unknown';
                $doctorName = $request->doctor ? $request->doctor->name : 'Unknown';
                $priority = $request->priority ?? 'routine';
                $reason = $request->admission_reason;

                $this->notifyAdmissionRequest($patientName, $doctorName, $priority, $reason);
                $this->markNotified('admission', $request->id);
            }
        } catch (\Exception $e) {
            Log::error("Error checking admission requests: " . $e->getMessage());
        }
    }

    /**
     * Check for new discharge requests (within last hour)
     */
    protected function checkNewDischargeRequests()
    {
        try {
            $cutoff = $this->getCutoffTime();

            $requests = AdmissionRequest::with(['patient.user', 'doctor', 'bed.wardRelation'])
                ->where('updated_at', '>=', $cutoff)
                ->where('admission_status', 'discharge_requested')
                ->get();

            foreach ($requests as $request) {
                if ($this->wasNotified('discharge', $request->id)) {
                    continue;
                }

                $patientName = $request->patient && $request->patient->user
                    ? $request->patient->user->name : 'Unknown';
                $doctorName = $request->doctor ? $request->doctor->name : 'Unknown';
                $wardBed = $request->bed
                    ? ($request->bed->wardRelation->name ?? 'Ward') . ' - ' . $request->bed->name
                    : 'Unknown';
                $reason = $request->discharge_reason;

                $this->notifyDischargeRequest($patientName, $wardBed, $doctorName, $reason);
                $this->markNotified('discharge', $request->id);
            }
        } catch (\Exception $e) {
            Log::error("Error checking discharge requests: " . $e->getMessage());
        }
    }

    /**
     * Check for upcoming medication schedules (within next 30 minutes)
     */
    public function checkUpcomingMedications()
    {
        try {
            $now = Carbon::now();
            $upcomingTime = Carbon::now()->addMinutes(30);

            $schedules = MedicationSchedule::with(['patient.user', 'productOrServiceRequest.product'])
                ->whereBetween('scheduled_time', [$now, $upcomingTime])
                ->whereNull('deleted_at')
                ->get();

            foreach ($schedules as $schedule) {
                if ($this->wasNotified('med_schedule', $schedule->id)) {
                    continue;
                }

                $patient = $schedule->patient;
                if (!$patient) {
                    $this->markNotified('med_schedule', $schedule->id);
                    continue;
                }

                $wardBed = $this->getPatientWardBed($patient->id);
                if (!$wardBed) {
                    // Patient not admitted or no bed assigned
                    $this->markNotified('med_schedule', $schedule->id);
                    continue;
                }

                $patientName = $patient->user ? $patient->user->name : 'Unknown Patient';
                $medication = $schedule->productOrServiceRequest && $schedule->productOrServiceRequest->product
                    ? $schedule->productOrServiceRequest->product->product_name
                    : 'Unknown Medication';
                $dueTime = Carbon::parse($schedule->scheduled_time)->format('h:i A');

                $this->notifyMedicationDue($patientName, $wardBed, $medication . ' (' . ($schedule->dose ?? '') . ')', $dueTime);
                $this->markNotified('med_schedule', $schedule->id);
            }
        } catch (\Exception $e) {
            Log::error("Error checking upcoming medications: " . $e->getMessage());
        }
    }

    // ========== DIRECT NOTIFICATION METHODS (for real-time use in controllers) ==========

    public function sendNursingNotification($title, $message)
    {
        return $this->sendToGroup(self::GROUP_NURSING, $title, $message, '👩‍⚕️');
    }

    public function notifyWardRoundNote($patientName, $doctorName, $wardBed, $note = null)
    {
        $message = "Patient: **{$patientName}**\nLocation: {$wardBed}\nDoctor: Dr. {$doctorName}";
        if ($note) {
            $message .= "\n\nNote: " . substr($note, 0, 200) . (strlen($note) > 200 ? '...' : '');
        }
        return $this->sendNursingNotification('Ward Round Note Added', $message);
    }

    public function notifyNewMedication($patientName, $wardBed, $medications, $doctorName)
    {
        $medList = is_array($medications) ? implode(', ', $medications) : $medications;
        $message = "Patient: **{$patientName}**\nLocation: {$wardBed}\nMedications: {$medList}\nOrdered by: Dr. {$doctorName}";
        return $this->sendToGroup(self::GROUP_NURSING, 'New Medication Order', $message, '💊');
    }

    public function notifyAdmissionRequest($patientName, $doctorName, $priority, $reason = null)
    {
        $priorityEmoji = $priority === 'emergency' ? '🚨' : ($priority === 'urgent' ? '⚠️' : '📋');
        $message = "Patient: **{$patientName}**\nPriority: " . strtoupper($priority) . "\nRequested by: Dr. {$doctorName}";
        if ($reason) {
            $message .= "\nReason: {$reason}";
        }
        return $this->sendToGroup(self::GROUP_NURSING, "{$priorityEmoji} Admission Request", $message, '🛏️');
    }

    public function notifyDischargeRequest($patientName, $wardBed, $doctorName, $reason = null)
    {
        $message = "Patient: **{$patientName}**\nLocation: {$wardBed}\nRequested by: Dr. {$doctorName}";
        if ($reason) {
            $message .= "\nReason: {$reason}";
        }
        return $this->sendToGroup(self::GROUP_NURSING, 'Discharge Request', $message, '🚪');
    }

    public function notifyMedicationDue($patientName, $wardBed, $medication, $dueTime)
    {
        $message = "Patient: **{$patientName}**\nLocation: {$wardBed}\nMedication: {$medication}\nDue at: {$dueTime}";
        return $this->sendToGroup(self::GROUP_NURSING, 'Medication Due Soon', $message, '⏰');
    }

    // ========== LAB NOTIFICATIONS ==========

    public function sendLabNotification($title, $message)
    {
        return $this->sendToGroup(self::GROUP_LAB, $title, $message, '🧪');
    }

    public function notifyLabRequest($patientName, $wardBed, $tests, $doctorName, $isAdmitted = false)
    {
        $testList = is_array($tests) ? implode(', ', $tests) : $tests;
        $admittedBadge = $isAdmitted ? ' [ADMITTED]' : '';
        $message = "Patient: **{$patientName}**{$admittedBadge}";
        if ($wardBed) {
            $message .= "\nLocation: {$wardBed}";
        }
        $message .= "\nTests: {$testList}\nOrdered by: Dr. {$doctorName}";
        return $this->sendLabNotification('New Lab Request', $message);
    }

    // ========== IMAGING NOTIFICATIONS ==========

    public function sendImagingNotification($title, $message)
    {
        return $this->sendToGroup(self::GROUP_IMAGING, $title, $message, '📷');
    }

    public function notifyImagingRequest($patientName, $wardBed, $tests, $doctorName, $isAdmitted = false)
    {
        $testList = is_array($tests) ? implode(', ', $tests) : $tests;
        $admittedBadge = $isAdmitted ? ' [ADMITTED]' : '';
        $message = "Patient: **{$patientName}**{$admittedBadge}";
        if ($wardBed) {
            $message .= "\nLocation: {$wardBed}";
        }
        $message .= "\nStudies: {$testList}\nOrdered by: Dr. {$doctorName}";
        return $this->sendImagingNotification('New Imaging Request', $message);
    }

    // ========== HMO NOTIFICATIONS ==========

    public function sendHmoNotification($title, $message)
    {
        return $this->sendToGroup(self::GROUP_HMO, $title, $message, '🏥');
    }
}
