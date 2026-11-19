<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (!Schema::hasColumn('application_status', 'enable_nhmis_results_tracking')) {
                $table->boolean('enable_nhmis_results_tracking')->default(true)->after('nurse_self_approve_imaging_result');
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (Schema::hasColumn('application_status', 'enable_nhmis_results_tracking')) {
                $table->dropColumn('enable_nhmis_results_tracking');
            }
        });
    }
};
