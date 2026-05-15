<?php

namespace App\Services;

use App\Models\SlowQuery;
use App\Models\ApplicationStatu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SlowQueryService
{
    /**
     * Check MySQL configuration and try to detect the log file.
     */
    public function checkConfiguration()
    {
        try {
            $variables = DB::select("SHOW VARIABLES WHERE Variable_name IN ('slow_query_log', 'slow_query_log_file', 'long_query_time')");
            $config = [];
            foreach ($variables as $v) {
                $config[$v->Variable_name] = $v->Value;
            }

            return [
                'enabled' => ($config['slow_query_log'] ?? 'OFF') === 'ON',
                'log_file' => $config['slow_query_log_file'] ?? null,
                'threshold' => $config['long_query_time'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error("SlowQueryService: Failed to check MySQL config: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Attempt to configure MySQL for slow query logging.
     */
    public function configureMySQL($threshold = 2, $customPath = null)
    {
        try {
            DB::statement("SET GLOBAL slow_query_log = 'ON'");
            DB::statement("SET GLOBAL long_query_time = {$threshold}");
            
            if ($customPath) {
                DB::statement("SET GLOBAL slow_query_log_file = '{$customPath}'");
            }

            return ['success' => true, 'message' => 'MySQL configuration updated successfully.'];
        } catch (\Exception $e) {
            Log::error("SlowQueryService: Failed to configure MySQL: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Failed to configure MySQL. You may lack SUPER or SYSTEM_VARIABLES_ADMIN privileges. Details: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse the slow query log file incrementally.
     */
    public function parseLog()
    {
        $appSettings = ApplicationStatu::first();
        if (!$appSettings) return;

        $logPath = $appSettings->slow_query_log_path;
        
        // If no path set, try to detect from MySQL
        if (!$logPath) {
            $config = $this->checkConfiguration();
            $logPath = $config['log_file'] ?? null;
            if ($logPath) {
                $appSettings->update(['slow_query_log_path' => $logPath]);
            }
        }

        if (!$logPath || !file_exists($logPath) || !is_readable($logPath)) {
            Log::warning("SlowQueryService: Log file not found or not readable at: " . ($logPath ?? 'unknown'));
            return;
        }

        $offset = (int) $appSettings->slow_query_log_offset;
        $fileSize = filesize($logPath);

        // If file was rotated (size smaller than offset), reset offset
        if ($fileSize < $offset) {
            $offset = 0;
        }

        $handle = fopen($logPath, 'r');
        if (!$handle) return;

        fseek($handle, $offset);

        $buffer = '';
        $queriesFound = 0;

        // Process in chunks
        while (!feof($handle)) {
            $chunk = fread($handle, 65536); // 64KB chunks
            if (!$chunk) break;
            $buffer .= $chunk;

            // Split buffer into entries using the "# User@Host:" marker as the start of an entry
            // but keep the "# Time:" if it exists just before it.
            $parts = preg_split('/(?=# Time:)|(?=# User@Host:)/', $buffer);
            
            // The last part might be incomplete, keep it in buffer
            $buffer = array_pop($parts);

            foreach ($parts as $part) {
                if ($this->processEntry($part)) {
                    $queriesFound++;
                }
            }
        }

        // Final buffer check if it contains a complete entry (less likely but possible)
        if ($this->processEntry($buffer)) {
            $queriesFound++;
        }

        $newOffset = ftell($handle);
        fclose($handle);

        $appSettings->update([
            'slow_query_log_offset' => $newOffset,
            'last_slow_query_check' => now(),
        ]);

        if ($queriesFound > 0) {
            Log::info("SlowQueryService: Parsed {$queriesFound} new slow queries.");
        }
    }

    /**
     * Process a single entry from the log.
     */
    protected function processEntry($entryText)
    {
        $entryText = trim($entryText);
        if (empty($entryText)) return false;

        // Extract metadata using regex
        // Format:
        // # Time: 2026-05-15T12:00:00.123456Z
        // # User@Host: root[root] @ localhost []  Id: 82438
        // # Query_time: 2.340000  Lock_time: 0.000000 Rows_sent: 1  Rows_examined: 25
        
        $data = [
            'timestamp' => null,
            'user_host' => null,
            'query_time' => 0,
            'lock_time' => 0,
            'rows_sent' => 0,
            'rows_examined' => 0,
            'query' => '',
        ];

        // Extract Time
        if (preg_match('/# Time: ([\d\-\:T\.Z]+)/', $entryText, $matches)) {
            try {
                $data['timestamp'] = Carbon::parse($matches[1]);
            } catch (\Exception $e) {}
        }

        // Extract User@Host
        if (preg_match('/# User@Host: (.*) @ (.*) \[.*\]/', $entryText, $matches)) {
            $data['user_host'] = $matches[1] . ' @ ' . $matches[2];
        }

        // Extract Metrics
        if (preg_match('/# Query_time: ([\d\.]+)  Lock_time: ([\d\.]+) Rows_sent: (\d+)  Rows_examined: (\d+)/', $entryText, $matches)) {
            $data['query_time'] = (float) $matches[1];
            $data['lock_time'] = (float) $matches[2];
            $data['rows_sent'] = (int) $matches[3];
            $data['rows_examined'] = (int) $matches[4];
        }

        // Extract Query (everything after the last comment line)
        $lines = explode("\n", $entryText);
        $queryLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (str_starts_with($line, '#')) continue;
            if (str_starts_with($line, 'SET timestamp=')) {
                // If we didn't get a timestamp from # Time, use this
                if (!$data['timestamp'] && preg_match('/SET timestamp=(\d+)/', $line, $tsMatches)) {
                    $data['timestamp'] = Carbon::createFromTimestamp($tsMatches[1]);
                }
                continue;
            }
            $queryLines[] = $line;
        }
        $data['query'] = implode("\n", $queryLines);

        if (empty($data['query'])) return false;
        if (!$data['timestamp']) $data['timestamp'] = now();

        // Generate a hash to avoid duplicates
        $hash = hash('sha256', $data['timestamp']->toDateTimeString() . $data['user_host'] . $data['query']);

        // Save to database
        try {
            SlowQuery::firstOrCreate(
                ['query_hash' => $hash],
                [
                    'timestamp' => $data['timestamp'],
                    'user_host' => $data['user_host'],
                    'query_time' => $data['query_time'],
                    'lock_time' => $data['lock_time'],
                    'rows_sent' => $data['rows_sent'],
                    'rows_examined' => $data['rows_examined'],
                    'query' => $data['query'],
                ]
            );
            return true;
        } catch (\Exception $e) {
            // Probably a duplicate hash or DB error
            return false;
        }
    }
}
