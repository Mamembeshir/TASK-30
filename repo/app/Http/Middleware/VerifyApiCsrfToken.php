<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF verification for session-authenticated /api/* mutation routes.
 *
 * Security model
 * ──────────────
 * This API is exclusively JSON-over-HTTP.  Cross-origin CSRF attacks rely on
 * the browser submitting a form or simple-request body (application/x-www-
 * form-urlencoded, multipart/form-data, text/plain) with session cookies.
 * Those content types cannot carry JSON payloads and therefore cannot satisfy
 * our route validation rules — the attack can't get past the first `validate()`
 * call.  More fundamentally, a `Content-Type: application/json` body triggers
 * a CORS preflight for cross-origin requests; the same-origin policy blocks
 * the preflight unless our CORS configuration explicitly permits it.
 *
 * For requests that supply a JSON Content-Type header we therefore pass
 * through without requiring a CSRF token (the header itself is the proof of
 * browser cooperation).  For any non-JSON POST/PATCH/PUT/DELETE we fall back
 * to the classical token check (_token field or X-CSRF-TOKEN header) so that
 * future non-JSON callers are still protected.
 *
 * This two-tier approach is the same pattern used by Laravel Sanctum's
 * EnsureFrontendRequestsAreStateful middleware and by many production JSON
 * APIs.
 */
class VerifyApiCsrfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isReadRequest($request) || $this->isJsonRequest($request) || $this->tokensMatch($request)) {
            return $next($request);
        }

        throw new TokenMismatchException('CSRF token mismatch.');
    }

    private function isReadRequest(Request $request): bool
    {
        return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS'], true);
    }

    /**
     * JSON mutation requests are protected by the same-origin policy + CORS
     * preflight rather than a synchronizer token.  A cross-origin attacker
     * cannot set Content-Type: application/json on a simple-request and the
     * browser will not auto-submit JSON bodies via forms.
     */
    private function isJsonRequest(Request $request): bool
    {
        return $request->isJson();
    }

    private function tokensMatch(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $sessionToken = $request->session()->token();
        $requestToken = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');

        return is_string($sessionToken)
            && is_string($requestToken)
            && hash_equals($sessionToken, $requestToken);
    }
}
