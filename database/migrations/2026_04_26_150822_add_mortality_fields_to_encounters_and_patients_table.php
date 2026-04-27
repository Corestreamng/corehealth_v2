<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMortalityFieldsToEncountersAndPatientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->enum('outcome', ['discharged', 'improved', 'unimproved', 'referred', 'absconded', 'death_rip', 'death_bid'])->default('discharged')->after('completed');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_deceased')->default(false)->after('hmo_id');
            $table->date('date_of_death')->nullable()->after('is_deceased');
        });

        Schema::table('admission_requests', function (Blueprint $table) {
            $table->foreignId('death_record_id')->nullable()->constrained('death_records')->after('discharge_note');
        });
    }

    public function down()
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['is_deceased', 'date_of_death']);
        });

        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropForeign(['death_record_id']);
            $table->dropColumn('death_record_id');
        });
    }
}
