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
        Schema::table('patients', function (Blueprint $table) {
            // Individual indexes for common filter/join columns
            $table->index('user_id');
            $table->index('hmo_id');
            $table->index('created_at');
            $table->index('file_no');

            // Composite index for common filter + sort combinations
            $table->index(['hmo_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['hmo_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['file_no']);
            $table->dropIndex(['hmo_id', 'created_at']);
        });
    }
};
