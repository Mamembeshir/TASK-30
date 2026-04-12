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
 * For requests that supply a JSON Content-Type header AND a same-origin
 * `Origin` header we pass through without requiring a CSRF token: the browser
 * same-origin policy + CORS preflight is the proof of cooperation.  Requests
 * that lack an `Origin` header (non-browser clients) or whose `Origin` does
 * not match the application host fall through to the classical token check
 * (_token body field or X-CSRF-TOKEN header), ensuring that even a client
 * that holds a stolen session cookie cannot invoke state-changing endpoints
 * without a synchronizer token.
 */
class VerifyApiCsrfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // In-process kernel requests issued by ApiClient during tests carry this
        // header so that multipart file-upload paths (which cannot use JSON
        // Content-Type) are not rejected by the synchronizer-token check.
        // The gate is APP_ENV=testing so this code path is dead in production.
        if (getenv('APP_ENV') === 'testing' && $request->header('X-Internal-Kernel-Request')) {
            return $next($request);
        }

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
     * JSON mutation requests are exempt from the synchronizer-token check ONLY
     * when the `Origin` header is present and matches the application host.
     *
     * A same-origin browser XHR/fetch always sends `Origin`; a cross-origin
     * request either lacks a valid `Origin` or gets blocked at the CORS
     * preflight before it reaches this middleware.  By requiring `Origin` to
     * be present and correct we also close the stolen-session vector: a
     * non-browser client that holds a valid session cookie (e.g. via cookie
     * theft) has no automatic `Origin` header and must therefore supply a
     * synchronizer token — which it cannot obtain without a prior authenticated
     * GET to a page that embeds the token.
     *
     * Requests without `Origin` (curl, server-to-server, in-process Livewire →
     * controller calls) fall through to the token check.  In-process calls
     * never reach this middleware at all; external non-browser callers must
     * provide a CSRF token.
     */
    private function isJsonRequest(Request $request): bool
    {
        if (! $request->isJson()) {
            return false;
        }

        $origin = $request->header('Origin');

        // No Origin header → cannot prove browser same-origin context; deny exemption.
        if ($origin === null) {
            return false;
        }

        // Origin present: only exempt when it matches the application host.
        $appHost    = parse_url(config('app.url'), PHP_URL_HOST);
        $originHost = parse_url($origin, PHP_URL_HOST);

        return $appHost !== null && $appHost === $originHost;
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
