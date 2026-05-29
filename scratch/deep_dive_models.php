<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$models = [
    'Encounter' => \App\Models\Encounter::class,
    'AdmissionRequest' => \App\Models\AdmissionRequest::class,
    'Clinic' => \App\Models\Clinic::class,
    'DoctorQueue' => \App\Models\DoctorQueue::class,
    'Hmo' => \App\Models\Hmo::class,
    'ImagingServiceRequest' => \App\Models\ImagingServiceRequest::class,
    'LabServiceRequest' => \App\Models\LabServiceRequest::class,
    'NursingNote' => \App\Models\NursingNote::class,
    'NursingNoteType' => \App\Models\NursingNoteType::class,
    'Patient' => \App\Models\Patient::class,
    'ProductOrServiceRequest' => \App\Models\ProductOrServiceRequest::class,
    'ProductRequest' => \App\Models\ProductRequest::class,
    'ReasonForEncounter' => \App\Models\ReasonForEncounter::class,
    'Service' => \App\Models\Service::class,
    'Staff' => \App\Models\Staff::class,
    'User' => \App\Models\User::class,
    'Procedure' => \App\Models\Procedure::class,
    'Bed' => \App\Models\Bed::class,
    'Product' => \App\Models\Product::class,
    'ServiceCategory' => \App\Models\ServiceCategory::class,
    'ProductCategory' => \App\Models\ProductCategory::class,
    'VitalSign' => \App\Models\VitalSign::class,
    'MedicationSchedule' => \App\Models\MedicationSchedule::class,
    'MedicationAdministration' => \App\Models\MedicationAdministration::class,
    'IntakeOutputPeriod' => \App\Models\IntakeOutputPeriod::class,
    'IntakeOutputRecord' => \App\Models\IntakeOutputRecord::class,
    'InjectionAdministration' => \App\Models\InjectionAdministration::class,
    'ImmunizationRecord' => \App\Models\ImmunizationRecord::class,
    'VaccineScheduleTemplate' => \App\Models\VaccineScheduleTemplate::class,
    'VaccineScheduleItem' => \App\Models\VaccineScheduleItem::class,
    'VaccineProductMapping' => \App\Models\VaccineProductMapping::class,
    'PatientImmunizationSchedule' => \App\Models\PatientImmunizationSchedule::class,
    'HmoTariff' => \App\Models\HmoTariff::class,
    'Store' => \App\Models\Store::class,
    'StoreStock' => \App\Models\StoreStock::class,
    'StockBatch' => \App\Models\StockBatch::class,
    'StoreContextRule' => \App\Models\StoreContextRule::class,
    'MaternityEnrollment' => \App\Models\MaternityEnrollment::class,
    'MaternityMedicalHistory' => \App\Models\MaternityMedicalHistory::class,
    'MaternityPreviousPregnancy' => \App\Models\MaternityPreviousPregnancy::class,
    'AncVisit' => \App\Models\AncVisit::class,
    'AncInvestigation' => \App\Models\AncInvestigation::class,
    'DeliveryRecord' => \App\Models\DeliveryRecord::class,
    'DeliveryPartograph' => \App\Models\DeliveryPartograph::class,
    'MaternityPartograph' => \App\Models\MaternityPartograph::class,
    'MaternityBaby' => \App\Models\MaternityBaby::class,
    'ChildGrowthRecord' => \App\Models\ChildGrowthRecord::class,
    'PostnatalVisit' => \App\Models\PostnatalVisit::class,
    'WhoGrowthStandard' => \App\Models\WhoGrowthStandard::class,
    'DeathRecord' => \App\Models\DeathRecord::class,
    'TreatmentPlan' => \App\Models\TreatmentPlan::class,
    'MorgueAdmission' => \App\Models\MorgueAdmission::class,
    'ProcedureItem' => \App\Models\ProcedureItem::class,
    'ProcedureTeamMember' => \App\Models\ProcedureTeamMember::class,
    'ProcedureNote' => \App\Models\ProcedureNote::class,
    'ProcedureAttachment' => \App\Models\ProcedureAttachment::class,
    'PurchaseOrder' => \App\Models\PurchaseOrder::class,
    'StoreRequisition' => \App\Models\StoreRequisition::class,
];

$output = "# Clinical & Operations Models Deep Dive Analysis\n\n";
$output .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
$output .= "Total Models Analyzed: " . count($models) . "\n\n";

$output .= "## Overview of Models & Row Counts\n\n";
$output .= "| Model Name | Table Name | Row Count | Status |\n";
$output .= "| :--- | :--- | :--- | :--- |\n";

$details = "\n## Detailed Model Specifications\n\n";

foreach ($models as $name => $class) {
    try {
        if (!class_exists($class)) {
            $output .= "| {$name} | [CLASS NOT FOUND] | - | :x: Not Loaded |\n";
            continue;
        }

        $instance = new $class;
        $table = $instance->getTable();

        if (!Schema::hasTable($table)) {
            $output .= "| {$name} | `{$table}` | [TABLE NOT FOUND] | :warning: Not Migrated |\n";
            continue;
        }

        $count = DB::table($table)->count();
        $output .= "| {$name} | `{$table}` | **{$count}** | :white_check_mark: Active |\n";

        $details .= "### " . $name . " (`" . $table . "`)\n";
        $details .= "- **Class**: `{$class}`\n";
        $details .= "- **Row Count**: {$count} rows\n";
        $details .= "- **Columns**:\n";
        
        $columns = Schema::getColumnListing($table);
        $details .= "  `" . implode("`, `", $columns) . "`\n\n";

        $sample = DB::table($table)->first();
        if ($sample) {
            $details .= "#### Sample Record Structure:\n";
            $details .= "```json\n" . json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n\n";
        } else {
            $details .= "#### Sample Record: *[No rows present in table]*\n\n";
        }
        $details .= "---\n\n";

    } catch (\Exception $e) {
        $output .= "| {$name} | Error | - | :x: Error: " . $e->getMessage() . " |\n";
    }
}

file_put_contents(__DIR__ . '/clinical_models_deep_dive.md', $output . $details);
echo "Analysis Completed! Saved to scratch/clinical_models_deep_dive.md\n";
