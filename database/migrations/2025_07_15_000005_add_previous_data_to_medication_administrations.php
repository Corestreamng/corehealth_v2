<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->json('previous_data')->nullable()->after('edit_reason');
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
            $table->dropColumn('previous_data');
        });
    }
};
