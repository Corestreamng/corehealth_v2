<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDrugSourceColumnsToAdministrationsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->enum('drug_source', ['pharmacy_dispensed', 'patient_own', 'ward_stock'])
                  ->default('pharmacy_dispensed')
                  ->after('administered_by');
            $table->unsignedBigInteger('product_request_id')->nullable()->after('drug_source');
            $table->string('external_drug_name')->nullable()->after('product_request_id');
            $table->decimal('external_qty', 8, 2)->nullable()->after('external_drug_name');
            $table->string('external_batch_number', 50)->nullable()->after('external_qty');
            $table->date('external_expiry_date')->nullable()->after('external_batch_number');
            $table->text('external_source_note')->nullable()->after('external_expiry_date');

            $table->foreign('product_request_id')->references('id')->on('product_requests')->onDelete('set null');
        });

        Schema::table('injection_administrations', function (Blueprint $table) {
            $table->enum('drug_source', ['pharmacy_dispensed', 'patient_own', 'ward_stock'])
                  ->default('pharmacy_dispensed')
                  ->after('administered_by');
            $table->unsignedBigInteger('product_request_id')->nullable()->after('drug_source');
            $table->string('external_drug_name')->nullable()->after('product_request_id');
            $table->decimal('external_qty', 8, 2)->nullable()->after('external_drug_name');
            $table->string('external_batch_number', 50)->nullable()->after('external_qty');
            $table->date('external_expiry_date')->nullable()->after('external_batch_number');
            $table->text('external_source_note')->nullable()->after('external_expiry_date');

            $table->foreign('product_request_id')->references('id')->on('product_requests')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropForeign(['product_request_id']);
            $table->dropColumn([
                'drug_source',
                'product_request_id',
                'external_drug_name',
                'external_qty',
                'external_batch_number',
                'external_expiry_date',
                'external_source_note'
            ]);
        });

        Schema::table('injection_administrations', function (Blueprint $table) {
            $table->dropForeign(['product_request_id']);
            $table->dropColumn([
                'drug_source',
                'product_request_id',
                'external_drug_name',
                'external_qty',
                'external_batch_number',
                'external_expiry_date',
                'external_source_note'
            ]);
        });
    }
}
