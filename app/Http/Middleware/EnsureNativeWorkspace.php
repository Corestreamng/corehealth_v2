<?php

namespace App\Http\Middleware;

use App\Support\Navigation\WorkspaceRegistry;
use Closure;
use Illuminate\Http\Request;

/**
 * Applies the web sidebar's guard for a workspace to native-client API routes.
 *
 * Usage: ->middleware('native.workspace:reception') or several keys separated by "|"
 * ('native.workspace:doctor|nursing') when a route serves more than one workspace;
 * the request passes if the user may use any of them. Only active staff accounts pass;
 * 'native.workspace:global' therefore means "any staff member".
 */
class EnsureNativeWorkspace
{
    public function handle(Request $request, Closure $next, string $workspaces)
    {
        $user = $request->user();
        if (!\App\Http\Controllers\API\MobileAuthController::isActiveStaff($user)) {
            return response()->json(['status' => false, 'message' => 'Staff access only.'], 403);
        }
        foreach (explode('|', $workspaces) as $key) {
            if (WorkspaceRegistry::allowsSection($user, trim($key))) {
                return $next($request);
            }
        }

        return response()->json([
            'status' => false,
            'message' => 'You do not have access to this workspace.',
        ], 403);
    }
}
