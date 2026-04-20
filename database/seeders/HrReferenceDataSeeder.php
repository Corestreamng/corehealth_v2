<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Nigerian standard grade levels (GL 01-17 / CONHESS / CONMESS),
 * common hospital cadres, and standard units
 */
class HrReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCadres();
        $this->seedGradeLevels();
        $this->seedUnits();
    }

    private function seedCadres(): void
    {
        $cadres = [
            ['name' => 'Medical', 'code' => 'MED', 'description' => 'Medical Officers, Consultants, Registrars'],
            ['name' => 'Nursing', 'code' => 'NUR', 'description' => 'Registered Nurses, Midwives, Nursing Officers'],
            ['name' => 'Pharmacy', 'code' => 'PHR', 'description' => 'Pharmacists, Pharmacy Technicians'],
            ['name' => 'Laboratory', 'code' => 'LAB', 'description' => 'Medical Lab Scientists, Lab Technicians'],
            ['name' => 'Radiology', 'code' => 'RAD', 'description' => 'Radiologists, Radiographers, Imaging Technicians'],
            ['name' => 'Health Records', 'code' => 'HRO', 'description' => 'Health Records Officers, Data Clerks'],
            ['name' => 'Physiotherapy', 'code' => 'PHY', 'description' => 'Physiotherapists, Rehabilitation Officers'],
            ['name' => 'Administration', 'code' => 'ADM', 'description' => 'Admin Officers, HR, Finance, Procurement'],
            ['name' => 'Engineering / Works', 'code' => 'ENG', 'description' => 'Biomedical Engineers, Maintenance Staff'],
            ['name' => 'Health Education', 'code' => 'HED', 'description' => 'Health Educators, Community Health Workers'],
            ['name' => 'Dental', 'code' => 'DEN', 'description' => 'Dentists, Dental Therapists, Dental Technologists'],
            ['name' => 'Environmental Health', 'code' => 'ENV', 'description' => 'Environmental Health Officers, Sanitary Inspectors'],
            ['name' => 'Nutrition / Dietetics', 'code' => 'NUT', 'description' => 'Dietitians, Nutritionists'],
            ['name' => 'Social Work', 'code' => 'SOC', 'description' => 'Medical Social Workers'],
            ['name' => 'Security', 'code' => 'SEC', 'description' => 'Security Officers, Guards'],
            ['name' => 'Support Staff', 'code' => 'SUP', 'description' => 'Cleaners, Porters, Drivers, Artisans'],
            ['name' => 'ICT', 'code' => 'ICT', 'description' => 'IT Officers, Systems Administrators, Software Developers'],
        ];

        foreach ($cadres as $cadre) {
            DB::table('cadres')->updateOrInsert(
                ['code' => $cadre['code']],
                array_merge($cadre, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    private function seedGradeLevels(): void
    {
        // Nigerian CONHESS structure (Consolidated Health Salary Structure)
        // GL 01-06: Junior Staff, GL 07-10: Senior Staff, GL 12-17: Management
        $levels = [
            ['name' => 'GL 01', 'level' => 1, 'step' => 1, 'description' => 'Cleaner, Messenger, Ward Attendant', 'min_years_to_next' => 2, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 30000, 'max_salary' => 45000],
            ['name' => 'GL 02', 'level' => 2, 'step' => 1, 'description' => 'Office Assistant, Security Guard', 'min_years_to_next' => 2, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 33000, 'max_salary' => 50000],
            ['name' => 'GL 03', 'level' => 3, 'step' => 1, 'description' => 'Driver, Porter, Store Assistant', 'min_years_to_next' => 2, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 36000, 'max_salary' => 55000],
            ['name' => 'GL 04', 'level' => 4, 'step' => 1, 'description' => 'Clerical Officer, Typist, Data Entry', 'min_years_to_next' => 3, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 43000, 'max_salary' => 65000],
            ['name' => 'GL 05', 'level' => 5, 'step' => 1, 'description' => 'Senior Clerical Officer, Artisan', 'min_years_to_next' => 3, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 50000, 'max_salary' => 75000],
            ['name' => 'GL 06', 'level' => 6, 'step' => 1, 'description' => 'Executive Officer, Lab Attendant', 'min_years_to_next' => 3, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 60000, 'max_salary' => 90000],
            ['name' => 'GL 07', 'level' => 7, 'step' => 1, 'description' => 'Nursing Officer, Health Records Officer', 'min_years_to_next' => 3, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 80000, 'max_salary' => 120000],
            ['name' => 'GL 08', 'level' => 8, 'step' => 1, 'description' => 'Senior Nursing Officer, Pharmacist II', 'min_years_to_next' => 3, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 100000, 'max_salary' => 150000],
            ['name' => 'GL 09', 'level' => 9, 'step' => 1, 'description' => 'Medical Officer, Principal Nursing Officer', 'min_years_to_next' => 3, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 130000, 'max_salary' => 200000],
            ['name' => 'GL 10', 'level' => 10, 'step' => 1, 'description' => 'Senior Medical Officer, Chief Nursing Officer', 'min_years_to_next' => 3, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 170000, 'max_salary' => 260000],
            ['name' => 'GL 12', 'level' => 12, 'step' => 1, 'description' => 'Principal Medical Officer, Asst. Director', 'min_years_to_next' => 4, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 230000, 'max_salary' => 350000],
            ['name' => 'GL 13', 'level' => 13, 'step' => 1, 'description' => 'Senior Registrar, Deputy Director', 'min_years_to_next' => 4, 'retirement_age' => 60, 'max_years_of_service' => 35, 'min_salary' => 300000, 'max_salary' => 450000],
            ['name' => 'GL 14', 'level' => 14, 'step' => 1, 'description' => 'Consultant, Director', 'min_years_to_next' => 4, 'retirement_age' => 65, 'max_years_of_service' => 35, 'min_salary' => 400000, 'max_salary' => 600000],
            ['name' => 'GL 15', 'level' => 15, 'step' => 1, 'description' => 'Senior Consultant, Deputy CMD', 'min_years_to_next' => null, 'retirement_age' => 65, 'max_years_of_service' => 35, 'min_salary' => 500000, 'max_salary' => 750000],
            ['name' => 'GL 16', 'level' => 16, 'step' => 1, 'description' => 'Chief Consultant, CMD', 'min_years_to_next' => null, 'retirement_age' => 70, 'max_years_of_service' => 35, 'min_salary' => 650000, 'max_salary' => 1000000],
            ['name' => 'GL 17', 'level' => 17, 'step' => 1, 'description' => 'Permanent Secretary Level', 'min_years_to_next' => null, 'retirement_age' => 70, 'max_years_of_service' => 35, 'min_salary' => 800000, 'max_salary' => 1300000],
        ];

        foreach ($levels as $level) {
            DB::table('grade_levels')->updateOrInsert(
                ['name' => $level['name']],
                array_merge($level, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    private function seedUnits(): void
    {
        // Common hospital units — these get linked to departments after departments are set up
        // We create them without department_id; admin can assign later
        $units = [
            ['name' => 'Outpatient Unit', 'code' => 'OPD'],
            ['name' => 'Inpatient / Ward Unit', 'code' => 'IPD'],
            ['name' => 'Emergency Unit', 'code' => 'A&E'],
            ['name' => 'Operating Theatre', 'code' => 'OT'],
            ['name' => 'Intensive Care Unit', 'code' => 'ICU'],
            ['name' => 'Neonatal Unit', 'code' => 'NICU'],
            ['name' => 'Maternity Unit', 'code' => 'MAT'],
            ['name' => 'Antenatal Clinic', 'code' => 'ANC'],
            ['name' => 'Immunisation Unit', 'code' => 'IMM'],
            ['name' => 'Family Planning Unit', 'code' => 'FP'],
            ['name' => 'Dialysis Unit', 'code' => 'DIA'],
            ['name' => 'Endoscopy Unit', 'code' => 'ENDO'],
            ['name' => 'Physiotherapy Unit', 'code' => 'PHYSIO'],
            ['name' => 'Medical Records Unit', 'code' => 'MRU'],
            ['name' => 'Accounts / Finance Unit', 'code' => 'FIN'],
            ['name' => 'Human Resources Unit', 'code' => 'HRU'],
            ['name' => 'Procurement / Stores', 'code' => 'PROC'],
            ['name' => 'General Administration', 'code' => 'GA'],
            ['name' => 'CSSD', 'code' => 'CSSD'],
            ['name' => 'Blood Bank', 'code' => 'BB'],
            ['name' => 'Mortuary', 'code' => 'MORT'],
        ];

        foreach ($units as $unit) {
            DB::table('units')->updateOrInsert(
                ['code' => $unit['code']],
                array_merge($unit, [
                    'department_id' => null,
                    'head_of_unit_id' => null,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
