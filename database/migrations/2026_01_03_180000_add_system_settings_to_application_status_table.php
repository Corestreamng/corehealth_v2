<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSystemSettingsToApplicationStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            // Service Category IDs
            $table->integer('bed_service_category_id')->default(3)->after('allow_halve_sale');
            $table->integer('investigation_category_id')->default(2)->after('bed_service_category_id');
            $table->integer('consultation_category_id')->default(1)->after('investigation_category_id');
            $table->integer('nursing_service_category')->default(4)->after('consultation_category_id');
            $table->integer('misc_service_category_id')->default(5)->after('nursing_service_category');
            $table->integer('imaging_category_id')->default(6)->after('misc_service_category_id');

            // Time-based settings (in hours)
            $table->integer('consultation_cycle_duration')->default(24)->after('imaging_category_id')->comment('Hours before consultation expires');
            $table->integer('note_edit_window')->default(60)->after('consultation_cycle_duration')->comment('Minutes after encounter note creation that editing is allowed');
            $table->integer('result_edit_duration')->default(60)->after('note_edit_window')->comment('Minutes after result entry that editing is allowed');

            // Feature flags
            $table->boolean('goonline')->default(0)->after('result_edit_duration')->comment('Enable DHIS2 patient enrollment');
            $table->boolean('requirediagnosis')->default(0)->after('goonline')->comment('Require diagnosis during consultation');
            $table->boolean('enable_twakto')->default(0)->after('requirediagnosis')->comment('Enable Tawk.to support');

            // DHIS2 Integration Settings
            $table->string('dhis_api_url')->nullable()->after('enable_twakto');
            $table->string('dhis_org_unit')->nullable()->after('dhis_api_url');
            $table->string('dhis_tracked_entity_program')->nullable()->after('dhis_org_unit');
            $table->string('dhis_tracked_entity_program_stage1')->nullable()->after('dhis_tracked_entity_program');
            $table->string('dhis_tracked_entity_program_stage2')->nullable()->after('dhis_tracked_entity_program_stage1');
            $table->string('dhis_tracked_entity_program_event_dataelement')->nullable()->after('dhis_tracked_entity_program_stage2');
            $table->string('dhis_username')->nullable()->after('dhis_tracked_entity_program_event_dataelement');
            $table->string('dhis_pass')->nullable()->after('dhis_username');
            $table->string('dhis_tracked_entity_type')->nullable()->after('dhis_pass');
            $table->string('dhis_tracked_entity_attr_fname')->nullable()->after('dhis_tracked_entity_type');
            $table->string('dhis_tracked_entity_attr_lname')->nullable()->after('dhis_tracked_entity_attr_fname');
            $table->string('dhis_tracked_entity_attr_gender')->nullable()->after('dhis_tracked_entity_attr_lname');
            $table->string('dhis_tracked_entity_attr_dob')->nullable()->after('dhis_tracked_entity_attr_gender');
            $table->string('dhis_tracked_entity_attr_city')->nullable()->after('dhis_tracked_entity_attr_dob');

            // API Credentials
            $table->string('client_id')->nullable()->after('dhis_tracked_entity_attr_city');
            $table->string('client_secret')->nullable()->after('client_id');

            // CoreHMS SuperAdmin Settings
            $table->string('corehms_superadmin_url')->nullable()->after('client_secret');
            $table->string('corehms_superadmin_username')->nullable()->after('corehms_superadmin_url');
            $table->string('corehms_superadmin_pass')->nullable()->after('corehms_superadmin_username');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn([
                'bed_service_category_id',
                'investigation_category_id',
                'consultation_category_id',
                'nursing_service_category',
                'misc_service_category_id',
                'imaging_category_id',
                'consultation_cycle_duration',
                'note_edit_window',
                'result_edit_duration',
                'goonline',
                'requirediagnosis',
                'enable_twakto',
                'dhis_api_url',
                'dhis_org_unit',
                'dhis_tracked_entity_program',
                'dhis_tracked_entity_program_stage1',
                'dhis_tracked_entity_program_stage2',
                'dhis_tracked_entity_program_event_dataelement',
                'dhis_username',
                'dhis_pass',
                'dhis_tracked_entity_type',
                'dhis_tracked_entity_attr_fname',
                'dhis_tracked_entity_attr_lname',
                'dhis_tracked_entity_attr_gender',
                'dhis_tracked_entity_attr_dob',
                'dhis_tracked_entity_attr_city',
                'client_id',
                'client_secret',
                'corehms_superadmin_url',
                'corehms_superadmin_username',
                'corehms_superadmin_pass',
            ]);
        });
    }
}
