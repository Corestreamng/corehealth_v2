<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class ApplicationStatu extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'site_name',
        'header_text',
        'footer_text',
        'logo',
        'favicon',
        'hos_color',
        'contact_address',
        'contact_phones',
        'contact_emails',
        'social_links',
        'description',
        'version',
        'active',
        'debug_mode',
        'notification_sound',
        'bed_service_category_id',
        'investigation_category_id',
        'consultation_category_id',
        'nursing_service_category',
        'misc_service_category_id',
        'imaging_category_id',
        'consultation_cycle_duration',
        'note_edit_window',
        'result_edit_duration',
        'timezone',
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
        'registration_category_id',
        'procedure_category_id',
    ];

    /**
     * Get the registration service category.
     */
    public function registrationCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'registration_category_id', 'id');
    }

    /**
     * Get the procedure service category.
     */
    public function procedureCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'procedure_category_id', 'id');
    }
}
