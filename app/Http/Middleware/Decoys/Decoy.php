<?php

declare(strict_types=1);

namespace App\Http\Middleware\Decoys;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface Decoy
{
    /**
     * Build a fake (but plausible and poisoned) response for the given request.
     *
     * Implementations must:
     *  - return HTTP 200
     *  - set an appropriate Content-Type
     *  - vary output per call so static fingerprinting fails
     *  - never include real secrets or real internal data
     */
    public function render(Request $request, string $variant): Response;
}
