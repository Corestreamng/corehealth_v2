<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('file_no')->nullable();
            $table->unsignedBigInteger('insurance_scheme')->nullable();
            $table->unsignedBigInteger('hmo_id')->nullable();
            $table->unsignedBigInteger('hmo_no');
            $table->enum('gender',['Male','Female','Others'])->nullable();
            $table->timestamp('dob')->nullable();
            $table->enum('blood_group',['A+','A-','B+','B-','AB+','AB-','O+','O-','Others'])->nullable();
            $table->enum('genotype',['AA','AS','AC','SS','SC','Others'])->nullable();
            $table->integer('disability')->default(0);
            $table->string('address')->nullable();
            $table->string('nationality')->nullable();
            $table->string('ethnicity')->nullable();
            $table->text('misc')->nullable();
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
        Schema::dropIfExists('patients');
    }
}
