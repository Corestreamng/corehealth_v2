<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nhmis_monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('form_version', 50)->default('v2019');
            $table->string('facility_code', 50)->nullable();
            $table->integer('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->enum('status', ['draft', 'compiled', 'verified', 'locked'])->default('draft');
            $table->json('metadata')->nullable(); // facility details, beds, supervision, REW microplan, etc.
            $table->foreignId('compiled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('compiled_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['form_version', 'year', 'month'], 'nhmis_reports_version_period_unique');
            $table->index(['year', 'month']);
            $table->index('status');
        });

        Schema::create('nhmis_monthly_report_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('nhmis_monthly_reports')->onDelete('cascade');
            $table->string('cell_key', 100); // e.g. row_1:m_0_28d, row_10:total, row_65:fixed_lt_1y
            $table->decimal('auto_value', 14, 2)->nullable();
            $table->decimal('override_value', 14, 2)->nullable();
            $table->decimal('final_value', 14, 2)->nullable();
            $table->string('override_reason', 255)->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'cell_key'], 'nhmis_values_report_cell_unique');
            $table->index('cell_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nhmis_monthly_report_values');
        Schema::dropIfExists('nhmis_monthly_reports');
    }
};
