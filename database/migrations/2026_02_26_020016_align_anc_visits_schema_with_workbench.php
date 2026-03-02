<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $tableName = 'anc_visits';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'patient_id')) {
                $table->foreignId('patient_id')->nullable()->after('enrollment_id')->constrained('patients')->nullOnDelete();
            }

            if (!Schema::hasColumn($tableName, 'encounter_id')) {
                $table->unsignedBigInteger('encounter_id')->nullable()->after('patient_id');
            }

            if (!Schema::hasColumn($tableName, 'blood_pressure_systolic')) {
                $table->smallInteger('blood_pressure_systolic')->nullable()->after('weight_kg');
            }

            if (!Schema::hasColumn($tableName, 'blood_pressure_diastolic')) {
                $table->smallInteger('blood_pressure_diastolic')->nullable()->after('blood_pressure_systolic');
            }

            if (!Schema::hasColumn($tableName, 'fundal_height_cm')) {
                $table->decimal('fundal_height_cm', 5, 1)->nullable()->after('blood_pressure_diastolic');
            }

            if (!Schema::hasColumn($tableName, 'presentation')) {
                $table->string('presentation')->nullable()->after('fundal_height_cm');
            }

            if (!Schema::hasColumn($tableName, 'fetal_heart_rate')) {
                $table->smallInteger('fetal_heart_rate')->nullable()->after('presentation');
            }

            if (!Schema::hasColumn($tableName, 'clinical_notes')) {
                $table->text('clinical_notes')->nullable()->after('haemoglobin');
            }
        });

        if (Schema::hasColumn($tableName, 'enrollment_id') && Schema::hasColumn($tableName, 'patient_id')) {
            DB::statement("UPDATE anc_visits av JOIN maternity_enrollments me ON me.id = av.enrollment_id SET av.patient_id = me.patient_id WHERE av.patient_id IS NULL");
        }

        if (Schema::hasColumn($tableName, 'blood_pressure') && Schema::hasColumn($tableName, 'blood_pressure_systolic') && Schema::hasColumn($tableName, 'blood_pressure_diastolic')) {
            DB::statement("UPDATE anc_visits SET blood_pressure_systolic = CAST(SUBSTRING_INDEX(blood_pressure, '/', 1) AS UNSIGNED), blood_pressure_diastolic = CAST(SUBSTRING_INDEX(blood_pressure, '/', -1) AS UNSIGNED) WHERE blood_pressure IS NOT NULL AND blood_pressure LIKE '%/%' AND blood_pressure_systolic IS NULL AND blood_pressure_diastolic IS NULL");
        }

        if (Schema::hasColumn($tableName, 'height_of_fundus') && Schema::hasColumn($tableName, 'fundal_height_cm')) {
            DB::statement("UPDATE anc_visits SET fundal_height_cm = CAST(height_of_fundus AS DECIMAL(5,1)) WHERE fundal_height_cm IS NULL AND height_of_fundus REGEXP '^[0-9]+(\\\\.[0-9]+)?$'");
        }

        if (Schema::hasColumn($tableName, 'presentation_and_position') && Schema::hasColumn($tableName, 'presentation')) {
            DB::statement("UPDATE anc_visits SET presentation = presentation_and_position WHERE presentation IS NULL AND presentation_and_position IS NOT NULL");
        }

        if (Schema::hasColumn($tableName, 'foetal_heart_rate') && Schema::hasColumn($tableName, 'fetal_heart_rate')) {
            DB::statement("UPDATE anc_visits SET fetal_heart_rate = foetal_heart_rate WHERE fetal_heart_rate IS NULL AND foetal_heart_rate IS NOT NULL");
        }

        if (Schema::hasColumn($tableName, 'notes') && Schema::hasColumn($tableName, 'clinical_notes')) {
            DB::statement("UPDATE anc_visits SET clinical_notes = notes WHERE clinical_notes IS NULL AND notes IS NOT NULL");
        }
    }

    public function down()
    {
        $tableName = 'anc_visits';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $dropColumns = [
                'patient_id',
                'encounter_id',
                'blood_pressure_systolic',
                'blood_pressure_diastolic',
                'fundal_height_cm',
                'presentation',
                'fetal_heart_rate',
                'clinical_notes',
            ];

            $existing = array_values(array_filter($dropColumns, fn ($col) => Schema::hasColumn($tableName, $col)));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
