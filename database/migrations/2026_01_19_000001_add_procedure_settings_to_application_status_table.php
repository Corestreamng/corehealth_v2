<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProcedureSettingsToApplicationStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->unsignedBigInteger('registration_category_id')->nullable();
            $table->unsignedBigInteger('procedure_category_id')->nullable();

            // Foreign keys to service_categories
            $table->foreign('registration_category_id')
                  ->references('id')
                  ->on('service_categories')
                  ->onDelete('set null');

            $table->foreign('procedure_category_id')
                  ->references('id')
                  ->on('service_categories')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropForeign(['registration_category_id']);
            $table->dropForeign(['procedure_category_id']);
            $table->dropColumn(['registration_category_id', 'procedure_category_id']);
        });
    }
}
