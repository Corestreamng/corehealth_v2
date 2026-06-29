<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFamilyFolderToPatientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_family_principal')->default(false)->after('id');
            $table->unsignedBigInteger('principal_id')->nullable()->after('is_family_principal');
            
            $table->foreign('principal_id')->references('id')->on('patients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['principal_id']);
            $table->dropColumn(['is_family_principal', 'principal_id']);
        });
    }
}
