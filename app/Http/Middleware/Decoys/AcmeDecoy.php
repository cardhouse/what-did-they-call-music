<?php

declare(strict_types=1);

namespace App\Http\Middleware\Decoys;

use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

class AcmeDecoy implements Decoy
{
    public function render(Request $request, string $variant): Response
    {
        $token = $this->urlSafeBase64(random_bytes(32));
        $thumbprint = $this->urlSafeBase64(random_bytes(32));

        return new IlluminateResponse(
            $token.'.'.$thumbprint,
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    private function urlSafeBase64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
