<?php

namespace App\Http\Controllers;

use App\Models\DiagnosisFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiagnosisFavoriteController extends Controller
{
    /**
     * List all favorites for the authenticated doctor.
     */
    public function index()
    {
        $favorites = DiagnosisFavorite::where('doctor_id', Auth::id())
            ->orderBy('name')
            ->get();

        return response()->json($favorites);
    }

    /**
     * Save a new diagnosis favorite set.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'diagnoses' => 'required|array|min:1',
            'diagnoses.*.code' => 'required|string',
            'diagnoses.*.name' => 'required|string',
            'diagnoses.*.comment_1' => 'nullable|string',
            'diagnoses.*.comment_2' => 'nullable|string',
        ]);

        $favorite = DiagnosisFavorite::create([
            'doctor_id' => Auth::id(),
            'name' => $request->name,
            'diagnoses' => $request->diagnoses,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Diagnosis favorite saved successfully',
            'favorite' => $favorite,
        ], 201);
    }

    /**
     * Delete a diagnosis favorite.
     */
    public function destroy($id)
    {
        $favorite = DiagnosisFavorite::where('id', $id)
            ->where('doctor_id', Auth::id())
            ->firstOrFail();

        $favorite->delete();

        return response()->json([
            'success' => true,
            'message' => 'Favorite deleted successfully',
        ]);
    }
}
