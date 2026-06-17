<?php

namespace App\Http\Controllers;

use App\Models\ApplicationStatu;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class HospitalConfigController extends Controller
{
    public function index()
    {
        $config = ApplicationStatu::first();

        if (!$config) {
            $config = ApplicationStatu::create([
                'site_name' => env('APP_NAME', 'Hospital'),
                'version' => '1.0.0',
                'active' => true,
                'debug_mode' => false
            ]);
        }

        // Get service categories for dropdowns
        $serviceCategories = ServiceCategory::orderBy('category_name')->get();

        // Fetch dynamic models for LLM config
        $llmConfig = is_string($config->llm_config) ? json_decode($config->llm_config, true) : (is_array($config->llm_config) ? $config->llm_config : []);
        $providers = $llmConfig['providers'] ?? [];
        
        $providerModels = [];
        $gateway = app(\App\Services\LlmGatewayService::class);
        foreach (['gemini', 'anthropic', 'openai', 'ollama', 'huggingface'] as $prov) {
            if (!empty($providers[$prov]['api_key']) || $prov === 'ollama') {
                try {
                    $providerModels[$prov] = Cache::remember("llm_models_{$prov}", 3600, function() use ($gateway, $prov) {
                        return $gateway->listModels($prov);
                    });
                } catch (\Exception $e) {
                    $providerModels[$prov] = [];
                }
            } else {
                $providerModels[$prov] = [];
            }
        }

        return view('admin.hospital-config.index', compact('config', 'serviceCategories', 'providerModels'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_abbreviation' => 'nullable|string|max:10',
            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'hos_color' => 'nullable|string|max:7',
            'contact_address' => 'nullable|string',
            'contact_phones' => 'nullable|string',
            'contact_emails' => 'nullable|string',
            'social_links' => 'nullable|string',
            'description' => 'nullable|string',
            'version' => 'nullable|string|max:50',
            'active' => 'boolean',
            'debug_mode' => 'boolean',
            'notification_sound' => 'nullable',

            // Service Categories
            'bed_service_category_id' => 'nullable|integer|min:1',
            'investigation_category_id' => 'nullable|integer|min:1',
            'consultation_category_id' => 'nullable|integer|min:1',
            'nursing_service_category' => 'nullable|integer|min:1',
            'misc_service_category_id' => 'nullable|integer',
            'imaging_category_id'      => 'nullable|integer',
            'morgue_category_id'       => 'nullable|integer',
            'registration_category_id' => 'nullable|exists:service_categories,id',
            'procedure_category_id' => 'nullable|integer|exists:service_categories,id',

            // Time Windows
            'consultation_cycle_duration' => 'nullable|integer|min:1',
            'note_edit_window' => 'nullable|integer|min:1',
            'result_edit_duration' => 'nullable|integer|min:1',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|in:en,fr,nl,es,pt,ar,zh',
            'currency_symbol' => 'nullable|string|max:10',

            // Integration Settings
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',

            // DHIS2 Settings
            'dhis_api_url' => 'nullable|string',
            'dhis_org_unit' => 'nullable|string',
            'dhis_tracked_entity_program' => 'nullable|string',
            'dhis_tracked_entity_program_stage1' => 'nullable|string',
            'dhis_tracked_entity_program_stage2' => 'nullable|string',
            'dhis_tracked_entity_program_event_dataelement' => 'nullable|string',
            'dhis_username' => 'nullable|string',
            'dhis_pass' => 'nullable|string',
            'dhis_tracked_entity_type' => 'nullable|string',
            'dhis_tracked_entity_attr_fname' => 'nullable|string',
            'dhis_tracked_entity_attr_lname' => 'nullable|string',
            'dhis_tracked_entity_attr_gender' => 'nullable|string',
            'dhis_tracked_entity_attr_dob' => 'nullable|string',
            'dhis_tracked_entity_attr_city' => 'nullable|string',

            // CoreHMS SuperAdmin Settings
            'corehms_superadmin_url' => 'nullable|string',
            'corehms_superadmin_username' => 'nullable|string',
            'corehms_superadmin_pass' => 'nullable|string',

            // SMTP Configuration
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl',
            'smtp_from_address' => 'nullable|email|max:255',
            'smtp_from_name' => 'nullable|string|max:255',

            // Appointment Email Notifications
            'send_appointment_email_to_doctors' => 'boolean',
            'send_appointment_email_to_patients' => 'boolean',

            // Backup Settings
            'backup_compression' => 'boolean',

            // Feature Flags
            'goonline' => 'boolean',
            'chat_enabled' => 'boolean',
            'group_chat_enabled' => 'boolean',
            'requirediagnosis' => 'boolean',
            'enable_twakto' => 'boolean',
            'lab_results_require_approval' => 'boolean',
            'imaging_results_require_approval' => 'boolean',
            'doctor_can_enter_lab_result' => 'boolean',
            'nurse_can_enter_lab_result' => 'boolean',
            'doctor_can_enter_imaging_result' => 'boolean',
            'nurse_can_enter_imaging_result' => 'boolean',
            'doctor_self_approve_lab_result' => 'boolean',
            'nurse_self_approve_lab_result' => 'boolean',
            'doctor_self_approve_imaging_result' => 'boolean',
            'nurse_self_approve_imaging_result' => 'boolean',
            'allow_piece_sale' => 'boolean',
            'allow_halve_sale' => 'boolean',
            'consent_template' => 'nullable|string',
            'llm_config' => 'nullable|array',
        ]);

        $config = ApplicationStatu::first();

        if (!$config) {
            $config = new ApplicationStatu();
        }

        // Handle Checkboxes
        $validated['notification_sound'] = $request->has('notification_sound');
        $validated['goonline'] = $request->has('goonline');
        $validated['chat_enabled'] = $request->has('chat_enabled');
        $validated['group_chat_enabled'] = $request->has('group_chat_enabled');
        $validated['requirediagnosis'] = $request->has('requirediagnosis');
        $validated['enable_twakto'] = $request->has('enable_twakto');
        $validated['lab_results_require_approval'] = $request->has('lab_results_require_approval');
        $validated['imaging_results_require_approval'] = $request->has('imaging_results_require_approval');
        $validated['doctor_can_enter_lab_result'] = $request->has('doctor_can_enter_lab_result');
        $validated['nurse_can_enter_lab_result'] = $request->has('nurse_can_enter_lab_result');
        $validated['doctor_can_enter_imaging_result'] = $request->has('doctor_can_enter_imaging_result');
        $validated['nurse_can_enter_imaging_result'] = $request->has('nurse_can_enter_imaging_result');
        $validated['doctor_self_approve_lab_result'] = $request->has('doctor_self_approve_lab_result');
        $validated['nurse_self_approve_lab_result'] = $request->has('nurse_self_approve_lab_result');
        $validated['doctor_self_approve_imaging_result'] = $request->has('doctor_self_approve_imaging_result');
        $validated['nurse_self_approve_imaging_result'] = $request->has('nurse_self_approve_imaging_result');
        $validated['send_appointment_email_to_doctors'] = $request->has('send_appointment_email_to_doctors');
        $validated['send_appointment_email_to_patients'] = $request->has('send_appointment_email_to_patients');
        $validated['backup_compression'] = $request->has('backup_compression');
        $validated['allow_piece_sale'] = $request->has('allow_piece_sale');
        $validated['allow_halve_sale'] = $request->has('allow_halve_sale');

        // Handle LLM Config Checkboxes and processing
        if ($request->has('llm_config')) {
            $llmConfig = $request->input('llm_config');
            
            $existingLlmConfig = is_string($config->llm_config) ? json_decode($config->llm_config, true) : (is_array($config->llm_config) ? $config->llm_config : []);

            $llmConfig['enabled'] = isset($llmConfig['enabled']);
            $llmConfig['summary_voice_enabled'] = isset($llmConfig['summary_voice_enabled']);
            // Ensure numbers are cast correctly if needed
            $llmConfig['summary_scope_months'] = (int) ($llmConfig['summary_scope_months'] ?? 3);
            $llmConfig['summary_scope_max_entries'] = (int) ($llmConfig['summary_scope_max_entries'] ?? 50);

            // Merge providers securely
            if (isset($llmConfig['providers']) && is_array($llmConfig['providers'])) {
                foreach ($llmConfig['providers'] as $provider => $providerData) {
                    if (empty($providerData['api_key'])) {
                        // Restore old api key if new one is empty
                        $llmConfig['providers'][$provider]['api_key'] = $existingLlmConfig['providers'][$provider]['api_key'] ?? '';
                    } else {
                        // Encrypt new API key
                        try {
                            $llmConfig['providers'][$provider]['api_key'] = \Illuminate\Support\Facades\Crypt::encryptString($providerData['api_key']);
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to encrypt API key for ' . $provider);
                            $llmConfig['providers'][$provider]['api_key'] = $existingLlmConfig['providers'][$provider]['api_key'] ?? '';
                        }
                    }
                }
            }

            // The UI now submits system_prompts. Merge any missing ones from existing config.
            if (isset($llmConfig['system_prompts']) && is_array($llmConfig['system_prompts'])) {
                $llmConfig['system_prompts'] = array_merge($existingLlmConfig['system_prompts'] ?? [], $llmConfig['system_prompts']);
            } else {
                $llmConfig['system_prompts'] = $existingLlmConfig['system_prompts'] ?? [];
            }
            $llmConfig['rag_settings'] = $existingLlmConfig['rag_settings'] ?? [];

            $validated['llm_config'] = $llmConfig;
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoFile = $request->file('logo');
            $logoBase64 = base64_encode(file_get_contents($logoFile->getRealPath()));
            $validated['logo'] = $logoBase64;
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $faviconFile = $request->file('favicon');
            $faviconBase64 = base64_encode(file_get_contents($faviconFile->getRealPath()));
            $validated['favicon'] = $faviconBase64;
        }

        $config->fill($validated);
        $config->save();

        // Clear cache to ensure new settings are loaded immediately
        clearAppSettingsCache();

        return redirect()->route('hospital-config.index')
            ->with('success', 'Hospital configuration updated successfully!');
    }
}
