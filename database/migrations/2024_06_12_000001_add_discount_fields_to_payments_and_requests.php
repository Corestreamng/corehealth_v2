<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountFieldsToPaymentsAndRequests extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('total_discount', 12, 2)->default(0)->after('total');
            $table->unsignedBigInteger('hmo_id')->nullable()->after('patient_id');
        });

        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->decimal('discount', 8, 2)->default(0)->after('qty');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['total_discount', 'hmo_id']);
        });

        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
}
