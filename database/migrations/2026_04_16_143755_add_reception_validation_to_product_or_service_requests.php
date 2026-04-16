<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReceptionValidationToProductOrServiceRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->tinyInteger('reception_validated')->default(0)->after('validation_notes');
            $table->unsignedBigInteger('reception_validated_by')->nullable()->after('reception_validated');
            $table->timestamp('reception_validated_at')->nullable()->after('reception_validated_by');
            $table->string('reception_validation_notes', 500)->nullable()->after('reception_validated_at');

            $table->foreign('reception_validated_by')->references('id')->on('users')->onDelete('set null');
            $table->index('reception_validated');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropForeign(['reception_validated_by']);
            $table->dropIndex(['reception_validated']);
            $table->dropColumn(['reception_validated', 'reception_validated_by', 'reception_validated_at', 'reception_validation_notes']);
        });
    }
}
