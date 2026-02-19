<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Clinic;
use App\Models\ClinicNoteTemplate;

class ClinicNoteTemplateSeeder extends Seeder
{
    /**
     * Seed additional clinics and clinical note templates.
     *
     * Templates follow international best-practice clerking formats:
     *  - Initial Consultation / First Visit (SOAP-based)
     *  - Ward Round Note
     *  - Follow-Up Visit
     *  - Discharge Summary
     *  - Procedure Note
     *  - Referral Letter
     *  - Emergency Assessment (ABCDE)
     *  - Death Summary
     *  - Plus specialty-specific templates per clinic
     *
     * Run: php artisan db:seed --class=ClinicNoteTemplateSeeder
     */
    public function run(): void
    {
        $this->seedGlobalTemplates();
        $this->seedSpecialtyTemplates();
        $this->printSummary();
    }

    // ─── 2. Global templates (clinic_id = null → all clinics) ─────────────

    private function seedGlobalTemplates(): void
    {
        $this->command->info('Seeding global templates...');
        $count = 0;

        foreach ($this->globalTemplates() as $t) {
            if (!ClinicNoteTemplate::whereNull('clinic_id')->where('name', $t['name'])->exists()) {
                ClinicNoteTemplate::create([
                    'clinic_id'   => null,
                    'name'        => $t['name'],
                    'description' => $t['description'],
                    'content'     => $t['content'],
                    'category'    => $t['category'],
                    'sort_order'  => $t['sort_order'],
                    'is_active'   => true,
                    'created_by'  => 1,
                ]);
                $count++;
                $this->command->line("  + {$t['category']}: {$t['name']}");
            }
        }

        $this->command->info("Global templates seeded: {$count}");
    }

    // ─── 3. Specialty templates ───────────────────────────────────────────

    private function seedSpecialtyTemplates(): void
    {
        $this->command->info('Seeding specialty templates...');
        $count = 0;

        foreach ($this->specialtyTemplates() as $clinicName => $templates) {
            $clinic = Clinic::where('name', $clinicName)->first();
            if (!$clinic) {
                $this->command->warn("  Clinic not found: {$clinicName} — skipping");
                continue;
            }

            foreach ($templates as $idx => $t) {
                if (!ClinicNoteTemplate::where('clinic_id', $clinic->id)->where('name', $t['name'])->exists()) {
                    ClinicNoteTemplate::create([
                        'clinic_id'   => $clinic->id,
                        'name'        => $t['name'],
                        'description' => $t['description'],
                        'content'     => $t['content'],
                        'category'    => $t['category'],
                        'sort_order'  => $idx + 10,
                        'is_active'   => true,
                        'created_by'  => 1,
                    ]);
                    $count++;
                    $this->command->line("  + [{$clinicName}] {$t['category']}: {$t['name']}");
                }
            }
        }

        $this->command->info("Specialty templates seeded: {$count}");
    }

    // ─── Summary ──────────────────────────────────────────────────────────

    private function printSummary(): void
    {
        $globalCount = ClinicNoteTemplate::whereNull('clinic_id')->count();
        $specialtyCount = ClinicNoteTemplate::whereNotNull('clinic_id')->count();
        $totalClinics = Clinic::count();

        // Check which clinics still have no specialty template
        $coveredClinicIds = ClinicNoteTemplate::whereNotNull('clinic_id')
            ->distinct()
            ->pluck('clinic_id')
            ->toArray();
        $uncovered = Clinic::whereNotIn('id', $coveredClinicIds)
            ->where('name', '!=', 'Blank')
            ->pluck('name')
            ->toArray();

        $this->command->newLine();
        $this->command->info("=== SUMMARY ===");
        $this->command->info("Global templates:    {$globalCount}");
        $this->command->info("Specialty templates: {$specialtyCount}");
        $this->command->info("Total clinics:       {$totalClinics}");
        $this->command->info("Clinics covered:     " . count($coveredClinicIds) . "/{$totalClinics}");

        if (count($uncovered) > 0) {
            $this->command->warn('Clinics still missing specialty templates: ' . implode(', ', $uncovered));
        } else {
            $this->command->info('All clinics have at least one specialty template.');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  TEMPLATE DATA
    // ═══════════════════════════════════════════════════════════════════════

    private function globalTemplates(): array
    {
        return [
            [
                'name'        => 'Initial Consultation (SOAP)',
                'category'    => 'First Visit',
                'description' => 'Standard SOAP-format clerking for a new patient\'s first visit',
                'sort_order'  => 1,
                'content'     => '<h3>INITIAL CONSULTATION</h3>

<h4>S — Subjective</h4>
<p><strong>Chief Complaint:</strong><br>[State the patient\'s main complaint in their own words with duration]</p>
<p><strong>History of Presenting Illness (HPI):</strong><br>[Onset, Character, Location, Radiation, Association, Timing, Exacerbating/Relieving factors, Severity (SOCRATES)]</p>
<p><strong>Past Medical History:</strong><br>[Chronic illnesses, surgeries, hospitalizations]</p>
<p><strong>Drug History &amp; Allergies:</strong><br>[Current medications, known allergies]</p>
<p><strong>Family History:</strong><br>[Relevant family conditions]</p>
<p><strong>Social History:</strong><br>[Occupation, smoking, alcohol, exercise, living situation]</p>
<p><strong>Review of Systems:</strong><br>CVS: / RS: / GIT: / GUS: / MSS: / CNS: / Endo: / Psych:</p>

<h4>O — Objective</h4>
<p><strong>General Examination:</strong><br>[Appearance, alertness, distress level, BMI]</p>
<p><strong>Vital Signs:</strong><br>BP: / HR: / Temp: / RR: / SpO2: / Pain:</p>
<p><strong>Systemic Examination:</strong></p>
<table class="table" border="1">
<tr><th>System</th><th>Findings</th></tr>
<tr><td>CVS</td><td>[Heart sounds, murmurs, JVP, peripheral pulses]</td></tr>
<tr><td>RS</td><td>[Air entry, breath sounds, percussion]</td></tr>
<tr><td>Abdomen</td><td>[Soft/tender, organomegaly, bowel sounds]</td></tr>
<tr><td>CNS</td><td>[GCS, pupils, power, tone, reflexes, sensation]</td></tr>
<tr><td>MSS</td><td>[Joint inspection, ROM, deformities]</td></tr>
</table>

<h4>A — Assessment</h4>
<p><strong>Working Diagnosis:</strong><br>[Primary diagnosis]</p>
<p><strong>Differential Diagnoses:</strong></p>
<ol><li></li><li></li><li></li></ol>

<h4>P — Plan</h4>
<p><strong>Investigations:</strong><br>[Labs, imaging, special tests ordered]</p>
<p><strong>Treatment:</strong><br>[Medications, non-pharmacological measures]</p>
<p><strong>Patient Education:</strong><br>[Counselling points, lifestyle modifications]</p>
<p><strong>Follow-Up:</strong><br>[When, where, what to watch for]</p>
<p><strong>Disposition:</strong> [ ] Discharge &nbsp; [ ] Admit &nbsp; [ ] Refer</p>',
            ],

            [
                'name'        => 'Ward Round Note',
                'category'    => 'Ward Round',
                'description' => 'Daily ward round documentation following ISBAR format',
                'sort_order'  => 2,
                'content'     => '<h3>WARD ROUND NOTE</h3>
<p><strong>Day of Admission:</strong> [Day #] &nbsp; | &nbsp; <strong>Bed:</strong> [Ward/Bed]</p>

<h4>I — Identification</h4>
<p>[Age/Sex] admitted on [date] with [primary diagnosis]. Day [#] post-admission / Day [#] post-op [procedure].</p>

<h4>S — Situation</h4>
<p><strong>Overnight Events:</strong><br>[Any significant events, nurse concerns, new symptoms]</p>
<p><strong>Patient Complaints:</strong><br>[Current symptoms, pain score, sleep quality]</p>

<h4>B — Background</h4>
<p><strong>Admission Diagnosis:</strong> [Diagnosis]</p>
<p><strong>Key History:</strong> [Relevant PMH, allergies]</p>
<p><strong>Current Treatment:</strong> [Active medications, IV fluids, O2]</p>

<h4>A — Assessment</h4>
<table class="table" border="1">
<tr><th>Parameter</th><th>Value</th></tr>
<tr><td>BP</td><td></td></tr>
<tr><td>HR</td><td></td></tr>
<tr><td>Temp</td><td></td></tr>
<tr><td>RR</td><td></td></tr>
<tr><td>SpO2</td><td></td></tr>
</table>
<p><strong>General:</strong> [Appearance, hydration, mobility]</p>
<p><strong>Focused Examination:</strong><br>[System-specific findings relevant to admission diagnosis]</p>
<p><strong>Investigations:</strong><br>[New results — labs, imaging, cultures]</p>
<p><strong>Fluid Balance:</strong> Input: ___ml &nbsp; Output: ___ml &nbsp; Balance: ___ml</p>
<p><strong>Progress:</strong> [ ] Improving &nbsp; [ ] Stable &nbsp; [ ] Deteriorating</p>

<h4>R — Recommendation</h4>
<ul>
<li>Medications: [changes]</li>
<li>Investigations: [new orders]</li>
<li>Diet: [changes]</li>
<li>Activity: [bed rest / mobilize / physio]</li>
<li>Nursing instructions: [special monitoring]</li>
</ul>
<p><strong>Escalation Plan:</strong><br>[When to call doctor, early warning score threshold]</p>
<p><strong>Estimated Discharge:</strong> [Date / criteria to meet]</p>',
            ],

            [
                'name'        => 'Follow-Up Visit',
                'category'    => 'Follow-Up',
                'description' => 'Structured follow-up consultation template',
                'sort_order'  => 3,
                'content'     => '<h3>FOLLOW-UP CONSULTATION</h3>

<p><strong>Previous Diagnosis:</strong><br>[Diagnosis from last visit]</p>
<p><strong>Last Visit Date:</strong> [Date] &nbsp; | &nbsp; <strong>Interval:</strong> [weeks/months since last visit]</p>

<h4>Interval History</h4>
<p><strong>Symptom Progress:</strong><br>[Has the condition improved / worsened / unchanged?]</p>
<p><strong>Medication Compliance:</strong><br>[Adherence to prescribed treatment, side effects experienced]</p>
<p><strong>New Complaints:</strong><br>[Any new symptoms since last visit]</p>

<h4>Examination Today</h4>
<p><strong>Vital Signs:</strong> BP: / HR: / Temp: / Wt: / BMI:</p>
<p><strong>Relevant Findings:</strong><br>[Focused examination findings]</p>

<h4>Investigation Results</h4>
<p>[Review of outstanding results since last visit]</p>

<h4>Assessment</h4>
<p><strong>Current Status:</strong> [ ] Resolved &nbsp; [ ] Improving &nbsp; [ ] Stable &nbsp; [ ] Worsening</p>
<p><strong>Updated Diagnosis:</strong><br>[Revised/confirmed diagnosis]</p>

<h4>Plan</h4>
<p><strong>Treatment Adjustments:</strong><br>[Dose changes, new medications, stop medications]</p>
<p><strong>New Investigations:</strong><br>[If any]</p>
<p><strong>Patient Education:</strong><br>[Reinforcement of key points]</p>
<p><strong>Next Follow-Up:</strong> [Date/interval] &nbsp; | &nbsp; <strong>Criteria for urgent review:</strong> [Red flags]</p>',
            ],

            [
                'name'        => 'Discharge Summary',
                'category'    => 'Discharge',
                'description' => 'Comprehensive hospital discharge summary',
                'sort_order'  => 4,
                'content'     => '<h3>DISCHARGE SUMMARY</h3>

<table class="table" border="1">
<tr><td><strong>Date of Admission</strong></td><td>[Date]</td></tr>
<tr><td><strong>Date of Discharge</strong></td><td>[Date]</td></tr>
<tr><td><strong>Length of Stay</strong></td><td>[# days]</td></tr>
</table>

<h4>Admission Diagnosis</h4>
<p>[Primary and secondary diagnoses at admission]</p>

<h4>Discharge Diagnosis</h4>
<p>[Final confirmed diagnoses (ICD coded)]</p>

<h4>Presenting Complaint &amp; History</h4>
<p>[Brief summary of why the patient was admitted]</p>

<h4>Hospital Course</h4>
<p>[Summary of treatment, procedures performed, significant events, complications]</p>

<h4>Investigation Summary</h4>
<table class="table" border="1">
<tr><th>Investigation</th><th>Date</th><th>Key Finding</th></tr>
<tr><td></td><td></td><td></td></tr>
</table>

<h4>Procedures Performed</h4>
<p>[List any surgical or invasive procedures with dates]</p>

<h4>Condition at Discharge</h4>
<p>[ ] Recovered &nbsp; [ ] Improved &nbsp; [ ] Unchanged &nbsp; [ ] Against Medical Advice</p>

<h4>Discharge Medications</h4>
<table class="table" border="1">
<tr><th>Medication</th><th>Dose</th><th>Route</th><th>Frequency</th><th>Duration</th></tr>
<tr><td></td><td></td><td></td><td></td><td></td></tr>
</table>

<h4>Follow-Up Instructions</h4>
<ul>
<li><strong>Clinic Appointment:</strong> [Date, Clinic]</li>
<li><strong>Investigations before next visit:</strong> [If any]</li>
<li><strong>Activity restrictions:</strong> [If any]</li>
<li><strong>Diet:</strong> [Special instructions]</li>
<li><strong>Wound care:</strong> [If applicable]</li>
</ul>

<h4>Red Flags — Return to Hospital If:</h4>
<ul>
<li>[Symptom 1]</li>
<li>[Symptom 2]</li>
<li>[Fever > 38.5°C, uncontrolled pain, bleeding, etc.]</li>
</ul>',
            ],

            [
                'name'        => 'Procedure Note',
                'category'    => 'Procedure',
                'description' => 'Standard operative/procedural documentation',
                'sort_order'  => 5,
                'content'     => '<h3>PROCEDURE NOTE</h3>

<table class="table" border="1">
<tr><td><strong>Procedure</strong></td><td>[Name of procedure]</td></tr>
<tr><td><strong>Indication</strong></td><td>[Clinical indication]</td></tr>
<tr><td><strong>Anesthesia</strong></td><td>[Local / Regional / General / Sedation / None]</td></tr>
<tr><td><strong>Operator</strong></td><td>[Name]</td></tr>
<tr><td><strong>Assistant(s)</strong></td><td>[Names]</td></tr>
<tr><td><strong>Consent</strong></td><td>[ ] Written informed consent obtained</td></tr>
</table>

<h4>Pre-Procedure</h4>
<p><strong>Vital Signs:</strong> BP: / HR: / SpO2:</p>
<p><strong>Time-Out Performed:</strong> [ ] Yes — Correct patient, procedure, site confirmed</p>
<p><strong>Antibiotic Prophylaxis:</strong> [Drug, dose, time given / Not indicated]</p>

<h4>Technique</h4>
<p>[Step-by-step description of the procedure performed, site, approach, instruments, findings]</p>

<h4>Findings</h4>
<p>[Intra-operative/procedural findings]</p>

<h4>Specimens</h4>
<p>[Specimens sent to lab / histopathology / None]</p>

<h4>Complications</h4>
<p>[ ] None &nbsp; [ ] Bleeding &nbsp; [ ] Infection &nbsp; [ ] Other: ________</p>
<p><strong>Estimated Blood Loss:</strong> [ml]</p>

<h4>Post-Procedure Orders</h4>
<ul>
<li>Monitoring: [Vitals q__h, drain output, etc.]</li>
<li>Medications: [Analgesics, antibiotics]</li>
<li>Diet: [NPO / Clear fluids / Regular]</li>
<li>Activity: [Bed rest / Ambulate]</li>
</ul>',
            ],

            [
                'name'        => 'Referral Letter',
                'category'    => 'Referral',
                'description' => 'Inter-specialty or external referral letter',
                'sort_order'  => 6,
                'content'     => '<h3>REFERRAL LETTER</h3>

<p><strong>To:</strong> Dr. ______________ — [Specialty/Hospital]</p>

<p>Dear Colleague,</p>
<p>I would be grateful if you could kindly review the following patient:</p>

<h4>Reason for Referral</h4>
<p>[Specific reason and question for the specialist]</p>

<h4>Clinical Summary</h4>
<p><strong>Presenting Complaint:</strong><br>[Brief summary]</p>
<p><strong>Relevant History:</strong><br>[PMH, medications, allergies]</p>
<p><strong>Examination Findings:</strong><br>[Key positive and negative findings]</p>
<p><strong>Investigations Done:</strong><br>[Relevant results — labs, imaging]</p>
<p><strong>Current Treatment:</strong><br>[Active medications and management]</p>
<p><strong>Working Diagnosis:</strong><br>[Current diagnosis or differential]</p>

<h4>Urgency</h4>
<p>[ ] Routine &nbsp; [ ] Soon (within 2 weeks) &nbsp; [ ] Urgent &nbsp; [ ] Emergency</p>

<p>Thank you for seeing this patient. I look forward to your expert opinion.</p>
<p>Yours sincerely,</p>',
            ],

            [
                'name'        => 'Emergency Assessment (ABCDE)',
                'category'    => 'Emergency',
                'description' => 'Systematic ABCDE emergency assessment framework',
                'sort_order'  => 7,
                'content'     => '<h3>EMERGENCY ASSESSMENT</h3>

<p><strong>Triage Category:</strong> [ ] Red [ ] Orange [ ] Yellow [ ] Green</p>

<h4>Mode of Arrival</h4>
<p>[ ] Ambulance &nbsp; [ ] Walk-in &nbsp; [ ] Referral &nbsp; | &nbsp; <strong>Brought by:</strong> [Self / Relative / EMS]</p>
<p><strong>Chief Complaint:</strong><br>[Main complaint and duration]</p>

<h4>A — Airway</h4>
<p>[ ] Patent &nbsp; [ ] Compromised &nbsp; [ ] Secured (ETT/LMA) &nbsp; | &nbsp; <strong>C-spine:</strong> [ ] Not indicated [ ] Immobilized</p>

<h4>B — Breathing</h4>
<p>RR: ___ /min &nbsp; SpO2: ___% on [RA / O2 ___L] &nbsp; | &nbsp; Chest: [ ] Clear [ ] Wheeze [ ] Creps [ ] Reduced air entry</p>

<h4>C — Circulation</h4>
<p>HR: ___ bpm &nbsp; BP: ___/___ &nbsp; Cap refill: ___ sec &nbsp; | &nbsp; [ ] IV access [ ] Fluids started</p>
<p><strong>ECG:</strong> [ ] Not done [ ] Normal [ ] Abnormal: ___________</p>

<h4>D — Disability</h4>
<p>GCS: E___V___M___ = ___/15 &nbsp; | &nbsp; Pupils: L: ___ mm R: ___ mm [ ] Reactive</p>
<p>Blood glucose: ___ mmol/L &nbsp; | &nbsp; Temp: ___°C</p>

<h4>E — Exposure</h4>
<p>[Full body check — rashes, wounds, deformities, bleeding, signs of abuse]</p>

<h4>AMPLE History</h4>
<table class="table" border="1">
<tr><td><strong>A</strong> — Allergies</td><td></td></tr>
<tr><td><strong>M</strong> — Medications</td><td></td></tr>
<tr><td><strong>P</strong> — Past history</td><td></td></tr>
<tr><td><strong>L</strong> — Last meal</td><td></td></tr>
<tr><td><strong>E</strong> — Events leading to presentation</td><td></td></tr>
</table>

<h4>Investigations Ordered</h4>
<p>[ ] FBC [ ] U&amp;E [ ] LFT [ ] Glucose [ ] ABG [ ] Lactate [ ] Coag [ ] X-ray [ ] CT [ ] US [ ] ECG [ ] Urinalysis</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Provisional Diagnosis:</strong><br>[Diagnosis]</p>
<p><strong>Immediate Management:</strong><br>[Interventions performed / ordered]</p>
<p><strong>Disposition:</strong> [ ] Discharge [ ] Observe [ ] Admit Ward [ ] Admit ICU [ ] Transfer [ ] Theatre</p>',
            ],

            [
                'name'        => 'Death Summary Note',
                'category'    => 'Death',
                'description' => 'Documentation for patient death in hospital',
                'sort_order'  => 8,
                'content'     => '<h3>DEATH SUMMARY</h3>

<table class="table" border="1">
<tr><td><strong>Date of Admission</strong></td><td>[Date]</td></tr>
<tr><td><strong>Date of Death</strong></td><td>[Date]</td></tr>
<tr><td><strong>Time of Death</strong></td><td>[Time]</td></tr>
</table>

<h4>Admission Diagnosis</h4>
<p>[Diagnosis at time of admission]</p>

<h4>Hospital Course Summary</h4>
<p>[Brief narrative of clinical course, treatments given, complications, deterioration]</p>

<h4>Cause of Death</h4>
<table class="table" border="1">
<tr><td><strong>1a — Immediate Cause</strong></td><td>[Direct cause of death]</td></tr>
<tr><td><strong>1b — Due to</strong></td><td>[Antecedent cause]</td></tr>
<tr><td><strong>1c — Due to</strong></td><td>[Underlying cause]</td></tr>
<tr><td><strong>2 — Contributing Conditions</strong></td><td>[Other significant conditions]</td></tr>
</table>

<h4>Resuscitation</h4>
<p>[ ] CPR attempted — Duration: ___ min &nbsp; [ ] DNAR order in place &nbsp; [ ] Family informed prior</p>

<h4>Family Notification</h4>
<p><strong>Next of Kin informed:</strong> [ ] Yes [ ] No &nbsp; | &nbsp; <strong>Name:</strong> [NOK name] &nbsp; | &nbsp; <strong>Time notified:</strong> [Time]</p>
<p><strong>Body released to:</strong> [Mortuary / Family]</p>',
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  SPECIALTY TEMPLATES
    // ═══════════════════════════════════════════════════════════════════════

    private function specialtyTemplates(): array
    {
        return [
            'Pediatrics' => [
                [
                    'name'     => 'Pediatric Clerking',
                    'category' => 'First Visit',
                    'description' => 'Age-appropriate pediatric initial assessment with growth milestones',
                    'content'  => '<h3>PEDIATRIC INITIAL ASSESSMENT</h3>
<p><strong>Informant:</strong> [Parent/Guardian name and relationship]</p>

<h4>Presenting Complaint</h4>
<p>[Chief complaint in parent\'s words, duration]</p>

<h4>History of Presenting Illness</h4>
<p>[Detailed history including feeding, activity level, urine output]</p>

<h4>Birth &amp; Neonatal History</h4>
<table class="table" border="1">
<tr><td><strong>Gestation</strong></td><td>[Term/Preterm — weeks]</td></tr>
<tr><td><strong>Delivery</strong></td><td>[SVD/CS]</td></tr>
<tr><td><strong>Birth Weight</strong></td><td>[kg]</td></tr>
<tr><td><strong>Neonatal Issues</strong></td><td>[Jaundice, NICU admission, etc.]</td></tr>
</table>

<h4>Feeding History</h4>
<p>[ ] Breastfed [ ] Formula [ ] Mixed &nbsp; | &nbsp; <strong>Weaning started:</strong> [Age]</p>
<p><strong>Current diet:</strong> [Description]</p>

<h4>Developmental Milestones</h4>
<table class="table" border="1">
<tr><th>Domain</th><th>Milestone</th><th>Age Achieved</th><th>Status</th></tr>
<tr><td>Gross Motor</td><td>[Sitting/Walking/etc.]</td><td></td><td>[ ] Normal [ ] Delayed</td></tr>
<tr><td>Fine Motor</td><td>[Grasp/Pincer/etc.]</td><td></td><td>[ ] Normal [ ] Delayed</td></tr>
<tr><td>Language</td><td>[Babble/Words/Sentences]</td><td></td><td>[ ] Normal [ ] Delayed</td></tr>
<tr><td>Social</td><td>[Smile/Stranger anxiety]</td><td></td><td>[ ] Normal [ ] Delayed</td></tr>
</table>

<h4>Immunization</h4>
<p>[ ] Up to date [ ] Incomplete — Missing: ___________</p>

<h4>Examination</h4>
<table class="table" border="1">
<tr><td><strong>Weight</strong></td><td>___kg ([percentile])</td></tr>
<tr><td><strong>Height</strong></td><td>___cm ([percentile])</td></tr>
<tr><td><strong>OFC</strong></td><td>___cm ([percentile])</td></tr>
<tr><td><strong>HR</strong></td><td></td></tr>
<tr><td><strong>RR</strong></td><td></td></tr>
<tr><td><strong>Temp</strong></td><td></td></tr>
<tr><td><strong>SpO2</strong></td><td></td></tr>
</table>
<p><strong>General:</strong> [Alert, hydration, nutritional status, dysmorphic features]</p>
<p><strong>Systems:</strong><br>[Focused examination]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Plan:</strong></p>',
                ],
                [
                    'name'     => 'Pediatric Ward Round',
                    'category' => 'Ward Round',
                    'description' => 'Pediatric inpatient daily review with feeding/growth focus',
                    'content'  => '<h3>PEDIATRIC WARD ROUND</h3>
<p><strong>Day:</strong> [#] of admission</p>

<p><strong>Diagnosis:</strong> [Current diagnosis]</p>
<p><strong>Overnight:</strong> [Events, parent concerns, feeding, activity]</p>

<table class="table" border="1">
<tr><th>Parameter</th><th>Value</th></tr>
<tr><td>HR</td><td></td></tr>
<tr><td>RR</td><td></td></tr>
<tr><td>Temp</td><td></td></tr>
<tr><td>SpO2</td><td></td></tr>
<tr><td>Weight</td><td>___kg</td></tr>
</table>

<p><strong>I/O:</strong> Intake: ___ml &nbsp; Output: ___ml (wet nappies: ___)</p>
<p><strong>Feeding:</strong> [Tolerated / Vomiting / NPO]</p>
<p><strong>Examination:</strong><br>[Focused findings]</p>
<p><strong>Results:</strong><br>[New labs/imaging]</p>
<p><strong>Progress:</strong> [ ] Improving [ ] Stable [ ] Declining</p>
<p><strong>Plan:</strong></p>
<ul><li></li><li></li></ul>',
                ],
            ],

            'Gynecology/Obstetrics' => [
                [
                    'name'     => 'Antenatal Visit (Booking)',
                    'category' => 'Antenatal',
                    'description' => 'First antenatal booking visit with comprehensive history',
                    'content'  => '<h3>ANTENATAL BOOKING VISIT</h3>

<h4>Obstetric History</h4>
<p><strong>Gravida:</strong> ___ &nbsp; <strong>Para:</strong> ___ &nbsp; <strong>Abortions:</strong> ___ &nbsp; <strong>Living:</strong> ___</p>
<p><strong>LMP:</strong> [Date] &nbsp; | &nbsp; <strong>EDD:</strong> [Date] &nbsp; | &nbsp; <strong>GA:</strong> [Weeks+Days]</p>

<p><strong>Previous Deliveries:</strong></p>
<table class="table" border="1">
<tr><th>#</th><th>Year</th><th>GA</th><th>Mode</th><th>Weight</th><th>Sex</th><th>Outcome</th><th>Complications</th></tr>
<tr><td>1</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
</table>

<h4>Current Pregnancy</h4>
<p><strong>Planned:</strong> [ ] Yes [ ] No &nbsp; | &nbsp; <strong>ANC visits elsewhere:</strong> [ ] Yes [ ] No</p>
<p><strong>Symptoms:</strong> Nausea: / Bleeding: / Discharge: / Fetal movements:</p>

<h4>Medical / Surgical History</h4>
<p>[HTN, DM, thyroid, previous surgery, blood transfusion]</p>
<p><strong>Rhesus:</strong> [ ] Pos [ ] Neg</p>

<h4>Examination</h4>
<table class="table" border="1">
<tr><td><strong>Weight</strong></td><td>___kg</td></tr>
<tr><td><strong>Height</strong></td><td>___cm</td></tr>
<tr><td><strong>BMI</strong></td><td></td></tr>
<tr><td><strong>BP</strong></td><td>___/___</td></tr>
<tr><td><strong>SFH</strong></td><td>___cm</td></tr>
<tr><td><strong>Fetal Heart</strong></td><td>[ ] Heard [ ] Not heard</td></tr>
<tr><td><strong>Presentation</strong></td><td></td></tr>
</table>
<p><strong>General:</strong> [Pallor, edema, thyroid, breasts]</p>

<h4>Investigations Ordered</h4>
<p>[ ] FBC [ ] Blood group [ ] Rh [ ] VDRL [ ] HIV [ ] HBsAg [ ] HCV [ ] Urinalysis [ ] RBS [ ] Ultrasound</p>

<h4>Risk Assessment</h4>
<p>[ ] Low risk [ ] High risk — Reason: ___________</p>

<h4>Plan</h4>
<p><strong>Supplements:</strong> [ ] Folic acid [ ] Iron [ ] Calcium</p>
<p><strong>Next visit:</strong> [Date] &nbsp; | &nbsp; <strong>Delivery plan:</strong> [Hospital / facility]</p>',
                ],
                [
                    'name'     => 'Gynecology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Gynecological consultation template with menstrual and reproductive history',
                    'content'  => '<h3>GYNECOLOGY CONSULTATION</h3>

<h4>Chief Complaint</h4>
<p>[Main complaint and duration]</p>

<h4>Menstrual History</h4>
<table class="table" border="1">
<tr><td><strong>Menarche</strong></td><td>[Age]</td></tr>
<tr><td><strong>Cycle</strong></td><td>___/___days</td></tr>
<tr><td><strong>LMP</strong></td><td>[Date]</td></tr>
<tr><td><strong>Flow</strong></td><td>[ ] Normal [ ] Heavy [ ] Light</td></tr>
<tr><td><strong>Dysmenorrhea</strong></td><td>[ ] Yes [ ] No</td></tr>
<tr><td><strong>Intermenstrual bleeding</strong></td><td>[ ] Yes [ ] No</td></tr>
<tr><td><strong>Post-coital bleeding</strong></td><td>[ ] Yes [ ] No</td></tr>
</table>

<h4>Obstetric History</h4>
<p>G___P___A___L___ &nbsp; | &nbsp; <strong>Last delivery:</strong> [Year, mode]</p>

<h4>Contraception</h4>
<p><strong>Current method:</strong> [None / OCP / IUCD / Implant / Condom / Other]</p>

<h4>Sexual / Reproductive History</h4>
<p><strong>Sexually active:</strong> [ ] Yes [ ] No &nbsp; | &nbsp; <strong>Dyspareunia:</strong> [ ] Yes [ ] No</p>
<p><strong>Last Pap smear:</strong> [Date, result]</p>

<h4>Examination</h4>
<p><strong>Abdomen:</strong> [Tenderness, masses, scars]</p>
<p><strong>Speculum:</strong> [Cervix appearance, discharge, bleeding]</p>
<p><strong>Bimanual:</strong> [Uterus size/position, adnexal masses, tenderness]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Investigations:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Cardiology' => [
                [
                    'name'     => 'Cardiology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Cardiovascular-focused initial assessment',
                    'content'  => '<h3>CARDIOLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Chest pain / Dyspnea / Palpitations / Syncope / Edema]</p>

<h4>Cardiovascular History</h4>
<p><strong>Chest Pain:</strong> Character: / Location: / Radiation: / Duration: / Triggers: / Relief:</p>
<p><strong>Dyspnea:</strong> NYHA Class: [ ] I [ ] II [ ] III [ ] IV &nbsp; | &nbsp; Orthopnea: ___ pillows &nbsp; PND: [ ] Yes [ ] No</p>
<p><strong>Palpitations:</strong> [ ] Regular [ ] Irregular &nbsp; Duration: &nbsp; Frequency:</p>
<p><strong>Syncope / Pre-syncope:</strong> [ ] Yes [ ] No &nbsp; Triggers:</p>
<p><strong>Peripheral edema:</strong> [ ] Yes [ ] No &nbsp; Location:</p>
<p><strong>Claudication:</strong> [ ] Yes [ ] No &nbsp; Distance:</p>

<h4>Risk Factors</h4>
<p>[ ] Hypertension [ ] Diabetes [ ] Dyslipidemia [ ] Smoking [ ] Family Hx of CAD [ ] Obesity [ ] Sedentary</p>

<h4>Examination</h4>
<table class="table" border="1">
<tr><td><strong>BP (Right)</strong></td><td>___/___</td><td><strong>BP (Left)</strong></td><td>___/___</td></tr>
<tr><td><strong>HR</strong></td><td></td><td><strong>JVP</strong></td><td>[Normal / Raised ___cm]</td></tr>
</table>
<p><strong>Precordium:</strong> Apex: [Position] &nbsp; | &nbsp; Heave: [ ] No [ ] Yes &nbsp; | &nbsp; Thrill: [ ] No [ ] Yes</p>
<p><strong>Heart Sounds:</strong> S1: / S2: / S3: / S4: / Murmur: [Grade, timing, location, radiation]</p>
<p><strong>Lungs:</strong> [Basal crepitations, pleural effusion]</p>
<p><strong>Peripheral:</strong> [Pulses, edema, cyanosis, clubbing]</p>

<h4>Investigations</h4>
<p><strong>ECG:</strong> [Rate, rhythm, axis, ST changes, intervals]</p>
<p><strong>Echocardiogram:</strong> [EF___, valve function, chamber sizes, wall motion]</p>
<p><strong>Labs:</strong> [Troponin, BNP, lipid profile, HbA1c]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Orthopedic' => [
                [
                    'name'     => 'Orthopedic Consultation',
                    'category' => 'First Visit',
                    'description' => 'Musculoskeletal assessment with injury/joint focus',
                    'content'  => '<h3>ORTHOPEDIC CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Pain / Swelling / Deformity / Reduced mobility — Site, Duration]</p>

<h4>Mechanism of Injury</h4>
<p>[If trauma: How, When, Force, Direction — RTA / Fall / Sport / Work]</p>

<h4>Pain Assessment</h4>
<table class="table" border="1">
<tr><td><strong>Site</strong></td><td></td></tr>
<tr><td><strong>Onset</strong></td><td></td></tr>
<tr><td><strong>Character</strong></td><td></td></tr>
<tr><td><strong>Radiation</strong></td><td></td></tr>
<tr><td><strong>Aggravating</strong></td><td></td></tr>
<tr><td><strong>Relieving</strong></td><td></td></tr>
<tr><td><strong>VAS Score</strong></td><td>___/10</td></tr>
<tr><td><strong>Night pain</strong></td><td>[ ] Yes [ ] No</td></tr>
<tr><td><strong>Rest pain</strong></td><td>[ ] Yes [ ] No</td></tr>
</table>

<h4>Functional Assessment</h4>
<p><strong>Mobility:</strong> [ ] Full [ ] Limited — Walking aid: [ ] None [ ] Crutch [ ] Walker [ ] Wheelchair</p>
<p><strong>ADLs affected:</strong> [Dressing, bathing, stairs, work]</p>
<p><strong>Occupation:</strong> [Type and physical demands]</p>

<h4>Examination</h4>
<p><strong>Look:</strong> [Swelling, bruising, deformity, scars, skin changes, muscle wasting]</p>
<p><strong>Feel:</strong> [Tenderness, warmth, effusion, crepitus, pulses]</p>
<p><strong>Move:</strong></p>
<table class="table" border="1">
<tr><th>Movement</th><th>Active ROM</th><th>Passive ROM</th><th>Pain</th></tr>
<tr><td>Flexion</td><td>°</td><td>°</td><td></td></tr>
<tr><td>Extension</td><td>°</td><td>°</td><td></td></tr>
<tr><td>Other</td><td>°</td><td>°</td><td></td></tr>
</table>
<p><strong>Special Tests:</strong> [Relevant tests for the joint/region]</p>
<p><strong>Neurovascular:</strong> [Distal sensation, pulses, motor function]</p>

<h4>Imaging</h4>
<p><strong>X-ray:</strong> [Findings]</p>
<p><strong>Other:</strong> [MRI / CT / US if done]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong> [ ] Conservative [ ] Surgical — [Description]</p>
<p><strong>Physiotherapy:</strong> [ ] Referred</p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Ophthalmology' => [
                [
                    'name'     => 'Ophthalmology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Comprehensive eye examination template',
                    'content'  => '<h3>OPHTHALMOLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Reduced vision / Pain / Redness / Discharge / Floaters / Flashes / Trauma]</p>
<p><strong>Duration:</strong> &nbsp; <strong>Laterality:</strong> [ ] Right [ ] Left [ ] Both &nbsp; <strong>Onset:</strong> [ ] Sudden [ ] Gradual</p>

<h4>Ocular History</h4>
<p>[Previous eye surgery, glasses/contacts, glaucoma, trauma, amblyopia]</p>

<h4>Visual Acuity</h4>
<table class="table" border="1">
<tr><th></th><th>Right Eye (OD)</th><th>Left Eye (OS)</th></tr>
<tr><td><strong>Unaided</strong></td><td></td><td></td></tr>
<tr><td><strong>Pinhole</strong></td><td></td><td></td></tr>
<tr><td><strong>Best Corrected</strong></td><td></td><td></td></tr>
<tr><td><strong>Near Vision</strong></td><td></td><td></td></tr>
</table>

<h4>Examination</h4>
<table class="table" border="1">
<tr><th></th><th>OD</th><th>OS</th></tr>
<tr><td><strong>IOP (mmHg)</strong></td><td></td><td></td></tr>
<tr><td><strong>Lids/Adnexa</strong></td><td></td><td></td></tr>
<tr><td><strong>Conjunctiva</strong></td><td></td><td></td></tr>
<tr><td><strong>Cornea</strong></td><td></td><td></td></tr>
<tr><td><strong>Anterior Chamber</strong></td><td></td><td></td></tr>
<tr><td><strong>Iris/Pupil</strong></td><td></td><td></td></tr>
<tr><td><strong>Lens</strong></td><td></td><td></td></tr>
<tr><td><strong>Fundus</strong></td><td></td><td></td></tr>
</table>
<p><strong>Pupils:</strong> RAPD: [ ] Yes [ ] No &nbsp; | &nbsp; <strong>EOM:</strong> [Full / Restricted]</p>
<p><strong>Confrontation Fields:</strong> [Normal / Defect]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Dental' => [
                [
                    'name'     => 'Dental Examination',
                    'category' => 'First Visit',
                    'description' => 'Comprehensive dental examination and charting',
                    'content'  => '<h3>DENTAL EXAMINATION</h3>

<h4>Chief Complaint</h4>
<p>[Pain / Swelling / Bleeding gums / Broken tooth / Cosmetic concern]</p>

<h4>Dental History</h4>
<p><strong>Last dental visit:</strong> [Date/timeframe] &nbsp; | &nbsp; <strong>Oral hygiene:</strong> Brushing ___x/day, Flossing: [ ] Yes [ ] No</p>
<p><strong>Previous treatments:</strong> [Fillings, extractions, root canals, dentures, braces]</p>

<h4>Extra-Oral Examination</h4>
<table class="table" border="1">
<tr><td><strong>Face</strong></td><td>[ ] Symmetrical [ ] Asymmetrical — [Swelling, lymph nodes, TMJ]</td></tr>
<tr><td><strong>TMJ</strong></td><td>[ ] Normal [ ] Click [ ] Pain [ ] Limited opening &nbsp; Max opening: ___mm</td></tr>
<tr><td><strong>Lymph nodes</strong></td><td>[Submandibular, cervical — palpable/normal]</td></tr>
</table>

<h4>Intra-Oral Examination</h4>
<p><strong>Soft Tissues:</strong> [Lips, tongue, floor of mouth, palate, buccal mucosa, oropharynx]</p>
<p><strong>Gingiva:</strong> [ ] Healthy [ ] Gingivitis [ ] Periodontitis &nbsp; BOP: [ ] Yes [ ] No</p>
<p><strong>Oral Hygiene:</strong> [ ] Good [ ] Fair [ ] Poor &nbsp; Calculus: [ ] None [ ] Mild [ ] Moderate [ ] Heavy</p>

<h4>Dental Charting</h4>
<p><em>[Mark caries (C), missing (X), restored (R), crown (#), RCT, mobility]</em></p>
<pre>
Upper:  18 17 16 15 14 13 12 11 | 21 22 23 24 25 26 27 28
Lower:  48 47 46 45 44 43 42 41 | 31 32 33 34 35 36 37 38
</pre>
<p><strong>Teeth of concern:</strong> [Tooth #, findings]</p>

<h4>Radiographs</h4>
<p>[ ] OPG [ ] Periapical [ ] Bitewing [ ] CBCT &nbsp; Findings: ___</p>

<h4>Diagnosis &amp; Treatment Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Immediate treatment:</strong></p>
<p><strong>Treatment plan:</strong></p>
<ol><li></li><li></li></ol>',
                ],
            ],

            'Psychiatry' => [
                [
                    'name'     => 'Psychiatric Assessment',
                    'category' => 'First Visit',
                    'description' => 'Comprehensive psychiatric evaluation with mental state exam',
                    'content'  => '<h3>PSYCHIATRIC ASSESSMENT</h3>
<p><strong>Informant(s):</strong> [Patient + relative/carer if applicable]</p>

<h4>Presenting Complaint</h4>
<p>[In patient\'s own words]</p>

<h4>History of Presenting Illness</h4>
<p>[Detailed chronological narrative, precipitating factors, functional impact]</p>

<h4>Risk Assessment</h4>
<table class="table" border="1">
<tr><th>Domain</th><th>Level</th><th>Details</th></tr>
<tr><td>Suicidal ideation</td><td>[ ] None [ ] Passive [ ] Active</td><td>Plan: [ ] Yes [ ] No &nbsp; Means: [ ] Access [ ] No access</td></tr>
<tr><td>Self-harm</td><td>[ ] None [ ] Current [ ] Historical</td><td>Method/frequency:</td></tr>
<tr><td>Harm to others</td><td>[ ] None [ ] Ideation [ ] Plans</td><td>Identified target:</td></tr>
<tr><td>Vulnerability</td><td>[ ] Self-neglect [ ] Exploitation [ ] None</td><td></td></tr>
</table>

<h4>Past Psychiatric History</h4>
<p>[Previous diagnoses, hospitalizations, treatments, ECT, medication trials]</p>

<h4>Substance Use</h4>
<p><strong>Alcohol:</strong> [Type, quantity, frequency, CAGE score] &nbsp; | &nbsp; <strong>Cannabis:</strong> [ ] Yes [ ] No</p>
<p><strong>Other substances:</strong> [Specify] &nbsp; | &nbsp; <strong>IV drug use:</strong> [ ] Yes [ ] No</p>

<h4>Personal History</h4>
<p><strong>Early life:</strong> [Birth, development, childhood experiences]</p>
<p><strong>Education:</strong> [Level attained]</p>
<p><strong>Occupation:</strong> [Current, employment history]</p>
<p><strong>Relationships:</strong> [Marital status, children, social support]</p>

<h4>Premorbid Personality</h4>
<p>[How was the patient before illness — described by self and others]</p>

<h4>Mental State Examination</h4>
<table class="table" border="1">
<tr><td><strong>Appearance</strong></td><td>[Dress, hygiene, eye contact, psychomotor activity]</td></tr>
<tr><td><strong>Behavior</strong></td><td>[Cooperation, agitation, retardation, mannerisms]</td></tr>
<tr><td><strong>Speech</strong></td><td>[Rate, volume, tone, spontaneity, coherence]</td></tr>
<tr><td><strong>Mood (subjective)</strong></td><td>[Patient\'s own words]</td></tr>
<tr><td><strong>Affect (objective)</strong></td><td>[Euthymic / Depressed / Elated / Anxious / Flat / Labile / Incongruent]</td></tr>
<tr><td><strong>Thought Form</strong></td><td>[Linear / Circumstantial / Tangential / Loose / Flight of ideas / Thought block]</td></tr>
<tr><td><strong>Thought Content</strong></td><td>[Delusions, obsessions, overvalued ideas, suicidal/homicidal ideation]</td></tr>
<tr><td><strong>Perception</strong></td><td>[Hallucinations: auditory/visual/tactile, illusions, depersonalization]</td></tr>
<tr><td><strong>Cognition</strong></td><td>[Oriented to time/place/person, attention, memory, MMSE/MoCA score]</td></tr>
<tr><td><strong>Insight</strong></td><td>[ ] Full [ ] Partial [ ] None</td></tr>
<tr><td><strong>Judgment</strong></td><td>[ ] Intact [ ] Impaired</td></tr>
</table>

<h4>Assessment</h4>
<p><strong>Formulation:</strong> [Bio-psycho-social formulation]</p>
<p><strong>Diagnosis (ICD-11):</strong></p>
<p><strong>Risk Level:</strong> [ ] Low [ ] Medium [ ] High</p>

<h4>Plan</h4>
<p><strong>Pharmacological:</strong></p>
<p><strong>Psychological:</strong> [CBT, counselling, etc.]</p>
<p><strong>Social:</strong> [Housing, employment, support groups]</p>
<p><strong>Safety plan:</strong> [Crisis contacts, emergency procedures]</p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Endocrinology' => [
                [
                    'name'     => 'Diabetes Review',
                    'category' => 'Follow-Up',
                    'description' => 'Structured diabetes management review',
                    'content'  => '<h3>DIABETES REVIEW</h3>

<h4>Diabetes Profile</h4>
<table class="table" border="1">
<tr><td><strong>Type</strong></td><td>[ ] Type 1 [ ] Type 2 [ ] GDM [ ] Other</td></tr>
<tr><td><strong>Duration</strong></td><td>[years since diagnosis]</td></tr>
<tr><td><strong>Current Treatment</strong></td><td>[OHA / Insulin / Both — details]</td></tr>
</table>

<h4>Glycemic Control</h4>
<table class="table" border="1">
<tr><td><strong>HbA1c</strong></td><td>___% (Date: ___)</td><td><strong>Target:</strong> &lt;7% (individualized: ___)</td></tr>
<tr><td><strong>FBS</strong></td><td>___ mg/dL</td><td></td></tr>
<tr><td><strong>RBS</strong></td><td>___ mg/dL</td><td></td></tr>
<tr><td><strong>PPBS</strong></td><td>___ mg/dL</td><td></td></tr>
</table>
<p><strong>Hypoglycemia episodes:</strong> [ ] None [ ] Mild [ ] Severe — Frequency:</p>
<p><strong>Home monitoring:</strong> [ ] Regular [ ] Irregular [ ] None &nbsp; Average readings: ___</p>

<h4>Complication Screening</h4>
<table class="table" border="1">
<tr><th>Complication</th><th>Screened</th><th>Finding</th></tr>
<tr><td>Retinopathy (eye exam)</td><td>[ ] Yes [ ] No</td><td></td></tr>
<tr><td>Nephropathy (urine ACR, eGFR)</td><td>[ ] Yes [ ] No</td><td></td></tr>
<tr><td>Neuropathy (monofilament/vibration)</td><td>[ ] Yes [ ] No</td><td></td></tr>
<tr><td>Peripheral vascular (pedal pulses)</td><td>[ ] Yes [ ] No</td><td></td></tr>
<tr><td>Foot examination</td><td>[ ] Yes [ ] No</td><td></td></tr>
<tr><td>Cardiovascular (BP, lipids)</td><td>[ ] Yes [ ] No</td><td></td></tr>
</table>

<h4>Vitals &amp; Anthropometry</h4>
<p><strong>BP:</strong> ___/___ &nbsp; <strong>Wt:</strong> ___kg &nbsp; <strong>BMI:</strong> ___ &nbsp; <strong>Waist:</strong> ___cm</p>

<h4>Labs</h4>
<p>Lipids: TC: / LDL: / HDL: / TG: &nbsp; | &nbsp; Cr: / eGFR: / Urine ACR:</p>

<h4>Plan</h4>
<p><strong>Medication changes:</strong></p>
<p><strong>Diet advice:</strong></p>
<p><strong>Exercise:</strong></p>
<p><strong>Referrals:</strong> [ ] Ophthalmology [ ] Podiatry [ ] Dietitian [ ] Educator</p>
<p><strong>Next HbA1c:</strong> [Date] &nbsp; | &nbsp; <strong>Next visit:</strong> [Date]</p>',
                ],
            ],

            'General Surgery' => [
                [
                    'name'     => 'Surgical Clerking',
                    'category' => 'First Visit',
                    'description' => 'Pre-operative surgical assessment and clerking note',
                    'content'  => '<h3>SURGICAL CLERKING NOTE</h3>

<h4>Presenting Complaint</h4>
<p>[Main surgical complaint with duration]</p>

<h4>History of Presenting Illness</h4>
<p>[SOCRATES for pain, onset, progression, associated symptoms]</p>

<h4>Past Surgical History</h4>
<p>[Previous operations with dates and complications]</p>

<h4>Anesthetic History</h4>
<table class="table" border="1">
<tr><td><strong>Previous GA</strong></td><td>[ ] Yes [ ] No &nbsp; Complications: [None / Difficult airway / PONV / MH]</td></tr>
<tr><td><strong>Allergies</strong></td><td></td></tr>
<tr><td><strong>Medications</strong></td><td>[esp. anticoagulants, antiplatelets, steroids, insulin]</td></tr>
<tr><td><strong>Fasting status</strong></td><td>Last meal: [Time] &nbsp; Last drink: [Time]</td></tr>
</table>

<h4>Examination</h4>
<p><strong>General:</strong> [Appearance, hydration, jaundice, lymph nodes]</p>
<p><strong>Vitals:</strong> BP: / HR: / Temp: / RR: / SpO2:</p>
<p><strong>Abdominal Examination:</strong></p>
<table class="table" border="1">
<tr><td><strong>Inspection</strong></td><td>[Distension, scars, visible masses, hernias]</td></tr>
<tr><td><strong>Palpation</strong></td><td>[Tenderness, guarding, rebound, organomegaly, masses]</td></tr>
<tr><td><strong>Percussion</strong></td><td>[Tympanic / Dull]</td></tr>
<tr><td><strong>Auscultation</strong></td><td>[Bowel sounds — present / absent / tinkling]</td></tr>
</table>
<p><strong>DRE:</strong> [If indicated — tone, masses, blood, prostate]</p>

<h4>ASA Classification</h4>
<p>[ ] ASA I [ ] ASA II [ ] ASA III [ ] ASA IV [ ] ASA V</p>

<h4>Investigation Results</h4>
<p>FBC: / U&amp;E: / LFT: / Coag: / G&amp;S: / ECG: / CXR:</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Proposed Operation:</strong></p>
<p><strong>Consent:</strong> [ ] Obtained [ ] Pending</p>
<p><strong>VTE prophylaxis:</strong> [ ] TED stockings [ ] LMWH [ ] Both</p>
<p><strong>Antibiotic prophylaxis:</strong> [If planned]</p>',
                ],
            ],

            'Ear, Nose & Throat' => [
                [
                    'name'     => 'ENT Consultation',
                    'category' => 'First Visit',
                    'description' => 'Ear, nose and throat examination template',
                    'content'  => '<h3>ENT CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Hearing loss / Ear pain / Discharge / Tinnitus / Vertigo / Nasal obstruction / Epistaxis / Sore throat / Hoarseness / Neck mass]</p>

<h4>Ear Examination</h4>
<table class="table" border="1">
<tr><th></th><th>Right</th><th>Left</th></tr>
<tr><td><strong>Pinna</strong></td><td></td><td></td></tr>
<tr><td><strong>EAC</strong></td><td></td><td></td></tr>
<tr><td><strong>Tympanic Membrane</strong></td><td></td><td></td></tr>
<tr><td><strong>Rinne Test</strong></td><td>[ ] +ve [ ] -ve</td><td>[ ] +ve [ ] -ve</td></tr>
<tr><td><strong>Weber</strong></td><td colspan="2">[ ] Central [ ] Lateralizes to: ___</td></tr>
</table>
<p><strong>Audiometry:</strong> [If done — PTA results]</p>

<h4>Nose Examination</h4>
<p><strong>External:</strong> [Deformity, swelling]</p>
<p><strong>Anterior rhinoscopy:</strong> [Mucosa, septum, turbinates, polyps, discharge]</p>

<h4>Throat Examination</h4>
<p><strong>Oral cavity:</strong> [Tongue, floor of mouth, palate]</p>
<p><strong>Oropharynx:</strong> [Tonsils (size/grade/exudate), posterior pharyngeal wall]</p>
<p><strong>Larynx:</strong> [If indirect laryngoscopy done — vocal cords, mobility]</p>

<h4>Neck</h4>
<p>[Lymph nodes, thyroid, masses, salivary glands]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Urology' => [
                [
                    'name'     => 'Urology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Genitourinary assessment template',
                    'content'  => '<h3>UROLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[LUTS / Hematuria / Renal colic / Retention / Incontinence / Mass]</p>

<h4>Lower Urinary Tract Symptoms</h4>
<table class="table" border="1">
<tr><th>Category</th><th>Symptom</th><th>Present</th></tr>
<tr><td rowspan="3">Storage</td><td>Frequency</td><td>___/day</td></tr>
<tr><td>Nocturia</td><td>___/night</td></tr>
<tr><td>Urgency / Incontinence</td><td>[ ] Urge [ ] Stress [ ] Mixed [ ] None</td></tr>
<tr><td rowspan="3">Voiding</td><td>Hesitancy</td><td>[ ] Yes [ ] No</td></tr>
<tr><td>Poor stream</td><td>[ ] Yes [ ] No</td></tr>
<tr><td>Straining</td><td>[ ] Yes [ ] No</td></tr>
<tr><td>Post-micturition</td><td>Incomplete emptying / Dribbling</td><td>[ ] Yes [ ] No</td></tr>
</table>
<p><strong>IPSS Score:</strong> ___/35 &nbsp; | &nbsp; <strong>QoL Score:</strong> ___/6</p>

<h4>Examination</h4>
<p><strong>Abdomen:</strong> [Flank tenderness, palpable kidney/bladder, loin mass]</p>
<p><strong>External Genitalia:</strong> [Testes, epididymis, scrotum, penis — if relevant]</p>
<p><strong>DRE:</strong> [Prostate size, consistency, nodules, tenderness, median sulcus]</p>

<h4>Investigations</h4>
<p>U/A: / Cr: / eGFR: / PSA: / US KUB: / Flow rate: / PVR:</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Physiotherapy' => [
                [
                    'name'     => 'Physiotherapy Assessment',
                    'category' => 'First Visit',
                    'description' => 'Functional physiotherapy initial assessment',
                    'content'  => '<h3>PHYSIOTHERAPY ASSESSMENT</h3>
<p><strong>Referred by:</strong> Dr. ____________ &nbsp; | &nbsp; <strong>Diagnosis:</strong> _______________</p>

<h4>Presenting Complaint</h4>
<p>[Pain / Weakness / Stiffness / Reduced mobility — Body part, Duration]</p>

<h4>Functional Assessment</h4>
<table class="table" border="1">
<tr><td><strong>Mobility</strong></td><td>[ ] Independent [ ] Supervised [ ] Assisted [ ] Dependent</td></tr>
<tr><td><strong>Walking aids</strong></td><td>[ ] None [ ] Stick [ ] Crutches [ ] Frame [ ] Wheelchair</td></tr>
<tr><td><strong>Transfers</strong></td><td>[Bed&#8596;Chair, Sit&#8596;Stand — Independent/Assisted]</td></tr>
<tr><td><strong>Stairs</strong></td><td>[ ] Independent [ ] With rail [ ] Unable</td></tr>
<tr><td><strong>ADLs</strong></td><td>[Dressing, bathing, toileting — level of independence]</td></tr>
</table>

<h4>Pain Assessment</h4>
<p><strong>VAS:</strong> ___/10 at rest, ___/10 on movement &nbsp; | &nbsp; <strong>Location:</strong> [Body map]</p>

<h4>Objective Examination</h4>
<p><strong>Posture:</strong> [Observation findings]</p>
<p><strong>Range of Motion:</strong></p>
<table class="table" border="1">
<tr><th>Joint / Movement</th><th>Active</th><th>Passive</th><th>Pain</th></tr>
<tr><td></td><td></td><td></td><td></td></tr>
</table>
<p><strong>Muscle Power:</strong> (MRC 0-5)</p>
<p><strong>Sensation:</strong> [Normal / Reduced / Absent]</p>
<p><strong>Special Tests:</strong> [Relevant to condition]</p>
<p><strong>Gait Analysis:</strong> [Pattern, deviations, speed, endurance]</p>

<h4>Goals</h4>
<p><strong>Short-term (2 weeks):</strong></p>
<p><strong>Long-term (6 weeks):</strong></p>

<h4>Treatment Plan</h4>
<ul>
<li>[ ] Exercises — [Type: strengthening/stretching/balance]</li>
<li>[ ] Manual therapy</li>
<li>[ ] Electrotherapy — [TENS / US / IFT]</li>
<li>[ ] Gait training</li>
<li>[ ] Patient education</li>
</ul>
<p><strong>Frequency:</strong> ___x/week for ___ weeks</p>',
                ],
            ],

            'Neurology' => [
                [
                    'name'     => 'Neurology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Comprehensive neurological assessment',
                    'content'  => '<h3>NEUROLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Headache / Seizures / Weakness / Numbness / Tremor / Gait disturbance / Memory loss / Visual change]</p>

<h4>Neurological Examination</h4>
<p><strong>Mental Status:</strong> GCS: E___V___M___ &nbsp; Orientation: [ ] Person [ ] Place [ ] Time &nbsp; | &nbsp; MMSE: ___/30</p>

<p><strong>Cranial Nerves:</strong></p>
<table class="table" border="1">
<tr><th>CN</th><th>Test</th><th>Right</th><th>Left</th></tr>
<tr><td>I</td><td>Smell</td><td></td><td></td></tr>
<tr><td>II</td><td>Acuity / Fields / Fundoscopy</td><td></td><td></td></tr>
<tr><td>III,IV,VI</td><td>EOM / Pupils</td><td></td><td></td></tr>
<tr><td>V</td><td>Sensation / Motor / Corneal reflex</td><td></td><td></td></tr>
<tr><td>VII</td><td>Facial symmetry (UMN/LMN)</td><td></td><td></td></tr>
<tr><td>VIII</td><td>Hearing / Rinne / Weber</td><td></td><td></td></tr>
<tr><td>IX,X</td><td>Palate / Gag</td><td></td><td></td></tr>
<tr><td>XI</td><td>Trapezius / SCM</td><td></td><td></td></tr>
<tr><td>XII</td><td>Tongue</td><td></td><td></td></tr>
</table>

<p><strong>Motor Examination:</strong></p>
<table class="table" border="1">
<tr><th></th><th>R Upper</th><th>L Upper</th><th>R Lower</th><th>L Lower</th></tr>
<tr><td><strong>Tone</strong></td><td></td><td></td><td></td><td></td></tr>
<tr><td><strong>Power (0-5)</strong></td><td></td><td></td><td></td><td></td></tr>
<tr><td><strong>Reflexes</strong></td><td></td><td></td><td></td><td></td></tr>
</table>
<p><strong>Plantars:</strong> R: [ ] Flexor [ ] Extensor &nbsp; L: [ ] Flexor [ ] Extensor</p>
<p><strong>Coordination:</strong> [Finger-nose, heel-shin, dysdiadochokinesia] &nbsp; Romberg: [ ] +ve [ ] -ve</p>
<p><strong>Sensation:</strong> [Light touch, pinprick, vibration, proprioception, temperature]</p>
<p><strong>Gait:</strong> [Normal / Hemiplegic / Ataxic / Parkinsonian / Steppage / Waddling]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Localization:</strong> [Cortical / Subcortical / Brainstem / Spinal / Peripheral / NMJ / Muscle]</p>
<p><strong>Diagnosis:</strong></p>
<p><strong>Investigations:</strong> [ ] MRI Brain [ ] CT Head [ ] EEG [ ] NCS/EMG [ ] LP [ ] Labs</p>
<p><strong>Treatment:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Dermatology' => [
                [
                    'name'     => 'Dermatology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Skin lesion assessment with morphology description',
                    'content'  => '<h3>DERMATOLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Rash / Itching / Lesion / Hair loss / Nail change — Duration, progression]</p>
<p><strong>Onset:</strong> [ ] Acute [ ] Chronic &nbsp; | &nbsp; <strong>Course:</strong> [ ] Stable [ ] Progressive [ ] Relapsing</p>

<h4>Skin Examination</h4>
<table class="table" border="1">
<tr><td><strong>Distribution</strong></td><td>[ ] Localized [ ] Generalized [ ] Symmetric [ ] Asymmetric</td></tr>
<tr><td><strong>Sites involved</strong></td><td>[Body regions]</td></tr>
<tr><td><strong>Primary Morphology</strong></td><td>[ ] Macule [ ] Papule [ ] Plaque [ ] Nodule [ ] Vesicle [ ] Bulla [ ] Pustule [ ] Wheal [ ] Patch</td></tr>
<tr><td><strong>Secondary Changes</strong></td><td>[ ] Scale [ ] Crust [ ] Erosion [ ] Ulcer [ ] Lichenification [ ] Excoriation [ ] Atrophy [ ] Scar</td></tr>
<tr><td><strong>Color</strong></td><td>[Erythematous / Hyperpigmented / Hypopigmented / Violaceous]</td></tr>
<tr><td><strong>Border</strong></td><td>[ ] Well-defined [ ] Ill-defined [ ] Irregular</td></tr>
<tr><td><strong>Size</strong></td><td>___mm/cm</td></tr>
<tr><td><strong>Shape</strong></td><td>[ ] Round [ ] Oval [ ] Annular [ ] Linear [ ] Irregular</td></tr>
<tr><td><strong>Surface</strong></td><td>[ ] Smooth [ ] Rough [ ] Verrucous [ ] Umbilicated</td></tr>
</table>
<p><strong>Nails:</strong> [Normal / Pitting / Onycholysis / Subungual debris / Dystrophy]</p>
<p><strong>Hair:</strong> [Normal / Alopecia — pattern, scarring/non-scarring]</p>
<p><strong>Mucous membranes:</strong> [If involved]</p>

<h4>Investigations</h4>
<p>[ ] Skin biopsy [ ] KOH [ ] Dermoscopy [ ] Patch test [ ] Wood\'s lamp [ ] Swab for C&amp;S</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Pulmonology' => [
                [
                    'name'     => 'Pulmonology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Respiratory-focused assessment',
                    'content'  => '<h3>PULMONOLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Cough / Dyspnea / Hemoptysis / Wheezing / Chest pain (pleuritic)]</p>

<h4>Respiratory History</h4>
<table class="table" border="1">
<tr><td><strong>Cough</strong></td><td>[ ] Dry [ ] Productive — Sputum: [Color, volume, blood] &nbsp; Duration: ___</td></tr>
<tr><td><strong>Dyspnea</strong></td><td>mMRC Grade: [ ] 0 [ ] 1 [ ] 2 [ ] 3 [ ] 4 &nbsp; | &nbsp; At rest: [ ] Yes [ ] No</td></tr>
<tr><td><strong>Wheezing</strong></td><td>[ ] Yes [ ] No &nbsp; Triggers: ___</td></tr>
<tr><td><strong>Smoking</strong></td><td>[ ] Never [ ] Current [ ] Ex — Pack-years: ___</td></tr>
<tr><td><strong>Occupational exposure</strong></td><td>[Dust, asbestos, chemicals]</td></tr>
<tr><td><strong>TB contact</strong></td><td>[ ] Yes [ ] No &nbsp; Previous TB: [ ] Yes [ ] No — Treatment: ___</td></tr>
</table>

<h4>Examination</h4>
<p><strong>General:</strong> [Cyanosis, clubbing, accessory muscle use, pursed-lip breathing]</p>
<p><strong>Chest:</strong></p>
<table class="table" border="1">
<tr><td><strong>Inspection</strong></td><td>[Shape, expansion symmetry, respiratory rate]</td></tr>
<tr><td><strong>Palpation</strong></td><td>[Trachea, expansion, tactile fremitus]</td></tr>
<tr><td><strong>Percussion</strong></td><td>[Resonant / Dull / Hyper-resonant]</td></tr>
<tr><td><strong>Auscultation</strong></td><td>[Air entry, breath sounds, added sounds — creps/wheeze/rub]</td></tr>
</table>
<p><strong>SpO2:</strong> ___% on [RA / O2 ___L]</p>

<h4>Investigations</h4>
<p><strong>CXR:</strong> [Findings]</p>
<table class="table" border="1">
<tr><th colspan="2">Spirometry</th></tr>
<tr><td>FEV1</td><td>___L (___% predicted)</td></tr>
<tr><td>FVC</td><td>___L</td></tr>
<tr><td>FEV1/FVC</td><td>___%</td></tr>
<tr><td>Post-BD change</td><td>___%</td></tr>
</table>
<p><strong>Other:</strong> [ ] CT chest [ ] ABG [ ] Sputum AFB [ ] Sputum C&amp;S [ ] Bronchoscopy</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Oncology' => [
                [
                    'name'     => 'Oncology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Cancer staging and treatment planning template',
                    'content'  => '<h3>ONCOLOGY CONSULTATION</h3>

<h4>Cancer Diagnosis</h4>
<table class="table" border="1">
<tr><td><strong>Histology</strong></td><td>[Type, grade, receptor status]</td></tr>
<tr><td><strong>Primary site</strong></td><td>[Organ]</td></tr>
<tr><td><strong>TNM Staging</strong></td><td>T___ N___ M___ &nbsp; Stage: ___</td></tr>
<tr><td><strong>Date of diagnosis</strong></td><td>[Date]</td></tr>
<tr><td><strong>Biopsy date</strong></td><td>[Date]</td></tr>
</table>

<h4>Performance Status</h4>
<p><strong>ECOG:</strong> [ ] 0 [ ] 1 [ ] 2 [ ] 3 [ ] 4</p>

<h4>Treatment History</h4>
<table class="table" border="1">
<tr><td><strong>Surgery</strong></td><td>[Type, date, margins]</td></tr>
<tr><td><strong>Chemotherapy</strong></td><td>[Regimen, cycles completed, dates, response]</td></tr>
<tr><td><strong>Radiotherapy</strong></td><td>[Site, dose, fractions, dates]</td></tr>
<tr><td><strong>Targeted/Immunotherapy</strong></td><td>[If applicable]</td></tr>
</table>

<h4>Current Symptoms</h4>
<p><strong>Pain:</strong> VAS ___/10 — [Location, character]</p>
<p><strong>Constitutional:</strong> Weight loss: ___kg in ___months &nbsp; Appetite: [ ] Good [ ] Poor &nbsp; Fatigue: [ ] Mild [ ] Moderate [ ] Severe</p>
<p><strong>Other symptoms:</strong> [Nausea, breathlessness, neuropathy, etc.]</p>

<h4>Examination</h4>
<p>[General condition, specific tumour site examination, lymph nodes, organomegaly]</p>

<h4>Imaging / Labs</h4>
<p>[CT, PET, tumour markers, FBC, renal, hepatic function]</p>

<h4>MDT Discussion</h4>
<p>[ ] Discussed at MDT on [Date] &nbsp; Recommendation: ___</p>

<h4>Plan</h4>
<p><strong>Treatment intent:</strong> [ ] Curative [ ] Palliative</p>
<p><strong>Proposed treatment:</strong></p>
<p><strong>Supportive care:</strong> [Pain management, nutrition, psychosocial]</p>
<p><strong>Follow-up imaging:</strong> [Modality, date]</p>',
                ],
            ],

            'Palliative Care' => [
                [
                    'name'     => 'Palliative Care Assessment',
                    'category' => 'First Visit',
                    'description' => 'Holistic palliative care assessment',
                    'content'  => '<h3>PALLIATIVE CARE ASSESSMENT</h3>

<h4>Primary Diagnosis</h4>
<p>[Disease, stage, prognosis estimate]</p>
<p><strong>Performance status</strong> (PPS/ECOG): ___</p>

<h4>Symptom Assessment</h4>
<table class="table" border="1">
<tr><th>Symptom</th><th>Severity (0-10)</th><th>Current Management</th><th>Effective?</th></tr>
<tr><td>Pain</td><td></td><td></td><td></td></tr>
<tr><td>Nausea/Vomiting</td><td></td><td></td><td></td></tr>
<tr><td>Breathlessness</td><td></td><td></td><td></td></tr>
<tr><td>Fatigue</td><td></td><td></td><td></td></tr>
<tr><td>Constipation</td><td></td><td></td><td></td></tr>
<tr><td>Anxiety</td><td></td><td></td><td></td></tr>
<tr><td>Depression</td><td></td><td></td><td></td></tr>
<tr><td>Insomnia</td><td></td><td></td><td></td></tr>
<tr><td>Appetite</td><td></td><td></td><td></td></tr>
</table>

<h4>Psychosocial Assessment</h4>
<p><strong>Understanding of illness:</strong> [Patient\'s awareness of diagnosis and prognosis]</p>
<p><strong>Emotional state:</strong> [Coping, fears, anxieties]</p>
<p><strong>Family/carer:</strong> [Support available, carer burden, family dynamics]</p>
<p><strong>Spiritual needs:</strong> [Religious beliefs, spiritual distress, chaplaincy referral]</p>
<p><strong>Financial concerns:</strong> [ ] Yes [ ] No — Details:</p>

<h4>Advance Care Planning</h4>
<table class="table" border="1">
<tr><td><strong>ACP discussed</strong></td><td>[ ] Yes [ ] No [ ] Not ready</td></tr>
<tr><td><strong>Resuscitation status</strong></td><td>[ ] Full code [ ] DNAR — Documented: [ ] Yes</td></tr>
<tr><td><strong>Preferred place of death</strong></td><td>[ ] Home [ ] Hospital [ ] Hospice [ ] Not discussed</td></tr>
<tr><td><strong>Power of Attorney</strong></td><td>[ ] Appointed [ ] Not yet</td></tr>
</table>

<h4>Plan</h4>
<p><strong>Symptom management changes:</strong></p>
<p><strong>Referrals:</strong> [ ] Social work [ ] Psychology [ ] Chaplain [ ] Home care [ ] Hospice</p>
<p><strong>Goals of care:</strong> [Comfort, function, specific patient goals]</p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Emergency Medicine' => [
                [
                    'name'     => 'Trauma Primary Survey',
                    'category' => 'Emergency',
                    'description' => 'ATLS trauma primary and secondary survey',
                    'content'  => '<h3>TRAUMA ASSESSMENT</h3>

<h4>Pre-Hospital</h4>
<p><strong>Mechanism:</strong> [RTA / Fall (height: ___) / Assault / Penetrating / Blast / Burns]</p>
<p><strong>GCS at scene:</strong> ___ &nbsp; | &nbsp; <strong>Interventions:</strong> [C-spine, IV, intubation]</p>

<h4>Primary Survey</h4>
<table class="table" border="1">
<tr><td><strong>A (Airway + C-spine)</strong></td><td>[ ] Patent [ ] Obstructed — Intervention: ___ &nbsp; C-spine: [ ] Immobilized</td></tr>
<tr><td><strong>B (Breathing)</strong></td><td>RR: ___ SpO2: ___% &nbsp; Chest: [ ] Bilateral AE [ ] Pneumothorax [ ] Hemothorax</td></tr>
<tr><td><strong>C (Circulation)</strong></td><td>HR: ___ BP: ___/___ &nbsp; [ ] IV access x___ &nbsp; Fluids: ___ml</td></tr>
<tr><td><strong>D (Disability)</strong></td><td>GCS: E___V___M___ = ___ &nbsp; Pupils: L___mm R___mm [ ] Equal [ ] Reactive &nbsp; Glucose: ___</td></tr>
<tr><td><strong>E (Exposure)</strong></td><td>Temp: ___°C &nbsp; Log roll: [ ] Done</td></tr>
</table>

<h4>Secondary Survey (Head to Toe)</h4>
<table class="table" border="1">
<tr><td><strong>Head/Face</strong></td><td>[Lacerations, hematoma, Battle sign, raccoon eyes, CSF leak]</td></tr>
<tr><td><strong>Neck</strong></td><td>[Tenderness, deformity, trachea, JVP, subcutaneous emphysema]</td></tr>
<tr><td><strong>Chest</strong></td><td>[Flail, crepitus, wounds, rib tenderness]</td></tr>
<tr><td><strong>Abdomen</strong></td><td>[Tenderness, rigidity, distension, pelvis stability]</td></tr>
<tr><td><strong>Pelvis</strong></td><td>[ ] Stable [ ] Unstable — Binder: [ ] Applied</td></tr>
<tr><td><strong>Extremities</strong></td><td>[Fractures, dislocations, pulses, compartments]</td></tr>
<tr><td><strong>Back/Spine</strong></td><td>[Tenderness, step, wounds (log roll)]</td></tr>
<tr><td><strong>Perineum</strong></td><td>[If indicated — blood at meatus, scrotal hematoma]</td></tr>
</table>

<h4>FAST Scan</h4>
<p>[ ] Negative [ ] Positive — [RUQ / LUQ / Pelvis / Pericardium]</p>

<h4>Imaging</h4>
<p>[ ] CXR [ ] Pelvis XR [ ] CT Head [ ] CT C-spine [ ] CT Chest/Abdomen/Pelvis [ ] Focused XR: ___</p>

<h4>Injuries Identified</h4>
<ol><li></li><li></li></ol>

<h4>Plan</h4>
<p><strong>Disposition:</strong> [ ] Resus [ ] Theatre [ ] ICU [ ] Ward [ ] Transfer &nbsp; | &nbsp; <strong>Blood products:</strong> [ ] None [ ] PRBCs [ ] MTP</p>
<p><strong>Specialty consults:</strong></p>
<p><strong>Tetanus:</strong> [ ] Up to date [ ] Given [ ] Not indicated</p>',
                ],
            ],

            'Nephrology' => [
                [
                    'name'     => 'Nephrology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Renal assessment with CKD staging',
                    'content'  => '<h3>NEPHROLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Elevated creatinine / Proteinuria / Hematuria / Edema / Electrolyte abnormality / Dialysis review]</p>

<h4>Renal History</h4>
<table class="table" border="1">
<tr><td><strong>Known CKD</strong></td><td>[ ] Yes — Stage: ___ [ ] New finding</td></tr>
<tr><td><strong>Etiology</strong></td><td>[ ] DM [ ] HTN [ ] GN [ ] PKD [ ] Obstruction [ ] Unknown [ ] Other: ___</td></tr>
<tr><td><strong>Dialysis</strong></td><td>[ ] None [ ] HD (___x/week, since ___) [ ] PD (since ___)</td></tr>
<tr><td><strong>Transplant</strong></td><td>[ ] None [ ] Previous (Year: ___, Donor type: ___)</td></tr>
</table>

<h4>Examination</h4>
<p><strong>BP:</strong> ___/___ &nbsp; <strong>Wt:</strong> ___kg (Dry weight: ___kg) &nbsp; <strong>Edema:</strong> [ ] None [ ] Pedal [ ] Sacral [ ] Anasarca</p>
<p><strong>Volume status:</strong> [ ] Euvolemic [ ] Hypovolemic [ ] Hypervolemic</p>
<p><strong>AV Fistula/Graft:</strong> [If applicable — thrill, bruit, maturation]</p>

<h4>Investigations</h4>
<table class="table" border="1">
<tr><th>Test</th><th>Result</th><th>Previous</th></tr>
<tr><td>Creatinine</td><td></td><td></td></tr>
<tr><td>eGFR</td><td></td><td></td></tr>
<tr><td>Urea</td><td></td><td></td></tr>
<tr><td>K+</td><td></td><td></td></tr>
<tr><td>Na+</td><td></td><td></td></tr>
<tr><td>Ca2+ / PO4 / PTH</td><td></td><td></td></tr>
<tr><td>Albumin</td><td></td><td></td></tr>
<tr><td>Urine ACR / PCR</td><td></td><td></td></tr>
<tr><td>Hb / Ferritin / TSAT</td><td></td><td></td></tr>
</table>
<p><strong>Imaging:</strong> US KUB — Kidney sizes: R: ___cm L: ___cm &nbsp; Echogenicity: &nbsp; Obstruction: [ ] Yes [ ] No</p>

<h4>CKD Stage</h4>
<p><strong>eGFR-based:</strong> [ ] G1 [ ] G2 [ ] G3a [ ] G3b [ ] G4 [ ] G5 &nbsp; | &nbsp; <strong>Albuminuria:</strong> [ ] A1 [ ] A2 [ ] A3</p>

<h4>Plan</h4>
<p><strong>BP target:</strong> &lt;___/___</p>
<p><strong>Medications:</strong> [ACEi/ARB, EPO, phosphate binders, bicarb, etc.]</p>
<p><strong>Diet:</strong> [Low K+, low PO4, protein restriction, fluid limit]</p>
<p><strong>Dialysis planning:</strong> [If applicable]</p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'General' => [
                [
                    'name'     => 'General Consultation',
                    'category' => 'First Visit',
                    'description' => 'Standard general outpatient consultation',
                    'content'  => '<h3>GENERAL CONSULTATION</h3>

<h4>Chief Complaint</h4>
<p>[Main complaint in patient\'s own words, duration]</p>

<h4>History of Presenting Illness</h4>
<p>[Onset, character, location, duration, aggravating/relieving factors, associated symptoms]</p>

<h4>Past Medical/Surgical History</h4>
<p>[Chronic conditions, previous surgeries, hospitalizations]</p>

<h4>Drug History &amp; Allergies</h4>
<p><strong>Current medications:</strong></p>
<p><strong>Allergies:</strong> [ ] NKDA [ ] ___________</p>

<h4>Social History</h4>
<p><strong>Smoking:</strong> [ ] Never [ ] Current [ ] Ex &nbsp; | &nbsp; <strong>Alcohol:</strong> [ ] None [ ] Social [ ] Heavy</p>
<p><strong>Occupation:</strong></p>

<h4>Examination</h4>
<p><strong>Vital Signs:</strong> BP: / HR: / Temp: / RR: / SpO2: / Wt: / BMI:</p>
<p><strong>General:</strong> [Appearance, alertness, distress level]</p>
<p><strong>Focused Examination:</strong><br>[System-specific findings based on presenting complaint]</p>

<h4>Assessment</h4>
<p><strong>Diagnosis/Impression:</strong></p>
<p><strong>Differentials:</strong></p>

<h4>Plan</h4>
<p><strong>Investigations:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Patient education:</strong></p>
<p><strong>Follow-up:</strong></p>
<p><strong>Disposition:</strong> [ ] Discharge [ ] Admit [ ] Refer</p>',
                ],
            ],

            'Family Physician' => [
                [
                    'name'     => 'Family Medicine Consultation',
                    'category' => 'First Visit',
                    'description' => 'Comprehensive family medicine visit with preventive care focus',
                    'content'  => '<h3>FAMILY MEDICINE CONSULTATION</h3>

<h4>Chief Complaint</h4>
<p>[Main reason for visit]</p>

<h4>History of Presenting Illness</h4>
<p>[Detailed history with attention to psychosocial context]</p>

<h4>Chronic Disease Review</h4>
<table class="table" border="1">
<tr><th>Condition</th><th>Status</th><th>Current Treatment</th><th>Compliance</th></tr>
<tr><td>Hypertension</td><td>[ ] Yes [ ] No</td><td></td><td></td></tr>
<tr><td>Diabetes</td><td>[ ] Yes [ ] No</td><td></td><td></td></tr>
<tr><td>Dyslipidemia</td><td>[ ] Yes [ ] No</td><td></td><td></td></tr>
<tr><td>Asthma/COPD</td><td>[ ] Yes [ ] No</td><td></td><td></td></tr>
<tr><td>Other</td><td></td><td></td><td></td></tr>
</table>

<h4>Preventive Care Screening</h4>
<table class="table" border="1">
<tr><th>Screening</th><th>Last Done</th><th>Due</th></tr>
<tr><td>Blood pressure</td><td></td><td></td></tr>
<tr><td>Blood glucose/HbA1c</td><td></td><td></td></tr>
<tr><td>Lipid profile</td><td></td><td></td></tr>
<tr><td>Cancer screening (cervical/breast/colon)</td><td></td><td></td></tr>
<tr><td>Immunizations</td><td></td><td></td></tr>
<tr><td>Vision/Hearing</td><td></td><td></td></tr>
</table>

<h4>Lifestyle Assessment</h4>
<p><strong>Diet:</strong> [Quality, portion control] &nbsp; | &nbsp; <strong>Exercise:</strong> [Frequency, type]</p>
<p><strong>Smoking:</strong> [ ] Never [ ] Current [ ] Ex &nbsp; | &nbsp; <strong>Alcohol:</strong></p>
<p><strong>Mental health:</strong> [ ] PHQ-2 score: ___ &nbsp; Sleep: [ ] Adequate [ ] Poor</p>

<h4>Examination</h4>
<p><strong>Vital Signs:</strong> BP: / HR: / Temp: / Wt: / BMI:</p>
<p><strong>General &amp; focused examination:</strong></p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Active problems:</strong></p>
<ol><li></li><li></li></ol>
<p><strong>Treatment:</strong></p>
<p><strong>Health education:</strong></p>
<p><strong>Referrals:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Internal Medicine' => [
                [
                    'name'     => 'Internal Medicine Consultation',
                    'category' => 'First Visit',
                    'description' => 'Comprehensive internal medicine clerking',
                    'content'  => '<h3>INTERNAL MEDICINE CONSULTATION</h3>

<h4>Chief Complaint</h4>
<p>[Primary complaint with duration]</p>

<h4>History of Presenting Illness</h4>
<p>[Detailed chronological narrative, SOCRATES for pain, relevant positives and negatives]</p>

<h4>Past Medical History</h4>
<p>[Chronic illnesses — HTN, DM, CKD, liver disease, heart disease, TB, HIV, etc.]</p>

<h4>Drug History &amp; Allergies</h4>
<p><strong>Medications:</strong> [Name, dose, frequency]</p>
<p><strong>Allergies:</strong> [ ] NKDA [ ] ___________</p>

<h4>Social History</h4>
<p><strong>Smoking:</strong> [Pack-years] &nbsp; | &nbsp; <strong>Alcohol:</strong> [Units/week] &nbsp; | &nbsp; <strong>Occupation:</strong></p>

<h4>Review of Systems</h4>
<table class="table" border="1">
<tr><td><strong>CVS</strong></td><td>[Chest pain, palpitations, orthopnea, PND, edema]</td></tr>
<tr><td><strong>RS</strong></td><td>[Cough, sputum, hemoptysis, dyspnea, wheeze]</td></tr>
<tr><td><strong>GIT</strong></td><td>[Appetite, nausea, vomiting, abdo pain, change in bowel habit, GI bleeding]</td></tr>
<tr><td><strong>GUS</strong></td><td>[Frequency, nocturia, dysuria, hematuria, retention]</td></tr>
<tr><td><strong>MSS</strong></td><td>[Joint pain, stiffness, swelling, weakness]</td></tr>
<tr><td><strong>CNS</strong></td><td>[Headache, dizziness, seizures, numbness, visual changes]</td></tr>
<tr><td><strong>Endo</strong></td><td>[Weight change, heat/cold intolerance, polydipsia, polyuria]</td></tr>
</table>

<h4>Examination</h4>
<p><strong>Vital Signs:</strong> BP: / HR: / Temp: / RR: / SpO2:</p>
<p><strong>General:</strong> [Appearance, pallor, jaundice, cyanosis, clubbing, lymphadenopathy, edema]</p>
<p><strong>Systemic Examination:</strong></p>
<table class="table" border="1">
<tr><th>System</th><th>Findings</th></tr>
<tr><td>CVS</td><td></td></tr>
<tr><td>RS</td><td></td></tr>
<tr><td>Abdomen</td><td></td></tr>
<tr><td>CNS</td><td></td></tr>
</table>

<h4>Assessment</h4>
<p><strong>Working Diagnosis:</strong></p>
<p><strong>Differentials:</strong></p>
<ol><li></li><li></li><li></li></ol>

<h4>Plan</h4>
<p><strong>Investigations:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Disposition:</strong> [ ] Discharge [ ] Admit [ ] Refer</p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Gastroenterology' => [
                [
                    'name'     => 'Gastroenterology Consultation',
                    'category' => 'First Visit',
                    'description' => 'GI-focused assessment with abdominal examination',
                    'content'  => '<h3>GASTROENTEROLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Abdominal pain / Dyspepsia / Dysphagia / Nausea/Vomiting / Diarrhea / Constipation / GI bleeding / Jaundice / Weight loss]</p>

<h4>GI History</h4>
<table class="table" border="1">
<tr><td><strong>Appetite</strong></td><td>[ ] Normal [ ] Reduced [ ] Increased</td></tr>
<tr><td><strong>Weight change</strong></td><td>[ ] Stable [ ] Loss ___kg in ___months [ ] Gain</td></tr>
<tr><td><strong>Swallowing</strong></td><td>[ ] Normal [ ] Dysphagia — Solids/Liquids/Both &nbsp; Level: ___</td></tr>
<tr><td><strong>Nausea/Vomiting</strong></td><td>[ ] None [ ] Nausea [ ] Vomiting — Content: [food/bile/blood/coffee-ground]</td></tr>
<tr><td><strong>Bowel habit</strong></td><td>Frequency: ___/day &nbsp; Consistency: Bristol ___ &nbsp; [ ] Blood [ ] Mucus [ ] Tenesmus</td></tr>
<tr><td><strong>GI bleeding</strong></td><td>[ ] None [ ] Hematemesis [ ] Melena [ ] Hematochezia</td></tr>
<tr><td><strong>Jaundice</strong></td><td>[ ] None [ ] Yes — Urine: [ ] Dark &nbsp; Stool: [ ] Pale &nbsp; Itching: [ ] Yes [ ] No</td></tr>
</table>

<h4>Risk Factors</h4>
<p><strong>Alcohol:</strong> [Units/week] &nbsp; | &nbsp; <strong>NSAIDs:</strong> [ ] Yes [ ] No &nbsp; | &nbsp; <strong>H. pylori:</strong> [ ] Tested [ ] Positive [ ] Not tested</p>
<p><strong>Family Hx:</strong> [GI cancers, IBD, celiac, liver disease]</p>
<p><strong>Travel:</strong> [Recent travel to endemic areas]</p>

<h4>Examination</h4>
<p><strong>General:</strong> [Jaundice, pallor, cachexia, spider naevi, palmar erythema]</p>
<p><strong>Abdomen:</strong></p>
<table class="table" border="1">
<tr><td><strong>Inspection</strong></td><td>[Distension, scars, visible veins, masses]</td></tr>
<tr><td><strong>Palpation</strong></td><td>[Tenderness (location), guarding, rebound, hepatomegaly (___cm), splenomegaly, masses]</td></tr>
<tr><td><strong>Percussion</strong></td><td>[Liver span: ___cm, shifting dullness, tympany]</td></tr>
<tr><td><strong>Auscultation</strong></td><td>[Bowel sounds — normal/hyperactive/absent]</td></tr>
</table>
<p><strong>DRE:</strong> [If indicated — masses, blood, stool in rectum]</p>

<h4>Investigations</h4>
<p>FBC: / LFT: / Amylase/Lipase: / CRP: / Celiac screen: / H. pylori:</p>
<p><strong>Imaging:</strong> [US abdomen / CT / MRI / Barium]</p>
<p><strong>Endoscopy:</strong> [ ] OGD [ ] Colonoscopy — Findings: ___</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Diet advice:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Hematology' => [
                [
                    'name'     => 'Hematology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Hematological assessment with blood count review',
                    'content'  => '<h3>HEMATOLOGY CONSULTATION</h3>

<h4>Presenting Complaint / Referral Reason</h4>
<p>[Anemia / Bleeding / Thrombocytopenia / Leukocytosis / Lymphadenopathy / Abnormal blood film / Coagulopathy]</p>

<h4>Hematological History</h4>
<table class="table" border="1">
<tr><td><strong>Bleeding tendency</strong></td><td>[ ] None [ ] Easy bruising [ ] Mucosal bleeding [ ] Menorrhagia [ ] Post-surgical bleeding</td></tr>
<tr><td><strong>Thrombosis history</strong></td><td>[ ] None [ ] DVT [ ] PE [ ] CVA [ ] Other: ___</td></tr>
<tr><td><strong>Transfusion history</strong></td><td>[ ] None [ ] Previous — Reactions: [ ] Yes [ ] No</td></tr>
<tr><td><strong>Family Hx</strong></td><td>[Sickle cell, thalassemia, hemophilia, malignancy, bleeding disorders]</td></tr>
</table>

<h4>Constitutional Symptoms</h4>
<p>[ ] Fatigue [ ] Weight loss [ ] Night sweats [ ] Fever [ ] Pruritus [ ] Bone pain</p>

<h4>Examination</h4>
<p><strong>General:</strong> [Pallor, jaundice, petechiae, purpura, bruises, lymphadenopathy]</p>
<p><strong>Lymph nodes:</strong></p>
<table class="table" border="1">
<tr><th>Site</th><th>Palpable</th><th>Size</th><th>Consistency</th></tr>
<tr><td>Cervical</td><td></td><td></td><td></td></tr>
<tr><td>Axillary</td><td></td><td></td><td></td></tr>
<tr><td>Inguinal</td><td></td><td></td><td></td></tr>
<tr><td>Other</td><td></td><td></td><td></td></tr>
</table>
<p><strong>Hepatomegaly:</strong> [ ] No [ ] Yes ___cm &nbsp; | &nbsp; <strong>Splenomegaly:</strong> [ ] No [ ] Yes ___cm</p>
<p><strong>Skin/Mucosae:</strong> [Petechiae, purpura, ecchymoses, gum hypertrophy]</p>

<h4>Investigations</h4>
<table class="table" border="1">
<tr><th>Test</th><th>Result</th><th>Reference</th></tr>
<tr><td>Hb</td><td></td><td></td></tr>
<tr><td>WBC</td><td></td><td></td></tr>
<tr><td>Platelets</td><td></td><td></td></tr>
<tr><td>MCV / MCH / MCHC</td><td></td><td></td></tr>
<tr><td>Reticulocyte count</td><td></td><td></td></tr>
<tr><td>Peripheral blood film</td><td></td><td></td></tr>
<tr><td>Ferritin / Iron / TIBC</td><td></td><td></td></tr>
<tr><td>B12 / Folate</td><td></td><td></td></tr>
<tr><td>Coagulation (PT/INR/aPTT)</td><td></td><td></td></tr>
<tr><td>LDH / Haptoglobin / Bilirubin</td><td></td><td></td></tr>
</table>
<p><strong>Bone marrow:</strong> [ ] Not done [ ] Done — Findings: ___</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong></p>
<p><strong>Transfusion plan:</strong> [ ] Not needed [ ] PRBCs [ ] Platelets [ ] FFP</p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Rheumatology' => [
                [
                    'name'     => 'Rheumatology Consultation',
                    'category' => 'First Visit',
                    'description' => 'Musculoskeletal and autoimmune assessment',
                    'content'  => '<h3>RHEUMATOLOGY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Joint pain / Swelling / Stiffness / Rash / Fatigue / Muscle weakness / Raynaud\'s]</p>

<h4>Joint Assessment</h4>
<table class="table" border="1">
<tr><td><strong>Pattern</strong></td><td>[ ] Mono [ ] Oligo [ ] Polyarticular &nbsp; | &nbsp; [ ] Symmetric [ ] Asymmetric</td></tr>
<tr><td><strong>Distribution</strong></td><td>[ ] Large joints [ ] Small joints [ ] Axial [ ] Mixed</td></tr>
<tr><td><strong>Morning stiffness</strong></td><td>Duration: ___minutes/hours</td></tr>
<tr><td><strong>Onset</strong></td><td>[ ] Acute [ ] Insidious &nbsp; Duration: ___</td></tr>
</table>

<h4>Active Joint Count</h4>
<p><strong>Tender joints:</strong> ___/28 &nbsp; | &nbsp; <strong>Swollen joints:</strong> ___/28</p>
<p><strong>Joints involved:</strong> [List affected joints]</p>

<h4>Extra-Articular Features</h4>
<table class="table" border="1">
<tr><td><strong>Skin</strong></td><td>[ ] Rash [ ] Photosensitivity [ ] Nodules [ ] Psoriasis [ ] Raynaud\'s [ ] Oral ulcers</td></tr>
<tr><td><strong>Eyes</strong></td><td>[ ] Dry eyes [ ] Red eyes [ ] Uveitis</td></tr>
<tr><td><strong>Lungs</strong></td><td>[ ] Dyspnea [ ] ILD [ ] Pleurisy</td></tr>
<tr><td><strong>Renal</strong></td><td>[ ] Proteinuria [ ] Hematuria</td></tr>
<tr><td><strong>Neuro</strong></td><td>[ ] Neuropathy [ ] CNS involvement</td></tr>
<tr><td><strong>Constitutional</strong></td><td>[ ] Fatigue [ ] Fever [ ] Weight loss</td></tr>
</table>

<h4>Functional Assessment</h4>
<p><strong>HAQ/DAS28 Score:</strong> ___</p>
<p><strong>ADL impact:</strong> [Dressing, grip, walking, stairs, work]</p>

<h4>Investigations</h4>
<table class="table" border="1">
<tr><th>Test</th><th>Result</th></tr>
<tr><td>ESR / CRP</td><td></td></tr>
<tr><td>RF</td><td></td></tr>
<tr><td>Anti-CCP</td><td></td></tr>
<tr><td>ANA</td><td></td></tr>
<tr><td>dsDNA / ENA</td><td></td></tr>
<tr><td>HLA-B27</td><td></td></tr>
<tr><td>Uric acid</td><td></td></tr>
<tr><td>Complement (C3/C4)</td><td></td></tr>
</table>
<p><strong>Imaging:</strong> [X-ray / US / MRI — joint findings, erosions, synovitis]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Treatment:</strong> [ ] NSAIDs [ ] Steroids [ ] csDMARD [ ] bDMARD [ ] tsDMARD</p>
<p><strong>Physiotherapy:</strong> [ ] Referred</p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Infectious Disease' => [
                [
                    'name'     => 'Infectious Disease Consultation',
                    'category' => 'First Visit',
                    'description' => 'Infection-focused assessment with antimicrobial review',
                    'content'  => '<h3>INFECTIOUS DISEASE CONSULTATION</h3>

<h4>Reason for Consultation</h4>
<p>[Pyrexia of unknown origin / Sepsis / Specific infection / Antimicrobial advice / Returning traveler / HIV management]</p>

<h4>Infection History</h4>
<table class="table" border="1">
<tr><td><strong>Onset of illness</strong></td><td>[Date, duration]</td></tr>
<tr><td><strong>Fever pattern</strong></td><td>[Continuous / Intermittent / Remittent / Relapsing] &nbsp; Peak: ___°C</td></tr>
<tr><td><strong>Travel history</strong></td><td>[Countries, dates, activities — within 6 months]</td></tr>
<tr><td><strong>Exposure</strong></td><td>[ ] Sick contacts [ ] Animals [ ] Insects [ ] Food/Water [ ] Sexual</td></tr>
<tr><td><strong>Immunizations</strong></td><td>[Status, recent vaccines]</td></tr>
<tr><td><strong>HIV status</strong></td><td>[ ] Negative (date: ___) [ ] Positive (CD4: ___, VL: ___, ART: ___) [ ] Unknown</td></tr>
</table>

<h4>Current Antimicrobials</h4>
<table class="table" border="1">
<tr><th>Drug</th><th>Dose</th><th>Route</th><th>Start Date</th><th>Day #</th></tr>
<tr><td></td><td></td><td></td><td></td><td></td></tr>
</table>
<p><strong>Previous antibiotics this illness:</strong> [List with duration and response]</p>

<h4>Microbiology Results</h4>
<table class="table" border="1">
<tr><th>Specimen</th><th>Date</th><th>Organism</th><th>Sensitivity</th></tr>
<tr><td>Blood culture</td><td></td><td></td><td></td></tr>
<tr><td>Urine C&amp;S</td><td></td><td></td><td></td></tr>
<tr><td>Wound/Sputum/Other</td><td></td><td></td><td></td></tr>
</table>

<h4>Examination</h4>
<p><strong>Vital Signs:</strong> Temp: / HR: / BP: / RR: / SpO2:</p>
<p><strong>SIRS criteria met:</strong> [ ] Yes [ ] No &nbsp; | &nbsp; <strong>qSOFA:</strong> ___/3</p>
<p><strong>Source examination:</strong> [Skin, Lines, Chest, Abdomen, Urine, CNS, Joints, Wounds]</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Likely source:</strong></p>
<p><strong>Likely organism:</strong></p>
<p><strong>Antimicrobial recommendation:</strong></p>
<p><strong>Duration of therapy:</strong></p>
<p><strong>Additional investigations:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Anesthesiology' => [
                [
                    'name'     => 'Pre-Anesthetic Assessment',
                    'category' => 'Pre-Op',
                    'description' => 'Pre-operative anesthetic evaluation and airway assessment',
                    'content'  => '<h3>PRE-ANESTHETIC ASSESSMENT</h3>

<h4>Proposed Surgery</h4>
<p><strong>Procedure:</strong> [Name]</p>
<p><strong>Surgeon:</strong></p>
<p><strong>Urgency:</strong> [ ] Elective [ ] Urgent [ ] Emergency</p>

<h4>Patient Assessment</h4>
<table class="table" border="1">
<tr><td><strong>ASA Classification</strong></td><td>[ ] I [ ] II [ ] III [ ] IV [ ] V</td></tr>
<tr><td><strong>BMI</strong></td><td></td></tr>
<tr><td><strong>Functional capacity</strong></td><td>[ ] &gt;4 METs [ ] &lt;4 METs</td></tr>
</table>

<h4>Medical History</h4>
<table class="table" border="1">
<tr><td><strong>Cardiovascular</strong></td><td>[HTN, IHD, heart failure, valvular disease, pacemaker, stents]</td></tr>
<tr><td><strong>Respiratory</strong></td><td>[Asthma, COPD, OSA, recent URTI]</td></tr>
<tr><td><strong>Neurological</strong></td><td>[CVA, seizures, raised ICP]</td></tr>
<tr><td><strong>Endocrine</strong></td><td>[DM, thyroid, adrenal]</td></tr>
<tr><td><strong>Hepatic / Renal</strong></td><td>[Liver disease, CKD stage]</td></tr>
<tr><td><strong>Hematological</strong></td><td>[Bleeding disorder, anticoagulant use, sickle cell]</td></tr>
</table>

<h4>Airway Assessment</h4>
<table class="table" border="1">
<tr><td><strong>Mallampati</strong></td><td>[ ] I [ ] II [ ] III [ ] IV</td></tr>
<tr><td><strong>Mouth opening</strong></td><td>___cm (≥3cm normal)</td></tr>
<tr><td><strong>Thyromental distance</strong></td><td>___cm (≥6.5cm normal)</td></tr>
<tr><td><strong>Neck mobility</strong></td><td>[ ] Normal [ ] Limited</td></tr>
<tr><td><strong>Dentition</strong></td><td>[ ] Normal [ ] Loose teeth [ ] Dentures [ ] Caps/Crowns</td></tr>
<tr><td><strong>Previous difficult airway</strong></td><td>[ ] No [ ] Yes — Details: ___</td></tr>
</table>
<p><strong>Predicted difficulty:</strong> [ ] Easy [ ] Potentially difficult [ ] Known difficult</p>

<h4>Previous Anesthetic History</h4>
<p>[ ] None [ ] Uneventful [ ] Complications: [PONV, awareness, difficult intubation, MH family hx]</p>

<h4>Medications &amp; Allergies</h4>
<p><strong>Current medications:</strong> [Especially anticoagulants, antihypertensives, insulin, steroids]</p>
<p><strong>Allergies:</strong> [ ] NKDA [ ] ___________</p>
<p><strong>Fasting status:</strong> Last solids: [Time] &nbsp; Last clear fluids: [Time]</p>

<h4>Investigations</h4>
<p>FBC: / U&amp;E: / Glucose: / Coag: / G&amp;S/XM: / ECG: / CXR: / Echo:</p>

<h4>Anesthetic Plan</h4>
<p><strong>Technique:</strong> [ ] GA [ ] Spinal [ ] Epidural [ ] Regional block [ ] Sedation [ ] Combined</p>
<p><strong>Airway plan:</strong> [ ] ETT [ ] LMA [ ] Face mask &nbsp; | &nbsp; RSI: [ ] Yes [ ] No</p>
<p><strong>Monitoring:</strong> [ ] Standard [ ] Arterial line [ ] CVP [ ] BIS</p>
<p><strong>Post-op plan:</strong> [ ] Ward [ ] HDU [ ] ICU &nbsp; | &nbsp; <strong>Analgesia:</strong> [Plan]</p>
<p><strong>Consent for anesthesia:</strong> [ ] Obtained — Risks discussed</p>',
                ],
            ],

            'Radiology' => [
                [
                    'name'     => 'Radiology Report',
                    'category' => 'Report',
                    'description' => 'Structured radiology reporting template',
                    'content'  => '<h3>RADIOLOGY REPORT</h3>

<h4>Study Information</h4>
<table class="table" border="1">
<tr><td><strong>Modality</strong></td><td>[ ] X-ray [ ] Ultrasound [ ] CT [ ] MRI [ ] Fluoroscopy [ ] Other: ___</td></tr>
<tr><td><strong>Body part</strong></td><td>[Region examined]</td></tr>
<tr><td><strong>Contrast</strong></td><td>[ ] None [ ] Oral [ ] IV [ ] Both &nbsp; Agent: ___ Volume: ___ml</td></tr>
<tr><td><strong>Clinical indication</strong></td><td>[Reason for study, clinical question]</td></tr>
<tr><td><strong>Comparison</strong></td><td>[Previous studies and dates]</td></tr>
</table>

<h4>Technique</h4>
<p>[Views obtained, sequences performed, protocol used]</p>

<h4>Findings</h4>
<p><strong>Primary findings:</strong></p>
<p>[Detailed description of relevant findings, organized by region/system]</p>

<p><strong>Secondary/Incidental findings:</strong></p>
<p>[Any additional notable findings]</p>

<h4>Impression</h4>
<ol>
<li>[Primary diagnosis/finding]</li>
<li>[Secondary finding]</li>
</ol>

<h4>Recommendation</h4>
<p>[Follow-up imaging, clinical correlation, further investigation if needed]</p>',
                ],
            ],

            'Neonatology' => [
                [
                    'name'     => 'Neonatal Admission',
                    'category' => 'First Visit',
                    'description' => 'NICU/SCBU neonatal admission assessment',
                    'content'  => '<h3>NEONATAL ADMISSION ASSESSMENT</h3>

<h4>Birth Details</h4>
<table class="table" border="1">
<tr><td><strong>Date/Time of Birth</strong></td><td></td></tr>
<tr><td><strong>Gestational Age</strong></td><td>___weeks + ___days</td></tr>
<tr><td><strong>Birth Weight</strong></td><td>___g (___percentile)</td></tr>
<tr><td><strong>Head Circumference</strong></td><td>___cm</td></tr>
<tr><td><strong>Length</strong></td><td>___cm</td></tr>
<tr><td><strong>Mode of Delivery</strong></td><td>[ ] SVD [ ] Instrumental [ ] Elective CS [ ] Emergency CS</td></tr>
<tr><td><strong>Indication for CS</strong></td><td>[If applicable]</td></tr>
<tr><td><strong>Presentation</strong></td><td>[ ] Cephalic [ ] Breech [ ] Transverse</td></tr>
</table>

<h4>Resuscitation</h4>
<table class="table" border="1">
<tr><td><strong>Apgar scores</strong></td><td>1 min: ___ &nbsp; 5 min: ___ &nbsp; 10 min: ___</td></tr>
<tr><td><strong>Resuscitation needed</strong></td><td>[ ] None [ ] Stimulation [ ] Suction [ ] O2 [ ] BVM [ ] Intubation [ ] Chest compressions [ ] Medications</td></tr>
<tr><td><strong>Cord gases</strong></td><td>pH: ___ BE: ___ Lactate: ___</td></tr>
</table>

<h4>Maternal History</h4>
<table class="table" border="1">
<tr><td><strong>Maternal age</strong></td><td></td></tr>
<tr><td><strong>Blood group</strong></td><td>___ Rh: ___</td></tr>
<tr><td><strong>GBS status</strong></td><td>[ ] Negative [ ] Positive [ ] Unknown &nbsp; Prophylaxis: [ ] Given [ ] Not given</td></tr>
<tr><td><strong>ROM</strong></td><td>Duration: ___hours &nbsp; Liquor: [ ] Clear [ ] Meconium [ ] Blood-stained</td></tr>
<tr><td><strong>Maternal conditions</strong></td><td>[GDM, PIH, pre-eclampsia, infections]</td></tr>
<tr><td><strong>Antenatal steroids</strong></td><td>[ ] Complete [ ] Partial [ ] None</td></tr>
</table>

<h4>Reason for Admission</h4>
<p>[Prematurity / RDS / Sepsis risk / Jaundice / Hypoglycemia / Congenital anomaly / Birth asphyxia / Low birth weight]</p>

<h4>Examination</h4>
<table class="table" border="1">
<tr><td><strong>General</strong></td><td>[Activity, tone, color, cry]</td></tr>
<tr><td><strong>Respiratory</strong></td><td>RR: ___ SpO2: ___% &nbsp; Support: [ ] None [ ] O2 [ ] CPAP [ ] Ventilated &nbsp; Silverma score: ___</td></tr>
<tr><td><strong>CVS</strong></td><td>HR: ___ &nbsp; Murmur: [ ] No [ ] Yes &nbsp; Femoral pulses: [ ] Present [ ] Absent</td></tr>
<tr><td><strong>Abdomen</strong></td><td>[Soft, umbilicus, organomegaly]</td></tr>
<tr><td><strong>CNS</strong></td><td>[Fontanelle, tone, reflexes, seizures]</td></tr>
<tr><td><strong>Skin</strong></td><td>[Jaundice, rashes, birthmarks]</td></tr>
<tr><td><strong>Dysmorphic features</strong></td><td>[ ] None [ ] Present: ___</td></tr>
</table>

<h4>Investigations Ordered</h4>
<p>[ ] FBC [ ] CRP [ ] Blood culture [ ] Blood glucose [ ] Bilirubin [ ] Blood gas [ ] Blood group [ ] G6PD</p>

<h4>Plan</h4>
<p><strong>Respiratory support:</strong></p>
<p><strong>IV fluids / Feeds:</strong></p>
<p><strong>Antibiotics:</strong></p>
<p><strong>Monitoring:</strong></p>
<p><strong>Investigations pending:</strong></p>',
                ],
            ],

            'Plastic Surgery' => [
                [
                    'name'     => 'Plastic Surgery Consultation',
                    'category' => 'First Visit',
                    'description' => 'Plastic and reconstructive surgery assessment',
                    'content'  => '<h3>PLASTIC SURGERY CONSULTATION</h3>

<h4>Presenting Complaint</h4>
<p>[Wound / Burn / Scar / Soft tissue defect / Congenital anomaly / Cosmetic concern / Hand injury]</p>

<h4>History</h4>
<p><strong>Mechanism/Cause:</strong> [Trauma / Burn / Post-surgical / Congenital / Tumor excision]</p>
<p><strong>Duration:</strong></p>
<p><strong>Previous treatment/surgery:</strong></p>

<h4>Wound/Defect Assessment</h4>
<table class="table" border="1">
<tr><td><strong>Location</strong></td><td>[Anatomical site]</td></tr>
<tr><td><strong>Size</strong></td><td>___cm x ___cm x ___cm (depth)</td></tr>
<tr><td><strong>Wound bed</strong></td><td>[ ] Granulation [ ] Slough [ ] Necrotic [ ] Exposed bone/tendon</td></tr>
<tr><td><strong>Edges</strong></td><td>[ ] Clean [ ] Irregular [ ] Undermined</td></tr>
<tr><td><strong>Surrounding skin</strong></td><td>[Erythema, induration, maceration]</td></tr>
<tr><td><strong>Exudate</strong></td><td>[ ] None [ ] Serous [ ] Purulent &nbsp; Volume: [ ] Minimal [ ] Moderate [ ] Heavy</td></tr>
<tr><td><strong>Infection signs</strong></td><td>[ ] None [ ] Present: ___</td></tr>
</table>

<h4>Burns Assessment (if applicable)</h4>
<table class="table" border="1">
<tr><td><strong>Agent</strong></td><td>[ ] Thermal [ ] Scald [ ] Chemical [ ] Electrical [ ] Friction</td></tr>
<tr><td><strong>TBSA</strong></td><td>___%</td></tr>
<tr><td><strong>Depth</strong></td><td>[ ] Superficial [ ] Superficial partial [ ] Deep partial [ ] Full thickness</td></tr>
<tr><td><strong>Special areas</strong></td><td>[ ] Face [ ] Hands [ ] Feet [ ] Perineum [ ] Circumferential</td></tr>
</table>

<h4>Hand Examination (if applicable)</h4>
<p><strong>Tendons:</strong> [FDP, FDS, extensors — integrity] &nbsp; | &nbsp; <strong>Nerves:</strong> [Median, ulnar, radial — sensation/motor]</p>
<p><strong>Vascular:</strong> [Allen\'s test, capillary refill]</p>

<h4>Photographs</h4>
<p>[ ] Taken [ ] Consent for photography obtained</p>

<h4>Assessment &amp; Plan</h4>
<p><strong>Diagnosis:</strong></p>
<p><strong>Reconstructive ladder:</strong> [ ] Primary closure [ ] Skin graft [ ] Local flap [ ] Regional flap [ ] Free flap [ ] Tissue expansion</p>
<p><strong>Dressing plan:</strong></p>
<p><strong>Surgery planned:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],

            'Nutrition / Dietetics' => [
                [
                    'name'     => 'Nutrition Assessment',
                    'category' => 'First Visit',
                    'description' => 'Comprehensive nutritional assessment and dietary plan',
                    'content'  => '<h3>NUTRITION ASSESSMENT</h3>

<h4>Referral Reason</h4>
<p>[Malnutrition / Obesity / Diabetes dietary management / Renal diet / Enteral feeding / Pre-surgical optimization / Other]</p>

<h4>Anthropometry</h4>
<table class="table" border="1">
<tr><td><strong>Weight</strong></td><td>___kg &nbsp; (Usual weight: ___kg)</td></tr>
<tr><td><strong>Height</strong></td><td>___cm</td></tr>
<tr><td><strong>BMI</strong></td><td>___ ([ ] Underweight [ ] Normal [ ] Overweight [ ] Obese)</td></tr>
<tr><td><strong>Weight change</strong></td><td>[ ] Stable [ ] Loss ___kg in ___weeks/months [ ] Gain ___kg</td></tr>
<tr><td><strong>Waist circumference</strong></td><td>___cm</td></tr>
<tr><td><strong>MUAC</strong></td><td>___cm (if applicable)</td></tr>
</table>

<h4>Nutritional Screening</h4>
<p><strong>MUST Score / NRS-2002 / SGA:</strong> ___</p>
<p><strong>Risk level:</strong> [ ] Low [ ] Medium [ ] High</p>

<h4>Dietary History (24-hour recall)</h4>
<table class="table" border="1">
<tr><th>Meal</th><th>Time</th><th>Food/Drink</th><th>Quantity</th></tr>
<tr><td>Breakfast</td><td></td><td></td><td></td></tr>
<tr><td>Snack</td><td></td><td></td><td></td></tr>
<tr><td>Lunch</td><td></td><td></td><td></td></tr>
<tr><td>Snack</td><td></td><td></td><td></td></tr>
<tr><td>Dinner</td><td></td><td></td><td></td></tr>
</table>
<p><strong>Fluid intake:</strong> ___L/day &nbsp; | &nbsp; <strong>Supplements:</strong> [Vitamins, ONS]</p>

<h4>GI Assessment</h4>
<p><strong>Appetite:</strong> [ ] Good [ ] Fair [ ] Poor &nbsp; | &nbsp; <strong>Nausea:</strong> [ ] Yes [ ] No</p>
<p><strong>Swallowing:</strong> [ ] Normal [ ] Dysphagia (IDDSI level: ___)</p>
<p><strong>Bowel:</strong> [ ] Normal [ ] Constipation [ ] Diarrhea</p>

<h4>Relevant Medical Conditions</h4>
<p>[DM, CKD, liver disease, food allergies, celiac, IBD]</p>

<h4>Nutritional Requirements</h4>
<table class="table" border="1">
<tr><td><strong>Estimated energy</strong></td><td>___kcal/day</td></tr>
<tr><td><strong>Protein</strong></td><td>___g/day</td></tr>
<tr><td><strong>Fluid</strong></td><td>___ml/day</td></tr>
<tr><td><strong>Special restrictions</strong></td><td>[Low K+, low PO4, low Na+, diabetic, renal, etc.]</td></tr>
</table>

<h4>Nutrition Plan</h4>
<p><strong>Diet prescription:</strong></p>
<p><strong>Supplements:</strong> [ ] ONS (___kcal/day) [ ] Vitamins/Minerals [ ] None</p>
<p><strong>Feeding route:</strong> [ ] Oral [ ] NG tube [ ] PEG [ ] Parenteral</p>
<p><strong>Goals:</strong></p>
<p><strong>Patient education provided:</strong></p>
<p><strong>Follow-up:</strong></p>',
                ],
            ],
        ];
    }
}
