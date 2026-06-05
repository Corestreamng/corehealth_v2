<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocalizationToApplicationStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->string('language', 10)->default('en')->after('timezone');
            $table->string('currency_symbol', 10)->default('₦')->after('language');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn(['language', 'currency_symbol']);
        });
    }
}
