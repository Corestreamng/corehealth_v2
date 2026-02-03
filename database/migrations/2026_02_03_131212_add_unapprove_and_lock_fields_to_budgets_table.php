<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnapproveAndLockFieldsToBudgetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('budgets', function (Blueprint $table) {
            // Unapproval tracking
            $table->unsignedBigInteger('unapproved_by')->nullable()->after('approved_at');
            $table->timestamp('unapproved_at')->nullable()->after('unapproved_by');
            $table->text('unapproval_reason')->nullable()->after('unapproved_at');

            // Lock tracking
            $table->unsignedBigInteger('locked_by')->nullable()->after('unapproval_reason');
            $table->timestamp('locked_at')->nullable()->after('locked_by');

            // Rejection reason (if not exists)
            if (!Schema::hasColumn('budgets', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('locked_at');
            }

            // Foreign keys
            $table->foreign('unapproved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['unapproved_by']);
            $table->dropForeign(['locked_by']);

            $table->dropColumn([
                'unapproved_by',
                'unapproved_at',
                'unapproval_reason',
                'locked_by',
                'locked_at',
                'rejection_reason'
            ]);
        });
    }
}
