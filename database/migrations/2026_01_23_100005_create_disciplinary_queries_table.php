<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.5
 * Disciplinary Queries with response tracking
 */
class CreateDisciplinaryQueriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('disciplinary_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_number')->unique();
            $table->unsignedBigInteger('staff_id');
            $table->string('subject');
            $table->text('description');
            $table->enum('severity', ['minor', 'moderate', 'major', 'gross_misconduct'])->default('minor');
            $table->date('incident_date')->nullable();
            $table->text('expected_response')->nullable();
            $table->date('response_deadline');

            // Status: issued → response_received → reviewed → closed
            $table->enum('status', ['issued', 'response_received', 'under_review', 'closed'])->default('issued');

            // Staff Response
            $table->text('staff_response')->nullable();
            $table->timestamp('response_received_at')->nullable();

            // HR Decision
            $table->text('hr_decision')->nullable();
            $table->enum('outcome', ['warning', 'final_warning', 'suspension', 'termination', 'dismissed', 'no_action'])->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();

            // Issuer
            $table->unsignedBigInteger('issued_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('issued_by')->references('id')->on('users');
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['staff_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('disciplinary_queries');
    }
}
