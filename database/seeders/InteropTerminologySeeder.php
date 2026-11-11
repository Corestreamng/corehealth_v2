<?php

namespace Database\Seeders;

use App\Interoperability\Models\InteropTerminologyMap;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Seeder;

class InteropTerminologySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedVitalSigns();
        $this->seedLaboratoryServices();
        $this->seedMedications();
    }

    /**
     * Seed LOINC & UCUM mappings for all CoreHealth vital signs.
     */
    protected function seedVitalSigns(): void
    {
        $vitals = [
            [
                'standard' => 'LOINC',
                'standard_code' => '85354-9',
                'standard_display' => 'Blood pressure panel with all children optional',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'blood_pressure',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '8480-6',
                'standard_display' => 'Systolic blood pressure',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'blood_pressure_systolic',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '8462-4',
                'standard_display' => 'Diastolic blood pressure',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'blood_pressure_diastolic',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '8867-4',
                'standard_display' => 'Heart rate',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'heart_rate',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '8310-5',
                'standard_display' => 'Body temperature',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'temp',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '9279-1',
                'standard_display' => 'Respiratory rate',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'resp_rate',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '29463-7',
                'standard_display' => 'Body weight',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'weight',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '8302-2',
                'standard_display' => 'Body height',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'height',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '39156-5',
                'standard_display' => 'Body mass index (BMI) [Ratio]',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'bmi',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '59408-5',
                'standard_display' => 'Oxygen saturation in Arterial blood by Pulse oximetry',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'spo2',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '2339-0',
                'standard_display' => 'Glucose [Mass/volume] in Blood',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'blood_sugar',
            ],
            [
                'standard' => 'LOINC',
                'standard_code' => '72514-3',
                'standard_display' => 'Pain severity - 0-10 verbal numeric rating [Score] - Reported',
                'system_uri' => 'http://loinc.org',
                'local_entity_type' => 'vital',
                'local_key' => 'pain_score',
            ],
            // UCUM Unit Mappings
            [
                'standard' => 'UCUM',
                'standard_code' => 'mm[Hg]',
                'standard_display' => 'mmHg',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'blood_pressure',
            ],
            [
                'standard' => 'UCUM',
                'standard_code' => 'Cel',
                'standard_display' => '°C',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'temp',
            ],
            [
                'standard' => 'UCUM',
                'standard_code' => '/min',
                'standard_display' => 'beats/min',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'heart_rate',
            ],
            [
                'standard' => 'UCUM',
                'standard_code' => '/min',
                'standard_display' => 'breaths/min',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'resp_rate',
            ],
            [
                'standard' => 'UCUM',
                'standard_code' => 'kg',
                'standard_display' => 'kg',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'weight',
            ],
            [
                'standard' => 'UCUM',
                'standard_code' => 'cm',
                'standard_display' => 'cm',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'height',
            ],
            [
                'standard' => 'UCUM',
                'standard_code' => 'kg/m2',
                'standard_display' => 'kg/m²',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'bmi',
            ],
            [
                'standard' => 'UCUM',
                'standard_code' => '%',
                'standard_display' => '%',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'spo2',
            ],
            [
                'standard' => 'UCUM',
                'standard_code' => 'mg/dL',
                'standard_display' => 'mg/dL',
                'system_uri' => 'http://unitsofmeasure.org',
                'local_entity_type' => 'vital_unit',
                'local_key' => 'blood_sugar',
            ],
        ];

        foreach ($vitals as $vital) {
            InteropTerminologyMap::updateOrCreate(
                [
                    'standard' => $vital['standard'],
                    'standard_code' => $vital['standard_code'],
                    'local_entity_type' => $vital['local_entity_type'],
                    'local_key' => $vital['local_key'],
                ],
                $vital
            );
        }
    }

    /**
     * Dynamically map top laboratory services in database to LOINC codes.
     */
    protected function seedLaboratoryServices(): void
    {
        $labMappings = [
            'malaria' => [
                'code' => '50314-4',
                'display' => 'Plasmodium sp DNA [Presence] in Blood by NAA with probe detection',
            ],
            'fbc' => [
                'code' => '58410-2',
                'display' => 'Complete blood count (hemogram) panel - Blood by Automated count',
            ],
            'full blood' => [
                'code' => '58410-2',
                'display' => 'Complete blood count (hemogram) panel - Blood by Automated count',
            ],
            'pcv' => [
                'code' => '20570-8',
                'display' => 'Hematocrit [Volume Fraction] of Blood',
            ],
            'urinalysis' => [
                'code' => '24356-8',
                'display' => 'Urinalysis complete panel - Urine',
            ],
            'widal' => [
                'code' => '42238-6',
                'display' => 'Salmonella enterica serovar Typhi and Paratyphi antibodies panel - Serum',
            ],
            'glucose' => [
                'code' => '2345-7',
                'display' => 'Glucose [Mass/volume] in Serum or Plasma',
            ],
            'creatinine' => [
                'code' => '2160-0',
                'display' => 'Creatinine [Mass/volume] in Serum or Plasma',
            ],
            'electrolyte' => [
                'code' => '24326-1',
                'display' => 'Electrolytes 1998 panel - Serum or Plasma',
            ],
            'lipid' => [
                'code' => '24331-1',
                'display' => 'Lipid 1996 panel - Serum or Plasma',
            ],
            'genotype' => [
                'code' => '34468-9',
                'display' => 'Hemoglobin electrophoresis panel - Blood',
            ],
            'blood group' => [
                'code' => '883-9',
                'display' => 'ABO and Rh group [Type] in Blood',
            ],
        ];

        foreach ($labMappings as $keyword => $info) {
            $services = Service::where('service_name', 'LIKE', "%{$keyword}%")->get();
            foreach ($services as $service) {
                InteropTerminologyMap::updateOrCreate(
                    [
                        'standard' => 'LOINC',
                        'local_entity_type' => 'service',
                        'local_entity_id' => $service->id,
                    ],
                    [
                        'standard_code' => $info['code'],
                        'standard_display' => $info['display'],
                        'system_uri' => 'http://loinc.org',
                        'local_key' => 'lab_service',
                    ]
                );
            }
        }
    }

    /**
     * Dynamically map top pharmacy formulary products to WHO ATC codes.
     */
    protected function seedMedications(): void
    {
        $atcMappings = [
            'paracetamol' => [
                'code' => 'N02BE01',
                'display' => 'paracetamol',
            ],
            'amoxicillin' => [
                'code' => 'J01CA04',
                'display' => 'amoxicillin',
            ],
            'ibuprofen' => [
                'code' => 'M01AE01',
                'display' => 'ibuprofen',
            ],
            'artemether' => [
                'code' => 'P01BF01',
                'display' => 'artemether and lumefantrine',
            ],
            'metformin' => [
                'code' => 'A10BA02',
                'display' => 'metformin',
            ],
            'amlodipine' => [
                'code' => 'C08CA01',
                'display' => 'amlodipine',
            ],
            'ciprofloxacin' => [
                'code' => 'J01MA02',
                'display' => 'ciprofloxacin',
            ],
            'omeprazole' => [
                'code' => 'A02BC01',
                'display' => 'omeprazole',
            ],
            'ceftriaxone' => [
                'code' => 'J01DD04',
                'display' => 'ceftriaxone',
            ],
            'dextrose' => [
                'code' => 'B05BA03',
                'display' => 'carbohydrates (dextrose solutions)',
            ],
        ];

        foreach ($atcMappings as $keyword => $info) {
            $products = Product::where('product_name', 'LIKE', "%{$keyword}%")->get();
            foreach ($products as $product) {
                InteropTerminologyMap::updateOrCreate(
                    [
                        'standard' => 'ATC',
                        'local_entity_type' => 'product',
                        'local_entity_id' => $product->id,
                    ],
                    [
                        'standard_code' => $info['code'],
                        'standard_display' => $info['display'],
                        'system_uri' => 'http://www.whocc.no/atc',
                        'local_key' => 'medication',
                    ]
                );
            }
        }
    }
}
