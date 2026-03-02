<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('maternity_enrollments')->cascadeOnDelete();
            $table->dateTime('date_of_delivery');
            $table->string('place_of_delivery')->nullable();
            $table->string('duration_of_labour')->nullable();
            $table->enum('type_of_delivery', ['svd','assisted_vaginal','elective_cs','emergency_cs','vacuum','forceps']);
            $table->enum('episiotomy', ['none','mediolateral','median'])->default('none');
            $table->boolean('induction')->default(false);
            $table->string('induction_method')->nullable();
            $table->boolean('augmentation')->default(false);
            $table->text('complications')->nullable();
            $table->integer('blood_loss_ml')->nullable();
            $table->enum('placenta_delivery', ['complete','incomplete','manual_removal'])->default('complete');
            $table->enum('perineal_tear', ['none','first_degree','second_degree','third_degree','fourth_degree'])->default('none');
            $table->foreignId('delivered_by')->constrained('users');
            $table->string('anaesthesia_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_records');
    }
};
