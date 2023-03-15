<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('specialization_id')->nullable();
            $table->unsignedBigInteger('clinic_id')->nullable();
            $table->enum('gender',['Male','Female','Others'])->nullable();
            $table->timestamp('date_of_birth')->nullable();
            $table->text('home_address')->nullable();
            $table->string('phone_number')->nullable();
            $table->float('consultation_fee')->default(0.00);
            $table->integer('status')->default(1);
            $table->foreign('specialization_id')->references('id')->on('specializations');
            // $table->foreign('clinic_id')->references('id')->on('clinics');
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
        Schema::dropIfExists('staff');
    }
}
