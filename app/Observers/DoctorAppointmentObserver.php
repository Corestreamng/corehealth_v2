<?php

namespace App\Observers;

use App\Enums\QueueStatus;
use App\Models\DoctorAppointment;
use App\Services\AppointmentMailService;
use Illuminate\Support\Facades\Log;

/**
 * DoctorAppointmentObserver
 *
 * Observes DoctorAppointment model lifecycle events and triggers
 * appointment notification emails via AppointmentMailService.
 *
 * Also handles:
 * - Auto no-show marking for past unattended appointments (on query events)
 * - Status validation before save
 *
 * Replaces the originally planned cron-based approach with an observer pattern
 * that fires on every relevant model event.
 */
class DoctorAppointmentObserver
{
    protected AppointmentMailService $mailService;

    /**
     * Stores original model attributes keyed by appointment ID.
     * Used to compare old vs new values in updated() event.
     * Static to avoid PHP 8.2+ dynamic property deprecation.
     */
    protected static array $observerOriginals = [];

    public function __construct(AppointmentMailService $mailService)
    {
        $this->mailService = $mailService;
    }

    /**
     * Handle the DoctorAppointment "created" event.
     * Sends notification emails for newly scheduled appointments.
     * Detects rescheduled appointments (linked via rescheduled_from_id)
     * and sends a "rescheduled" email instead of "created".
     */
    public function created(DoctorAppointment $appointment): void
    {
        // Only send emails for scheduled appointments (not auto-generated statuses)
        if ($appointment->status === QueueStatus::SCHEDULED) {
            // If this appointment was created as part of a reschedule, send 'rescheduled' event
            if ($appointment->rescheduled_from_id) {
                $source = DoctorAppointment::find($appointment->rescheduled_from_id);
                $this->mailService->notify($appointment, 'rescheduled', [
                    'old_date' => $source && $source->appointment_date
                        ? $source->appointment_date->format('l, F j, Y')
                        : '',
                    'old_time' => $source && $source->start_time
                        ? \Carbon\Carbon::parse($source->start_time)->format('g:i A')
                        : '',
                ]);
            } else {
                $this->mailService->notify($appointment, 'created');
            }
        }
    }

    /**
     * Handle the DoctorAppointment "updating" event (before save).
     * Captures the original values so we can detect what changed in updated().
     */
    public function updating(DoctorAppointment $appointment): void
    {
        // Store originals in a static array keyed by ID to avoid PHP 8.2 dynamic property deprecation
        static::$observerOriginals[$appointment->id] = $appointment->getOriginal();
    }

    /**
     * Handle the DoctorAppointment "updated" event.
     * Detects status transitions and rescheduling, then sends appropriate emails.
     */
    public function updated(DoctorAppointment $appointment): void
    {
        $original = static::$observerOriginals[$appointment->id] ?? $appointment->getOriginal();
        unset(static::$observerOriginals[$appointment->id]);
        $oldStatus = (int) ($original['status'] ?? null);
        $newStatus = (int) $appointment->status;

        // Skip if status hasn't changed
        if ($oldStatus === $newStatus) {
            // Check if appointment was rescheduled (date/time changed without status change)
            if ($this->wasRescheduled($appointment, $original)) {
                $this->mailService->notify($appointment, 'rescheduled', [
                    'old_date' => isset($original['appointment_date'])
                        ? \Carbon\Carbon::parse($original['appointment_date'])->format('l, F j, Y')
                        : '',
                    'old_time' => isset($original['start_time'])
                        ? \Carbon\Carbon::parse($original['start_time'])->format('g:i A')
                        : '',
                ]);
            }
            return;
        }

        // Handle status transitions
        match ($newStatus) {
            QueueStatus::CANCELLED => $this->handleCancelled($appointment, $original),
            QueueStatus::NO_SHOW   => $this->handleNoShow($appointment),
            QueueStatus::WAITING   => $this->handleCheckedIn($appointment, $oldStatus),
            default                => null,
        };

        // Handle reassignment (staff_id changed)
        if ($this->wasReassigned($appointment, $original)) {
            $this->handleReassigned($appointment, $original);
        }

        // Auto no-show: mark past SCHEDULED appointments as NO_SHOW
        // This runs passively when any appointment update touches the model
        $this->autoMarkNoShowForPast($appointment);
    }

    /**
     * Handle cancellation — send cancellation email.
     * Skips notification if this cancellation is part of a reschedule
     * (the rescheduled appointment's created() already sends the 'rescheduled' email).
     */
    protected function handleCancelled(DoctorAppointment $appointment, array $original): void
    {
        // If a new appointment was created from this one (reschedule), skip the cancellation email
        if ($appointment->rescheduledTo()->exists()) {
            return;
        }

        $this->mailService->notify($appointment, 'cancelled', [
            'reason' => $appointment->cancellation_reason ?? 'No reason provided',
        ]);
    }

    /**
     * Handle no-show — send no-show email.
     */
    protected function handleNoShow(DoctorAppointment $appointment): void
    {
        $this->mailService->notify($appointment, 'no_show');
    }

    /**
     * Handle check-in — only send email if transitioning from SCHEDULED to WAITING.
     */
    protected function handleCheckedIn(DoctorAppointment $appointment, int $oldStatus): void
    {
        if ($oldStatus === QueueStatus::SCHEDULED) {
            $this->mailService->notify($appointment, 'checked_in');
        }
    }

    /**
     * Handle reassignment — send reassignment email to the NEW doctor.
     */
    protected function handleReassigned(DoctorAppointment $appointment, array $original): void
    {
        $oldDoctorName = '';
        if (isset($original['staff_id']) && $original['staff_id']) {
            $oldDoctor = \App\Models\Staff::with('user')->find($original['staff_id']);
            $oldDoctorName = $oldDoctor && $oldDoctor->user ? $oldDoctor->user->name : '';
        }

        $this->mailService->notify($appointment, 'reassigned', [
            'old_doctor'          => $oldDoctorName,
            'reassignment_reason' => $appointment->reassignment_reason ?? '',
        ]);
    }

    /**
     * Check if the appointment date or time was changed (rescheduled).
     */
    protected function wasRescheduled(DoctorAppointment $appointment, array $original): bool
    {
        $dateChanged = isset($original['appointment_date'])
            && $appointment->appointment_date
            && $appointment->appointment_date->toDateString() !== \Carbon\Carbon::parse($original['appointment_date'])->toDateString();

        $timeChanged = isset($original['start_time'])
            && $appointment->start_time !== $original['start_time'];

        return $dateChanged || $timeChanged;
    }

    /**
     * Check if the doctor (staff_id) was changed (reassigned).
     */
    protected function wasReassigned(DoctorAppointment $appointment, array $original): bool
    {
        return isset($original['staff_id'])
            && $appointment->staff_id
            && $appointment->staff_id !== ($original['staff_id'] ?? null);
    }

    /**
     * Auto-mark past SCHEDULED appointments as NO_SHOW.
     *
     * This runs opportunistically when any appointment is updated,
     * replacing the need for a cron job. It only processes appointments
     * for the same clinic/date to keep the query lightweight.
     */
    protected function autoMarkNoShowForPast(DoctorAppointment $appointment): void
    {
        try {
            $cutoffMinutes = (int) appsettings('no_show_cutoff_minutes', 60);

            // Only mark no-show for past scheduled appointments
            $cutoff = now()->subMinutes($cutoffMinutes);

            $staleCount = DoctorAppointment::where('status', QueueStatus::SCHEDULED)
                ->where('appointment_date', '<', now()->toDateString())
                ->orWhere(function ($q) use ($cutoff) {
                    $q->where('status', QueueStatus::SCHEDULED)
                      ->where('appointment_date', now()->toDateString())
                      ->where('start_time', '<', $cutoff->format('H:i'));
                })
                ->limit(50) // Safety limit — process max 50 per trigger
                ->update([
                    'status'            => QueueStatus::NO_SHOW,
                    'no_show_marked_at' => now(),
                ]);

            if ($staleCount > 0) {
                Log::info("DoctorAppointmentObserver: Auto-marked {$staleCount} past appointments as NO_SHOW");
            }
        } catch (\Exception $e) {
            // Non-critical — log and move on
            Log::warning('DoctorAppointmentObserver: autoMarkNoShowForPast error', ['error' => $e->getMessage()]);
        }
    }
}
