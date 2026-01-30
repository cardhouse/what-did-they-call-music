<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageProxyController extends Controller
{
    private const ALLOWED_DOMAINS = [
        'static.wikia.nocookie.net',
    ];

    private const USER_AGENT = 'WhatDidTheyCallMusic/1.0 (Educational Project)';

    public function proxy(string $url): Response
    {
        $decodedUrl = base64_decode($url);

        if (!$this->isAllowedUrl($decodedUrl)) {
            Log::warning('Blocked image proxy request to disallowed domain', [
                'ip' => request()->ip(),
                'requested_url' => $decodedUrl,
                'user_agent' => request()->userAgent(),
                'referer' => request()->header('referer'),
            ]);

            abort(403, 'Domain not allowed');
        }

        $cacheKey = 'image_proxy.'.sha1($decodedUrl);

        $imageData = Cache::remember($cacheKey, now()->addWeek(), function () use ($decodedUrl): ?array {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get($decodedUrl);

            if (!$response->successful()) {
                return null;
            }

            return [
                'content' => base64_encode($response->body()),
                'content_type' => $response->header('Content-Type'),
            ];
        });

        if (!$imageData) {
            abort(404, 'Image not found');
        }

        return response(base64_decode($imageData['content']))
            ->header('Content-Type', $imageData['content_type'])
            ->header('Cache-Control', 'public, max-age=604800');
    }

    private function isAllowedUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        foreach (self::ALLOWED_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
