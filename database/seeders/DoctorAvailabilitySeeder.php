<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Staff;
use App\Models\DoctorAvailability;
use App\Models\Clinic;

/**
 * Seed default availability for all doctors (staff with a clinic).
 *
 * Defaults: Mon–Fri 08:00–17:00, Sat 08:00–13:00 at their assigned clinic
 * Run: php artisan db:seed --class=DoctorAvailabilitySeeder
 */
class DoctorAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        // Get staff assigned to real clinics (exclude "Blank")
        $blankClinicIds = Clinic::whereRaw("LOWER(TRIM(name)) = 'blank'")->pluck('id')->toArray();

        $doctors = Staff::whereNotNull('clinic_id')
            ->when(!empty($blankClinicIds), fn($q) => $q->whereNotIn('clinic_id', $blankClinicIds))
            ->get();

        if ($doctors->isEmpty()) {
            $this->command?->warn('No doctors with clinic assignments found — skipping.');
            return;
        }

        $seeded = 0;

        foreach ($doctors as $doc) {
            // Mon–Fri 08:00–17:00
            for ($day = 1; $day <= 5; $day++) {
                DoctorAvailability::firstOrCreate(
                    [
                        'staff_id'    => $doc->id,
                        'clinic_id'   => $doc->clinic_id,
                        'day_of_week' => $day,
                    ],
                    [
                        'start_time' => '08:00',
                        'end_time'   => '17:00',
                        'is_active'  => true,
                    ]
                );
            }

            // Saturday 08:00–13:00
            DoctorAvailability::firstOrCreate(
                [
                    'staff_id'    => $doc->id,
                    'clinic_id'   => $doc->clinic_id,
                    'day_of_week' => 6,
                ],
                [
                    'start_time' => '08:00',
                    'end_time'   => '13:00',
                    'is_active'  => true,
                ]
            );

            $seeded++;
        }

        $this->command?->info("Seeded availability for {$seeded} doctors (Mon–Sat).");
    }
}
