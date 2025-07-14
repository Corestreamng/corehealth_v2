<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('intake_output_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->enum('type', ['intake', 'output']);
            $table->decimal('amount', 8, 2);
            $table->string('description')->nullable();
            $table->dateTime('recorded_at');
            $table->unsignedBigInteger('nurse_id')->nullable();
            $table->timestamps();
            $table->foreign('period_id')->references('id')->on('intake_output_periods');
            $table->foreign('nurse_id')->references('id')->on('users');
        });
    }
    public function down() {
        Schema::dropIfExists('intake_output_records');
    }
};
