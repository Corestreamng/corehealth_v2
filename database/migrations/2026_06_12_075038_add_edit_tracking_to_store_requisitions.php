<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEditTrackingToStoreRequisitions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('store_requisitions', function (Blueprint $table) {
            $table->unsignedBigInteger('edited_by')->nullable()->after('fulfilled_at');
            $table->timestamp('edited_at')->nullable()->after('edited_by');
            $table->unsignedInteger('edit_count')->default(0)->after('edited_at');

            $table->foreign('edited_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('store_requisitions', function (Blueprint $table) {
            $table->dropForeign(['edited_by']);
            $table->dropColumn(['edited_by', 'edited_at', 'edit_count']);
        });
    }
}
