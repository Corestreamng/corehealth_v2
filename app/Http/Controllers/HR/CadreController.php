<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Cadre;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class CadreController extends Controller
{
    public function index()
    {
        $cadres = Cadre::withCount('staff')->ordered()->get();
        return view('admin.hr.cadres.index', compact('cadres'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:cadres,name',
            'code' => 'nullable|string|max:20|unique:cadres,code',
            'description' => 'nullable|string',
        ]);

        Cadre::create($request->only(['name', 'code', 'description', 'is_active']));
        Alert::success('Success', 'Cadre created successfully.');
        return redirect()->route('hr.cadres.index');
    }

    public function update(Request $request, Cadre $cadre)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:cadres,name,' . $cadre->id,
            'code' => 'nullable|string|max:20|unique:cadres,code,' . $cadre->id,
        ]);

        $cadre->update($request->only(['name', 'code', 'description', 'is_active']));
        Alert::success('Success', 'Cadre updated successfully.');
        return redirect()->route('hr.cadres.index');
    }

    public function destroy(Cadre $cadre)
    {
        if ($cadre->staff()->count() > 0) {
            Alert::error('Error', 'Cannot delete cadre with assigned staff.');
            return redirect()->route('hr.cadres.index');
        }
        $cadre->delete();
        Alert::success('Success', 'Cadre deleted.');
        return redirect()->route('hr.cadres.index');
    }
}
