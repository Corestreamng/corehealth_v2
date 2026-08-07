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
        Schema::table('treatment_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('treatment_plans', 'retired_at')) {
                $table->timestamp('retired_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('treatment_plans', 'retired_by')) {
                $table->unsignedBigInteger('retired_by')->nullable()->after('retired_at');
                $table->foreign('retired_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('treatment_plans', 'retirement_reason')) {
                $table->string('retirement_reason', 100)->nullable()->after('retired_by');
            }
            if (!Schema::hasColumn('treatment_plans', 'retirement_notes')) {
                $table->text('retirement_notes')->nullable()->after('retirement_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_plans', 'retired_by')) {
                $table->dropForeign(['retired_by']);
                $table->dropColumn('retired_by');
            }
            if (Schema::hasColumn('treatment_plans', 'retired_at')) {
                $table->dropColumn('retired_at');
            }
            if (Schema::hasColumn('treatment_plans', 'retirement_reason')) {
                $table->dropColumn('retirement_reason');
            }
            if (Schema::hasColumn('treatment_plans', 'retirement_notes')) {
                $table->dropColumn('retirement_notes');
            }
        });
    }
};
