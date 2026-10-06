<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Staff;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $clinics = Clinic::query();

            return datatables()->of($clinics)
                ->addIndexColumn()
                ->addColumn('vitals_count', function ($clinic) {
                    return is_array($clinic->vitals_template) ? count($clinic->vitals_template) : 0;
                })
                ->addColumn('actions', function ($clinic) {
                    $showUrl = route('clinics.show', $clinic->id);
                    $editUrl = route('clinics.edit', $clinic->id);

                    return "
                        <div class='btn-group'>
                            <a href='{$showUrl}' class='btn btn-outline-info btn-xs'><i class='fa fa-eye'></i> View</a>
                            <a href='{$editUrl}' class='btn btn-outline-primary btn-xs'><i class='fa fa-edit'></i> Edit</a>
                        </div>
                    ";
                })
                ->rawColumns(['actions'])
                ->make(true);
        }

        return view('admin.clinic.index');
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

        return redirect()->route('clinics.index')->withMessage('Clinic created successfully')->withMessageType('success');
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
            'vitals_template' => 'nullable|array',
        ]);

        if (isset($validatedData['vitals_template'])) {
            $processedTemplate = [];
            foreach ($validatedData['vitals_template'] as $field) {
                if (empty($field['name'])) {
                    continue;
                }

                $processedField = [
                    'name' => $field['name'],
                    'label' => $field['label'] ?? ucwords(str_replace('_', ' ', $field['name'])),
                    'type' => $field['type'] ?? 'text',
                    'required' => isset($field['required']) && ($field['required'] == '1' || $field['required'] == 'on'),
                ];

                if ($processedField['type'] === 'select') {
                    $optionsInput = $field['unit'] ?? ($field['options'] ?? '');
                    if (is_array($optionsInput)) {
                        $processedField['options'] = $optionsInput;
                    } else {
                        $options = array_filter(array_map('trim', explode(',', $optionsInput)));
                        $processedField['options'] = array_values($options);
                    }
                } else {
                    $processedField['unit'] = $field['unit'] ?? null;
                }

                $processedTemplate[] = $processedField;
            }
            $validatedData['vitals_template'] = $processedTemplate;
        }

        $clinic->update($validatedData);

        return redirect()->route('clinics.index')->withMessage('Clinic updated successfully')->withMessageType('success');
    }

    public function destroy(Clinic $clinic)
    {
        $clinic->delete();

        return back()->withMessage('Clinic deleted successfully')->withMessageType('success');
    }

    public function getDoctors($clinic_id)
    {
        $clinic = Clinic::find($clinic_id);
        if (!$clinic) {
            return response()->json(['error' => 'Clinic not found'], 404);
        }

        // Fetch doctors whose primary clinic is this clinic OR who have this clinic in can_see_clinic_queues
        $doctors = Staff::with(['user', 'specialization'])
            ->where(function ($q) use ($clinic_id) {
                $q->where('clinic_id', $clinic_id)
                  ->orWhereJsonContains('can_see_clinic_queues', (int) $clinic_id);
            })
            ->whereHas('user')
            ->get();

        if ($doctors->isEmpty()) {
            $doctors = $clinic->doctors()->with(['user', 'specialization'])->whereHas('user')->get();
        }

        $doctors->transform(function ($staff) {
            $user = $staff->user;
            $nameParts = array_filter([$user?->surname, $user?->firstname, $user?->othername]);
            $fullName = implode(' ', $nameParts);
            $spec = $staff->specialization?->name ? ' (' . $staff->specialization->name . ')' : '';

            $staff->name = $fullName . $spec;
            $staff->display_name = 'Dr. ' . $fullName . $spec;

            return $staff;
        });

        return response()->json($doctors);
    }
}
