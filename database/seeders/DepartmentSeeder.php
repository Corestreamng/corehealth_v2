<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Seed comprehensive list of hospital departments
 * Based on organizational structure and roles in the system
 */
class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            // Clinical Departments
            [
                'name' => 'Medical Department',
                'code' => 'MED',
                'description' => 'General medicine, internal medicine, and medical consultations',
                'is_active' => true,
            ],
            [
                'name' => 'Surgical Department',
                'code' => 'SURG',
                'description' => 'Surgical services, operating theatre, and post-operative care',
                'is_active' => true,
            ],
            [
                'name' => 'Obstetrics & Gynaecology',
                'code' => 'OBG',
                'description' => 'Maternal health, childbirth, and women\'s reproductive health',
                'is_active' => true,
            ],
            [
                'name' => 'Paediatrics Department',
                'code' => 'PAED',
                'description' => 'Child health services and neonatal care',
                'is_active' => true,
            ],
            [
                'name' => 'Emergency Department',
                'code' => 'EMG',
                'description' => 'Emergency and accident services, trauma care',
                'is_active' => true,
            ],
            [
                'name' => 'Outpatient Department',
                'code' => 'OPD',
                'description' => 'Outpatient consultations and clinic services',
                'is_active' => true,
            ],
            [
                'name' => 'Intensive Care Unit',
                'code' => 'ICU',
                'description' => 'Critical care and intensive monitoring services',
                'is_active' => true,
            ],

            // Nursing Department
            [
                'name' => 'Nursing Department',
                'code' => 'NURS',
                'description' => 'Nursing services, patient care, and ward management',
                'is_active' => true,
            ],

            // Diagnostic Departments
            [
                'name' => 'Laboratory Department',
                'code' => 'LAB',
                'description' => 'Medical laboratory, pathology, and diagnostic testing',
                'is_active' => true,
            ],
            [
                'name' => 'Radiology Department',
                'code' => 'RAD',
                'description' => 'Imaging services, X-ray, CT scan, MRI, and ultrasound',
                'is_active' => true,
            ],

            // Pharmacy
            [
                'name' => 'Pharmacy Department',
                'code' => 'PHAR',
                'description' => 'Pharmaceutical services, drug dispensing, and medication management',
                'is_active' => true,
            ],

            // Support Services
            [
                'name' => 'Health Records Department',
                'code' => 'HRD',
                'description' => 'Medical records, patient registration, and health information management',
                'is_active' => true,
            ],
            [
                'name' => 'Reception & Front Office',
                'code' => 'REC',
                'description' => 'Patient reception, scheduling, and front desk services',
                'is_active' => true,
            ],

            // Administrative Departments
            [
                'name' => 'Administration',
                'code' => 'ADMIN',
                'description' => 'Hospital administration, management, and general operations',
                'is_active' => true,
            ],
            [
                'name' => 'Human Resources',
                'code' => 'HR',
                'description' => 'Staff management, recruitment, payroll, and employee relations',
                'is_active' => true,
            ],
            [
                'name' => 'Finance & Accounts',
                'code' => 'FIN',
                'description' => 'Financial management, billing, accounts payable/receivable',
                'is_active' => true,
            ],
            [
                'name' => 'Billing Department',
                'code' => 'BILL',
                'description' => 'Patient billing, invoicing, and payment processing',
                'is_active' => true,
            ],
            [
                'name' => 'HMO & Insurance',
                'code' => 'HMO',
                'description' => 'Health insurance management, HMO relations, and claims processing',
                'is_active' => true,
            ],

            // Store & Inventory
            [
                'name' => 'Store & Inventory',
                'code' => 'STOR',
                'description' => 'Inventory management, supplies, and procurement',
                'is_active' => true,
            ],
            [
                'name' => 'Procurement Department',
                'code' => 'PROC',
                'description' => 'Purchasing, vendor management, and supply chain',
                'is_active' => true,
            ],

            // IT & Technical
            [
                'name' => 'Information Technology',
                'code' => 'IT',
                'description' => 'IT systems, software support, and technical infrastructure',
                'is_active' => true,
            ],
            [
                'name' => 'Biomedical Engineering',
                'code' => 'BME',
                'description' => 'Medical equipment maintenance and calibration',
                'is_active' => true,
            ],

            // Facility Management
            [
                'name' => 'Facility Management',
                'code' => 'FAC',
                'description' => 'Building maintenance, housekeeping, and facility services',
                'is_active' => true,
            ],
            [
                'name' => 'Maintenance Department',
                'code' => 'MAINT',
                'description' => 'Equipment and infrastructure maintenance',
                'is_active' => true,
            ],
            [
                'name' => 'Security Department',
                'code' => 'SEC',
                'description' => 'Hospital security and safety services',
                'is_active' => true,
            ],

            // Specialized Units
            [
                'name' => 'Dental Department',
                'code' => 'DENT',
                'description' => 'Dental services and oral health care',
                'is_active' => true,
            ],
            [
                'name' => 'Ophthalmology Department',
                'code' => 'OPH',
                'description' => 'Eye care, vision services, and optical department',
                'is_active' => true,
            ],
            [
                'name' => 'Physiotherapy Department',
                'code' => 'PHYS',
                'description' => 'Physical therapy and rehabilitation services',
                'is_active' => true,
            ],
            [
                'name' => 'Nutrition & Dietetics',
                'code' => 'NUTR',
                'description' => 'Dietary services, nutrition counseling, and food services',
                'is_active' => true,
            ],
            [
                'name' => 'Mental Health Department',
                'code' => 'MH',
                'description' => 'Psychiatry, psychology, and mental health services',
                'is_active' => true,
            ],
            [
                'name' => 'Social Services',
                'code' => 'SOC',
                'description' => 'Medical social work and patient support services',
                'is_active' => true,
            ],

            // Quality & Compliance
            [
                'name' => 'Quality Assurance',
                'code' => 'QA',
                'description' => 'Quality control, accreditation, and compliance',
                'is_active' => true,
            ],
            [
                'name' => 'Infection Control',
                'code' => 'IPC',
                'description' => 'Infection prevention and control unit',
                'is_active' => true,
            ],

            // Training & Education
            [
                'name' => 'Medical Education',
                'code' => 'EDU',
                'description' => 'Training, continuing medical education, and internship programs',
                'is_active' => true,
            ],

            // Customer Service
            [
                'name' => 'Patient Relations',
                'code' => 'PR',
                'description' => 'Patient experience, complaints handling, and customer service',
                'is_active' => true,
            ],

            // Ancillary Services
            [
                'name' => 'Mortuary Services',
                'code' => 'MORT',
                'description' => 'Mortuary and funeral arrangement services',
                'is_active' => true,
            ],
            [
                'name' => 'Ambulance Services',
                'code' => 'AMB',
                'description' => 'Emergency transport and ambulance services',
                'is_active' => true,
            ],
            [
                'name' => 'Blood Bank',
                'code' => 'BB',
                'description' => 'Blood collection, storage, and transfusion services',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                $dept
            );
        }

        $this->command->info('Departments seeded successfully! Total: ' . count($departments));
    }
}
