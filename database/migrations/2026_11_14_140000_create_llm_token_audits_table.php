<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Creates auxiliary audit table for tracking LLM token consumption across
     * both internal hospital clinicians (Local Scribe) and external API callers (Partner SDKs),
     * adhering strictly to the ZERO CORE TABLE SCHEMA MUTATION invariant.
     */
    public function up(): void
    {
        if (!Schema::hasTable('llm_token_audits')) {
            Schema::create('llm_token_audits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('source', 40)->default('local_scribe');
                $table->string('client_id', 100)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('patient_id')->nullable();
                $table->unsignedBigInteger('encounter_id')->nullable();
                $table->string('provider', 40);
                $table->string('model', 80);
                $table->unsignedInteger('prompt_tokens')->default(0);
                $table->unsignedInteger('completion_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->unsignedInteger('latency_ms')->default(0);
                $table->boolean('is_estimated')->default(false);
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['source', 'created_at'], 'idx_llm_tokens_source_date');
                $table->index(['client_id', 'created_at'], 'idx_llm_tokens_client_date');
                $table->index(['user_id', 'created_at'], 'idx_llm_tokens_user_date');
                $table->index(['patient_id', 'encounter_id'], 'idx_llm_tokens_context');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_token_audits');
    }
};
