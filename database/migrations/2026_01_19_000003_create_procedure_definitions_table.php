<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcedureDefinitionsTable extends Migration
{
    /**
     * Run the migrations.
     * Creates the procedure_definitions table which links procedures to services.
     * This is the "catalog" of procedures that can be performed.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procedure_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->unique();
            $table->unsignedBigInteger('procedure_category_id');
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_surgical')->default(false);
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();

            // Foreign keys
            $table->foreign('service_id')
                  ->references('id')
                  ->on('services')
                  ->onDelete('cascade');

            $table->foreign('procedure_category_id')
                  ->references('id')
                  ->on('procedure_categories')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procedure_definitions');
    }
}
