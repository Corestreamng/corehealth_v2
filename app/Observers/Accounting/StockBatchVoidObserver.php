<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\StockBatch;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Stock Batch Void Observer
 *
 * Handles reversal of the intake journal entry when a stock batch is voided
 * ("Recorded in error" / non-existent stock).
 */
class StockBatchVoidObserver
{
    public function updated(StockBatch $batch): void
    {
        // Only trigger when voided_at was just set and void reversal hasn't been created yet
        if (!($batch->wasChanged('voided_at') && $batch->voided_at && !$batch->void_reversal_je_id)) {
            return;
        }

        if (!$batch->journal_entry_id) {
            return;
        }

        try {
            $journalEntry = JournalEntry::find($batch->journal_entry_id);
            if (!$journalEntry) {
                return;
            }

            $accountingService = App::make(AccountingService::class);
            $userId = auth()->id() ?? $batch->voided_by ?? 1;
            $reason = $batch->void_reason ?? 'Batch voided as recorded in error';

            $reversingEntry = $accountingService->reverseEntry($journalEntry, $userId, $reason);

            $batch->void_reversal_je_id = $reversingEntry->id;
            $batch->saveQuietly();

            Log::info('StockBatchVoidObserver: Reversal journal entry created successfully', [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'original_je_id' => $journalEntry->id,
                'reversal_je_id' => $reversingEntry->id,
            ]);
        } catch (\Exception $e) {
            Log::error('StockBatchVoidObserver: Failed to reverse journal entry', [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
