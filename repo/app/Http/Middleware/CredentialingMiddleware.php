<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard for credentialing reviewer/admin areas.
 *
 * Applies to /credentialing/cases* routes so that access to the
 * case list and detail pages is enforced at the routing layer,
 * not solely inside each component's mount() method.
 * Component-level checks remain as defense-in-depth.
 *
 * Note: /credentialing/profile is intentionally excluded — doctors
 * use that route and are governed by their own component-level gate.
 */
class CredentialingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (
            ! $user ||
            (! $user->hasRole(UserRole::CREDENTIALING_REVIEWER) && ! $user->isAdmin())
        ) {
            abort(403, 'Access restricted to Credentialing Reviewers and Administrators.');
        }

        return $next($request);
    }
}
