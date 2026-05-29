<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$tables = [
    'accounts',
    'journal_entries',
    'journal_entry_lines',
    'petty_cash_funds',
    'petty_cash_transactions',
    'bank_reconciliations',
    'fixed_assets',
    'encounters',
    'doctor_appointments',
    'store_requisitions',
    'store_requisition_items',
    'lab_service_requests',
    'imaging_service_requests',
    'medication_schedules',
    'medication_administrations',
    'morgue_admissions',
    'patients',
    'wards',
    'stores',
    'stock',
    'products',
    'hmos',
    'hmo_claims',
    'hmo_remittances',
    'anc_visits',
    'maternity_enrollments',
    'nursing_shifts'
];

echo "=== TABLE ROW COUNTS ===\n";
foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo sprintf("%-30s : %d rows\n", $table, $count);
    } else {
        echo sprintf("%-30s : [TABLE DOES NOT EXIST]\n", $table);
    }
}
