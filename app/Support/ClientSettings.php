<?php

namespace App\Support;

use App\Services\LlmGatewayService;

/**
 * Hospital Config values (appsettings(), application_status) that change how the clinical and
 * front-desk workbenches behave, computed once with the web's defaults.
 *
 * The web encounter page renders these into WORKBENCH_CONFIG and the native clients receive the
 * same array from GET /api/mobile/staff/settings, so a setting changed in Hospital Config changes
 * both, and their defaults can't drift apart.
 */
class ClientSettings
{
    /** Next-of-kin relationships offered on every registration form (web and staff app). */
    public const NOK_RELATIONSHIPS = ['Spouse', 'Father', 'Mother', 'Son', 'Daughter', 'Brother', 'Sister', 'Guardian', 'Grandparent', 'Grandchild', 'Uncle', 'Aunt', 'Cousin', 'Friend', 'Employer', 'Other'];

    public static function workbench(): array
    {
        $structured = (bool) (appsettings('enable_structured_dose') ?? 1);

        return [
            'nok_relationships' => self::NOK_RELATIONSHIPS,
            // CoreHealth version, as the web sidebar shows it (_brand.blade.php)
            'core_version' => (appsettings()->version ?? null) ?: env('APP_VER', '1.0'),
            // Branding
            'hos_color' => appsettings('hos_color') ?? '#0066cc',
            'site_name' => appsettings('site_name'),
            'contact_phones' => appsettings('contact_phones'),
            'contact_address' => appsettings('contact_address'),
            'contact_emails' => appsettings('contact_emails'),

            // Prescribing and orders
            'enable_structured_dose' => $structured,
            'default_dose_mode' => $structured ? (appsettings('default_dose_mode') ?? 'structured') : 'simple',
            'allow_doctor_set_procedure_price' => (bool) (appsettings('allow_doctor_set_procedure_price') ?? 0),
            'procedure_category_id' => (int) (appsettings('procedure_category_id', 8) ?: 8),
            'investigation_category_id' => (int) (appsettings('investigation_category_id', 2) ?: 2),
            'imaging_category_id' => (int) (appsettings('imaging_category_id', 6) ?: 6),

            // Clinical notes and treatment plans
            'require_diagnosis' => (bool) appsettings('requirediagnosis'),
            'note_edit_window' => (int) (appsettings('note_edit_window') ?? appsettings('note_edit_duration') ?? 30),
            'enable_treatment_plans_in_consult' => (bool) appsettings('enable_treatment_plans_in_consult', true),
            'require_treatment_plan_in_consult' => (bool) appsettings('require_treatment_plan_in_consult', false),

            // Results
            'lab_results_require_approval' => (bool) appsettings('lab_results_require_approval'),
            'imaging_results_require_approval' => (bool) appsettings('imaging_results_require_approval'),
            'doctor_self_approve_lab_result' => (bool) appsettings('doctor_self_approve_lab_result'),
            'doctor_self_approve_imaging_result' => (bool) appsettings('doctor_self_approve_imaging_result'),
            'nurse_self_approve_lab_result' => (bool) appsettings('nurse_self_approve_lab_result'),
            'nurse_self_approve_imaging_result' => (bool) appsettings('nurse_self_approve_imaging_result'),

            // Admission / discharge and emergency intake
            'doctor_full_admission' => (bool) appsettings('doctor_full_admission'),
            'doctor_full_discharge' => (bool) appsettings('doctor_full_discharge'),
            'enable_ei_reception' => (bool) appsettings('enable_ei_reception'),
            'enable_ei_doctor' => (bool) appsettings('enable_ei_doctor'),

            // AI features (AI Scribe Studio, note polish, patient briefing)
            'llm_enabled' => self::llmEnabled(),
        ];
    }

    protected static function llmEnabled(): bool
    {
        try {
            return app(LlmGatewayService::class)->isEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
