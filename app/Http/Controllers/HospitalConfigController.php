<?php

namespace App\Http\Controllers;

use App\Models\ApplicationStatu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        return view('admin.hospital-config.index', compact('config'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
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
            'misc_service_category_id' => 'nullable|integer|min:1',
            'imaging_category_id' => 'nullable|integer|min:1',

            // Time Windows
            'consultation_cycle_duration' => 'nullable|integer|min:1',
            'note_edit_window' => 'nullable|integer|min:1',
            'result_edit_duration' => 'nullable|integer|min:1',

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

            // Feature Flags
            'goonline' => 'boolean',
            'requirediagnosis' => 'boolean',
            'enable_twakto' => 'boolean',
        ]);

        $config = ApplicationStatu::first();

        if (!$config) {
            $config = new ApplicationStatu();
        }

        // Handle Checkboxes
        $validated['notification_sound'] = $request->has('notification_sound');
        $validated['goonline'] = $request->has('goonline');
        $validated['requirediagnosis'] = $request->has('requirediagnosis');
        $validated['enable_twakto'] = $request->has('enable_twakto');

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
        \Cache::forget('application_settings');

        return redirect()->route('hospital-config.index')
            ->with('success', 'Hospital configuration updated successfully!');
    }
}
