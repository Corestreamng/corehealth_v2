<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Clinic;
use App\Models\ClinicSchedule;

/**
 * Seed default operating hours for all active clinics.
 *
 * Defaults: Mon–Fri 08:00–17:00, Sat 08:00–13:00, 15-min slots, 3 concurrent
 * Run: php artisan db:seed --class=ClinicScheduleSeeder
 */
class ClinicScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = Clinic::all();

        if ($clinics->isEmpty()) {
            $this->command?->warn('No clinics found — skipping ClinicScheduleSeeder.');
            return;
        }

        $weekday = [
            'open_time'              => '08:00',
            'close_time'             => '17:00',
            'slot_duration_minutes'  => 15,
            'max_concurrent_slots'   => 3,
            'is_active'              => true,
        ];

        $saturday = [
            'open_time'              => '08:00',
            'close_time'             => '13:00',
            'slot_duration_minutes'  => 15,
            'max_concurrent_slots'   => 2,
            'is_active'              => true,
        ];

        $seeded = 0;

        foreach ($clinics as $clinic) {
            // Skip "Blank" placeholder clinic
            if (strtolower(trim($clinic->name)) === 'blank') {
                continue;
            }

            // Mon(1) – Fri(5)
            for ($day = 1; $day <= 5; $day++) {
                ClinicSchedule::firstOrCreate(
                    ['clinic_id' => $clinic->id, 'day_of_week' => $day],
                    $weekday
                );
            }

            // Saturday (6)
            ClinicSchedule::firstOrCreate(
                ['clinic_id' => $clinic->id, 'day_of_week' => 6],
                $saturday
            );

            // Sunday (0) — not created (closed by default)

            $seeded++;
        }

        $this->command?->info("Seeded schedules for {$seeded} clinics (Mon–Sat).");
    }
}
