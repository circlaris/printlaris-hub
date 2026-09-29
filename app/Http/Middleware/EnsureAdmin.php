<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public const SESSION_KEY = 'printlaris.admin';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get(self::SESSION_KEY) !== true) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
