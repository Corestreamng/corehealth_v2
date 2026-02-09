<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Resetting last_bed_billing_date to yesterday...\n";
$yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
DB::table('application_status')->where('id', 1)->update(['last_bed_billing_date' => $yesterday]);
echo "Done! Next web request will trigger billing.\n";