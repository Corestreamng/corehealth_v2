<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.14
 * HR Attachments - Polymorphic file attachments for all HR documents
 */
class CreateHrAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop if exists (in case of partial migration)
        Schema::dropIfExists('hr_attachments');

        Schema::create('hr_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');                        // attachable_type, attachable_id (automatically creates index)
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('document_type')->nullable();         // 'medical_report', 'query_response', 'termination_letter'
            $table->text('description')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('uploaded_by')->references('id')->on('users');
            // Note: morphs() already creates the index, so we don't need to add it again
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hr_attachments');
    }
}
