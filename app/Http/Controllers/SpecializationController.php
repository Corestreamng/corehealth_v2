<?php

namespace App\Http\Controllers;

use App\Models\Specialization;
use Illuminate\Http\Request;

class SpecializationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Retrieve all specializations and return them as a JSON response
        $specializations = Specialization::all();
        return view('admin.specialization.index', ['specializations' => $specializations]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // This is for rendering a form to create a new specialization (if needed for frontend)
        return view('admin.specialization.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Create a new specialization
        $specialization = Specialization::create($validatedData);

        // Return a response
        return back()->withMessage('Specialization created successfully')->withMessageType('success');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Specialization  $specialization
     * @return \Illuminate\Http\Response
     */
    public function show(Specialization $specialization)
    {
        return view('admin.specialization.show', ['specialization' => $specialization]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Specialization  $specialization
     * @return \Illuminate\Http\Response
     */
    public function edit(Specialization $specialization)
    {
        // Render a form for editing the specialization (if needed for frontend)
        return view('admin.specialization.edit', compact('specialization'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Specialization  $specialization
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Specialization $specialization)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Update the specialization with the validated data
        $specialization->update($validatedData);

        // Return a response
        return back()->withMessage('Specialization updated successfully')->withMessageType('success');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Specialization  $specialization
     * @return \Illuminate\Http\Response
     */
    public function destroy(Specialization $specialization)
    {
        // Delete the specialization
        $specialization->delete();

        // Return a response
        return back()->withMessage('Specialization deleted successfully')->withMessageType('success');
    }
}
