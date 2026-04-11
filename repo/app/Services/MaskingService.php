<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Role-aware masking service.
 *
 * Rules:
 *  - Admin: sees unmasked value + access is audit-logged.
 *  - Member viewing own profile: sees masked value (never plain).
 *  - Other roles / other users: see masked value.
 *
 * Usage:
 *   $masking->get($profile, 'address', $viewerUser);
 */
class MaskingService
{
    public function __construct(
        private readonly EncryptionService $encryption,
        private readonly AuditService $audit,
    ) {}

    /**
     * Get a sensitive field value, masked or decrypted based on viewer role.
     *
     * @param object      $entity        The model that owns the field
     * @param string      $field         Base field name (e.g. "address" → reads address_encrypted / address_mask)
     * @param User|null   $viewer        The user requesting the data
     * @param bool        $logAccess     Whether admin access should be audit-logged
     */
    public function get(object $entity, string $field, ?User $viewer = null, bool $logAccess = true): ?string
    {
        $encryptedField = $field . '_encrypted';
        $maskField      = $field . '_mask';

        $encrypted = $entity->{$encryptedField} ?? null;
        $mask      = $entity->{$maskField} ?? null;

        if (! $viewer) {
            return $mask;
        }

        if ($viewer->isAdmin()) {
            $plaintext = $this->encryption->decrypt($encrypted);

            if ($logAccess && $plaintext !== null) {
                AuditService::record(
                    action:     'sensitive_field.accessed',
                    entityType: class_basename($entity),
                    entityId:   $entity->id ?? null,
                    after:      ['field' => $field, 'viewer_id' => $viewer->id],
                );
            }

            return $plaintext;
        }

        return $mask;
    }

    /**
     * Prepare an array of masked fields for display by the given viewer.
     */
    public function maskForDisplay(object $entity, array $fields, ?User $viewer = null): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $this->get($entity, $field, $viewer);
        }
        return $result;
    }
}
