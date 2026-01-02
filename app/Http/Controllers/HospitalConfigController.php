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
            'debug_mode' => 'boolean'
        ]);

        $config = ApplicationStatu::first();

        if (!$config) {
            $config = new ApplicationStatu();
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

        return redirect()->route('hospital-config.index')
            ->with('success', 'Hospital configuration updated successfully!');
    }
}
