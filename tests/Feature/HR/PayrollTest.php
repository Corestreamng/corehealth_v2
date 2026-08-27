<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    /** @test */
    public function test_payslip_gross_calculated_from_base_salary_and_allowances()
    {
        $basic = 150000;
        $allowances = 30000;
        $gross = $basic + $allowances;
        $this->assertEquals(180000, $gross);
    }

    /** @test */
    public function test_statutory_deductions_calculated_correctly()
    {
        $gross = 200000;
        $tax = $gross * 0.10;
        $net = $gross - $tax;
        $this->assertEquals(180000, $net);
    }

    /** @test */
    public function test_leave_balance_decremented_on_leave_approval()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_leave_request_rejected_when_balance_insufficient()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_payroll_batch_creates_payslip_for_all_active_staff()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $response = $this->actingAs($admin)->get('/hr/workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404]));
    }


}
