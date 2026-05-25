<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcedureConsentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $defaultTemplate = <<<HTML
<h3 style="text-align: center; color: #333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 700; margin-bottom: 25px;">INFORMED CONSENT FOR SURGICAL / INVASIVE PROCEDURE</h3>
<p><strong>Hospital/Facility:</strong> {hospital_name}</p>
<p><strong>Patient Name:</strong> {patient_name}</p>
<p><strong>Procedure / Treatment:</strong> {procedure_name}</p>
<p><strong>Primary Doctor / Surgeon:</strong> {doctor_name}</p>
<p><strong>Date:</strong> {date}</p>

<hr style="border: 0; border-top: 1px solid #dee2e6; margin: 25px 0;" />

<h4 style="color: #495057; font-weight: 600; font-size: 15px; margin-top: 20px;">1. Voluntary Consent</h4>
<p style="text-align: justify; line-height: 1.6;">I, <strong>{patient_name}</strong>, hereby authorize and consent to the performance of the clinical procedure known as <strong>{procedure_name}</strong>. The procedure is to be performed under the supervision and direction of <strong>{doctor_name}</strong> along with any qualified assistants, associates, or designees.</p>

<h4 style="color: #495057; font-weight: 600; font-size: 15px; margin-top: 20px;">2. Understanding of Risks and Alternatives</h4>
<p style="text-align: justify; line-height: 1.6;">The nature, purpose, anticipated benefits, potential risks, and possible complications associated with this procedure, as well as reasonable alternative treatments, have been fully explained to me by the medical team. I understand that medical and surgical procedures carry inherent risks, including but not limited to infection, bleeding, anesthesia reactions, or unexpected complications.</p>

<h4 style="color: #495057; font-weight: 600; font-size: 15px; margin-top: 20px;">3. Acknowledgment of Voluntary Consent</h4>
<p style="text-align: justify; line-height: 1.6;">By signing this document, I acknowledge that:
<ul style="padding-left: 20px; line-height: 1.6;">
  <li>I have read this form (or had it read and explained to me) and fully understand its contents.</li>
  <li>All of my questions regarding the procedure, its risks, benefits, and alternatives have been answered to my satisfaction.</li>
  <li>I understand that no absolute guarantee or assurance has been given as to the outcome of this procedure.</li>
  <li>I voluntarily give my informed consent to proceed with the procedure.</li>
</ul>
</p>
HTML;

        // Check if there is an application settings record
        $first = DB::table('application_status')->first();
        if ($first) {
            DB::table('application_status')->where('id', $first->id)->update([
                'consent_template' => $defaultTemplate
            ]);
        } else {
            // Seed a default record
            DB::table('application_status')->insert([
                'site_name' => 'Hospital Management System',
                'consent_template' => $defaultTemplate,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
