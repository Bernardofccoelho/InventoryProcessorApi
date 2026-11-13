<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogIncomingApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('Incoming API request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_body' => $request->getContent(),
        ]);

        return $next($request);
    }
}
