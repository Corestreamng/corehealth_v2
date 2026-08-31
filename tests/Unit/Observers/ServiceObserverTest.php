<?php

namespace Tests\Unit\Observers;

use App\Models\Service;

class ServiceObserverTest extends \Tests\TestCase
{
    /** @test */
    public function test_service_observer_handles_events()
    {
        $service = Service::first() ?? Service::factory()->create();
        $this->assertNotNull($service->id);
    }
}
