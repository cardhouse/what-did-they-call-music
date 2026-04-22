<?php

use App\Http\Middleware\HoneypotMiddleware;
use App\Http\Middleware\LogRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HoneypotMiddleware::class);
        $middleware->append(LogRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $e) {
            $statusCode = $e->getStatusCode();

            if ($statusCode === 403 || $statusCode === 401) {
                Log::warning('Unauthorized access attempt', [
                    'ip' => request()->ip(),
                    'method' => request()->method(),
                    'url' => request()->fullUrl(),
                    'path' => request()->path(),
                    'status' => $statusCode,
                    'message' => $e->getMessage(),
                    'user_agent' => request()->userAgent(),
                    'referer' => request()->header('referer'),
                ]);
            }

            return null;
        });
    })->create();
