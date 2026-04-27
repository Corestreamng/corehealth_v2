<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('morgue_admissions', function (Blueprint $table) {
            if (!Schema::hasColumn('morgue_admissions', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
            if (!Schema::hasColumn('morgue_admissions', 'current_service_request_id')) {
                $table->foreignId('current_service_request_id')->nullable()->after('daily_service_id')->constrained('product_or_service_requests');
            }
        });
    }

    public function down()
    {
        Schema::table('morgue_admissions', function (Blueprint $table) {
            $table->dropForeign(['current_service_request_id']);
            $table->dropColumn(['notes', 'current_service_request_id']);
        });
    }
};
