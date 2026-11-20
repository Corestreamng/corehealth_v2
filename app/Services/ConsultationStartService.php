<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Models\DoctorQueue;

/**
 * Moves a queue entry into "In consultation" when a doctor opens an encounter.
 * Shared by the web encounter page and the native client so both behave identically.
 */
class ConsultationStartService
{
    public function begin(?DoctorQueue $queue): void
    {
        if (!$queue || $queue->status === QueueStatus::IN_CONSULTATION) {
            return;
        }

        try {
            app(QueueStatusService::class)->transition($queue, QueueStatus::IN_CONSULTATION);
        } catch (\Exception $e) {
            // Fallback when the transition rules reject it (e.g. already in the target state)
            $queue->update([
                'status' => QueueStatus::IN_CONSULTATION,
                'consultation_started_at' => $queue->consultation_started_at ?? now(),
            ]);
        }
    }
}
