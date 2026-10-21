<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Free-form fields on product_or_service_requests
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->boolean('is_free_form')->default(false)->after('is_bundle_item');
            $table->string('free_form_name', 255)->nullable()->after('is_free_form');
            $table->text('free_form_description')->nullable()->after('free_form_name');
            $table->boolean('is_billable')->default(true)->after('free_form_description');
            $table->index('is_free_form', 'idx_psr_free_form');
        });

        // Walk-in sales configuration in application_status
        Schema::table('application_status', function (Blueprint $table) {
            $table->boolean('walkin_biller_enabled')->default(true);
            $table->boolean('walkin_reception_enabled')->default(true);
            $table->boolean('walkin_lab_enabled')->default(true);
            $table->boolean('walkin_imaging_enabled')->default(true);
            $table->boolean('walkin_products_enabled')->default(true);
            $table->boolean('walkin_free_form_enabled')->default(true);
            $table->boolean('walkin_procedures_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropIndex('idx_psr_free_form');
            $table->dropColumn(['is_free_form', 'free_form_name', 'free_form_description', 'is_billable']);
        });

        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn([
                'walkin_biller_enabled',
                'walkin_reception_enabled',
                'walkin_lab_enabled',
                'walkin_imaging_enabled',
                'walkin_products_enabled',
                'walkin_free_form_enabled',
                'walkin_procedures_enabled',
            ]);
        });
    }
};
