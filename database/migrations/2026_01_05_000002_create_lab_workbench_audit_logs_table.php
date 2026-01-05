<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLabWorkbenchAuditLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lab_workbench_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_service_request_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action'); // 'view', 'edit', 'delete', 'restore', 'dismiss', 'undismiss', 'billing', 'sample_collection', 'result_entry'
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('lab_service_request_id')->references('id')->on('lab_service_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lab_workbench_audit_logs');
    }
}
