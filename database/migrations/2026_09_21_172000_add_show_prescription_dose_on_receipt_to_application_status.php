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
            if (!Schema::hasColumn('application_status', 'show_prescription_dose_on_receipt')) {
                $table->boolean('show_prescription_dose_on_receipt')->default(1)->after('thermal_printer_width');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (Schema::hasColumn('application_status', 'show_prescription_dose_on_receipt')) {
                $table->dropColumn('show_prescription_dose_on_receipt');
            }
        });
    }
};
