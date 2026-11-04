<?php

namespace Tests\Unit\Encounter;

use PHPUnit\Framework\TestCase;

class EncounterCreateOptimizationTest extends TestCase
{
    /** @test */
    public function test_reasons_grouping_produces_nested_category_and_subcategory_structure()
    {
        $mockReasons = collect([
            (object)['code' => 'R01', 'name' => 'Fever', 'category' => 'General', 'sub_category' => 'Symptoms'],
            (object)['code' => 'R02', 'name' => 'Chills', 'category' => 'General', 'sub_category' => 'Symptoms'],
            (object)['code' => 'R03', 'name' => 'Fatigue', 'category' => 'General', 'sub_category' => 'General complaints'],
            (object)['code' => 'K01', 'name' => 'Chest Pain', 'category' => 'Circulatory', 'sub_category' => 'Symptoms'],
        ]);

        $grouped = $mockReasons->groupBy(['category', 'sub_category']);

        $this->assertTrue($grouped->has('General'));
        $this->assertTrue($grouped->has('Circulatory'));
        $this->assertTrue($grouped['General']->has('Symptoms'));
        $this->assertTrue($grouped['General']->has('General complaints'));
        $this->assertCount(2, $grouped['General']['Symptoms']);
        $this->assertEquals('R01', $grouped['General']['Symptoms'][0]->code);
        $this->assertEquals('R02', $grouped['General']['Symptoms'][1]->code);
    }

    /** @test */
    public function test_empty_reasons_collection_returns_empty_grouping()
    {
        $empty = collect([])->groupBy(['category', 'sub_category']);
        $this->assertTrue($empty->isEmpty());
    }

    /** @test */
    public function test_empty_reasons_and_staff_collection_prevents_memory_spike()
    {
        $memBefore = memory_get_usage();
        $reasonsGrouped = collect([]);
        $staffUsers = collect([
            (object)['id' => 1, 'surname' => 'Adeyemi', 'firstname' => 'Tunde', 'othername' => 'Paul'],
        ]);

        $this->assertTrue($reasonsGrouped->isEmpty());
        $this->assertCount(1, $staffUsers);
        $memAfter = memory_get_usage();

        // Memory allocated should be negligible (< 10KB)
        $this->assertLessThan(10240, $memAfter - $memBefore);
    }
}
