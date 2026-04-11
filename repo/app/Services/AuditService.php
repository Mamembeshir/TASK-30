<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Record an audit log entry.
     *
     * @param string      $action        Verb describing what happened (e.g. "trip.created", "payment.confirmed")
     * @param string      $entityType    Model class short name or table name
     * @param string|null $entityId      UUID of the affected entity
     * @param mixed       $before        State before the change (array or null)
     * @param mixed       $after         State after the change (array or null)
     * @param string|null $correlationId UUID grouping related audit entries
     */
    public static function record(
        string  $action,
        string  $entityType,
        ?string $entityId     = null,
        mixed   $before       = null,
        mixed   $after        = null,
        ?string $correlationId = null,
    ): AuditLog {
        return AuditLog::record(
            actorId:        auth()->id(),
            action:         $action,
            entityType:     $entityType,
            entityId:       $entityId,
            before:         $before,
            after:          $after,
            ipAddress:      request()?->ip(),
            idempotencyKey: request()?->header('X-Idempotency-Key'),
            correlationId:  $correlationId ?? $entityId,
        );
    }

    /**
     * Convenience: record a model create event.
     */
    public static function created(Model $model, ?string $correlationId = null): AuditLog
    {
        return static::record(
            action:        strtolower(class_basename($model)) . '.created',
            entityType:    class_basename($model),
            entityId:      $model->getKey(),
            before:        null,
            after:         $model->toArray(),
            correlationId: $correlationId,
        );
    }

    /**
     * Convenience: record a model update event.
     */
    public static function updated(Model $model, array $before, ?string $correlationId = null): AuditLog
    {
        return static::record(
            action:        strtolower(class_basename($model)) . '.updated',
            entityType:    class_basename($model),
            entityId:      $model->getKey(),
            before:        $before,
            after:         $model->toArray(),
            correlationId: $correlationId,
        );
    }

    /**
     * Convenience: record a status transition event.
     */
    public static function transitioned(
        Model   $model,
        string  $from,
        string  $to,
        ?string $correlationId = null
    ): AuditLog {
        return static::record(
            action:        strtolower(class_basename($model)) . '.status_changed',
            entityType:    class_basename($model),
            entityId:      $model->getKey(),
            before:        ['status' => $from],
            after:         ['status' => $to],
            correlationId: $correlationId,
        );
    }

    /**
     * Generate a new correlation ID for multi-step operations.
     */
    public static function newCorrelationId(): string
    {
        return (string) Str::uuid();
    }
}
