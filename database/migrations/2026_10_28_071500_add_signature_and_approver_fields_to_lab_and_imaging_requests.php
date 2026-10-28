<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_service_requests', 'entered_by_name')) {
                $table->string('entered_by_name')->nullable()->after('result_by');
            }
            if (!Schema::hasColumn('lab_service_requests', 'entered_by_designation')) {
                $table->string('entered_by_designation')->nullable()->after('entered_by_name');
            }
            if (!Schema::hasColumn('lab_service_requests', 'approved_by_name')) {
                $table->string('approved_by_name')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('lab_service_requests', 'approved_by_designation')) {
                $table->string('approved_by_designation')->nullable()->after('approved_by_name');
            }
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('imaging_service_requests', 'entered_by_name')) {
                $table->string('entered_by_name')->nullable()->after('result_by');
            }
            if (!Schema::hasColumn('imaging_service_requests', 'entered_by_designation')) {
                $table->string('entered_by_designation')->nullable()->after('entered_by_name');
            }
            if (!Schema::hasColumn('imaging_service_requests', 'approved_by_name')) {
                $table->string('approved_by_name')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('imaging_service_requests', 'approved_by_designation')) {
                $table->string('approved_by_designation')->nullable()->after('approved_by_name');
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
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $cols = ['entered_by_name', 'entered_by_designation', 'approved_by_name', 'approved_by_designation'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('lab_service_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $cols = ['entered_by_name', 'entered_by_designation', 'approved_by_name', 'approved_by_designation'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('imaging_service_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
