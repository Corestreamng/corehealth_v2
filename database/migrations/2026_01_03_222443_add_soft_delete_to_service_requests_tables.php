<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeleteToServiceRequestsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add soft delete columns to lab_service_requests
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deletion_reason')->nullable();

            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });

        // Add soft delete columns to imaging_service_requests
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deletion_reason')->nullable();

            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });

        // Add soft delete columns to product_requests
        Schema::table('product_requests', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deletion_reason')->nullable();

            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove soft delete columns from lab_service_requests
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by', 'deletion_reason']);
        });

        // Remove soft delete columns from imaging_service_requests
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by', 'deletion_reason']);
        });

        // Remove soft delete columns from product_requests
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by', 'deletion_reason']);
        });
    }
}
