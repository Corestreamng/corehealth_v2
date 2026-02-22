<?php

namespace Tests\Feature;

use App\Models\InjectionAdministration;
use App\Models\MedicationAdministration;
use App\Models\MedicationSchedule;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\User;
use App\Models\patient;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class NurseDrugSourceTest extends TestCase
{

    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');

        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        $this->withoutMiddleware();

        $this->migrateMinimalSchema();
    }

    private function migrateMinimalSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'audits',
            'injection_administrations',
            'medication_administrations',
            'medication_histories',
            'stock_batches',
            'medication_schedules',
            'product_requests',
            'product_or_service_requests',
            'stores',
            'stocks',
            'prices',
            'products',
            'product_categories',
            'patients',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('is_admin')->default(20);
            $table->string('email')->unique();
            $table->string('filename')->nullable();
            $table->string('old_records')->nullable();
            $table->string('surname');
            $table->string('firstname');
            $table->string('othername')->nullable();
            $table->string('assignRole')->nullable();
            $table->string('assignPermission')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->integer('status')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('file_no')->nullable();
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name');
            $table->string('category_code')->nullable();
            $table->string('category_description')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id');
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->integer('current_quantity')->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('initial_quantity')->default(0);
            $table->integer('order_quantity')->default(0);
            $table->integer('current_quantity')->default(0);
            $table->integer('quantity_sale')->default(0);
            $table->timestamps();
        });

        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('pr_buy_price', 12, 2)->default(0);
            $table->decimal('initial_sale_price', 12, 2)->default(0);
            $table->decimal('current_sale_price', 12, 2)->default(0);
            $table->integer('max_discount')->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('store_name');
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        Schema::create('product_or_service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('staff_user_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('qty')->default(1);
            $table->unsignedBigInteger('dispensed_from_store_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('order_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('payable_amount', 12, 2)->default(0);
            $table->decimal('claims_amount', 12, 2)->default(0);
            $table->string('coverage_mode')->nullable();
            $table->timestamps();
        });

        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_request_id')->nullable();
            $table->unsignedBigInteger('billed_by')->nullable();
            $table->timestamp('billed_date')->nullable();
            $table->unsignedBigInteger('dispensed_by')->nullable();
            $table->timestamp('dispense_date')->nullable();
            $table->unsignedBigInteger('dispensed_from_store_id')->nullable();
            $table->unsignedBigInteger('dispensed_from_batch_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->text('dose')->nullable();
            $table->integer('status')->default(1);
            $table->integer('qty')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medication_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_or_service_request_id');
            $table->dateTime('scheduled_time');
            $table->string('dose')->nullable();
            $table->string('route')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id');
            $table->string('batch_name');
            $table->string('batch_number')->nullable();
            $table->integer('initial_qty');
            $table->integer('current_qty');
            $table->integer('sold_qty')->default(0);
            $table->decimal('cost_price', 12, 2);
            $table->date('expiry_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('source')->default('manual');
            $table->unsignedBigInteger('created_by');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_or_service_request_id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->dateTime('administered_at');
            $table->string('dose');
            $table->string('route');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('administered_by');
            $table->string('drug_source')->default('pharmacy_dispensed');
            $table->unsignedBigInteger('product_request_id')->nullable();
            $table->string('external_drug_name')->nullable();
            $table->decimal('external_qty', 8, 2)->nullable();
            $table->string('external_batch_number')->nullable();
            $table->date('external_expiry_date')->nullable();
            $table->text('external_source_note')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('dispensed_from_batch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('medication_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_or_service_request_id');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('injection_administrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_or_service_request_id')->nullable();
            $table->string('dose', 100);
            $table->string('route', 5);
            $table->string('site', 100)->nullable();
            $table->dateTime('administered_at');
            $table->unsignedBigInteger('administered_by');
            $table->string('drug_source')->default('pharmacy_dispensed');
            $table->unsignedBigInteger('product_request_id')->nullable();
            $table->string('external_drug_name')->nullable();
            $table->decimal('external_qty', 8, 2)->nullable();
            $table->string('external_batch_number')->nullable();
            $table->date('external_expiry_date')->nullable();
            $table->text('external_source_note')->nullable();
            $table->unsignedBigInteger('dispensed_from_store_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('url')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_medication_administer_accepts_dispensed_prescription()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $billing = $this->makeBilling($patient, $nurse, $product);
        $prescription = $this->makePrescription($patient, $product, $billing, ['status' => 3]);
        $schedule = $this->makeSchedule($patient, $billing, $nurse);

        $this->actingAs($nurse);

        $payload = [
            'schedule_id' => $schedule->id,
            'administered_at' => Carbon::now()->toDateTimeString(),
            'administered_dose' => '10mg',
            'route' => 'oral',
            'drug_source' => 'pharmacy_dispensed',
            'product_request_id' => $prescription->id,
        ];

        $response = $this->postJson('/patients/nurse-chart/medication/administer', $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('administration.drug_source', 'pharmacy_dispensed');

        $this->assertDatabaseHas('medication_administrations', [
            'schedule_id' => $schedule->id,
            'product_request_id' => $prescription->id,
            'drug_source' => 'pharmacy_dispensed',
            'store_id' => null,
        ]);
    }

    public function test_medication_administer_from_ward_stock_records_batch_and_store()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $store = $this->makeStore();
        $billing = $this->makeBilling($patient, $nurse, $product, $store);
        $schedule = $this->makeSchedule($patient, $billing, $nurse);
        $batch = $this->makeStockBatch($store, $product, $nurse, 3);

        $stockService = Mockery::mock(StockService::class);
        $stockService->shouldReceive('getAvailableStock')->once()->andReturn(3);
        $stockService->shouldReceive('dispenseStock')->once()->andReturn([$batch->id => 1]);
        $this->app->instance(StockService::class, $stockService);

        $this->actingAs($nurse);

        $payload = [
            'schedule_id' => $schedule->id,
            'administered_at' => Carbon::now()->toDateTimeString(),
            'administered_dose' => '5ml',
            'route' => 'IV',
            'drug_source' => 'ward_stock',
            'store_id' => $store->id,
            'product_id' => $product->id,
        ];

        $response = $this->postJson('/patients/nurse-chart/medication/administer', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('administration.drug_source', 'ward_stock');

        $this->assertDatabaseHas('medication_administrations', [
            'schedule_id' => $schedule->id,
            'drug_source' => 'ward_stock',
            'store_id' => $store->id,
            'dispensed_from_batch_id' => $batch->id,
        ]);
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

        $response->assertStatus(200)
            ->assertJsonPath('administration.drug_source', 'patient_own')
            ->assertJsonPath('administration.external_drug_name', 'Home med');

        $this->assertDatabaseHas('medication_administrations', [
            'schedule_id' => $schedule->id,
            'drug_source' => 'patient_own',
            'product_request_id' => null,
            'store_id' => null,
        ]);
    }

    public function test_medication_administer_rejects_undispensed_prescription()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $billing = $this->makeBilling($patient, $nurse, $product);
        $prescription = $this->makePrescription($patient, $product, $billing, ['status' => 2]);
        $schedule = $this->makeSchedule($patient, $billing, $nurse);

        $this->actingAs($nurse);

        $payload = [
            'schedule_id' => $schedule->id,
            'administered_at' => Carbon::now()->toDateTimeString(),
            'administered_dose' => '10mg',
            'route' => 'oral',
            'drug_source' => 'pharmacy_dispensed',
            'product_request_id' => $prescription->id,
        ];

        $response = $this->postJson('/patients/nurse-chart/medication/administer', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'This prescription has not been dispensed yet',
            ]);

        $this->assertDatabaseCount('medication_administrations', 0);
    }

    public function test_injection_administer_accepts_dispensed_prescription()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $billing = $this->makeBilling($patient, $nurse, $product);
        $prescription = $this->makePrescription($patient, $product, $billing, ['status' => 3]);

        $this->actingAs($nurse);

        $payload = [
            'patient_id' => $patient->id,
            'drug_source' => 'pharmacy_dispensed',
            'route' => 'IM',
            'site' => 'Left Arm',
            'administered_at' => Carbon::now()->toDateTimeString(),
            'products' => [
                [
                    'product_id' => $product->id,
                    'product_request_id' => $prescription->id,
                    'dose' => '5ml',
                ],
            ],
        ];

        $response = $this->postJson('/nursing-workbench/administer-injection', $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('injection_administrations', [
            'patient_id' => $patient->id,
            'product_request_id' => $prescription->id,
            'drug_source' => 'pharmacy_dispensed',
        ]);
    }

    public function test_injection_administer_from_ward_stock_creates_bill_and_store_link()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);
        $store = $this->makeStore('Injection Store');

        $stockService = Mockery::mock(StockService::class);
        $stockService->shouldReceive('getAvailableStock')->once()->andReturn(5);
        $stockService->shouldReceive('dispenseStock')->once()->andReturn([]);
        $this->app->instance(StockService::class, $stockService);

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

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('injection_administrations', [
            'patient_id' => $patient->id,
            'drug_source' => 'ward_stock',
            'dispensed_from_store_id' => $store->id,
            'product_request_id' => null,
        ]);

        $this->assertDatabaseCount('product_or_service_requests', 1);
    }

    public function test_injection_administer_from_patient_own_records_external_fields()
    {
        $nurse = $this->makeUser(['surname' => 'Nurse']);
        $patient = $this->makePatient();
        $category = $this->makeCategory();
        $product = $this->makeProduct($nurse, $category);

        $this->actingAs($nurse);

        $payload = [
            'patient_id' => $patient->id,
            'drug_source' => 'patient_own',
            'route' => 'SC',
            'site' => 'Abdomen',
            'administered_at' => Carbon::now()->toDateTimeString(),
            'products' => [
                [
                    'product_id' => $product->id,
                    'dose' => '1 dose',
                    'external_drug_name' => 'Patient supply',
                    'external_qty' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/nursing-workbench/administer-injection', $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('injections.0.drug_source', 'patient_own');

        $this->assertDatabaseHas('injection_administrations', [
            'patient_id' => $patient->id,
            'drug_source' => 'patient_own',
            'product_request_id' => null,
        ]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'surname' => 'Test',
            'firstname' => 'User',
            'othername' => null,
            'email' => Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
            'is_admin' => 1,
            'status' => 1,
        ], $overrides));
    }

    private function makePatient(): patient
    {
        $user = $this->makeUser([
            'surname' => 'Patient',
            'firstname' => 'Alpha',
        ]);

        return patient::create([
            'user_id' => $user->id,
            'file_no' => 'FN-' . $user->id,
        ]);
    }

    private function makeCategory(): ProductCategory
    {
        return ProductCategory::create([
            'category_name' => 'Drugs-' . Str::random(4),
        ]);
    }

    private function makeProduct(User $owner, ProductCategory $category): Product
    {
        return Product::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'product_name' => 'Product ' . Str::random(4),
            'product_code' => 'P-' . Str::random(5),
            'status' => 1,
            'current_quantity' => 10,
        ]);
    }

    private function makeStore(string $name = 'Ward Store'): Store
    {
        return Store::create([
            'store_name' => $name,
            'status' => 1,
        ]);
    }

    private function makeStockBatch(Store $store, Product $product, User $creator, int $qty = 5): StockBatch
    {
        return StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'BATCH-' . Str::random(4),
            'batch_number' => 'BN-' . Str::random(5),
            'initial_qty' => $qty,
            'current_qty' => $qty,
            'sold_qty' => 0,
            'cost_price' => 10,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'received_date' => now()->toDateString(),
            'source' => StockBatch::SOURCE_MANUAL,
            'created_by' => $creator->id,
            'is_active' => true,
        ]);
    }

    private function makeBilling(patient $patient, User $staff, Product $product, ?Store $store = null): ProductOrServiceRequest
    {
        return ProductOrServiceRequest::create([
            'type' => 'product',
            'user_id' => $patient->user_id,
            'patient_id' => $patient->id,
            'staff_user_id' => $staff->id,
            'product_id' => $product->id,
            'qty' => 1,
            'dispensed_from_store_id' => $store?->id,
            'created_by' => $staff->id,
            'order_date' => Carbon::now(),
            'amount' => 0,
        ]);
    }

    private function makePrescription(patient $patient, Product $product, ProductOrServiceRequest $billing, array $overrides = []): ProductRequest
    {
        return ProductRequest::create(array_merge([
            'product_request_id' => $billing->id,
            'patient_id' => $patient->id,
            'product_id' => $product->id,
            'status' => 3,
            'qty' => 1,
        ], $overrides));
    }

    private function makeSchedule(patient $patient, ProductOrServiceRequest $billing, User $creator): MedicationSchedule
    {
        return MedicationSchedule::create([
            'patient_id' => $patient->id,
            'product_or_service_request_id' => $billing->id,
            'scheduled_time' => Carbon::now()->addHour(),
            'dose' => '10mg',
            'route' => 'oral',
            'created_by' => $creator->id,
        ]);
    }
}
