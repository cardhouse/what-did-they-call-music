<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/*
|--------------------------------------------------------------------------
| HoneypotMiddleware — Deceptive tarpit for automated scanners
|--------------------------------------------------------------------------
|
| What this does
| --------------
| Runs before every other middleware. If the incoming request's path matches
| a configured attack vector (e.g. /wp-login.php, /.env, /adminer.php) or the
| client IP is already blocklisted from a previous hit, the request is:
|
|   1. Delayed by a random 15–45s (the "tarpit") to waste the scanner's
|      worker time and slow horizontal sweeps.
|   2. Answered with a plausible but poisoned fake response so the scanner
|      stores bad data and flags this host as exploitable-but-handled.
|   3. Logged to the `security` channel with IP, path, UA, and category.
|   4. The source IP is added to a cache-backed blocklist for 48 hours so
|      that *any* subsequent request from that IP is also tarpitted.
|
| To keep worker pools safe, a cache-backed atomic counter caps concurrent
| tarpits; requests over the cap still get a fake response but without the
| sleep.
|
| How to add a new attack vector
| ------------------------------
| Edit `config/honeypot.php` — append to the `vectors` array:
|
|     ['pattern' => 'some/new/*path', 'decoy' => WordPressDecoy::class,
|      'category' => 'wordpress', 'variant' => 'login'],
|
| `pattern` uses `Str::is` syntax (no leading slash). `decoy` must implement
| `App\Http\Middleware\Decoys\Decoy`. `variant` is passed through to the
| decoy's `render()` method so one class can serve multiple fake pages.
|
| If you need a brand-new fake response type, drop a class implementing
| `Decoy` into `app/Http/Middleware/Decoys/` and reference it.
|
| Safety notes
| ------------
| - Health checks (`/up`, `/health`, ...) are exempt via `exempt_paths`.
| - Whitelisted crawler UAs (Googlebot, Bingbot, ...) skip the honeypot.
| - Set `honeypot.handle_acme = false` while rotating real certs to let
|   `/.well-known/acme-challenge/*` through.
| - `honeypot.tarpit_enabled = false` removes the sleep entirely (used in
|   local/testing so developer iteration isn't punished).
|
*/

use App\Http\Middleware\Decoys\Decoy;
use App\Http\Middleware\Decoys\WordPressDecoy;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HoneypotMiddleware
{
    private const CONCURRENT_COUNTER_KEY = 'honeypot:concurrent';

    public function __construct(private Container $container) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('honeypot.enabled', true)) {
            return $next($request);
        }

        $path = $request->path();

        if ($this->isExemptPath($path)) {
            return $next($request);
        }

        if ($this->isExemptUserAgent($request->userAgent())) {
            return $next($request);
        }

        $vector = $this->matchVector($path);
        $ip = (string) $request->ip();
        $blocklisted = $ip !== '' && Cache::has($this->blocklistKey($ip));

        if ($vector === null && ! $blocklisted) {
            return $next($request);
        }

        if ($vector !== null) {
            $this->addToBlocklist($ip);
            $this->logHit($request, $vector, blocklistedHit: false);
        } else {
            $this->logBlocklistedHit($request);
        }

        $this->tarpit();

        return $this->renderDecoy($request, $vector);
    }

    private function isExemptPath(string $path): bool
    {
        foreach ((array) config('honeypot.exempt_paths', []) as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function isExemptUserAgent(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        foreach ((array) config('honeypot.exempt_user_agents', []) as $needle) {
            if (stripos($userAgent, (string) $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{pattern:string,decoy:string,category:string,variant:string}|null
     */
    private function matchVector(string $path): ?array
    {
        $handleAcme = (bool) config('honeypot.handle_acme', true);

        foreach ((array) config('honeypot.vectors', []) as $vector) {
            if (! is_array($vector) || ! isset($vector['pattern'], $vector['decoy'], $vector['category'])) {
                continue;
            }

            if (! $handleAcme && ($vector['category'] ?? null) === 'acme') {
                continue;
            }

            if (Str::is($vector['pattern'], $path)) {
                return [
                    'pattern' => (string) $vector['pattern'],
                    'decoy' => (string) $vector['decoy'],
                    'category' => (string) $vector['category'],
                    'variant' => (string) ($vector['variant'] ?? 'default'),
                ];
            }
        }

        return null;
    }

    private function blocklistKey(string $ip): string
    {
        return 'honeypot:blocklist:'.$ip;
    }

    private function addToBlocklist(string $ip): void
    {
        if ($ip === '') {
            return;
        }

        $ttl = (int) config('honeypot.blocklist_ttl_seconds', 48 * 60 * 60);
        Cache::put($this->blocklistKey($ip), true, $ttl);
    }

    private function tarpit(): void
    {
        if (! (bool) config('honeypot.tarpit_enabled', false)) {
            return;
        }

        $cap = (int) config('honeypot.max_concurrent_tarpits', 10);

        try {
            $current = (int) Cache::increment(self::CONCURRENT_COUNTER_KEY);
        } catch (Throwable) {
            $current = 1;
        }

        if ($current > $cap) {
            $this->decrementConcurrent();

            return;
        }

        try {
            $min = (int) config('honeypot.tarpit_min_seconds', 15);
            $max = (int) config('honeypot.tarpit_max_seconds', 45);
            if ($max < $min) {
                $max = $min;
            }
            sleep(random_int($min, $max));
        } finally {
            $this->decrementConcurrent();
        }
    }

    private function decrementConcurrent(): void
    {
        try {
            Cache::decrement(self::CONCURRENT_COUNTER_KEY);
        } catch (Throwable) {
            // counter is advisory only
        }
    }

    /**
     * @param  array{pattern:string,decoy:string,category:string,variant:string}|null  $vector
     */
    private function renderDecoy(Request $request, ?array $vector): Response
    {
        $decoyClass = $vector['decoy'] ?? WordPressDecoy::class;
        $variant = $vector['variant'] ?? 'login';

        /** @var Decoy $decoy */
        $decoy = $this->container->make($decoyClass);

        return $decoy->render($request, $variant);
    }

    /**
     * @param  array{pattern:string,decoy:string,category:string,variant:string}  $vector
     */
    private function logHit(Request $request, array $vector, bool $blocklistedHit): void
    {
        Log::channel('security')->warning('Honeypot triggered', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
            'category' => $vector['category'],
            'variant' => $vector['variant'],
            'pattern' => $vector['pattern'],
            'blocklisted' => $blocklistedHit,
        ]);
    }

    private function logBlocklistedHit(Request $request): void
    {
        Log::channel('security')->info('Blocklisted IP request tarpitted', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);
    }
}
