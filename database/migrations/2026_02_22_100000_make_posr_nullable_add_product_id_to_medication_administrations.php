<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakePosrNullableAddProductIdToMedicationAdministrations extends Migration
{
    /**
     * §5.1: Direct administrations (patient_own, unbilled ward_stock) have no POSR linkage,
     * so product_or_service_request_id must be nullable. Also adds product_id for direct
     * ward stock entries where the product can't be derived from POSR/ProductRequest chain.
     */
    public function up()
    {
        if (!Schema::hasTable('medication_administrations')) {
            return; // Table doesn't exist yet, nothing to migrate
        }

        $table = 'medication_administrations';
        $fkName = 'medication_administrations_product_or_service_request_id_foreign';

        // Step 1: Drop the existing FK on product_or_service_request_id (if it exists)
        if ($this->foreignKeyExists($table, $fkName)) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['product_or_service_request_id']);
            });
        }

        // Step 2: Make product_or_service_request_id nullable (idempotent — safe to re-run)
        if (Schema::hasColumn($table, 'product_or_service_request_id')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `product_or_service_request_id` BIGINT UNSIGNED NULL");
        }

        // Step 3: Null out orphaned references that don't exist in product_or_service_requests
        if (Schema::hasTable('product_or_service_requests') && Schema::hasColumn($table, 'product_or_service_request_id')) {
            DB::statement("
                UPDATE `{$table}`
                SET product_or_service_request_id = NULL
                WHERE product_or_service_request_id IS NOT NULL
                  AND product_or_service_request_id NOT IN (SELECT id FROM product_or_service_requests)
            ");
        }

        // Step 4: Re-add the FK with onDelete('set null') — only if not already present
        if (Schema::hasColumn($table, 'product_or_service_request_id') && !$this->foreignKeyExists($table, $fkName)) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreign('product_or_service_request_id')
                  ->references('id')->on('product_or_service_requests')
                  ->onDelete('set null');
            });
        }

        // Step 5: Add product_id for direct ward stock entries
        if (!Schema::hasColumn($table, 'product_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('product_id')->nullable()->after('patient_id');
                $t->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            });
        } elseif (!$this->foreignKeyExists($table, 'medication_administrations_product_id_foreign')) {
            // Column exists but FK is missing — add it
            Schema::table($table, function (Blueprint $t) {
                $t->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (!Schema::hasTable('medication_administrations')) {
            return;
        }

        $table = 'medication_administrations';

        // Drop product_id FK + column
        if (Schema::hasColumn($table, 'product_id')) {
            if ($this->foreignKeyExists($table, 'medication_administrations_product_id_foreign')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['product_id']);
                });
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('product_id');
            });
        }

        // Restore product_or_service_request_id to NOT NULL with plain FK
        $fkName = 'medication_administrations_product_or_service_request_id_foreign';

        if ($this->foreignKeyExists($table, $fkName)) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['product_or_service_request_id']);
            });
        }

        if (Schema::hasColumn($table, 'product_or_service_request_id')) {
            // Null out any NULLs before making NOT NULL (avoid data errors)
            DB::statement("DELETE FROM `{$table}` WHERE product_or_service_request_id IS NULL");
            DB::statement("ALTER TABLE `{$table}` MODIFY `product_or_service_request_id` BIGINT UNSIGNED NOT NULL");
        }

        if (!$this->foreignKeyExists($table, $fkName) && Schema::hasColumn($table, 'product_or_service_request_id')) {
            Schema::table($table, function (Blueprint $t) {
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
}
