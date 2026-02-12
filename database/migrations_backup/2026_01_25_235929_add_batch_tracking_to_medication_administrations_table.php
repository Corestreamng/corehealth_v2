<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBatchTrackingToMedicationAdministrationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            // Add batch tracking for stock management integration
            $table->unsignedBigInteger('dispensed_from_batch_id')->nullable()->after('store_id');
            $table->foreign('dispensed_from_batch_id')->references('id')->on('stock_batches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropForeign(['dispensed_from_batch_id']);
            $table->dropColumn('dispensed_from_batch_id');
        });
    }
}
