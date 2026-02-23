<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalColumnsToLabAndImagingRequests extends Migration
{
    /**
     * Run the migrations.
     * Adds pending result columns and approval tracking to both
     * lab_service_requests and imaging_service_requests tables.
     */
    public function up()
    {
        // ── Lab Service Requests ──
        Schema::table('lab_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_service_requests', 'pending_result')) {
                $table->longText('pending_result')->nullable()->after('result');
            }
            if (!Schema::hasColumn('lab_service_requests', 'pending_result_data')) {
                $table->json('pending_result_data')->nullable()->after('result_data');
            }
            if (!Schema::hasColumn('lab_service_requests', 'pending_attachments')) {
                $table->json('pending_attachments')->nullable()->after('attachments');
            }
            if (!Schema::hasColumn('lab_service_requests', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (!Schema::hasColumn('lab_service_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (!Schema::hasColumn('lab_service_requests', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable();
            }
            if (!Schema::hasColumn('lab_service_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            if (!Schema::hasColumn('lab_service_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });

        // Add foreign keys separately (avoids issues with column existence checks)
        Schema::table('lab_service_requests', function (Blueprint $table) {
            if (Schema::hasColumn('lab_service_requests', 'approved_by')) {
                try {
                    $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
                } catch (\Exception $e) {
                    // FK may already exist
                }
            }
            if (Schema::hasColumn('lab_service_requests', 'rejected_by')) {
                try {
                    $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
                } catch (\Exception $e) {
                    // FK may already exist
                }
            }
        });

        // ── Imaging Service Requests ──
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('imaging_service_requests', 'pending_result')) {
                $table->longText('pending_result')->nullable()->after('result');
            }
            if (!Schema::hasColumn('imaging_service_requests', 'pending_result_data')) {
                $table->json('pending_result_data')->nullable()->after('result_data');
            }
            if (!Schema::hasColumn('imaging_service_requests', 'pending_attachments')) {
                $table->json('pending_attachments')->nullable()->after('attachments');
            }
            if (!Schema::hasColumn('imaging_service_requests', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (!Schema::hasColumn('imaging_service_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (!Schema::hasColumn('imaging_service_requests', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable();
            }
            if (!Schema::hasColumn('imaging_service_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            if (!Schema::hasColumn('imaging_service_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            if (Schema::hasColumn('imaging_service_requests', 'approved_by')) {
                try {
                    $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
                } catch (\Exception $e) {
                    // FK may already exist
                }
            }
            if (Schema::hasColumn('imaging_service_requests', 'rejected_by')) {
                try {
                    $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
                } catch (\Exception $e) {
                    // FK may already exist
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        $columns = [
            'pending_result', 'pending_result_data', 'pending_attachments',
            'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason'
        ];

        Schema::table('lab_service_requests', function (Blueprint $table) use ($columns) {
            // Drop FKs first
            try { $table->dropForeign(['approved_by']); } catch (\Exception $e) {}
            try { $table->dropForeign(['rejected_by']); } catch (\Exception $e) {}

            foreach ($columns as $col) {
                if (Schema::hasColumn('lab_service_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) use ($columns) {
            try { $table->dropForeign(['approved_by']); } catch (\Exception $e) {}
            try { $table->dropForeign(['rejected_by']); } catch (\Exception $e) {}

            foreach ($columns as $col) {
                if (Schema::hasColumn('imaging_service_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
