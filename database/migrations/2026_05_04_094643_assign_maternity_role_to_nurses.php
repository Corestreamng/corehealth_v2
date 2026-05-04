<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AssignMaternityRoleToNurses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Get all users who have the 'NURSE' role
        $nurses = \App\Models\User::role('NURSE')->get();
        
        foreach ($nurses as $nurse) {
            // Assign 'MATERNITY' role if not already assigned
            if (!$nurse->hasRole('MATERNITY')) {
                $nurse->assignRole('MATERNITY');
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This is tricky as we might remove role from someone who should have it
        // but for the sake of reversal:
        $nurses = \App\Models\User::role('NURSE')->get();
        
        foreach ($nurses as $nurse) {
            if ($nurse->hasRole('MATERNITY')) {
                $nurse->removeRole('MATERNITY');
            }
        }
    }
}
