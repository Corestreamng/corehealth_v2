<?php
/**
 * Debug script to check why budgets list is empty
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\FiscalYear;
use Illuminate\Support\Facades\DB;

echo "=== BUDGET DEBUG SCRIPT ===\n\n";

// 1. Check if budgets table exists
echo "1. Checking budgets table...\n";
try {
    $tableExists = DB::select("SHOW TABLES LIKE 'budgets'");
    if (empty($tableExists)) {
        echo "   ❌ 'budgets' table does NOT exist!\n";
        echo "   Run: php artisan migrate\n\n";
    } else {
        echo "   ✅ 'budgets' table exists\n";

        // Show table structure
        echo "\n   Table structure:\n";
        $columns = DB::select("DESCRIBE budgets");
        foreach ($columns as $col) {
            echo "   - {$col->Field} ({$col->Type})\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 2. Check budget count
echo "\n2. Checking budget records...\n";
try {
    $count = Budget::count();
    echo "   Total budgets in database: {$count}\n";

    if ($count > 0) {
        echo "\n   Budget records:\n";
        $budgets = Budget::with(['fiscalYear', 'department'])->limit(10)->get();
        foreach ($budgets as $budget) {
            echo "   - ID: {$budget->id}, Name: {$budget->name}, Status: {$budget->status}, ";
            echo "FY: " . ($budget->fiscalYear->year_name ?? 'N/A') . "\n";
        }
    } else {
        echo "   ⚠️ No budget records found. You need to create budgets first.\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 3. Check budget_lines table
echo "\n3. Checking budget_lines table...\n";
try {
    $tableExists = DB::select("SHOW TABLES LIKE 'budget_lines'");
    if (empty($tableExists)) {
        echo "   ❌ 'budget_lines' table does NOT exist!\n";
    } else {
        echo "   ✅ 'budget_lines' table exists\n";
        $lineCount = BudgetLine::count();
        echo "   Total budget lines: {$lineCount}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 4. Check fiscal years
echo "\n4. Checking fiscal years...\n";
try {
    $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
    echo "   Total fiscal years: " . $fiscalYears->count() . "\n";
    foreach ($fiscalYears as $fy) {
        echo "   - ID: {$fy->id}, Name: {$fy->year_name}, Status: {$fy->status}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 5. Check the datatable route
echo "\n5. Checking datatable query...\n";
try {
    $query = Budget::with(['fiscalYear', 'department', 'createdBy'])
        ->select('budgets.*');

    echo "   SQL: " . $query->toSql() . "\n";
    echo "   Record count: " . $query->count() . "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 6. Raw query check
echo "\n6. Raw database check...\n";
try {
    $rawBudgets = DB::table('budgets')->get();
    echo "   Raw budget count: " . $rawBudgets->count() . "\n";

    if ($rawBudgets->count() > 0) {
        echo "   First record: " . json_encode($rawBudgets->first()) . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
