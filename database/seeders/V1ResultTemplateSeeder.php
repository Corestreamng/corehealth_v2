<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\V1ResultTemplate;

class V1ResultTemplateSeeder extends Seeder
{
    /**
     * Seed V1 result templates based on physical laboratory report forms.
     *
     * Templates cover:
     *  1. Haematology (FBC, Genotype, Blood Group, ESR, Coagulation, Widal, Blood Film)
     *  2. Hormonal Assay - Simple (T4, T3, TSH, PSA, Prolactin, FSH, Testosterone, etc.)
     *  3. Hormonal Assay - Detailed (with phase-specific reference ranges)
     *  4. Hormonal Assay - Full Panel (with Progesterone)
     *  5. Chemistry - U/E/Cr, LFT, Glucose, Lipids, Tumor Markers, OGTT, CSF
     *  6. Glycated Haemoglobin (HBA1C)
     *  7. Parasitology (Urinalysis, Urine Microscopy, Stool Analysis)
     *  8. Drugs of Abuse (DOA)
     *  9. Medical Microbiology (Culture & Sensitivity)
     * 10. Seminal Fluid Analysis (SFA)
     * 11. Serology (ESR, KOH, Microfilaria, Prothrombin Time, APTT)
     *
     * Run: php artisan db:seed --class=V1ResultTemplateSeeder
     */
    public function run(): void
    {
        $this->command->info('Seeding V1 Result Templates...');
        $count = 0;

        foreach ($this->templates() as $t) {
            $template = V1ResultTemplate::updateOrCreate(
                ['name' => $t['name']],
                [
                    'description'   => $t['description'],
                    'content'       => $t['content'],
                    'category'      => $t['category'],
                    'sort_order'    => $t['sort_order'],
                    'template_type' => $t['template_type'] ?? 'lab',
                    'is_active'     => true,
                    'created_by'    => 1,
                ]
            );
            if ($template->wasRecentlyCreated) {
                $count++;
                $this->command->line("  + [{$t['category']}] {$t['name']}");
            } else {
                $this->command->line("  = [{$t['category']}] {$t['name']} (updated)");
            }
        }

        $this->command->info("V1 Result Templates seeded: {$count}");
    }

    /**
     * All templates array.
     */
    private function templates(): array
    {
        return array_merge(
            $this->haematologyTemplates(),
            $this->hormonalAssayTemplates(),
            $this->chemistryTemplates(),
            $this->hba1cTemplates(),
            $this->parasitologyTemplates(),
            $this->drugsOfAbuseTemplates(),
            $this->microbiologyTemplates(),
            $this->seminalFluidTemplates(),
            $this->serologyTemplates(),
            $this->chemistryExtensions(),
            $this->pathologyExtensions(),
            $this->imagingTemplates()
        );
    }

    // ─── HAEMATOLOGY ──────────────────────────────────────────────────────

    private function haematologyTemplates(): array
    {
        return [
            [
                'name' => 'Haematology (FBC, Genotype, Blood Group, Coagulation, Widal, Blood Film)',
                'description' => 'Full haematology report covering FBC, PCV, Hb, WBC, Plt, Genotype, Blood Group, ESR, PT, APTT, Widal, and Blood Film.',
                'category' => 'Haematology',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Parameter</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Hb</td>
            <td></td>
            <td>M: 13-18g/dl, F: 12-16g/dl</td>
        </tr>
        <tr>
            <td>PCV</td>
            <td></td>
            <td>M: 40-52%, F: 37-47%</td>
        </tr>
        <tr>
            <td>MCH</td>
            <td></td>
            <td>27-32pg</td>
        </tr>
        <tr>
            <td>MCV</td>
            <td></td>
            <td>80-100fL</td>
        </tr>
        <tr>
            <td>MCHC</td>
            <td></td>
            <td>32-36g/dl</td>
        </tr>
        <tr>
            <td>RBC</td>
            <td></td>
            <td>M: 4.5-5.5x10&sup1;&sup2;/L, F: 4.1-5.1x10&sup1;&sup2;/L</td>
        </tr>
        <tr>
            <td>Retic</td>
            <td></td>
            <td>2-20%</td>
        </tr>
        <tr>
            <td>Platelets</td>
            <td></td>
            <td>1.5-4.0x10&sup5;/mm&sup3;</td>
        </tr>
        <tr>
            <td>WBC</td>
            <td></td>
            <td>4500-11000/mm&sup3;</td>
        </tr>
        <tr>
            <td>ESR</td>
            <td></td>
            <td>M: &lt;10mm/hr, F: &lt;20mm/hr</td>
        </tr>
        <tr>
            <td>Genotype</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Blood Group</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>X-Matching</td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Differential</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Neutrophils</td>
            <td></td>
            <td>46-72%</td>
        </tr>
        <tr>
            <td>Lymphocytes</td>
            <td></td>
            <td>20-45%</td>
        </tr>
        <tr>
            <td>Monocytes</td>
            <td></td>
            <td>2-8%</td>
        </tr>
        <tr>
            <td>Eosinophils</td>
            <td></td>
            <td>1-4%</td>
        </tr>
        <tr>
            <td>Basophils</td>
            <td></td>
            <td>0-0.5%</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Blood Film</th>
            <th class="col-sm-4">Result</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Poikilocytosis</td>
            <td></td>
        </tr>
        <tr>
            <td>Hypochromasia</td>
            <td></td>
        </tr>
        <tr>
            <td>Polychromasia</td>
            <td></td>
        </tr>
        <tr>
            <td>Target cells</td>
            <td></td>
        </tr>
        <tr>
            <td>Macrocytosis</td>
            <td></td>
        </tr>
        <tr>
            <td>Anisocytosis</td>
            <td></td>
        </tr>
        <tr>
            <td>Sickle Cells</td>
            <td></td>
        </tr>
        <tr>
            <td>Malaria Parasite (RDT)</td>
            <td></td>
        </tr>
        <tr>
            <td>Nucleated RBC</td>
            <td></td>
        </tr>
        <tr>
            <td>Malaria Parasite (micro)</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Coagulation</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Prothrombin Time - Test</td>
            <td></td>
            <td>15-20sec</td>
        </tr>
        <tr>
            <td>Prothrombin Time - Control</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>INR</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>APTT - Test</td>
            <td></td>
            <td>25-35sec</td>
        </tr>
        <tr>
            <td>APTT - Control</td>
            <td></td>
            <td>30-35sec</td>
        </tr>
        <tr>
            <td>Average PTT</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Bleeding Time</td>
            <td></td>
            <td>2-8 Mins</td>
        </tr>
        <tr>
            <td>Clotting Time</td>
            <td></td>
            <td>5-10 Mins</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="4" style="text-align:center;"><strong>Widal Result</strong></th>
        </tr>
        <tr>
            <th class="col-sm-3">Organism</th>
            <th class="col-sm-3">O (Somatic)</th>
            <th class="col-sm-3">H (Flagella)</th>
            <th class="col-sm-3">Significant Titer</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Salmonella Typhi</td>
            <td></td>
            <td></td>
            <td rowspan="5" style="vertical-align:middle; text-align:center;"><strong>160 &amp; ABOVE</strong></td>
        </tr>
        <tr>
            <td>Sal Paratyphi A</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Sal Paratyphi B</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Sal Paratyphi C</td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>',
            ],
        ];
    }

    // ─── HORMONAL ASSAY ───────────────────────────────────────────────────

    private function hormonalAssayTemplates(): array
    {
        return [
            // Simple Hormonal Assay (from Image 2)
            [
                'name' => 'Hormonal Assay - Simple (T4, T3, TSH, PSA, Prolactin, FSH, Testosterone, Estrogen, LH, CEA, B HCG, AFP, CRP, D-Dimer)',
                'description' => 'Simple hormonal assay covering T4, T3, TSH, PSA, Prolactin, FSH, Testosterone, Estrogen, LH, CEA, B-HCG, AFP, CRP, and D-Dimer.',
                'category' => 'Hormonal Assay',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Parameter</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Reference Ranges</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>T4</td>
            <td></td>
            <td>66-181nmol/L</td>
        </tr>
        <tr>
            <td>T3</td>
            <td></td>
            <td>3.10-6.80pmol/L</td>
        </tr>
        <tr>
            <td>TSH</td>
            <td></td>
            <td>0.45-4.5mIu/L</td>
        </tr>
        <tr>
            <td>PSA (Qualitative)</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>PSA (Quantitative)</td>
            <td></td>
            <td>&lt; 4 ng/ml</td>
        </tr>
        <tr>
            <td>PROLACTIN</td>
            <td></td>
            <td>Male: 86-324&micro;Iu/Ml; Female: 102-496&micro;Iu/mL</td>
        </tr>
        <tr>
            <td>FSH</td>
            <td></td>
            <td>1.50-98.62mIu/Ml</td>
        </tr>
        <tr>
            <td>TESTOSTERONE</td>
            <td></td>
            <td>1.61-8.4ng/mL</td>
        </tr>
        <tr>
            <td>ESTROGEN</td>
            <td></td>
            <td>90-400ng/mL</td>
        </tr>
        <tr>
            <td>LH</td>
            <td></td>
            <td>2-10mIu/mL</td>
        </tr>
        <tr>
            <td>CEA</td>
            <td></td>
            <td>0.00-5.00ng/ml</td>
        </tr>
        <tr>
            <td>B HCG (Quantitative)</td>
            <td></td>
            <td>0.00-5.00mIu/ml</td>
        </tr>
        <tr>
            <td>B HCG (Qualitative)</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>AFP (Quantitative)</td>
            <td></td>
            <td>0.00-20.00ng/ml</td>
        </tr>
        <tr>
            <td>AFP (Qualitative)</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>C-REACTIVE PROTEIN</td>
            <td></td>
            <td>&lt;5mg/L</td>
        </tr>
        <tr>
            <td>D-DIMER</td>
            <td></td>
            <td>&le; 500ng/ml FEU</td>
        </tr>
    </tbody>
</table>

<p><strong>Comment:</strong></p>
<p></p>',
            ],

            // Detailed Hormonal Assay without Progesterone (from Image 3)
            [
                'name' => 'Hormonal Assay - Detailed (Prolactin, TSH, LH, FSH, Estradiol)',
                'description' => 'Detailed hormonal assay covering Prolactin, TSH, LH, FSH, and Estradiol with phase-specific reference ranges.',
                'category' => 'Hormonal Assay',
                'sort_order' => 2,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-3">Parameter</th>
            <th class="col-sm-3">Result</th>
            <th class="col-sm-6">Reference Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td rowspan="2">PROLACTIN</td>
            <td rowspan="2"></td>
            <td>Male: 86-324&micro;Iu/mL</td>
        </tr>
        <tr>
            <td>Female: 102-496&micro;Iu/mL</td>
        </tr>
        <tr>
            <td>TSH</td>
            <td></td>
            <td>0.45-4.5mIu/L</td>
        </tr>
        <tr>
            <td rowspan="5">LH</td>
            <td rowspan="5"></td>
            <td>Male: 1.81-8.16mIu/mL</td>
        </tr>
        <tr>
            <td>Follicular Phase: 2.95-13.65mIu/mL</td>
        </tr>
        <tr>
            <td>Ovulatory Phase: 13.65-95.75mIu/mL</td>
        </tr>
        <tr>
            <td>Luteal Phase: 1.25-11.0mIu/mL</td>
        </tr>
        <tr>
            <td>Menopause: 20.0-98.62mIu/mL</td>
        </tr>
        <tr>
            <td rowspan="5">FSH</td>
            <td rowspan="5"></td>
            <td>Male: 1.50-12.40mIu/mL</td>
        </tr>
        <tr>
            <td>Follicular Phase: 4.46-12.43mIu/mL</td>
        </tr>
        <tr>
            <td>Ovulatory Phase: 4.88-20.96mIu/mL</td>
        </tr>
        <tr>
            <td>Luteal Phase: 1.95-8.04mIu/mL</td>
        </tr>
        <tr>
            <td>Menopause: 20.0-98.62mIu/mL</td>
        </tr>
        <tr>
            <td rowspan="7">ESTRADIOL</td>
            <td rowspan="7"></td>
            <td>Male: &lt;9.0-85.0ng/mL</td>
        </tr>
        <tr>
            <td>Follicular Phase: 12-26.2.0ng/mL</td>
        </tr>
        <tr>
            <td>Ovulatory Phase: 40-396.0ng/mL</td>
        </tr>
        <tr>
            <td>Luteal Phase: 21-381.0ng/mL</td>
        </tr>
        <tr>
            <td>Menopause: &lt;9.0-190.0ng/mL</td>
        </tr>
        <tr>
            <td>Early Pregnancy: 14.5-298.8ng/mL</td>
        </tr>
        <tr>
            <td>Second Trimester: 15.02-&gt;300.0ng/mL</td>
        </tr>
    </tbody>
</table>',
            ],

            // Full Hormonal Assay with Progesterone (from Image 4/13/14)
            [
                'name' => 'Hormonal Assay - Full Panel (Prolactin, Testosterone, LH, FSH, Progesterone, Estradiol)',
                'description' => 'Complete hormonal assay covering Prolactin, Testosterone, LH, FSH, Progesterone, and Estradiol.',
                'category' => 'Hormonal Assay',
                'sort_order' => 3,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-3">Parameter</th>
            <th class="col-sm-3">Result</th>
            <th class="col-sm-6">Reference Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td rowspan="2">PROLACTIN</td>
            <td rowspan="2"></td>
            <td>Male: 86-324&micro;Iu/mL</td>
        </tr>
        <tr>
            <td>Female: 102-496&micro;Iu/mL</td>
        </tr>
        <tr>
            <td>TESTOSTERONE</td>
            <td></td>
            <td>1.61-8.4ng/mL</td>
        </tr>
        <tr>
            <td rowspan="5">LH</td>
            <td rowspan="5"></td>
            <td>Male: 1.81-8.16mIu/mL</td>
        </tr>
        <tr>
            <td>Follicular Phase: 2.95-13.65mIu/mL</td>
        </tr>
        <tr>
            <td>Ovulatory Phase: 13.65-95.75mIu/mL</td>
        </tr>
        <tr>
            <td>Luteal Phase: 1.25-11.0mIu/mL</td>
        </tr>
        <tr>
            <td>Menopause: 20.0-98.62mIu/mL</td>
        </tr>
        <tr>
            <td rowspan="5">FSH</td>
            <td rowspan="5"></td>
            <td>Male: 1.50-12.40mIu/mL</td>
        </tr>
        <tr>
            <td>Follicular Phase: 4.46-12.43mIu/mL</td>
        </tr>
        <tr>
            <td>Ovulatory Phase: 4.88-20.96mIu/mL</td>
        </tr>
        <tr>
            <td>Luteal Phase: 1.95-8.04mIu/mL</td>
        </tr>
        <tr>
            <td>Menopause: 20.0-98.62mIu/mL</td>
        </tr>
        <tr>
            <td rowspan="7">PROGESTERONE</td>
            <td rowspan="7"></td>
            <td>Male: 0-1.5ng/Ml</td>
        </tr>
        <tr>
            <td>Follicular Phase: 0-1.9ng/Ml</td>
        </tr>
        <tr>
            <td>Ovulatory Phase: 0-12.0ng/Ml</td>
        </tr>
        <tr>
            <td>Luteal Phase: 1.7-28.7ng/Ml</td>
        </tr>
        <tr>
            <td>Menopause: 0-1.4ng/Ml</td>
        </tr>
        <tr>
            <td>Pregnancy (&lt;12 weeks): 11.0-53.0ng/Ml</td>
        </tr>
        <tr>
            <td>Pregnancy (12-24 weeks): 21.5-60.0ng/Ml</td>
        </tr>
        <tr>
            <td rowspan="7">ESTRADIOL</td>
            <td rowspan="7"></td>
            <td>Male: &lt;9.0-85.0ng/mL</td>
        </tr>
        <tr>
            <td>Follicular Phase: 12-26.2.0ng/mL</td>
        </tr>
        <tr>
            <td>Ovulatory Phase: 40-396.0ng/mL</td>
        </tr>
        <tr>
            <td>Luteal Phase: 21-381.0ng/mL</td>
        </tr>
        <tr>
            <td>Menopause: &lt;9.0-190.0ng/mL</td>
        </tr>
        <tr>
            <td>Early Pregnancy: 14.5-298.8ng/mL</td>
        </tr>
        <tr>
            <td>Second Trimester: 15.02-&gt;300.0ng/mL</td>
        </tr>
    </tbody>
</table>',
            ],
        ];
    }

    // ─── CHEMISTRY ────────────────────────────────────────────────────────

    private function chemistryTemplates(): array
    {
        return [
            [
                'name' => 'Chemistry - U/E/Cr (Urea, Electrolytes, Creatinine)',
                'description' => 'U/E/Cr panel covering Sodium (Na+), Potassium (K+), Chloride (Cl-), Bicarbonate (HCO3-), Urea, Creatinine, and Uric Acid.',
                'category' => 'Chemistry',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">U/E/Cr (Blood)</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Na+</td>
            <td></td>
            <td>135-146mmol/L</td>
        </tr>
        <tr>
            <td>K+</td>
            <td></td>
            <td>3.5-5.2mmol/L</td>
        </tr>
        <tr>
            <td>Cl</td>
            <td></td>
            <td>75-105mmol/L</td>
        </tr>
        <tr>
            <td>HCO<sub>3</sub><sup>-</sup></td>
            <td></td>
            <td>21-28mmol/L</td>
        </tr>
        <tr>
            <td>Urea</td>
            <td></td>
            <td>2.5-7.0mmol/L</td>
        </tr>
        <tr>
            <td>Creatinine</td>
            <td></td>
            <td>M: 88-135&micro;mol/L, F: 62-115&micro;mol/L</td>
        </tr>
        <tr>
            <td>URIC ACID</td>
            <td></td>
            <td>M: 210-420&micro;mol/L, F: 150-350&micro;mol/L</td>
        </tr>
    </tbody>
</table>',
            ],

            [
                'name' => 'Chemistry - LFT (Liver Function Test)',
                'description' => 'Liver Function Test (LFT) covering Total Protein, Albumin, Bilirubin (Total/Conjugated), ALP, ALT (SGPT), AST (SGOT), and GGT.',
                'category' => 'Chemistry',
                'sort_order' => 2,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">LFT</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>T. Protein</td>
            <td></td>
            <td>60-80g/L</td>
        </tr>
        <tr>
            <td>Albumin</td>
            <td></td>
            <td>35-50g/L</td>
        </tr>
        <tr>
            <td>Bilirubin-T</td>
            <td></td>
            <td>&lt;1.0mg/dl</td>
        </tr>
        <tr>
            <td>Bilirubin-C</td>
            <td></td>
            <td>&lt;0.3mg/dl</td>
        </tr>
        <tr>
            <td>Alk phos</td>
            <td></td>
            <td>73-207u/L</td>
        </tr>
        <tr>
            <td>ALT (SGPT)</td>
            <td></td>
            <td>6-21u/L</td>
        </tr>
        <tr>
            <td>AST (SGOT)</td>
            <td></td>
            <td>7-21u/L</td>
        </tr>
        <tr>
            <td>GGT</td>
            <td></td>
            <td>4-20u/L</td>
        </tr>
    </tbody>
</table>',
            ],

            [
                'name' => 'Chemistry - Glucose (FBG, RBS, 2hrPP)',
                'description' => 'Blood glucose panel covering Fasting Blood Sugar (FBS), Random Blood Sugar (RBS), and 2-hour Post Prandial (2HPP).',
                'category' => 'Chemistry',
                'sort_order' => 3,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Glucose</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>FBG (Glucose)</td>
            <td></td>
            <td>3.9-6.1mmol/L</td>
        </tr>
        <tr>
            <td>RBS (Glucose)</td>
            <td></td>
            <td>5.6-6.9mmol/L</td>
        </tr>
        <tr>
            <td>2hrPP</td>
            <td></td>
            <td>3.5-11.0mmol/L</td>
        </tr>
    </tbody>
</table>',
            ],

            [
                'name' => 'Chemistry - Lipid Profile',
                'description' => 'Lipid Profile covering Total Cholesterol, HDL, LDL, and Triglycerides.',
                'category' => 'Chemistry',
                'sort_order' => 4,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Lipids</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Total Cholesterol</td>
            <td></td>
            <td>3.88-6.2mmol/L</td>
        </tr>
        <tr>
            <td>HDL Cholesterol</td>
            <td></td>
            <td>0.4-4.0mmol/L</td>
        </tr>
        <tr>
            <td>LDL Cholesterol</td>
            <td></td>
            <td>2.6-4.0mmol/L</td>
        </tr>
        <tr>
            <td>Triglyceride</td>
            <td></td>
            <td>0.9-1.7mmol/L</td>
        </tr>
    </tbody>
</table>',
            ],

            [
                'name' => 'Chemistry - Full Panel (U/E/Cr, LFT, Glucose, Lipids, Tumor Markers, OGTT, CSF, Misc)',
                'description' => 'Complete chemistry panel covering U/E/Cr (Na, K, Urea, Cr), LFT (ALT, AST, ALP), Glucose (FBS, RBS), Lipids, Tumor Markers, OGTT, and CSF.',
                'category' => 'Chemistry',
                'sort_order' => 5,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">U/E/Cr (Blood)</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Na+</td>
            <td></td>
            <td>135-146mmol/L</td>
        </tr>
        <tr>
            <td>K+</td>
            <td></td>
            <td>3.5-5.2mmol/L</td>
        </tr>
        <tr>
            <td>Cl</td>
            <td></td>
            <td>75-105mmol/L</td>
        </tr>
        <tr>
            <td>HCO<sub>3</sub><sup>-</sup></td>
            <td></td>
            <td>21-28mmol/L</td>
        </tr>
        <tr>
            <td>Urea</td>
            <td></td>
            <td>2.5-7.0mmol/L</td>
        </tr>
        <tr>
            <td>Creatinine</td>
            <td></td>
            <td>M: 88-135&micro;mol/L, F: 62-115&micro;mol/L</td>
        </tr>
        <tr>
            <td>URIC ACID</td>
            <td></td>
            <td>M: 210-420&micro;mol/L, F: 150-350&micro;mol/L</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">LFT</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>T. Protein</td>
            <td></td>
            <td>60-80g/L</td>
        </tr>
        <tr>
            <td>Albumin</td>
            <td></td>
            <td>35-50g/L</td>
        </tr>
        <tr>
            <td>Bilirubin-T</td>
            <td></td>
            <td>&lt;1.0mg/dl</td>
        </tr>
        <tr>
            <td>Bilirubin-C</td>
            <td></td>
            <td>&lt;0.3mg/dl</td>
        </tr>
        <tr>
            <td>Alk phos</td>
            <td></td>
            <td>73-207u/L</td>
        </tr>
        <tr>
            <td>ALT (SGPT)</td>
            <td></td>
            <td>6-21u/L</td>
        </tr>
        <tr>
            <td>AST (SGOT)</td>
            <td></td>
            <td>7-21u/L</td>
        </tr>
        <tr>
            <td>GGT</td>
            <td></td>
            <td>4-20u/L</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Miscellaneous</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>A. Phos. Total</td>
            <td></td>
            <td>3-10IU/l</td>
        </tr>
        <tr>
            <td>A. Phos. (prostatic)</td>
            <td></td>
            <td>Up to 4u/L</td>
        </tr>
        <tr>
            <td>Amylase</td>
            <td></td>
            <td>100-340u/L</td>
        </tr>
        <tr>
            <td>Ca<sup>2+</sup></td>
            <td></td>
            <td>2.25-2.75mmol/L</td>
        </tr>
        <tr>
            <td>PO<sub>4</sub><sup>2-</sup></td>
            <td></td>
            <td>0.8-1.4mmol/L</td>
        </tr>
        <tr>
            <td>Protein</td>
            <td></td>
            <td>Up to 25g/L</td>
        </tr>
        <tr>
            <td>Glucose</td>
            <td></td>
            <td>2.5-6.5mmol/L</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Glucose</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>FBG (Glucose)</td>
            <td></td>
            <td>3.9-6.1mmol/L</td>
        </tr>
        <tr>
            <td>RBS (Glucose)</td>
            <td></td>
            <td>5.6-6.9mmol/L</td>
        </tr>
        <tr>
            <td>2hrPP</td>
            <td></td>
            <td>3.5-11.0mmol/L</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Lipids</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Total Cholesterol</td>
            <td></td>
            <td>3.88-6.2mmol/L</td>
        </tr>
        <tr>
            <td>HDL Cholesterol</td>
            <td></td>
            <td>0.4-4.0mmol/L</td>
        </tr>
        <tr>
            <td>LDL Cholesterol</td>
            <td></td>
            <td>2.6-4.0mmol/L</td>
        </tr>
        <tr>
            <td>Triglyceride</td>
            <td></td>
            <td>0.9-1.7mmol/L</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Tumor Marker</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PSA</td>
            <td></td>
            <td>0-4ng/ml</td>
        </tr>
        <tr>
            <td>CEA</td>
            <td></td>
            <td>&lt;5.0nmol/ml</td>
        </tr>
        <tr>
            <td>AFP</td>
            <td></td>
            <td>&lt; 10 ng/ml</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">OGTT</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>OGTT</td>
            <td></td>
            <td>Normal Range</td>
        </tr>
        <tr>
            <td>OGTT X1</td>
            <td></td>
            <td>3.3-5.6mmol/l</td>
        </tr>
        <tr>
            <td>OGTT X2</td>
            <td></td>
            <td>1.7-3.3mmol/l</td>
        </tr>
        <tr>
            <td>OGTT X3</td>
            <td></td>
            <td>1.1-2.8mmol/l</td>
        </tr>
        <tr>
            <td>OGTT X4</td>
            <td></td>
            <td>0.8-0.8 mmol/l</td>
        </tr>
        <tr>
            <td>OGTT X5</td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">CSF Chemistry</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Glucose</td>
            <td></td>
            <td>2.5-6.5mmol/L</td>
        </tr>
        <tr>
            <td>Protein</td>
            <td></td>
            <td>100-400mg/L</td>
        </tr>
        <tr>
            <td>Chloride</td>
            <td></td>
            <td>100-130mmol/L</td>
        </tr>
    </tbody>
</table>',
            ],

            [
                'name' => 'Chemistry - Tumor Markers (PSA, CEA, AFP)',
                'description' => 'Basic tumor marker panel covering PSA, CEA, and AFP.',
                'category' => 'Chemistry',
                'sort_order' => 6,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Tumor Marker</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PSA</td>
            <td></td>
            <td>0-4ng/ml</td>
        </tr>
        <tr>
            <td>CEA</td>
            <td></td>
            <td>&lt;5.0nmol/ml</td>
        </tr>
        <tr>
            <td>AFP</td>
            <td></td>
            <td>&lt; 10 ng/ml</td>
        </tr>
    </tbody>
</table>',
            ],

            [
                'name' => 'Chemistry - OGTT (Oral Glucose Tolerance Test)',
                'description' => 'Oral Glucose Tolerance Test (OGTT) covering baseline and subsequent hour readings (X1, X2, X3, X4, X5).',
                'category' => 'Chemistry',
                'sort_order' => 7,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">OGTT</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>OGTT</td>
            <td></td>
            <td>Normal Range</td>
        </tr>
        <tr>
            <td>OGTT X1</td>
            <td></td>
            <td>3.3-5.6mmol/l</td>
        </tr>
        <tr>
            <td>OGTT X2</td>
            <td></td>
            <td>1.7-3.3mmol/l</td>
        </tr>
        <tr>
            <td>OGTT X3</td>
            <td></td>
            <td>1.1-2.8mmol/l</td>
        </tr>
        <tr>
            <td>OGTT X4</td>
            <td></td>
            <td>0.8-0.8 mmol/l</td>
        </tr>
        <tr>
            <td>OGTT X5</td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>',
            ],

            [
                'name' => 'Chemistry - CSF (Cerebrospinal Fluid)',
                'description' => 'Cerebrospinal Fluid (CSF) Chemistry covering Glucose, Protein, and Chloride.',
                'category' => 'Chemistry',
                'sort_order' => 8,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">CSF Chemistry</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Normal Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Glucose</td>
            <td></td>
            <td>2.5-6.5mmol/L</td>
        </tr>
        <tr>
            <td>Protein</td>
            <td></td>
            <td>100-400mg/L</td>
        </tr>
        <tr>
            <td>Chloride</td>
            <td></td>
            <td>100-130mmol/L</td>
        </tr>
    </tbody>
</table>',
            ],
        ];
    }

    // ─── HBA1C ────────────────────────────────────────────────────────────

    private function hba1cTemplates(): array
    {
        return [
            [
                'name' => 'Glycated Haemoglobin (HBA1C)',
                'description' => 'Glycated Haemoglobin (HbA1c) test with diabetic interpretation ranges.',
                'category' => 'Chemistry',
                'sort_order' => 9,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Test</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Ranges</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td rowspan="3">GLYCATED HAEMOGLOBIN (HBA1C) REPORT</td>
            <td rowspan="3"></td>
            <td>4 - 5.6% Normal</td>
        </tr>
        <tr>
            <td>5.7 - 6.4% High Risk For DM</td>
        </tr>
        <tr>
            <td>6.5 And Above - DM</td>
        </tr>
    </tbody>
</table>

<p><strong>Comment:</strong></p>
<p></p>',
            ],
        ];
    }

    // ─── PARASITOLOGY ─────────────────────────────────────────────────────

    private function parasitologyTemplates(): array
    {
        return [
            [
                'name' => 'Parasitology (Urinalysis, Urine Microscopy, Stool Analysis)',
                'description' => 'Parasitology panel covering Urinalysis (pH, Protein, Glucose), Urine Microscopy (Pus cells, RBC, Casts), and Stool Analysis (Ova, Cyst, Protozoa).',
                'category' => 'Parasitology',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-6">Urinalysis</th>
            <th class="col-sm-6">Result</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Appearance</td>
            <td></td>
        </tr>
        <tr>
            <td>Colour</td>
            <td></td>
        </tr>
        <tr>
            <td>pH</td>
            <td></td>
        </tr>
        <tr>
            <td>Blood</td>
            <td></td>
        </tr>
        <tr>
            <td>Urobilinogen</td>
            <td></td>
        </tr>
        <tr>
            <td>Protein</td>
            <td></td>
        </tr>
        <tr>
            <td>Nitrate</td>
            <td></td>
        </tr>
        <tr>
            <td>Ketone</td>
            <td></td>
        </tr>
        <tr>
            <td>Ascorbic Acid</td>
            <td></td>
        </tr>
        <tr>
            <td>Glucose</td>
            <td></td>
        </tr>
        <tr>
            <td>Bilirubin</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-6">Urine Microscopy</th>
            <th class="col-sm-6">Result</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Pus Cells</td>
            <td></td>
        </tr>
        <tr>
            <td>Epithlial Cells</td>
            <td></td>
        </tr>
        <tr>
            <td>Yeast Cells</td>
            <td></td>
        </tr>
        <tr>
            <td>Crystals</td>
            <td></td>
        </tr>
        <tr>
            <td>RBC</td>
            <td></td>
        </tr>
        <tr>
            <td>Cast</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="2" style="text-align:center;"><strong>Stool Analysis</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="2" style="text-align:center;"><strong>Macroscopy</strong></td>
        </tr>
        <tr>
            <td>Colour</td>
            <td></td>
        </tr>
        <tr>
            <td>Consistency</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2" style="text-align:center;"><strong>Microscopy</strong></td>
        </tr>
        <tr>
            <td>Pus cells</td>
            <td></td>
        </tr>
        <tr>
            <td>Ova</td>
            <td></td>
        </tr>
        <tr>
            <td>Cyst</td>
            <td></td>
        </tr>
        <tr>
            <td>Protozoa</td>
            <td></td>
        </tr>
    </tbody>
</table>',
            ],
        ];
    }

    // ─── DRUGS OF ABUSE (DOA) ─────────────────────────────────────────────

    private function drugsOfAbuseTemplates(): array
    {
        return [
            [
                'name' => 'Clinical Chemistry - Drugs of Abuse (DOA)',
                'description' => 'Drugs of Abuse (DOA) covering TCA, MET, AMP, COC, OPI, THC, BAR, MDMA, TML, and BZO.',
                'category' => 'Clinical Chemistry',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="3" style="text-align:center;"><strong>Drugs of Abuse (DOA)</strong></th>
        </tr>
        <tr>
            <th class="col-sm-2">Codes</th>
            <th class="col-sm-6">Name of Drug</th>
            <th class="col-sm-4">Result (Pos/Neg)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>TCA</td>
            <td>TRICYCLIC ANTIDEPRESSANTS</td>
            <td></td>
        </tr>
        <tr>
            <td>MET</td>
            <td>Methamphetamine</td>
            <td></td>
        </tr>
        <tr>
            <td>AMP</td>
            <td>Amphetamine</td>
            <td></td>
        </tr>
        <tr>
            <td>COC</td>
            <td>Cocaine</td>
            <td></td>
        </tr>
        <tr>
            <td>OPI</td>
            <td>OPIOIDS</td>
            <td></td>
        </tr>
        <tr>
            <td>THC</td>
            <td>Marijuana</td>
            <td></td>
        </tr>
        <tr>
            <td>BAR</td>
            <td>Barbiturates</td>
            <td></td>
        </tr>
        <tr>
            <td>MDMA</td>
            <td>3,4-Methylenedioxymethamphetamine (ecstasy)</td>
            <td></td>
        </tr>
        <tr>
            <td>TML</td>
            <td>TRAMADOL</td>
            <td></td>
        </tr>
        <tr>
            <td>BZO</td>
            <td>Benzodiazepines</td>
            <td></td>
        </tr>
    </tbody>
</table>',
            ],
        ];
    }

    // ─── MEDICAL MICROBIOLOGY ─────────────────────────────────────────────

    private function microbiologyTemplates(): array
    {
        return [
            [
                'name' => 'Medical Microbiology (Culture & Sensitivity)',
                'description' => 'Microbiology (M/C/S) covering Microscopy, Culture, and Antibiotic Sensitivity testing.',
                'category' => 'Microbiology',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="2" style="text-align:center;"><strong>Medical Microbiology</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Skin snip report</strong></td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Skinscrappings KOH report</strong></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-6">Microscopy</th>
            <th class="col-sm-6">Result</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>MACRO</td>
            <td></td>
        </tr>
        <tr>
            <td>MICRO: Epith cells</td>
            <td></td>
        </tr>
        <tr>
            <td>Pus cells (/hpf)</td>
            <td></td>
        </tr>
        <tr>
            <td>Yeast cells</td>
            <td></td>
        </tr>
        <tr>
            <td>RBC</td>
            <td></td>
        </tr>
        <tr>
            <td>T. vaginalis</td>
            <td></td>
        </tr>
        <tr>
            <td>Calcium oxalate</td>
            <td></td>
        </tr>
        <tr>
            <td>Casts</td>
            <td></td>
        </tr>
        <tr>
            <td>Cysts</td>
            <td></td>
        </tr>
        <tr>
            <td>Ova</td>
            <td></td>
        </tr>
        <tr>
            <td>Others</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <tbody>
        <tr>
            <td><strong>CULTURE</strong></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="5" style="text-align:center;"><strong>SENSITIVITY</strong></th>
        </tr>
        <tr>
            <th class="col-sm-4">Antibiotics</th>
            <th class="col-sm-2">1+</th>
            <th class="col-sm-2">2+</th>
            <th class="col-sm-2">3+</th>
            <th class="col-sm-2">Resistant</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>CIPROFLOXACIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>GENTAMYCIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>CEFTRIAXONE</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>AUGMENTIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>AMOXIL</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>SPARFLOXACIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>PEFLOXACIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>STREPTOMYCIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>OFLOXACIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>AZITHROMYCIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>SEPTRIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>LEVOFLOXACIN</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>CEFOTAXIM</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Others</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>',
            ],
        ];
    }

    // ─── SEMINAL FLUID ANALYSIS ───────────────────────────────────────────

    private function seminalFluidTemplates(): array
    {
        return [
            [
                'name' => 'Seminal Fluid Analysis (SFA)',
                'description' => 'Seminal Fluid Analysis (SFA) covering Volume, Motility, Sperm Count, Morphology, and Abnormalities.',
                'category' => 'Microbiology',
                'sort_order' => 2,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="4" style="text-align:center;"><strong>Seminal Fluid Analysis (SFA)</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Days of Abstinence</strong></td>
            <td></td>
            <td><strong>Method of Collection</strong></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="4"><strong>Interval Between Collection and Analysis:</strong></td>
        </tr>
        <tr>
            <td><strong>Volume</strong></td>
            <td></td>
            <td><strong>Liquefaction</strong></td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Consistency</strong></td>
            <td></td>
            <td><strong>Appearance</strong></td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Total Motility</strong></td>
            <td></td>
            <td><strong>Rapid Progressive Motility</strong></td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Sluggish Progressive Motility</strong></td>
            <td></td>
            <td><strong>Non Progressive Motility</strong></td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Immotile</strong></td>
            <td></td>
            <td><strong>Rapid/Sluggish Non-Linear Progression</strong></td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Sperm Agglutination</strong></td>
            <td></td>
            <td><strong>Sperm Count</strong></td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Sperm Concentration</strong></td>
            <td></td>
            <td><strong>% Normal</strong></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">WBC</th>
            <th class="col-sm-4">EPITH CELL</th>
            <th class="col-sm-4">CRYSTALS/YEASTS</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="2" style="text-align:center;"><strong>Abnormality</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Bent tailed</td>
            <td></td>
        </tr>
        <tr>
            <td>Big Headed</td>
            <td></td>
        </tr>
        <tr>
            <td>Swollen Neck</td>
            <td></td>
        </tr>
        <tr>
            <td>Tapering Head</td>
            <td></td>
        </tr>
        <tr>
            <td>Amorphous Head</td>
            <td></td>
        </tr>
        <tr>
            <td>Others Specify</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="2" style="text-align:center;"><strong>Comment</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Asthenospermia</td>
            <td></td>
        </tr>
        <tr>
            <td>Oligospermia</td>
            <td></td>
        </tr>
        <tr>
            <td>Hypospermia</td>
            <td></td>
        </tr>
        <tr>
            <td>Normozoospermia</td>
            <td></td>
        </tr>
        <tr>
            <td>Others Specify</td>
            <td></td>
        </tr>
    </tbody>
</table>',
            ],
        ];
    }

    // ─── SEROLOGY (ESR, KOH, MF, Prothrombin, APTT) ─────────────────────

    private function serologyTemplates(): array
    {
        return [
            [
                'name' => 'Serology (ESR, KOH Skin Screpping, Microfilaria, Prothrombin Time, APTT)',
                'description' => 'General serology/haematology covering ESR, KOH Skin Scraping, Microfilaria (MF), Prothrombin Time (PT), and APTT.',
                'category' => 'Haematology',
                'sort_order' => 2,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Parameter</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>ESR</td>
            <td></td>
            <td>0-10mm/hr</td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">KOH (SKIN SCREPPING)</th>
            <th class="col-sm-4">Fungal element</th>
            <th class="col-sm-4">Result</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td></td>
            <td>Seen</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>Not seen</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">MF (MICROFILARIA)/SKIN SNIP</th>
            <th class="col-sm-4">Microfilaria</th>
            <th class="col-sm-4">Result</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td></td>
            <td>Seen</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>Not seen</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Prothrombin Time</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Test</td>
            <td></td>
            <td>10-13sec</td>
        </tr>
        <tr>
            <td>Control</td>
            <td></td>
            <td>12-16sec</td>
        </tr>
        <tr>
            <td>INR</td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
        <tr>
            <th class="col-sm-4">Activated Partial Thromboplastine Test</th>
            <th class="col-sm-4">Result</th>
            <th class="col-sm-4">Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Test</td>
            <td></td>
            <td>25-35sec</td>
        </tr>
        <tr>
            <td>Control</td>
            <td></td>
            <td>30-35sec</td>
        </tr>
        <tr>
            <td>Average PTT</td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>',
            ],
        ];
    }

    // ─── IMAGING TEMPLATES ───────────────────────────────────────────────────

    private function imagingTemplates(): array
    {
        return [
            [
                'name' => 'Ultrasound Scan (General)',
                'description' => 'General Ultrasound Scan (USS) reporting covering Clinical Indication, Findings, and Conclusion/Impression.',
                'category' => 'Ultrasound',
                'template_type' => 'imaging',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <tbody>
        <tr>
            <th style="width: 25%; background-color: #f8f9fa;">Clinical Indication</th>
            <td><p>&nbsp;</p></td>
        </tr>
        <tr>
            <th style="background-color: #f8f9fa;">Findings</th>
            <td><p>&nbsp;</p></td>
        </tr>
        <tr>
            <th style="background-color: #f8f9fa;">Conclusion / Impression</th>
            <td><strong></strong></td>
        </tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'Obstetric Ultrasound Scan',
                'description' => 'Obstetric Ultrasound Scan (USS) covering LMP, EDD, Fetal Presentation, BPD, FL, AC, Placenta, Liquor, and Conclusion.',
                'category' => 'Ultrasound',
                'template_type' => 'imaging',
                'sort_order' => 2,
                'content' => '<table class="table table-bordered">
    <tbody>
        <tr>
            <th colspan="2" style="background-color: #f8f9fa; text-align: center;">OBSTETRIC ULTRASOUND REPORT</th>
        </tr>
        <tr>
            <th style="width: 30%;">Clinical Indication</th>
            <td></td>
        </tr>
        <tr>
            <th>LMP / EDD by LMP</th>
            <td></td>
        </tr>
        <tr>
            <th>Number of Fetuses</th>
            <td>Single/Multiple</td>
        </tr>
        <tr>
            <th>Presentation / Lie</th>
            <td>Cephalic / Longitudinal</td>
        </tr>
        <tr>
            <th>Fetal Heart Rate (FHR)</th>
            <td>Present, regular</td>
        </tr>
        <tr>
            <th>Biometry (BPD, FL, AC, HC)</th>
            <td></td>
        </tr>
        <tr>
            <th>Estimated Fetal Weight (EFW)</th>
            <td></td>
        </tr>
        <tr>
            <th>Gestational Age (EGA by USS)</th>
            <td></td>
        </tr>
        <tr>
            <th>EDD by USS</th>
            <td></td>
        </tr>
        <tr>
            <th>Placenta</th>
            <td></td>
        </tr>
        <tr>
            <th>Liquor Volume</th>
            <td>Adequate</td>
        </tr>
        <tr>
            <th style="background-color: #f8f9fa;">Impression / Conclusion</th>
            <td><strong></strong></td>
        </tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'X-Ray Report',
                'description' => 'Standard X-Ray reporting template covering Indication, Region, Findings, and Impression.',
                'category' => 'X-Ray',
                'template_type' => 'imaging',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <tbody>
        <tr>
            <th style="width: 25%; background-color: #f8f9fa;">Investigation</th>
            <td><strong>X-Ray</strong></td>
        </tr>
        <tr>
            <th style="background-color: #f8f9fa;">Clinical Details</th>
            <td><p>&nbsp;</p></td>
        </tr>
        <tr>
            <th style="background-color: #f8f9fa;">Findings</th>
            <td><p>&nbsp;</p></td>
        </tr>
        <tr>
            <th style="background-color: #f8f9fa;">Impression</th>
            <td><strong></strong></td>
        </tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'ECG Report',
                'description' => 'Electrocardiogram (ECG) report covering Rate, Rhythm, Axis, Intervals, ST-T wave changes, and Interpretation.',
                'category' => 'Cardiology',
                'template_type' => 'imaging',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <tbody>
        <tr>
            <th colspan="2" style="background-color: #f8f9fa; text-align: center;">ELECTROCARDIOGRAM (ECG) REPORT</th>
        </tr>
        <tr>
            <th style="width: 30%;">Clinical Indication</th>
            <td></td>
        </tr>
        <tr>
            <th>Heart Rate</th>
            <td> bpm</td>
        </tr>
        <tr>
            <th>Rhythm</th>
            <td>Sinus Rhythm</td>
        </tr>
        <tr>
            <th>Axis</th>
            <td>Normal</td>
        </tr>
        <tr>
            <th>PR Interval</th>
            <td></td>
        </tr>
        <tr>
            <th>QRS Duration</th>
            <td></td>
        </tr>
        <tr>
            <th>QTc Interval</th>
            <td></td>
        </tr>
        <tr>
            <th>ST-T Wave Changes</th>
            <td>None</td>
        </tr>
        <tr>
            <th style="background-color: #f8f9fa;">Interpretation</th>
            <td><strong>Normal ECG</strong></td>
        </tr>
    </tbody>
</table>',
            ]
        ];
    }

    // ─── ADDED EXTENSIONS ───────────────────────────────────────────────────

    private function chemistryExtensions(): array
    {
        return [
            [
                'name' => 'Chemistry - Cardiac Markers (Troponin I, Troponin T)',
                'description' => 'Cardiac biomarkers panel covering Troponin I and Troponin T.',
                'category' => 'Chemistry',
                'template_type' => 'lab',
                'sort_order' => 12,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-4">Test</th><th class="col-sm-4">Result</th><th class="col-sm-4">Reference Range</th></tr>
    </thead>
    <tbody>
        <tr><td>Troponin I</td><td></td><td>Negative</td></tr>
        <tr><td>Troponin T</td><td></td><td>Negative</td></tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'Chemistry - Tumor Markers Extended (PSA, CEA, AFP, CA-125)',
                'description' => 'Extended tumor marker panel covering PSA, CEA, AFP, and CA-125.',
                'category' => 'Chemistry',
                'template_type' => 'lab',
                'sort_order' => 13,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-4">Test</th><th class="col-sm-4">Result</th><th class="col-sm-4">Reference Range</th></tr>
    </thead>
    <tbody>
        <tr><td>PSA</td><td></td><td>0 - 4.0 ng/mL</td></tr>
        <tr><td>CEA</td><td></td><td>0 - 3.0 ng/mL</td></tr>
        <tr><td>AFP</td><td></td><td>0 - 8.5 ng/mL</td></tr>
        <tr><td>CA-125</td><td></td><td>0 - 35.0 U/mL</td></tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'Chemistry - 24-Hour Urine Chemistry',
                'description' => '24-Hour Urine Chemistry covering 24h Volume, 24h Protein, 24h Calcium, and Creatinine Clearance.',
                'category' => 'Chemistry',
                'template_type' => 'lab',
                'sort_order' => 14,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-4">Test</th><th class="col-sm-4">Result</th><th class="col-sm-4">Reference Range</th></tr>
    </thead>
    <tbody>
        <tr><td>24h Urine Volume</td><td></td><td></td></tr>
        <tr><td>24h Urine Protein</td><td></td><td>< 150 mg/24h</td></tr>
        <tr><td>24h Urine Calcium</td><td></td><td>2.5 - 7.5 mmol/24h</td></tr>
        <tr><td>Creatinine Clearance</td><td></td><td>80 - 120 mL/min</td></tr>
    </tbody>
</table>',
            ],
        ];
    }

    private function pathologyExtensions(): array
    {
        return [
            [
                'name' => 'Chemical Pathology - Qualitative Tests (PT, FOB)',
                'description' => 'Basic qualitative tests covering Urine/Serum Pregnancy Test (PT) and Fecal Occult Blood (FOB).',
                'category' => 'Chemical Pathology',
                'template_type' => 'lab',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-6">Test</th><th class="col-sm-6">Result</th></tr>
    </thead>
    <tbody>
        <tr><td>Pregnancy Test (Urine)</td><td></td></tr>
        <tr><td>Pregnancy Test (Serum)</td><td></td></tr>
        <tr><td>Fecal Occult Blood (FOB)</td><td></td></tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'Microbiology - Sputum AFB',
                'description' => 'Sputum Acid Fast Bacilli (AFB) covering Appearance and WHO Grading (Negative, Scanty, 1+, 2+, 3+).',
                'category' => 'Microbiology',
                'template_type' => 'lab',
                'sort_order' => 3,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-6">Parameter</th><th class="col-sm-6">Result</th></tr>
    </thead>
    <tbody>
        <tr><td>Appearance</td><td></td></tr>
        <tr><td>AFB Grading</td><td></td></tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'Serology - Infectious Diseases & Rapid Tests',
                'description' => 'Infectious diseases & rapid tests covering HIV (RVS), HBsAg, HCV, VDRL/TPHA, H. Pylori, RF, and ASO Titre.',
                'category' => 'Serology',
                'template_type' => 'lab',
                'sort_order' => 2,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-4">Test</th><th class="col-sm-4">Result</th><th class="col-sm-4">Normal Range</th></tr>
    </thead>
    <tbody>
        <tr><td>HIV Test (RVS)</td><td></td><td>Non-Reactive</td></tr>
        <tr><td>HBsAg</td><td></td><td>Non-Reactive</td></tr>
        <tr><td>HCV</td><td></td><td>Non-Reactive</td></tr>
        <tr><td>VDRL / TPHA</td><td></td><td>Non-Reactive</td></tr>
        <tr><td>H. Pylori</td><td></td><td>Negative</td></tr>
        <tr><td>Rheumatoid Factor (RF)</td><td></td><td>< 20 IU/mL</td></tr>
        <tr><td>ASO Titre</td><td></td><td>< 200 IU/mL</td></tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'Virology - Viral Load (HIV, HBV, HCV)',
                'description' => 'Quantitative Viral Load reporting (HIV, HBV, HCV) covering Copies/mL and Log Values.',
                'category' => 'Virology',
                'template_type' => 'lab',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-4">Test</th><th class="col-sm-4">Result</th><th class="col-sm-4">Interpretation</th></tr>
    </thead>
    <tbody>
        <tr><td>Viral Load (Copies/mL)</td><td></td><td>Target Not Detected</td></tr>
        <tr><td>Log Value</td><td></td><td></td></tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'Virology - Gene Xpert',
                'description' => 'Sputum Gene Xpert covering Mycobacterium Tuberculosis (MTB) detection and Rifampicin (RIF) Resistance.',
                'category' => 'Virology',
                'template_type' => 'lab',
                'sort_order' => 2,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-6">Test</th><th class="col-sm-6">Result</th></tr>
    </thead>
    <tbody>
        <tr><td>Mycobacterium Tuberculosis (MTB)</td><td></td></tr>
        <tr><td>Rifampicin (RIF) Resistance</td><td></td></tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'Histology & Cytology Report',
                'description' => 'Pathology report covering Clinical Details, Macroscopic (Gross), Microscopic Appearance, and Diagnosis/Conclusion.',
                'category' => 'Histology',
                'template_type' => 'lab',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <tbody>
        <tr><td class="col-sm-3"><strong>Clinical Details:</strong></td><td></td></tr>
        <tr><td class="col-sm-3"><strong>Macroscopic (Gross) Appearance:</strong></td><td></td></tr>
        <tr><td class="col-sm-3"><strong>Microscopic Appearance:</strong></td><td></td></tr>
        <tr><td class="col-sm-3"><strong>Diagnosis / Conclusion:</strong></td><td></td></tr>
    </tbody>
</table><p><strong>Pathologist Comment:</strong></p>',
            ],
            [
                'name' => 'Immunology - CD4 Count',
                'description' => 'Immunology reporting covering CD4 Absolute Count and CD4 Percentage.',
                'category' => 'Immunology',
                'template_type' => 'lab',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-4">Parameter</th><th class="col-sm-4">Result</th><th class="col-sm-4">Reference Range</th></tr>
    </thead>
    <tbody>
        <tr><td>CD4 Absolute Count</td><td></td><td>410-1590 Cells/&micro;L</td></tr>
        <tr><td>CD4 Percent Count</td><td></td><td>31-60 %</td></tr>
    </tbody>
</table>',
            ],
            [
                'name' => 'APIN Baseline / Follow-Up Test Panel',
                'description' => 'Comprehensive APIN Panel covering CD4 Absolute Count, Viral Load, PCV, WBC, Creatinine, and ALT.',
                'category' => 'APIN',
                'template_type' => 'lab',
                'sort_order' => 1,
                'content' => '<table class="table table-bordered">
    <thead>
        <tr><th class="col-sm-4">Parameter</th><th class="col-sm-4">Result</th><th class="col-sm-4">Reference Range</th></tr>
    </thead>
    <tbody>
        <tr><td colspan="3"><strong>Immunology / Virology</strong></td></tr>
        <tr><td>CD4 Absolute Count</td><td></td><td>410-1590 Cells/&micro;L</td></tr>
        <tr><td>Viral Load</td><td></td><td>Target Not Detected</td></tr>
        <tr><td colspan="3"><strong>Haematology</strong></td></tr>
        <tr><td>PCV</td><td></td><td>37-52%</td></tr>
        <tr><td>WBC</td><td></td><td>4.0-11.0 x10^9/L</td></tr>
        <tr><td colspan="3"><strong>Chemistry</strong></td></tr>
        <tr><td>Creatinine</td><td></td><td>60-110 &micro;mol/L</td></tr>
        <tr><td>ALT (SGPT)</td><td></td><td>Up to 40 U/L</td></tr>
    </tbody>
</table>',
            ]
        ];
    }
}


