<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRemovalFieldsToProductOrServiceRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('removed_by')->nullable()->after('created_by');
            $table->timestamp('removed_at')->nullable()->after('removed_by');

            $table->foreign('removed_by')->references('id')->on('users')->onDelete('set null');
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
            $table->dropForeign(['removed_by']);
            $table->dropColumn(['removed_by', 'removed_at']);
        });
    }
}
