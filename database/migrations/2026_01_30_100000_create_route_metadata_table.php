<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Route Metadata Table Migration
 *
 * Stores hierarchical navigation metadata for global search functionality.
 * This table is populated by the routes:scan artisan command.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('route_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('route_name', 100)->unique()->nullable();
            $table->string('url', 255);
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->string('section', 100)->nullable();          // e.g., "Health Records", "Pharmacy"
            $table->string('parent_section', 100)->nullable();   // e.g., "Patients", "Accounts"
            $table->string('icon', 50)->nullable();              // MDI icon class
            $table->json('keywords')->nullable();                // Search keywords
            $table->json('roles')->nullable();                   // Roles that can access
            $table->json('permissions')->nullable();             // Permissions required
            $table->string('hierarchy_path', 500)->nullable();   // "Health Records > Patients > New Registration"
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('title');
            $table->index('section');
            $table->index('hierarchy_path');
            $table->fulltext(['title', 'description', 'hierarchy_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_metadata');
    }
};
