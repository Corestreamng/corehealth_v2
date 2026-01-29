<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\JournalEntryEdit;
use App\Services\Accounting\AccountingNotificationService;
use Illuminate\Support\Facades\App;

/**
 * Journal Entry Edit Observer
 *
 * Reference: Accounting System Plan §6 - Observers
 *
 * Observes journal entry edit request lifecycle events.
 */
class JournalEntryEditObserver
{
    /**
     * Handle the JournalEntryEdit "created" event.
     */
    public function created(JournalEntryEdit $edit): void
    {
        $notificationService = App::make(AccountingNotificationService::class);
        $notificationService->notifyEditRequestSubmitted($edit);
    }

    /**
     * Handle the JournalEntryEdit "updated" event.
     */
    public function updated(JournalEntryEdit $edit): void
    {
        // Check if status changed
        if ($edit->isDirty('status')) {
            $this->handleStatusChange($edit);
        }
    }

    /**
     * Handle status changes and send appropriate notifications.
     */
    protected function handleStatusChange(JournalEntryEdit $edit): void
    {
        $notificationService = App::make(AccountingNotificationService::class);

        switch ($edit->status) {
            case JournalEntryEdit::STATUS_APPROVED:
                $notificationService->notifyEditRequestApproved($edit);
                break;

            case JournalEntryEdit::STATUS_REJECTED:
                $notificationService->notifyEditRequestRejected($edit);
                break;
        }
    }
}
