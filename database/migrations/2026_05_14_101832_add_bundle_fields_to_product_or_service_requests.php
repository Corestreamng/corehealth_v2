<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBundleFieldsToProductOrServiceRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('admission_request_id');
            $table->boolean('is_bundle_item')->default(false)->after('parent_id');

            $table->foreign('parent_id')->references('id')->on('product_or_service_requests')->onDelete('cascade');
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
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'is_bundle_item']);
        });
    }
}
