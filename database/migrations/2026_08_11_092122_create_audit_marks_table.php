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
        Schema::create('audit_marks', function (Blueprint $table) {
            $table->id();
            $table->morphs('auditable');
            $table->string('zone_key')->nullable();
            $table->foreignId('auditor_id')->constrained('users');
            
            // Can be 'audited' or 'queried'
            $table->enum('status', ['audited', 'queried']);
            
            // Query Tracking fields
            $table->text('query_notes')->nullable();
            $table->foreignId('query_resolved_by')->nullable()->constrained('users');
            $table->timestamp('query_resolved_at')->nullable();
            $table->text('query_resolution_notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_marks');
    }
};
