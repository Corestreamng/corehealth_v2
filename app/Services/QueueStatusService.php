<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\DoctorAppointment;
use App\Models\DoctorQueue;
use Illuminate\Support\Facades\DB;

class QueueStatusService
{
    /**
     * Defines which status transitions are allowed.
     * Key = current status, Value = array of valid next statuses.
     */
    public const ALLOWED_TRANSITIONS = [
        QueueStatus::SCHEDULED       => [QueueStatus::WAITING, QueueStatus::CANCELLED, QueueStatus::NO_SHOW],
        QueueStatus::WAITING         => [QueueStatus::VITALS_PENDING, QueueStatus::IN_CONSULTATION, QueueStatus::CANCELLED],
        QueueStatus::VITALS_PENDING  => [QueueStatus::READY, QueueStatus::CANCELLED],
        QueueStatus::READY           => [QueueStatus::IN_CONSULTATION, QueueStatus::CANCELLED],
        QueueStatus::IN_CONSULTATION => [QueueStatus::COMPLETED, QueueStatus::CANCELLED],
        QueueStatus::COMPLETED       => [], // terminal
        QueueStatus::CANCELLED       => [QueueStatus::SCHEDULED], // can reschedule
        QueueStatus::NO_SHOW         => [QueueStatus::SCHEDULED], // can reschedule
    ];

    /**
     * Transition a DoctorQueue entry to a new status with validation and side effects.
     *
     * @param  DoctorQueue  $queue
     * @param  int          $newStatus
     * @param  string|null  $reason    Optional reason (e.g. for cancellation)
     * @return DoctorQueue  Fresh instance after update
     *
     * @throws InvalidStatusTransitionException
     */
    public function transition(DoctorQueue $queue, int $newStatus, ?string $reason = null): DoctorQueue
    {
        $oldStatus = $queue->status;

        $allowed = self::ALLOWED_TRANSITIONS[$oldStatus] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw new InvalidStatusTransitionException($oldStatus, $newStatus);
        }

        DB::transaction(function () use ($queue, $newStatus, $oldStatus, $reason) {
            $updates = ['status' => $newStatus];

            // Side effects based on target status
            if ($newStatus === QueueStatus::IN_CONSULTATION && !$queue->consultation_started_at) {
                $updates['consultation_started_at'] = now();
            }

            if ($newStatus === QueueStatus::COMPLETED) {
                $updates['consultation_ended_at'] = now();
            }

            $queue->update($updates);

            // Sync linked appointment status
            if ($queue->appointment_id) {
                $appointmentUpdates = ['status' => $newStatus];

                if ($newStatus === QueueStatus::CANCELLED && $reason) {
                    $appointmentUpdates['cancellation_reason'] = $reason;
                    $appointmentUpdates['cancelled_at'] = now();
                }

                if ($newStatus === QueueStatus::NO_SHOW) {
                    $appointmentUpdates['no_show_marked_at'] = now();
                }

                DoctorAppointment::where('id', $queue->appointment_id)
                    ->update($appointmentUpdates);
            }
        });

        // Future: event(new QueueStatusChanged($queue->id, $oldStatus, $newStatus, $queue->clinic_id, $queue->staff_id));

        return $queue->fresh();
    }

    /**
     * Check if a transition is valid without executing it.
     */
    public function canTransition(int $fromStatus, int $toStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
        return in_array($toStatus, $allowed, true);
    }

    /**
     * Get all valid next statuses for a given current status.
     */
    public function allowedNextStatuses(int $currentStatus): array
    {
        $nextStatuses = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        return array_map(function (int $status) {
            return [
                'value' => $status,
                'label' => QueueStatus::label($status),
                'badge' => QueueStatus::badge($status),
            ];
        }, $nextStatuses);
    }

    /**
     * Pause the consultation timer for a queue entry.
     */
    public function pauseConsultation(DoctorQueue $queue): DoctorQueue
    {
        if ($queue->status !== QueueStatus::IN_CONSULTATION) {
            throw new \RuntimeException('Can only pause a queue entry that is in consultation.');
        }

        if ($queue->is_paused) {
            return $queue;
        }

        $queue->update([
            'is_paused'      => true,
            'last_paused_at' => now(),
        ]);

        return $queue->fresh();
    }

    /**
     * Resume the consultation timer for a queue entry.
     */
    public function resumeConsultation(DoctorQueue $queue): DoctorQueue
    {
        if (!$queue->is_paused) {
            return $queue;
        }

        $pausedSeconds = $queue->last_paused_at
            ? now()->diffInSeconds($queue->last_paused_at)
            : 0;

        $queue->update([
            'is_paused'                   => false,
            'last_resumed_at'             => now(),
            'consultation_paused_seconds' => $queue->consultation_paused_seconds + $pausedSeconds,
        ]);

        return $queue->fresh();
    }
}
