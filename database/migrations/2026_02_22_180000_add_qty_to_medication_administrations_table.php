<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            if (!Schema::hasColumn('medication_administrations', 'qty')) {
                $table->decimal('qty', 8, 2)->nullable()->default(1)->after('dose')
                    ->comment('Qty per administration (units consumed from prescribed total)');
            }
        });

        // Backfill existing pharmacy_dispensed administrations with qty = 1
        DB::table('medication_administrations')
            ->whereNull('qty')
            ->update(['qty' => 1]);
    }

    public function down()
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            if (Schema::hasColumn('medication_administrations', 'qty')) {
                $table->dropColumn('qty');
            }
        });
    }
};
