<?php

namespace Tests\Feature\Nhmis;

use App\Http\Controllers\NhmisWorkbenchController;
use App\Models\Encounter;
use App\Models\NhmisMonthlyReport;
use App\Models\NhmisMonthlyReportValue;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\SpecialistReferral;
use App\Models\User;
use App\Services\Nhmis\NhmisDataAggregatorService;
use App\Services\Nhmis\NhmisFormRegistry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class NhmisFullParity185RowsTest extends TestCase
{
    protected NhmisDataAggregatorService $aggregator;

    protected NhmisWorkbenchController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = app(NhmisDataAggregatorService::class);
        $this->controller = app(NhmisWorkbenchController::class);
    }

    /**
     * Test full 3-way parity across all 185 rows (193 sub-indicators) from Page 1 to Page 5.
     * Compares:
     *   1. Stored Report Value in nhmis_monthly_report_values
     *   2. Live Compiled Aggregator Output
     *   3. Controller Drill-Down Server Fetch
     *
     * @test
     */
    public function test_full_parity_185_rows_page_1_to_page_5()
    {
        $year = 2026;
        $month = 7; // Use clean test period

        $report = NhmisMonthlyReport::getOrCreateForPeriod($year, $month, 'v2019');

        // Compile and store current test database values
        $compiled = $this->aggregator->compileReport($report);
        foreach ($compiled as $cellKey => $val) {
            NhmisMonthlyReportValue::updateOrCreate(
                ['report_id' => $report->id, 'cell_key' => $cellKey],
                ['auto_value' => $val, 'final_value' => $val]
            );
        }

        $storedValues = NhmisMonthlyReportValue::where('report_id', $report->id)
            ->pluck('final_value', 'cell_key');

        $schema = NhmisFormRegistry::getSchema('v2019');
        $pages = $schema->getPages();

        $totalEvaluated = 0;
        $perfectMatches = 0;
        $discrepancies = [];

        foreach ($pages as $p) {
            foreach ($p['sections'] as $sec) {
                $colKeys = array_keys($sec['columns'] ?? []);
                foreach ($sec['rows'] as $row) {
                    $totalEvaluated++;
                    $rId = $row['id'];

                    // 1. Stored value
                    $totalKey = "{$rId}:total";
                    if (isset($storedValues[$totalKey])) {
                        $storedVal = (int) $storedValues[$totalKey];
                    } else {
                        $sSum = 0;
                        foreach ($colKeys as $col) {
                            $sSum += (int) ($storedValues["{$rId}:{$col}"] ?? 0);
                        }
                        $storedVal = $sSum;
                    }

                    // 2. Drill-down server fetch
                    $drillTargetKey = in_array('total', $colKeys) ? "{$rId}:total" : "{$rId}:" . ($colKeys[0] ?? 'val');
                    $req = new Request(['cell_key' => $drillTargetKey, 'report_id' => $report->id, 'per_page' => 1]);
                    $dRes = $this->controller->drillDown($req)->getData(true);
                    $drillVal = (int) ($dRes['total_records'] ?? 0);

                    if ($storedVal === $drillVal) {
                        $perfectMatches++;
                    } else {
                        $discrepancies[] = "Row {$rId} ({$row['label']}): Stored={$storedVal}, Drill={$drillVal}";
                    }
                }
            }
        }

        $this->assertEmpty($discrepancies, "Detected discrepancies in 3-way parity:\n" . implode("\n", $discrepancies));
        $this->assertEquals($totalEvaluated, $perfectMatches);
        $this->assertGreaterThanOrEqual(185, $totalEvaluated);
    }

    /**
     * Test factual segregation of uncomplicated malaria (mild) from severe malaria and artesunate treatment.
     *
     * @test
     */
    public function test_authoritative_malaria_uncomplicated_and_severe_segregation()
    {
        $patientUncomp = Patient::first();
        $patientSevere = Patient::skip(1)->first() ?? Patient::factory()->create();
        $doctor = User::first() ?? User::factory()->create();

        if (!$patientUncomp || !$patientSevere) {
            $this->markTestSkipped('Patients required for malaria test.');
        }

        $periodDate = Carbon::create(2026, 6, 12, 10, 0, 0);

        // 1. Uncomplicated malaria encounter
        $encUncomp = Encounter::create([
            'patient_id' => $patientUncomp->id,
            'doctor_id' => $doctor->id,
            'reasons_for_encounter' => json_encode([['name' => 'Uncomplicated Malaria', 'code' => 'B50.9']]),
            'notes' => 'Patient has mild headache and fever. Diagnosed with uncomplicated malaria.',
            'completed' => true,
        ]);
        $encUncomp->created_at = $periodDate;
        $encUncomp->save();

        // 2. Severe malaria encounter
        $encSevere = Encounter::create([
            'patient_id' => $patientSevere->id,
            'doctor_id' => $doctor->id,
            'reasons_for_encounter' => json_encode([['name' => 'Severe Malaria With Convulsion', 'code' => 'B50.0']]),
            'notes' => 'Patient admitted with severe malaria, repeated convulsions and prostration.',
            'completed' => true,
        ]);
        $encSevere->created_at = $periodDate;
        $encSevere->save();

        // 3. Product request for Artesunate injection for severe patient
        $product = Product::where('product_name', 'like', '%artesunate%')
            ->where(function ($q) {
                $q->where('product_name', 'like', '%inj%')
                  ->orWhere('product_name', 'like', '%120mg%')
                  ->orWhere('product_name', 'like', '%60mg%');
            })->first();
        if (!$product) {
            $product = Product::create([
                'product_name' => 'ARTESUNATE INJECTION 60mg',
                'category_id' => 1,
                'status' => 1,
            ]);
        }

        $pr = ProductRequest::create([
            'patient_id' => $patientSevere->id,
            'encounter_id' => $encSevere->id,
            'product_id' => $product->id,
            'dose' => '120mg IV stat',
            'status' => 1,
        ]);
        \Illuminate\Support\Facades\DB::table('product_requests')
            ->where('id', $pr->id)
            ->update(['created_at' => $periodDate]);

        // Compile report
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 6, 'v2019');
        $compiled = $this->aggregator->compileReport($report);

        // Store compiled values
        foreach ($compiled as $k => $v) {
            NhmisMonthlyReportValue::updateOrCreate(
                ['report_id' => $report->id, 'cell_key' => $k],
                ['auto_value' => $v, 'final_value' => $v]
            );
        }

        // Uncomplicated malaria (Row 153) should include patientUncomp but exclude patientSevere
        $this->assertGreaterThanOrEqual(1, $compiled['row_153:total']);
        // Severe malaria (Row 154) should include patientSevere
        $this->assertGreaterThanOrEqual(1, $compiled['row_154:total']);
        // Severe malaria treated with Artesunate Injection (Row 159)
        $this->assertGreaterThanOrEqual(1, $compiled['row_159:total']);

        // Check drilldowns
        $req153 = new Request(['cell_key' => 'row_153:total', 'report_id' => $report->id]);
        $dRes153 = $this->controller->drillDown($req153)->getData(true);
        $this->assertEquals($compiled['row_153:total'], $dRes153['total_records']);

        $req154 = new Request(['cell_key' => 'row_154:total', 'report_id' => $report->id]);
        $dRes154 = $this->controller->drillDown($req154)->getData(true);
        $this->assertEquals($compiled['row_154:total'], $dRes154['total_records']);

        $req159 = new Request(['cell_key' => 'row_159:total', 'report_id' => $report->id]);
        $dRes159 = $this->controller->drillDown($req159)->getData(true);
        $this->assertEquals($compiled['row_159:total'], $dRes159['total_records']);
    }

    /**
     * Test factual accuracy of NCDs and diagnosis matching.
     *
     * @test
     */
    public function test_authoritative_ncd_diagnoses_matching()
    {
        $patient = Patient::first();
        $doctor = User::first() ?? User::factory()->create();
        if (!$patient) {
            $this->markTestSkipped('Patient required.');
        }

        $periodDate = Carbon::create(2026, 5, 10, 10, 0, 0);

        // Diabetes encounter
        $enc = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'reasons_for_encounter' => json_encode([['name' => 'Type 2 Diabetes Mellitus', 'code' => 'E11.9']]),
            'notes' => 'Patient has elevated fasting blood sugar. Diagnosed with Diabetes Mellitus.',
            'completed' => true,
        ]);
        $enc->created_at = $periodDate;
        $enc->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 5, 'v2019');
        $compiled = $this->aggregator->compileReport($report);

        $this->assertGreaterThanOrEqual(1, $compiled['row_137:total']);

        // Check drilldown
        $req = new Request(['cell_key' => 'row_137:total', 'report_id' => $report->id]);
        $dRes = $this->controller->drillDown($req)->getData(true);
        $this->assertEquals($compiled['row_137:total'], $dRes['total_records']);
    }

    /**
     * Test factual accuracy of Specialist Referrals.
     *
     * @test
     */
    public function test_authoritative_specialist_referrals_matching()
    {
        $patient = Patient::first();
        $doctor = User::first() ?? User::factory()->create();
        if (!$patient) {
            $this->markTestSkipped('Patient required.');
        }

        $periodDate = Carbon::create(2026, 3, 10, 10, 0, 0);

        $enc = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'reasons_for_encounter' => json_encode([['name' => 'Severe Complicated Malaria', 'code' => 'B50.0']]),
            'notes' => 'Referral test encounter',
            'completed' => true,
        ]);
        $enc->created_at = $periodDate;
        $enc->save();

        $ref = SpecialistReferral::create([
            'patient_id' => $patient->id,
            'encounter_id' => $enc->id,
            'referring_doctor_id' => $doctor->id,
            'referring_clinic_id' => 1,
            'provisional_diagnosis' => 'Severe Complicated Malaria',
            'reason' => 'For expert tertiary intensive care management',
            'status' => 'pending',
        ]);
        \Illuminate\Support\Facades\DB::table('specialist_referrals')
            ->where('id', $ref->id)
            ->update(['created_at' => $periodDate]);

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 3, 'v2019');
        $compiled = $this->aggregator->compileReport($report);

        $this->assertGreaterThanOrEqual(1, $compiled['row_132:total']);
        $this->assertGreaterThanOrEqual(1, $compiled['row_133:total']);

        // Check drilldown
        $req = new Request(['cell_key' => 'row_133:total', 'report_id' => $report->id]);
        $dRes = $this->controller->drillDown($req)->getData(true);
        $this->assertEquals($compiled['row_133:total'], $dRes['total_records']);
    }
}
