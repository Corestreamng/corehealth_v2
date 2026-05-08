<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a standalone maternity_partograph table linked to the
     * enrollment (not the delivery record), enabling partograph recording
     * BEFORE a delivery record exists (i.e. during active labour) as well
     * as post-delivery monitoring.
     */
    public function up(): void
    {
        Schema::create('maternity_partograph', function (Blueprint $table) {
            $table->id();

            // Enrollment-level link (always present)
            $table->foreignId('enrollment_id')
                ->constrained('maternity_enrollments')
                ->cascadeOnDelete();

            // Delivery-record link (nullable — null = pre-delivery entry)
            $table->foreignId('delivery_record_id')
                ->nullable()
                ->constrained('delivery_records')
                ->nullOnDelete();

            // Phase: when in the continuum of care this entry was recorded
            $table->enum('phase', ['pre_delivery', 'post_delivery'])
                ->default('pre_delivery');

            // ── Labour / Foetal Monitoring ─────────────────────────
            $table->dateTime('recorded_at');
            $table->decimal('cervical_dilation_cm', 3, 1)->nullable();
            $table->string('descent_of_head')->nullable();
            $table->smallInteger('contractions_per_10_min')->nullable();
            $table->smallInteger('contraction_duration_sec')->nullable();
            $table->string('foetal_heart_rate')->nullable(); // varchar; supports text like "132 (regular)"

            $table->enum('amniotic_fluid', ['intact', 'clear', 'meconium_stained', 'bloody', 'absent'])
                ->nullable();
            $table->enum('moulding', ['none', '+', '++', '+++'])->nullable();

            // ── Maternal Observations ─────────────────────────────
            $table->string('maternal_bp')->nullable();   // e.g. "120/80"
            $table->smallInteger('maternal_pulse')->nullable();
            $table->decimal('maternal_temp', 4, 1)->nullable();
            $table->integer('urine_output_ml')->nullable();
            $table->enum('urine_protein', ['nil', 'trace', '+', '++', '+++'])->nullable();

            // ── Interventions ──────────────────────────────────────
            $table->string('oxytocin_dose')->nullable();
            $table->string('iv_fluids')->nullable();
            $table->text('medications')->nullable();

            // ── Audit ──────────────────────────────────────────────
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternity_partograph');
    }
};
