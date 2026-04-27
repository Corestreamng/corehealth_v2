<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMorgueCategoryIdToApplicationStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->unsignedBigInteger('morgue_category_id')->nullable()->after('procedure_category_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn('morgue_category_id');
        });
    }
}
