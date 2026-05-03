<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Clinic;

class ClinicVitalsTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $standardTemplate = [
            ['name' => 'bloodPressure', 'label' => 'Blood Pressure', 'type' => 'text', 'placeholder' => '120/80', 'required' => false, 'pattern' => '\d{2,3}/\d{2,3}', 'unit' => 'mmHg', 'hint' => 'Normal: 90-140 / 60-90'],
            ['name' => 'bodyTemperature', 'label' => 'Temperature', 'type' => 'number', 'placeholder' => '36.5', 'required' => false, 'step' => '0.1', 'min' => 34, 'max' => 42, 'unit' => '°C', 'hint' => 'Normal: 36.1-37.2°C'],
            ['name' => 'heartRate', 'label' => 'Heart Rate', 'type' => 'number', 'placeholder' => '72', 'required' => false, 'unit' => 'bpm', 'hint' => 'Normal: 60-100 bpm'],
            ['name' => 'respiratoryRate', 'label' => 'Respiratory Rate', 'type' => 'number', 'placeholder' => '16', 'required' => false, 'unit' => 'bpm', 'hint' => 'Normal: 12-20 bpm'],
            ['name' => 'spo2', 'label' => 'SpO2 (Oxygen)', 'type' => 'number', 'placeholder' => '98', 'required' => false, 'unit' => '%', 'hint' => 'Normal: 95-100%'],
            ['name' => 'bodyWeight', 'label' => 'Weight', 'type' => 'number', 'placeholder' => '70', 'required' => false, 'unit' => 'kg'],
            ['name' => 'height', 'label' => 'Height', 'type' => 'number', 'placeholder' => '170', 'required' => false, 'unit' => 'cm'],
            ['name' => 'bloodSugar', 'label' => 'Blood Sugar', 'type' => 'number', 'placeholder' => '100', 'required' => false, 'unit' => 'mg/dL', 'hint' => 'Fasting: 70-100'],
            ['name' => 'painScore', 'label' => 'Pain Score', 'type' => 'select', 'options' => [0,1,2,3,4,5,6,7,8,9,10], 'required' => false, 'hint' => '0=None, 10=Severe'],
        ];

        $ophthalmologyExtras = [
            ['name' => 'iop_right', 'label' => 'IOP (Right Eye)', 'type' => 'number', 'placeholder' => '15', 'required' => false, 'unit' => 'mmHg'],
            ['name' => 'iop_left', 'label' => 'IOP (Left Eye)', 'type' => 'number', 'placeholder' => '15', 'required' => false, 'unit' => 'mmHg'],
            ['name' => 'visual_acuity_right', 'label' => 'Visual Acuity (Right)', 'type' => 'text', 'placeholder' => '6/6', 'required' => false],
            ['name' => 'visual_acuity_left', 'label' => 'Visual Acuity (Left)', 'type' => 'text', 'placeholder' => '6/6', 'required' => false],
        ];

        $maternityExtras = [
            ['name' => 'fundal_height', 'label' => 'Fundal Height', 'type' => 'number', 'placeholder' => '28', 'required' => false, 'unit' => 'cm'],
            ['name' => 'fhr', 'label' => 'Fetal Heart Rate', 'type' => 'number', 'placeholder' => '140', 'required' => false, 'unit' => 'bpm'],
            ['name' => 'fetal_position', 'label' => 'Fetal Position', 'type' => 'text', 'placeholder' => 'LOA', 'required' => false],
            ['name' => 'fetal_presentation', 'label' => 'Fetal Presentation', 'type' => 'text', 'placeholder' => 'Cephalic', 'required' => false],
        ];

        $pediatricsExtras = [
            ['name' => 'head_circumference', 'label' => 'Head Circumference', 'type' => 'number', 'placeholder' => '35', 'required' => false, 'unit' => 'cm'],
            ['name' => 'mid_upper_arm_circ', 'label' => 'MUAC', 'type' => 'number', 'placeholder' => '12.5', 'required' => false, 'unit' => 'cm'],
        ];

        $cardiologyExtras = [
            ['name' => 'oxygen_flow_rate', 'label' => 'O2 Flow Rate', 'type' => 'number', 'placeholder' => '2', 'required' => false, 'unit' => 'L/min'],
            ['name' => 'ecg_notes', 'label' => 'ECG Rhythm/Notes', 'type' => 'text', 'placeholder' => 'Normal Sinus', 'required' => false],
        ];

        $emergencyExtras = [
            ['name' => 'esi_level', 'label' => 'ESI Level', 'type' => 'select', 'options' => [1, 2, 3, 4, 5], 'required' => false, 'hint' => '1=Resuscitation, 5=Non-urgent'],
            ['name' => 'gcs_score', 'label' => 'GCS Score', 'type' => 'number', 'min' => 3, 'max' => 15, 'placeholder' => '15', 'required' => false, 'hint' => '3-15'],
        ];

        $clinics = Clinic::all();

        foreach ($clinics as $clinic) {
            $template = $standardTemplate;
            $name = strtolower($clinic->name);
            
            if (str_contains($name, 'ophthalmology')) {
                $template = array_merge($template, $ophthalmologyExtras);
            } elseif (str_contains($name, 'pediatrics') || str_contains($name, 'neonatology')) {
                $template = array_merge($template, $pediatricsExtras);
            } elseif (str_contains($name, 'gynecology') || str_contains($name, 'obstetrics')) {
                $template = array_merge($template, $maternityExtras);
            } elseif (str_contains($name, 'cardiology')) {
                $template = array_merge($template, $cardiologyExtras);
            } elseif (str_contains($name, 'emergency')) {
                $template = array_merge($template, $emergencyExtras);
            }
            
            $clinic->update(['vitals_template' => $template]);
        }
    }
}
