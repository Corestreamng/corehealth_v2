<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddReturnedStatusToStoreRequisitionItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Altering ENUM requires raw DB statement to avoid doctrine/dbal issues
        DB::statement("ALTER TABLE store_requisition_items MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'partial', 'fulfilled', 'cancelled', 'returned') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverting the enum. Note: if there are rows with 'returned', this might fail, so we'd have to update them first.
        DB::statement("ALTER TABLE store_requisition_items MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'partial', 'fulfilled', 'cancelled') DEFAULT 'pending'");
    }
}
