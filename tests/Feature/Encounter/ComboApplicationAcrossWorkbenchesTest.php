<?php

namespace Tests\Feature\Encounter;

use App\Models\Clinic;
use App\Models\Encounter;
use App\Models\LabServiceRequest;
use App\Models\MaternityEnrollment;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\Service;
use App\Models\ServiceBundleItem;
use App\Models\Staff;
use App\Models\TreatmentPlan;
use App\Models\User;
use Tests\TestCase;

class ComboApplicationAcrossWorkbenchesTest extends TestCase
{
    protected $doctorUser;

    protected $patient;

    protected $treatmentPlan;

    protected $comboService;

    protected $labItem;

    protected $drugItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctorUser = User::factory()->create(['status' => 1]);
        $clinic = Clinic::first() ?? Clinic::factory()->create();
        Staff::create([
            'user_id' => $this->doctorUser->id,
            'clinic_id' => $clinic->id,
            'staff_id' => 'STF-' . $this->doctorUser->id,
            'specialization' => 'General Practice',
        ]);

        $this->patient = Patient::factory()->create();

        // Create a real TreatmentPlan for foreign key references
        $this->treatmentPlan = TreatmentPlan::create([
            'name' => 'General Clinical Care Plan',
            'patient_id' => $this->patient->id,
            'created_by' => $this->doctorUser->id,
            'clinic_id' => $clinic->id,
            'status' => 'active',
            'priority' => 'medium',
        ]);

        $labCatId = (int) (appsettings('investigation_category_id', 2) ?: 2);
        $prodCatId = ProductCategory::first()->id ?? 1;

        $prefix = 'TEST_' . uniqid();

        // Create Lab Item
        $this->labItem = Service::create([
            'service_name' => "{$prefix} Blood Test",
            'service_code' => "{$prefix}_BT",
            'category_id' => $labCatId,
            'user_id' => $this->doctorUser->id,
            'status' => 1,
        ]);

        // Create Drug Item
        $this->drugItem = Product::create([
            'product_name' => "{$prefix} Amoxicillin",
            'product_code' => "{$prefix}_AMX",
            'product_type' => 'drug',
            'category_id' => $prodCatId,
            'user_id' => $this->doctorUser->id,
            'status' => 1,
        ]);

        // Create Combo Service
        $this->comboService = Service::create([
            'service_name' => "{$prefix} Malaria Package",
            'service_code' => "{$prefix}_MAL",
            'category_id' => $labCatId,
            'is_combo' => 1,
            'user_id' => $this->doctorUser->id,
            'status' => 1,
        ]);

        // Attach bundle items
        ServiceBundleItem::create([
            'parent_service_id' => $this->comboService->id,
            'item_type' => 'service',
            'item_id' => $this->labItem->id,
            'qty' => 1,
            'note' => 'Fasting required',
            'sort_order' => 1,
        ]);

        ServiceBundleItem::create([
            'parent_service_id' => $this->comboService->id,
            'item_type' => 'product',
            'item_id' => $this->drugItem->id,
            'qty' => 2,
            'dose' => '500mg TDS x 5 days',
            'sort_order' => 2,
        ]);
    }

    /**
     * Test the exact URL and payload from the user's issue:
     * POST /encounters/applyCombo
     * service_id, treatment_plan_id, treatment_plan_name
     */
    public function test_post_encounters_applyCombo_no_longer_returns_405_method_not_allowed()
    {
        $response = $this->actingAs($this->doctorUser)
            ->postJson('/encounters/applyCombo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
                'treatment_plan_id' => $this->treatmentPlan->id,
                'treatment_plan_name' => $this->treatmentPlan->name,
            ]);

        $this->assertNotEquals(405, $response->status(), 'Endpoint /encounters/applyCombo must NOT return 405 Method Not Allowed.');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify treatment plan attached to created lab request
        $labRequest = LabServiceRequest::where('service_id', $this->labItem->id)
            ->where('patient_id', $this->patient->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($labRequest, 'Lab request should be created for bundle item.');
        $this->assertEquals($this->treatmentPlan->id, $labRequest->treatment_plan_id);
    }

    /**
     * Test POST /encounters/apply-combo (hyphenated route without encounter in URL)
     */
    public function test_post_encounters_apply_combo_direct()
    {
        $response = $this->actingAs($this->doctorUser)
            ->postJson('/encounters/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test POST /encounters/{encounter}/apply-combo (route with encounter parameter)
     */
    public function test_post_encounters_with_encounter_id_apply_combo()
    {
        $encounter = Encounter::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctorUser->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->doctorUser)
            ->postJson("/encounters/{$encounter->id}/apply-combo", [
                'service_id' => $this->comboService->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test POST /lab-workbench/apply-combo
     */
    public function test_post_lab_workbench_apply_combo()
    {
        $response = $this->actingAs($this->doctorUser)
            ->postJson('/lab-workbench/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
                'treatment_plan_id' => $this->treatmentPlan->id,
                'treatment_plan_name' => $this->treatmentPlan->name,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test POST /imaging-workbench/clinical-requests/apply-combo and alias /imaging-workbench/apply-combo
     */
    public function test_post_imaging_workbench_apply_combo()
    {
        $response = $this->actingAs($this->doctorUser)
            ->postJson('/imaging-workbench/clinical-requests/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Test alias
        $aliasResponse = $this->actingAs($this->doctorUser)
            ->postJson('/imaging-workbench/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
            ]);

        $aliasResponse->assertStatus(200);
        $aliasResponse->assertJson(['success' => true]);
    }

    /**
     * Test POST /nursing-workbench/clinical-requests/apply-combo
     */
    public function test_post_nursing_workbench_apply_combo()
    {
        $response = $this->actingAs($this->doctorUser)
            ->postJson('/nursing-workbench/clinical-requests/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
                'treatment_plan_id' => $this->treatmentPlan->id,
                'treatment_plan_name' => $this->treatmentPlan->name,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test POST /pharmacy-workbench/apply-combo
     */
    public function test_post_pharmacy_workbench_apply_combo()
    {
        $response = $this->actingAs($this->doctorUser)
            ->postJson('/pharmacy-workbench/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
                'treatment_plan_id' => $this->treatmentPlan->id,
                'treatment_plan_name' => $this->treatmentPlan->name,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify prescription created
        $presc = ProductRequest::where('product_id', $this->drugItem->id)
            ->where('patient_id', $this->patient->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($presc, 'ProductRequest should be created for pharmacy combo item.');
        $this->assertEquals($this->treatmentPlan->id, $presc->treatment_plan_id);
    }

    /**
     * Test POST /maternity-workbench/enrollment/{id}/apply-combo
     */
    public function test_post_maternity_workbench_apply_combo()
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $this->doctorUser->assignRole($role);

        $enrollment = MaternityEnrollment::create([
            'patient_id' => $this->patient->id,
            'enrolled_by' => $this->doctorUser->id,
            'status' => 'active',
            'enrollment_date' => now(),
        ]);

        $response = $this->actingAs($this->doctorUser)
            ->postJson("/maternity-workbench/enrollment/{$enrollment->id}/apply-combo", [
                'service_id' => $this->comboService->id,
                'treatment_plan_id' => $this->treatmentPlan->id,
                'treatment_plan_name' => $this->treatmentPlan->name,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test POST /service-combo/remove-bundle removes all child items.
     */
    public function test_post_service_combo_remove_bundle_generic()
    {
        // First apply combo
        $applyRes = $this->actingAs($this->doctorUser)
            ->postJson('/encounters/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
                'treatment_plan_id' => $this->treatmentPlan->id,
            ]);

        $applyRes->assertStatus(200);

        $parentRequest = ProductOrServiceRequest::where('service_id', $this->comboService->id)
            ->where('parent_id', null)
            ->latest('id')
            ->first();

        $this->assertNotNull($parentRequest, 'Parent combo request should exist.');

        // Now remove it
        $removeRes = $this->actingAs($this->doctorUser)
            ->postJson('/service-combo/remove-bundle', [
                'parent_request_id' => $parentRequest->id,
            ]);

        $removeRes->assertStatus(200);
        $removeRes->assertJson(['success' => true]);

        // Verify parent is marked as removed
        $parentRequest->refresh();
        $this->assertNotNull($parentRequest->removed_at);
        $this->assertEquals($this->doctorUser->id, $parentRequest->removed_by);

        // Verify child clinical rows are deleted
        $lab = LabServiceRequest::where('service_id', $this->labItem->id)
            ->where('patient_id', $this->patient->id)
            ->first();
        $this->assertNull($lab, 'Lab service request should be soft deleted after combo removal.');
    }

    /**
     * Test POST /pharmacy-workbench/remove-bundle.
     */
    public function test_post_pharmacy_workbench_remove_bundle()
    {
        $this->actingAs($this->doctorUser)
            ->postJson('/pharmacy-workbench/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
            ]);

        $parentRequest = ProductOrServiceRequest::where('service_id', $this->comboService->id)
            ->where('parent_id', null)
            ->latest('id')
            ->first();

        $this->assertNotNull($parentRequest);

        $removeRes = $this->actingAs($this->doctorUser)
            ->postJson('/pharmacy-workbench/remove-bundle', [
                'parent_request_id' => $parentRequest->id,
            ]);

        $removeRes->assertStatus(200);
        $removeRes->assertJson(['success' => true]);
    }

    /**
     * Test POST /lab-workbench/remove-bundle.
     */
    public function test_post_lab_workbench_remove_bundle()
    {
        $this->actingAs($this->doctorUser)
            ->postJson('/lab-workbench/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
            ]);

        $parentRequest = ProductOrServiceRequest::where('service_id', $this->comboService->id)
            ->where('parent_id', null)
            ->latest('id')
            ->first();

        $this->assertNotNull($parentRequest);

        $removeRes = $this->actingAs($this->doctorUser)
            ->postJson('/lab-workbench/remove-bundle', [
                'parent_request_id' => $parentRequest->id,
            ]);

        $removeRes->assertStatus(200);
        $removeRes->assertJson(['success' => true]);
    }

    /**
     * Test investigationHistoryList datatable includes View Combo and Remove Combo buttons.
     */
    public function test_investigation_history_list_contains_view_and_remove_combo_buttons()
    {
        $this->actingAs($this->doctorUser)
            ->postJson('/encounters/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
            ]);

        $response = $this->actingAs($this->doctorUser)
            ->get("/investigationHistoryList/{$this->patient->id}");

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('View Combo', $content);
        $this->assertStringContainsString('BundleViewModal.show', $content);
        $this->assertStringContainsString('Remove Combo', $content);
        $this->assertStringContainsString('showBundleRemove', $content);
        $this->assertStringContainsString('Remove Item', $content);
        $this->assertStringContainsString('showBundleItemRemove', $content);
    }

    /**
     * Test removing an individual combo item via /service-combo/remove-item.
     */
    public function test_remove_individual_combo_item_endpoint()
    {
        $this->actingAs($this->doctorUser)
            ->postJson('/encounters/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
            ]);

        $parentRequest = ProductOrServiceRequest::where('service_id', $this->comboService->id)
            ->whereNull('parent_id')
            ->latest('id')
            ->first();

        $this->assertNotNull($parentRequest);

        $labChild = ProductOrServiceRequest::where('parent_id', $parentRequest->id)
            ->where('service_id', $this->labItem->id)
            ->first();
        $drugChild = ProductOrServiceRequest::where('parent_id', $parentRequest->id)
            ->where('product_id', $this->drugItem->id)
            ->first();

        $this->assertNotNull($labChild);
        $this->assertNotNull($drugChild);

        // Remove only the lab item
        $res = $this->actingAs($this->doctorUser)
            ->postJson('/service-combo/remove-item', [
                'child_request_id' => $labChild->id,
                'reason' => 'Patient declined lab test',
            ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        // Verify lab child is marked removed
        $labChild->refresh();
        $this->assertNotNull($labChild->removed_at);
        $this->assertEquals($this->doctorUser->id, $labChild->removed_by);

        // Verify linked LabServiceRequest is soft-deleted
        $labReq = LabServiceRequest::withTrashed()->where('service_request_id', $labChild->id)->first();
        $this->assertNotNull($labReq);
        $this->assertTrue($labReq->trashed());

        // Verify parent is NOT removed because drug item remains
        $parentRequest->refresh();
        $this->assertNull($parentRequest->removed_at);

        // Now remove the drug item as well
        $res2 = $this->actingAs($this->doctorUser)
            ->postJson('/service-combo/remove-item', [
                'child_request_id' => $drugChild->id,
                'reason' => 'Not needed',
            ]);

        $res2->assertStatus(200);
        $res2->assertJson(['success' => true]);

        // Verify parent is now also removed because all children are removed
        $parentRequest->refresh();
        $this->assertNotNull($parentRequest->removed_at);
        $this->assertEquals($this->doctorUser->id, $parentRequest->removed_by);
    }

    /**
     * Test cannot remove combo item if parent combo is already paid.
     */
    public function test_cannot_remove_combo_item_if_parent_is_paid()
    {
        $this->actingAs($this->doctorUser)
            ->postJson('/encounters/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
            ]);

        $parentRequest = ProductOrServiceRequest::where('service_id', $this->comboService->id)
            ->whereNull('parent_id')
            ->latest('id')
            ->first();

        $parentRequest->payment_id = 12345;
        $parentRequest->save();

        $labChild = ProductOrServiceRequest::where('parent_id', $parentRequest->id)
            ->where('service_id', $this->labItem->id)
            ->first();

        $res = $this->actingAs($this->doctorUser)
            ->postJson('/service-combo/remove-item', [
                'child_request_id' => $labChild->id,
            ]);

        $res->assertStatus(400);
        $res->assertJson(['success' => false]);
        $this->assertStringContainsString('paid', strtolower($res->json('message')));
    }

    /**
     * Test deleteLab and deletePrescription controller endpoints support combo items.
     */
    public function test_delete_lab_and_prescription_controller_endpoints_support_combo_items()
    {
        $encounter = Encounter::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctorUser->id,
            'completed' => 0,
        ]);

        $this->actingAs($this->doctorUser)
            ->postJson('/encounters/apply-combo', [
                'service_id' => $this->comboService->id,
                'patient_id' => $this->patient->id,
                'encounter_id' => $encounter->id,
            ]);

        $parentRequest = ProductOrServiceRequest::where('service_id', $this->comboService->id)
            ->whereNull('parent_id')
            ->latest('id')
            ->first();

        $labChild = ProductOrServiceRequest::where('parent_id', $parentRequest->id)
            ->where('service_id', $this->labItem->id)
            ->first();
        $drugChild = ProductOrServiceRequest::where('parent_id', $parentRequest->id)
            ->where('product_id', $this->drugItem->id)
            ->first();

        $labReq = LabServiceRequest::where('service_request_id', $labChild->id)->first();
        $drugReq = ProductRequest::where('product_request_id', $drugChild->id)->first();

        $this->assertNotNull($labReq);
        $this->assertNotNull($drugReq);

        // Delete lab via encounter deleteLab endpoint
        $delLabRes = $this->actingAs($this->doctorUser)
            ->deleteJson("/encounters/{$encounter->id}/labs/{$labReq->id}", [
                'reason' => 'Duplicate order',
            ]);

        $delLabRes->assertStatus(200);
        $delLabRes->assertJson(['success' => true]);

        $labChild->refresh();
        $this->assertNotNull($labChild->removed_at);

        // Delete prescription via encounter deletePrescription endpoint
        $delPrescRes = $this->actingAs($this->doctorUser)
            ->deleteJson("/encounters/{$encounter->id}/prescriptions/{$drugReq->id}", [
                'reason' => 'Patient has allergy',
            ]);

        $delPrescRes->assertStatus(200);
        $delPrescRes->assertJson(['success' => true]);

        $drugChild->refresh();
        $this->assertNotNull($drugChild->removed_at);

        // Verify parent is also now marked removed
        $parentRequest->refresh();
        $this->assertNotNull($parentRequest->removed_at);
    }
}
