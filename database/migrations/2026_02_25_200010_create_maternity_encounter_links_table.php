<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maternity_encounter_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('maternity_enrollments')->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->enum('visit_type', ['anc_booking','anc_followup','emergency','delivery','postnatal','immunization','growth_monitoring','other']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'encounter_id']);
            $table->index('visit_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('maternity_encounter_links');
    }
};
