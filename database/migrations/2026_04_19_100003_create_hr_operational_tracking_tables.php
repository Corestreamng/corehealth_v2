<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Enhancement - Operational Tracking Tables
 * Qualifications, Promotions, Trainings, Medical Exams, Next of Kin, Follow-ups
 */
return new class extends Migration
{
    public function up(): void
    {
        // Staff Qualifications - education & certifications
        Schema::create('staff_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->enum('type', ['entry', 'additional'])->default('entry');
            $table->string('qualification_name');
            $table->string('field_of_study')->nullable();
            $table->string('institution')->nullable();
            $table->year('year_of_graduation')->nullable();
            $table->boolean('result_seen')->default(false);
            $table->foreignId('result_seen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('result_seen_at')->nullable();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'type']);
        });

        // Staff Promotions - promotion history log
        Schema::create('staff_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('from_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->foreignId('to_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->string('from_job_title')->nullable();
            $table->string('to_job_title')->nullable();
            $table->date('promotion_date');
            $table->date('effective_date')->nullable();
            $table->date('next_promotion_due_date')->nullable();
            $table->string('authority')->nullable()->comment('Who approved the promotion');
            $table->text('remarks')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'promotion_date']);
        });

        // Staff Trainings - attended, identified, career plan
        Schema::create('staff_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->enum('type', ['attended', 'identified', 'career_plan']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('institution')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->string('certificate_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'type']);
            $table->index(['staff_id', 'status']);
        });

        // Staff Medical Exams - periodic health checks
        Schema::create('staff_medical_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('exam_date');
            $table->enum('exam_type', ['pre_employment', 'periodic', 'exit'])->default('periodic');
            $table->enum('result', ['fit', 'unfit', 'conditional'])->nullable();
            $table->date('next_exam_due')->nullable();
            $table->string('conducted_by')->nullable()->comment('Name of examiner/facility');
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'exam_date']);
        });

        // Staff Next of Kin - formal NOK records (multiple per staff)
        Schema::create('staff_next_of_kin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['staff_id', 'is_primary']);
        });

        // Staff Follow-ups - operational notes and action items
        Schema::create('staff_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('subject');
            $table->text('details')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_follow_ups');
        Schema::dropIfExists('staff_next_of_kin');
        Schema::dropIfExists('staff_medical_exams');
        Schema::dropIfExists('staff_trainings');
        Schema::dropIfExists('staff_promotions');
        Schema::dropIfExists('staff_qualifications');
    }
};
