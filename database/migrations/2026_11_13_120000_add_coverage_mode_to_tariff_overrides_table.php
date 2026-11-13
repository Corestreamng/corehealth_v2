<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tariff_overrides', function (Blueprint $table) {
            $table->enum('coverage_mode', ['express', 'primary', 'secondary'])
                ->nullable()
                ->after('amount')
                ->comment('Optional coverage mode override: express (auto-approved), primary (validation required), secondary (validation + auth code). Null = inherit from underlying tariff.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tariff_overrides', function (Blueprint $table) {
            $table->dropColumn('coverage_mode');
        });
    }
};

