<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsAuditedToStockBatchTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stock_batch_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_batch_transactions', 'is_audited')) {
                $table->boolean('is_audited')->default(false)->after('updated_at');
            }
            if (!Schema::hasColumn('stock_batch_transactions', 'audited_by')) {
                $table->unsignedBigInteger('audited_by')->nullable()->after('is_audited');
            }
            if (!Schema::hasColumn('stock_batch_transactions', 'audited_at')) {
                $table->timestamp('audited_at')->nullable()->after('audited_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_batch_transactions', function (Blueprint $table) {
            $table->dropColumn(['is_audited', 'audited_by', 'audited_at']);
        });
    }
}
