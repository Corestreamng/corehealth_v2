<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientProceduresTable extends Migration
{
    /**
     * Run the migrations.
     * Adds new fields to the existing procedures table for enhanced tracking.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('procedures', function (Blueprint $table) {
            // Link to procedure definition (catalog)
            $table->unsignedBigInteger('procedure_definition_id')->nullable()->after('service_id');

            // Add encounter_id if not exists
            $table->unsignedBigInteger('encounter_id')->nullable()->after('patient_id');
            $table->unsignedBigInteger('admission_request_id')->nullable()->after('encounter_id');
            $table->unsignedBigInteger('product_or_service_request_id')->nullable()->after('admission_request_id');

            // Status and scheduling
            $table->enum('procedure_status', [
                'requested',
                'scheduled',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('requested')->after('status');
            $table->enum('priority', ['routine', 'urgent', 'emergency'])->default('routine')->after('procedure_status');

            // Scheduling
            $table->date('scheduled_date')->nullable()->after('priority');
            $table->time('scheduled_time')->nullable()->after('scheduled_date');
            $table->dateTime('actual_start_time')->nullable()->after('scheduled_time');
            $table->dateTime('actual_end_time')->nullable()->after('actual_start_time');
            $table->string('operating_room', 100)->nullable()->after('actual_end_time');

            // Outcome
            $table->enum('outcome', [
                'successful',
                'complications',
                'aborted',
                'converted'
            ])->nullable()->after('operating_room');
            $table->text('outcome_notes')->nullable()->after('outcome');

            // Cancellation
            $table->text('cancellation_reason')->nullable()->after('post_notes_by');
            $table->decimal('refund_amount', 15, 2)->nullable()->after('cancellation_reason');
            $table->dateTime('cancelled_at')->nullable()->after('refund_amount');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');

            // Soft deletes
            $table->softDeletes();

            // Foreign keys
            $table->foreign('procedure_definition_id')
                  ->references('id')
                  ->on('procedure_definitions')
                  ->onDelete('set null');

            $table->foreign('encounter_id')
                  ->references('id')
                  ->on('encounters')
                  ->onDelete('set null');

            $table->foreign('admission_request_id')
                  ->references('id')
                  ->on('admission_requests')
                  ->onDelete('set null');

            $table->foreign('product_or_service_request_id')
                  ->references('id')
                  ->on('product_or_service_requests')
                  ->onDelete('set null');

            $table->foreign('cancelled_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes for common queries
            $table->index(['patient_id', 'procedure_status']);
            $table->index(['scheduled_date', 'procedure_status']);
            $table->index('procedure_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('procedures', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['procedure_definition_id']);
            $table->dropForeign(['encounter_id']);
            $table->dropForeign(['admission_request_id']);
            $table->dropForeign(['product_or_service_request_id']);
            $table->dropForeign(['cancelled_by']);

            // Drop indexes
            $table->dropIndex(['patient_id', 'procedure_status']);
            $table->dropIndex(['scheduled_date', 'procedure_status']);
            $table->dropIndex(['procedure_status']);

            // Drop columns
            $table->dropColumn([
                'procedure_definition_id',
                'encounter_id',
                'admission_request_id',
                'product_or_service_request_id',
                'procedure_status',
                'priority',
                'scheduled_date',
                'scheduled_time',
                'actual_start_time',
                'actual_end_time',
                'operating_room',
                'outcome',
                'outcome_notes',
                'cancellation_reason',
                'refund_amount',
                'cancelled_at',
                'cancelled_by',
                'deleted_at',
            ]);
        });
    }
}
