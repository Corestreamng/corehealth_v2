<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add Fields to Stores Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Add additional fields needed for store management
 *
 * Related Models: Store
 */
class AddFieldsToStoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'code')) {
                $table->string('code', 10)->nullable()->unique();
            }
            if (!Schema::hasColumn('stores', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('stores', 'store_type')) {
                $table->enum('store_type', ['pharmacy', 'warehouse', 'theatre', 'ward', 'other'])->default('pharmacy');
            }
            if (!Schema::hasColumn('stores', 'is_default')) {
                $table->boolean('is_default')->default(false);
            }
            if (!Schema::hasColumn('stores', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable();
            }
        });

        // Add foreign key for manager
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'manager_id')) {
                $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'manager_id')) {
                $table->dropForeign(['manager_id']);
            }

            $columns = ['code', 'description', 'store_type', 'is_default', 'manager_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('stores', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
