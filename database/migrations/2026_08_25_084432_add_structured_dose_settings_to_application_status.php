<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->boolean('enable_structured_dose')->default(1)->after('require_treatment_plan_in_consult');
            $table->string('default_dose_mode', 20)->default('structured')->after('enable_structured_dose');
        });
    }

    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn(['enable_structured_dose', 'default_dose_mode']);
        });
    }
};
