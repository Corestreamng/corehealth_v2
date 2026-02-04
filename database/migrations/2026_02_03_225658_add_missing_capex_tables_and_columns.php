<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing CAPEX tables and columns
 *
 * Fixes gaps identified during verification:
 * - Missing tables: capex_request_items, capex_approval_history
 * - Missing columns: completion_date, revision_notes, approved_at
 */
class AddMissingCapexTablesAndColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add missing columns to capex_projects
        Schema::table('capex_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('capex_projects', 'completion_date')) {
                $table->date('completion_date')->nullable()->after('actual_completion_date');
            }
            if (!Schema::hasColumn('capex_projects', 'revision_notes')) {
                $table->text('revision_notes')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('capex_projects', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_date');
            }
        });

        // Create capex_request_items table if not exists
        if (!Schema::hasTable('capex_request_items')) {
            Schema::create('capex_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('capex_request_id')->constrained('capex_projects')->onDelete('cascade');
                $table->string('description');
                $table->integer('quantity')->default(1);
                $table->decimal('unit_cost', 15, 2);
                $table->decimal('amount', 15, 2);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('capex_request_id');
            });
        }

        // Create capex_approval_history table if not exists
        if (!Schema::hasTable('capex_approval_history')) {
            Schema::create('capex_approval_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('capex_request_id')->constrained('capex_projects')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users');
                $table->enum('action', ['submitted', 'approved', 'rejected', 'revision_requested', 'started', 'completed', 'cancelled']);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['capex_request_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('capex_approval_history');
        Schema::dropIfExists('capex_request_items');

        Schema::table('capex_projects', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('capex_projects', 'completion_date')) {
                $columns[] = 'completion_date';
            }
            if (Schema::hasColumn('capex_projects', 'revision_notes')) {
                $columns[] = 'revision_notes';
            }
            if (Schema::hasColumn('capex_projects', 'approved_at')) {
                $columns[] = 'approved_at';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
}
