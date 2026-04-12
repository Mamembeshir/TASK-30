<?php

namespace App\Http\Controllers\Api;

use App\Models\UserProfile;
use App\Services\AuditService;
use App\Services\EncryptionService;
use App\Services\IdempotencyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProfileApiController extends Controller
{
    /**
     * PUT /api/profile
     *
     * Save the authenticated user's profile.
     * Sensitive fields (address, ssn_fragment) are encrypted before storage.
     * Blank input for a sensitive field means "leave existing value untouched".
     *
     * Body:
     *   first_name    string  required  max:100
     *   last_name     string  required  max:100
     *   date_of_birth string  optional  date before today
     *   phone         string  optional  max:20
     *   address       string  optional  max:300
     *   ssn_fragment  string  optional  regex:/^\d{4}$/
     *
     * 200 OK  – UserProfile JSON
     * 422     – Validation failure
     */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'date_of_birth'   => ['nullable', 'date', 'before:today'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'address'         => ['nullable', 'string', 'max:300'],
            'ssn_fragment'    => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        /** @var \App\Models\User $user */
        $user    = $request->user();
        $profile = $this->ensureProfile($user);

        $key   = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'profile.save.' . $user->id . '.' . md5(($data['first_name'] ?? '') . '|' . ($data['last_name'] ?? '') . '|' . ($data['date_of_birth'] ?? '') . '|' . ($data['phone'] ?? ''));
        $store = new IdempotencyStore();

        if ($store->alreadyProcessed($key, 'profile.save', $user->id)) {
            return response()->json($profile->fresh());
        }

        $before = $profile->only([
            'first_name', 'last_name', 'date_of_birth', 'phone',
            'address_mask', 'ssn_fragment_mask',
        ]);

        $profile->first_name    = $data['first_name'];
        $profile->last_name     = $data['last_name'];
        $profile->date_of_birth = $data['date_of_birth'] ?? null;
        $profile->phone         = $data['phone'] ?? null;

        $encryption = app(EncryptionService::class);

        if (! empty($data['address'])) {
            $bundle = $encryption->encryptWithMask($data['address'], 'address');
            $profile->address_encrypted = $bundle['encrypted'];
            $profile->address_mask      = $bundle['mask'];
        }

        if (! empty($data['ssn_fragment'])) {
            $bundle = $encryption->encryptWithMask($data['ssn_fragment'], 'ssn');
            $profile->ssn_fragment_encrypted = $bundle['encrypted'];
            $profile->ssn_fragment_mask      = $bundle['mask'];
        }

        $profile->save();

        AuditService::record(
            action:     'user_profile.updated',
            entityType: 'UserProfile',
            entityId:   $profile->user_id,
            before:     $before,
            after:      $profile->only([
                'first_name', 'last_name', 'date_of_birth', 'phone',
                'address_mask', 'ssn_fragment_mask',
            ]),
        );

        $store->record($key, 'profile.save', 'UserProfile', $user->id);

        return response()->json($profile->fresh());
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function ensureProfile(\App\Models\User $user): UserProfile
    {
        return $user->profile ?? UserProfile::create([
            'user_id'    => $user->id,
            'first_name' => '',
            'last_name'  => '',
        ]);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
