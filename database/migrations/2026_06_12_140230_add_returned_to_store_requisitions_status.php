<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add 'returned' to store_requisitions status ENUM
        DB::statement("ALTER TABLE store_requisitions MODIFY COLUMN status ENUM('pending','approved','rejected','partial','fulfilled','cancelled','returned') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE store_requisitions MODIFY COLUMN status ENUM('pending','approved','rejected','partial','fulfilled','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
