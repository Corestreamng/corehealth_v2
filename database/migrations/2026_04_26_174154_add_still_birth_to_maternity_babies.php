<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('maternity_babies', function (Blueprint $table) {
            $table->boolean('is_still_birth')->default(false)->after('sex');
            $table->dateTime('deceased_at')->nullable()->after('status');
            $table->text('cause_of_death')->nullable()->after('deceased_at');
        });
    }

    public function down()
    {
        Schema::table('maternity_babies', function (Blueprint $table) {
            $table->dropColumn(['is_still_birth', 'deceased_at', 'cause_of_death']);
        });
    }
};
