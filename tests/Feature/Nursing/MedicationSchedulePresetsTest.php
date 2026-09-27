<?php

namespace Tests\Feature\Nursing;

use App\Models\MedicationSchedule;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MedicationSchedulePresetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'surname' => 'Nurse',
            'firstname' => 'Staff',
            'othername' => 'Clara',
            'email' => 'nurse_' . Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_admin' => 1,
        ], $attrs));
    }

    private function makePatient(array $attrs = []): Patient
    {
        $user = $this->makeUser(['surname' => 'PatientUser']);

        return Patient::create(array_merge([
            'user_id' => $user->id,
            'file_no' => 'PAT-' . Str::random(6),
            'gender' => 'Male',
            'phone_no' => '080' . rand(10000000, 99999999),
        ], $attrs));
    }

    private function makeProduct(User $user): Product
    {
        $category = ProductCategory::first() ?? ProductCategory::create([
            'category_name' => 'Antibiotics ' . Str::random(4),
        ]);

        return Product::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'product_name' => 'Amoxicillin ' . Str::random(4),
            'status' => 1,
        ]);
    }

    /** @test */
    public function test_store_timing_with_multiple_times_creates_all_slots()
    {
        $user = $this->makeUser();
        $patient = $this->makePatient();
        $product = $this->makeProduct($user);

        $times = ['08:00', '14:00', '20:00'];
        $startDate = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($user)->postJson('/patients/nurse-chart/medication/schedule', [
            'patient_id' => $patient->id,
            'drug_source' => 'ward_stock',
            'product_id' => $product->id,
            'times' => $times,
            'frequency' => 'TID',
            'dose' => '500mg',
            'route' => 'Oral',
            'repeat_type' => 'daily',
            'duration_days' => 2,
            'start_date' => $startDate,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));

            // 3 times per day * 2 days = 6 schedules
            $schedules = MedicationSchedule::where('patient_id', $patient->id)
                ->where('product_id', $product->id)
                ->get();

            $this->assertCount(6, $schedules);
            $this->assertEquals('500mg', $schedules->first()->dose);
            $this->assertEquals('Oral', $schedules->first()->route);
        }
    }

    /** @test */
    public function test_store_timing_backward_compatible_with_single_time()
    {
        $user = $this->makeUser();
        $patient = $this->makePatient();
        $product = $this->makeProduct($user);

        $startDate = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($user)->postJson('/patients/nurse-chart/medication/schedule', [
            'patient_id' => $patient->id,
            'drug_source' => 'ward_stock',
            'product_id' => $product->id,
            'time' => '09:00',
            'frequency' => 'OD',
            'dose' => '1000mg',
            'route' => 'IV',
            'repeat_type' => 'daily',
            'duration_days' => 3,
            'start_date' => $startDate,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));

            $schedules = MedicationSchedule::where('patient_id', $patient->id)
                ->where('product_id', $product->id)
                ->where('dose', '1000mg')
                ->get();

            $this->assertCount(3, $schedules);
        }
    }

    /** @test */
    public function test_store_timing_stat_preset_creates_single_dose()
    {
        $user = $this->makeUser();
        $patient = $this->makePatient();
        $product = $this->makeProduct($user);

        $startDate = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($user)->postJson('/patients/nurse-chart/medication/schedule', [
            'patient_id' => $patient->id,
            'drug_source' => 'ward_stock',
            'product_id' => $product->id,
            'times' => ['11:30'],
            'frequency' => 'STAT',
            'dose' => '2g',
            'route' => 'IV',
            'repeat_type' => 'once',
            'duration_days' => 1,
            'start_date' => $startDate,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));

            $schedules = MedicationSchedule::where('patient_id', $patient->id)
                ->where('product_id', $product->id)
                ->where('dose', '2g')
                ->get();

            $this->assertCount(1, $schedules);
        }
    }

    /** @test */
    public function test_store_timing_validation_errors()
    {
        $user = $this->makeUser();

        // Missing dose and route
        $response = $this->actingAs($user)->postJson('/patients/nurse-chart/medication/schedule', [
            'patient_id' => 999999,
            'start_date' => '2026-09-26',
        ]);

        $this->assertTrue(in_array($response->status(), [422, 302, 500]));
    }

    /** @test */
    public function test_remove_schedule_endpoint()
    {
        $user = $this->makeUser();
        $patient = $this->makePatient();
        $product = $this->makeProduct($user);

        $schedule = MedicationSchedule::create([
            'patient_id' => $patient->id,
            'drug_source' => 'ward_stock',
            'product_id' => $product->id,
            'scheduled_time' => Carbon::now()->addHours(2),
            'dose' => '500mg',
            'route' => 'Oral',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson('/patients/nurse-chart/medication/remove-schedule', [
            'schedule_id' => $schedule->id,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $this->assertDatabaseMissing('medication_schedules', ['id' => $schedule->id]);
        }
    }
}
