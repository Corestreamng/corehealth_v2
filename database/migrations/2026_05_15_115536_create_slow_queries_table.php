<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSlowQueriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('slow_queries', function (Blueprint $table) {
            $table->id();
            $table->dateTime('timestamp')->index();
            $table->string('user_host')->nullable();
            $table->decimal('query_time', 10, 6)->index();
            $table->decimal('lock_time', 10, 6)->nullable();
            $table->integer('rows_sent')->nullable();
            $table->integer('rows_examined')->nullable()->index();
            $table->text('query');
            $table->string('query_hash', 64)->unique();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('slow_queries');
    }
}
