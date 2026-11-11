<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGuardianFieldsToPatientConsentsTable extends Migration
{
    /**
     * Run the migrations.
     * Implements NDPA 2023 Section 31 (Children's Personal Data / Parental Verification).
     */
    public function up(): void
    {
        if (Schema::hasTable('patient_consents')) {
            Schema::table('patient_consents', function (Blueprint $table) {
                if (!Schema::hasColumn('patient_consents', 'parent_guardian_name')) {
                    $table->string('parent_guardian_name', 191)->nullable()->after('notes');
                }
                if (!Schema::hasColumn('patient_consents', 'parent_guardian_nin')) {
                    $table->string('parent_guardian_nin', 32)->nullable()->after('parent_guardian_name');
                }
                if (!Schema::hasColumn('patient_consents', 'parent_guardian_relationship')) {
                    $table->string('parent_guardian_relationship', 64)->nullable()->after('parent_guardian_nin');
                }
                if (!Schema::hasColumn('patient_consents', 'is_minor')) {
                    $table->boolean('is_minor')->default(false)->after('parent_guardian_relationship');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('patient_consents')) {
            Schema::table('patient_consents', function (Blueprint $table) {
                $columns = ['parent_guardian_name', 'parent_guardian_nin', 'parent_guardian_relationship', 'is_minor'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('patient_consents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
