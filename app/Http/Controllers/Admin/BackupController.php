<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    protected $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Display the backup admin UI.
     */
    public function index()
    {
        return view('admin.backups.index');
    }

    /**
     * Get all local backup files as JSON.
     */
    public function listBackups()
    {
        $backups = $this->backupService->getBackups();
        $dbSize = $this->backupService->getDatabaseSize();

        return response()->json([
            'backups'  => $backups,
            'db_size'  => $dbSize,
            'total'    => count($backups),
            'compression_enabled' => (bool) appsettings('backup_compression'),
        ]);
    }

    /**
     * Manually trigger a backup creation.
     */
    public function createBackup()
    {
        $result = $this->backupService->createBackup();

        if ($result['success']) {
            // Prune old backups
            $pruned = $this->backupService->pruneOldBackups(7);

            // Replicate to external drives
            $replicated = $this->backupService->replicateToExternalDrives($result['filename']);

            return response()->json([
                'success'     => true,
                'message'     => $result['message'],
                'filename'    => $result['filename'],
                'size'        => $result['size'],
                'pruned'      => $pruned,
                'replicated'  => $replicated,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 500);
    }

    /**
     * Get mounted drives and USB devices as JSON.
     */
    public function listDrives()
    {
        $drives = $this->backupService->getMountedDrives();

        return response()->json([
            'drives' => $drives,
            'total'  => count($drives),
        ]);
    }

    /**
     * Get backup files from external drives as JSON.
     */
    public function listExternalBackups()
    {
        $backups = $this->backupService->getExternalBackups();

        return response()->json([
            'backups' => $backups,
            'total'   => count($backups),
        ]);
    }

    /**
     * Restore database from a local backup file.
     */
    public function restore(Request $request)
    {
        $request->validate([
            'filename' => 'required|string|max:255',
        ]);

        $result = $this->backupService->restoreFromBackup($request->filename);

        $statusCode = $result['success'] ? 200 : 500;

        return response()->json($result, $statusCode);
    }

    /**
     * Restore database from an external drive backup file.
     */
    public function restoreExternal(Request $request)
    {
        $request->validate([
            'full_path' => 'required|string',
        ]);

        $result = $this->backupService->restoreFromExternalBackup($request->full_path);

        $statusCode = $result['success'] ? 200 : 500;

        return response()->json($result, $statusCode);
    }

    /**
     * Delete a specific backup file.
     */
    public function delete(string $filename)
    {
        $result = $this->backupService->deleteBackup($filename);

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * Download a backup file.
     */
    public function download(string $filename)
    {
        $filepath = storage_path('app/backups/corehealth/' . $filename);

        if (!file_exists($filepath)) {
            abort(404, 'Backup file not found');
        }

        // Only allow downloading actual backup files (sanitize path traversal)
        if (basename($filename) !== $filename || !preg_match('/^(corehealth_backup_|pre_restore_)/', $filename)) {
            abort(403, 'Invalid filename');
        }

        return response()->download($filepath, $filename);
    }
}
