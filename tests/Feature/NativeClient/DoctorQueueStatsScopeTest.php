<?php

namespace Tests\Feature\NativeClient;

use App\Enums\QueueStatus;
use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The doctor queue badges count the same rows as the list under them: with a start and end date the
 * stats follow that range (as the web stats bar does), instead of counting every day.
 */
class DoctorQueueStatsScopeTest extends TestCase
{
    public function test_stats_follow_the_date_range_of_the_list()
    {
        if (!Role::where('name', 'DOCTOR')->exists() || !($patientId = Patient::query()->value('id'))) {
            $this->markTestSkipped('Needs the DOCTOR role and one patient.');
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole('DOCTOR');
        $doc = Staff::where('user_id', $user->id)->first() ?? Staff::create(['user_id' => $user->id, 'status' => 1]);
        Sanctum::actingAs($user);

        $old = DoctorQueue::create([
            'patient_id' => $patientId,
            'clinic_id' => Clinic::query()->value('id'),
            'staff_id' => $doc->id,
            'receptionist_id' => $user->id,
            'request_entry_id' => 0,
            'status' => QueueStatus::IN_CONSULTATION,
        ]);
        $old->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $all = $this->getJson('/api/mobile/doctor/queues/stats');
        $all->assertOk();
        $this->assertSame(1, $all->json('data.in_consult'), 'No dates: every day is counted (older app builds).');

        $range = $this->getJson('/api/mobile/doctor/queues/stats?start_date=' . now()->toDateString() . '&end_date=' . now()->addDays(30)->toDateString());
        $range->assertOk();
        $this->assertSame(0, $range->json('data.in_consult'), 'A consult outside the chosen dates is not counted.');
    }
}
