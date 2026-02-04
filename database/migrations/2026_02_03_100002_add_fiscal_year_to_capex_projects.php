<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add fiscal_year column to capex_projects and create view for backward compatibility
 *
 * The CapexController was written to use 'capex_requests' table with fiscal_year column,
 * but the migration created 'capex_projects' without this field.
 * This migration adds fiscal_year and creates a view for compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add fiscal_year to capex_projects
        Schema::table('capex_projects', function (Blueprint $table) {
            $table->integer('fiscal_year')->after('project_code')->nullable();
            $table->index('fiscal_year');
        });

        // Create a view 'capex_requests' as alias to 'capex_projects' for backward compatibility
        // This allows existing code using 'capex_requests' to work without changes
        DB::statement('CREATE OR REPLACE VIEW capex_requests AS SELECT * FROM capex_projects');
    }

    public function down(): void
    {
        // Drop the view
        DB::statement('DROP VIEW IF EXISTS capex_requests');

        // Remove fiscal_year column
        Schema::table('capex_projects', function (Blueprint $table) {
            $table->dropIndex(['fiscal_year']);
            $table->dropColumn('fiscal_year');
        });
    }
};
