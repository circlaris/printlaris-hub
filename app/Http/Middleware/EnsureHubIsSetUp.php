<?php

namespace App\Http\Middleware;

use App\Services\HubCredentials;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Until a key and admin password exist, the setup page is the only reachable page. */
class EnsureHubIsSetUp
{
    public function __construct(protected HubCredentials $credentials) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->credentials->isConfigured()) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
