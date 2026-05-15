<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\QueryContext;
use Illuminate\Support\Facades\DB;
use App\Database\CommenterMySqlGrammar;

class DatabaseQueryTagger
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        // 1. Capture the current action
        QueryContext::$currentAction = $request->route() ? $request->route()->getActionName() : 'Web/Unknown';

        // 2. Apply the custom grammar to all MySQL connections
        $this->applyGrammar();

        return $next($request);
    }

    /**
     * Apply the custom grammar to all active MySQL connections.
     */
    private function applyGrammar()
    {
        $grammar = new CommenterMySqlGrammar();
        
        foreach (config('database.connections') as $name => $config) {
            if (isset($config['driver']) && $config['driver'] === 'mysql') {
                DB::connection($name)->setQueryGrammar($grammar);
            }
        }
    }
}
