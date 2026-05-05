<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVitalRangesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vital_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('vital_key')->index(); // temp, heart_rate, resp_rate, spo2, bp_sys, bp_dia, etc.
            
            // Age range in days for precision (e.g. 0-28 days for neonate)
            $table->integer('age_min_days')->default(0);
            $table->integer('age_max_days')->default(43800); // ~120 years
            
            $table->string('gender')->nullable(); // male, female, or null for both
            
            // Thresholds
            $table->decimal('normal_min', 8, 2)->nullable();
            $table->decimal('normal_max', 8, 2)->nullable();
            
            $table->decimal('warning_min', 8, 2)->nullable();
            $table->decimal('warning_max', 8, 2)->nullable();
            
            $table->decimal('critical_min', 8, 2)->nullable();
            $table->decimal('critical_max', 8, 2)->nullable();
            
            $table->string('notes')->nullable();
            $table->timestamps();
            
            $table->index(['vital_key', 'age_min_days', 'age_max_days'], 'vitals_age_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vital_ranges');
    }
}
