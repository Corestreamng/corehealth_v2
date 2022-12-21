<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('is_admin')->default(19);
            $table->string('email')->unique();
            $table->string('filename')->nullable();//name of pic uploaded for user
            $table->string('old_records')->nullable();
            $table->string('surname');
            $table->string('firstname');
            $table->string('othername')->nullable();
            $table->string('assignRole')->nullable();
            $table->string('assignPermission')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->integer('status')->default(1);
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
