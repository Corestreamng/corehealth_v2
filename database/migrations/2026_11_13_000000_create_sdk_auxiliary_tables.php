<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Creates auxiliary tables for the CoreHealth AI Scribe Embeddable SDK
     * adhering strictly to the ZERO CORE TABLE SCHEMA MUTATION invariant.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sdk_clients')) {
            Schema::create('sdk_clients', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('client_id', 100)->unique();
                $table->string('client_secret', 255);
                $table->string('name', 255);
                $table->string('organization', 255)->nullable();
                $table->json('allowed_scopes')->nullable();
                $table->unsignedInteger('rate_limit_rpm')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['client_id', 'is_active'], 'idx_sdk_clients_lookup');
            });
        }

        if (!Schema::hasTable('sdk_token_audits')) {
            Schema::create('sdk_token_audits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('client_id', 100);
                $table->unsignedBigInteger('patient_id')->nullable();
                $table->unsignedBigInteger('encounter_id')->nullable();
                $table->unsignedBigInteger('clinician_id')->nullable();
                $table->string('token_jti', 100)->unique();
                $table->json('scopes')->nullable();
                $table->dateTime('issued_at');
                $table->dateTime('expires_at');
                $table->dateTime('revoked_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['client_id', 'expires_at'], 'idx_sdk_token_expiry');
                $table->index(['patient_id', 'encounter_id'], 'idx_sdk_token_context');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sdk_token_audits');
        Schema::dropIfExists('sdk_clients');
    }
};
