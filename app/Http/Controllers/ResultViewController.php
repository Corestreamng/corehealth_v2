<?php

namespace App\Http\Controllers;

use App\Models\ResultView;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ResultViewController
 *
 * Tracks and retrieves view/print events for lab and imaging results.
 * Supports unviewed-count badges on clinical workbench tabs.
 */
class ResultViewController extends Controller
{
    /**
     * Map short type names to model classes.
     */
    private const TYPE_MAP = [
        'lab'     => LabServiceRequest::class,
        'imaging' => ImagingServiceRequest::class,
    ];

    /**
     * POST /result-views
     *
     * Record a view or print event for a lab/imaging result.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'viewable_type' => 'required|in:lab,imaging',
                'viewable_id'   => 'required|integer',
                'view_type'     => 'required|in:modal,print',
            ]);

            $modelClass = self::TYPE_MAP[$request->viewable_type];

            // Verify the result exists and has a result (status >= 4)
            $result = $modelClass::find($request->viewable_id);
            if (!$result) {
                return response()->json(['success' => false, 'message' => 'Result not found'], 404);
            }

            // Record the view
            ResultView::create([
                'viewable_type' => $modelClass,
                'viewable_id'   => $request->viewable_id,
                'user_id'       => Auth::id(),
                'view_type'     => $request->view_type,
                'ip_address'    => $request->ip(),
            ]);

            $viewCount = ResultView::where('viewable_type', $modelClass)
                ->where('viewable_id', $request->viewable_id)
                ->distinct('user_id')
                ->count('user_id');

            return response()->json([
                'success'    => true,
                'view_count' => $viewCount,
            ]);
        } catch (\Exception $e) {
            Log::error('ResultView store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Error recording view'], 500);
        }
    }

    /**
     * GET /result-views/{type}/{id}
     *
     * Get the view history for a specific result.
     */
    public function show($type, $id)
    {
        try {
            if (!isset(self::TYPE_MAP[$type])) {
                return response()->json(['success' => false, 'message' => 'Invalid type'], 400);
            }

            $modelClass = self::TYPE_MAP[$type];

            $views = ResultView::where('viewable_type', $modelClass)
                ->where('viewable_id', $id)
                ->with('user:id,surname,firstname,othername')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($view) {
                    $user = $view->user;
                    return [
                        'user_name'  => $user ? trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '')) : 'Unknown',
                        'view_type'  => $view->view_type,
                        'viewed_at'  => $view->created_at->format('h:i a D M j, Y'),
                    ];
                });

            // Unique viewer count
            $uniqueViewers = ResultView::where('viewable_type', $modelClass)
                ->where('viewable_id', $id)
                ->distinct('user_id')
                ->count('user_id');

            return response()->json([
                'success' => true,
                'views'   => $views,
                'count'   => $uniqueViewers,
            ]);
        } catch (\Exception $e) {
            Log::error('ResultView show error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Error fetching views'], 500);
        }
    }

    /**
     * GET /result-views/unviewed-counts/{patient_id}
     *
     * Returns the count of lab and imaging results that the current user
     * has NOT yet viewed for the given patient. Only counts completed results
     * (status >= 4) that have a non-null result.
     */
    public function unviewedCounts($patient_id)
    {
        try {
            $userId = Auth::id();

            // Lab results with a result that I haven't viewed
            $labUnviewed = LabServiceRequest::where('patient_id', $patient_id)
                ->where('status', '>=', 4)
                ->whereNotNull('result')
                ->whereDoesntHave('resultViews', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->count();

            // Imaging results with a result that I haven't viewed
            $imagingUnviewed = ImagingServiceRequest::where('patient_id', $patient_id)
                ->where('status', '>=', 4)
                ->whereNotNull('result')
                ->whereDoesntHave('resultViews', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->count();

            return response()->json([
                'success'          => true,
                'lab_unviewed'     => $labUnviewed,
                'imaging_unviewed' => $imagingUnviewed,
            ]);
        } catch (\Exception $e) {
            Log::error('ResultView unviewedCounts error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success'          => false,
                'lab_unviewed'     => 0,
                'imaging_unviewed' => 0,
            ]);
        }
    }
}
