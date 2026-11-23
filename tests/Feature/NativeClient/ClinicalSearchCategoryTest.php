<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Nursing and maternity lab/imaging searches: the server limits a search to 15 rows across all categories, so
 * the app asks for the category itself (ids from `context`, the same settings the web uses) instead of
 * filtering a mixed top-15 afterwards, which could leave no lab items at all.
 */
class ClinicalSearchCategoryTest extends TestCase
{
    public function test_context_gives_the_category_ids_and_search_honours_them()
    {
        if (!Role::where('name', 'NURSE')->exists()) {
            $this->markTestSkipped('NURSE role missing.');
        }
        $nurse = User::factory()->create(['status' => 1]);
        $nurse->assignRole('NURSE');
        Sanctum::actingAs($nurse);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $cats = $this->getJson('/api/mobile/nursing/context', $ajax)->assertOk()->json('categories');
        $this->assertSame(['lab', 'imaging', 'procedure'], array_keys($cats));
        $this->assertGreaterThan(0, $cats['lab']);

        $rows = $this->getJson('/api/mobile/nursing/search-services?term=c&category_id=' . $cats['lab'], $ajax)->assertOk()->json();
        if (empty($rows)) {
            $this->markTestSkipped('No lab services in the test database.');
        }
        $this->assertSame(['Investigation'], array_values(array_unique(array_column($rows, 'category'))), 'Only lab services come back when the lab category is asked for.');
    }
}
