<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $tableName = 'maternity_enrollments';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'booking_date')) {
                $table->date('booking_date')->nullable()->after('enrollment_date');
            }

            if (!Schema::hasColumn($tableName, 'gestational_age_at_booking')) {
                $table->smallInteger('gestational_age_at_booking')->nullable()->after('edd');
            }

            if (!Schema::hasColumn($tableName, 'parity')) {
                $table->smallInteger('parity')->nullable()->after('gravida');
            }

            if (!Schema::hasColumn($tableName, 'alive')) {
                $table->smallInteger('alive')->default(0)->after('parity');
            }

            if (!Schema::hasColumn($tableName, 'abortion_miscarriage')) {
                $table->smallInteger('abortion_miscarriage')->default(0)->after('alive');
            }

            if (!Schema::hasColumn($tableName, 'booking_bmi')) {
                $table->decimal('booking_bmi', 5, 1)->nullable()->after('booking_weight_kg');
            }

            if (!Schema::hasColumn($tableName, 'booking_bp')) {
                $table->string('booking_bp')->nullable()->after('booking_bmi');
            }

            if (!Schema::hasColumn($tableName, 'birth_plan_notes')) {
                $table->text('birth_plan_notes')->nullable()->after('risk_factors');
            }

            if (!Schema::hasColumn($tableName, 'preferred_delivery_place')) {
                $table->string('preferred_delivery_place')->nullable()->after('birth_plan_notes');
            }

            if (!Schema::hasColumn($tableName, 'outcome_summary')) {
                $table->text('outcome_summary')->nullable()->after('completed_at');
            }
        });

        // Backfill compatibility values from legacy columns where present.
        if (Schema::hasColumn($tableName, 'enrollment_date') && Schema::hasColumn($tableName, 'booking_date')) {
            DB::statement("UPDATE {$tableName} SET booking_date = enrollment_date WHERE booking_date IS NULL AND enrollment_date IS NOT NULL");
        }

        if (Schema::hasColumn($tableName, 'para') && Schema::hasColumn($tableName, 'parity')) {
            DB::statement("UPDATE {$tableName} SET parity = para WHERE parity IS NULL AND para IS NOT NULL");
        }

        if (Schema::hasColumn($tableName, 'living_children') && Schema::hasColumn($tableName, 'alive')) {
            DB::statement("UPDATE {$tableName} SET alive = living_children WHERE alive IS NULL");
        }

        if (Schema::hasColumn($tableName, 'abortions') && Schema::hasColumn($tableName, 'abortion_miscarriage')) {
            DB::statement("UPDATE {$tableName} SET abortion_miscarriage = abortions WHERE abortion_miscarriage IS NULL");
        }
    }

    public function down()
    {
        $tableName = 'maternity_enrollments';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $dropColumns = [
                'booking_date',
                'gestational_age_at_booking',
                'parity',
                'alive',
                'abortion_miscarriage',
                'booking_bmi',
                'booking_bp',
                'birth_plan_notes',
                'preferred_delivery_place',
                'outcome_summary',
            ];

            $existing = array_values(array_filter($dropColumns, fn ($col) => Schema::hasColumn($tableName, $col)));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
