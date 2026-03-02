<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('who_growth_standards', function (Blueprint $table) {
            $table->id();

            // Indicator: wfa = weight-for-age, lhfa = length/height-for-age,
            //            hcfa = head-circumference-for-age, bfa = BMI-for-age
            $table->enum('indicator', ['wfa', 'lhfa', 'hcfa', 'bfa']);

            // Sex: M = male, F = female
            $table->enum('sex', ['M', 'F']);

            // Age in months (0–60 for all indicators)
            $table->decimal('age_months', 4, 1);

            // LMS parameters (WHO Box-Cox power exponential)
            $table->decimal('l_value', 8, 4);   // Lambda (Box-Cox power)
            $table->decimal('m_value', 8, 4);    // Mu (median)
            $table->decimal('s_value', 8, 5);    // Sigma (coefficient of variation)

            $table->timestamps();

            // Unique constraint: one row per indicator + sex + age
            $table->unique(['indicator', 'sex', 'age_months'], 'who_growth_unique');

            // Index for fast lookups
            $table->index(['indicator', 'sex'], 'who_growth_indicator_sex');
        });
    }

    public function down()
    {
        Schema::dropIfExists('who_growth_standards');
    }
};
