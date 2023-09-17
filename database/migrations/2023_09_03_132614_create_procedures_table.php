<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProceduresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained('services');
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('patient_id')->nullable()->constrained('patients');
            $table->timestamp('requested_on')->nullable();
            $table->foreignId('billed_by')->nullable()->constrained('users');
            $table->timestamp('billed_on')->nullable();
            $table->text('pre_notes')->nullable();
            $table->foreignId('pre_notes_by')->nullable()->constrained('users');
            $table->text('post_notes')->nullable();
            $table->foreignId('post_notes_by')->nullable()->constrained('users');
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procedures');
    }
}
