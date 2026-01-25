<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeDepartmentToForeignKeyInStaffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            // Drop the old string department column if it exists
            if (Schema::hasColumn('staff', 'department')) {
                $table->dropColumn('department');
            }

            // Add department_id as foreign key
            if (!Schema::hasColumn('staff', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('job_title')->constrained('departments')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            // Drop foreign key and column
            if (Schema::hasColumn('staff', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }

            // Restore string column
            if (!Schema::hasColumn('staff', 'department')) {
                $table->string('department')->nullable()->after('job_title');
            }
        });
    }
}
