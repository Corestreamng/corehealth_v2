<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up()
    {
        if (!Schema::hasTable('medication_schedules')) {
            return;
        }

        $table = 'medication_schedules';
        $fkName = 'medication_schedules_product_or_service_request_id_foreign';

        // Step 1: Drop existing FK on product_or_service_request_id (if present)
        if ($this->foreignKeyExists($table, $fkName)) {
            Schema::table($table, function ($t) {
                $t->dropForeign(['product_or_service_request_id']);
            });
        }

        // Step 2: Make product_or_service_request_id nullable
        if (Schema::hasColumn($table, 'product_or_service_request_id')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `product_or_service_request_id` BIGINT UNSIGNED NULL");
        }

        // Step 3: Null out orphaned references
        if (Schema::hasTable('product_or_service_requests') && Schema::hasColumn($table, 'product_or_service_request_id')) {
            DB::statement("
                UPDATE `{$table}`
                SET product_or_service_request_id = NULL
                WHERE product_or_service_request_id IS NOT NULL
                  AND product_or_service_request_id NOT IN (SELECT id FROM product_or_service_requests)
            ");
        }

        // Step 4: Re-add FK with nullOnDelete (only if not already present)
        if (Schema::hasColumn($table, 'product_or_service_request_id') && !$this->foreignKeyExists($table, $fkName)) {
            Schema::table($table, function ($t) {
                $t->foreign('product_or_service_request_id')
                  ->references('id')->on('product_or_service_requests')
                  ->nullOnDelete();
            });
        }

        // Step 5: Add product_id column + FK
        if (!Schema::hasColumn($table, 'product_id')) {
            Schema::table($table, function ($t) {
                $t->unsignedBigInteger('product_id')->nullable()->after('product_or_service_request_id');
                $t->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        } elseif (!$this->foreignKeyExists($table, 'medication_schedules_product_id_foreign')) {
            Schema::table($table, function ($t) {
                $t->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }

        // Step 6: Add drug_source column
        if (!Schema::hasColumn($table, 'drug_source')) {
            Schema::table($table, function ($t) {
                $t->string('drug_source', 30)->default('pharmacy_dispensed')->after('product_id');
            });
        }

        // Step 7: Add external_drug_name column
        if (!Schema::hasColumn($table, 'external_drug_name')) {
            Schema::table($table, function ($t) {
                $t->string('external_drug_name', 255)->nullable()->after('drug_source');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('medication_schedules')) {
            return;
        }

        $table = 'medication_schedules';

        if (Schema::hasColumn($table, 'external_drug_name')) {
            Schema::table($table, function ($t) {
                $t->dropColumn('external_drug_name');
            });
        }
        if (Schema::hasColumn($table, 'drug_source')) {
            Schema::table($table, function ($t) {
                $t->dropColumn('drug_source');
            });
        }
        if (Schema::hasColumn($table, 'product_id')) {
            if ($this->foreignKeyExists($table, 'medication_schedules_product_id_foreign')) {
                Schema::table($table, function ($t) {
                    $t->dropForeign(['product_id']);
                });
            }
            Schema::table($table, function ($t) {
                $t->dropColumn('product_id');
            });
        }

        // Revert product_or_service_request_id to NOT NULL with plain FK
        $fkName = 'medication_schedules_product_or_service_request_id_foreign';

        if ($this->foreignKeyExists($table, $fkName)) {
            Schema::table($table, function ($t) {
                $t->dropForeign(['product_or_service_request_id']);
            });
        }

        if (Schema::hasColumn($table, 'product_or_service_request_id')) {
            DB::statement("DELETE FROM `{$table}` WHERE product_or_service_request_id IS NULL");
            DB::statement("ALTER TABLE `{$table}` MODIFY `product_or_service_request_id` BIGINT UNSIGNED NOT NULL");
        }

        if (!$this->foreignKeyExists($table, $fkName) && Schema::hasColumn($table, 'product_or_service_request_id')) {
            Schema::table($table, function ($t) {
                $t->foreign('product_or_service_request_id')
                  ->references('id')->on('product_or_service_requests');
            });
        }
    }

    /**
     * Check if a foreign key constraint exists on a table.
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $db = config('database.connections.mysql.database', env('DB_DATABASE'));
        $result = DB::select("
            SELECT COUNT(*) AS cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$db, $table, $foreignKey]);

        return $result[0]->cnt > 0;
    }
};
