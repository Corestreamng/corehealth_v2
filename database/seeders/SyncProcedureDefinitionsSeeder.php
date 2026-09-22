<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use App\Models\ProcedureCategory;
use App\Models\ProcedureDefinition;
use App\Models\Service;
use Illuminate\Database\Seeder;

class SyncProcedureDefinitionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Surgical Checklist Template if not exists
        $surgicalTemplate = ChecklistTemplate::firstOrCreate(
            ['type' => 'surgical', 'is_default' => true],
            [
                'name' => 'WHO Surgical Safety & Theatre Checklist',
                'description' => 'Standard pre-operative and intra-operative surgical safety verification protocol based on WHO guidelines.',
                'is_active' => true,
            ]
        );

        if ($surgicalTemplate->items()->count() === 0) {
            $surgicalItems = [
                ['item_text' => 'Patient Identity, Surgical Site Marked & Procedure Confirmed', 'guidance' => 'Verify two patient identifiers and active surgical site marking before theatre entry.', 'is_required' => true, 'sort_order' => 1],
                ['item_text' => 'Fasting / NPO Status Verified', 'guidance' => 'Confirm appropriate fasting interval (minimum 6 hours solids, 2 hours clear fluids).', 'is_required' => true, 'sort_order' => 2],
                ['item_text' => 'Informed Surgical Consent Form Signed & Verified', 'guidance' => 'Ensure valid signed surgical and anesthesia consent is present in patient chart.', 'is_required' => true, 'sort_order' => 3],
                ['item_text' => 'Pre-Operative Anesthesia Assessment Completed', 'guidance' => 'Confirm ASA status, airway assessment, and pre-medication given if indicated.', 'is_required' => true, 'sort_order' => 4],
                ['item_text' => 'Blood Products & Crossmatch (G&X) Available', 'guidance' => 'Verify blood units available or on standby if blood loss is anticipated.', 'is_required' => false, 'sort_order' => 5],
                ['item_text' => 'Prophylactic Antibiotics Administered (Within 60 mins)', 'guidance' => 'Confirm appropriate surgical antibiotic prophylaxis administered prior to incision.', 'is_required' => false, 'sort_order' => 6],
                ['item_text' => 'Known Drug Allergies & Airway / Aspiration Risk Communicated', 'guidance' => 'Timeout briefing with surgical and anesthesia team.', 'is_required' => true, 'sort_order' => 7],
                ['item_text' => 'Sterility Indicators & Surgical Packs Checked', 'guidance' => 'Scrub nurse verifies instrument packs, chemical indicators, and implant availability.', 'is_required' => true, 'sort_order' => 8],
            ];

            foreach ($surgicalItems as $item) {
                $surgicalTemplate->items()->create($item);
            }
        }

        // 2. Seed Bedside Procedure Checklist Template if not exists
        $clinicalTemplate = ChecklistTemplate::firstOrCreate(
            ['type' => 'procedure', 'is_default' => true],
            [
                'name' => 'Standard Bedside & Minor Procedure Checklist',
                'description' => 'Clinical safety and preparation checklist for minor procedures, bedside interventions, and clinic treatment rooms.',
                'is_active' => true,
            ]
        );

        if ($clinicalTemplate->items()->count() === 0) {
            $clinicalItems = [
                ['item_text' => 'Patient Identity & Procedure Indication Verified', 'guidance' => 'Confirm patient name, hospital number, and planned bedside procedure.', 'is_required' => true, 'sort_order' => 1],
                ['item_text' => 'Informed Consent & Procedure Explanation Provided', 'guidance' => 'Explain procedure steps, expectations, and obtain patient agreement.', 'is_required' => true, 'sort_order' => 2],
                ['item_text' => 'Sterile Field, Procedure Pack & Consumables Prepared', 'guidance' => 'Aseptic non-touch technique (ANTT) setup with all necessary instruments.', 'is_required' => true, 'sort_order' => 3],
                ['item_text' => 'Allergy Status & Local Anesthesia / Antiseptic Checked', 'guidance' => 'Confirm no allergy to lignocaine/chlorhexidine/iodine.', 'is_required' => true, 'sort_order' => 4],
                ['item_text' => 'Pre-Procedure Vital Signs Recorded', 'guidance' => 'Baseline BP, pulse, and oxygen saturation if indicated.', 'is_required' => false, 'sort_order' => 5],
                ['item_text' => 'Post-Procedure Recovery & Wound Care Instructions Given', 'guidance' => 'Patient advised on warning signs, hygiene, and follow-up plan.', 'is_required' => true, 'sort_order' => 6],
            ];

            foreach ($clinicalItems as $item) {
                $clinicalTemplate->items()->create($item);
            }
        }

        // 3. Sync Procedure Definitions for services in category 8
        $procCatId = (int) (appsettings('procedure_category_id', 8) ?: 8);
        $services = Service::where('category_id', $procCatId)->get();

        $surgicalKeywords = [
            'section', 'ectomy', 'repair', 'surgery', 'amputation', 'biopsy',
            'incision', 'drainage', 'reduction', 'fixation', 'graft', 'circumcision',
            'curettage', 'delivery', 'episiotomy',
        ];

        // Default categories
        $genSurgeryCat = ProcedureCategory::where('code', 'GS')->first();
        $minorCat = ProcedureCategory::where('code', 'MINOR')->first();
        $ogCat = ProcedureCategory::where('code', 'OG')->first();
        $dentCat = ProcedureCategory::where('code', 'DENT')->first();

        foreach ($services as $service) {
            $nameLower = strtolower($service->service_name);
            $isSurg = false;
            foreach ($surgicalKeywords as $kw) {
                if (strpos($nameLower, $kw) !== false) {
                    $isSurg = true;

                    break;
                }
            }

            // Determine appropriate procedure category
            $categoryId = $isSurg ? ($genSurgeryCat?->id ?? 1) : ($minorCat?->id ?? 13);
            if (strpos($nameLower, 'dental') !== false) {
                $categoryId = $dentCat?->id ?? $categoryId;
            } elseif (strpos($nameLower, 'o&g') !== false || strpos($nameLower, 'delivery') !== false || strpos($nameLower, 'placenta') !== false) {
                $categoryId = $ogCat?->id ?? $categoryId;
            }

            ProcedureDefinition::updateOrCreate(
                ['service_id' => $service->id],
                [
                    'procedure_category_id' => $categoryId,
                    'name' => trim($service->service_name),
                    'code' => substr($service->service_code ?: ('PROC-' . $service->id), 0, 50),
                    'description' => $service->service_name,
                    'is_surgical' => $isSurg,
                    'estimated_duration_minutes' => $isSurg ? 60 : 20,
                    'status' => 1,
                ]
            );
        }
    }
}
