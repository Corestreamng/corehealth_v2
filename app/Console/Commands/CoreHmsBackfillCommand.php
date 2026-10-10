<?php

namespace App\Console\Commands;

use App\Jobs\DispatchCoreHmsSyncJob;
use App\Models\AdmissionRequest;
use App\Models\Encounter;
use App\Models\LabServiceRequest;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ProductRequest;
use App\Models\Staff;
use App\Services\CoreHms\Events\AdmissionSyncEvent;
use App\Services\CoreHms\Events\ConsultationSyncEvent;
use App\Services\CoreHms\Events\DispenseSyncEvent;
use App\Services\CoreHms\Events\LabSyncEvent;
use App\Services\CoreHms\Events\PatientSyncEvent;
use App\Services\CoreHms\Events\ProcedureSyncEvent;
use App\Services\CoreHms\Events\StaffSyncEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CoreHmsBackfillCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corehms:backfill 
        {--type=all : Subsystem to backfill (all, patients, encounters, staff, pharmacy, lab, procedures, admissions)}
        {--days=30 : Number of days of historical records}
        {--start= : Start date (YYYY-MM-DD)}
        {--end= : End date (YYYY-MM-DD)}
        {--batch=50 : Batch size for HTTP batch payloads}
        {--queue : Dispatch to database queue instead of running synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retroactively backfill historical clinical and administrative data to CoreHMS SuperAdmin with strict deduplication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== CoreHMS Historical Data Backfill Tool ===");

        $facilityId = (int)appsettings('client_id', 4);
        $superAdminUrl = rtrim(appsettings('COREHMS_SUPERADMIN_URL', ''), '/');
        $username = appsettings('COREHMS_SUPERADMIN_USERNAME');
        $password = appsettings('COREHMS_SUPERADMIN_PASS');

        $this->line("Facility ID: <info>{$facilityId}</info>");
        $this->line("SuperAdmin URL: <info>{$superAdminUrl}</info>");

        if (empty($superAdminUrl) || empty($username) || empty($password)) {
            $this->error("SuperAdmin credentials are missing from appsettings. Please configure COREHMS_SUPERADMIN_URL, USERNAME, and PASS.");

            return Command::FAILURE;
        }

        // Determine date range
        if ($this->option('start')) {
            $startDate = Carbon::parse($this->option('start'))->startOfDay();
            $endDate = $this->option('end') ? Carbon::parse($this->option('end'))->endOfDay() : Carbon::now()->endOfDay();
        } else {
            $days = (int)$this->option('days');
            $startDate = Carbon::now()->subDays($days)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        }

        $this->line("Date Range: <info>{$startDate->toDateTimeString()}</info> to <info>{$endDate->toDateTimeString()}</info>");

        $type = strtolower($this->option('type'));
        $useQueue = $this->option('queue');
        $batchSize = (int)$this->option('batch') ?: 50;

        $typesToProcess = ($type === 'all')
            ? ['patients', 'staff', 'encounters', 'pharmacy', 'lab', 'procedures', 'admissions']
            : [$type];

        $grandTotal = 0;
        $grandProcessed = 0;
        $grandDuplicates = 0;
        $grandErrors = 0;

        foreach ($typesToProcess as $currentType) {
            $this->newLine();
            $this->info("Gathering historical records for: [{$currentType}]...");

            $events = $this->gatherEvents($currentType, $startDate, $endDate, $facilityId);
            $count = count($events);
            $this->line("Found <comment>{$count}</comment> records.");

            if ($count === 0) {
                continue;
            }

            $grandTotal += $count;

            if ($useQueue) {
                $bar = $this->output->createProgressBar($count);
                $bar->start();

                foreach ($events as $event) {
                    DispatchCoreHmsSyncJob::dispatch($event);
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
                $this->info("Queued {$count} {$currentType} events for background synchronization via database queue.");
                $grandProcessed += $count;
            } else {
                // Synchronous batch execution
                $chunks = array_chunk($events, $batchSize);
                $bar = $this->output->createProgressBar(count($chunks));
                $bar->start();

                foreach ($chunks as $chunk) {
                    $batchPayload = [];
                    foreach ($chunk as $ev) {
                        $batchPayload[] = array_merge($ev->toPayload(), [
                            'facility_id' => $ev->getFacilityId(),
                            'source_id' => $ev->getSourceId(),
                            'event_key' => $ev->getEventKey(),
                            'event_date' => $ev->getEventDate(),
                            'type' => $ev->getEventType(),
                        ]);
                    }

                    try {
                        $response = Http::withBasicAuth($username, $password)
                            ->timeout(30)
                            ->post($superAdminUrl . '/event-notification.php?action=batch&facility_id=' . $facilityId, $batchPayload);

                        if ($response->successful()) {
                            $resData = $response->json();
                            $grandProcessed += (int)($resData['processed'] ?? 0);
                            $grandDuplicates += (int)($resData['duplicates'] ?? 0);
                            $grandErrors += (int)($resData['errors'] ?? 0);
                        } else {
                            $grandErrors += count($chunk);
                        }
                    } catch (\Throwable $e) {
                        $grandErrors += count($chunk);
                    }

                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
            }
        }

        $this->newLine();
        $this->info("=== Backfill Complete ===");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Records Found', $grandTotal],
                [$useQueue ? 'Jobs Queued' : 'Processed (Fresh)', $grandProcessed],
                ['Duplicates (Deduplicated)', $grandDuplicates],
                ['Errors / Failed', $grandErrors],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Gather typed DTO events for the requested subsystem within the date range.
     */
    protected function gatherEvents(string $type, Carbon $startDate, Carbon $endDate, int $facilityId): array
    {
        $events = [];

        switch ($type) {
            case 'patients':
                $patients = Patient::with('user', 'hmo')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                foreach ($patients as $p) {
                    $user = $p->user;
                    $name = $user ? trim($user->surname . ' ' . $user->firstname . ' ' . ($user->othername ?? '')) : 'Patient #' . $p->id;
                    $events[] = new PatientSyncEvent(
                        $p->id,
                        $name,
                        $user?->gender ?? $p->gender,
                        $p->dob,
                        $p->phone_no,
                        $p->file_no,
                        $p->hmo_id,
                        $p->hmo?->name,
                        'Active',
                        $facilityId,
                        $p->created_at?->toDateTimeString()
                    );
                }

                break;

            case 'staff':
                $staffList = Staff::with('user')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                foreach ($staffList as $s) {
                    $user = $s->user;
                    $name = $user ? trim($user->surname . ' ' . $user->firstname . ' ' . ($user->othername ?? '')) : 'Staff #' . $s->id;
                    $staffType = ($user?->is_admin == 21) ? 'Doctors' : (($user?->is_admin == 22) ? 'Nurse' : 'Staffs');
                    $events[] = new StaffSyncEvent(
                        $s->id,
                        $name,
                        $staffType,
                        $s->specialization,
                        $user?->gender,
                        $user?->email,
                        $s->phone_no,
                        'Active',
                        $facilityId,
                        $s->created_at?->toDateTimeString()
                    );
                }

                break;

            case 'encounters':
                $encounters = Encounter::with('clinic')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                foreach ($encounters as $e) {
                    $clinic = $e->clinic?->name ?? 'General Clinic';
                    $chiefComplaint = is_array($e->reasons_for_encounter) ? ($e->reasons_for_encounter[0] ?? null) : $e->reasons_for_encounter;
                    $diagnosis = $e->doctor_diagnosis ?? ($chiefComplaint ?? 'Clinical Encounter');
                    $events[] = new ConsultationSyncEvent(
                        $e->id,
                        (int)$e->patient_id,
                        $clinic,
                        $chiefComplaint,
                        $diagnosis,
                        $e->doctor_id ? (int)$e->doctor_id : null,
                        'cash',
                        0.00,
                        'completed',
                        $facilityId,
                        $e->created_at?->toDateTimeString()
                    );
                }

                break;

            case 'pharmacy':
                $productRequests = ProductRequest::where('status', 3)
                    ->with('product', 'productOrServiceRequest')
                    ->whereBetween('dispense_date', [$startDate, $endDate])
                    ->get();

                foreach ($productRequests as $pr) {
                    $posr = $pr->productOrServiceRequest;
                    $events[] = new DispenseSyncEvent(
                        $pr->id,
                        $pr->product?->name ?? 'Medication',
                        $pr->patient_id ? (int)$pr->patient_id : null,
                        $pr->product_id ? (int)$pr->product_id : null,
                        $pr->dispensed_from_store_id ? (int)$pr->dispensed_from_store_id : null,
                        null,
                        $pr->dispensed_from_batch_id ? (string)$pr->dispensed_from_batch_id : null,
                        (float)($pr->qty ?? 1),
                        (float)($posr?->amount ?? 0.00),
                        (float)($posr?->payable_amount ?? 0.00),
                        $posr?->coverage_mode ?? 'cash',
                        $posr?->hmo_id ? (int)$posr->hmo_id : null,
                        'dispensed',
                        $facilityId,
                        $pr->dispense_date ? Carbon::parse($pr->dispense_date)->toDateTimeString() : null
                    );
                }

                break;

            case 'lab':
                $labRequests = LabServiceRequest::where('status', 4)
                    ->with('service')
                    ->whereBetween('result_date', [$startDate, $endDate])
                    ->get();

                foreach ($labRequests as $lab) {
                    $events[] = new LabSyncEvent(
                        $lab->id,
                        (int)$lab->patient_id,
                        $lab->service?->name ?? ($lab->free_form_name ?? 'Investigation'),
                        $lab->service_id ? (int)$lab->service_id : null,
                        null,
                        $lab->nhmis_outcome,
                        $lab->nhmis_outcome_raw,
                        'cash',
                        0.00,
                        $lab->result_by ? (int)$lab->result_by : null,
                        $lab->approved_by ? (int)$lab->approved_by : null,
                        'completed',
                        $facilityId,
                        $lab->result_date ? Carbon::parse($lab->result_date)->toDateTimeString() : null
                    );
                }

                break;

            case 'procedures':
                $procedures = Procedure::whereNotNull('outcome')
                    ->with('service')
                    ->whereBetween('updated_at', [$startDate, $endDate])
                    ->get();

                foreach ($procedures as $proc) {
                    $procName = $proc->is_free_form ? ($proc->free_form_name ?? 'Procedure') : ($proc->service?->service_name ?? 'Procedure');
                    $events[] = new ProcedureSyncEvent(
                        $proc->id,
                        (int)$proc->patient_id,
                        $procName,
                        $proc->outcome ?? 'successful',
                        'Minor Theater',
                        $proc->outcome_notes,
                        $proc->nhmis_outcome,
                        $proc->nhmis_outcome_raw,
                        'cash',
                        0.00,
                        null,
                        'completed',
                        $facilityId,
                        $proc->updated_at?->toDateTimeString()
                    );
                }

                break;

            case 'admissions':
                $admissions = AdmissionRequest::whereBetween('created_at', [$startDate, $endDate])
                    ->with('bed.ward')
                    ->get();

                foreach ($admissions as $adm) {
                    $status = $adm->discharged ? 'discharged' : 'admitted';
                    $events[] = new AdmissionSyncEvent(
                        $adm->id,
                        (int)$adm->patient_id,
                        $status,
                        $adm->bed_id ? (int)$adm->bed_id : null,
                        $adm->bed?->name,
                        $adm->bed?->ward?->id ? (int)$adm->bed?->ward?->id : null,
                        $adm->bed?->ward?->name ?? 'General Ward',
                        $adm->discharge_date ? Carbon::parse($adm->discharge_date)->toDateTimeString() : null,
                        'cash',
                        0.00,
                        $status,
                        $facilityId,
                        $adm->created_at?->toDateTimeString()
                    );
                }

                break;
        }

        return $events;
    }
}
