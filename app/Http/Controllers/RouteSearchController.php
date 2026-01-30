<?php

namespace App\Http\Controllers;

use App\Models\RouteMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Route Search Controller
 *
 * Handles global search functionality for navigation routes.
 * Returns matching routes based on user's roles and permissions.
 */
class RouteSearchController extends Controller
{
    /**
     * Search routes by query string.
     * Returns JSON for AJAX calls.
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $user = Auth::user();
        $userRoles = $user ? $user->getRoleNames()->toArray() : [];

        // Search routes matching query and user's roles
        $routes = RouteMetadata::where('is_active', true)
            ->search($query)
            ->forRoles($userRoles, $user)
            ->orderBy('sort_order')
            ->limit(30) // Get more than needed to account for permission filtering
            ->get();

        // Filter by permissions (done in PHP for complex permission checks)
        $routes = RouteMetadata::filterByPermissions($routes, $user)
            ->take(15)
            ->map(function ($route) {
                return [
                    'id' => $route->id,
                    'title' => $route->title,
                    'description' => $route->description,
                    'url' => $route->url,
                    'route_name' => $route->route_name,
                    'section' => $route->section,
                    'hierarchy' => $route->hierarchy_path,
                    'icon' => $route->icon,
                ];
            });

        return response()->json($routes->values());
    }

    /**
     * Show the search results page.
     */
    public function index(Request $request)
    {
        $query = $request->get('q', '');
        $user = Auth::user();
        $userRoles = $user ? $user->getRoleNames()->toArray() : [];

        // Default empty paginator
        $routes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect(),
            0,
            20,
            1,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        if (strlen($query) >= 2) {
            // Get all matching routes first
            $allRoutes = RouteMetadata::where('is_active', true)
                ->search($query)
                ->forRoles($userRoles, $user)
                ->orderBy('section')
                ->orderBy('sort_order')
                ->get();

            // Filter by permissions
            $filteredRoutes = RouteMetadata::filterByPermissions($allRoutes, $user);
            $filteredTotal = $filteredRoutes->count();

            // Manual pagination since we're filtering in PHP
            $page = $request->get('page', 1);
            $perPage = 20;
            $offset = ($page - 1) * $perPage;

            $paginatedRoutes = $filteredRoutes->slice($offset, $perPage);

            // Create a LengthAwarePaginator
            $routes = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginatedRoutes,
                $filteredTotal,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('search.index', compact('routes', 'query'));
    }
}
