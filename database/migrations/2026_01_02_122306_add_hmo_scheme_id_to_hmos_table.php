<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHmoSchemeIdToHmosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hmos', function (Blueprint $table) {
            $table->unsignedBigInteger('hmo_scheme_id')->nullable()->after('id');
            $table->foreign('hmo_scheme_id')->references('id')->on('hmo_schemes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hmos', function (Blueprint $table) {
            $table->dropForeign(['hmo_scheme_id']);
            $table->dropColumn('hmo_scheme_id');
        });
    }
}
