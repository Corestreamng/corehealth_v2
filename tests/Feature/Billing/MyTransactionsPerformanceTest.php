<?php

namespace Tests\Feature\Billing;

use App\Models\Department;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class MyTransactionsPerformanceTest extends TestCase
{
    protected User $user;

    protected Patient $patient;

    protected Department $department;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->user = User::first() ?? User::factory()->create(['status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $this->patient = Patient::create([
            'user_id' => $patientUser->id,
            'file_no' => 'PRF_' . uniqid(),
            'status' => 1,
        ]);

        $deptCode = strtoupper(substr('PR' . uniqid(), 0, 8));
        $this->department = Department::create([
            'name' => 'Performance Dept ' . uniqid(),
            'code' => $deptCode,
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'user_id' => $this->user->id,
            'service_name' => 'High Volume Service ' . uniqid(),
            'category_id' => 1,
            'department_id' => $this->department->id,
            'status' => 1,
        ]);
    }

    public function test_my_transactions_truncates_and_computes_sql_summary_when_dataset_exceeds_limit()
    {
        // Create 3 payments with explicit limit = 2
        for ($i = 0; $i < 3; $i++) {
            $payment = Payment::create([
                'user_id' => $this->user->id,
                'patient_id' => $this->patient->id,
                'reference_no' => 'PAY-PRF-' . uniqid(),
                'payment_type' => 'CASH',
                'payment_method' => 'CASH',
                'total' => 1500,
                'total_discount' => 0,
            ]);

            ProductOrServiceRequest::create([
                'user_id' => $this->user->id,
                'staff_user_id' => $this->user->id,
                'patient_id' => $this->patient->id,
                'payment_id' => $payment->id,
                'service_id' => $this->service->id,
                'department_id' => $this->department->id,
                'qty' => 1,
                'payable_amount' => 1500,
                'status' => 1,
            ]);
        }

        $response = $this->actingAs($this->user)->getJson('/billing-workbench/my-transactions?' . http_build_query([
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'limit' => 2,
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertTrue($data['is_truncated']);
        $this->assertGreaterThanOrEqual(3, $data['total_count']);
        $this->assertLessThanOrEqual(2, $data['showing_count']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertGreaterThanOrEqual(4500, (float) $data['summary']['total_amount']);
    }

    public function test_wide_date_range_completes_with_status_200()
    {
        $response = $this->actingAs($this->user)->getJson('/billing-workbench/my-transactions?' . http_build_query([
            'from' => '2026-06-01',
            'to' => '2026-10-25',
            'payment_type' => '',
            'department_id' => '',
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('transactions', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('department_kpis', $data['summary']);
    }

    public function test_my_transactions_print_view_includes_truncation_notice()
    {
        $response = $this->actingAs($this->user)->get('/billing-workbench/my-transactions?' . http_build_query([
            'from' => '2026-06-01',
            'to' => '2026-10-25',
            'action' => 'print',
        ]));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('Transaction Itemization', $content);
    }

    public function test_my_transactions_datatable_serverside_pagination_and_search()
    {
        $response = $this->actingAs($this->user)->getJson('/billing-workbench/my-transactions?' . http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'from' => '2026-06-01',
            'to' => '2026-10-25',
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['draw']);
        $this->assertArrayHasKey('recordsTotal', $data);
        $this->assertArrayHasKey('recordsFiltered', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('all_departments', $data);
        $this->assertArrayHasKey('all_categories', $data);
        $this->assertLessThanOrEqual(10, count($data['data']));

        if (!empty($data['data'])) {
            $firstRow = $data['data'][0];
            $this->assertArrayHasKey('created_at', $firstRow);
            $this->assertArrayHasKey('reference_badge', $firstRow);
            $this->assertArrayHasKey('patient_info', $firstRow);
            $this->assertArrayHasKey('payment_method_badge', $firstRow);
            $this->assertArrayHasKey('items_summary', $firstRow);
            $this->assertArrayHasKey('total_formatted', $firstRow);
            $this->assertArrayHasKey('actions', $firstRow);
        }
    }

    public function test_my_transactions_supports_datetime_ranges_and_refund_kpis()
    {
        // Create an inflow payment
        $inflow = Payment::create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'reference_no' => 'PAY-INFLOW-' . uniqid(),
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'total' => 5000,
            'total_discount' => 0,
            'created_at' => now()->format('Y-m-d') . ' 10:00:00',
        ]);

        // Create a refund / withdrawal voucher
        $refund = Payment::create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'reference_no' => 'REF-VOUCHER-' . uniqid(),
            'payment_type' => 'ACC_WITHDRAW',
            'payment_method' => 'CASH',
            'total' => -2000,
            'total_discount' => 0,
            'created_at' => now()->format('Y-m-d') . ' 11:00:00',
        ]);

        $today = now()->format('Y-m-d');
        $response = $this->actingAs($this->user)->getJson('/billing-workbench/my-transactions?' . http_build_query([
            'from' => "{$today}T00:00",
            'to' => "{$today}T23:59",
            'action' => 'summary',
        ]));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('summary', $data);

        $summary = $data['summary'];
        $this->assertArrayHasKey('refund_amount', $summary);
        $this->assertArrayHasKey('refund_count', $summary);
        $this->assertArrayHasKey('cash_inflow', $summary);
        $this->assertArrayHasKey('cash_inflow_count', $summary);
        $this->assertGreaterThanOrEqual(2000, abs((float) $summary['refund_amount']));
        $this->assertGreaterThanOrEqual(1, (int) $summary['refund_count']);
        $this->assertGreaterThanOrEqual(5000, (float) $summary['cash_inflow']);
    }

    public function test_audit_cashier_filter_scoping()
    {
        // Assign Admin role to user for audit context
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web']);
        $this->user->assignRole($role);

        // Another cashier
        $otherUser = User::factory()->create(['status' => 1]);
        Payment::create([
            'user_id' => $otherUser->id,
            'patient_id' => $this->patient->id,
            'reference_no' => 'PAY-OTHER-' . uniqid(),
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'total' => 3500,
            'total_discount' => 0,
        ]);

        // Query with cashier_id = otherUser->id and is_audit = 1
        $response = $this->actingAs($this->user)->getJson('/billing-workbench/my-transactions?' . http_build_query([
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'cashier_id' => $otherUser->id,
            'is_audit' => 1,
        ]));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['count']);
    }
}
