<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Models\ClinicSchedule;
use App\Models\DoctorAppointment;
use App\Models\DoctorAvailability;
use App\Models\DoctorAvailabilityOverride;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AppointmentSlotService
{
    /**
     * Get available time slots for a clinic on a given date, optionally filtered by doctor.
     *
     * @param  int         $clinicId
     * @param  string|Carbon $date
     * @param  int|null    $doctorId   Staff ID (optional — null = any available)
     * @return Collection  Collection of ['time' => '09:00', 'available' => true/false, 'reason' => ?string]
     */
    public function getAvailableSlots(int $clinicId, $date, ?int $doctorId = null): Collection
    {
        $date = Carbon::parse($date);
        $dayOfWeek = $date->dayOfWeek;

        // 1. Get clinic schedule for this day of week
        $clinicSchedule = ClinicSchedule::where('clinic_id', $clinicId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (!$clinicSchedule) {
            return collect(); // Clinic not open on this day
        }

        $slotDuration     = $clinicSchedule->slot_duration_minutes;
        $maxConcurrent    = $clinicSchedule->max_concurrent_slots;
        $openTime         = Carbon::parse($clinicSchedule->open_time);
        $closeTime        = Carbon::parse($clinicSchedule->close_time);

        // 2. If a specific doctor is requested, check their availability
        if ($doctorId) {
            // Check for overrides first (takes precedence)
            $override = DoctorAvailabilityOverride::where('staff_id', $doctorId)
                ->where('override_date', $date->toDateString())
                ->first();

            if ($override) {
                if (!$override->is_available) {
                    return collect(); // Doctor is blocked on this date
                }
                // If extra availability, use override times
                if ($override->start_time && $override->end_time) {
                    $openTime  = Carbon::parse($override->start_time);
                    $closeTime = Carbon::parse($override->end_time);
                }
            } else {
                // Check regular weekly availability
                $availability = DoctorAvailability::where('staff_id', $doctorId)
                    ->where('clinic_id', $clinicId)
                    ->where('day_of_week', $dayOfWeek)
                    ->where('is_active', true)
                    ->first();

                if (!$availability) {
                    return collect(); // Doctor not available on this day of week
                }

                // Narrow the window to doctor's availability
                $doctorStart = Carbon::parse($availability->start_time);
                $doctorEnd   = Carbon::parse($availability->end_time);

                $openTime  = $openTime->max($doctorStart);
                $closeTime = $closeTime->min($doctorEnd);
            }
        }

        if ($openTime->gte($closeTime)) {
            return collect(); // No valid time window
        }

        // 3. Generate all possible time slots
        $slots = collect();
        $current = $openTime->copy();

        while ($current->copy()->addMinutes($slotDuration)->lte($closeTime)) {
            $slots->push($current->format('H:i'));
            $current->addMinutes($slotDuration);
        }

        // 4. Count existing booked appointments per slot
        $existingQuery = DoctorAppointment::where('clinic_id', $clinicId)
            ->where('appointment_date', $date->toDateString())
            ->whereNotIn('status', [QueueStatus::CANCELLED, QueueStatus::NO_SHOW]);

        if ($doctorId) {
            $existingQuery->where('staff_id', $doctorId);
        }

        $existingAppointments = $existingQuery
            ->selectRaw("TIME_FORMAT(start_time, '%H:%i') as slot_time, COUNT(*) as cnt")
            ->groupBy('slot_time')
            ->pluck('cnt', 'slot_time');

        // 5. Return slot availability
        return $slots->map(function (string $time) use ($existingAppointments, $maxConcurrent) {
            $count = $existingAppointments->get($time, 0);
            $available = $count < $maxConcurrent;

            return [
                'time'      => $time,
                'available' => $available,
                'booked'    => $count,
                'reason'    => $available ? null : 'Fully booked',
            ];
        });
    }

    /**
     * Check if a specific slot is available.
     */
    public function isSlotAvailable(int $clinicId, $date, string $time, ?int $doctorId = null): bool
    {
        $slots = $this->getAvailableSlots($clinicId, $date, $doctorId);

        $slot = $slots->firstWhere('time', $time);

        return $slot ? $slot['available'] : false;
    }

    /**
     * Detect double-booking conflicts for a proposed appointment.
     *
     * Checks whether the patient or doctor already has an overlapping appointment
     * at the given date/time window. Returns an array of conflicts found.
     *
     * @param  int         $patientId
     * @param  int|null    $doctorId   Staff ID
     * @param  int         $clinicId
     * @param  string|Carbon $date
     * @param  string      $startTime  H:i format
     * @param  string|null $endTime    H:i format (optional)
     * @param  int|null    $excludeAppointmentId  Exclude this appointment (for reschedule)
     * @return array       ['has_conflict' => bool, 'conflicts' => [...]]
     */
    public function detectDoubleBooking(
        int $patientId,
        ?int $doctorId,
        int $clinicId,
        $date,
        string $startTime,
        ?string $endTime = null,
        ?int $excludeAppointmentId = null
    ): array {
        $date = Carbon::parse($date)->toDateString();
        $endTime = $endTime ?: Carbon::parse($startTime)->addMinutes(15)->format('H:i');

        $conflicts = [];

        // 1. Check patient conflicts — patient can't be in two places at once
        $patientConflicts = DoctorAppointment::where('patient_id', $patientId)
            ->where('appointment_date', $date)
            ->whereNotIn('status', [QueueStatus::CANCELLED, QueueStatus::NO_SHOW])
            ->when($excludeAppointmentId, fn($q) => $q->where('id', '!=', $excludeAppointmentId))
            ->where(function ($q) use ($startTime, $endTime) {
                // Overlap: existing.start < new.end AND existing.end > new.start
                $q->where('start_time', '<', $endTime)
                  ->where(function ($q2) use ($startTime) {
                      $q2->where('end_time', '>', $startTime)
                         ->orWhereNull('end_time');
                  });
            })
            ->with(['clinic', 'doctor.user'])
            ->get();

        foreach ($patientConflicts as $conflict) {
            $clinicName = $conflict->clinic ? $conflict->clinic->name : 'another clinic';
            $doctorName = ($conflict->doctor && $conflict->doctor->user) ? $conflict->doctor->user->name : 'Unknown';
            $conflicts[] = [
                'type'    => 'patient',
                'message' => "Patient already has an appointment at {$clinicName} "
                           . "with Dr. {$doctorName}"
                           . " at " . Carbon::parse($conflict->start_time)->format('g:i A'),
                'appointment_id' => $conflict->id,
            ];
        }

        // 2. Check doctor conflicts — doctor can't see two patients at the same time
        if ($doctorId) {
            $doctorConflicts = DoctorAppointment::where('staff_id', $doctorId)
                ->where('appointment_date', $date)
                ->whereNotIn('status', [QueueStatus::CANCELLED, QueueStatus::NO_SHOW])
                ->when($excludeAppointmentId, fn($q) => $q->where('id', '!=', $excludeAppointmentId))
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where(function ($q2) use ($startTime) {
                          $q2->where('end_time', '>', $startTime)
                             ->orWhereNull('end_time');
                      });
                })
                ->with(['patient.user', 'clinic'])
                ->get();

            foreach ($doctorConflicts as $conflict) {
                $patientName = ($conflict->patient && $conflict->patient->user) ? $conflict->patient->user->name : 'a patient';
                $conflicts[] = [
                    'type'    => 'doctor',
                    'message' => "Doctor already has an appointment with {$patientName}"
                               . " at " . Carbon::parse($conflict->start_time)->format('g:i A'),
                    'appointment_id' => $conflict->id,
                ];
            }
        }

        return [
            'has_conflict' => count($conflicts) > 0,
            'conflicts'    => $conflicts,
        ];
    }

    /**
     * Get the next available slot on or after a given date.
     */
    public function getNextAvailableSlot(int $clinicId, ?int $doctorId = null, ?Carbon $fromDate = null, int $maxDaysAhead = 30): ?array
    {
        $date = $fromDate ? $fromDate->copy() : Carbon::today();
        $endDate = $date->copy()->addDays($maxDaysAhead);

        while ($date->lte($endDate)) {
            $slots = $this->getAvailableSlots($clinicId, $date, $doctorId);
            $available = $slots->firstWhere('available', true);

            if ($available) {
                return [
                    'date' => $date->toDateString(),
                    'time' => $available['time'],
                ];
            }

            $date->addDay();
        }

        return null;
    }
}
