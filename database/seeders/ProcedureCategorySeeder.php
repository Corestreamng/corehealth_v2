<?php

namespace Database\Seeders;

use App\Models\ProcedureCategory;
use Illuminate\Database\Seeder;

class ProcedureCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds all procedure categories (surgical specialties).
     *
     * @return void
     */
    public function run()
    {
        $categories = [
            [
                'name' => 'General Surgery',
                'code' => 'GS',
                'description' => 'General surgical procedures including abdominal, breast, skin, and soft tissue surgeries',
            ],
            [
                'name' => 'ENT (Ear, Nose & Throat)',
                'code' => 'ENT',
                'description' => 'Otorhinolaryngology procedures including tonsillectomy, adenoidectomy, and ear surgeries',
            ],
            [
                'name' => 'Ophthalmology',
                'code' => 'OPH',
                'description' => 'Eye surgeries including cataract, glaucoma, and retinal procedures',
            ],
            [
                'name' => 'Dental & Oral Surgery',
                'code' => 'DENT',
                'description' => 'Dental extractions, implants, and maxillofacial surgeries',
            ],
            [
                'name' => 'Obstetrics & Gynaecology',
                'code' => 'OG',
                'description' => 'Caesarean sections, hysterectomies, D&C, and other O&G procedures',
            ],
            [
                'name' => 'Orthopaedic Surgery',
                'code' => 'ORTH',
                'description' => 'Bone and joint surgeries including fracture fixation, joint replacement',
            ],
            [
                'name' => 'Urology',
                'code' => 'URO',
                'description' => 'Urinary tract and male reproductive system procedures',
            ],
            [
                'name' => 'Cardiothoracic Surgery',
                'code' => 'CTS',
                'description' => 'Heart and chest cavity surgical procedures',
            ],
            [
                'name' => 'Neurosurgery',
                'code' => 'NEURO',
                'description' => 'Brain, spine, and nervous system surgical procedures',
            ],
            [
                'name' => 'Plastic & Reconstructive Surgery',
                'code' => 'PLAS',
                'description' => 'Cosmetic and reconstructive surgical procedures',
            ],
            [
                'name' => 'Paediatric Surgery',
                'code' => 'PAED',
                'description' => 'Surgical procedures for infants, children, and adolescents',
            ],
            [
                'name' => 'Endoscopy',
                'code' => 'ENDO',
                'description' => 'Endoscopic procedures including gastroscopy, colonoscopy, bronchoscopy',
            ],
            [
                'name' => 'Minor Procedures',
                'code' => 'MINOR',
                'description' => 'Minor surgical and office-based procedures',
            ],
            [
                'name' => 'Vascular Surgery',
                'code' => 'VASC',
                'description' => 'Blood vessel and circulatory system surgical procedures',
            ],
            [
                'name' => 'Laparoscopic Surgery',
                'code' => 'LAP',
                'description' => 'Minimally invasive laparoscopic procedures',
            ],
        ];

        foreach ($categories as $category) {
            ProcedureCategory::firstOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => 1,
                ]
            );
        }

        $this->command->info("Seeded " . count($categories) . " procedure categories successfully!");
    }
}
