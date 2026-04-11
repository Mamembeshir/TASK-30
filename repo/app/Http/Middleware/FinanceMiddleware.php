<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard for finance areas.
 *
 * Applies to all /finance/* and /finance/refunds routes so that
 * role enforcement is not dependent on each Livewire component
 * correctly implementing its own mount() gate. Component-level
 * checks remain as defense-in-depth.
 */
class FinanceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (
            ! $user ||
            (! $user->hasRole(UserRole::FINANCE_SPECIALIST) && ! $user->isAdmin())
        ) {
            abort(403, 'Access restricted to Finance Specialists and Administrators.');
        }

        return $next($request);
    }
}
