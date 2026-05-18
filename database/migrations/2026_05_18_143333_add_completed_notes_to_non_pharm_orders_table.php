<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompletedNotesToNonPharmOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('non_pharm_orders', function (Blueprint $table) {
            $table->text('completed_notes')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('non_pharm_orders', function (Blueprint $table) {
            $table->dropColumn('completed_notes');
        });
    }
}
