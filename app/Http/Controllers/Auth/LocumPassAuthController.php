<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Locum\LocumManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Locum Shift Pass Authentication Controller
 *
 * Provides fast 1-tap PIN login (< 5 seconds) for shift-bounded locum practitioners.
 */
class LocumPassAuthController extends Controller
{
    protected LocumManagementService $locumService;

    public function __construct(LocumManagementService $locumService)
    {
        $this->middleware('guest')->except('logout');
        $this->locumService = $locumService;
    }

    /**
     * Authenticate locum clinician using Phone/Email and 6-digit Shift PIN.
     */
    public function loginWithPass(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'pin' => 'required|string|min:6|max:12',
        ], [
            'identifier.required' => 'Please enter your registered phone number or email.',
            'pin.required' => 'Please enter your 6-digit shift pass PIN.',
        ]);

        $result = $this->locumService->authenticatePass($request->identifier, $request->pin);

        if (!$result['success']) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            return back()->withInput($request->only('identifier'))
                ->withErrors(['identifier' => $result['message']]);
        }

        // Login user
        $user = $result['user'];
        Auth::login($user, (bool) $request->boolean('remember'));

        // Resolve landing destination based on clinical roles
        $redirectUrl = $this->resolveLandingWorkbench($user);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Welcome, ' . $user->name . '. Shift pass verified.',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->intended($redirectUrl);
    }

    /**
     * Route locum practitioner to their respective clinical workbench.
     */
    protected function resolveLandingWorkbench($user): string
    {
        if ($user->hasRole('DOCTOR') || $user->is_admin == 21) {
            return route('encounters.create');
        }

        if ($user->hasRole('RADIOLOGIST')) {
            return url('/imaging/workbench');
        }

        if ($user->hasRole('LAB SCIENTIST') || $user->is_admin == 24) {
            return url('/lab/workbench');
        }

        if ($user->hasRole('PHARMACIST') || $user->is_admin == 23) {
            return url('/pharmacy/workbench');
        }

        if ($user->hasRole('NURSE') || $user->is_admin == 22) {
            return url('/nursing/workbench');
        }

        return route('home');
    }
}
