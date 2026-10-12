<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Migration to automatically seed and update route metadata on every instance.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Automatically populate or refresh route metadata on any instance executing migrations
        Artisan::call('routes:scan');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down action needed
    }
};
