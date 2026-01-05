<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeleteToLabServiceRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            // Only add columns that don't exist yet
            // deleted_at, deleted_by, and deletion_reason already exist from previous migration
            $table->timestamp('dismissed_at')->nullable();
            $table->unsignedBigInteger('dismissed_by')->nullable();
            $table->text('dismiss_reason')->nullable();

            // Add foreign key for dismissed_by
            $table->foreign('dismissed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropForeign(['dismissed_by']);
            $table->dropColumn(['dismissed_at', 'dismissed_by', 'dismiss_reason']);
        });
    }
}
