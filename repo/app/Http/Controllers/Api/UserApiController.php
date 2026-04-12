<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IdempotencyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserApiController extends Controller
{
    /**
     * POST /api/admin/users/{user}/transition
     *
     * Transition a user's status (Admin only).
     *
     * Body:
     *   status  string  required  (UserStatus enum value)
     *
     * 200 OK  – User JSON
     * 422     – Invalid status transition
     */
    public function transitionTo(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'status'          => ['required', 'string', 'in:' . implode(',', array_column(UserStatus::cases(), 'value'))],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $newStatus = UserStatus::from($data['status']);

        $key   = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'user.transition.' . $user->id . '.' . $newStatus->value;
        $store = new IdempotencyStore();

        if ($store->alreadyProcessed($key, 'user.transitionTo', $user->id)) {
            return response()->json($user->fresh());
        }

        try {
            $user->transitionStatus($newStatus);
        } catch (InvalidStatusTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $store->record($key, 'user.transitionTo', 'User', $user->id);

        return response()->json($user->fresh());
    }

    /**
     * POST /api/admin/users/{user}/unlock
     *
     * Unlock a locked user account (Admin only).
     *
     * 200 OK  – User JSON
     */
    public function unlock(Request $request, User $user): JsonResponse
    {
        $key   = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'user.unlock.' . $user->id;
        $store = new IdempotencyStore();

        if ($store->alreadyProcessed($key, 'user.unlock', $user->id)) {
            return response()->json($user->fresh());
        }

        $user->forceFill([
            'failed_login_count' => 0,
            'locked_until'       => null,
        ])->save();

        AuditService::record('user.unlocked', 'User', $user->id, null, null);

        $store->record($key, 'user.unlock', 'User', $user->id);

        return response()->json($user->fresh());
    }

    /**
     * PUT /api/admin/users/{user}/roles
     *
     * Update the roles assigned to a user (Admin only).
     *
     * Body:
     *   roles  array  required  (array of UserRole values)
     *
     * 200 OK  – User JSON (with roles loaded)
     * 422     – Invalid role values
     */
    public function saveRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'roles'          => ['present', 'array'],
            'roles.*'        => ['string', 'in:' . implode(',', array_column(UserRole::cases(), 'value'))],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $sortedRoles = $data['roles'];
        sort($sortedRoles);
        $key   = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'user.roles.' . $user->id . '.' . md5(implode(',', $sortedRoles));
        $store = new IdempotencyStore();

        if ($store->alreadyProcessed($key, 'user.saveRoles', $user->id)) {
            $user->load('roles');
            return response()->json($user->fresh());
        }

        $desiredRoles = array_map(
            fn ($v) => UserRole::from($v),
            $data['roles'],
        );

        foreach (UserRole::cases() as $role) {
            $shouldHave = in_array($role, $desiredRoles, true);
            $hasNow     = $user->hasRole($role);

            if ($shouldHave && ! $hasNow) {
                $user->addRole($role);
            } elseif (! $shouldHave && $hasNow) {
                $user->removeRole($role);
            }
        }

        $store->record($key, 'user.saveRoles', 'User', $user->id);

        $user->load('roles');

        return response()->json($user->fresh());
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
