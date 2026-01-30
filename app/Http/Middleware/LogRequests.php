<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->logRequest($request, $response, $duration);

        return $response;
    }

    protected function logRequest(Request $request, Response $response, float $duration): void
    {
        $context = [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
        ];

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400 && $statusCode < 500) {
            Log::channel('requests')->warning('Client error request', $context);
        } elseif ($statusCode >= 500) {
            Log::channel('requests')->error('Server error request', $context);
        } else {
            Log::channel('requests')->info('Request', $context);
        }
    }
}
