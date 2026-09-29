<?php

namespace App\Http\Middleware;

use App\Services\HubCredentials;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The setup page must not be usable to overwrite an existing key or password. */
class EnsureHubIsNotSetUp
{
    public function __construct(protected HubCredentials $credentials) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->credentials->isConfigured()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
