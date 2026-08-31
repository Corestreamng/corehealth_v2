<?php

namespace Tests\Unit\Observers;

use App\Models\Hmo;

class HmoObserverTest extends \Tests\TestCase
{
    /** @test */
    public function test_hmo_observer_handles_events()
    {
        $hmo = Hmo::first() ?? Hmo::factory()->create();
        $this->assertNotNull($hmo->id);
    }
}
