<?php

namespace Tests\Feature\NativeClient;

use App\Models\Patient;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every mobile wrapper added for reception and doctor parity answers with JSON from the web
 * controller (no 500s hidden behind the status assertion), and keeps the sidebar guards.
 */
class NativeParityEndpointsTest extends TestCase
{
    protected function staff(string $role): User
    {
        if (!Role::where('name', $role)->exists()) {
            $this->markTestSkipped("{$role} role missing.");
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole($role);

        return $user;
    }

    protected function anyPatientId(): int
    {
        $id = Patient::query()->value('id');
        if (!$id) {
            $this->markTestSkipped('No patient in the test database.');
        }

        return (int) $id;
    }

    /** Asserts a JSON answer that isn't a server error. */
    protected function assertJsonOk(string $uri, array $headers = []): void
    {
        $resp = $this->getJson($uri, $headers);
        $this->assertContains($resp->status(), [200, 422], "{$uri} returned {$resp->status()}: " . substr($resp->getContent(), 0, 300));
        $this->assertIsArray(json_decode($resp->getContent(), true), "{$uri} did not return JSON");
    }

    /** @test */
    public function test_reception_parity_endpoints_return_json()
    {
        Sanctum::actingAs($this->staff('RECEPTIONIST'));
        $pid = $this->anyPatientId();
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        foreach ([
            '/api/mobile/reception/context',
            '/api/mobile/reception/services/lab?q=a',
            '/api/mobile/reception/services/imaging?q=a',
            '/api/mobile/reception/services/procedure?q=a',
            '/api/mobile/reception/products?q=a',
            "/api/mobile/reception/patient/{$pid}/recent-requests",
            "/api/mobile/reception/patient/{$pid}/service-requests",
            "/api/mobile/reception/patient/{$pid}/service-requests-stats",
            '/api/mobile/reception/hmo-pending-validation',
            '/api/mobile/reception/hmo-pending-count',
            '/api/mobile/reception/referrals/pending',
            '/api/mobile/reception/referrals/pending-count',
            '/api/mobile/reception/banks/active',
            "/api/mobile/reception/patient/{$pid}/account-summary",
            "/api/mobile/shared/patient/{$pid}/admissions",
            '/api/mobile/shared/emergency/clinics',
            '/api/mobile/shared/emergency/services',
            '/api/mobile/shared/emergency/available-beds',
            '/api/mobile/shared/ward-availability',
            "/api/mobile/shared/patient/{$pid}/alerts",
            '/api/mobile/shared/appointments/list?date=' . now()->toDateString(),
        ] as $uri) {
            $this->assertJsonOk($uri);
        }
        $this->assertJsonOk('/api/mobile/reception/my-transactions?from=' . now()->toDateString() . '&to=' . now()->toDateString(), $ajax);
        $this->assertJsonOk('/api/mobile/shared/hospital-contacts?draw=1&start=0&length=10', $ajax);
        $this->assertJsonOk('/api/mobile/shared/price-list/services?draw=1&start=0&length=10', $ajax);
        $this->assertJsonOk('/api/mobile/shared/price-list/products?draw=1&start=0&length=10', $ajax);

        $ctx = $this->getJson('/api/mobile/reception/context')->json();
        $this->assertArrayHasKey('can_bill', $ctx);
        $this->assertArrayHasKey('registration_services', $ctx);
    }

    /** @test */
    public function test_doctor_parity_endpoints_return_json()
    {
        Sanctum::actingAs($this->staff('DOCTOR'));
        $pid = $this->anyPatientId();

        foreach ([
            '/api/mobile/doctor/queue/calendar-events?start=' . now()->startOfWeek()->toDateString() . '&end=' . now()->endOfWeek()->toDateString(),
            '/api/mobile/doctor/diagnosis-favorites',
            "/api/mobile/doctor/patient/{$pid}/treatment-plans",
            "/api/mobile/doctor/non-pharm-orders/patient/{$pid}",
            "/api/mobile/doctor/result-views/unviewed-counts/{$pid}",
            "/api/mobile/doctor/patient/{$pid}/profiles",
            '/api/mobile/shared/ward-availability',
            "/api/mobile/shared/patient/{$pid}/alerts",
        ] as $uri) {
            $this->assertJsonOk($uri);
        }
    }

    /** @test */
    public function test_new_groups_keep_the_sidebar_guards()
    {
        Sanctum::actingAs($this->staff('DOCTOR'));
        $this->assertEquals(403, $this->getJson('/api/mobile/reception/hmo-pending-count')->status());

        Sanctum::actingAs($this->staff('RECEPTIONIST'));
        $this->assertEquals(403, $this->getJson('/api/mobile/doctor/diagnosis-favorites')->status());
        $this->assertEquals(200, $this->getJson('/api/mobile/shared/ward-availability')->status());
    }

    /** @test */
    public function test_payment_wrapper_applies_the_web_billing_rule()
    {
        if (!Role::where('name', 'RECEPTIONIST')->exists()) {
            $this->markTestSkipped('RECEPTIONIST role missing.');
        }
        $user = $this->staff('RECEPTIONIST');
        Sanctum::actingAs($user);
        $canBill = \App\Http\Controllers\ReceptionWorkbenchController::userCanBill($user);
        $resp = $this->postJson('/api/mobile/reception/process-payment', ['patient_id' => $this->anyPatientId(), 'payment_type' => 'CASH', 'items' => []]);
        // Without billing rights: refused before reaching billing. With them: billing's own validation (422).
        $this->assertEquals($canBill ? 422 : 403, $resp->status(), substr($resp->getContent(), 0, 300));
    }

    /** @test */
    public function test_more_doctor_parity_endpoints_return_json()
    {
        Sanctum::actingAs($this->staff('DOCTOR'));
        $pid = $this->anyPatientId();
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        foreach ([
            '/api/mobile/doctor/treatment-plans',
            "/api/mobile/doctor/patient/{$pid}/medical-reports",
            "/api/mobile/doctor/patient/{$pid}/report-data",
            "/api/mobile/shared/patient/{$pid}/admissions",
            '/api/mobile/shared/search-patients?q=ab',
            '/api/mobile/shared/hmos',
            '/api/mobile/shared/registration-context',
            '/api/mobile/shared/patient/next-file-number?workbench=doctor&prefix=DOC-',
        ] as $uri) {
            $this->assertJsonOk($uri);
        }
        $this->assertJsonOk("/api/mobile/doctor/patient/{$pid}/investigation-actions?draw=1&start=0&length=5", $ajax);
        $this->assertJsonOk("/api/mobile/doctor/patient/{$pid}/imaging-actions?draw=1&start=0&length=5", $ajax);
    }

    /** @test */
    public function test_staff_settings_follow_hospital_config()
    {
        Sanctum::actingAs($this->staff('DOCTOR'));
        $resp = $this->getJson('/api/mobile/staff/settings');
        $this->assertEquals(200, $resp->status());
        $data = $resp->json('data');
        $this->assertSame(\App\Support\ClientSettings::workbench()['default_dose_mode'], $data['default_dose_mode']);
        foreach (['enable_structured_dose', 'default_dose_mode', 'require_diagnosis', 'note_edit_window', 'llm_enabled', 'enable_ei_doctor', 'allow_doctor_set_procedure_price'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        // Structured dosing off forces simple mode, exactly as the web encounter page does.
        $structuredOn = (bool) (appsettings('enable_structured_dose') ?? 1);
        $this->assertSame($structuredOn ? (appsettings('default_dose_mode') ?? 'structured') : 'simple', $data['default_dose_mode']);

        $this->assertArrayHasKey('settings', $this->getJson('/api/mobile/staff/workspaces')->json('data'));
    }

    /** @test */
    public function test_represcribe_from_past_encounter_lists_items()
    {
        $encounters = \App\Models\Encounter::query()->latest('id')->take(2)->get();
        if ($encounters->count() < 2) {
            $this->markTestSkipped('Needs two encounters.');
        }
        Sanctum::actingAs($this->staff('DOCTOR'));
        [$current, $source] = [$encounters[0], $encounters[1]];
        $resp = $this->getJson("/api/mobile/doctor/encounters/{$current->id}/encounter-items/{$source->id}");
        $this->assertEquals(200, $resp->status(), substr($resp->getContent(), 0, 300));
        foreach (['labs', 'imaging', 'prescriptions', 'procedures'] as $k) {
            $this->assertArrayHasKey($k, $resp->json('items'));
        }
    }

    /** @test */
    public function test_encounter_detail_loads_when_the_encounter_has_labs_and_imaging()
    {
        $lab = \App\Models\LabServiceRequest::whereNotNull('encounter_id')->where('status', '>', 0)->latest('id')->first();
        if (!$lab) {
            $this->markTestSkipped('No encounter with labs in the test database.');
        }
        Sanctum::actingAs($this->staff('DOCTOR'));
        $resp = $this->getJson("/api/mobile/doctor/encounters/{$lab->encounter_id}");
        $this->assertEquals(200, $resp->status(), substr($resp->getContent(), 0, 300));
        $this->assertNotEmpty($resp->json('data.labs'));
    }

    /** @test */
    public function test_datatables_searches_work_with_the_columns_the_app_sends()
    {
        Sanctum::actingAs($this->staff('DOCTOR'));
        $cols = [
            'columns[0][data]' => 'name', 'columns[0][name]' => 'name', 'columns[0][searchable]' => 'true', 'columns[0][orderable]' => 'true',
            'columns[1][data]' => 'action', 'columns[1][name]' => 'action', 'columns[1][searchable]' => 'false', 'columns[1][orderable]' => 'false',
        ];
        $resp = $this->getJson('/api/mobile/shared/hospital-contacts?' . http_build_query(['draw' => 1, 'start' => 0, 'length' => 5, 'search' => ['value' => 'a']] + $cols), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertEquals(200, $resp->status(), substr($resp->getContent(), 0, 300));
        $this->assertArrayHasKey('data', $resp->json());
    }

    /** @test */
    public function test_starting_an_encounter_links_the_queue_and_puts_it_in_consultation()
    {
        $queue = \App\Models\DoctorQueue::where('status', \App\Enums\QueueStatus::WAITING)->latest('id')->first();
        if (!$queue) {
            $this->markTestSkipped('No waiting queue entry in the test database.');
        }
        $doctor = $this->staff('DOCTOR');
        Sanctum::actingAs($doctor);
        $resp = $this->postJson('/api/mobile/doctor/encounters/start', ['patient_id' => $queue->patient_id, 'queue_id' => $queue->id, 'req_entry_id' => $queue->request_entry_id]);
        $this->assertEquals(200, $resp->status(), substr($resp->getContent(), 0, 300));
        $encId = $resp->json('data.encounter.id');
        $this->assertEquals($queue->id, \App\Models\Encounter::find($encId)->queue_id);
        $this->assertEquals(\App\Enums\QueueStatus::IN_CONSULTATION, $queue->fresh()->status);
    }

    /** @test */
    public function test_billing_desk_endpoints_return_json_for_billers()
    {
        Sanctum::actingAs($this->staff('BILLER'));
        $pid = $this->anyPatientId();
        foreach (['active-shift', 'queue-counts', 'payment-queue?filter=all', 'search-patients?q=an', 'my-transactions', 'banks/active',
            "patient/{$pid}/billing-data", "patient/{$pid}/billing-defaults", "patient/{$pid}/receipts", "patient/{$pid}/account-summary",
            "patient/{$pid}/account-transactions", "patient/{$pid}/admissions", 'context'] as $path) {
            $this->assertJsonOk('/api/mobile/billing/' . $path);
        }
    }

    /** @test */
    public function test_billing_desk_refuses_roles_outside_billing()
    {
        $other = $this->staff('DOCTOR');
        if ($other->can('billing-workbench') || $other->hasRole('BILLER')) {
            $this->markTestSkipped('Doctor role has billing access in this database.');
        }
        Sanctum::actingAs($other);
        $this->getJson('/api/mobile/billing/queue-counts')->assertStatus(403);
        $this->postJson('/api/mobile/billing/process-payment', [])->assertStatus(403);
    }

    /** @test */
    public function test_nursing_desk_endpoints_return_json_for_nurses()
    {
        Sanctum::actingAs($this->staff('NURSE'));
        $pid = $this->anyPatientId();
        foreach (['context', 'queue-counts', 'queue/vitals', 'queue/medication-due', 'queue/bed-requests', 'queue/discharge-requests',
            'admitted-patients', 'deceased-queue', 'wards', 'note-types', 'shift/check', 'shift/wards', 'shift/pending-handovers',
            'handovers?format=cards', 'schedule-templates', 'ward-dashboard/stats', 'ward-dashboard/wards', 'ward-dashboard/available-beds',
            'ward-dashboard/admission-queue', 'outreach-tallies', "patient/{$pid}/details", "patient/{$pid}/vitals", "patient/{$pid}/schedule",
            "patient/{$pid}/immunization-history", "patient/{$pid}/pending-bills", "patient/{$pid}/service-requests-stats"] as $path) {
            $this->assertJsonOk('/api/mobile/nursing/' . $path);
        }
    }

    /** @test */
    public function test_shift_summary_uses_real_vital_columns()
    {
        Sanctum::actingAs($this->staff('NURSE'));
        $resp = $this->getJson('/api/mobile/nursing/shift-summary');
        $this->assertSame(200, $resp->status(), substr($resp->getContent(), 0, 300));
        $this->assertArrayHasKey('vitals_abnormal', $resp->json());
    }

    /** @test */
    public function test_charting_routes_are_shared_by_doctor_nursing_and_maternity_only()
    {
        $pid = $this->anyPatientId();
        foreach (['DOCTOR', 'NURSE', 'MATERNITY'] as $role) {
            Sanctum::actingAs($this->staff($role));
            $this->assertJsonOk("/api/mobile/doctor/patient/{$pid}/vitals");
            $this->assertJsonOk("/api/mobile/doctor/patient/{$pid}/intake-output");
        }
        Sanctum::actingAs($this->staff('BILLER'));
        $this->getJson("/api/mobile/doctor/patient/{$pid}/vitals")->assertStatus(403);
        // A nurse gets the charting routes but not the rest of the doctor workspace.
        Sanctum::actingAs($this->staff('NURSE'));
        $this->getJson('/api/mobile/doctor/queues')->assertStatus(403);
    }

    /** @test */
    public function test_nursing_desk_refuses_roles_outside_nursing()
    {
        Sanctum::actingAs($this->staff('BILLER'));
        $this->getJson('/api/mobile/nursing/queue-counts')->assertStatus(403);
        $this->postJson('/api/mobile/nursing/shift/start', [])->assertStatus(403);
    }
}
