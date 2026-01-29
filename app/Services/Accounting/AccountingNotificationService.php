<?php

namespace App\Services\Accounting;

use App\Models\Accounting\CreditNote;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryEdit;
use App\Services\DepartmentNotificationService;

/**
 * Accounting Notification Service
 *
 * Reference: Accounting System Plan §6 - Notification System
 *
 * Sends in-app notifications to the "Accounts Staff" group chat
 * for accounting-related events.
 */
class AccountingNotificationService
{
    protected DepartmentNotificationService $notificationService;

    public function __construct(DepartmentNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Notify when a journal entry is submitted for approval.
     */
    public function notifyEntrySubmitted(JournalEntry $entry): void
    {
        $message = $this->formatMessage(
            '📝 Journal Entry Submitted for Approval',
            [
                'Entry Number' => $entry->entry_number,
                'Date' => $entry->entry_date->format('d M Y'),
                'Description' => $this->truncate($entry->description, 100),
                'Amount' => number_format($entry->total_debit, 2),
                'Submitted By' => $entry->submitter->name ?? 'Unknown',
            ],
            "Please review and approve/reject this entry."
        );

        $this->sendToAccountingStaff($message, $entry->submitted_by);
    }

    /**
     * Notify when a journal entry is approved.
     */
    public function notifyEntryApproved(JournalEntry $entry): void
    {
        $message = $this->formatMessage(
            '✅ Journal Entry Approved',
            [
                'Entry Number' => $entry->entry_number,
                'Description' => $this->truncate($entry->description, 100),
                'Approved By' => $entry->approver->name ?? 'Unknown',
            ],
            "The entry can now be posted."
        );

        $this->sendToAccountingStaff($message, $entry->approved_by);
    }

    /**
     * Notify when a journal entry is rejected.
     */
    public function notifyEntryRejected(JournalEntry $entry): void
    {
        $message = $this->formatMessage(
            '❌ Journal Entry Rejected',
            [
                'Entry Number' => $entry->entry_number,
                'Description' => $this->truncate($entry->description, 100),
                'Rejected By' => $entry->rejector->name ?? 'Unknown',
                'Reason' => $this->truncate($entry->rejection_reason, 150),
            ],
            "Please review the rejection reason and resubmit if needed."
        );

        // Notify the original creator
        $this->sendToAccountingStaff($message, $entry->rejected_by);
    }

    /**
     * Notify when a journal entry is posted.
     */
    public function notifyEntryPosted(JournalEntry $entry): void
    {
        // Only notify for significant manual entries
        if ($entry->is_manual && $entry->total_debit >= 10000) {
            $message = $this->formatMessage(
                '📊 Large Journal Entry Posted',
                [
                    'Entry Number' => $entry->entry_number,
                    'Description' => $this->truncate($entry->description, 100),
                    'Amount' => number_format($entry->total_debit, 2),
                    'Posted By' => $entry->poster->name ?? 'Unknown',
                ],
                null
            );

            $this->sendToAccountingStaff($message, $entry->posted_by);
        }
    }

    /**
     * Notify when a journal entry is reversed.
     */
    public function notifyEntryReversed(JournalEntry $originalEntry, JournalEntry $reversingEntry): void
    {
        $message = $this->formatMessage(
            '🔄 Journal Entry Reversed',
            [
                'Original Entry' => $originalEntry->entry_number,
                'Reversing Entry' => $reversingEntry->entry_number,
                'Amount' => number_format($originalEntry->total_debit, 2),
                'Description' => $this->truncate($reversingEntry->description, 100),
            ],
            null
        );

        $this->sendToAccountingStaff($message, $reversingEntry->created_by);
    }

    /**
     * Notify when a credit note is submitted for approval.
     */
    public function notifyCreditNoteSubmitted(CreditNote $creditNote): void
    {
        $message = $this->formatMessage(
            '💳 Credit Note Submitted for Approval',
            [
                'Credit Note #' => $creditNote->credit_note_number,
                'Patient' => $creditNote->patient->full_name ?? 'Unknown',
                'Amount' => number_format($creditNote->total_amount, 2),
                'Reason' => $this->truncate($creditNote->reason, 100),
            ],
            "Please review and approve/reject this credit note."
        );

        $this->sendToAccountingStaff($message, $creditNote->created_by);
    }

    /**
     * Notify when a credit note is approved.
     */
    public function notifyCreditNoteApproved(CreditNote $creditNote): void
    {
        $message = $this->formatMessage(
            '✅ Credit Note Approved',
            [
                'Credit Note #' => $creditNote->credit_note_number,
                'Patient' => $creditNote->patient->full_name ?? 'Unknown',
                'Amount' => number_format($creditNote->total_amount, 2),
                'Approved By' => $creditNote->approver->name ?? 'Unknown',
            ],
            "The refund can now be processed."
        );

        $this->sendToAccountingStaff($message, $creditNote->approved_by);
    }

    /**
     * Notify when a credit note is rejected.
     */
    public function notifyCreditNoteRejected(CreditNote $creditNote): void
    {
        $message = $this->formatMessage(
            '❌ Credit Note Rejected',
            [
                'Credit Note #' => $creditNote->credit_note_number,
                'Patient' => $creditNote->patient->full_name ?? 'Unknown',
                'Rejected By' => $creditNote->rejector->name ?? 'Unknown',
                'Reason' => $this->truncate($creditNote->rejection_reason, 150),
            ],
            null
        );

        $this->sendToAccountingStaff($message, $creditNote->rejected_by);
    }

    /**
     * Notify when a refund is processed.
     */
    public function notifyRefundProcessed(CreditNote $creditNote): void
    {
        $message = $this->formatMessage(
            '💰 Refund Processed',
            [
                'Credit Note #' => $creditNote->credit_note_number,
                'Patient' => $creditNote->patient->full_name ?? 'Unknown',
                'Amount' => number_format($creditNote->total_amount, 2),
                'Method' => $creditNote->refund_method_label,
                'Reference' => $creditNote->refund_reference ?? '-',
            ],
            null
        );

        $this->sendToAccountingStaff($message, $creditNote->refunded_by);
    }

    /**
     * Notify when an edit request is submitted for a posted entry.
     */
    public function notifyEditRequestSubmitted(JournalEntryEdit $editRequest): void
    {
        $message = $this->formatMessage(
            '✏️ Edit Request Submitted',
            [
                'Entry Number' => $editRequest->journalEntry->entry_number,
                'Requested By' => $editRequest->requester->name ?? 'Unknown',
                'Reason' => $this->truncate($editRequest->request_reason, 100),
            ],
            "Please review this edit request."
        );

        $this->sendToAccountingStaff($message, $editRequest->requested_by);
    }

    /**
     * Notify when accounting period is about to close.
     */
    public function notifyPeriodClosingSoon(string $periodName, string $endDate): void
    {
        $message = $this->formatMessage(
            '⏰ Accounting Period Closing Soon',
            [
                'Period' => $periodName,
                'End Date' => $endDate,
            ],
            "Please ensure all transactions are entered and reviewed."
        );

        $this->sendToAccountingStaff($message);
    }

    /**
     * Notify when accounting period is closed.
     */
    public function notifyPeriodClosed(string $periodName, string $closedBy): void
    {
        $message = $this->formatMessage(
            '🔒 Accounting Period Closed',
            [
                'Period' => $periodName,
                'Closed By' => $closedBy,
                'Closed At' => now()->format('d M Y H:i'),
            ],
            "No further entries can be made to this period."
        );

        $this->sendToAccountingStaff($message);
    }

    /**
     * Send daily summary of pending items.
     */
    public function sendDailySummary(array $summary): void
    {
        $message = $this->formatMessage(
            '📋 Daily Accounting Summary',
            [
                'Pending Approvals' => $summary['pending_entries'] ?? 0,
                'Pending Credit Notes' => $summary['pending_credit_notes'] ?? 0,
                'Pending Edit Requests' => $summary['pending_edits'] ?? 0,
                'Today\'s Entries' => $summary['today_entries'] ?? 0,
            ],
            null
        );

        $this->sendToAccountingStaff($message);
    }

    // =========================================
    // HELPER METHODS
    // =========================================

    /**
     * Format a notification message.
     */
    protected function formatMessage(string $title, array $details, ?string $footer = null): string
    {
        $lines = ["**{$title}**", ""];

        foreach ($details as $label => $value) {
            $lines[] = "• **{$label}:** {$value}";
        }

        if ($footer) {
            $lines[] = "";
            $lines[] = "_{$footer}_";
        }

        return implode("\n", $lines);
    }

    /**
     * Truncate text to specified length.
     */
    protected function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }

    /**
     * Send message to the Accounts Staff group.
     */
    protected function sendToAccountingStaff(string $message, ?int $excludeUserId = null): void
    {
        try {
            // Use the GROUP_ACCOUNTS constant (will need to be added to DepartmentNotificationService)
            $this->notificationService->sendGroupNotification(
                'GROUP_ACCOUNTS',
                $message,
                $excludeUserId
            );
        } catch (\Exception $e) {
            // Log the error but don't throw - notifications should not break operations
            \Log::warning("Failed to send accounting notification", [
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
