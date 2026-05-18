<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNonPharmOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('non_pharm_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->onDelete('set null');
            $table->foreignId('maternity_enrollment_id')->nullable()->constrained('maternity_enrollments')->onDelete('set null');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            
            $table->string('category', 50); // e.g., 'Diet', 'Exercise', 'Counseling', 'Nursing Care', 'Other'
            $table->string('target_executor', 20)->default('patient'); // 'patient' (home care) or 'nurse' (bedside care)
            $table->text('instructions');
            $table->string('frequency', 50)->nullable(); // e.g., 'Daily', 'Q2H', 'PRN'
            $table->string('duration', 50)->nullable(); // e.g., '3 days', 'Ongoing'
            
            $table->string('status', 20)->default('active'); // 'active', 'completed', 'discontinued'
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('discontinued_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('discontinued_at')->nullable();
            $table->string('discontinue_reason')->nullable();
            
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
        Schema::dropIfExists('non_pharm_orders');
    }
}
