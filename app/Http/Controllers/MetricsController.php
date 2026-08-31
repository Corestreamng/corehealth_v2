<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /**
     * Return Prometheus metrics for application health and performance observability.
     *
     * @return Response
     */
    public function index(): Response
    {
        $metrics = [];

        // Application status metric (1 = healthy)
        $metrics[] = '# HELP app_up Operational status of the CoreHealth application (1 = up)';
        $metrics[] = '# TYPE app_up gauge';
        $metrics[] = 'app_up 1';

        // Database connection health
        $dbStatus = 0;

        try {
            DB::connection()->getPdo();
            $dbStatus = 1;
        } catch (\Throwable $e) {
            $dbStatus = 0;
        }
        $metrics[] = '# HELP app_db_up Operational status of the primary database connection (1 = connected)';
        $metrics[] = '# TYPE app_db_up gauge';
        $metrics[] = "app_db_up {$dbStatus}";

        // System memory usage in bytes
        $metrics[] = '# HELP app_memory_usage_bytes Current PHP process memory consumption in bytes';
        $metrics[] = '# TYPE app_memory_usage_bytes gauge';
        $metrics[] = 'app_memory_usage_bytes ' . memory_get_usage(true);

        // Peak system memory usage in bytes
        $metrics[] = '# HELP app_memory_peak_bytes Peak PHP process memory consumption in bytes';
        $metrics[] = '# TYPE app_memory_peak_bytes gauge';
        $metrics[] = 'app_memory_peak_bytes ' . memory_get_peak_usage(true);

        // PHP Version metric tag
        $phpVersion = PHP_VERSION;
        $metrics[] = '# HELP app_info Runtime environment metadata';
        $metrics[] = '# TYPE app_info gauge';
        $metrics[] = 'app_info{php_version="' . $phpVersion . '"} 1';

        $output = implode("\n", $metrics) . "\n";

        return response($output, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
