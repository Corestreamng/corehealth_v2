<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddConcludedToEncountersOutcomeEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `encounters` MODIFY `outcome` ENUM('discharged','improved','unimproved','referred','absconded','death_rip','death_bid','concluded') NOT NULL DEFAULT 'discharged'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `encounters` MODIFY `outcome` ENUM('discharged','improved','unimproved','referred','absconded','death_rip','death_bid') NOT NULL DEFAULT 'discharged'");
    }
}
