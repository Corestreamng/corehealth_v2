<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProcedureColumnsToProductOrServiceRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            // Add missing columns for procedure/service requests
            if (!Schema::hasColumn('product_or_service_requests', 'type')) {
                $table->string('type', 20)->nullable()->after('id')->comment('product or service');
            }
            if (!Schema::hasColumn('product_or_service_requests', 'patient_id')) {
                $table->unsignedBigInteger('patient_id')->nullable()->after('user_id');
                $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            }
            if (!Schema::hasColumn('product_or_service_requests', 'encounter_id')) {
                $table->unsignedBigInteger('encounter_id')->nullable()->after('patient_id');
                $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');
            }
            if (!Schema::hasColumn('product_or_service_requests', 'admission_request_id')) {
                $table->unsignedBigInteger('admission_request_id')->nullable()->after('encounter_id');
                $table->foreign('admission_request_id')->references('id')->on('admission_requests')->onDelete('set null');
            }
            if (!Schema::hasColumn('product_or_service_requests', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('staff_user_id');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('product_or_service_requests', 'order_date')) {
                $table->timestamp('order_date')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('product_or_service_requests', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0)->after('qty');
            }
            if (!Schema::hasColumn('product_or_service_requests', 'hmo_id')) {
                $table->unsignedBigInteger('hmo_id')->nullable()->after('coverage_mode');
                $table->foreign('hmo_id')->references('id')->on('hmos')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['encounter_id']);
            $table->dropForeign(['admission_request_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['hmo_id']);

            $table->dropColumn([
                'type',
                'patient_id',
                'encounter_id',
                'admission_request_id',
                'created_by',
                'order_date',
                'amount',
                'hmo_id'
            ]);
        });
    }
}
