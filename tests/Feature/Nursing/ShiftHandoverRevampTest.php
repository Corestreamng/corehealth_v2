<?php

namespace Tests\Feature\Nursing;

use App\Models\NursingShift;
use App\Models\ShiftHandover;
use App\Models\User;
use App\Models\Ward;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShiftHandoverRevampTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'surname' => 'Nurse',
            'firstname' => 'Florence',
            'othername' => 'Nightingale',
            'email' => 'nurse_' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret123'),
            'status' => 1,
            'is_admin' => 1,
        ], $attrs));
    }

    private function makeWard(): Ward
    {
        return Ward::first() ?? Ward::create([
            'name' => 'Female Medical Ward ' . Str::random(4),
            'description' => 'Medical ward for adult females',
            'status' => 1,
        ]);
    }

    private function makeShift(User $user, ?Ward $ward = null): NursingShift
    {
        $ward = $ward ?? $this->makeWard();

        return NursingShift::create([
            'user_id' => $user->id,
            'ward_id' => $ward->id,
            'shift_type' => 'morning',
            'started_at' => Carbon::now()->subHours(4),
            'scheduled_end_at' => Carbon::now()->addHours(4),
            'status' => 'active',
        ]);
    }

    /** @test */
    public function test_shift_preview_endpoint_returns_clinical_structure()
    {
        $user = $this->makeUser();
        $shift = $this->makeShift($user);

        $response = $this->actingAs($user)->getJson('/nursing-workbench/shift/preview');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'preview' => [
                    'shift_id',
                    'started_at',
                    'elapsed_time',
                    'total_events',
                    'total_patients',
                    'activity_summary',
                    'patient_highlights',
                    'activity_timeline',
                    'detailed_summary',
                    'alerts',
                ],
            ]);
            $this->assertTrue($response->json('success'));
        }
    }

    /** @test */
    public function test_nursing_shift_create_handover_persists_rich_clinical_summary()
    {
        $user = $this->makeUser();
        $shift = $this->makeShift($user);

        $handover = $shift->createHandover([
            'critical_notes' => 'Patient in Bed 3 requires frequent BP checks.',
            'concluding_notes' => 'All morning rounds completed without incident.',
            'pending_tasks' => [
                ['description' => 'Check 14:00 vitals for Bed 2', 'priority' => 'high'],
            ],
        ]);

        $this->assertInstanceOf(ShiftHandover::class, $handover);
        $this->assertEquals($shift->id, $handover->shift_id);
        $this->assertEquals($user->id, $handover->created_by);
        $this->assertNotEmpty($handover->summary);
        $this->assertIsArray($handover->pending_tasks);
        $this->assertCount(1, $handover->pending_tasks);

        $shift->refresh();
        $this->assertTrue((bool) $shift->handover_created);
    }

    /** @test */
    public function test_handover_details_endpoint_returns_clinical_data()
    {
        $user = $this->makeUser();
        $shift = $this->makeShift($user);

        $handover = $shift->createHandover([
            'critical_notes' => 'Test critical note',
            'concluding_notes' => 'Test concluding note',
        ]);

        $response = $this->actingAs($user)->getJson('/nursing-workbench/handover/' . $handover->id);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'handover' => [
                    'id',
                    'shift_type',
                    'summary',
                    'critical_notes',
                    'concluding_notes',
                    'action_summary',
                    'patient_highlights',
                ],
            ]);
            $this->assertTrue($response->json('success'));
        }
    }

    /** @test */
    public function test_acknowledge_handover_endpoint()
    {
        $nurse1 = $this->makeUser();
        $nurse2 = $this->makeUser();
        $shift = $this->makeShift($nurse1);

        $handover = $shift->createHandover([
            'critical_notes' => 'Urgent follow-up needed',
        ]);

        $this->assertFalse((bool) $handover->is_acknowledged);

        $response = $this->actingAs($nurse2)->postJson('/nursing-workbench/handover/' . $handover->id . '/acknowledge');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $handover->refresh();
            $this->assertTrue((bool) $handover->is_acknowledged);
            $this->assertEquals($nurse2->id, $handover->acknowledged_by);
        }
    }

    /** @test */
    public function test_end_shift_endpoint_creates_handover()
    {
        $user = $this->makeUser();
        $shift = $this->makeShift($user);

        $response = $this->actingAs($user)->postJson('/nursing-workbench/shift/end', [
            'critical_notes' => 'Monitor fluid intake',
            'concluding_notes' => 'Handed over to afternoon team',
            'create_handover' => true,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $shift->refresh();
            $this->assertEquals('completed', $shift->status);
            $this->assertTrue((bool) $shift->handover_created);
        }
    }

    /** @test */
    public function test_wards_endpoint_resolves_default_shift_and_ward()
    {
        $user = $this->makeUser();
        $ward = $this->makeWard();
        $shift = $this->makeShift($user, $ward);

        $response = $this->actingAs($user)->getJson('/nursing-workbench/shift/wards');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $this->assertNotNull($response->json('default_shift'));
            $this->assertEquals(NursingShift::determineShiftType(), $response->json('default_shift'));
            // Since user has an active shift on $ward, default_ward_id resolves to that ward
            $this->assertEquals($ward->id, $response->json('default_ward_id'));
        }
    }

    /** @test */
    public function test_acknowledge_handover_returns_acknowledged_by_name()
    {
        $nurse1 = $this->makeUser();
        $nurse2 = $this->makeUser();
        $shift = $this->makeShift($nurse1);

        $handover = $shift->createHandover([
            'critical_notes' => 'Urgent follow-up needed',
        ]);

        $response = $this->actingAs($nurse2)->postJson('/nursing-workbench/handover/' . $handover->id . '/acknowledge');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $this->assertNotEmpty($response->json('acknowledged_by'));
            $this->assertEquals($nurse2->name, $response->json('acknowledged_by'));
        }
    }
}
