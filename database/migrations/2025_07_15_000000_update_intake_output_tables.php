<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateIntakeOutputTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add ended_by to intake_output_periods table
        Schema::table('intake_output_periods', function (Blueprint $table) {
            $table->unsignedBigInteger('ended_by')->nullable()->after('ended_at');
            $table->foreign('ended_by')->references('id')->on('users')->onDelete('set null');
        });
        
        // Add edit and delete fields to intake_output_records
        Schema::table('intake_output_records', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('recorded_at');
            $table->unsignedBigInteger('edited_by')->nullable()->after('edited_at');
            $table->string('edit_reason')->nullable()->after('edited_by');
            
            $table->timestamp('deleted_at')->nullable()->after('edit_reason');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            $table->string('delete_reason')->nullable()->after('deleted_by');
            
            $table->foreign('edited_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
        
        // Create intake_output_histories table
        Schema::create('intake_output_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('action'); // create, edit, delete, start_period, end_period
            $table->text('reason')->nullable();
            $table->text('original_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamps();
            
            $table->foreign('period_id')->references('id')->on('intake_output_periods')->onDelete('cascade');
            $table->foreign('record_id')->references('id')->on('intake_output_records')->onDelete('cascade');
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
        Schema::dropIfExists('intake_output_histories');
        
        Schema::table('intake_output_records', function (Blueprint $table) {
            $table->dropForeign(['edited_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['edited_at', 'edited_by', 'edit_reason', 'deleted_at', 'deleted_by', 'delete_reason']);
        });
        
        Schema::table('intake_output_periods', function (Blueprint $table) {
            $table->dropForeign(['ended_by']);
            $table->dropColumn('ended_by');
        });
    }
}
