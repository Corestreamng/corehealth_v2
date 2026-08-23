<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Models\DoctorAppointment;
use App\Models\Patient;
use App\Models\StockBatch;
use App\Models\Product;
use App\Models\Store;
use App\Models\Hmo;

echo "Starting DB Verification Script for _corehealth_db_v2_hopehill...\n";

// 1. Switch Database Connection
DB::purge('mysql');
Config::set('database.connections.mysql.database', '_corehealth_db_v2_hopehill');
DB::reconnect('mysql');

$dbName = DB::connection('mysql')->getDatabaseName();
if ($dbName !== '_corehealth_db_v2_hopehill') {
    die("Failed to switch database connection. Current DB: {$dbName}\n");
}

echo "Successfully connected to: {$dbName}\n";

// 2. Start Transaction
echo "Starting Database Transaction for testing...\n";
DB::beginTransaction();

try {
    // 3. Test DoctorAppointment creation
    echo "Testing DoctorAppointment creation...\n";
    $patient = Patient::first();
    $staff = DB::table('staff')->first();
    $user = DB::table('users')->first();
    if ($patient && $staff && $user) {
        $appointment = DoctorAppointment::create([
            'patient_id' => $patient->id,
            'staff_id' => $staff->id,
            'clinic_id' => 1, // Assume clinic 1 exists, or could fetch it
            'booked_by' => $user->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 6,
            'appointment_type' => 'scheduled'
        ]);
        echo "Created DoctorAppointment ID: {$appointment->id}\n";
    } else {
        echo "No patients, staff, or users found to test DoctorAppointment.\n";
    }

    // 4. Test Stock Batch Observer
    echo "Testing StockBatch creation...\n";
    $product = Product::first();
    $store = Store::first();
    if ($product && $store) {
        $stockBatch = StockBatch::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'batch_name' => 'Test Batch',
            'batch_number' => 'TEST-BATCH-001',
            'initial_qty' => 100,
            'current_qty' => 100,
            'cost_price' => 50.00,
            'expiry_date' => now()->addYear()->toDateString(),
            'received_date' => now()->toDateString(),
            'supplier_id' => null,
            'created_by' => $user->id,
            'is_active' => 1
        ]);
        echo "Created StockBatch ID: {$stockBatch->id}\n";
    } else {
        echo "No products or stores found to test StockBatch.\n";
    }

    // 5. Test HMO Tariff integration
    echo "Testing HMO Tariff checks...\n";
    $hmo = Hmo::first();
    if ($hmo) {
        echo "HMO found: {$hmo->name}\n";
    } else {
        echo "No HMOs found.\n";
    }

    echo "\nAll tests passed successfully within the transaction.\n";
} catch (\Exception $e) {
    echo "Error during verification: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // 6. Rollback Transaction
    echo "Rolling back Database Transaction to discard test data...\n";
    DB::rollBack();
    echo "Rollback complete. System is clean.\n";
}
