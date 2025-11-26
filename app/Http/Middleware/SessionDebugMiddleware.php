<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SessionDebugMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Log session info for OAuth routes
        if (str_contains($request->path(), 'auth/')) {
            Log::info('Session Debug', [
                'path' => $request->path(),
                'method' => $request->method(),
                'session_id' => session()->getId(),
                'session_started' => session()->isStarted(),
                'session_data' => [
                    'oauth_user_id' => session('oauth_user_id'),
                    'oauth_platform' => session('oauth_platform'),
                    'oauth_auth_token' => session('oauth_auth_token') ? 'present' : 'missing',
                    'linkedin_oauth_state' => session('linkedin_oauth_state') ? 'present' : 'missing',
                ],
                'cookies' => $request->cookies->all(),
            ]);
        }

        return $next($request);
    }
}