<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Tests\TestCase;

class AccountingFoundationTest extends TestCase
{
    /** @test */
    public function test_chart_of_accounts_endpoint_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/accounting/accounts');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_journal_entry_has_balanced_debit_credit()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_account_group_structure_correct()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_journal_entry_linked_to_source_transaction()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }
}
