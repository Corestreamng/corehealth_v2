<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Store;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreContextOverrideModalTest extends TestCase
{
    protected User $pharmacist;

    protected Store $pharmacyStore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Permission::firstOrCreate(['name' => 'store-context.change-manual', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'PHARMACIST', 'guard_name' => 'web']);

        $this->pharmacist = User::factory()->create();
        $this->pharmacist->assignRole('PHARMACIST');
        $this->pharmacist->givePermissionTo('store-context.change-manual');

        $this->pharmacyStore = Store::firstOrCreate(
            ['store_name' => 'Main Pharmacy Store Test'],
            [
                'distribution_role' => Store::ROLE_PHARMACY_HUB,
                'status' => 1,
                'is_default' => true,
            ]
        );
    }

    public function test_pharmacy_workbench_renders_store_context_override_modal(): void
    {
        $response = $this->actingAs($this->pharmacist)->get(route('pharmacy.workbench'));

        $response->assertStatus(200);
        $response->assertSee('storeContextOverrideModal');
        $response->assertSee('ctx-store-select');
        $response->assertSee('ctx-override-btn');
        $response->assertSee('ctx-override-clear-btn');
    }

    public function test_set_and_clear_store_context_endpoints(): void
    {
        $response = $this->actingAs($this->pharmacist)->postJson(route('store-context.set'), [
            'store_id' => $this->pharmacyStore->id,
            'context' => 'pharmacy',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $clearResponse = $this->actingAs($this->pharmacist)->postJson(route('store-context.clear'));
        $clearResponse->assertStatus(200);
        $clearResponse->assertJson(['success' => true]);
    }
}
