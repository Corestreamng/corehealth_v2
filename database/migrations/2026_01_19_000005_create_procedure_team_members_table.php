<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcedureTeamMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procedure_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procedure_id');
            $table->unsignedBigInteger('user_id');

            // Role - includes "other" for custom roles
            $table->enum('role', [
                'chief_surgeon',
                'assistant_surgeon',
                'anesthesiologist',
                'nurse_anesthetist',
                'scrub_nurse',
                'circulating_nurse',
                'surgical_first_assistant',
                'perfusionist',
                'radiologist',
                'pathologist',
                'other'
            ]);
            $table->string('custom_role', 100)->nullable(); // Used when role = 'other'

            $table->boolean('is_lead')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('procedure_id')
                  ->references('id')
                  ->on('procedures')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Prevent duplicate user in same procedure with same role
            $table->unique(['procedure_id', 'user_id', 'role'], 'procedure_team_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procedure_team_members');
    }
}
