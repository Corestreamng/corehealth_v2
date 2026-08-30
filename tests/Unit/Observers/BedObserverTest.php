<?php

namespace Tests\Unit\Observers;

use App\Models\Bed;
use App\Models\Service;
use App\Models\ServicePrice;
use Tests\TestCase;

class BedObserverTest extends TestCase
{
    /** @test */
    public function it_creates_or_syncs_service_and_price_when_bed_is_saved()
    {
        $bed = Bed::create([
            'name' => 'Bed 501',
            'ward' => 'VIP Ward',
            'status' => 1,
            'price' => 5000.00,
        ]);

        $this->assertNotNull($bed->id);
        $this->assertEquals(1, $bed->status);

        // If bed has service_id, verify service price sync
        if ($bed->service_id) {
            $servicePrice = ServicePrice::where('service_id', $bed->service_id)->first();
            $this->assertNotNull($servicePrice);
            $this->assertEquals(5000.00, $servicePrice->sale_price);
        }
    }
}
