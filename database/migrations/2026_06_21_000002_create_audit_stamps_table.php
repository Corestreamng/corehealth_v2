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
        Schema::create('audit_stamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // The auditor who stamped the period
            $table->string('responsibility_key'); // e.g., 'PNW_WARD', 'PETTY_CASH'
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status')->default('approved_correct');
            $table->text('notes')->nullable();
            $table->timestamp('stamped_at');
            $table->timestamps();

            $table->index('responsibility_key');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_stamps');
    }
};
