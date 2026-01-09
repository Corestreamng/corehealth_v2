<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vaccine_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('vaccine_schedule_templates')->cascadeOnDelete();
            $table->string('vaccine_name'); // e.g., "BCG", "OPV", "Pentavalent"
            $table->string('vaccine_code')->nullable(); // Standard vaccine code
            $table->integer('dose_number')->default(1); // 1, 2, 3 for multi-dose vaccines
            $table->string('dose_label')->nullable(); // "OPV-0", "OPV-1", "Penta-1", etc.
            $table->integer('age_days')->default(0); // Age in days when vaccine should be given (0 = at birth)
            $table->string('age_display'); // Human readable: "At Birth", "6 Weeks", "14 Weeks"
            $table->string('route')->nullable(); // "IM", "SC", "Oral", "ID"
            $table->string('site')->nullable(); // "Left Thigh", "Right Arm", etc.
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['template_id', 'age_days']);
            $table->index(['template_id', 'vaccine_name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vaccine_schedule_items');
    }
};
