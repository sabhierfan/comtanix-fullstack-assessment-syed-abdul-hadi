<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every API request an id, for tracing problems.
 *
 * - If the caller (or a load balancer) sent an X-Request-Id header, we reuse it.
 * - Otherwise we make a new UUID.
 * - Every log line written during this request includes the id ("request_id").
 * - The id is sent back in the X-Request-Id response header.
 *
 * So when a user reports "it failed", the id from their browser's network tab
 * leads straight to the matching log lines.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');

        // Only accept short, safe ids (letters, digits, - and _), so nobody can inject junk into our logs.
        $id = preg_match('/^[A-Za-z0-9_-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();

        $request->headers->set('X-Request-Id', $id);
        Log::withContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
