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
        Schema::table('hmo_tariffs', function (Blueprint $table) {
            // Individual indexes for common filter columns
            $table->index('hmo_id');
            $table->index('coverage_mode');
            $table->index('created_at');

            // Composite index for common filter + sort combinations
            $table->index(['hmo_id', 'coverage_mode', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hmo_tariffs', function (Blueprint $table) {
            $table->dropIndex(['hmo_id']);
            $table->dropIndex(['coverage_mode']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['hmo_id', 'coverage_mode', 'created_at']);
        });
    }
};
