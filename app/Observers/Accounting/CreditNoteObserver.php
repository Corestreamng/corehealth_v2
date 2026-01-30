<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\CreditNote;
use App\Services\Accounting\AccountingNotificationService;
use Illuminate\Support\Facades\App;

/**
 * Credit Note Observer
 *
 * Reference: Accounting System Plan §6 - Observers
 *
 * Observes credit note lifecycle events and triggers notifications.
 */
class CreditNoteObserver
{
    /**
     * Handle the CreditNote "created" event.
     */
    public function created(CreditNote $creditNote): void
    {
        $notificationService = App::make(AccountingNotificationService::class);
        $notificationService->notifyCreditNoteCreated($creditNote);
    }

    /**
     * Handle the CreditNote "updated" event.
     */
    public function updated(CreditNote $creditNote): void
    {
        // Check if status changed
        if ($creditNote->isDirty('status')) {
            $this->handleStatusChange($creditNote);
        }
    }

    /**
     * Handle status changes and send appropriate notifications.
     */
    protected function handleStatusChange(CreditNote $creditNote): void
    {
        $notificationService = App::make(AccountingNotificationService::class);

        switch ($creditNote->status) {
            case CreditNote::STATUS_PENDING_APPROVAL:
                $notificationService->notifyCreditNoteSubmitted($creditNote);
                break;

            case CreditNote::STATUS_APPROVED:
                $notificationService->notifyCreditNoteApproved($creditNote);
                break;

            case CreditNote::STATUS_PROCESSED:
                $notificationService->notifyCreditNoteProcessed($creditNote);
                break;

            case CreditNote::STATUS_VOID:
                $notificationService->notifyCreditNoteVoided($creditNote);
                break;
        }
    }
}
