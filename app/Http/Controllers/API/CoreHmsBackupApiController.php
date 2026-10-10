<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CoreHmsBackupApiController extends Controller
{
    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Get backup storage status and statistics.
     */
    public function status()
    {
        try {
            $backups = $this->backupService->getBackups();
            $dbSize = $this->backupService->getDatabaseSize();
            $backupPath = storage_path('app/backups/corehealth');
            $freeBytes = file_exists($backupPath) ? disk_free_space($backupPath) : disk_free_space(storage_path());

            return response()->json([
                'status' => true,
                'data' => [
                    'database_size' => $dbSize,
                    'database_size_human' => $this->humanFileSize((int)$dbSize),
                    'total_backups' => count($backups),
                    'latest_backup' => $backups[0] ?? null,
                    'disk_free_bytes' => $freeBytes,
                    'disk_free_human' => $this->humanFileSize((int)$freeBytes),
                    'compression_enabled' => (bool) appsettings('backup_compression', true),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('CoreHMS Backup Status Error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['status' => false, 'message' => 'Failed to read backup status'], 500);
        }
    }

    /**
     * List all available backups with checksums and file metadata.
     */
    public function list(Request $request)
    {
        try {
            $backups = $this->backupService->getBackups();
            $backupPath = storage_path('app/backups/corehealth');
            $computeChecksum = $request->boolean('checksum', false);

            $enrichedBackups = array_map(function ($b) use ($backupPath, $computeChecksum) {
                if ($computeChecksum) {
                    $fullPath = $backupPath . '/' . $b['filename'];
                    $b['checksum_sha256'] = file_exists($fullPath) ? hash_file('sha256', $fullPath) : null;
                } else {
                    $b['checksum_sha256'] = null;
                }

                return $b;
            }, $backups);

            return response()->json([
                'status' => true,
                'total' => count($enrichedBackups),
                'backups' => $enrichedBackups,
            ]);
        } catch (Exception $e) {
            Log::error('CoreHMS Backup List Error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['status' => false, 'message' => 'Failed to retrieve backups list'], 500);
        }
    }

    /**
     * Trigger on-demand backup generation remotely.
     */
    public function generate(Request $request)
    {
        try {
            Log::channel('backup')->info('Remote backup generation triggered by CoreHMS SuperAdmin');
            $result = $this->backupService->createBackup();

            if (!$result['success']) {
                return response()->json([
                    'status' => false,
                    'message' => $result['message'],
                ], 500);
            }

            $filepath = storage_path('app/backups/corehealth/' . $result['filename']);
            $checksum = file_exists($filepath) ? hash_file('sha256', $filepath) : null;

            return response()->json([
                'status' => true,
                'message' => 'Backup created successfully',
                'data' => [
                    'filename' => $result['filename'],
                    'size' => $result['size'],
                    'size_human' => $this->humanFileSize((int)$result['size']),
                    'checksum_sha256' => $checksum,
                    'compressed' => str_ends_with($result['filename'], '.gz'),
                    'created_at' => now()->toDateTimeString(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('CoreHMS Remote Backup Generate Error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['status' => false, 'message' => 'Backup generation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Stream download backup file securely.
     */
    public function download(Request $request, string $filename)
    {
        try {
            // Strictly guard against directory traversal
            if (basename($filename) !== $filename || !preg_match('/^(corehealth_backup_|pre_restore_)[a-zA-Z0-9_\-\.]+\.(sql|sql\.gz)$/', $filename)) {
                return response()->json(['status' => false, 'message' => 'Invalid or illegal backup filename'], 400);
            }

            $filepath = storage_path('app/backups/corehealth/' . $filename);

            if (!file_exists($filepath)) {
                return response()->json(['status' => false, 'message' => 'Backup file not found on facility server'], 404);
            }

            return response()->download($filepath, $filename, [
                'Content-Type' => 'application/octet-stream',
                'X-Checksum-SHA256' => hash_file('sha256', $filepath),
                'X-Backup-Size' => filesize($filepath),
            ]);
        } catch (Exception $e) {
            Log::error('CoreHMS Backup Stream Error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['status' => false, 'message' => 'Failed to stream backup'], 500);
        }
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2) . ' ' . ($units[$power] ?? 'B');
    }
}
