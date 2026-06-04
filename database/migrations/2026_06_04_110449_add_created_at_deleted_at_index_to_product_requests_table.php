<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreatedAtDeletedAtIndexToProductRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->index(['deleted_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropIndex(['deleted_at', 'created_at']);
        });
    }
}
