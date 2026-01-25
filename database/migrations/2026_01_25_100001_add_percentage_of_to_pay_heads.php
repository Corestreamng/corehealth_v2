<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pay_heads', function (Blueprint $table) {
            if (!Schema::hasColumn('pay_heads', 'percentage_of')) {
                $table->string('percentage_of')->nullable()->after('calculation_type')
                      ->comment('basic, gross, basic_salary, gross_salary');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pay_heads', function (Blueprint $table) {
            $table->dropColumn('percentage_of');
        });
    }
};
