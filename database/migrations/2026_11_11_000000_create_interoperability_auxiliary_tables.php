<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Creates auxiliary tables for multi-standard interoperability (HL7 FHIR R4, openEHR, OpenEMR, NDPA 2023)
     * adhering strictly to the ZERO CORE TABLE SCHEMA MUTATION invariant.
     */
    public function up(): void
    {
        if (!Schema::hasTable('interop_terminology_maps')) {
            Schema::create('interop_terminology_maps', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('standard', 32); // 'LOINC', 'SNOMED-CT', 'ICD-10', 'ATC', 'UCUM'
                $table->string('standard_code', 64); // e.g. '85354-9', 'N02BE01', 'mm[Hg]'
                $table->string('standard_display', 255);
                $table->string('system_uri', 255)->nullable(); // e.g. 'http://loinc.org'
                $table->string('local_entity_type', 64); // 'service', 'product', 'vital', 'diagnosis'
                $table->unsignedBigInteger('local_entity_id')->nullable();
                $table->string('local_key', 64)->nullable(); // e.g. 'systolic_blood_pressure'
                $table->timestamps();

                $table->index(['standard', 'standard_code'], 'idx_interop_term_std_code');
                $table->index(['local_entity_type', 'local_entity_id'], 'idx_interop_term_local_entity');
                $table->index(['local_entity_type', 'local_key'], 'idx_interop_term_local_key');
            });
        }

        if (!Schema::hasTable('interop_external_identifiers')) {
            Schema::create('interop_external_identifiers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('standard', 32); // 'FHIR', 'OPENEHR', 'OPENEMR', 'NHIE'
                $table->string('resource_type', 64); // 'Patient', 'Encounter', 'Observation', etc.
                $table->unsignedBigInteger('local_id');
                $table->string('external_id', 128); // External UUID or identifier
                $table->string('system', 255)->nullable(); // Namespace / Issuer URI
                $table->timestamps();

                $table->unique(['standard', 'resource_type', 'local_id'], 'uq_interop_ext_std_res_local');
                $table->index(['standard', 'resource_type', 'external_id'], 'idx_interop_ext_std_res_ext');
            });
        }

        if (!Schema::hasTable('patient_consents')) {
            Schema::create('patient_consents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('patient_id');
                $table->string('consent_type', 64); // 'TREATMENT', 'RESEARCH', 'CLOUD_AI', 'THIRD_PARTY_TRANSFER', 'ALL'
                $table->string('status', 32)->default('ACTIVE'); // 'ACTIVE', 'REVOKED', 'EXPIRED'
                $table->string('ndpa_lawful_basis', 64)->default('EXPLICIT_CONSENT'); // 'EXPLICIT_CONSENT', 'VITAL_INTEREST', 'LEGAL_OBLIGATION'
                $table->unsignedBigInteger('granted_by')->nullable(); // User/staff who recorded it
                $table->string('ip_address', 45)->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('granted_at');
                $table->dateTime('revoked_at')->nullable();
                $table->timestamps();

                $table->foreign('patient_id')
                    ->references('id')
                    ->on('patients')
                    ->onDelete('cascade');

                $table->index(['patient_id', 'consent_type', 'status'], 'idx_consent_patient_type_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('interop_external_identifiers');
        Schema::dropIfExists('interop_terminology_maps');
    }
};
