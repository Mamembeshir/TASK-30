<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyRecord;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only check idempotency on state-changing methods
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $key = $request->header('X-Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $endpoint = $request->route()?->getName() ?? $request->path();

        // Check for existing valid record
        $existing = IdempotencyRecord::findValid($key, $endpoint);

        if ($existing) {
            return response()->json(
                $existing->response_body,
                $existing->response_status,
                ['X-Idempotency-Key' => $key, 'X-Idempotency-Replayed' => 'true']
            );
        }

        // Process request and capture response
        $response = $next($request);

        // Only cache successful responses
        if ($response->getStatusCode() < 500) {
            $body = json_decode($response->getContent(), true) ?? [];
            IdempotencyRecord::store($key, $endpoint, $response->getStatusCode(), $body);
        }

        return $response;
    }
}
