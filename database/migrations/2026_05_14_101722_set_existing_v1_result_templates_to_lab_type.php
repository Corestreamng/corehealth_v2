<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SetExistingV1ResultTemplatesToLabType extends Migration
{
    /**
     * Set all existing V1 result templates (which defaulted to 'both') to 'lab'.
     * This reflects that historical templates were created for lab use.
     */
    public function up()
    {
        DB::table('v1_result_templates')
            ->where('template_type', 'both')
            ->update(['template_type' => 'lab']);
    }

    /**
     * Reverse: restore 'lab' records back to 'both'.
     */
    public function down()
    {
        DB::table('v1_result_templates')
            ->where('template_type', 'lab')
            ->update(['template_type' => 'both']);
    }
}
