<?php

namespace App\Http\Controllers;

use App\Models\HospitalContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class HospitalContactController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = HospitalContact::with('creator')->orderBy('name', 'asc');

            if ($request->filled('filter_mine') && $request->filter_mine == '1') {
                $query->where('created_by', Auth::id());
            }

            if ($request->filled('filter_role')) {
                $role = $request->filter_role;
                $query->whereHas('creator', function($q) use($role) {
                    $q->role($role);
                });
            }

            if ($request->filled('filter_department')) {
                $deptId = $request->filter_department;
                $query->whereHas('creator', function($q) use($deptId) {
                    $q->whereHas('staff', function($sq) use($deptId) {
                        $sq->where('department_id', $deptId);
                    });
                });
            }

            $contacts = $query->get();
            return DataTables::of($contacts)
                ->addColumn('action', function ($contact) {
                    $user = Auth::user();
                    $isAdmin = in_array($user->is_admin, [1, 2, 3]);
                    $isCreator = $user->id === $contact->created_by;

                    $btn = '';
                    if ($isAdmin || $isCreator) {
                        $btn .= '<button type="button" class="btn btn-sm btn-primary edit-contact" data-id="' . $contact->id . '"><i class="mdi mdi-pencil"></i> Edit</button>';
                        $btn .= ' <button type="button" class="btn btn-sm btn-danger delete-contact" data-id="' . $contact->id . '"><i class="mdi mdi-delete"></i></button>';
                    }
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return abort(404);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
        ]);

        $contact = HospitalContact::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'description' => $request->description,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Contact created successfully', 'data' => $contact]);
    }

    public function show($id)
    {
        $contact = HospitalContact::findOrFail($id);
        return response()->json(['success' => true, 'data' => $contact]);
    }

    public function update(Request $request, $id)
    {
        $contact = HospitalContact::findOrFail($id);
        $user = Auth::user();
        
        $isAdmin = in_array($user->is_admin, [1, 2, 3]);
        $isCreator = $user->id === $contact->created_by;

        if (!$isAdmin && !$isCreator) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to edit this contact'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
        ]);

        $contact->update($request->only('name', 'phone', 'email', 'description'));

        return response()->json(['success' => true, 'message' => 'Contact updated successfully', 'data' => $contact]);
    }

    public function destroy($id)
    {
        $contact = HospitalContact::findOrFail($id);
        $user = Auth::user();
        
        $isAdmin = in_array($user->is_admin, [1, 2, 3]);
        $isCreator = $user->id === $contact->created_by;

        if (!$isAdmin && !$isCreator) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to delete this contact'], 403);
        }

        $contact->delete();

        return response()->json(['success' => true, 'message' => 'Contact deleted successfully']);
    }
}
