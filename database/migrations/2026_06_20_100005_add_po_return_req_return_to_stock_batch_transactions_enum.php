<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPoReturnReqReturnToStockBatchTransactionsEnum extends Migration
{
    public function up()
    {
        // MySQL requires re-specifying the full enum list to alter it
        DB::statement("ALTER TABLE stock_batch_transactions MODIFY COLUMN type ENUM('in','out','adjustment','transfer_out','transfer_in','return','expired','damaged','po_return','req_return') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE stock_batch_transactions MODIFY COLUMN type ENUM('in','out','adjustment','transfer_out','transfer_in','return','expired','damaged') NOT NULL");
    }
}
