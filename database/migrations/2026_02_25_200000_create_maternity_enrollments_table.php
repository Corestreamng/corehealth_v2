<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maternity_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('enrolled_by')->constrained('users');
            $table->unsignedBigInteger('service_request_id')->nullable();
            $table->date('enrollment_date');

            // Entry point — patient can enter at ANC, delivery, or postnatal
            $table->enum('entry_point', ['anc', 'delivery', 'postnatal'])->default('anc');

            // Obstetric data
            $table->date('lmp')->nullable();
            $table->date('edd')->nullable();
            $table->smallInteger('gravida')->nullable();
            $table->smallInteger('para')->nullable();
            $table->smallInteger('abortions')->default(0);
            $table->smallInteger('living_children')->default(0);

            // Mother's data at booking
            $table->enum('blood_group', ['A+','A-','B+','B-','AB+','AB-','O+','O-'])->nullable();
            $table->enum('genotype', ['AA','AS','AC','SS','SC','Others'])->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('booking_weight_kg', 5, 2)->nullable();
            $table->string('pelvis_assessment')->nullable();
            $table->string('nipple_assessment')->nullable();
            $table->text('general_condition')->nullable();

            // Risk
            $table->enum('risk_level', ['low','moderate','high','very_high'])->default('low');
            $table->json('risk_factors')->nullable();

            // Status
            $table->enum('ante_natal_records', ['booked','un-booked'])->default('booked');
            $table->enum('status', ['active','delivered','postnatal','completed','transferred','deceased'])->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_request_id')->references('id')->on('product_or_service_requests')->nullOnDelete();
            $table->index(['patient_id', 'status']);
            $table->index('status');
            $table->index('edd');
        });
    }

    public function down()
    {
        Schema::dropIfExists('maternity_enrollments');
    }
};
