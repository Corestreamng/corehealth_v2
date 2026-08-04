<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHmoPrincipalToPatientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_hmo_principal')->default(true)->after('hmo_no');
            $table->unsignedBigInteger('hmo_principal_id')->nullable()->after('is_hmo_principal');
            $table->string('hmo_dependent_role')->nullable()->after('hmo_principal_id'); // Spouse, Child, Other
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
            $table->dropColumn(['is_hmo_principal', 'hmo_principal_id', 'hmo_dependent_role']);
        });
    }
}
