<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;
use Illuminate\Support\Facades\Log;

class JsonExceptionHandler
{
    /**
     * Handle an incoming request and ensure exceptions are returned as JSON if requested.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            // Log the error using the structured Monolog channel
            Log::channel('json')->error('API Exception', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'inputs' => $request->except(['password', 'password_confirmation'])
            ]);

            if ($request->expectsJson() || $request->is('api/*')) {
                $statusCode = $this->getStatusCode($e);
                
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => $this->getErrorMessage($e, $statusCode),
                        'code' => $statusCode,
                        'type' => class_basename($e)
                    ]
                ], $statusCode);
            }

            throw $e;
        }
    }

    /**
     * Determine the appropriate HTTP status code for the exception.
     *
     * @param Throwable $e
     * @return int
     */
    protected function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return 401;
        }

        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return 422;
        }
        
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return 404;
        }

        return 500;
    }

    /**
     * Determine the appropriate error message to return.
     * Hides actual exception messages for 500 errors in production.
     *
     * @param Throwable $e
     * @param int $statusCode
     * @return string
     */
    protected function getErrorMessage(Throwable $e, int $statusCode): string
    {
        if (app()->environment('production') && $statusCode === 500) {
            return 'Server Error. An unexpected error occurred.';
        }

        return $e->getMessage() ?: 'An error occurred while processing the request.';
    }
}
