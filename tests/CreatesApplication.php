<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Override database strict mode and name for mysql tests to bypass zero-date constraints
        if (env('DB_CONNECTION') === 'mysql') {
            config(['database.connections.mysql.database' => env('DB_DATABASE', '_corehealth_db_v2_test')]);
            config(['database.connections.mysql.strict' => false]);
        }

        return $app;
    }
}
