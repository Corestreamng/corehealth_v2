<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function index()
    {
        $clinics = Clinic::all();
        return view('admin.clinic.index', ['clinics' => $clinics]);
    }

    public function create()
    {
        return view('admin.clinic.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Clinic::create($validatedData);
        return back()->withMessage('Clinic created successfully')->withMessageType('success');
    }

    public function show(Clinic $clinic)
    {
        return view('admin.clinic.show', ['clinic' => $clinic]);
    }

    public function edit(Clinic $clinic)
    {
        return view('admin.clinic.edit', compact('clinic'));
    }

    public function update(Request $request, Clinic $clinic)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $clinic->update($validatedData);
        return back()->withMessage('Clinic updated successfully')->withMessageType('success');
    }

    public function destroy(Clinic $clinic)
    {
        $clinic->delete();
        return back()->withMessage('Clinic deleted successfully')->withMessageType('success');
    }

    public function getDoctors($clinic_id)
    {
        $clinic = Clinic::findOrFail($clinic_id);
        if (!$clinic) {
            return response()->json(['error' => 'Clinic not found'], 404);
        }

        // Fetch doctors associated with the clinic
        $doctors = $clinic->doctors()->with(['user', 'specialization'])->get();
        return response()->json($doctors);
    }
}
