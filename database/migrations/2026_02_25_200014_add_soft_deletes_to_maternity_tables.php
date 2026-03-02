<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing deleted_at (softDeletes) to maternity tables whose
     * Eloquent models use the SoftDeletes trait.
     */
    public function up()
    {
        $tables = [
            'maternity_medical_history',
            'maternity_previous_pregnancies',
            'maternity_encounter_links',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }

    public function down()
    {
        $tables = [
            'maternity_medical_history',
            'maternity_previous_pregnancies',
            'maternity_encounter_links',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
