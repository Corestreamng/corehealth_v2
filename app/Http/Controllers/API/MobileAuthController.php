<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatu;
use App\Models\patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MobileAuthController extends Controller
{
    /**
     * Public endpoint — returns branding info for the instance.
     * Called before login so the app can display the hospital's logo/colors.
     */
    public function instanceInfo()
    {
        try {
            $settings = ApplicationStatu::first();

            return response()->json([
                'status' => true,
                'data' => [
                    'site_name'          => $settings->site_name ?? 'CoreHealth',
                    'header_text'        => $settings->header_text ?? '',
                    'hos_color'          => $settings->hos_color ?? '#0066cc',
                    'logo'               => $settings->logo, // base64
                    'favicon'            => $settings->favicon,
                    'contact_address'    => $settings->contact_address ?? '',
                    'contact_phones'     => $settings->contact_phones ?? '',
                    'contact_emails'     => $settings->contact_emails ?? '',
                    'version'            => $settings->version ?? '2.0',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile instanceInfo error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve instance info.',
            ], 500);
        }
    }

    /**
     * Staff login (Doctor app).
     * Returns a Sanctum token + user profile.
     */
    public function staffLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            // Revoke previous mobile tokens
            $user->tokens()->where('name', 'mobile-doctor')->delete();

            $token = $user->createToken('mobile-doctor')->plainTextToken;

            $staff = $user->staff;

            return response()->json([
                'status' => true,
                'message' => 'Login successful.',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id'    => $user->id,
                        'name'  => $user->name,
                        'email' => $user->email,
                        'role'  => $user->is_admin,
                        'roles' => $user->getRoleNames(),
                    ],
                    'staff' => $staff ? [
                        'id'          => $staff->id,
                        'first_name'  => $staff->first_name,
                        'last_name'   => $staff->last_name,
                        'gender'      => $staff->gender,
                        'phone'       => $staff->mobile_phone,
                        'department'  => $staff->department,
                        'designation' => $staff->designation,
                        'photo'       => $staff->photo,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile staffLogin error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Login failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Patient login (Patient app).
     * Uses patient_id (hospital number) + phone to authenticate.
     */
    public function patientLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|string',
            'phone'      => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $patient = patient::with('user', 'hmo.scheme')
                ->where('file_no', $request->patient_id)
                ->first();

            if (! $patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found. Please check your hospital number.',
                ], 404);
            }

            // Verify phone number matches
            $patientPhone = preg_replace('/\D/', '', $patient->phone_no ?? '');
            $inputPhone   = preg_replace('/\D/', '', $request->phone);

            if (substr($patientPhone, -10) !== substr($inputPhone, -10)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Phone number does not match our records.',
                ], 401);
            }

            // Create a token for the patient's user account
            $user = $patient->user;
            if ($user) {
                $user->tokens()->where('name', 'mobile-patient')->delete();
                $token = $user->createToken('mobile-patient')->plainTextToken;
            } else {
                $token = null;
            }

            return response()->json([
                'status' => true,
                'message' => 'Login successful.',
                'data' => [
                    'token' => $token,
                    'patient' => [
                        'id'          => $patient->id,
                        'card_no'     => $patient->file_no,
                        'first_name'  => $patient->first_name,
                        'last_name'   => $patient->last_name,
                        'gender'      => $patient->gender,
                        'dob'         => $patient->date_of_birth,
                        'phone'       => $patient->phone_no,
                        'email'       => $patient->email,
                        'blood_group' => $patient->blood_group,
                        'genotype'    => $patient->genotype,
                        'address'     => $patient->address,
                        'photo'       => $patient->photo,
                        'hmo' => $patient->hmo ? [
                            'name'   => $patient->hmo->scheme->scheme_name ?? null,
                            'plan'   => $patient->hmo->plan ?? null,
                            'status' => $patient->hmo->status ?? null,
                        ] : null,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile patientLogin error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Login failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout — revoke the current token.
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['status' => true, 'message' => 'Logged out.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Logout failed.'], 500);
        }
    }
}
