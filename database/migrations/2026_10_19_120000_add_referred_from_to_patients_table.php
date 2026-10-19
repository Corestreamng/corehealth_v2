<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('patients', 'referred_from')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->string('referred_from', 255)->nullable()->after('registration_workbench');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('patients', 'referred_from')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('referred_from');
            });
        }
    }
};
