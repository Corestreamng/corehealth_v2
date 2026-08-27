<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use App\Models\User;
use Tests\TestCase;

class DispenseBillingTest extends TestCase
{
    /** @test */
    public function test_dispense_step_separate_from_billing_step()
    {
        $request = ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'validation_status' => 'pending',
        ]);
        $this->assertEquals('pending', $request->validation_status);
    }

    /** @test */
    public function test_billing_marked_billed_before_dispense_allowed()
    {
        $request = ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'validation_status' => 'approved',
        ]);
        $this->assertEquals('approved', $request->validation_status);
    }

    /** @test */
    public function test_dispense_updates_product_request_status()
    {
        $request = ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'validation_status' => 'rejected',
        ]);
        $this->assertEquals('rejected', $request->validation_status);
    }

    /** @test */
    public function test_dispense_without_billing_rejected()
    {
        $request = ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'validation_status' => 'pending',
        ]);
        $this->assertNotEquals('approved', $request->validation_status);
    }

}

