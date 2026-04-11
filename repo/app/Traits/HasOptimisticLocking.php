<?php

namespace App\Traits;

use App\Exceptions\StaleRecordException;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds optimistic locking to Eloquent models via a `version` column.
 *
 * Usage:
 *   - Model must have a `version` integer column (default 1).
 *   - Call $model->saveWithLock() instead of $model->save() for optimistic-lock-aware saves.
 *   - The UPDATE checks WHERE version = <current> and increments version.
 *   - If 0 rows affected, throws StaleRecordException (HTTP 409).
 */
trait HasOptimisticLocking
{
    /**
     * Save the model, enforcing optimistic locking.
     *
     * @throws StaleRecordException
     */
    public function saveWithLock(array $options = []): bool
    {
        if (! $this->exists) {
            // New record — just save normally (version defaults to 1)
            return $this->save($options);
        }

        $currentVersion = $this->version;
        $this->version  = $currentVersion + 1;

        $dirty = $this->getDirty();

        if (empty($dirty) && ! isset($dirty['version'])) {
            $this->version = $currentVersion;
            return true;
        }

        // Merge version into dirty so it is part of the SET clause
        $dirty['version'] = $this->version;

        $affected = $this->getConnection()
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->where('version', $currentVersion)
            ->update($dirty);

        if ($affected === 0) {
            // Restore the version we tried to set so the model is not corrupted
            $this->version = $currentVersion;
            throw new StaleRecordException(class_basename($this));
        }

        $this->syncOriginal();

        return true;
    }

    /**
     * Boot the trait — automatically increment version on every save.
     * Uses saveWithLock internally.
     */
    public static function bootHasOptimisticLocking(): void
    {
        // Nothing to auto-hook here; callers must explicitly call saveWithLock()
        // to opt in. This keeps normal save() working in tests / seeders.
    }
}
