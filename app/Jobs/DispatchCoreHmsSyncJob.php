<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchCoreHmsSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [15, 60, 300];

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    protected string $notificationType;

    protected array $payload;

    protected string $sourceId;

    protected ?string $eventDate;

    protected ?int $facilityId;

    /**
     * Create a new job instance.
     *
     * @param string|\App\Contracts\CoreHms\CoreHmsSyncEventInterface $notificationTypeOrEvent
     * @param array $payload Event data payload
     * @param string|int|null $sourceId Unique entity ID from CoreHealth
     * @param string|null $eventDate Timestamp string (YYYY-MM-DD HH:MM:SS)
     * @param int|null $facilityId Facility ID registered in CoreHMS SuperAdmin
     */
    public function __construct(
        $notificationTypeOrEvent,
        array $payload = [],
        $sourceId = null,
        ?string $eventDate = null,
        ?int $facilityId = null
    ) {
        if ($notificationTypeOrEvent instanceof \App\Contracts\CoreHms\CoreHmsSyncEventInterface) {
            $this->notificationType = $notificationTypeOrEvent->getEventType();
            $this->payload = $notificationTypeOrEvent->toPayload();
            $this->sourceId = $notificationTypeOrEvent->getSourceId();
            $this->eventDate = $notificationTypeOrEvent->getEventDate();
            $this->facilityId = $notificationTypeOrEvent->getFacilityId();
        } else {
            $this->notificationType = (string)$notificationTypeOrEvent;
            $this->payload = $payload;
            $this->sourceId = (string)($sourceId ?? uniqid('ev_'));
            $this->eventDate = $eventDate ?? now()->toDateTimeString();
            $this->facilityId = $facilityId ?? (int)appsettings('client_id', 4);
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Guard: Verify if facility is set to sync online
        if ((int)appsettings('goonline', 0) !== 1) {
            Log::info("CoreHMS sync skipped: facility 'goonline' flag is disabled.");

            return;
        }

        $superAdminUrl = rtrim(appsettings('COREHMS_SUPERADMIN_URL', ''), '/');
        $username = appsettings('COREHMS_SUPERADMIN_USERNAME');
        $password = appsettings('COREHMS_SUPERADMIN_PASS');

        if (empty($superAdminUrl) || empty($username) || empty($password)) {
            Log::warning("CoreHMS sync skipped: SuperAdmin API credentials are not configured in appsettings.");

            return;
        }

        $endpoint = $superAdminUrl . '/event-notification.php?notification_type=' . urlencode($this->notificationType) . '&facility_id=' . $this->facilityId;
        $eventKey = sha1($this->facilityId . ':' . $this->notificationType . ':' . $this->sourceId);

        $requestBody = array_merge($this->payload, [
            'facility_id' => $this->facilityId,
            'source_id' => $this->sourceId,
            'event_key' => $eventKey,
            'event_date' => $this->eventDate,
            'notification_type' => $this->notificationType,
        ]);

        try {
            $response = Http::withBasicAuth($username, $password)
                ->timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($endpoint, $requestBody);

            if ($response->successful()) {
                $responseData = $response->json();
                $isDuplicate = $responseData['duplicate'] ?? false;
                Log::info("CoreHMS sync success [{$this->notificationType} #{$this->sourceId}]" . ($isDuplicate ? " (deduplicated)" : ""), [
                    'event_key' => $eventKey,
                    'response' => $responseData,
                ]);
            } else {
                Log::error("CoreHMS sync failed [{$this->notificationType} #{$this->sourceId}]: HTTP {$response->status()}", [
                    'body' => $response->body(),
                ]);
                $response->throw();
            }
        } catch (\Throwable $e) {
            Log::error("CoreHMS sync error [{$this->notificationType} #{$this->sourceId}]: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
