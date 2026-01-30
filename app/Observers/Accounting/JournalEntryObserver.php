<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingNotificationService;
use Illuminate\Support\Facades\App;

/**
 * Journal Entry Observer
 *
 * Reference: Accounting System Plan §6 - Observers
 *
 * Observes journal entry lifecycle events and triggers notifications.
 */
class JournalEntryObserver
{
    /**
     * Handle the JournalEntry "updated" event.
     */
    public function updated(JournalEntry $entry): void
    {
        // Check if status changed
        if ($entry->isDirty('status')) {
            $this->handleStatusChange($entry);
        }
    }

    /**
     * Handle status changes and send appropriate notifications.
     */
    protected function handleStatusChange(JournalEntry $entry): void
    {
        $notificationService = App::make(AccountingNotificationService::class);

        switch ($entry->status) {
            case JournalEntry::STATUS_PENDING:
                $notificationService->notifyEntrySubmitted($entry);
                break;

            case JournalEntry::STATUS_APPROVED:
                $notificationService->notifyEntryApproved($entry);
                break;

            case JournalEntry::STATUS_REJECTED:
                $notificationService->notifyEntryRejected($entry);
                break;

            case JournalEntry::STATUS_POSTED:
                $notificationService->notifyEntryPosted($entry);
                break;

            case JournalEntry::STATUS_REVERSED:
                // Reversal notification is handled separately in AccountingService
                break;
        }
    }
}
