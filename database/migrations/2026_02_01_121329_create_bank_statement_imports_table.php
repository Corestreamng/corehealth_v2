<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBankStatementImportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('bank_statement_imports')) {
            return;
        }

        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('bank_reconciliations')->onDelete('cascade');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('file_type', 20); // pdf, excel, csv, word, image
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 20)->default('uploaded'); // uploaded, processing, ready, error
            $table->text('html_content')->nullable(); // Rendered HTML for Excel/CSV/Word
            $table->unsignedInteger('rows_detected')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['reconciliation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bank_statement_imports');
    }
}
