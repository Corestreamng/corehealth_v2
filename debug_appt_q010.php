<?php
/**
 * Debug: Why Q-010 (Apollos Baby 1 / #000001) doesn't appear
 *        in the reception calendar or doctor (user_id=1) encounter index.
 *
 * Run: php debug_appt_q010.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Staff;
use App\Models\DoctorAppointment;
use App\Models\DoctorQueue;
use App\Models\Clinic;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$sep  = str_repeat('─', 72);
$sep2 = str_repeat('═', 72);

echo "\n$sep2\n  DEBUG: Appointment Q-010 / Apollos Baby 1 (#000001)\n$sep2\n\n";

// ── 1. PATIENT ────────────────────────────────────────────────────────
echo "1. PATIENT LOOKUP\n$sep\n";
$patient = Patient::where('file_no', '000001')->orWhere('file_no', '#000001')->first();
if (!$patient) {
    // try by name fragment
    $user = DB::table('users')
        ->where('firstname', 'like', '%Apollos%')
        ->orWhere('surname', 'like', '%Apollos%')
        ->first();
    $patient = $user ? Patient::where('user_id', $user->id)->first() : null;
}
if (!$patient) {
    echo "  ❌  Patient #000001 / 'Apollos Baby 1' NOT FOUND in patients table.\n\n";
} else {
    echo "  ✅  Patient found  id={$patient->id}  file_no={$patient->file_no}  user_id={$patient->user_id}\n\n";
}

// ── 2. DOCTOR (user_id=1) ─────────────────────────────────────────────
echo "2. DOCTOR STAFF PROFILE  (user_id=1)\n$sep\n";
$doc = Staff::where('user_id', 1)->first();
if (!$doc) {
    echo "  ❌  No Staff profile found for user_id=1.\n\n";
} else {
    $allIds  = $doc->all_clinic_ids;
    $canSee  = json_encode($doc->can_see_clinic_queues ?? []);
    $clinic  = Clinic::find($doc->clinic_id)?->name ?? 'null';
    echo "  Staff id          : {$doc->id}\n";
    echo "  Primary clinic_id : {$doc->clinic_id} ({$clinic})\n";
    echo "  can_see_clinic_queues (raw) : {$canSee}\n";
    echo "  all_clinic_ids accessor     : " . json_encode($allIds) . "\n";
    // Find ENT clinic
    $ent = Clinic::where('name', 'like', '%Ear%')->orWhere('name', 'like', '%ENT%')->first();
    if ($ent) {
        $inArray = in_array($ent->id, $allIds);
        echo "  ENT Clinic        : id={$ent->id} name='{$ent->name}'\n";
        echo "  ENT in all_clinic_ids? " . ($inArray ? "✅ YES" : "❌ NO") . "\n";
    } else {
        echo "  ⚠️  No ENT/Ear clinic found in clinics table.\n";
    }
    echo "\n";
}

// ── 3. DOCTOR QUEUES – find Q-010 (10th queue for patient) ──────────
echo "3. DOCTOR QUEUE ENTRIES for Patient id=" . ($patient?->id ?? '?') . "\n$sep\n";
$queues = $patient
    ? DoctorQueue::where('patient_id', $patient->id)->orderByDesc('id')->get()
    : collect();
if ($queues->isEmpty()) {
    echo "  ❌  No DoctorQueue rows found for this patient.\n\n";
    $queue = null;
} else {
    foreach ($queues as $i => $q) {
        $qClinic  = Clinic::find($q->clinic_id)?->name ?? 'null';
        $allIds   = $doc ? $doc->all_clinic_ids : [];
        $visib    = $doc && (in_array($q->clinic_id, $allIds) || $q->staff_id == $doc->id);
        $qNum     = 'Q-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
        echo "  [{$qNum} approx]  Queue id={$q->id}  status={$q->status}\n";
        echo "    staff_id    : {$q->staff_id}  (doc->id=" . ($doc?->id ?? 'N/A') . ")  match=" . ($doc && $q->staff_id == $doc->id ? '✅' : '❌') . "\n";
        echo "    clinic_id   : {$q->clinic_id} ({$qClinic})  in allIds=" . (in_array($q->clinic_id, $allIds) ? '✅ YES' : '❌ NO') . "\n";
        echo "    created_at  : {$q->created_at}\n";
        echo "    Visible to doc? " . ($visib ? "✅ YES" : "❌ NO") . "\n\n";
    }
    $queue = $queues->first();
}

// ── 4. APPOINTMENT RECORD ─────────────────────────────────────────────
echo "4. DOCTOR APPOINTMENT RECORDS for Patient id=" . ($patient?->id ?? '?') . "\n$sep\n";
$appts = $patient
    ? DoctorAppointment::where('patient_id', $patient->id)->orderByDesc('id')->get()
    : collect();

if ($appts->isEmpty()) {
    echo "  ❌  No DoctorAppointment rows found for this patient.\n\n";
} else {
    foreach ($appts as $appt) {
        $aClinic     = Clinic::find($appt->clinic_id)?->name ?? 'null';
        $allIds      = $doc ? $doc->all_clinic_ids : [];
        $staffMatch  = $doc && $appt->staff_id == $doc->id;
        $clinicMatch = in_array($appt->clinic_id, $allIds);
        $visible     = $staffMatch || $clinicMatch;
        echo "  Appointment id      : {$appt->id}\n";
        echo "  staff_id            : {$appt->staff_id}  (doc->id=" . ($doc?->id ?? 'N/A') . ")  match=" . ($staffMatch  ? '✅' : '❌') . "\n";
        echo "  clinic_id           : {$appt->clinic_id} ({$aClinic})  in allIds=" . ($clinicMatch ? '✅ YES' : '❌ NO — not in ' . json_encode($allIds)) . "\n";
        echo "  appointment_date    : {$appt->appointment_date}\n";
        echo "  start_time          : {$appt->start_time}\n";
        echo "  status              : {$appt->status}\n";
        echo "  doctor_queue_id     : {$appt->doctor_queue_id}\n";
        echo "  Calendar visible?   : " . ($visible ? "✅ YES — will show" : "❌ NO — neither staff_id nor clinic_id match") . "\n\n";
    }
}

// ── 5. RECEPTION CALENDAR – simulate getDoctorsByClinic ──────────────
echo "5. RECEPTION WORKBENCH — getDoctorsByClinic simulation\n$sep\n";
if ($queue || ($appts->isNotEmpty())) {
    $targetClinicId = $queue?->clinic_id ?? $appts->first()?->clinic_id;
    $targetClinic   = Clinic::find($targetClinicId)?->name ?? 'unknown';
    echo "  Target clinic: id={$targetClinicId} name='{$targetClinic}'\n";
    $doctors = Staff::with('user')
        ->where(function ($sq) use ($targetClinicId) {
            $sq->where('clinic_id', $targetClinicId)
              ->orWhereJsonContains('can_see_clinic_queues', (int)$targetClinicId);
        })
        ->whereHas('user')
        ->get();
    if ($doctors->isEmpty()) {
        echo "  ❌  No doctors returned for this clinic_id — reception dropdown will be empty!\n";
    } else {
        echo "  Doctors returned (" . $doctors->count() . "):\n";
        foreach ($doctors as $d) {
            $isPrimary = (int)$d->clinic_id === (int)$targetClinicId;
            $tag  = $isPrimary ? '[primary]' : '[can_see]';
            $name = $d->user ? ($d->user->firstname . ' ' . $d->user->surname) : 'N/A';
            echo "    - Staff id={$d->id}  user_id={$d->user_id}  {$tag}  name={$name}\n";
        }
    }
} else {
    echo "  ⚠️  Skipped — no queue or appointment found to derive clinic.\n";
}
echo "\n";

// ── 6. RAW DB CHECK ───────────────────────────────────────────────────
echo "6. RAW DB — can_see_clinic_queues column value for staff user_id=1\n$sep\n";
$raw = DB::table('staff')->where('user_id', 1)->value('can_see_clinic_queues');
echo "  Raw DB value: " . var_export($raw, true) . "\n";
$decoded = json_decode($raw, true);
echo "  JSON decoded: " . var_export($decoded, true) . "\n";
echo "  Types in array: " . implode(', ', array_map('gettype', (array)$decoded)) . "\n\n";

// ── 7. ENCOUNTER INDEX QUERY SIMULATION ──────────────────────────────
echo "7. ENCOUNTER INDEX — NewEncounterList query simulation (as user_id=1)\n$sep\n";
if ($doc) {
    $encountered = DoctorQueue::where(function ($q) use ($doc) {
            $q->whereIn('clinic_id', $doc->all_clinic_ids)
              ->orWhere('staff_id', $doc->id);
        })
        ->where('status', \App\Enums\QueueStatus::WAITING)
        ->get();

    echo "  Query: whereIn(clinic_id, " . json_encode($doc->all_clinic_ids) . ") OR staff_id={$doc->id}\n";
    echo "         AND status=WAITING(1)\n";
    echo "  Rows returned: " . $encountered->count() . "\n";
    foreach ($encountered as $e) {
        $eCl = Clinic::find($e->clinic_id)?->name ?? '?';
        echo "    → Queue id={$e->id}  clinic_id={$e->clinic_id}({$eCl})  staff_id={$e->staff_id}  status={$e->status}  created={$e->created_at}\n";
    }
    echo "\n";
} else {
    echo "  ⚠️  Skipped — no staff profile for user_id=1.\n\n";
}

// ── 8. RECEPTION CALENDAR — why the walk-in won't appear ─────────────
echo "8. RECEPTION WORKBENCH CALENDAR — getCalendarEvents analysis\n$sep\n";
echo "  getCalendarEvents() queries DoctorAppointment ONLY by default.\n";
echo "  Queue walk-ins only included if request has include_queue=true.\n\n";
if ($patient) {
    $apptCount = DoctorAppointment::where('patient_id', $patient->id)->count();
    echo "  DoctorAppointment rows for patient_id={$patient->id}: {$apptCount}\n";
    if ($apptCount === 0) {
        echo "  ❌  ROOT CAUSE: No DoctorAppointment record exists for this patient.\n";
        echo "      This is a walk-in queue entry only.\n";
        echo "      It will NEVER appear in the reception calendar unless:\n";
        echo "        a) include_queue=true is sent with the calendar request, AND\n";
        echo "        b) No doctor_id filter is applied (queue has staff_id=null).\n";
    }
}
echo "\n";

echo "$sep2\n  END OF DEBUG\n$sep2\n\n";
