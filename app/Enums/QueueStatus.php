<?php

namespace App\Enums;

/**
 * Unified queue/appointment status constants.
 *
 * Used by DoctorQueue, DoctorAppointment, and all workbench views
 * (reception, nurse, doctor) to ensure consistent status semantics.
 *
 * @see APPOINTMENT_ENHANCEMENT_PLAN.md §3.1
 */
class QueueStatus
{
    const CANCELLED       = 0;
    const WAITING         = 1;  // Queued, no vitals taken yet
    const VITALS_PENDING  = 2;  // Nurse picked up patient
    const READY           = 3;  // Vitals done, waiting for doctor
    const IN_CONSULTATION = 4;  // Doctor started encounter
    const COMPLETED       = 5;  // Encounter finalized
    const SCHEDULED       = 6;  // Future appointment (not yet in queue)
    const NO_SHOW         = 7;  // Patient didn't arrive

    /**
     * Human-readable labels indexed by status integer.
     */
    const LABELS = [
        self::CANCELLED       => 'Cancelled',
        self::WAITING         => 'Waiting',
        self::VITALS_PENDING  => 'Vitals Pending',
        self::READY           => 'Ready',
        self::IN_CONSULTATION => 'In Consultation',
        self::COMPLETED       => 'Completed',
        self::SCHEDULED       => 'Scheduled',
        self::NO_SHOW         => 'No-Show',
    ];

    /**
     * Bootstrap badge CSS classes for each status.
     */
    const BADGE_CLASSES = [
        self::CANCELLED       => 'bg-secondary',
        self::WAITING         => 'bg-warning text-dark',
        self::VITALS_PENDING  => 'bg-info text-white',
        self::READY           => 'bg-primary',
        self::IN_CONSULTATION => 'bg-success',
        self::COMPLETED       => 'bg-dark',
        self::SCHEDULED       => 'bg-purple',
        self::NO_SHOW         => 'bg-danger',
    ];

    /**
     * Hex colors for calendar events and charts.
     */
    const COLORS = [
        self::CANCELLED       => '#6c757d', // grey
        self::WAITING         => '#ffc107', // yellow
        self::VITALS_PENDING  => '#17a2b8', // cyan
        self::READY           => '#0d6efd', // blue
        self::IN_CONSULTATION => '#198754', // green
        self::COMPLETED       => '#212529', // dark
        self::SCHEDULED       => '#6f42c1', // purple
        self::NO_SHOW         => '#dc3545', // red
    ];

    /**
     * Statuses that represent "active" queue entries (visible in live queue).
     */
    const ACTIVE = [
        self::WAITING,
        self::VITALS_PENDING,
        self::READY,
        self::IN_CONSULTATION,
    ];

    /**
     * Statuses that represent terminal/resolved states.
     */
    const TERMINAL = [
        self::CANCELLED,
        self::COMPLETED,
        self::NO_SHOW,
    ];

    /**
     * Get the human-readable label for a status.
     */
    public static function label(int $status): string
    {
        return self::LABELS[$status] ?? 'Unknown';
    }

    /**
     * Get an HTML badge for a status.
     */
    public static function badge(int $status): string
    {
        $label = self::label($status);
        $class = self::BADGE_CLASSES[$status] ?? 'bg-secondary';

        return "<span class='badge {$class}'>{$label}</span>";
    }

    /**
     * Get the hex color for a status.
     */
    public static function color(int $status): string
    {
        return self::COLORS[$status] ?? '#6c757d';
    }

    /**
     * Check if a status is an active (in-progress) state.
     */
    public static function isActive(int $status): bool
    {
        return in_array($status, self::ACTIVE, true);
    }

    /**
     * Check if a status is a terminal (resolved) state.
     */
    public static function isTerminal(int $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    /**
     * Get all status values as an array (useful for validation rules).
     */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }
}
