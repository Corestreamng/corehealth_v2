<?php

namespace App\Http\Middleware;

use App\Support\Navigation\WorkspaceRegistry;
use Closure;
use Illuminate\Http\Request;

/**
 * Staff app routes behind a sidebar condition (e.g. `native.condition:hmo_mgmt`, the same test the sidebar uses to show
 * HMOs, Tariffs and Billing Organizations), on top of the section guard.
 */
class EnsureNativeCondition
{
    public function handle(Request $request, Closure $next, string $condition)
    {
        $user = $request->user();
        if (!$user || !WorkspaceRegistry::conditionPasses($user, $condition)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this part of the app.'], 403);
        }

        return $next($request);
    }
}
