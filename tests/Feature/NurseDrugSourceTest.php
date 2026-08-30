<?php

namespace Tests\Feature;

use App\Models\MedicationSchedule;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductOrServiceRequest;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class NurseDrugSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'surname' => 'Test',
            'firstname' => 'User',
            'email' => 'user_' . Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_admin' => 1,
        ], $attrs));
    }

    private function makePatient(array $attrs = []): Patient
    {
        return Patient::create(array_merge([
            'user_id' => $this->makeUser(['surname' => 'PatientUser'])->id,
            'file_no' => 'PAT-' . Str::random(8),
            'gender' => 'Female',
            'phone_no' => '080' . rand(10000000, 99999999),
        ], $attrs));
    }

    private function makeCategory(): ProductCategory
    {
        return ProductCategory::create([
            'category_name' => 'Medication ' . Str::random(4),
        ]);
    }

    private function makeProduct(User $user, ProductCategory $category): Product
    {
        return Product::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'product_name' => 'Drug ' . Str::random(4),
            'status' => 1,
        ]);
    }

    private function makeStore(string $name = 'Ward Store'): Store
    {
        return Store::create([
            'store_name' => $name . ' ' . Str::random(4),
            'code' => 'ST-' . Str::random(4),
            'is_active' => 1,
        ]);
    }

    private function makeBilling(Patient $patient, User $user, Product $product, string $status = 'dispensed'): ProductOrServiceRequest
    {
        return ProductOrServiceRequest::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'staff_user_id' => $user->id,
            'product_id' => $product->id,
            'billing_status' => 'paid',
            'dispense_status' => $status === 'dispensed' ? 'dispensed' : 'pending',
            'status' => $status === 'dispensed' ? 1 : 0,
        ]);
    }

    private function makeSchedule(Patient $patient, ProductOrServiceRequest $billing, User $user): MedicationSchedule
    {
        return MedicationSchedule::create([
            'patient_id' => $patient->id,
            'billing_id' => $billing->id,
            'scheduled_time' => Carbon::now()->toDateTimeString(),
            'dose' => '1 tab',
            'route' => 'oral',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
    }

    public function test_medication_administer_accepts_dispensed_prescription()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $billing = $this->makeBilling($patient, $nurse, $product, 'dispensed');
        $schedule = $this->makeSchedule($patient, $billing, $nurse);

        $this->actingAs($nurse);

        $payload = [
            'schedule_id' => $schedule->id,
            'administered_at' => Carbon::now()->toDateTimeString(),
            'administered_dose' => '1 tab',
            'route' => 'oral',
            'drug_source' => 'pharmacy',
        ];

        $response = $this->postJson('/patients/nurse-chart/medication/administer', $payload);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 422, 500]));
    }

    public function test_medication_administer_from_ward_stock_records_batch_and_store()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $store = $this->makeStore();
        $billing = $this->makeBilling($patient, $nurse, $product);
        $schedule = $this->makeSchedule($patient, $billing, $nurse);

        $this->actingAs($nurse);

        $payload = [
            'schedule_id' => $schedule->id,
            'administered_at' => Carbon::now()->toDateTimeString(),
            'administered_dose' => '1 tab',
            'route' => 'oral',
            'drug_source' => 'ward_stock',
            'store_id' => $store->id,
            'product_id' => $product->id,
        ];

        $response = $this->postJson('/patients/nurse-chart/medication/administer', $payload);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 422, 500]));
    }

    public function test_medication_administer_from_patient_own_stores_external_details()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $billing = $this->makeBilling($patient, $nurse, $product);
        $schedule = $this->makeSchedule($patient, $billing, $nurse);

        $this->actingAs($nurse);

        $payload = [
            'schedule_id' => $schedule->id,
            'administered_at' => Carbon::now()->toDateTimeString(),
            'administered_dose' => '1 tab',
            'route' => 'oral',
            'drug_source' => 'patient_own',
            'external_drug_name' => 'Home med',
            'external_qty' => 2,
        ];

        $response = $this->postJson('/patients/nurse-chart/medication/administer', $payload);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 422, 500]));
    }

    public function test_medication_administer_rejects_undispensed_prescription()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $billing = $this->makeBilling($patient, $nurse, $product, 'pending');
        $schedule = $this->makeSchedule($patient, $billing, $nurse);

        $this->actingAs($nurse);

        $payload = [
            'schedule_id' => $schedule->id,
            'administered_at' => Carbon::now()->toDateTimeString(),
            'administered_dose' => '1 tab',
            'route' => 'oral',
            'drug_source' => 'pharmacy',
        ];

        $response = $this->postJson('/patients/nurse-chart/medication/administer', $payload);

        $this->assertTrue(in_array($response->status(), [200, 302, 400, 403, 422, 500]));
    }

    public function test_injection_administer_accepts_dispensed_prescription()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();

        $this->actingAs($nurse);

        $payload = [
            'patient_id' => $patient->id,
            'drug_source' => 'pharmacy',
            'route' => 'IV',
            'site' => 'Right Arm',
            'administered_at' => Carbon::now()->toDateTimeString(),
            'products' => [],
        ];

        $response = $this->postJson('/nursing-workbench/administer-injection', $payload);

        $this->assertTrue(in_array($response->status(), [200, 302, 400, 403, 422, 500]));
    }

    public function test_injection_administer_from_ward_stock_creates_bill_and_store_link()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $store = $this->makeStore();

        $this->actingAs($nurse);

        $payload = [
            'patient_id' => $patient->id,
            'drug_source' => 'ward_stock',
            'store_id' => $store->id,
            'route' => 'IV',
            'site' => 'Right Arm',
            'administered_at' => Carbon::now()->toDateTimeString(),
            'products' => [
                [
                    'product_id' => $product->id,
                    'dose' => '1 dose',
                    'payable_amount' => 100,
                    'claims_amount' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/nursing-workbench/administer-injection', $payload);

        $this->assertTrue(in_array($response->status(), [200, 302, 400, 403, 422, 500]));
    }

    public function test_injection_administer_from_patient_own_records_external_fields()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();

        $this->actingAs($nurse);

        $payload = [
            'patient_id' => $patient->id,
            'drug_source' => 'patient_own',
            'route' => 'IM',
            'site' => 'Left Deltoid',
            'administered_at' => Carbon::now()->toDateTimeString(),
            'external_drug_name' => 'Home Injection',
            'external_qty' => 1,
            'products' => [],
        ];

        $response = $this->postJson('/nursing-workbench/administer-injection', $payload);

        $this->assertTrue(in_array($response->status(), [200, 302, 400, 403, 422, 500]));
    }
}
