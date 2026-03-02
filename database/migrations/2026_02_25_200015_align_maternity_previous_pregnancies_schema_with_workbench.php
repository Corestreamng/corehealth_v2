<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $tableName = 'maternity_previous_pregnancies';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'place_of_delivery')) {
                $table->string('place_of_delivery')->nullable()->after('year');
            }

            if (!Schema::hasColumn($tableName, 'duration_weeks')) {
                $table->smallInteger('duration_weeks')->nullable()->after('place_of_delivery');
            }

            if (!Schema::hasColumn($tableName, 'complications')) {
                $table->text('complications')->nullable()->after('duration_weeks');
            }

            if (!Schema::hasColumn($tableName, 'type_of_labour')) {
                $table->text('type_of_labour')->nullable()->after('complications');
            }

            if (!Schema::hasColumn($tableName, 'baby_alive')) {
                $table->boolean('baby_alive')->default(false)->after('type_of_labour');
            }

            if (!Schema::hasColumn($tableName, 'baby_dead')) {
                $table->boolean('baby_dead')->default(false)->after('baby_alive');
            }

            if (!Schema::hasColumn($tableName, 'baby_stillbirth')) {
                $table->boolean('baby_stillbirth')->default(false)->after('baby_dead');
            }

            if (!Schema::hasColumn($tableName, 'baby_sex')) {
                $table->enum('baby_sex', ['male', 'female'])->nullable()->after('baby_stillbirth');
            }

            if (!Schema::hasColumn($tableName, 'present_health')) {
                $table->string('present_health')->nullable()->after('birth_weight_kg');
            }

            if (!Schema::hasColumn($tableName, 'notes')) {
                $table->text('notes')->nullable()->after('present_health');
            }
        });

        if (Schema::hasColumn($tableName, 'duration_of_pregnancy') && Schema::hasColumn($tableName, 'duration_weeks')) {
            DB::statement("UPDATE {$tableName} SET duration_weeks = CAST(duration_of_pregnancy AS UNSIGNED) WHERE duration_weeks IS NULL AND duration_of_pregnancy REGEXP '^[0-9]+$'");
        }

        if (Schema::hasColumn($tableName, 'ante_natal_complications') && Schema::hasColumn($tableName, 'complications')) {
            DB::statement("UPDATE {$tableName} SET complications = ante_natal_complications WHERE complications IS NULL AND ante_natal_complications IS NOT NULL");
        }

        if (Schema::hasColumn($tableName, 'labour_notes') && Schema::hasColumn($tableName, 'type_of_labour')) {
            DB::statement("UPDATE {$tableName} SET type_of_labour = labour_notes WHERE type_of_labour IS NULL AND labour_notes IS NOT NULL");
        }

        if (Schema::hasColumn($tableName, 'sex') && Schema::hasColumn($tableName, 'baby_sex')) {
            DB::statement("UPDATE {$tableName} SET baby_sex = sex WHERE baby_sex IS NULL AND sex IS NOT NULL");
        }

        if (Schema::hasColumn($tableName, 'baby_alive_or_dead')) {
            if (Schema::hasColumn($tableName, 'baby_alive')) {
                DB::statement("UPDATE {$tableName} SET baby_alive = 1 WHERE baby_alive_or_dead = 'alive' AND baby_alive = 0");
            }
            if (Schema::hasColumn($tableName, 'baby_dead')) {
                DB::statement("UPDATE {$tableName} SET baby_dead = 1 WHERE baby_alive_or_dead = 'dead' AND baby_dead = 0");
            }
            if (Schema::hasColumn($tableName, 'baby_stillbirth')) {
                DB::statement("UPDATE {$tableName} SET baby_stillbirth = 1 WHERE baby_alive_or_dead = 'stillbirth' AND baby_stillbirth = 0");
            }
        }
    }

    public function down()
    {
        $tableName = 'maternity_previous_pregnancies';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $dropColumns = [
                'place_of_delivery',
                'duration_weeks',
                'complications',
                'type_of_labour',
                'baby_alive',
                'baby_dead',
                'baby_stillbirth',
                'baby_sex',
                'present_health',
                'notes',
            ];

            $existing = array_values(array_filter($dropColumns, fn ($col) => Schema::hasColumn($tableName, $col)));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
