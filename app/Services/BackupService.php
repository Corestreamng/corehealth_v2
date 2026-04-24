<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupService
{
    /**
     * Local backup directory inside storage/app
     */
    protected $backupDir = 'backups/corehealth';

    /**
     * Find mysqldump binary with multiple fallback paths.
     */
    protected function findMysqldump(): ?string
    {
        $paths = [
            env('MYSQLDUMP_PATH', ''),        // .env override first
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/opt/lampp/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
            '/usr/sbin/mysqldump',
            'mysqldump',                       // system PATH last
        ];

        foreach ($paths as $path) {
            if (empty($path)) continue;

            // Check if file exists (absolute paths)
            if ($path !== 'mysqldump' && file_exists($path) && is_executable($path)) {
                return $path;
            }

            // Check via `which` for relative/PATH-based
            $which = trim(shell_exec("which {$path} 2>/dev/null") ?? '');
            if (!empty($which) && file_exists($which)) {
                return $which;
            }
        }

        return null;
    }

    /**
     * Find mysql binary with multiple fallback paths.
     */
    protected function findMysql(): ?string
    {
        $paths = [
            env('MYSQL_PATH', ''),
            '/usr/bin/mysql',
            '/usr/local/bin/mysql',
            '/usr/local/mysql/bin/mysql',
            '/opt/lampp/bin/mysql',
            '/opt/homebrew/bin/mysql',
            '/usr/sbin/mysql',
            'mysql',
        ];

        foreach ($paths as $path) {
            if (empty($path)) continue;

            if ($path !== 'mysql' && file_exists($path) && is_executable($path)) {
                return $path;
            }

            $which = trim(shell_exec("which {$path} 2>/dev/null") ?? '');
            if (!empty($which) && file_exists($which)) {
                return $which;
            }
        }

        return null;
    }

    /**
     * Check if backup compression is enabled in application settings.
     */
    protected function isCompressionEnabled(): bool
    {
        return (bool) appsettings('backup_compression');
    }

    /**
     * Get the full filesystem path for the backup directory.
     */
    protected function getBackupPath(): string
    {
        $path = storage_path('app/' . $this->backupDir);

        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Create a database backup using mysqldump.
     *
     * @return array ['success' => bool, 'filename' => string|null, 'message' => string, 'size' => int|null]
     */
    public function createBackup(): array
    {
        $mysqldump = $this->findMysqldump();

        if (!$mysqldump) {
            $msg = 'mysqldump binary not found. Tried multiple paths. Set MYSQLDUMP_PATH in .env.';
            Log::channel('backup')->error($msg);
            return ['success' => false, 'filename' => null, 'message' => $msg, 'size' => null];
        }

        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $compress = $this->isCompressionEnabled();
        $ext = $compress ? '.sql.gz' : '.sql';
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "corehealth_backup_{$timestamp}{$ext}";
        $filepath = $this->getBackupPath() . '/' . $filename;

        // Build mysqldump command
        $envPrefix = !empty($dbPass) ? 'MYSQL_PWD=' . escapeshellarg($dbPass) . ' ' : '';
        $cmd = sprintf(
            '%s%s --host=%s --port=%s -u %s --single-transaction --routines --triggers %s',
            $envPrefix,
            escapeshellarg($mysqldump),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbName)
        );

        if ($compress) {
            $cmd .= ' | gzip > ' . escapeshellarg($filepath);
        } else {
            $cmd .= ' > ' . escapeshellarg($filepath);
        }

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
            $errorMsg = implode("\n", $output);
            // Clean up empty file
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            $msg = "mysqldump failed (exit code: {$exitCode}): {$errorMsg}";
            Log::channel('backup')->error($msg);
            return ['success' => false, 'filename' => null, 'message' => $msg, 'size' => null];
        }

        $size = filesize($filepath);
        $msg = "Backup created: {$filename} (" . $this->humanFileSize($size) . ")";
        Log::channel('backup')->info($msg);

        return ['success' => true, 'filename' => $filename, 'message' => $msg, 'size' => $size];
    }

    /**
     * Get all local backup files sorted by newest first.
     */
    public function getBackups(): array
    {
        $path = $this->getBackupPath();
        $files = array_merge(
            glob($path . '/corehealth_backup_*') ?: [],
            glob($path . '/pre_restore_*') ?: []
        );
        $backups = [];

        foreach ($files as $file) {
            $basename = basename($file);
            $mtime = filemtime($file);
            $size = filesize($file);
            $age = Carbon::createFromTimestamp($mtime)->diffForHumans();

            $backups[] = [
                'filename'   => $basename,
                'size'       => $size,
                'size_human' => $this->humanFileSize($size),
                'created_at' => Carbon::createFromTimestamp($mtime)->format('Y-m-d H:i:s'),
                'age'        => $age,
                'timestamp'  => $mtime,
                'compressed' => str_ends_with($basename, '.gz'),
            ];
        }

        // Sort newest first
        usort($backups, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

        return $backups;
    }

    /**
     * Delete backup files older than the retention period.
     *
     * @param int $retentionDays Number of days to keep backups
     * @return array ['deleted' => int, 'kept' => int, 'files' => array]
     */
    public function pruneOldBackups(int $retentionDays = 7): array
    {
        $path = $this->getBackupPath();
        $files = array_merge(
            glob($path . '/corehealth_backup_*') ?: [],
            glob($path . '/pre_restore_*') ?: []
        );
        $cutoff = Carbon::now()->subDays($retentionDays)->timestamp;

        $deleted = 0;
        $kept = 0;
        $deletedFiles = [];

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                $basename = basename($file);
                unlink($file);
                $deletedFiles[] = $basename;
                $deleted++;
                Log::channel('backup')->info("Pruned old backup: {$basename}");
            } else {
                $kept++;
            }
        }

        return ['deleted' => $deleted, 'kept' => $kept, 'files' => $deletedFiles];
    }

    /**
     * Restore database from a local backup file.
     * Creates a pre-restore backup first as a safety net.
     *
     * @param string $filename The backup filename
     * @return array ['success' => bool, 'message' => string, 'pre_restore_backup' => string|null]
     */
    public function restoreFromBackup(string $filename): array
    {
        $filepath = $this->getBackupPath() . '/' . $filename;

        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => "Backup file not found: {$filename}", 'pre_restore_backup' => null];
        }

        // Safety: create a pre-restore backup
        $preRestore = $this->createPreRestoreBackup();

        $mysql = $this->findMysql();
        if (!$mysql) {
            return [
                'success' => false,
                'message' => 'mysql binary not found. Set MYSQL_PATH in .env.',
                'pre_restore_backup' => $preRestore
            ];
        }

        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $envPrefix = !empty($dbPass) ? 'MYSQL_PWD=' . escapeshellarg($dbPass) . ' ' : '';

        $isCompressed = str_ends_with($filename, '.gz');

        if ($isCompressed) {
            $cmd = sprintf(
                'gunzip -c %s | %s%s --host=%s --port=%s -u %s %s',
                escapeshellarg($filepath),
                $envPrefix,
                escapeshellarg($mysql),
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbName)
            );
        } else {
            $cmd = sprintf(
                '%s%s --host=%s --port=%s -u %s %s < %s',
                $envPrefix,
                escapeshellarg($mysql),
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbName),
                escapeshellarg($filepath)
            );
        }

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $errorMsg = implode("\n", $output);
            $msg = "Restore failed (exit code: {$exitCode}): {$errorMsg}";
            Log::channel('backup')->error($msg);
            return ['success' => false, 'message' => $msg, 'pre_restore_backup' => $preRestore];
        }

        $msg = "Database restored successfully from {$filename}";
        Log::channel('backup')->info($msg);

        // Clear app settings cache after restore
        clearAppSettingsCache();

        return ['success' => true, 'message' => $msg, 'pre_restore_backup' => $preRestore];
    }

    /**
     * Create a pre-restore safety backup.
     */
    protected function createPreRestoreBackup(): ?string
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "pre_restore_{$timestamp}.sql";
        $filepath = $this->getBackupPath() . '/' . $filename;

        $mysqldump = $this->findMysqldump();
        if (!$mysqldump) return null;

        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $envPrefix = !empty($dbPass) ? 'MYSQL_PWD=' . escapeshellarg($dbPass) . ' ' : '';

        $cmd = sprintf(
            '%s%s --host=%s --port=%s -u %s --single-transaction --routines --triggers %s > %s',
            $envPrefix,
            escapeshellarg($mysqldump),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($filepath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
            if (file_exists($filepath)) unlink($filepath);
            Log::channel('backup')->warning("Pre-restore backup failed");
            return null;
        }

        Log::channel('backup')->info("Pre-restore backup created: {$filename}");
        return $filename;
    }

    /**
     * Get all mounted drives and detect USB devices.
     */
    public function getMountedDrives(): array
    {
        $drives = [];

        // Use lsblk to get block device info
        $lsblkOutput = shell_exec('lsblk -J -o NAME,SIZE,TYPE,MOUNTPOINT,FSTYPE,LABEL,HOTPLUG 2>/dev/null');

        if ($lsblkOutput) {
            $lsblk = json_decode($lsblkOutput, true);

            if (isset($lsblk['blockdevices'])) {
                foreach ($lsblk['blockdevices'] as $device) {
                    $this->parseBlockDevice($device, $drives);
                }
            }
        }

        // Enrich with df data for space info
        $dfOutput = shell_exec('df -BM --output=target,size,used,avail,pcent 2>/dev/null');
        $dfMap = $this->parseDfOutput($dfOutput);

        foreach ($drives as &$drive) {
            $mp = $drive['mountpoint'];
            if (isset($dfMap[$mp])) {
                $drive['total_space'] = $dfMap[$mp]['size'];
                $drive['used_space'] = $dfMap[$mp]['used'];
                $drive['free_space'] = $dfMap[$mp]['avail'];
                $drive['usage_percent'] = $dfMap[$mp]['pcent'];
            }

            // Count backups on this drive
            $backupPath = rtrim($mp, '/') . '/.backups/corehealth';
            $drive['backup_count'] = 0;
            $drive['backup_path'] = $backupPath;
            if (is_dir($backupPath)) {
                $drive['backup_count'] = count(glob($backupPath . '/*.sql*'));
            }
        }

        return $drives;
    }

    /**
     * Recursively parse block device tree from lsblk.
     */
    protected function parseBlockDevice(array $device, array &$drives, bool $parentIsUsb = false): void
    {
        $isUsb = $parentIsUsb || (isset($device['hotplug']) && $device['hotplug'] == '1');

        if (!empty($device['mountpoint']) && $device['type'] === 'part') {
            // Skip system mounts
            $skipMounts = ['/boot', '/boot/efi', '/snap'];
            $mp = $device['mountpoint'];
            $skip = false;
            foreach ($skipMounts as $s) {
                if (str_starts_with($mp, $s)) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip) {
                $drives[] = [
                    'name'          => $device['name'] ?? '',
                    'label'         => $device['label'] ?? $device['name'] ?? 'Unlabeled',
                    'mountpoint'    => $mp,
                    'filesystem'    => $device['fstype'] ?? 'unknown',
                    'size'          => $device['size'] ?? 'unknown',
                    'is_usb'        => $isUsb,
                    'total_space'   => null,
                    'used_space'    => null,
                    'free_space'    => null,
                    'usage_percent' => null,
                ];
            }
        }

        // Also include root if it's mounted
        if (!empty($device['mountpoint']) && $device['mountpoint'] === '/' && $device['type'] === 'part') {
            // Already handled above
        }

        // Process children
        if (isset($device['children'])) {
            foreach ($device['children'] as $child) {
                $this->parseBlockDevice($child, $drives, $isUsb);
            }
        }
    }

    /**
     * Parse df output into a map keyed by mountpoint.
     */
    protected function parseDfOutput(?string $output): array
    {
        $map = [];
        if (empty($output)) return $map;

        $lines = explode("\n", trim($output));
        // Skip header line
        array_shift($lines);

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 5) {
                $map[$parts[0]] = [
                    'size'  => $parts[1],
                    'used'  => $parts[2],
                    'avail' => $parts[3],
                    'pcent' => $parts[4],
                ];
            }
        }

        return $map;
    }

    /**
     * Replicate a backup file to all external/mounted drives.
     *
     * @param string $filename The backup file to replicate
     * @return array ['total' => int, 'success' => int, 'failed' => int, 'results' => array]
     */
    public function replicateToExternalDrives(string $filename): array
    {
        $sourceFile = $this->getBackupPath() . '/' . $filename;

        if (!file_exists($sourceFile)) {
            return ['total' => 0, 'success' => 0, 'failed' => 0, 'results' => []];
        }

        $drives = $this->getMountedDrives();
        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($drives as $drive) {
            $mp = $drive['mountpoint'];

            // Skip root filesystem — we already store there via storage/app
            if ($mp === '/') continue;

            $backupDir = rtrim($mp, '/') . '/.backups/corehealth';

            try {
                if (!is_dir($backupDir)) {
                    if (!@mkdir($backupDir, 0755, true)) {
                        $results[] = [
                            'drive'   => $drive['label'],
                            'mount'   => $mp,
                            'success' => false,
                            'message' => 'Could not create backup directory (permission denied)',
                        ];
                        $failed++;
                        continue;
                    }
                }

                $destFile = $backupDir . '/' . $filename;
                if (@copy($sourceFile, $destFile)) {
                    $results[] = [
                        'drive'   => $drive['label'],
                        'mount'   => $mp,
                        'success' => true,
                        'message' => 'Backup replicated successfully',
                    ];
                    $success++;

                    // Also prune old backups on this drive
                    $this->pruneExternalDriveBackups($backupDir);
                } else {
                    $results[] = [
                        'drive'   => $drive['label'],
                        'mount'   => $mp,
                        'success' => false,
                        'message' => 'Copy failed (permission denied or disk full)',
                    ];
                    $failed++;
                }
            } catch (\Exception $e) {
                $results[] = [
                    'drive'   => $drive['label'],
                    'mount'   => $mp,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
                $failed++;
            }
        }

        $total = $success + $failed;
        Log::channel('backup')->info("Replication complete: {$success}/{$total} drives");

        return ['total' => $total, 'success' => $success, 'failed' => $failed, 'results' => $results];
    }

    /**
     * Prune old backups on an external drive.
     */
    protected function pruneExternalDriveBackups(string $backupDir, int $retentionDays = 7): void
    {
        $files = glob($backupDir . '/corehealth_backup_*');
        $cutoff = Carbon::now()->subDays($retentionDays)->timestamp;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    /**
     * Get backup files from all external drives.
     */
    public function getExternalBackups(): array
    {
        $drives = $this->getMountedDrives();
        $backups = [];

        foreach ($drives as $drive) {
            $mp = $drive['mountpoint'];
            if ($mp === '/') continue;

            $backupDir = rtrim($mp, '/') . '/.backups/corehealth';
            if (!is_dir($backupDir)) continue;

            $files = glob($backupDir . '/*.sql*');
            foreach ($files as $file) {
                $mtime = filemtime($file);
                $backups[] = [
                    'filename'     => basename($file),
                    'drive_label'  => $drive['label'],
                    'drive_mount'  => $mp,
                    'full_path'    => $file,
                    'size'         => filesize($file),
                    'size_human'   => $this->humanFileSize(filesize($file)),
                    'created_at'   => Carbon::createFromTimestamp($mtime)->format('Y-m-d H:i:s'),
                    'age'          => Carbon::createFromTimestamp($mtime)->diffForHumans(),
                    'is_usb'       => $drive['is_usb'],
                    'compressed'   => str_ends_with(basename($file), '.gz'),
                ];
            }
        }

        usort($backups, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return $backups;
    }

    /**
     * Restore from a backup file on an external drive.
     */
    public function restoreFromExternalBackup(string $fullPath): array
    {
        if (!file_exists($fullPath)) {
            return ['success' => false, 'message' => "File not found: {$fullPath}", 'pre_restore_backup' => null];
        }

        // Copy to local backup dir first
        $localFilename = basename($fullPath);
        $localPath = $this->getBackupPath() . '/' . $localFilename;

        if (!copy($fullPath, $localPath)) {
            return ['success' => false, 'message' => 'Failed to copy file to local storage', 'pre_restore_backup' => null];
        }

        return $this->restoreFromBackup($localFilename);
    }

    /**
     * Delete a specific backup file.
     */
    public function deleteBackup(string $filename): array
    {
        $filepath = $this->getBackupPath() . '/' . $filename;

        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => "File not found: {$filename}"];
        }

        // Allow deleting pre-restore backups if the user explicitly chooses to via UI
        // (Previously blocked, now allowed to clear space manually)

        unlink($filepath);
        Log::channel('backup')->info("Backup deleted: {$filename}");

        return ['success' => true, 'message' => "Deleted: {$filename}"];
    }

    /**
     * Get the database size.
     */
    public function getDatabaseSize(): string
    {
        try {
            $dbName = config('database.connections.mysql.database');
            $result = \DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?", [$dbName]);

            if (!empty($result)) {
                return $result[0]->size_mb . ' MB';
            }
        } catch (\Exception $e) {
            Log::channel('backup')->warning("Could not get DB size: " . $e->getMessage());
        }

        return 'Unknown';
    }

    /**
     * Convert bytes to human-readable file size.
     */
    public function humanFileSize(int $bytes, int $decimals = 2): string
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . ($size[$factor] ?? 'B');
    }
}
