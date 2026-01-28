<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Service for tracking import progress in real-time via Cache
 */
class ImportProgressService
{
    const CACHE_PREFIX = 'import_progress_';
    const CACHE_TTL = 3600; // 1 hour

    /**
     * Generate a unique import ID
     */
    public static function generateImportId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Initialize a new import tracking session
     */
    public static function startImport(string $importId, string $type, int $totalRows, int $userId): void
    {
        $data = [
            'id' => $importId,
            'type' => $type,
            'status' => 'processing',
            'total_rows' => $totalRows,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'current_batch' => 0,
            'total_batches' => ceil($totalRows / 50),
            'percent' => 0,
            'started_at' => now()->toIso8601String(),
            'user_id' => $userId,
            'completed_at' => null,
        ];

        Cache::put(self::CACHE_PREFIX . $importId, $data, self::CACHE_TTL);
    }

    /**
     * Update progress after processing a batch
     */
    public static function updateProgress(
        string $importId,
        int $processed,
        int $created,
        int $updated,
        int $skipped,
        array $errors = [],
        int $currentBatch = 0
    ): void {
        $data = Cache::get(self::CACHE_PREFIX . $importId);

        if (!$data) {
            return;
        }

        $data['processed'] = $processed;
        $data['created'] = $created;
        $data['updated'] = $updated;
        $data['skipped'] = $skipped;
        $data['errors'] = array_merge($data['errors'], $errors);
        $data['current_batch'] = $currentBatch;
        $data['percent'] = $data['total_rows'] > 0
            ? round(($processed / $data['total_rows']) * 100, 1)
            : 0;

        Cache::put(self::CACHE_PREFIX . $importId, $data, self::CACHE_TTL);
    }

    /**
     * Mark import as complete
     */
    public static function completeImport(string $importId, array $finalReport = []): void
    {
        $data = Cache::get(self::CACHE_PREFIX . $importId);

        if (!$data) {
            return;
        }

        $data['status'] = 'completed';
        $data['percent'] = 100;
        $data['completed_at'] = now()->toIso8601String();

        if (!empty($finalReport)) {
            $data = array_merge($data, $finalReport);
        }

        // Keep result longer for retrieval
        Cache::put(self::CACHE_PREFIX . $importId, $data, self::CACHE_TTL * 2);
    }

    /**
     * Mark import as failed
     */
    public static function failImport(string $importId, string $errorMessage): void
    {
        $data = Cache::get(self::CACHE_PREFIX . $importId);

        if (!$data) {
            $data = ['id' => $importId];
        }

        $data['status'] = 'failed';
        $data['error_message'] = $errorMessage;
        $data['completed_at'] = now()->toIso8601String();

        Cache::put(self::CACHE_PREFIX . $importId, $data, self::CACHE_TTL);
    }

    /**
     * Get current import progress
     */
    public static function getProgress(string $importId): ?array
    {
        return Cache::get(self::CACHE_PREFIX . $importId);
    }

    /**
     * Cancel an import (set flag that job should check)
     */
    public static function cancelImport(string $importId): bool
    {
        $data = Cache::get(self::CACHE_PREFIX . $importId);

        if (!$data || $data['status'] !== 'processing') {
            return false;
        }

        $data['status'] = 'cancelled';
        $data['completed_at'] = now()->toIso8601String();

        Cache::put(self::CACHE_PREFIX . $importId, $data, self::CACHE_TTL);
        return true;
    }

    /**
     * Check if import was cancelled
     */
    public static function isCancelled(string $importId): bool
    {
        $data = Cache::get(self::CACHE_PREFIX . $importId);
        return $data && $data['status'] === 'cancelled';
    }

    /**
     * Clean up old import data
     */
    public static function cleanup(string $importId): void
    {
        Cache::forget(self::CACHE_PREFIX . $importId);
    }
}
