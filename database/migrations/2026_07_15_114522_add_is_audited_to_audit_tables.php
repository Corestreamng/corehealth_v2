<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsAuditedToAuditTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'product_requests', // For ProductRequest
            'lab_service_requests', // For LabServiceRequest
            'imaging_service_requests', // For ImagingServiceRequest
            'staff_bills',
            'organization_bills',
            'admission_requests', // Admissions
            'nursing_shifts'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'is_audited')) {
                    $t->boolean('is_audited')->default(false)->after('updated_at');
                }
                if (!Schema::hasColumn($table, 'audited_by')) {
                    $t->unsignedBigInteger('audited_by')->nullable()->after('is_audited');
                }
                if (!Schema::hasColumn($table, 'audited_at')) {
                    $t->timestamp('audited_at')->nullable()->after('audited_by');
                }
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
            'staff_bills',
            'organization_bills',
            'admission_requests',
            'nursing_shifts'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['is_audited', 'audited_by', 'audited_at']);
            });
        }
    }
}
