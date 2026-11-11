<?php

use Database\Seeders\InteropTerminologySeeder;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $seeder = new InteropTerminologySeeder();
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Interoperability\Models\InteropTerminologyMap::truncate();
    }
};
