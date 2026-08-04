<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'product_requests',
            'lab_service_requests',
            'imaging_service_requests',
            'procedures',
            'stock_batch_transactions',
            'staff_bills',
            'organization_bills',
            'admission_requests',
            'nursing_shifts'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('is_queried')->default(false);
                $table->unsignedBigInteger('queried_by')->nullable();
                $table->timestamp('queried_at')->nullable();
                $table->text('query_notes')->nullable();
                $table->unsignedBigInteger('query_resolved_by')->nullable();
                $table->timestamp('query_resolved_at')->nullable();
                $table->text('query_resolution_notes')->nullable();

                // We don't add foreign keys here to avoid complex cascades or missing tables on different environments,
                // just index the user reference columns.
                $table->index('queried_by');
                $table->index('query_resolved_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = [
            'product_requests',
            'lab_service_requests',
            'imaging_service_requests',
            'procedures',
            'stock_batch_transactions',
            'staff_bills',
            'organization_bills',
            'admission_requests',
            'nursing_shifts'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn([
                    'is_queried',
                    'queried_by',
                    'queried_at',
                    'query_notes',
                    'query_resolved_by',
                    'query_resolved_at',
                    'query_resolution_notes'
                ]);
            });
        }
    }
};
