<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (!Schema::hasColumn('application_status', 'allow_doctor_set_procedure_price')) {
                $table->boolean('allow_doctor_set_procedure_price')->default(0)->after('procedure_category_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (Schema::hasColumn('application_status', 'allow_doctor_set_procedure_price')) {
                $table->dropColumn('allow_doctor_set_procedure_price');
            }
        });
    }
};
