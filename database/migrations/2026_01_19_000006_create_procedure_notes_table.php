<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcedureNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procedure_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procedure_id');

            $table->enum('note_type', [
                'pre_op',
                'intra_op',
                'post_op',
                'anesthesia',
                'nursing'
            ]);

            $table->string('title')->nullable();
            $table->longText('content'); // LONGTEXT for CKEditor WYSIWYG rich content
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('procedure_id')
                  ->references('id')
                  ->on('procedures')
                  ->onDelete('cascade');

            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            // Index for filtering by type
            $table->index(['procedure_id', 'note_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procedure_notes');
    }
}
