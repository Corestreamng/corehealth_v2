<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoreHmsHealthApiController extends Controller
{
    /**
     * Ultra-fast ping endpoint for liveness checks.
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'pong' => true,
            'facility_id' => appsettings('client_id', 4),
            'facility_name' => appsettings('hospital_name', 'CoreHealth Facility'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Comprehensive health check endpoint.
     */
    public function health(Request $request): JsonResponse
    {
        $dbConnected = false;
        $dbLatencyMs = null;

        try {
            $t0 = microtime(true);
            DB::select('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $t0) * 1000, 2);
            $dbConnected = true;
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        $pendingJobs = 0;

        try {
            $pendingJobs = DB::table('jobs')->count();
        } catch (\Throwable $e) {
            $pendingJobs = 0;
        }

        $failedJobs = 0;

        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            $failedJobs = 0;
        }

        $appStatus = ApplicationStatu::first();

        return response()->json([
            'status' => true,
            'healthy' => $dbConnected,
            'facility' => [
                'id' => appsettings('client_id', 4),
                'name' => appsettings('hospital_name', $appStatus?->hospital_name ?? 'CoreHealth Facility'),
                'goonline' => (int)appsettings('goonline', 0),
                'version' => $appStatus?->version ?? '2.6',
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_time' => now()->toDateTimeString(),
                'queue_driver' => config('queue.default'),
                'pending_queue_jobs' => $pendingJobs,
                'failed_queue_jobs' => $failedJobs,
                'db_connected' => $dbConnected,
                'db_latency_ms' => $dbLatencyMs,
            ],
        ]);
    }
}
