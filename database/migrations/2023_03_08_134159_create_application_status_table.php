<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApplicationStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('application_status', function (Blueprint $table) {
            $table->id();
            $table->string('site_name');
            $table->string('site_abbreviation')->nullable();
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->text('contact_address')->nullable();
            $table->text('contact_phones')->nullable();
            $table->text('contact_emails')->nullable();
            $table->text('social_links')->nullable();
            $table->text('description')->nullable();
            $table->string('version')->nullable();
            $table->boolean('active')->default(1);
            $table->boolean('debug_mode')->default(1);
            $table->boolean('allow_piece_sale')->default(1);
            $table->boolean('allow_halve_sale')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('application_status');
    }
}
