<?php

namespace App\Http\Controllers;

use App\Models\V1ResultTemplate;
use Illuminate\Http\Request;

class V1ResultTemplateController extends Controller
{
    /**
     * Get all active V1 result templates grouped by category.
     * Used by the result entry modal to populate the template selector.
     */
    public function getTemplates(Request $request)
    {
        $templates = V1ResultTemplate::active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'content', 'category']);

        $grouped = $templates->groupBy('category')->map(function ($items, $category) {
            return [
                'category' => $category,
                'templates' => $items->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'description' => $t->description,
                        'content' => $t->content,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'groups' => $grouped,
        ]);
    }
}
