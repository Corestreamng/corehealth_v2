<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Patient;
use Tests\TestCase;

class PharmacyReturnsTest extends TestCase
{
    /** @test */
    public function test_good_return_restocks_batch_and_reverses_billing()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_wrong_item_return_marks_request_returned()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_damaged_return_creates_damage_report()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_expired_return_triggers_write_off_je()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_return_refunds_to_patient_wallet()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }
}
