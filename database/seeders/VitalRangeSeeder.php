<?php

namespace Database\Seeders;

use App\Models\VitalRange;
use Illuminate\Database\Seeder;

class VitalRangeSeeder extends Seeder
{
    public function run()
    {
        VitalRange::truncate();

        $data = [
            // ─────────────────────────────────────────────────────────────────
            // NEONATE (0 - 28 Days)
            // ─────────────────────────────────────────────────────────────────
            [
                'vital_key' => 'temp',
                'age_min_days' => 0, 'age_max_days' => 28,
                'normal_min' => 36.5, 'normal_max' => 37.5,
                'warning_min' => 36.0, 'warning_max' => 38.0,
                'critical_min' => 35.0, 'critical_max' => 39.0,
                'notes' => 'Neonate Temperature'
            ],
            [
                'vital_key' => 'heart_rate',
                'age_min_days' => 0, 'age_max_days' => 28,
                'normal_min' => 100, 'normal_max' => 180,
                'warning_min' => 80, 'warning_max' => 200,
                'critical_min' => 60, 'critical_max' => 220,
                'notes' => 'Neonate Heart Rate'
            ],
            [
                'vital_key' => 'resp_rate',
                'age_min_days' => 0, 'age_max_days' => 28,
                'normal_min' => 30, 'normal_max' => 60,
                'warning_min' => 25, 'warning_max' => 80,
                'critical_min' => 20, 'critical_max' => 100,
                'notes' => 'Neonate Respiratory Rate'
            ],
            [
                'vital_key' => 'bp_sys',
                'age_min_days' => 0, 'age_max_days' => 28,
                'normal_min' => 60, 'normal_max' => 90,
                'warning_min' => 50, 'warning_max' => 100,
                'critical_min' => 40, 'critical_max' => 110,
                'notes' => 'Neonate Systolic BP'
            ],
            [
                'vital_key' => 'bp_dia',
                'age_min_days' => 0, 'age_max_days' => 28,
                'normal_min' => 20, 'normal_max' => 60,
                'warning_min' => 15, 'warning_max' => 70,
                'critical_min' => 10, 'critical_max' => 80,
                'notes' => 'Neonate Diastolic BP'
            ],

            // ─────────────────────────────────────────────────────────────────
            // INFANT (29 Days - 1 Year)
            // ─────────────────────────────────────────────────────────────────
            [
                'vital_key' => 'heart_rate',
                'age_min_days' => 29, 'age_max_days' => 365,
                'normal_min' => 100, 'normal_max' => 160,
                'warning_min' => 80, 'warning_max' => 180,
                'critical_min' => 60, 'critical_max' => 200,
                'notes' => 'Infant Heart Rate'
            ],
            [
                'vital_key' => 'resp_rate',
                'age_min_days' => 29, 'age_max_days' => 365,
                'normal_min' => 30, 'normal_max' => 50,
                'warning_min' => 25, 'warning_max' => 60,
                'critical_min' => 20, 'critical_max' => 80,
                'notes' => 'Infant Respiratory Rate'
            ],
            [
                'vital_key' => 'bp_sys',
                'age_min_days' => 29, 'age_max_days' => 365,
                'normal_min' => 70, 'normal_max' => 100,
                'warning_min' => 60, 'warning_max' => 110,
                'critical_min' => 50, 'critical_max' => 120,
                'notes' => 'Infant Systolic BP'
            ],
            [
                'vital_key' => 'bp_dia',
                'age_min_days' => 29, 'age_max_days' => 365,
                'normal_min' => 50, 'normal_max' => 70,
                'warning_min' => 40, 'warning_max' => 80,
                'critical_min' => 30, 'critical_max' => 90,
                'notes' => 'Infant Diastolic BP'
            ],
            [
                'vital_key' => 'temp',
                'age_min_days' => 29, 'age_max_days' => 365,
                'normal_min' => 36.4, 'normal_max' => 37.5,
                'warning_min' => 36.0, 'warning_max' => 38.0,
                'critical_min' => 35.0, 'critical_max' => 39.0,
                'notes' => 'Infant Temperature'
            ],
            [
                'vital_key' => 'sugar',
                'age_min_days' => 29, 'age_max_days' => 365,
                'normal_min' => 70, 'normal_max' => 100,
                'warning_min' => 60, 'warning_max' => 140,
                'critical_min' => 50, 'critical_max' => 200,
                'notes' => 'Infant Blood Sugar'
            ],

            // ─────────────────────────────────────────────────────────────────
            // CHILD (1 Year - 12 Years)
            // ─────────────────────────────────────────────────────────────────
            [
                'vital_key' => 'heart_rate',
                'age_min_days' => 366, 'age_max_days' => 4380,
                'normal_min' => 70, 'normal_max' => 120,
                'warning_min' => 60, 'warning_max' => 140,
                'critical_min' => 50, 'critical_max' => 160,
                'notes' => 'Child Heart Rate'
            ],
            [
                'vital_key' => 'resp_rate',
                'age_min_days' => 366, 'age_max_days' => 4380,
                'normal_min' => 18, 'normal_max' => 30,
                'warning_min' => 15, 'warning_max' => 40,
                'critical_min' => 12, 'critical_max' => 50,
                'notes' => 'Child Respiratory Rate'
            ],
            [
                'vital_key' => 'bp_sys',
                'age_min_days' => 366, 'age_max_days' => 4380,
                'normal_min' => 90, 'normal_max' => 115,
                'warning_min' => 80, 'warning_max' => 130,
                'critical_min' => 70, 'critical_max' => 140,
                'notes' => 'Child Systolic BP'
            ],
            [
                'vital_key' => 'bp_dia',
                'age_min_days' => 366, 'age_max_days' => 4380,
                'normal_min' => 60, 'normal_max' => 75,
                'warning_min' => 50, 'warning_max' => 85,
                'critical_min' => 40, 'critical_max' => 95,
                'notes' => 'Child Diastolic BP'
            ],
            [
                'vital_key' => 'temp',
                'age_min_days' => 366, 'age_max_days' => 4380,
                'normal_min' => 36.4, 'normal_max' => 37.5,
                'warning_min' => 36.0, 'warning_max' => 38.0,
                'critical_min' => 35.0, 'critical_max' => 39.0,
                'notes' => 'Child Temperature'
            ],
            [
                'vital_key' => 'sugar',
                'age_min_days' => 366, 'age_max_days' => 4380,
                'normal_min' => 70, 'normal_max' => 100,
                'warning_min' => 60, 'warning_max' => 140,
                'critical_min' => 50, 'critical_max' => 200,
                'notes' => 'Child Blood Sugar'
            ],

            // ─────────────────────────────────────────────────────────────────
            // ADULT (13 Years+)
            // ─────────────────────────────────────────────────────────────────
            [
                'vital_key' => 'temp',
                'age_min_days' => 4381, 'age_max_days' => 43800,
                'normal_min' => 36.1, 'normal_max' => 37.2,
                'warning_min' => 35.5, 'warning_max' => 38.0,
                'critical_min' => 34.0, 'critical_max' => 39.0,
                'notes' => 'Adult Temperature'
            ],
            [
                'vital_key' => 'heart_rate',
                'age_min_days' => 4381, 'age_max_days' => 43800,
                'normal_min' => 60, 'normal_max' => 100,
                'warning_min' => 50, 'warning_max' => 120,
                'critical_min' => 40, 'critical_max' => 150,
                'notes' => 'Adult Heart Rate'
            ],
            [
                'vital_key' => 'resp_rate',
                'age_min_days' => 4381, 'age_max_days' => 43800,
                'normal_min' => 12, 'normal_max' => 20,
                'warning_min' => 10, 'warning_max' => 25,
                'critical_min' => 8, 'critical_max' => 30,
                'notes' => 'Adult Respiratory Rate'
            ],
            [
                'vital_key' => 'bp_sys',
                'age_min_days' => 4381, 'age_max_days' => 43800,
                'normal_min' => 90, 'normal_max' => 140,
                'warning_min' => 80, 'warning_max' => 160,
                'critical_min' => 70, 'critical_max' => 180,
                'notes' => 'Adult Systolic BP'
            ],
            [
                'vital_key' => 'bp_dia',
                'age_min_days' => 4381, 'age_max_days' => 43800,
                'normal_min' => 60, 'normal_max' => 90,
                'warning_min' => 50, 'warning_max' => 100,
                'critical_min' => 40, 'critical_max' => 110,
                'notes' => 'Adult Diastolic BP'
            ],
            [
                'vital_key' => 'spo2',
                'age_min_days' => 0, 'age_max_days' => 43800,
                'normal_min' => 95, 'normal_max' => 100,
                'warning_min' => 92, 'warning_max' => 100,
                'critical_min' => 90, 'critical_max' => 100,
                'notes' => 'Universal SpO2'
            ],
            
            // GENDER SPECIFIC (Example: Lower BP in Adult Females)
            [
                'vital_key' => 'bp_sys',
                'age_min_days' => 6571, 'age_max_days' => 43800,
                'gender' => 'female',
                'normal_min' => 90, 'normal_max' => 130,
                'warning_min' => 80, 'warning_max' => 150,
                'critical_min' => 70, 'critical_max' => 170,
                'notes' => 'Adult Female Systolic BP'
            ],
        ];

        foreach ($data as $item) {
            VitalRange::create($item);
        }
    }
}
