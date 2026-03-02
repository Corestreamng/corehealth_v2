<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align delivery_records columns with Model $fillable and controller fields.
     *
     * Adds:  patient_id, encounter_id, delivery_date, delivery_time,
     *        duration_of_labour_hours, placenta_complete, placenta_notes,
     *        perineal_tear_degree, oxytocin_given, number_of_babies
     *
     * Drops: date_of_delivery, duration_of_labour, placenta_delivery, perineal_tear
     *        (replaced by the new columns above)
     */
    public function up()
    {
        Schema::table('delivery_records', function (Blueprint $table) {
            // ── Add missing columns ───────────────────────────
            $table->foreignId('patient_id')->nullable()->after('enrollment_id')->constrained('patients')->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->after('patient_id')->constrained('encounters')->nullOnDelete();
            $table->dateTime('delivery_date')->nullable()->after('encounter_id');
            $table->time('delivery_time')->nullable()->after('delivery_date');
            $table->decimal('duration_of_labour_hours', 5, 1)->nullable()->after('delivery_time');
            $table->boolean('placenta_complete')->default(true)->after('blood_loss_ml');
            $table->text('placenta_notes')->nullable()->after('placenta_complete');
            $table->string('perineal_tear_degree')->nullable()->after('placenta_notes');
            $table->boolean('oxytocin_given')->default(false)->after('perineal_tear_degree');
            $table->integer('number_of_babies')->default(1)->after('oxytocin_given');

            // ── Drop columns replaced by the new ones ─────────
            $table->dropColumn([
                'date_of_delivery',
                'duration_of_labour',
                'placenta_delivery',
                'perineal_tear',
            ]);
        });
    }

    public function down()
    {
        Schema::table('delivery_records', function (Blueprint $table) {
            // Restore original columns
            $table->dateTime('date_of_delivery')->nullable()->after('encounter_id');
            $table->string('duration_of_labour')->nullable()->after('place_of_delivery');
            $table->enum('placenta_delivery', ['complete', 'incomplete', 'manual_removal'])->default('complete')->after('blood_loss_ml');
            $table->enum('perineal_tear', ['none', 'first_degree', 'second_degree', 'third_degree', 'fourth_degree'])->default('none')->after('placenta_delivery');

            // Drop added columns
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['encounter_id']);
            $table->dropColumn([
                'patient_id', 'encounter_id',
                'delivery_date', 'delivery_time',
                'duration_of_labour_hours',
                'placenta_complete', 'placenta_notes',
                'perineal_tear_degree', 'oxytocin_given',
                'number_of_babies',
            ]);
        });
    }
};
