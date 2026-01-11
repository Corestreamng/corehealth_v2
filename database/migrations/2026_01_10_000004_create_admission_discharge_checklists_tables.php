<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Admission and Discharge Checklists
 *
 * Reference: Plan Phase 4 - Admission/Discharge Workflow
 *
 * Current flow analysis:
 * - Doctor creates AdmissionRequest (admission_requests.store)
 * - Doctor can optionally select bed, but nurse should assign
 * - Nurse assigns bed via assign-bed route
 * - Doctor requests discharge via discharge modal
 * - Discharge happens immediately without nurse verification
 *
 * New flow with checklists:
 * - Doctor creates AdmissionRequest (status: pending_checklist)
 * - Nurse completes admission checklist items
 * - After checklist complete, nurse assigns bed (admission finalized)
 * - Doctor requests discharge (status: discharge_requested)
 * - Nurse completes discharge checklist items
 * - After checklist complete, nurse releases bed (discharge finalized)
 *
 * Checklist types based on nursing standards:
 * @see https://www.jointcommission.org/standards/
 *
 * @see App\Models\AdmissionChecklist
 * @see App\Models\DischargeChecklist
 * @see App\Models\ChecklistTemplate
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Checklist templates - reusable checklist definitions
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Template name');
            $table->enum('type', ['admission', 'discharge'])->comment('Checklist type');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->comment('Use by default for this type');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Checklist items within templates
        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->string('item_text')->comment('Checklist item description');
            $table->text('guidance')->nullable()->comment('Help text for completing item');
            $table->boolean('is_required')->default(true)->comment('Must be completed');
            $table->boolean('requires_comment')->default(false)->comment('Requires note when checked');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Admission checklists - per admission instance
        Schema::create('admission_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_request_id')->constrained('admission_requests')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('checklist_templates')->nullOnDelete();

            // Status tracking
            $table->enum('status', [
                'pending',      // Not started
                'in_progress',  // Some items completed
                'completed',    // All required items done
                'waived'        // Bypassed with reason
            ])->default('pending');

            // Completion tracking
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            // Waiver (if bypassed)
            $table->text('waiver_reason')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One checklist per admission
            $table->unique('admission_request_id');
        });

        // Admission checklist items - completed items
        Schema::create('admission_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_checklist_id')->constrained('admission_checklists')->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('checklist_template_items')->nullOnDelete();

            // Item details (copied from template for historical record)
            $table->string('item_text');
            $table->boolean('is_required')->default(true);

            // Completion
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable()->comment('Notes when completing item');

            $table->timestamps();
        });

        // Discharge checklists - per discharge instance
        Schema::create('discharge_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_request_id')->constrained('admission_requests')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('checklist_templates')->nullOnDelete();

            // Status tracking
            $table->enum('status', [
                'pending',      // Not started
                'in_progress',  // Some items completed
                'completed',    // All required items done
                'waived'        // Bypassed with reason
            ])->default('pending');

            // Completion tracking
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            // Waiver (if bypassed)
            $table->text('waiver_reason')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One discharge checklist per admission
            $table->unique('admission_request_id');
        });

        // Discharge checklist items - completed items
        Schema::create('discharge_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discharge_checklist_id')->constrained('discharge_checklists')->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('checklist_template_items')->nullOnDelete();

            // Item details
            $table->string('item_text');
            $table->boolean('is_required')->default(true);

            // Completion
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();

            $table->timestamps();
        });

        // Add admission workflow status to admission_requests
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->enum('admission_status', [
                'pending_checklist',    // Awaiting admission checklist
                'checklist_complete',   // Checklist done, awaiting bed
                'admitted',             // Bed assigned, fully admitted
                'discharge_requested',  // Doctor requested discharge
                'discharge_checklist',  // Completing discharge checklist
                'discharged'            // Fully discharged
            ])->default('admitted')->after('status')
                ->comment('Workflow status for admission/discharge process');
        });

        // Seed default checklist templates
        $this->seedDefaultTemplates();
    }

    /**
     * Seed default admission and discharge checklist templates
     * Based on Joint Commission and nursing best practices
     */
    private function seedDefaultTemplates(): void
    {
        // Default Admission Checklist
        $admissionTemplateId = \DB::table('checklist_templates')->insertGetId([
            'name' => 'Standard Admission Checklist',
            'type' => 'admission',
            'description' => 'Default checklist for patient admission',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admissionItems = [
            ['item_text' => 'Patient identification verified (ID band placed)', 'sort_order' => 1],
            ['item_text' => 'Vital signs recorded', 'sort_order' => 2],
            ['item_text' => 'Allergy information documented and band placed if applicable', 'sort_order' => 3],
            ['item_text' => 'Patient belongings inventory completed', 'sort_order' => 4],
            ['item_text' => 'Admission assessment completed', 'sort_order' => 5],
            ['item_text' => 'Fall risk assessment completed', 'sort_order' => 6],
            ['item_text' => 'Pressure ulcer risk assessment completed', 'sort_order' => 7],
            ['item_text' => 'Medication reconciliation completed', 'sort_order' => 8],
            ['item_text' => 'Patient oriented to room and unit', 'sort_order' => 9],
            ['item_text' => 'Call bell and bed controls explained', 'sort_order' => 10],
            ['item_text' => 'Emergency procedures explained', 'sort_order' => 11],
            ['item_text' => 'Diet order verified and meal service explained', 'sort_order' => 12],
            ['item_text' => 'IV access established if ordered', 'sort_order' => 13, 'is_required' => false],
            ['item_text' => 'Consent forms signed', 'sort_order' => 14],
            ['item_text' => 'Next of kin/emergency contact verified', 'sort_order' => 15],
        ];

        foreach ($admissionItems as $item) {
            \DB::table('checklist_template_items')->insert([
                'template_id' => $admissionTemplateId,
                'item_text' => $item['item_text'],
                'is_required' => $item['is_required'] ?? true,
                'sort_order' => $item['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Default Discharge Checklist
        $dischargeTemplateId = \DB::table('checklist_templates')->insertGetId([
            'name' => 'Standard Discharge Checklist',
            'type' => 'discharge',
            'description' => 'Default checklist for patient discharge',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dischargeItems = [
            ['item_text' => 'Doctor\'s discharge order verified', 'sort_order' => 1],
            ['item_text' => 'Outstanding bills reviewed and payment arranged', 'sort_order' => 2],
            ['item_text' => 'Discharge medications dispensed and explained', 'sort_order' => 3],
            ['item_text' => 'Discharge instructions provided and reviewed', 'sort_order' => 4],
            ['item_text' => 'Follow-up appointments scheduled', 'sort_order' => 5],
            ['item_text' => 'Warning signs requiring return explained', 'sort_order' => 6],
            ['item_text' => 'Activity restrictions explained', 'sort_order' => 7],
            ['item_text' => 'Dietary instructions provided if applicable', 'sort_order' => 8, 'is_required' => false],
            ['item_text' => 'Wound care instructions provided if applicable', 'sort_order' => 9, 'is_required' => false],
            ['item_text' => 'Medical equipment arranged if needed', 'sort_order' => 10, 'is_required' => false],
            ['item_text' => 'Transportation arranged', 'sort_order' => 11],
            ['item_text' => 'Patient belongings returned and verified', 'sort_order' => 12],
            ['item_text' => 'ID band and allergy band removed', 'sort_order' => 13],
            ['item_text' => 'Final vital signs recorded', 'sort_order' => 14],
            ['item_text' => 'Patient and family verbalized understanding of discharge instructions', 'sort_order' => 15],
            ['item_text' => 'Discharge summary copy provided', 'sort_order' => 16],
        ];

        foreach ($dischargeItems as $item) {
            \DB::table('checklist_template_items')->insert([
                'template_id' => $dischargeTemplateId,
                'item_text' => $item['item_text'],
                'is_required' => $item['is_required'] ?? true,
                'sort_order' => $item['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropColumn('admission_status');
        });

        Schema::dropIfExists('discharge_checklist_items');
        Schema::dropIfExists('discharge_checklists');
        Schema::dropIfExists('admission_checklist_items');
        Schema::dropIfExists('admission_checklists');
        Schema::dropIfExists('checklist_template_items');
        Schema::dropIfExists('checklist_templates');
    }
};
