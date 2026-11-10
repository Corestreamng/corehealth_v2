<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ai_scribe_audits')) {
            Schema::create('ai_scribe_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 32)->default('doctor');
                $table->string('taxonomy_type', 32)->default('clerking');
                $table->longText('original_transcript')->nullable();
                $table->longText('baseline_editor_content')->nullable();
                $table->json('structured_note_json')->nullable();
                $table->longText('final_signed_note')->nullable();
                $table->json('orders_extracted_json')->nullable();
                $table->json('orders_dispatched_json')->nullable();
                $table->string('model_used', 64)->nullable();
                $table->string('provider', 64)->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamps();

                $table->index(['patient_id', 'created_at']);
                $table->index(['encounter_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_scribe_audits');
    }
};
