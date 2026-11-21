<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patient occupation and next-of-kin relationship (registration on web and the staff app).
 * Additive and nullable: existing rows and integrations are unaffected.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'occupation')) {
                $table->string('occupation', 120)->nullable()->after('ethnicity');
            }
            if (!Schema::hasColumn('patients', 'next_of_kin_relationship')) {
                $table->string('next_of_kin_relationship', 60)->nullable()->after('next_of_kin_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach (['occupation', 'next_of_kin_relationship'] as $col) {
                if (Schema::hasColumn('patients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
